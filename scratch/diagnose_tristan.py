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
    
    # Query to compare Tristan (user_id=8) vs a working user (null or ihadmin)
    query = """SELECT * FROM chv_users WHERE user_username IN ('tristan','null','ihadmin')\\G"""
    cmd = f'docker exec imagehutchdocs-database-1 mysql -u chevereto -pchevereto_secure_password_456 chevereto -e "{query}"'
    
    print(f"Running query...")
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

