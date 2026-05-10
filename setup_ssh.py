import paramiko
import sys

hostname = 'inklit.ch'
username = 'root'
password = 'REDACTED_PASSWORD'
public_key = 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAACAQCcUKRbT8AGwang5DOriP61ElKTNE8x0GVQ8ab72s1tfDPcC4AYKOtvUGx1uXQ6PmUttR7JAva7D/EKnY8LptbY52swpxDvQscXJcz+zxzNVS/UDP4DU6I0/im2Jyc43Wv0WyL5TxvRdGOGk8gHTByXOKE5eOrYFn4/ojIuyWjR05odo5zDwpsfAR9puxJSnSEMeejBM885zd+2S8r6NkehN8qFkchaXqwwI8l7/TIQc/nApj6nuUvTNmQvkVDHQrkEHjHiJBroDArfHBUVgesw6Fs0O2eAx34MR4iUC+pUNnMCHZ5caRhESSJv3mbE5cu3Vgtdd1Zyaa9fvs03WKhVXfOFwsqz5Dia3wS4hO5TvxTuwdnoeLqxYKa+vwBcJb9PQLPRJ31A/oIml8zjDjpyp1eVGIYuB3rinKiJ4rfXtttCqF9L1Bmw7ayjhOWDu9Po7noxUlwxmEd3hFXMM9Cvggt3QgrwOtvgANMjFwCcCiAHkOy6iXiOLJRGwiJ2bGSQOjKzmDmFbLfrHHwvRh9xgzEr95LhmhQipNYI8MaMu0QfhxpffCuv45tqGSHGA5VyMQA5/LencvmpnTy/t9bvUCGcRtp4iK1xZmVnCQ0yqxJqylbwK5SbyiX60zF8xbx+bxouSwwazBkVGXAm6lHDlSv4MLsrvgcb4VbysS7Kyw== kylem@nullbook'

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

try:
    print(f"Connecting to {hostname}...")
    client.connect(hostname, username=username, password=password)
    print("Connected. Setting up authorized_keys...")
    
    commands = [
        "mkdir -p ~/.ssh",
        "chmod 700 ~/.ssh",
        f"echo '{public_key}' >> ~/.ssh/authorized_keys",
        "chmod 600 ~/.ssh/authorized_keys"
    ]
    
    for cmd in commands:
        stdin, stdout, stderr = client.exec_command(cmd)
        err = stderr.read().decode('utf-8')
        if err:
            print(f"Error executing '{cmd}': {err}")
            
    print("Successfully added SSH key.")
except Exception as e:
    print(f"Failed: {e}")
finally:
    client.close()

