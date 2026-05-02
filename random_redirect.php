<?php
$album_encoded = $_GET['album'] ?? '';
if (empty($album_encoded)) {
    die("No album specified.");
}

// Precise mapping of old randomizer links to new sub-album IDs
$map = [
    'AOk' => 162937, // Sen Wu Portrait
    'xHT' => 131516, // Alison Blaire Portrait
    'YzJ' => 59571,  // Izaiah Carter Portrait
    'YKd' => 60511,  // Izaiah Carter Square
    'csr' => 61648,  // Kimberly Parson Portrait
    'wSC' => 45885,  // Kimberly Parson Square
    'beZ' => 200416, // Kimberly Parson Secondary Square
    '2gM' => 49123,  // Kimberly Parson Rectangle/Banner
    'AGp' => 162861, // Sharon Davis Portrait
    'A2e' => 162200, // Sharon Davis Square
    'gry' => 185781, // Sharon Davis Secondary Square
    'A3v' => 162800, // Sharon Davis Rectangle/Banner
    'zmN' => 119024, // William Kaplan Portrait
    'xoK' => 134461, // Logan Portrait
    'ujY' => 13593,  // Juliet Hawkins Portrait
    'UMS' => 139620, // Katherine Murphy Portrait
    'oFb' => 233420, // Nirav Chaudhari Portrait
    'zbG' => 118566, // Tommy Shepherd Portrait
];

$album_id = null;
if (isset($map[$album_encoded])) {
    $album_id = $map[$album_encoded];
}

if (!$album_id) {
    function cheveretoID($in, $action = 'decode') {
        $index = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $salt = '751b13d3';
        $id_padding = 999;
        
        for ($n = 0; $n < strlen($index); ++$n) {
            $i[] = substr($index, $n, 1);
        }
        $passhash = hash('sha256', $salt);
        $passhash = (strlen($passhash) < strlen($index)) ? hash('sha512', $salt) : $passhash;
        for ($n = 0; $n < strlen($index); ++$n) {
            $p[] = substr($passhash, $n, 1);
        }
        array_multisort($p, SORT_DESC, $i);
        $index = implode('', $i);
        $base = strlen($index);
        if ($action === 'decode') {
            $out = 0;
            $len = strlen($in) - 1;
            for ($t = 0; $t <= $len; ++$t) {
                $bcpow = bcpow((string) $base, (string) ($len - $t));
                $out = $out + strpos($index, substr($in, $t, 1)) * $bcpow;
            }
            if ($id_padding > 0) {
                $out = $out / $id_padding;
                if (!is_numeric($out) || floor($out) != $out) {
                    $out = 0;
                }
            }
            $out = (int) sprintf('%s', $out);
        }
        return $out;
    }
    $album_id = cheveretoID($album_encoded);
}

if (!$album_id) {
    die("Invalid album ID");
}

try {
    $pdo = new PDO('mysql:host=chevereto-database-1;dbname=chevereto', 'chevereto', 'chevereto_secure_password_456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        SELECT i.image_name, i.image_extension, i.image_date
        FROM chv_images i
        LEFT JOIN chv_albums a ON i.image_album_id = a.album_id
        WHERE i.image_album_id = ? OR a.album_parent_id = ?
        ORDER BY RAND()
        LIMIT 1
    ");
    $stmt->execute([$album_id, $album_id]);
    $img = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($img) {
        $m = [];
        if (preg_match('#^([0-9]{4})-([0-9]{2})-([0-9]{2})#', $img['image_date'], $m)) {
            $src = "https://media.imagehut.ch/images/{$m[1]}/{$m[2]}/{$m[3]}/{$img['image_name']}.{$img['image_extension']}";
        } else {
            $src = "https://media.imagehut.ch/images/{$img['image_name']}.{$img['image_extension']}";
        }
        header("Location: $src");
        exit;
    } else {
        die("No images found in this album.");
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
