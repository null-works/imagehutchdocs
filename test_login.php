<?php
define('ACCESS', 'web');
require_once '/var/www/html/app/legacy/load/php-boot.php';
echo "isLoggedUser exists: " . (class_exists('Chevereto\\Legacy\\Classes\\Login') ? 'yes' : 'no') . "\n";
echo "Chevereto\\Legacy\\Classes\\Login::isLoggedUser(): " . (\Chevereto\Legacy\Classes\Login::isLoggedUser() ? 'yes' : 'no') . "\n";
