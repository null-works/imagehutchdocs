<?php
// Simple single-file management tool for assigning unmapped images to character folders
ob_start();
session_start();

try {
    $pdo = new PDO('mysql:host=chevereto-database-1;dbname=chevereto', 'chevereto', 'chevereto_secure_password_456');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Handle Form Submission or Delete
    $message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action']) && $_POST['action'] === 'generate_subalbums') {
            $stmt = $pdo->query("SELECT album_id, album_user_id FROM chv_albums WHERE album_parent_id IS NULL");
            $all_parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $createdCount = 0;
            $standard_subs = ['Portrait', 'Rectangle/Banner', 'Secondary Square', 'Square', 'Tertiary Square'];


            foreach ($all_parents as $parent) {
                $parentId = $parent['album_id'];
                $userId = $parent['album_user_id'];

                // Fetch existing sub-albums for this parent
                $stmt_sub = $pdo->prepare("SELECT album_name FROM chv_albums WHERE album_parent_id = ?");
                $stmt_sub->execute([$parentId]);
                $existing_subs = $stmt_sub->fetchAll(PDO::FETCH_COLUMN);

                foreach ($standard_subs as $std) {
                    if (!in_array($std, $existing_subs)) {
                        $ins = $pdo->prepare("
                            INSERT INTO chv_albums 
                            (album_name, album_user_id, album_date, album_date_gmt, album_creation_ip, album_privacy, album_parent_id) 
                            VALUES (?, ?, NOW(), NOW(), '127.0.0.1', 'public', ?)
                        ");
                        $ins->execute([$std, $userId, $parentId]);
                        $createdCount++;
                    }
                }
            }
            $message = "Successfully created $createdCount missing sub-albums.";
        } elseif (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['image_id'])) {
            $imageId = (int)$_POST['image_id'];
            $stmt = $pdo->prepare("DELETE FROM chv_images WHERE image_id = ?");
            $stmt->execute([$imageId]);
            $message = "Successfully deleted image ID $imageId.";
        } elseif (isset($_POST['image_id'], $_POST['album_id'])) {
            $imageId = (int)$_POST['image_id'];
            $albumId = (int)$_POST['album_id'];

            if ($albumId > 0) {
                // Fetch correct user for that album
                $stmt = $pdo->prepare("SELECT album_user_id FROM chv_albums WHERE album_id = ?");
                $stmt->execute([$albumId]);
                $userId = $stmt->fetchColumn();

                if ($userId) {
                    $upd = $pdo->prepare("UPDATE chv_images SET image_user_id = ?, image_album_id = ? WHERE image_id = ?");
                    $upd->execute([$userId, $albumId, $imageId]);
                    $message = "Successfully assigned image ID $imageId.";
                } else {
                    $message = "Album not found.";
                }
            }
        }

        // Refresh image counts for all albums
        $pdo->exec("
            UPDATE chv_albums a
            SET a.album_image_count = (
                SELECT COUNT(*) 
                FROM chv_images i 
                WHERE i.image_album_id = a.album_id
            )
        ");
    }

    // 2. Fetch all albums with player username and parent character name
    $stmt = $pdo->query("
        SELECT a.album_id, a.album_name, p.album_name as character_name, u.user_name as player_name 
        FROM chv_albums a 
        LEFT JOIN chv_albums p ON a.album_parent_id = p.album_id
        LEFT JOIN chv_users u ON a.album_user_id = u.user_id 
        ORDER BY u.user_name ASC, p.album_name ASC, a.album_name ASC
    ");
    $albums = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Determine Sorting
    $sort = $_GET['sort'] ?? 'newest';
    $orderClause = "image_id DESC";
    if ($sort === 'oldest') {
        $orderClause = "image_id ASC";
    } elseif ($sort === 'name') {
        $orderClause = "image_name ASC";
    } elseif ($sort === 'width_desc') {
        $orderClause = "image_width DESC";
    } elseif ($sort === 'width_asc') {
        $orderClause = "image_width ASC";
    }

    // 4. Fetch all remaining images assigned to Admin (user_id = 1)
    $stmt = $pdo->query("SELECT image_id, image_name, image_extension, image_date, image_width, image_height FROM chv_images WHERE image_user_id = 1 ORDER BY $orderClause");
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chevereto Image Assignment Tool</title>
    <style>
        :root {
            --bg-color: #0f172a;
            --panel-bg: #1e293b;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent-color: #38bdf8;
            --border-color: #334155;
            --btn-bg: #0284c7;
            --btn-hover: #0369a1;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            margin: 0;
            padding: 40px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        header {
            text-align: center;
            margin-bottom: 40px;
        }
        h1 {
            color: var(--text-primary);
            font-size: 2.5rem;
            margin-bottom: 8px;
            font-weight: 800;
            letter-spacing: -0.025em;
        }
        p.subtitle {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }
        .controls-wrapper {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 35px;
            background-color: var(--panel-bg);
            padding: 15px 25px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }
        .sort-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .control-label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.95rem;
        }
        .sort-link {
            background-color: var(--bg-color);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .sort-link.active, .sort-link:hover {
            background-color: var(--accent-color);
            color: var(--bg-color);
            border-color: var(--accent-color);
        }
        .message {
            background-color: #15803d;
            color: #bbf7d0;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 500;
            border: 1px solid #166534;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }
        .card {
            background-color: var(--panel-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 18px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px -10px rgba(0,0,0,0.4);
        }
        .thumb-wrapper {
            background-color: #0c111d;
            border-radius: 8px;
            overflow: hidden;
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            border: 1px solid var(--border-color);
        }
        .thumb-wrapper img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .img-details {
            margin-bottom: 16px;
        }
        .img-name {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 4px;
        }
        .img-meta {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .search-field {
            background-color: var(--bg-color);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 10px;
            border-radius: 6px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s ease;
        }
        .search-field:focus {
            border-color: var(--accent-color);
        }
        select {
            background-color: var(--bg-color);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 10px;
            border-radius: 6px;
            font-size: 0.9rem;
            outline: none;
            cursor: pointer;
            transition: border-color 0.2s ease;
        }
        select:focus {
            border-color: var(--accent-color);
        }
        button {
            background-color: var(--btn-bg);
            color: var(--text-primary);
            border: none;
            padding: 11px;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        button:hover {
            background-color: var(--btn-hover);
        }
        .empty-state {
            text-align: center;
            grid-column: 1 / -1;
            padding: 60px;
            background-color: var(--panel-bg);
            border-radius: 12px;
            border: 1px dashed var(--border-color);
        }
    </style>
    <script>
        function filterDropdown(input) {
            const filter = input.value.toLowerCase();
            const select = input.nextElementSibling;
            const options = select.getElementsByTagName('option');
            let firstMatch = null;

            for (let i = 1; i < options.length; i++) {
                const text = options[i].textContent || options[i].innerText;
                if (text.toLowerCase().indexOf(filter) > -1) {
                    options[i].style.display = "";
                    if (!firstMatch) {
                        firstMatch = options[i];
                    }
                } else {
                    options[i].style.display = "none";
                }
            }
            if (firstMatch) {
                firstMatch.selected = true;
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <header>
            <h1>Image Assignment Tool</h1>
            <p class="subtitle">Assign the remaining unmapped images to a character's folder.</p>
        </header>

        <div class="controls-wrapper">
            <div class="sort-controls">
                <span class="control-label">Sort By:</span>
                <a href="?sort=newest" class="sort-link <?= $sort === 'newest' ? 'active' : '' ?>">Newest</a>
                <a href="?sort=oldest" class="sort-link <?= $sort === 'oldest' ? 'active' : '' ?>">Oldest</a>
                <a href="?sort=width_desc" class="sort-link <?= $sort === 'width_desc' ? 'active' : '' ?>">Largest Pixels</a>
                <a href="?sort=width_asc" class="sort-link <?= $sort === 'width_asc' ? 'active' : '' ?>">Smallest Pixels</a>
            </div>
            <form action="" method="POST" style="display: inline-block; margin: 0; padding: 0;">
                <button type="submit" name="action" value="generate_subalbums" style="background-color: var(--accent-color); color: var(--bg-color); font-size: 0.9rem; padding: 8px 16px; border-radius: 6px; font-weight: 700; border: none; cursor: pointer;">Generate Missing Sub-albums</button>
            </form>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="grid" id="image-grid">
            <?php if (empty($images)): ?>
                <div class="empty-state">
                    <h3>All images have been successfully assigned!</h3>
                    <p class="subtitle">There are no more unmapped images owned by the admin.</p>
                </div>
            <?php else: ?>
                <?php foreach ($images as $img): ?>
                    <?php
                        $m = [];
                        if (preg_match('#^([0-9]{4})-([0-9]{2})-([0-9]{2})#', $img['image_date'], $m)) {
                            $src = "https://media.imagehut.ch/images/{$m[1]}/{$m[2]}/{$m[3]}/{$img['image_name']}.th.{$img['image_extension']}";
                            $full = "https://media.imagehut.ch/images/{$m[1]}/{$m[2]}/{$m[3]}/{$img['image_name']}.{$img['image_extension']}";
                        } else {
                            $src = "https://media.imagehut.ch/images/{$img['image_name']}.th.{$img['image_extension']}";
                            $full = "https://media.imagehut.ch/images/{$img['image_name']}.{$img['image_extension']}";
                        }
                    ?>
                    <div class="card">
                        <div class="thumb-wrapper">
                            <a href="<?= htmlspecialchars($full) ?>" target="_blank" title="View full image in new tab" style="display: block; width: 100%; height: 100%; text-align: center;">
                                <img src="<?= htmlspecialchars($src) ?>" alt="Image Preview">
                            </a>
                        </div>
                        <div class="img-details">
                            <div class="img-name">
                                <?= htmlspecialchars($img['image_name'] . '.' . $img['image_extension']) ?>
                                <a href="<?= htmlspecialchars($full) ?>" target="_blank" style="color: var(--accent-color); font-size: 0.85rem; margin-left: 6px; text-decoration: none;" title="Open original in new tab">
                                    <span class="fas fa-external-link-alt"></span> View Full
                                </a>
                            </div>
                            <div class="img-meta">Dimensions: <?= $img['image_width'] ?> x <?= $img['image_height'] ?> px</div>
                            <div class="img-meta">Date: <?= htmlspecialchars(substr($img['image_date'], 0, 10)) ?></div>
                        </div>
                        <form action="" method="POST">
                            <input type="hidden" name="image_id" value="<?= $img['image_id'] ?>">
                            <input type="text" class="search-field" placeholder="Type to filter folders..." onkeyup="filterDropdown(this)">
                            <select name="album_id" required>
                                <option value="" disabled selected>Select Character Folder...</option>
                                <?php foreach ($albums as $album): ?>
                                    <?php 
                                        $label = '';
                                        $pName = !empty($album['player_name']) ? $album['player_name'] : 'ihadmin';
                                        if (!empty($album['character_name'])) {
                                            $label = "[{$pName}] {$album['character_name']} - {$album['album_name']}";
                                        } else {
                                            $label = "[{$pName}] {$album['album_name']}";
                                        }
                                    ?>
                                    <option value="<?= $album['album_id'] ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit">Assign Character Folder</button>
                            <button type="submit" name="action" value="delete" formnovalidate style="background-color: #ef4444; margin-top: 2px;" onmouseover="this.style.backgroundColor='#dc2626'" onmouseout="this.style.backgroundColor='#ef4444'" onclick="return confirm('Are you sure you want to delete this image completely?');">Delete Image</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
