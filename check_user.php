<?php
try {
    $pdo = new PDO("mysql:host=chevereto-database-1;dbname=chevereto", "chevereto", "chevereto_secure_password_456");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Update the album count for Tristan
    $stmt_albums = $pdo->query("SELECT COUNT(*) FROM chv_albums WHERE album_user_id = 8");
    $album_count = $stmt_albums->fetchColumn();

    $stmt_images = $pdo->query("SELECT COUNT(*) FROM chv_images WHERE image_user_id = 8");
    $image_count = $stmt_images->fetchColumn();

    $stmt_update = $pdo->prepare("UPDATE chv_users SET user_album_count = ?, user_image_count = ? WHERE user_id = 8");
    $stmt_update->execute([$album_count, $image_count]);

    echo "Album count for Tristan: " . $album_count . "\n";
    echo "Image count for Tristan: " . $image_count . "\n";
    echo "Tristan counts updated successfully!\n";
} catch (Exception $e) {
    echo $e->getMessage();
}
