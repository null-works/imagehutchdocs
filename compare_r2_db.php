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

    // Fetch all image records from database
    $stmt = $pdo->prepare("SELECT image_name, image_extension, image_date FROM chv_images");
    $stmt->execute();
    $dbImages = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (preg_match('#^([0-9]{4})-([0-9]{2})-([0-9]{2})#', $row['image_date'], $m)) {
            $base = "images/{$m[1]}/{$m[2]}/{$m[3]}/{$row['image_name']}.{$row['image_extension']}";
            $dbImages[$base] = true;
            $dbImages["images/{$m[1]}/{$m[2]}/{$m[3]}/{$row['image_name']}.th.{$row['image_extension']}"] = true;
            $dbImages["images/{$m[1]}/{$m[2]}/{$m[3]}/{$row['image_name']}.md.{$row['image_extension']}"] = true;
        }
    }

    // Read all objects from R2
    $isTruncated = true;
    $continuationToken = null;
    $unmapped = [];

    while ($isTruncated) {
        $params = ['Bucket' => 'imagehut-media'];
        if ($continuationToken) {
            $params['ContinuationToken'] = $continuationToken;
        }

        $results = $s3->listObjectsV2($params);
        if (isset($results['Contents'])) {
            foreach ($results['Contents'] as $object) {
                $key = trim($object['Key']);
                if (!isset($dbImages[$key])) {
                    $unmapped[] = $key;
                }
            }
        }

        $isTruncated = $results['IsTruncated'];
        if ($isTruncated) {
            $continuationToken = $results['NextContinuationToken'];
        }
    }

    echo "FOUND " . count($unmapped) . " OBJECTS IN R2 THAT ARE NOT IN THE DATABASE:\n";
    foreach ($unmapped as $item) {
        echo "- $item\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
