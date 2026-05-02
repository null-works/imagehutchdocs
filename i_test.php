<?php
define('ACCESS', 'web');
ob_start();
try {
    require_once __DIR__ . '/../app/legacy/entrypoints/index.php';
} catch (\Throwable $e) {
    //
}
ob_end_clean();

var_dump(function_exists('\\Chevereto\\Legacy\\decodeID'));
$res = \Chevereto\Legacy\decodeID('YzJ');
var_dump($res);
