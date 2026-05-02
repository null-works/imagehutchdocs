<?php
define('ACCESS', 'cli');
require_once '/var/www/html/app/legacy/load/php-boot.php';

use function Chevereto\Legacy\decodeID;

$ids = ['AOk', 'YzJ', 'YKd', 'csr', 'wSC', 'beZ', '2gM', 'AGp', 'A2e', 'gry', 'A3v', 'zmN', 'xoK', 'ujY', 'UMS', 'oFb', 'zbG', 'xHT'];

foreach ($ids as $id) {
    echo "$id -> " . decodeID($id) . "\n";
}
