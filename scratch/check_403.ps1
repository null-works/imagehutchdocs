$password = 'REDACTED_PASSWORD'
$passFile = "c:\Users\Null\Projects\imagehutchdocs\scratch\pass.txt"
$password | Out-File -FilePath $passFile -Encoding ascii -NoNewline

$remoteCmd = "docker ps && docker logs --tail 20 imagehutchdocs-php-1 && cat /var/log/nginx/error.log | tail -n 20"
$cmd = "ssh -o StrictHostKeyChecking=no root@inklit.ch ""$remoteCmd"" < ""$passFile"""

$pinfo = New-Object System.Diagnostics.ProcessStartInfo
$pinfo.FileName = "cmd.exe"
$pinfo.Arguments = "/c `"$cmd`""
$pinfo.RedirectStandardOutput = $true
$pinfo.RedirectStandardError = $true
$pinfo.UseShellExecute = $false
$pinfo.CreateNoWindow = $true

$p = [System.Diagnostics.Process]::Start($pinfo)
$p.WaitForExit(15000)

Write-Output "--- OUTPUT ---"
Write-Output $p.StandardOutput.ReadToEnd()
Write-Output "--- ERRORS ---"
Write-Output $p.StandardError.ReadToEnd()

Remove-Item -Path $passFile -Force

