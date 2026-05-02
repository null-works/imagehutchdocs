<?php
require_once '/var/www/html/app/vendor/autoload.php';
use Aws\S3\S3Client;

try {
    $s3 = new S3Client([
        'version' => 'latest',
        'region' => 'us-east-1',
        'endpoint' => 'https://5215ebbf6291827b415632f0cd0eae79.r2.cloudflarestorage.com',
        'credentials' => [
            'key' => 'b304c6fa1284883447a2b29f5d6de57e',
            'secret' => 'd8ebca54626a30e220537106f024e309748048c490c629c7f00ebb593430b155',
        ],
    ]);

    $results = $s3->listObjectsV2([
        'Bucket' => 'imagehut-media',
        'Prefix' => 'images/2026/01/14/',
    ]);

    if (isset($results['Contents'])) {
        foreach ($results['Contents'] as $object) {
            echo "{$object['Key']} : {$object['Size']} bytes\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
