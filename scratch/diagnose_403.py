import paramiko
import sys

hostname = 'inklit.ch'
username = 'root'
password = 'REDACTED_PASSWORD'

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

try:
    print(f"Connecting to {hostname}...")
    client.connect(hostname, username=username, password=password, timeout=15)
    print("Connected.")
    
    cmd = "docker ps && echo '--- PHP LOGS ---' && docker logs --tail 20 imagehutchdocs-php-1 && echo '--- NGINX LOGS ---' && tail -n 20 /var/log/nginx/error.log"
    
    print(f"Running command...")
    stdin, stdout, stderr = client.exec_command(cmd, timeout=30)
    
    out = stdout.read().decode('utf-8')
    err = stderr.read().decode('utf-8')
    
    print("--- STDOUT ---")
    print(out)
    if err:
        print("--- STDERR ---")
        print(err)
        
except Exception as e:
    print(f"Failed: {e}")
finally:
    client.close()

