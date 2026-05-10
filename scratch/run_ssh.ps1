$env:SSH_ASKPASS = "c:\Users\Null\Projects\imagehutchdocs\scratch\askpass.bat"
$env:DISPLAY = "dummy:0"
$env:SSH_ASKPASS_REQUIRE = "force"

$cmd = 'ssh -o StrictHostKeyChecking=no root@inklit.ch "docker exec chevereto-php-1 cat /etc/apache2/conf-enabled/docker-php.conf"'
Invoke-Expression $cmd
