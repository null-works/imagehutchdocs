<?php
define('ACCESS', 'cli');
require_once '/var/www/html/app/legacy/load/php-boot.php';

use Chevereto\Legacy\Classes\DB;
use Chevereto\Legacy\Classes\Storage;

try {
    $storage = [
        'name' => 'Cloudflare R2',
        'api_id' => 9, // S3 compatible
        'bucket' => 'imagehut-media',
        'region' => 'auto',
        'key' => 'b304c6fa1284883447a2b29f5d6de57e',
        'secret' => 'd8ebca54626a30e220537106f024e309748048c490c629c7f00ebb593430b155',
        'server' => 'https://5215ebbf6291827b415632f0cd0eae79.r2.cloudflarestorage.com',
        'url' => 'https://media.imagehut.ch/',
        'is_active' => 1,
    ];
    
    $insert = Storage::insert($storage);
    
    if ($insert) {
        echo "Successfully added R2 storage with ID: " . $insert . "\n";
    } else {
        echo "Failed to insert storage.\n";
    }
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
