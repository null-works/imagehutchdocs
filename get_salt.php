<?php
define('ACCESS', true);
require_once '/var/www/html/app/legacy/load/loader.php';
// Let's print out the crypt_salt setting
echo "Crypt salt: " . getSetting('crypt_salt') . "\n";
echo "Id padding: " . getSetting('id_padding') . "\n";
