<?php
/**
 * This file is part of Chevereto.
 *
 * (c) Rodolfo Berrios <rodolfo@chevereto.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Chevereto\Legacy\Classes\DB;
use Chevereto\Legacy\Classes\Image;
use function Chevereto\Legacy\decodeID;
use Chevereto\Legacy\G\Handler;
use function Chevereto\Vars\get;

return function (Handler $handler) {
    $albumId = get()['album'] ?? null;
    if ($albumId === null) {
        return $handler->issueError(404);
    }
    $albumId = decodeID($albumId);
    $table = DB::getTable('images');
    $fetch = DB::queryFetchSingle(
        <<<SQL
        SELECT `image_id`
        FROM $table
        WHERE `image_album_id` = $albumId
        ORDER BY RAND() LIMIT 1;
SQL
    );
    if (!$fetch) {
        return $handler->issueError(404);
    }
    $imageId = $fetch['image_id'];
    $image = Image::getSingle(
        id: $imageId,
        pretty: true,
    );
    header('Location: ' . $image['url']);
    die();
};
