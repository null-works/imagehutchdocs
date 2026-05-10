$password = 'REDACTED_PASSWORD'
$passFile = "c:\Users\Null\Projects\imagehutchdocs\scratch\pass.txt"
$password | Out-File -FilePath $passFile -Encoding ascii -NoNewline

$sql = "UPDATE chv_users SET user_language = 'en', user_timezone = 'UTC' WHERE user_language IS NULL;"
$cmd = "ssh -o StrictHostKeyChecking=no root@inklit.ch ""docker exec imagehutchdocs-database-1 mysql -u chevereto -pchevereto_secure_password_456 chevereto -e \""$sql\"""" < ""$passFile"""

# Run it using cmd.exe because cmd supports the < operator
$pinfo = New-Object System.Diagnostics.ProcessStartInfo
$pinfo.FileName = "cmd.exe"
$pinfo.Arguments = "/c `"$cmd`""
$pinfo.RedirectStandardOutput = $true
$pinfo.RedirectStandardError = $true
$pinfo.UseShellExecute = $false
$pinfo.CreateNoWindow = $true

$p = [System.Diagnostics.Process]::Start($pinfo)
$p.WaitForExit(15000)

$stdout = $p.StandardOutput.ReadToEnd()
$stderr = $p.StandardError.ReadToEnd()

Remove-Item -Path $passFile -Force

Write-Output "--- DB FIX OUTPUT ---"
Write-Output $stdout
Write-Output "--- ERRORS ---"
Write-Output $stderr

