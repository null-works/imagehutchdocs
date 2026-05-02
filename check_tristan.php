<?php
try {
    $pdo = new PDO("mysql:host=chevereto-database-1;dbname=chevereto", "chevereto", "chevereto_secure_password_456");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Nirav Chaudhari
    $stmt_a1 = $pdo->prepare("INSERT INTO chv_albums (album_user_id, album_name, album_privacy, album_date, album_date_gmt, album_creation_ip) VALUES (8, 'Nirav Chaudhari', 'public', NOW(), NOW(), '127.0.0.1')");
    $stmt_a1->execute();
    $a1_id = $pdo->lastInsertId();

    $stmt_sub1 = $pdo->prepare("INSERT INTO chv_albums (album_id, album_user_id, album_name, album_parent_id, album_privacy, album_date, album_date_gmt, album_creation_ip) VALUES (233420, 8, 'Portrait', ?, 'public', NOW(), NOW(), '127.0.0.1')");
    $stmt_sub1->execute([$a1_id]);

    // 2. Tommy Shepherd
    $stmt_a2 = $pdo->prepare("INSERT INTO chv_albums (album_user_id, album_name, album_privacy, album_date, album_date_gmt, album_creation_ip) VALUES (8, 'Tommy Shepherd', 'public', NOW(), NOW(), '127.0.0.1')");
    $stmt_a2->execute();
    $a2_id = $pdo->lastInsertId();

    $stmt_sub2 = $pdo->prepare("INSERT INTO chv_albums (album_id, album_user_id, album_name, album_parent_id, album_privacy, album_date, album_date_gmt, album_creation_ip) VALUES (118566, 8, 'Portrait', ?, 'public', NOW(), NOW(), '127.0.0.1')");
    $stmt_sub2->execute([$a2_id]);

    echo "Recreated Tristan's albums successfully!\n";
} catch (Exception $e) {
    echo $e->getMessage();
}
