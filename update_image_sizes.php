<?php
require_once '/var/www/html/app/vendor/autoload.php';
use Aws\S3\S3Client;

try {
    $pdo = new PDO('mysql:host=chevereto-database-1;dbname=chevereto', 'chevereto', 'chevereto_secure_password_456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $s3 = new S3Client([
        'version' => 'latest',
        'region' => 'us-east-1',
        'endpoint' => 'https://5215ebbf6291827b415632f0cd0eae79.r2.cloudflarestorage.com',
        'credentials' => [
            'key' => 'b304c6fa1284883447a2b29f5d6de57e',
            'secret' => 'd8ebca54626a30e220537106f024e309748048c490c629c7f00ebb593430b155',
        ],
    ]);

    $isTruncated = true;
    $continuationToken = null;
    $r2Files = [];

    while ($isTruncated) {
        $params = ['Bucket' => 'imagehut-media'];
        if ($continuationToken) {
            $params['ContinuationToken'] = $continuationToken;
        }

        $results = $s3->listObjectsV2($params);
        if (isset($results['Contents'])) {
            foreach ($results['Contents'] as $object) {
                $r2Files[trim($object['Key'])] = (int)$object['Size'];
            }
        }

        $isTruncated = $results['IsTruncated'];
        if ($isTruncated) {
            $continuationToken = $results['NextContinuationToken'];
        }
    }

    // Update the database with real sizes
    $stmt = $pdo->query("SELECT image_id, image_name, image_extension, image_date FROM chv_images");
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updatedCount = 0;
    foreach ($images as $img) {
        if (preg_match('#^([0-9]{4})-([0-9]{2})-([0-9]{2})#', $img['image_date'], $m)) {
            $key = "images/{$m[1]}/{$m[2]}/{$m[3]}/{$img['image_name']}.{$img['image_extension']}";
            if (isset($r2Files[$key])) {
                $upd = $pdo->prepare("UPDATE chv_images SET image_size = ? WHERE image_id = ?");
                $upd->execute([$r2Files[$key], $img['image_id']]);
                $updatedCount++;
            }
        }
    }

    echo "SUCCESSFULLY UPDATED $updatedCount IMAGES WITH EXACT SIZES FROM R2.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
