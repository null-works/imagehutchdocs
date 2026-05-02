<?php
use function Chevereto\Legacy\G\get_public_url;
use Chevereto\Legacy\G\Handler;

if (!defined('ACCESS') || !ACCESS) {
    die('This file cannot be directly accessed.');
}
?>
<div id="modal-random" class="hidden">
    <span class="modal-box-title">Randomizer</span>
    <p>All images in this album will be in the randomizer. You can add or remove images from the album at any time to effect the randomizer.</p>
    <div>
        <div class="input-label margin-bottom-0">
            <label for="modal-share-url"><?php _se('Link'); ?></label>
            <div class="position-relative">
                <input type="text" name="modal-random-url" id="modal-random-url" class="text-input" value="<?php echo get_public_url('randomizer/' . Handler::var('album')['id_encoded']) . '.gif'; ?>" data-focus="select-all">
                <button type="button" class="input-action" data-action="copy" data-action-target="#modal-random-url"><i class="far fa-copy"></i> <?php _se('copy'); ?></button>
            </div>
        </div>
    </div>
</div>
