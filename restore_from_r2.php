<?php
require_once '/var/www/html/app/vendor/autoload.php';
use Aws\S3\S3Client;

try {
    $pdo = new PDO('mysql:host=chevereto-database-1;dbname=chevereto', 'chevereto', 'chevereto_secure_password_456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Parse MigrationTable.tsv to map R2 paths to users/albums
    $tsvFile = '/var/www/html/MigrationTable.tsv';
    if (!file_exists($tsvFile)) {
        // copy if it exists locally, or just check the current working directory
        $tsvFile = 'c:/Users/kylem/repos/imagehutchdocs/MigrationTable.tsv';
        if (!file_exists($tsvFile)) {
            $tsvFile = '/opt/chevereto/app/MigrationTable.tsv';
        }
    }

    // Read users to map usernames to user_ids
    $stmt = $pdo->query("SELECT user_id, user_name, user_username FROM chv_users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $userMap = [];
    foreach ($users as $u) {
        $uName = $u['user_name'] ?? '';
        $uUser = $u['user_username'] ?? '';
        $userMap[strtolower($uName)] = $u['user_id'];
        $userMap[strtolower($uUser)] = $u['user_id'];
    }

    // Read albums to map character names to album_ids
    $stmt = $pdo->query("SELECT album_id, album_name, album_user_id FROM chv_albums");
    $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $albumMap = [];
    foreach ($albums as $a) {
        $aName = $a['album_name'] ?? '';
        $albumMap[strtolower($aName)] = $a['album_id'];
    }


    $tsvMappings = [];
    if (file_exists($tsvFile)) {
        $lines = file($tsvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $currentPlayer = null;
        $currentCharacter = null;
        foreach ($lines as $index => $line) {
            if ($index === 0) continue; // Skip header
            $parts = explode("\t", $line);
            if (count($parts) >= 5) {
                if (!empty(trim($parts[0]))) {
                    $currentPlayer = trim($parts[0]);
                }
                if (!empty(trim($parts[1]))) {
                    $currentCharacter = trim($parts[1]);
                }

                $r2Path = trim($parts[4]);
                if (!empty($r2Path) && $r2Path !== 'Dynamic') {
                    // Normalize to include images/ prefix if missing
                    if (!str_starts_with($r2Path, 'images/')) {
                        $r2Path = 'images/' . $r2Path;
                    }
                    $tsvMappings[$r2Path] = [
                        'player' => $currentPlayer,
                        'character' => $currentCharacter,
                    ];
                }
            }
        }
    }

    // 2. Scan all objects in Cloudflare R2
    $s3 = new S3Client([
        'version' => 'latest',
        'region' => 'us-east-1',
        'endpoint' => 'https://5215ebbf6291827b415632f0cd0eae79.r2.cloudflarestorage.com',
        'credentials' => [
            'key' => 'b304c6fa1284883447a2b29f5d6de57e',
            'secret' => 'd8ebca54626a30e220537106f024e309748048c490c629c7f00ebb593430b155',
        ],
    ]);

    $isTruncated = true;
    $continuationToken = null;
    $r2Files = [];

    while ($isTruncated) {
        $params = ['Bucket' => 'imagehut-media'];
        if ($continuationToken) {
            $params['ContinuationToken'] = $continuationToken;
        }

        $results = $s3->listObjectsV2($params);
        if (isset($results['Contents'])) {
            foreach ($results['Contents'] as $object) {
                $r2Files[trim($object['Key'])] = true;
            }
        }

        $isTruncated = $results['IsTruncated'];
        if ($isTruncated) {
            $continuationToken = $results['NextContinuationToken'];
        }
    }

    // 3. Compare with DB and insert missing files
    $restoredCount = 0;
    foreach ($r2Files as $key => $val) {
        // Only process originals (ignore thumbnails/mediums)
        if (str_contains($key, '.th.') || str_contains($key, '.md.')) {
            continue;
        }
        if (!str_starts_with($key, 'images/')) {
            continue;
        }

        // Parse date and filename
        // Key format: images/YYYY/MM/DD/filename.ext
        if (preg_match('#^images/([0-9]{4})/([0-9]{2})/([0-9]{2})/(.+)\.([a-z0-9]+)$#i', $key, $matches)) {
            $year = $matches[1];
            $month = $matches[2];
            $day = $matches[3];
            $name = $matches[4];
            $ext = strtolower($matches[5]);

            // Check if already in DB
            $stmt = $pdo->prepare("SELECT image_id FROM chv_images WHERE image_name = ? AND image_extension = ? AND image_date LIKE ?");
            $stmt->execute([$name, $ext, "$year-$month-$day%"]);
            if ($stmt->fetch()) {
                continue; // Already exists
            }

            // Determine correct user and album
            $userId = 1; // default to admin
            $albumId = null;

            if (isset($tsvMappings[$key])) {
                $m = $tsvMappings[$key];
                if (isset($userMap[strtolower($m['player'])])) {
                    $userId = $userMap[strtolower($m['player'])];
                }
                if (isset($albumMap[strtolower($m['character'])])) {
                    $albumId = $albumMap[strtolower($m['character'])];
                }
            }

            // Determine correct image chain based on thumb/medium file presence
            $thKey = "images/$year/$month/$day/$name.th.$ext";
            $mdKey = "images/$year/$month/$day/$name.md.$ext";

            $chain = 4;
            if (isset($r2Files[$thKey]) && isset($r2Files[$mdKey])) {
                $chain = 7;
            } elseif (isset($r2Files[$thKey])) {
                $chain = 5;
            } elseif (isset($r2Files[$mdKey])) {
                $chain = 6;
            }

            // Insert new image
            $ins = $pdo->prepare("
                INSERT INTO chv_images (
                    image_name, image_extension, image_size, image_width, image_height,
                    image_date, image_date_gmt, image_user_id, image_album_id,
                    image_uploader_ip, image_storage_mode, image_storage_id, image_chain, image_checksum, image_original_filename, image_thumb_size
                ) VALUES (
                    ?, ?, 1024, 500, 500,
                    ?, ?, ?, ?,
                    '127.0.0.1', 'datefolder', 1, ?, '00', ?, 1024
                )
            ");


            $dateStr = "$year-$month-$day 12:00:00";
            $origFilename = "$name.$ext";
            $ins->execute([$name, $ext, $dateStr, $dateStr, $userId, $albumId, $chain, $origFilename]);
            $restoredCount++;

        }
    }

    // 4. Recalculate chv_stats
    $stmt = $pdo->query("SELECT COUNT(*) FROM chv_images");
    $totalImages = $stmt->fetchColumn();
    $stmt = $pdo->query("SELECT COUNT(*) FROM chv_albums");
    $totalAlbums = $stmt->fetchColumn();
    $stmt = $pdo->query("SELECT COUNT(*) FROM chv_users");
    $totalUsers = $stmt->fetchColumn();

    $pdo->prepare("UPDATE chv_stats SET stat_images = ?, stat_albums = ?, stat_users = ? WHERE stat_type = 'total'")
        ->execute([$totalImages, $totalAlbums, $totalUsers]);

    echo "SUCCESSFULLY RESTORED $restoredCount IMAGES TO THE DATABASE.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
