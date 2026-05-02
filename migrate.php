<?php

function cheveretoID(string|int $in, string $action = 'encode'): string|int
{
    $index = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $salt = ''; // Empty salt
    $id_padding = 0;
    
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
            $out = $out + strpos($index, substr((string)$in, $t, 1)) * $bcpow;
        }
        if ($id_padding > 0) {
            $out = $out / $id_padding;
            if (! is_int($out)) {
                $out = 0;
            }
        }
        $out = (int) sprintf('%s', $out);
    } else {
        if ($id_padding > 0) {
            $in = $in * $id_padding;
        }
        $out = '';
        for ($t = floor(log((float) $in, $base)); $t >= 0; --$t) {
            $bcp = bcpow((string) $base, (string) $t);
            $a = floor($in / $bcp) % $base;
            $out = $out . substr($index, $a, 1);
            $in = $in - ($a * $bcp);
        }
    }

    return $out;
}

try {
    $pdo = new PDO('mysql:host=chevereto-database-1;dbname=chevereto', 'chevereto', 'chevereto_secure_password_456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tsv_file = '/var/www/html/MigrationTable.tsv';
    if (!file_exists($tsv_file)) {
        die("TSV file not found.\n");
    }

    $lines = file($tsv_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    // Skip header
    array_shift($lines);

    $currentUser = null;
    $currentUserId = null;
    
    $currentCharacter = null;
    $currentCharacterAlbumId = null;

    foreach ($lines as $line) {
        $cols = explode("\t", $line);
        if (count($cols) < 6) continue;

        $player = trim($cols[0]);
        $character = trim($cols[1]);
        $id = trim($cols[2]); // We don't use this, this is JCink ID
        $field = trim($cols[3]);
        $r2Path = trim($cols[4]);
        $sourceUrl = trim($cols[5]);

        if (!empty($player)) {
            $currentUser = $player;
            // Get or create user
            $username = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $player));
            if (empty($username)) $username = 'user_' . rand(1000, 9999);
            
            $stmt = $pdo->prepare("SELECT user_id FROM chv_users WHERE user_username = ?");
            $stmt->execute([$username]);
            $u = $stmt->fetchColumn();
            
            if (!$u) {
                $email = $username . '@imagehut.ch';
                $date = date('Y-m-d H:i:s');
                $stmt = $pdo->prepare("INSERT INTO chv_users (user_username, user_name, user_email, user_status, user_is_admin, user_date, user_date_gmt, user_registration_ip, user_timezone) VALUES (?, ?, ?, 'valid', 0, ?, ?, '127.0.0.1', 'UTC')");
                $stmt->execute([$username, $player, $email, $date, $date]);
                $u = $pdo->lastInsertId();
                echo "Created User: $player ($u)\n";
            }
            $currentUserId = $u;
        }

        if (!empty($character)) {
            $currentCharacter = $character;
            // Get or create parent character album
            $stmt = $pdo->prepare("SELECT album_id FROM chv_albums WHERE album_user_id = ? AND album_name = ? AND album_parent_id IS NULL");
            $stmt->execute([$currentUserId, $character]);
            $a = $stmt->fetchColumn();
            
            if (!$a) {
                $date = date('Y-m-d H:i:s');
                $stmt = $pdo->prepare("INSERT INTO chv_albums (album_user_id, album_name, album_privacy, album_date, album_date_gmt, album_creation_ip) VALUES (?, ?, 'public', ?, ?, '127.0.0.1')");
                $stmt->execute([$currentUserId, $character, $date, $date]);
                $a = $pdo->lastInsertId();
                echo "Created Character Album: $character ($a)\n";
            }
            $currentCharacterAlbumId = $a;
        }

        // Handle Field sub-album
        if ($r2Path === 'Dynamic' && preg_match('#randomizer/([a-zA-Z0-9]+)\.gif#', $sourceUrl, $matches)) {
            $randomizerEncodedId = $matches[1];
            $forcedAlbumId = cheveretoID($randomizerEncodedId, 'decode');
            
            // Check if it exists
            $stmt = $pdo->prepare("SELECT album_id FROM chv_albums WHERE album_id = ?");
            $stmt->execute([$forcedAlbumId]);
            if (!$stmt->fetchColumn()) {
                $date = date('Y-m-d H:i:s');
                $stmt = $pdo->prepare("INSERT INTO chv_albums (album_id, album_user_id, album_name, album_parent_id, album_privacy, album_date, album_date_gmt, album_creation_ip) VALUES (?, ?, ?, ?, 'public', ?, ?, '127.0.0.1')");
                $stmt->execute([$forcedAlbumId, $currentUserId, $field, $currentCharacterAlbumId, $date, $date]);
                echo "Created Randomizer Album for $currentCharacter - $field (Forced ID: $forcedAlbumId from $randomizerEncodedId)\n";
            }
        } else if ($r2Path !== 'Dynamic' && $r2Path !== '') {
            // Direct image. We create the sub-album without forcing ID, then insert the image
            $stmt = $pdo->prepare("SELECT album_id FROM chv_albums WHERE album_user_id = ? AND album_name = ? AND album_parent_id = ?");
            $stmt->execute([$currentUserId, $field, $currentCharacterAlbumId]);
            $subAlbumId = $stmt->fetchColumn();
            
            if (!$subAlbumId) {
                $date = date('Y-m-d H:i:s');
                $stmt = $pdo->prepare("INSERT INTO chv_albums (album_user_id, album_name, album_parent_id, album_privacy, album_date, album_date_gmt, album_creation_ip) VALUES (?, ?, ?, 'public', ?, ?, '127.0.0.1')");
                $stmt->execute([$currentUserId, $field, $currentCharacterAlbumId, $date, $date]);
                $subAlbumId = $pdo->lastInsertId();
            }

            // Clean the R2 path
            // e.g. "images/2026/04/04/sqgif2.gif" -> "2026/04/04/sqgif2.gif"
            $cleanedPath = preg_replace('#^images/#', '', $r2Path);
            
            // Parse date, name, extension
            if (preg_match('#^([0-9]{4})/([0-9]{2})/([0-9]{2})/(.+)\.([a-zA-Z0-9]+)$#', $cleanedPath, $m)) {
                $dateStr = "{$m[1]}-{$m[2]}-{$m[3]} 12:00:00";
                $name = $m[4];
                $ext = $m[5];
                
                // Check if image exists
                $stmt = $pdo->prepare("SELECT image_id FROM chv_images WHERE image_name = ? AND image_extension = ? AND image_user_id = ?");
                $stmt->execute([$name, $ext, $currentUserId]);
                if (!$stmt->fetchColumn()) {
                    $stmt = $pdo->prepare("
                        INSERT INTO chv_images 
                        (image_name, image_extension, image_date, image_date_gmt, image_storage_mode, image_storage_id, image_user_id, image_album_id, image_is_approved, image_uploader_ip, image_size, image_width, image_height, image_checksum, image_original_filename, image_views, image_chain, image_thumb_size, image_medium_size, image_frame_size, image_likes, image_is_animated, image_is_360, image_duration) 
                        VALUES 
                        (?, ?, ?, ?, 'datefolder', 1, ?, ?, 1, '127.0.0.1', 1024, 500, 500, '000', ?, 0, 1, 0, 0, 0, 0, ?, 0, 0)
                    ");
                    $isAnimated = (strtolower($ext) === 'gif') ? 1 : 0;
                    $stmt->execute([$name, $ext, $dateStr, $dateStr, $currentUserId, $subAlbumId, "$name.$ext", $isAnimated]);
                    echo "Inserted Image: $cleanedPath (Album $subAlbumId)\n";
                }
            } else {
                echo "Warning: Could not parse date from path: $cleanedPath\n";
            }
        }
    }
    echo "Migration Complete!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
