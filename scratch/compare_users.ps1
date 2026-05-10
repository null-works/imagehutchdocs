$password = 'REDACTED_PASSWORD'
$pinfo = New-Object System.Diagnostics.ProcessStartInfo
$pinfo.FileName = "ssh"
$pinfo.Arguments = '-tt -o StrictHostKeyChecking=no root@inklit.ch "docker exec imagehutchdocs-database-1 mysql -u chevereto -pchevereto_secure_password_456 -e \"SELECT * FROM chv_users WHERE user_username=\\\"tristan\\\" OR user_username=\\\"null\\\"\G\""'
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

