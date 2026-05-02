<?php
try {
    $pdo = new PDO("mysql:host=chevereto-database-1;dbname=chevereto", "chevereto", "chevereto_secure_password_456");
    $stmt = $pdo->prepare("DELETE FROM chv_albums WHERE album_name = 'Avatar URL'");
    $stmt->execute();
    echo "Deleted albums named 'Avatar URL': " . $stmt->rowCount() . " rows affected.\n";
} catch (Exception $e) {
    echo $e->getMessage();
}
