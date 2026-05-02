<?php
session_start();
define('ACCESS', 'web');
require_once '/var/www/html/app/legacy/load/php-boot.php';

$pdo = new PDO("mysql:host=chevereto-database-1;dbname=chevereto", "chevereto", "chevereto_secure_password_456");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Fetch all users for dropdown
$stmt_users = $pdo->query("SELECT user_id, user_username, user_name FROM chv_users ORDER BY user_username ASC");
$users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

$output_results = [];
$error_msg = '';

function cheveretoID($in, $action = 'encode') {
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
            $out = $out + strpos($index, substr((string)$in, $t, 1)) * $bcpow;
        }
        if ($id_padding > 0) {
            $out = $out / $id_padding;
            if (!is_numeric($out) || floor($out) != $out) {
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['zip_file'])) {
    try {
        $userId = (int)($_POST['target_user'] ?? 0);
        if ($userId <= 0) {
            throw new Exception("Please select a target user.");
        }

        if (empty($_POST['confirm_user'])) {
            throw new Exception("Please confirm that the target user is correct.");
        }

        $zip_file = $_FILES['zip_file']['tmp_name'];
        if (empty($zip_file) || !file_exists($zip_file)) {
            throw new Exception("Please upload a valid ZIP file.");
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_file) !== TRUE) {
            throw new Exception("Could not open the uploaded ZIP file.");
        }

        $extract_dir = '/tmp/import_' . uniqid();
        mkdir($extract_dir, 0777, true);
        $zip->extractTo($extract_dir);
        $zip->close();

        $dateDir = date('Y/m/d');
        $targetStorageDir = "/var/www/html/images/" . $dateDir . "/";
        if (!file_exists($targetStorageDir)) {
            mkdir($targetStorageDir, 0755, true);
        }

        $dateStr = date('Y-m-d H:i:s');

        // Scan character folders inside extracted ZIP
        $items = scandir($extract_dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || !is_dir($extract_dir . '/' . $item) || strpos($item, '__MACOSX') === 0) continue;

            $character_name = trim($item);

            // Get or create parent character album
            $stmt = $pdo->prepare("SELECT album_id FROM chv_albums WHERE album_user_id = ? AND album_name = ? AND album_parent_id IS NULL");
            $stmt->execute([$userId, $character_name]);
            $parent_album_id = $stmt->fetchColumn();

            if (!$parent_album_id) {
                $stmt = $pdo->prepare("INSERT INTO chv_albums (album_user_id, album_name, album_privacy, album_date, album_date_gmt, album_creation_ip) VALUES (?, ?, 'public', ?, ?, '127.0.0.1')");
                $stmt->execute([$userId, $character_name, $dateStr, $dateStr]);
                $parent_album_id = $pdo->lastInsertId();
            }

            // Loop through sub-folders for each category
            $sub_items = scandir($extract_dir . '/' . $item);
            foreach ($sub_items as $sub_item) {
                if ($sub_item === '.' || $sub_item === '..' || !is_dir($extract_dir . '/' . $item . '/' . $sub_item)) continue;

                $sub_album_name = trim($sub_item);

                // Check if sub-album exists
                $stmt = $pdo->prepare("SELECT album_id FROM chv_albums WHERE album_user_id = ? AND album_name = ? AND album_parent_id = ?");
                $stmt->execute([$userId, $sub_album_name, $parent_album_id]);
                $sub_album_id = $stmt->fetchColumn();

                if (!$sub_album_id) {
                    $stmt = $pdo->prepare("INSERT INTO chv_albums (album_user_id, album_name, album_parent_id, album_privacy, album_date, album_date_gmt, album_creation_ip) VALUES (?, ?, ?, 'public', ?, ?, '127.0.0.1')");
                    $stmt->execute([$userId, $sub_album_name, $parent_album_id, $dateStr, $dateStr]);
                    $sub_album_id = $pdo->lastInsertId();
                }

                $encoded_id = cheveretoID($sub_album_id, 'encode');
                $randomizer_url = "https://imagehut.ch/randomizer/" . $encoded_id . ".gif";

                // Process files inside sub-album
                $files = scandir($extract_dir . '/' . $item . '/' . $sub_item);
                $file_count = 0;
                foreach ($files as $file) {
                    $file_path = $extract_dir . '/' . $item . '/' . $sub_item . '/' . $file;
                    if ($file === '.' || $file === '..' || !is_file($file_path)) continue;

                    $file_info = pathinfo($file);
                    $ext = strtolower($file_info['extension'] ?? '');
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) continue;

                    $clean_base_name = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '_', $file_info['filename'])) . '_' . uniqid();
                    $destination_filename = $clean_base_name . '.' . $ext;
                    $full_dest_path = $targetStorageDir . $destination_filename;

                    // Copy file to server storage directory
                    copy($file_path, $full_dest_path);
                    chmod($full_dest_path, 0644);

                    // Insert image record into DB
                    $stmt = $pdo->prepare("
                        INSERT INTO chv_images 
                        (image_name, image_extension, image_date, image_date_gmt, image_storage_mode, image_storage_id, image_user_id, image_album_id, image_is_approved, image_uploader_ip, image_size, image_width, image_height, image_checksum, image_original_filename, image_views, image_chain, image_thumb_size, image_medium_size, image_frame_size, image_likes, image_is_animated, image_is_360, image_duration, image_path) 
                        VALUES 
                        (?, ?, ?, ?, 'datefolder', 1, ?, ?, 1, '127.0.0.1', ?, 500, 500, '000', ?, 0, 1, 0, 0, 0, 0, ?, 0, 0, ?)
                    ");
                    $file_size = filesize($full_dest_path);
                    $isAnimated = ($ext === 'gif') ? 1 : 0;
                    $stmt->execute([$clean_base_name, $ext, $dateStr, $dateStr, $userId, $sub_album_id, $file_size, $file, $isAnimated, $dateDir . '/']);
                    $file_count++;
                }

                $output_results[] = [
                    'character' => $character_name,
                    'album' => $sub_album_name,
                    'files_imported' => $file_count,
                    'randomizer_url' => $randomizer_url
                ];
            }
        }

        // Cleanup temporary directory
        $it = new RecursiveDirectoryIterator($extract_dir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files_clean = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files_clean as $file_clean) {
            if ($file_clean->isDir()){
                rmdir($file_clean->getRealPath());
            } else {
                unlink($file_clean->getRealPath());
            }
        }
        rmdir($extract_dir);

        // Refresh user counts
        $stmt_albums = $pdo->prepare("SELECT COUNT(*) FROM chv_albums WHERE album_user_id = ?");
        $stmt_albums->execute([$userId]);
        $total_albums = $stmt_albums->fetchColumn();

        $stmt_images = $pdo->prepare("SELECT COUNT(*) FROM chv_images WHERE image_user_id = ?");
        $stmt_images->execute([$userId]);
        $total_images = $stmt_images->fetchColumn();

        $stmt_update = $pdo->prepare("UPDATE chv_users SET user_album_count = ?, user_image_count = ? WHERE user_id = ?");
        $stmt_update->execute([$total_albums, $total_images, $userId]);

    } catch (Exception $e) {
        $error_msg = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Character Migration - ImageHut</title>
    <style>
        body {
            background-color: #1a1c23;
            color: #e2e8f0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 40px 20px;
        }
        .main-wrapper {
            max-width: 760px;
            margin: 0 auto;
            background: #242834;
            border-radius: 8px;
            padding: 35px;
            border: 1px solid #333a4d;
        }
        h1 {
            font-size: 26px;
            font-weight: 600;
            margin: 0 0 10px 0;
            color: #ffffff;
        }
        p.subtitle {
            margin: 0 0 25px 0;
            color: #9aa3b1;
            font-size: 14.5px;
            line-height: 1.5;
        }
        .field-box {
            margin-bottom: 22px;
        }
        label.field-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #cbd5e1;
            margin-bottom: 8px;
        }
        select.custom-select {
            width: 100%;
            background-color: #2b303f;
            border: 1px solid #3d465c;
            color: #ffffff;
            padding: 12px;
            border-radius: 6px;
            font-size: 14.5px;
            outline: none;
            box-sizing: border-box;
        }
        select.custom-select:focus {
            border-color: #5d6d8a;
        }
        .chk-box {
            display: flex;
            align-items: center;
            margin: 22px 0;
            cursor: pointer;
            user-select: none;
        }
        .chk-box input {
            width: 16px;
            height: 16px;
            margin-right: 10px;
            cursor: pointer;
        }
        .chk-box span {
            font-size: 14px;
            color: #cbd5e1;
        }
        .drop-uploader {
            border: 2px dashed #444c5e;
            border-radius: 8px;
            padding: 35px 20px;
            text-align: center;
            background: #1d212b;
            cursor: pointer;
            margin-bottom: 22px;
            transition: background 0.1s ease;
        }
        .drop-uploader:hover {
            background: #252a37;
            border-color: #64748b;
        }
        .drop-uploader span.primary-txt {
            font-size: 15px;
            font-weight: 500;
            color: #f1f5f9;
        }
        .drop-uploader span.sub-txt {
            display: block;
            font-size: 12.5px;
            color: #64748b;
            margin-top: 6px;
        }
        .import-btn {
            background-color: #3b82f6;
            color: #ffffff;
            font-size: 15px;
            font-weight: 600;
            padding: 14px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            width: 100%;
            transition: background 0.1s ease;
        }
        .import-btn:hover {
            background-color: #2563eb;
        }
        .tpl-download {
            display: inline-block;
            margin-top: 25px;
            color: #60a5fa;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
        }
        .tpl-download:hover {
            text-decoration: underline;
        }
        .error-banner {
            background-color: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 14px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 22px;
        }
        .results-box {
            margin-top: 35px;
            padding-top: 25px;
            border-top: 1px solid #333a4d;
        }
        .results-box h2 {
            font-size: 18px;
            margin: 0 0 15px 0;
            color: #ffffff;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
        }
        .results-table th, .results-table td {
            padding: 12px;
            text-align: left;
            font-size: 13.5px;
            border-bottom: 1px solid #333a4d;
        }
        .results-table th {
            color: #9aa3b1;
            font-weight: 600;
            background: #1d212b;
        }
        .results-table tr:hover td {
            background: #2c3241;
        }
        .r-url {
            color: #60a5fa;
            text-decoration: none;
            word-break: break-all;
        }
        .r-url:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="main-wrapper">
        <h1>Character Migration Tool</h1>
        <p class="subtitle">Select the destination account, confirm, and select your character template ZIP file.</p>

        <?php if (!empty($error_msg)): ?>
            <div class="error-banner"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <div class="field-box">
                <label class="field-label" for="target_user">Target Account Folder</label>
                <select class="custom-select" id="target_user" name="target_user" required>
                    <option value="">-- Choose Target Account --</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['user_id']; ?>" <?php echo (isset($_POST['target_user']) && $_POST['target_user'] == $u['user_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['user_username'] . ($u['user_name'] ? ' (' . $u['user_name'] . ')' : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <label class="chk-box">
                <input type="checkbox" name="confirm_user" value="1" required <?php echo !empty($_POST['confirm_user']) ? 'checked' : ''; ?>>
                <span>I confirm that I have selected the correct target account.</span>
            </label>

            <label class="drop-uploader" for="zip_file">
                <span class="primary-txt">Choose ZIP File</span>
                <span class="sub-txt">Supports standard character migration template files</span>
                <input id="zip_file" name="zip_file" type="file" accept=".zip" required>
            </label>

            <button type="submit" class="import-btn">Process and Import Character ZIP</button>
        </form>

        <a class="tpl-download" href="/character_template.zip" download>Download template ZIP file</a>

        <?php if (!empty($output_results)): ?>
            <div class="results-box">
                <h2>Import Results</h2>
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>Character</th>
                            <th>Category</th>
                            <th>Imported</th>
                            <th>Direct Link</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($output_results as $res): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($res['character']); ?></strong></td>
                                <td><?php echo htmlspecialchars($res['album']); ?></td>
                                <td><?php echo (int)$res['files_imported']; ?></td>
                                <td><a class="r-url" href="<?php echo htmlspecialchars($res['randomizer_url']); ?>" target="_blank"><?php echo htmlspecialchars($res['randomizer_url']); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
