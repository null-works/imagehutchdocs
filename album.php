<?php

use function Chevereto\Legacy\arr_printer;
use function Chevereto\Legacy\G\get_base_url;
use function Chevereto\Legacy\G\get_global;
use Chevereto\Legacy\G\Handler;
use function Chevereto\Legacy\G\require_theme_file;
use function Chevereto\Legacy\G\require_theme_file_return;
use function Chevereto\Legacy\G\require_theme_footer;
use function Chevereto\Legacy\G\require_theme_header;
use function Chevereto\Legacy\getSetting;
use function Chevereto\Legacy\isShowEmbedContent;
use function Chevereto\Legacy\show_banner;
use function Chevereto\Legacy\time_elapsed_string;
use function Chevereto\Vars\request;

// @phpstan-ignore-next-line
if (!defined('ACCESS') || !ACCESS) {
    die('This file cannot be directly accessed.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_hierarchy') {
    try {
        $pdo_gen = new PDO('mysql:host=chevereto-database-1;dbname=chevereto', 'chevereto', 'chevereto_secure_password_456');
        $pdo_gen->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $parent_album_id = Handler::var('album')['id'];
        $user_id = Handler::var('album')['user_id'];
        $char_name = trim($_POST['character_name'] ?? '');

        if ($char_name !== '') {
            // 1. Create the top-level character album inside the current album
            $stmt_new_char = $pdo_gen->prepare("INSERT INTO chv_albums (album_name, album_user_id, album_parent_id, album_date, album_date_gmt, album_creation_ip) VALUES (?, ?, ?, NOW(), NOW(), '127.0.0.1')");
            $stmt_new_char->execute([$char_name, $user_id, $parent_album_id]);
            $new_char_album_id = $pdo_gen->lastInsertId();

            // 2. Create the 5 standard sub-albums inside that new character album
            $sub_albums_to_create = [
                'Portrait',
                'Rectangle/Banner',
                'Secondary Square',
                'Square',
                'Tertiary Square'
            ];

            foreach ($sub_albums_to_create as $sub_name) {
                $stmt_insert = $pdo_gen->prepare("INSERT INTO chv_albums (album_name, album_user_id, album_parent_id, album_date, album_date_gmt, album_creation_ip) VALUES (?, ?, ?, NOW(), NOW(), '127.0.0.1')");
                $stmt_insert->execute([$sub_name, $user_id, $new_char_album_id]);
            }
        }

        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    } catch (Exception $e) {
        // Fallback silently
    }
}
?>
<?php require_theme_header(); ?>

<div class="content-width">
	<?php show_banner('album_before_header', Handler::var('listing')->sfw()); ?>
    <div class="header header-content margin-bottom-10 margin-top-10">
		<div class="header-content-left">
			<div class="header-content-breadcrum">
            <?php
                if (Handler::var('album')['user']['id']) {
                    require_theme_file("snippets/breadcrum_owner_card");
                } ?>
                <div class="breadcrum-item buttons" data-contains="cta-album">
                <?php echo Handler::var('album')['cta_html']; ?>
                </div>
			</div>
		</div>
		<div class="header-content-right breaks-ui buttons">
<?php if((Handler::var('ancestors')[1] ?? []) !== []) { ?>
            <a href="<?php echo Handler::var('ancestors')[1]['url']; ?>/sub" class="btn btn-small default" title="<?php echo _s("Go up to: %s", Handler::var('ancestors')[1]['name_html']); ?>"><span class="icon fas fa-level-up-alt"></span></a>
<?php } ?>
        <?php
                if (Handler::cond('owner') || Handler::cond('content_manager')) {
                    ?>
                    <a data-action="edit" title="<?php _se('Edit'); ?> (E)" class="btn btn-small default" data-modal="edit"><span class="icon fas fa-edit"></span></a>
                    <a data-action="sub-album" title="<?php _se('Sub album'); ?> (J)" class="btn btn-small default" data-modal="edit" data-target="new-sub-album"><span class="icon fas fa-folder-tree"></span></a>
					<?php
                    if (Handler::cond('allowed_to_delete_content')) {
                        ?>
							<a data-action="delete" title="<?php _se('Delete'); ?> (Del)" class="btn btn-small default" data-confirm="<?php _se("Do you really want to delete this %a and all of its %i?", ['%a' => _n('album', 'albums', 1), '%i' => _n('file', 'files', 20)]); ?> <?php _se("This can't be undone."); ?>" data-submit-fn="CHV.fn.submit_resource_delete" data-ajax-deferred="CHV.fn.complete_resource_delete" data-ajax-url="<?php echo get_base_url("json"); ?>"><span class="icon fas fa-trash-alt"></span></a>
					<?php
                    } ?>
				<?php
                }
                ?>
        <?php
            if (Handler::cond('owner')) {
                if (getSetting('upload_gui') == 'js' && getSetting('homepage_style') !== 'route_upload') {
                    $createAlbumTag = 'button';
                    $createAlbumAttr = 'data-trigger="anywhere-upload-input" data-action="upload-to-album" title="' . _s('Upload to album') . ' (P)"';
                } else {
                    $createAlbumTag = 'a';
                    $createAlbumAttr = 'href="' . get_base_url(sprintf('upload/?toAlbum=%s', Handler::var('album')['id_encoded'])) . '"';
                } ?>
				<<?php echo $createAlbumTag; ?> class="btn btn-small default" <?php echo $createAlbumAttr; ?>><span class="btn-icon fas fa-cloud-upload-alt"></span></<?php echo $createAlbumTag; ?>>
			<?php
            }
            ?>
            <?php
            if (getSetting('theme_show_social_share')) {
                ?>
                <a class="btn btn-small default" data-modal="simple" data-target="modal-random" title="<?php _se('Randomizer'); ?>"><span class="btn-icon fas fa-random"></span></a>

				<a class="btn btn-small default" data-action="share" title="<?php _se('Share'); ?> (S)"><span class="btn-icon fas fa-share-alt"></span></a>
			<?php
            }
            ?>
            <?php
            if (getSetting('enable_likes')) {
                ?>
				<a title="<?php _se('Like'); ?> (L)" class="btn-like" data-type="album" data-id="<?php echo Handler::var('album')['id_encoded']; ?>" data-liked="<?php echo (int) (Handler::var('album')['liked'] ?? '0'); ?>">
					<span data-action="like" class="btn btn-small default btn-liked" rel="tooltip" title="<?php _se("You like this"); ?>"><span class="btn-icon fas fa-heart"></span><span class="btn-text" data-text="likes-count"><?php echo Handler::var('album')['likes']; ?></span></span>
					<span class="btn btn-small default btn-unliked" data-action="like"><span class="btn-icon far fa-heart"></span><span class="btn-text" data-text="likes-count"><?php echo Handler::var('album')['likes']; ?></span></span>
				</a>
			<?php
            }
            ?>
		</div>
	</div>
    <div class="header margin-bottom-10">
<?php
if(Handler::var('breadcrumbs') !== []) {
    $breadcrumbs = require_theme_file_return('snippets/breadcrumbs');
    echo '<div class="margin-bottom-5">';
    echo $breadcrumbs(Handler::var('breadcrumbs'));
    echo '</div>';
}
?>
<?php
try {
    $parent_url = null;
    if (!empty(Handler::var('album')['parent_id'])) {
        $pdo_p = new PDO('mysql:host=chevereto-database-1;dbname=chevereto', 'chevereto', 'chevereto_secure_password_456');
        $pdo_p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt_p = $pdo_p->prepare("SELECT * FROM chv_albums WHERE album_id = ?");
        $stmt_p->execute([Handler::var('album')['parent_id']]);
        $parent_alb = $stmt_p->fetch(PDO::FETCH_ASSOC);
        if ($parent_alb) {
            $parent_url = get_base_url("album/" . \Chevereto\Legacy\encodeID((int)$parent_alb['album_id']));
        }
    }
} catch (Exception $e) {
    $parent_url = null;
}
if (!empty($parent_url)): ?>
    <div style="margin-bottom: 15px;">
        <a href="<?php echo $parent_url; ?>" style="display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); padding: 8px 14px; border-radius: 6px; color: #38bdf8; font-weight: 600; text-decoration: none; font-size: 0.95rem;" onmouseover="this.style.background='rgba(255,255,255,0.1)'" onmouseout="this.style.background='rgba(255,255,255,0.06)'">
            <span class="fas fa-arrow-left"></span> Go back to parent
        </a>
    </div>
<?php endif; ?>
        <h1 class="header-title phone-float-none viewer-title">
            <a data-text="album-name" href="<?php echo Handler::var('album')["url"]; ?>"><?php echo Handler::var('album')["name_html"]; ?></a>
        </h1>
    </div>
    <div class="description-meta margin-bottom-10 overflow-auto">
        <div class="header-content-left">
            <span class="icon far fa-eye-slash <?php if (Handler::var('album')["privacy"] == "public") {
                echo "soft-hidden";
            } ?>" data-content="privacy-private" title="<?php _se('This content is private'); ?>" rel="tooltip"></span>
           <span class="fas fa-photo-film"></span> <span data-text="image-count"><?php echo Handler::var('album')["image_count"]; ?></span> <span data-text="image-label" data-label-single="<?php _ne('file', 'files', 1); ?>" data-label-plural="<?php _ne('file', 'files', 20); ?>"><?php _ne('file', 'files', Handler::var('album')['image_count']); ?></span> — <?php echo '<span title="' . Handler::var('album')['date_fixed_peer'] . '">' . time_elapsed_string(Handler::var('album')['date_gmt']) . '</span>'; ?> — <span class="far fa-views"></span><?php echo Handler::var('album')['views']; ?> <?php echo Handler::var('album')['views_label']; ?>
        </div>
    </div>
