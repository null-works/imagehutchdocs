$password = 'REDACTED_PASSWORD'
$pinfo = New-Object System.Diagnostics.ProcessStartInfo
$pinfo.FileName = "ssh"
$pinfo.Arguments = '-tt -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null root@inklit.ch "docker exec imagehutchdocs-database-1 mysql -u chevereto -pchevereto_secure_password_456 chevereto -e \"UPDATE chv_users SET user_language = ''en'', user_timezone = ''UTC'' WHERE user_language IS NULL; SELECT ROW_COUNT() as updated_rows;\""'
$pinfo.RedirectStandardInput = $true
$pinfo.RedirectStandardOutput = $true
$pinfo.RedirectStandardError = $true
$pinfo.UseShellExecute = $false
$pinfo.CreateNoWindow = $true
$p = [System.Diagnostics.Process]::Start($pinfo)

# Wait a moment for the password prompt
Start-Sleep -Seconds 3

# Send the password
$p.StandardInput.WriteLine($password)
$p.StandardInput.Flush()

# Wait for command execution
Start-Sleep -Seconds 5
$p.StandardInput.Close()

# Wait for the process to exit
if (-not $p.WaitForExit(10000)) {
    $p.Kill()
    Write-Output "TIMED OUT"
}

$stdout = $p.StandardOutput.ReadToEnd()
$stderr = $p.StandardError.ReadToEnd()

Write-Output "--- STDOUT ---"
Write-Output $stdout
Write-Output "--- STDERR ---"
Write-Output $stderr

