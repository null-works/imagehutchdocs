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
        $known_folders = ['Portrait', 'Rectangle_Banner', 'Rectangle/Banner', 'Secondary Square', 'Square', 'Tertiary Square'];
        $source_dir = $extract_dir;
        $zip_folder_name = '';

        $items = scandir($extract_dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || strpos($item, '__MACOSX') === 0) continue;
            if (is_dir($extract_dir . '/' . $item)) {
                $sub_items = scandir($extract_dir . '/' . $item);
                foreach ($sub_items as $si) {
                    if (in_array(trim($si), $known_folders)) {
                        $source_dir = $extract_dir . '/' . $item;
                        $zip_folder_name = trim($item);
                        break 2;
                    }
                }
            }
        }

        $character_name = trim($_POST['character_name'] ?? '');
        if (empty($character_name)) {
            $character_name = !empty($zip_folder_name) ? $zip_folder_name : 'Unknown Character';
        }

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
        $sub_items = scandir($source_dir);
        foreach ($sub_items as $sub_item) {
            if ($sub_item === '.' || $sub_item === '..' || !is_dir($source_dir . '/' . $sub_item)) continue;

            $sub_album_name = trim($sub_item);

            // Map ZIP folder Rectangle_Banner to Rectangle/Banner in Chevereto
            if ($sub_album_name === 'Rectangle_Banner') {
                $sub_album_name = 'Rectangle/Banner';
            }

            // Skip Avatar URL just in case some legacy ZIPs contain it
            if ($sub_album_name === 'Avatar URL') {
                continue;
            }

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
            $files = scandir($source_dir . '/' . $sub_item);
            $file_count = 0;
            foreach ($files as $file) {
                $file_path = $source_dir . '/' . $sub_item . '/' . $file;
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
            background-color: #16181d;
            color: #d1d5db;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 40px 15px;
        }
        .main-container {
            max-width: 680px;
            margin: 0 auto;
            background: #1f232b;
            border: 1px solid #2d333f;
            border-radius: 6px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        h1 {
            font-size: 24px;
            font-weight: 600;
            margin: 0 0 10px 0;
            color: #ffffff;
        }
        p.subtitle {
            margin: 0 0 25px 0;
            color: #8b9bb4;
            font-size: 14px;
            line-height: 1.4;
        }
        .form-field {
            margin-bottom: 20px;
        }
        label.field-heading {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            margin-bottom: 8px;
        }
        select.form-input-select {
            width: 100%;
            background-color: #2b303d;
            border: 1px solid #3d4659;
            color: #ffffff;
            padding: 10px;
            border-radius: 4px;
            font-size: 14px;
            outline: none;
            box-sizing: border-box;
        }
        select.form-input-select:focus {
            border-color: #4a5568;
        }
        .confirm-wrapper {
            display: flex;
            align-items: center;
            margin: 20px 0;
            cursor: pointer;
        }
        .confirm-wrapper input {
            width: 16px;
            height: 16px;
            margin-right: 10px;
            cursor: pointer;
        }
        .confirm-wrapper span {
            font-size: 13.5px;
            color: #94a3b8;
        }
        .file-upload-box {
            background-color: #2b303d;
            border: 1px solid #3d4659;
            border-radius: 4px;
            padding: 18px;
            margin-bottom: 20px;
            box-sizing: border-box;
        }
        .file-upload-box input[type="file"] {
            width: 100%;
            color: #ffffff;
            font-size: 13.5px;
            cursor: pointer;
            box-sizing: border-box;
        }
        .submit-import-btn {
            background-color: #2563eb;
            color: #ffffff;
            font-size: 14.5px;
            font-weight: 600;
            padding: 12px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.1s ease;
        }
        .submit-import-btn:hover {
            background-color: #1d4ed8;
        }
        .download-tpl-btn {
            display: inline-block;
            margin-top: 20px;
            color: #38bdf8;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
        }
        .download-tpl-btn:hover {
            text-decoration: underline;
        }
        .error-message {
            background-color: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 12px;
            border-radius: 4px;
            font-size: 13.5px;
            margin-bottom: 20px;
        }
        .import-results-container {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 1px solid #2d333f;
        }
        .import-results-container h2 {
            font-size: 18px;
            margin: 0 0 15px 0;
            color: #ffffff;
        }
        .results-list-table {
            width: 100%;
            border-collapse: collapse;
        }
        .results-list-table th, .results-list-table td {
            padding: 10px;
            text-align: left;
            font-size: 13px;
            border-bottom: 1px solid #2d333f;
        }
        .results-list-table th {
            color: #94a3b8;
            font-weight: 600;
            background: #2b303d;
        }
        .results-list-table tr:hover td {
            background: #232835;
        }
        .link-url-text {
            color: #38bdf8;
            text-decoration: none;
            word-break: break-all;
        }
        .link-url-text:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <h1>Character Migration Tool</h1>
        <p class="subtitle">Select destination account, confirm, and select your character template ZIP file to begin importing.</p>

        <?php if (!empty($error_msg)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <div class="form-field">
                <label class="field-heading" for="target_user">Target Destination Account</label>
                <select class="form-input-select" id="target_user" name="target_user" required>
                    <option value="">-- Choose Target Account --</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['user_id']; ?>" <?php echo (isset($_POST['target_user']) && $_POST['target_user'] == $u['user_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($u['user_username'] . ($u['user_name'] ? ' (' . $u['user_name'] . ')' : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-field">
                <label class="field-heading" for="character_name">Character Name (Optional)</label>
                <input type="text" id="character_name" name="character_name" placeholder="E.g. Kimberly Parson" style="background-color: #2b303d; border: 1px solid #3d4659; border-radius: 4px; color: #ffffff; font-size: 14px; padding: 12px; width: 100%; box-sizing: border-box;" value="<?php echo isset($_POST['character_name']) ? htmlspecialchars($_POST['character_name']) : ''; ?>">
            </div>

            <label class="confirm-wrapper">
                <input type="checkbox" name="confirm_user" value="1" required <?php echo !empty($_POST['confirm_user']) ? 'checked' : ''; ?>>
                <span>I confirm that I have selected the correct target account.</span>
            </label>

            <div class="file-upload-box">
                <label class="field-heading" style="margin-bottom: 10px; display: block;">Select Character ZIP File</label>
                <input id="zip_file" name="zip_file" type="file" accept=".zip" required>
            </div>

            <button type="submit" class="submit-import-btn">Process and Import Character ZIP</button>
        </form>

        <a class="download-tpl-btn" href="/character_template.zip" download>Download template ZIP file</a>

        <?php if (!empty($output_results)): ?>
            <div class="import-results-container">
                <h2>Import Results</h2>
                <table class="results-list-table">
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
                                <td><a class="link-url-text" href="<?php echo htmlspecialchars($res['randomizer_url']); ?>" target="_blank"><?php echo htmlspecialchars($res['randomizer_url']); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
