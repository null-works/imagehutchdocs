<?php
define('ACCESS', 'cli');
require_once '/var/www/html/app/legacy/load/php-boot.php';

use Chevereto\Legacy\Classes\Image;

print_r(Image::$chain_sizes);
