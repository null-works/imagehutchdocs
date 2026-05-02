<?php
require_once '/var/www/html/app/vendor/autoload.php';

try {
    $pdo = new PDO('mysql:host=chevereto-database-1;dbname=chevereto', 'chevereto', 'chevereto_secure_password_456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch all admin-owned images
    $stmt = $pdo->query("SELECT image_id, image_name, image_extension, image_date FROM chv_images WHERE image_user_id = 1");
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $updatedCount = 0;
    foreach ($images as $img) {
        if (preg_match('#^([0-9]{4})-([0-9]{2})-([0-9]{2})#', $img['image_date'], $m)) {
            $url = "https://media.imagehut.ch/images/{$m[1]}/{$m[2]}/{$m[3]}/{$img['image_name']}.{$img['image_extension']}";
            
            // Set context timeout for getimagesize
            $context = stream_context_create([
                'http' => ['timeout' => 3]
            ]);

            $dims = @getimagesize($url, $info);
            if ($dims) {
                $w = (int)$dims[0];
                $h = (int)$dims[1];

                $upd = $pdo->prepare("UPDATE chv_images SET image_width = ?, image_height = ? WHERE image_id = ?");
                $upd->execute([$w, $h, $img['image_id']]);
                $updatedCount++;
            }
        }
    }

    echo "SUCCESSFULLY UPDATED DIMENSIONS FOR $updatedCount IMAGES.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
