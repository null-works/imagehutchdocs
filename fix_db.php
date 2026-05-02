<?php
try {
    $pdo = new PDO('mysql:host=chevereto-database-1;dbname=chevereto', 'chevereto', 'chevereto_secure_password_456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("UPDATE chv_users SET user_language = 'en' WHERE user_language IS NULL");
    echo "Fixed user_language.\n";

    $pdo->exec("UPDATE chv_users SET user_image_count = (SELECT COUNT(1) FROM chv_images WHERE image_user_id = user_id)");
    echo "Updated user image counts.\n";

    $pdo->exec("UPDATE chv_users SET user_album_count = (SELECT COUNT(1) FROM chv_albums WHERE album_user_id = user_id AND album_parent_id IS NULL)");
    echo "Updated user album counts.\n";

    $pdo->exec("UPDATE chv_albums SET album_image_count = (SELECT COUNT(1) FROM chv_images WHERE image_album_id = album_id)");
    echo "Updated album image counts.\n";

} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
