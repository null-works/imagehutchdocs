<?php
require_once '/var/www/html/app/vendor/autoload.php';

try {
    $pdo = new PDO('mysql:host=chevereto-database-1;dbname=chevereto', 'chevereto', 'chevereto_secure_password_456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Fetch all albums to match character names
    $stmt = $pdo->query("SELECT album_id, album_name, album_user_id FROM chv_albums");
    $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Map keywords to specific albums/users
    // We break down character names into keywords (e.g., 'St. John Allerdyce' -> 'pyro')
    // and explicitly map them to character albums
    $keywordToAlbum = [];
    foreach ($albums as $a) {
        $name = strtolower($a['album_name']);
        
        // Split name by spaces
        $parts = explode(' ', $name);
        foreach ($parts as $part) {
            $part = trim($part, " .()'");
            if (strlen($part) > 3) {
                $keywordToAlbum[$part] = $a;
            }
        }

        // Custom well-known aliases
        if (str_contains($name, 'allerdyce')) {
            $keywordToAlbum['pyro'] = $a;
        }
        if (str_contains($name, 'wade wilson') || str_contains($name, 'deadpool')) {
            $keywordToAlbum['deadpool'] = $a;
            $keywordToAlbum['wade'] = $a;
        }
        if (str_contains($name, 'sharon davis')) {
            $keywordToAlbum['sharon'] = $a;
        }
        if (str_contains($name, 'delano krueger')) {
            $keywordToAlbum['delano'] = $a;
        }
    }

    // 3. Fetch all images owned by the admin (user 1)
    $stmt = $pdo->query("SELECT image_id, image_name FROM chv_images WHERE image_user_id = 1");
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $matchedCount = 0;
    foreach ($images as $img) {
        $imgName = strtolower($img['image_name']);

        // Check if image name contains any character/album keyword
        foreach ($keywordToAlbum as $keyword => $album) {
            if (str_contains($imgName, $keyword)) {
                // We have a match! Update image in DB
                $uId = $album['album_user_id'];
                $aId = $album['album_id'];

                $upd = $pdo->prepare("UPDATE chv_images SET image_user_id = ?, image_album_id = ? WHERE image_id = ?");
                $upd->execute([$uId, $aId, $img['image_id']]);

                echo "Matched image '{$img['image_name']}' -> Character Folder '{$album['album_name']}'\n";
                $matchedCount++;
                break;
            }
        }
    }

    // Recalculate chv_users counters for images and albums just to be perfectly accurate
    $pdo->query("UPDATE chv_users u SET user_image_count = (SELECT COUNT(*) FROM chv_images WHERE image_user_id = u.user_id)");

    echo "\nTOTAL IMAGES SUCCESSFULLY ASSOCIATED WITH THEIR CHARACTERS: $matchedCount\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
