<?php
use Aws\S3\S3Client;

try {
    $pdo = new PDO('mysql:host=chevereto-database-1;dbname=chevereto', 'chevereto', 'chevereto_secure_password_456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get real files from R2
    require_once '/var/www/html/app/vendor/autoload.php';


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
    ]);

    $r2Files = [];
    if (isset($results['Contents'])) {
        foreach ($results['Contents'] as $object) {
            $r2Files[trim($object['Key'])] = true;
        }
    }

    // Fetch all images
    $stmt = $pdo->prepare("SELECT image_id, image_name, image_extension, image_date FROM chv_images");
    $stmt->execute();
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updatedCount = 0;
    foreach ($images as $image) {
        $date = $image['image_date']; 
        if (preg_match('#^([0-9]{4})-([0-9]{2})-([0-9]{2})#', $date, $m)) {
            $basePath = "images/{$m[1]}/{$m[2]}/{$m[3]}/{$image['image_name']}";
            $origPath = "{$basePath}.{$image['image_extension']}";
            $thPath = "{$basePath}.th.{$image['image_extension']}";
            $mdPath = "{$basePath}.md.{$image['image_extension']}";

            $chainValue = 4; // default only original
            if (isset($r2Files[$origPath]) && isset($r2Files[$thPath])) {
                $chainValue = 7; // has thumb/medium/original
            }

            // Update database
            $upd = $pdo->prepare("UPDATE chv_images SET image_chain = ? WHERE image_id = ?");
            $upd->execute([$chainValue, $image['image_id']]);
            $updatedCount++;
        }
    }

    echo "Successfully updated chain values for $updatedCount images.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
