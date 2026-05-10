$env:SSH_ASKPASS = "c:\Users\Null\Projects\imagehutchdocs\scratch\askpass.bat"
$env:DISPLAY = "dummy:0"
$env:SSH_ASKPASS_REQUIRE = "force"

echo "UPDATE chv_settings SET setting_value = 'index' WHERE setting_name = 'root_route';" | ssh -o StrictHostKeyChecking=no root@inklit.ch "docker exec -i chevereto-database-1 mariadb -u chevereto -pchevereto_secure_password_456 chevereto"