<?php
$tags = require_theme_file_return('snippets/tags_filter');
echo $tags(Handler::var('tags_display'), Handler::var('tags_active'));
?>
	<?php show_banner('album_after_header', Handler::var('listing')->sfw()); ?>
    <div class="description-meta margin-bottom-10 hide-empty" data-text="album-description"><?php echo nl2br(trim(Handler::var('album_safe_html')['description'] ?? '')); ?></div>
</div>

<div class="top-sub-bar follow-scroll margin-bottom-5">
    <div class="content-width">
        <div class="header header-tabs no-select">
            <?php require_theme_file("snippets/tabs"); ?>
            <?php
            if (Handler::cond('owner') || Handler::cond('content_manager')) {
                require_theme_file("snippets/user_items_editor"); ?>
                <div class="header-content-right">
                    <?php require_theme_file("snippets/listing_tools_editor"); ?>
                </div>
            <?php
            }
            ?>
        </div>
    </div>
</div>
<style>
#tab-sub-link .sub-albums-close-x,
[data-tab="sub-albums"] .sub-albums-close-x {
    display: none;
    font-size: 1.15rem;
    color: #f43f5e;
    font-weight: bold;
    margin-left: 8px;
    cursor: pointer;
    text-decoration: none;
    vertical-align: middle;
}
#tab-sub-link.current .sub-albums-close-x,
[data-tab="sub-albums"].current .sub-albums-close-x,
#tab-sub-link.active .sub-albums-close-x,
[data-tab="sub-albums"].active .sub-albums-close-x,
.sub-albums-filemanager-active .sub-albums-close-x {
    display: inline-block !important;
}
</style>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var subAlbumsTab = document.getElementById("tab-sub-link") || document.querySelector('[data-tab="sub-albums"]') || document.querySelector('.tab-sub-link');
    if (subAlbumsTab && !subAlbumsTab.querySelector('.sub-albums-close-x')) {
        var closeLink = document.createElement('a');
        closeLink.href = "<?php echo Handler::var('album')['url']; ?>";
        closeLink.className = 'sub-albums-close-x';
        closeLink.innerHTML = '×';
        closeLink.title = "Close sub-albums view";
        subAlbumsTab.appendChild(closeLink);
    }
    
    if (window.location.href.indexOf('/sub') !== -1) {
        document.body.classList.add('sub-albums-filemanager-active');
        var xBtn = document.querySelector('.sub-albums-close-x');
        if (xBtn) {
            xBtn.style.display = 'inline-block';
        }
    }
});
</script>

