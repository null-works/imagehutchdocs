<?php
$it = new RecursiveDirectoryIterator('/var/www/html/app');
foreach(new RecursiveIteratorIterator($it) as $file) {
    if ($file->isDir()) continue;
    $content = file_get_contents($file->getPathname());
    if (strpos($content, 'function loaderHandler') !== false) {
        echo $file->getPathname() . "\n";
    }
}
