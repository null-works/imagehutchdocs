<?php
try {
    $pdo = new PDO("mysql:host=chevereto-database-1;dbname=chevereto", "chevereto", "chevereto_secure_password_456");
    $stmt = $pdo->query("DESCRIBE chv_users");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo $row['Field'] . "\n";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
