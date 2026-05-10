$password = 'REDACTED_PASSWORD'
$passFile = "c:\Users\Null\Projects\imagehutchdocs\scratch\pass.txt"
$password | Out-File -FilePath $passFile -Encoding ascii -NoNewline

$phpCmd = "docker exec imagehutchdocs-php-1 php -r `"echo password_hash('Welcome1!', PASSWORD_BCRYPT);`""
$cmd = "ssh -o StrictHostKeyChecking=no root@inklit.ch ""$phpCmd"" < ""$passFile"""

$pinfo = New-Object System.Diagnostics.ProcessStartInfo
$pinfo.FileName = "cmd.exe"
$pinfo.Arguments = "/c `"$cmd`""
$pinfo.RedirectStandardOutput = $true
$pinfo.RedirectStandardError = $true
$pinfo.UseShellExecute = $false
$pinfo.CreateNoWindow = $true

$p = [System.Diagnostics.Process]::Start($pinfo)
$p.WaitForExit(15000)

$hash = $p.StandardOutput.ReadToEnd().Trim()
$stderr = $p.StandardError.ReadToEnd()

Write-Output "--- HASH OUTPUT ---"
Write-Output $hash
Write-Output "--- ERRORS ---"
Write-Output $stderr

if ($hash.StartsWith('$2y$')) {
    $sql = "UPDATE chv_users SET user_password = '$hash' WHERE user_username IN ('tristan', 'xana', 'twaiadmin', 'reznik', 'mdk', 'mandee', 'kip', 'spider', 'blurple_waffle');"
    $sqlCmd = "ssh -o StrictHostKeyChecking=no root@inklit.ch ""docker exec imagehutchdocs-database-1 mysql -u chevereto -pchevereto_secure_password_456 chevereto -e \""$sql\"""" < ""$passFile"""
    
    $pinfo2 = New-Object System.Diagnostics.ProcessStartInfo
    $pinfo2.FileName = "cmd.exe"
    $pinfo2.Arguments = "/c `"$sqlCmd`""
    $pinfo2.RedirectStandardOutput = $true
    $pinfo2.RedirectStandardError = $true
    $pinfo2.UseShellExecute = $false
    $pinfo2.CreateNoWindow = $true

    $p2 = [System.Diagnostics.Process]::Start($pinfo2)
    $p2.WaitForExit(15000)
    
    Write-Output "--- DB UPDATE OUTPUT ---"
    Write-Output $p2.StandardOutput.ReadToEnd()
    Write-Output $p2.StandardError.ReadToEnd()
} else {
    Write-Output "Failed to generate valid hash."
}

Remove-Item -Path $passFile -Force

