docker exec chevereto-database-1 mariadb -u chevereto -pchevereto_secure_password_456 chevereto -e "SELECT * FROM chv_ip_bans;"
docker exec chevereto-database-1 mariadb -u chevereto -pchevereto_secure_password_456 chevereto -e "SELECT setting_name, setting_value FROM chv_settings WHERE setting_name IN ('website_mode', 'maintenance', 'website_privacy_mode');"
