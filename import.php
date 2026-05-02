<?php
define('ACCESS', true);
require_once '/var/www/html/app/legacy/load/loader.php';

// Only logged in users can access the tool
if (!CHV\Login::isLoggedUser()) {
    CHV\Redirect::to(get_base_url());
}

$currentUser = CHV\Login::getUser();
$userId = $currentUser['id'];

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

        $pdo = new PDO("mysql:host=chevereto-database-1;dbname=chevereto", "chevereto", "chevereto_secure_password_456");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
    <title>Character Migration Tool - ImageHut</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0b0f19;
            --surface: rgba(30, 41, 59, 0.45);
            --border: rgba(255, 255, 255, 0.08);
            --accent: linear-gradient(135deg, #a855f7, #6366f1, #3b82f6);
            --text: #f1f5f9;
        }
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #090d16, #111827, #18112b);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding-top: 50px;
        }
        .container {
            width: 90%;
            max-width: 800px;
            background: var(--surface);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.4);
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        h1 {
            font-size: 32px;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 10px;
            background: var(--accent);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        p {
            color: #94a3b8;
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .upload-zone {
            border: 2px dashed rgba(168, 85, 247, 0.4);
            border-radius: 14px;
            padding: 40px 20px;
            text-align: center;
            background: rgba(168, 85, 247, 0.03);
            cursor: pointer;
            transition: all 0.25s ease-in-out;
            margin-bottom: 25px;
        }
        .upload-zone:hover {
            border-color: #a855f7;
            background: rgba(168, 85, 247, 0.07);
            transform: translateY(-2px);
        }
        .upload-zone span {
            display: block;
            font-size: 14px;
            color: #cbd5e1;
            margin-top: 10px;
        }
        input[type="file"] {
            display: none;
        }
        .btn-action {
            background: var(--accent);
            border: none;
            color: white;
            padding: 14px 28px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.25s ease-in-out;
            box-shadow: 0 4px 15px rgba(168, 85, 247, 0.3);
        }
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(168, 85, 247, 0.4);
        }
        .template-link {
            display: inline-block;
            margin-top: 15px;
            color: #3b82f6;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s;
        }
        .template-link:hover {
            color: #60a5fa;
            text-decoration: underline;
        }
        .alert {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #f87171;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        th, td {
            padding: 14px 18px;
            text-align: left;
            font-size: 14px;
        }
        th {
            background: rgba(30, 41, 59, 0.6);
            color: #f1f5f9;
            font-weight: 600;
        }
        tr {
            background: rgba(30, 41, 59, 0.2);
            border-bottom: 1px solid var(--border);
        }
        tr:hover {
            background: rgba(30, 41, 59, 0.4);
        }
        .randomizer-link {
            color: #38bdf8;
            text-decoration: none;
            word-break: break-all;
            font-weight: 500;
        }
        .randomizer-link:hover {
            color: #7dd3fc;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Character Migration Tool</h1>
        <p>Drag and drop your character folder ZIP into the box below. The tool will parse it, place the character images in the proper folders, and produce clickable randomizer links.</p>

        <?php if (!empty($error_msg)): ?>
            <div class="alert"><?php echo htmlspecialchars($error_msg); ?></div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <label class="upload-zone" for="zip_file">
                <span style="font-size: 32px; color: #a855f7; margin-bottom: 5px;">📦</span>
                <span>Select or drop a ZIP file here to import your characters</span>
                <input id="zip_file" name="zip_file" type="file" accept=".zip" required onchange="this.form.submit()">
            </label>
        </form>

        <a class="template-link" href="/character_template.zip" download>📥 Download Character Import Template ZIP</a>

        <?php if (!empty($output_results)): ?>
            <h2 style="font-size: 20px; margin-top: 40px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">Newly Imported Sub-Albums & Links</h2>
            <table>
                <thead>
                    <tr>
                        <th>Character</th>
                        <th>Sub-Album</th>
                        <th>Files</th>
                        <th>Randomizer URL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($output_results as $res): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($res['character']); ?></strong></td>
                            <td><?php echo htmlspecialchars($res['album']); ?></td>
                            <td><?php echo (int)$res['files_imported']; ?></td>
                            <td><a class="randomizer-link" href="<?php echo htmlspecialchars($res['randomizer_url']); ?>" target="_blank"><?php echo htmlspecialchars($res['randomizer_url']); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