<div class="content-width">
    <?php
    try {
        $pdo_sub = new PDO('mysql:host=chevereto-database-1;dbname=chevereto', 'chevereto', 'chevereto_secure_password_456');
        $pdo_sub->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt_sub = $pdo_sub->prepare("SELECT * FROM chv_albums WHERE album_parent_id = ? ORDER BY album_name ASC");
        $stmt_sub->execute([Handler::var('album')['id']]);
        $sub_albums = $stmt_sub->fetchAll(PDO::FETCH_ASSOC);

        $stmt_is_root = $pdo_sub->prepare("SELECT album_parent_id FROM chv_albums WHERE album_id = ?");
        $stmt_is_root->execute([Handler::var('album')['id']]);
        $parent_id_val = $stmt_is_root->fetchColumn();
        $is_root_album = empty($parent_id_val);
    } catch (Exception $e) {
        $sub_albums = [];
        $is_root_album = false;
    }
    ?>


    <?php if (!empty($sub_albums)): ?>
    <div class="sub-albums-filemanager" style="margin-top: 15px; margin-bottom: 30px;">
        <h2 style="font-size: 1.25rem; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; gap: 8px; color: var(--text-primary);">
            <span style="display: flex; align-items: center; gap: 8px;">
                <span class="fas fa-folder-tree" style="color: #38bdf8;"></span> Sub-albums (<?php echo count($sub_albums); ?>)
            </span>
            <?php if((Handler::var('ancestors')[1] ?? []) !== []): ?>
                <a href="<?php echo Handler::var('ancestors')[1]['url']; ?>" style="font-size: 1rem; color: #94a3b8; text-decoration: none; display: flex; align-items: center; gap: 6px;" title="Go back to parent album">
                    <span class="fas fa-times"></span> Close
                </a>
            <?php endif; ?>
        </h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 15px;">
            <?php foreach ($sub_albums as $sub): ?>
                <?php
                    try {
                        $album_url = get_base_url("album/" . \Chevereto\Legacy\encodeID((int)$sub['album_id']));
                    } catch (\Throwable $e) {
                        $album_url = get_base_url("album/" . $sub['album_id']);
                    }
                ?>
                <a href="<?php echo $album_url; ?>" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 12px 16px; display: flex; align-items: center; gap: 12px; text-decoration: none; color: inherit; transition: all 0.2s ease;" onmouseover="this.style.background='rgba(255,255,255,0.08)'" onmouseout="this.style.background='rgba(255,255,255,0.04)'">
                    <span class="fas fa-folder" style="font-size: 1.5rem; color: #facc15;"></span>
                    <div style="overflow: hidden;">
                        <div style="font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-primary);"><?php echo htmlspecialchars($sub['album_name']); ?></div>
                        <div style="font-size: 0.8rem; color: #94a3b8;"><?php echo $sub['album_image_count']; ?> files</div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div id="content-listing-tabs" class="tabbed-listing">
		<div id="tabbed-content-group">
			<?php
            require_theme_file("snippets/listing");
            ?>
			<?php if (isShowEmbedContent()) {
                ?>
				<div id="tab-embeds" class="tabbed-content padding-10">
						<div class="content-listing-loading"></div>
						<div id="embed-codes" class="input-label margin-bottom-0 margin-top-0 soft-hidden">
							<label for="album-embed-toggle"><?php _se('Embed codes'); ?></label>
							<div class="c8 margin-bottom-10">
								<select name="album-embed-toggle" id="album-embed-toggle" class="text-input" data-combo="album-embed-toggle-combo">
									<?php
                                    foreach (get_global('embed_share_tpl') as $key => $value) {
                                        echo '<optgroup label="' . $value['label'] . '">' . "\n";
                                        foreach ($value['options'] as $k => $v) {
                                            echo '	<option value="' . $k . '" data-size="' . $v["size"] . '">' . $v["label"] . '</option>' . "\n";
                                        }
                                        echo '</optgroup>';
                                    } ?>
								</select>
							</div>
							<div id="album-embed-toggle-combo" class="position-relative">
								<?php
                                $i = 0;
                foreach (get_global('embed_share_tpl') as $key => $value) {
                    foreach ($value['options'] as $k => $v) {
                        echo '<div data-combo-value="' . $k . '" class="switch-combo' . ($i > 0 ? " soft-hidden" : "") . '">
										<textarea id="album-embed-code-' . $i . '" class="r8 resize-vertical" name="' . $k . '" data-size="' . $v["size"] . '" data-focus="select-all"></textarea>
										<button type="button" class="input-action" data-action="copy" data-action-target="#album-embed-code-' . $i . '"><i class="far fa-copy"></i> ' . _s('copy') . '</button>
									</div>' . "\n";
                        $i++;
                    }
                } ?>
							</div>
						</div>
				</div>
			<?php
            } ?>
			<?php
            if (Handler::cond('admin')) {
                ?>
				<div id="tab-info" class="tabbed-content padding-10<?php if (Handler::var('current_tab') === 'tab-info') {
                    echo ' visible';
                } ?>">
					<?php echo arr_printer(Handler::var('album_safe_html'), '<li><div class="c4 display-table-cell padding-right-10 font-weight-bold">%K</div> <div class="display-table-cell">%V</div></li>', ['<ul class="tabbed-content-list table-li">', '</ul>']); ?>
				</div>
			<?php
            }
            ?>
		</div>
	</div>
</div>
<?php if (Handler::cond('content_manager')) { ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    CHV.fn.ctaForm.enable = <?php echo Handler::var('album')['cta_enable']; ?>;
    CHV.fn.ctaForm.array = <?php echo Handler::var('album')['cta']; ?>;
});
</script>
<?php
            } ?>
<?php
if (Handler::cond('content_manager') || Handler::cond('owner')) {
                require_theme_file('snippets/modal_edit_album');
                require_theme_file('snippets/modal_create_sub_album');
            }
require_theme_file('snippets/modal_random');
?>
<?php if (Handler::cond('content_manager') and isset(request()["deleted"])) { ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    PF.fn.growl.call("<?php _se('The %s has been deleted.', _s('album')); ?>");
});
</script>
<?php } ?>
<?php if (Handler::var('current_tab') === 'tab-embeds') { ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    CHV.fn.album.showEmbedCodes();
})
</script>
<?php } ?>
<?php require_theme_footer(); ?>
