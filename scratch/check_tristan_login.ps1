$password = 'REDACTED_PASSWORD'
$pinfo = New-Object System.Diagnostics.ProcessStartInfo
$pinfo.FileName = "ssh"
$pinfo.Arguments = '-tt -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null root@inklit.ch "docker exec imagehutchdocs-database-1 mysql -u chevereto -pchevereto_secure_password_456 -e \"SELECT user_id, user_name, user_username, user_email, user_status, LENGTH(user_password) as pwd_len, user_password FROM chv_users WHERE user_username IN (''tristan'', ''tristian'', ''Tristan'', ''Tristian'')\G\""'
$pinfo.RedirectStandardInput = $true
$pinfo.RedirectStandardOutput = $true
$pinfo.RedirectStandardError = $true
$pinfo.UseShellExecute = $false
$pinfo.CreateNoWindow = $true
$p = [System.Diagnostics.Process]::Start($pinfo)
Start-Sleep -Seconds 5
$p.StandardInput.WriteLine($password)
Start-Sleep -Seconds 10
$p.StandardInput.Close()
$stdout = $p.StandardOutput.ReadToEnd()
$stderr = $p.StandardError.ReadToEnd()
Write-Output "--- STDOUT ---"
Write-Output $stdout
Write-Output "--- STDERR ---"
Write-Output $stderr

