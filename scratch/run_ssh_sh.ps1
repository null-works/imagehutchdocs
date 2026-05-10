$env:SSH_ASKPASS = "c:\Users\Null\Projects\imagehutchdocs\scratch\askpass.bat"
$env:DISPLAY = "dummy:0"
$env:SSH_ASKPASS_REQUIRE = "force"

Get-Content -Path "c:\Users\Null\Projects\imagehutchdocs\scratch\check_db.sh" -Raw | ssh -o StrictHostKeyChecking=no root@inklit.ch "bash"
