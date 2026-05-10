$env:SSH_ASKPASS = "c:\Users\Null\Projects\imagehutchdocs\scratch\askpass.bat"
$env:DISPLAY = "dummy:0"
$env:SSH_ASKPASS_REQUIRE = "force"

Get-Content -Path "c:\Users\Null\Projects\imagehutchdocs\scratch\find_loader.php" -Raw | ssh -o StrictHostKeyChecking=no root@inklit.ch "docker exec -i chevereto-php-1 php"
