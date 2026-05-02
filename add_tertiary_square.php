<?php
try {
    $pdo = new PDO('mysql:host=chevereto-database-1;dbname=chevereto', 'chevereto', 'chevereto_secure_password_456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT album_id, album_name, album_user_id FROM chv_albums WHERE album_parent_id IS NULL");
    $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $created = 0;
    foreach ($parents as $parent) {
        $parentId = $parent['album_id'];
        $userId = $parent['album_user_id'];
        $charName = $parent['album_name'];

        // Check if Tertiary Square already exists for this parent
        $check = $pdo->prepare("SELECT album_id FROM chv_albums WHERE album_parent_id = ? AND album_name = 'Tertiary Square'");
        $check->execute([$parentId]);
        if (!$check->fetch()) {
            $ins = $pdo->prepare("
                INSERT INTO chv_albums (album_name, album_user_id, album_date, album_date_gmt, album_creation_ip, album_privacy, album_parent_id)
                VALUES ('Tertiary Square', ?, NOW(), NOW(), '127.0.0.1', 'public', ?)
            ");
            $ins->execute([$userId, $parentId]);
            $created++;
            echo "Created Tertiary Square for character: $charName (Parent ID: $parentId)\n";
        }
    }
    echo "Done! Successfully created $created Tertiary Square sub-albums.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
