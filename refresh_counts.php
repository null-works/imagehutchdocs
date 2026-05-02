<?php
try {
    if (!isset($_GET['id'])) {
        die("Missing ID");
    }
    $id = (int)$_GET['id'];
    if ($id <= 0) {
        die("Invalid ID");
    }
    $pdo = new PDO("mysql:host=chevereto-database-1;dbname=chevereto", "chevereto", "chevereto_secure_password_456");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt_albums = $pdo->prepare("SELECT COUNT(*) FROM chv_albums WHERE album_user_id = ?");
    $stmt_albums->execute([$id]);
    $album_count = $stmt_albums->fetchColumn();

    $stmt_images = $pdo->prepare("SELECT COUNT(*) FROM chv_images WHERE image_user_id = ?");
    $stmt_images->execute([$id]);
    $image_count = $stmt_images->fetchColumn();

    $stmt_update = $pdo->prepare("UPDATE chv_users SET user_album_count = ?, user_image_count = ? WHERE user_id = ?");
    $stmt_update->execute([$album_count, $image_count, $id]);

    echo "ok";
} catch (Exception $e) {
    echo $e->getMessage();
}
