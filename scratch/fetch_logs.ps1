$password = 'REDACTED_PASSWORD'
$pinfo = New-Object System.Diagnostics.ProcessStartInfo
$pinfo.FileName = "ssh"
$pinfo.Arguments = '-tt -o StrictHostKeyChecking=no root@inklit.ch "docker ps && docker logs --tail 20 imagehutchdocs-php-1 && tail -n 20 /var/log/nginx/error.log"'
$pinfo.RedirectStandardInput = $true
$pinfo.RedirectStandardOutput = $true
$pinfo.RedirectStandardError = $true
$pinfo.UseShellExecute = $false
$pinfo.CreateNoWindow = $true
$p = [System.Diagnostics.Process]::Start($pinfo)
Start-Sleep -Seconds 3
$p.StandardInput.WriteLine($password)
Start-Sleep -Seconds 5
$p.StandardInput.Close()
$p.WaitForExit(15000)
$stdout = $p.StandardOutput.ReadToEnd()
$stderr = $p.StandardError.ReadToEnd()
Write-Output "--- STDOUT ---"
Write-Output $stdout
Write-Output "--- STDERR ---"
Write-Output $stderr

