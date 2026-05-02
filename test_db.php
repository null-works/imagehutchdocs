<?php
$pdo = new PDO('mysql:host=chevereto-database-1;dbname=chevereto', 'chevereto', 'chevereto_secure_password_456');
$stmt = $pdo->prepare("UPDATE chv_storages SET storage_url = 'https://imagehut.ch/images/' WHERE storage_id = 1");
if ($stmt->execute()) {
    echo "SUCCESSFULLY UPDATED STORAGE URL TO https://imagehut.ch/images/\n";
} else {
    echo "FAILED TO UPDATE STORAGE URL.\n";
}
