$script = @"
Set WshShell = WScript.CreateObject("WScript.Shell")
WshShell.Run "ssh -o StrictHostKeyChecking=no root@inklit.ch ""docker exec imagehutchdocs-database-1 mysql -u chevereto -pchevereto_secure_password_456 chevereto -e 'UPDATE chv_users SET user_language = ''en'', user_timezone = ''UTC'' WHERE user_language IS NULL; SELECT ROW_COUNT() as updated_rows;' > C:\Users\Null\Projects\imagehutchdocs\scratch\db_update.txt""", 1, False
WScript.Sleep 3000
WshShell.SendKeys "REDACTED_PASSWORD{ENTER}"
"@
Set-Content -Path "c:\Users\Null\Projects\imagehutchdocs\scratch\run_ssh_vbs.vbs" -Value $script

