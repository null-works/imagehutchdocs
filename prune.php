<?php
try {
    $pdo = new PDO('mysql:host=chevereto-database-1;dbname=chevereto', 'chevereto', 'chevereto_secure_password_456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $r2map_file = '/var/www/html/R2Map.txt';
    if (!file_exists($r2map_file)) {
        die("R2Map file not found.\n");
    }

    $lines = file($r2map_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $r2Files = [];
    foreach ($lines as $line) {
        $r2Files[trim($line)] = true;
    }

    // Fetch all images
    $stmt = $pdo->prepare("SELECT image_id, image_name, image_extension, image_date FROM chv_images WHERE image_storage_id = 1");
    $stmt->execute();
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $deletedCount = 0;
    foreach ($images as $image) {
        $date = $image['image_date']; // e.g. "2026-03-19 12:00:00"
        if (preg_match('#^([0-9]{4})-([0-9]{2})-([0-9]{2})#', $date, $m)) {
            $pathInR2 = "{$m[1]}/{$m[2]}/{$m[3]}/{$image['image_name']}.{$image['image_extension']}";
            
            if (!isset($r2Files[$pathInR2])) {
                // Not in R2, delete it
                $del = $pdo->prepare("DELETE FROM chv_images WHERE image_id = ?");
                $del->execute([$image['image_id']]);
                $deletedCount++;
                echo "Deleted missing image: $pathInR2 (ID: {$image['image_id']})\n";
            }
        }
    }

    echo "Deleted $deletedCount missing images in total.\n";

    // Re-update counts
    $pdo->exec("UPDATE chv_users SET user_image_count = (SELECT COUNT(1) FROM chv_images WHERE image_user_id = user_id)");
    $pdo->exec("UPDATE chv_users SET user_album_count = (SELECT COUNT(1) FROM chv_albums WHERE album_user_id = user_id AND album_parent_id IS NULL)");
    $pdo->exec("UPDATE chv_albums SET album_image_count = (SELECT COUNT(1) FROM chv_images WHERE image_album_id = album_id)");
    echo "Updated all cached counters.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
