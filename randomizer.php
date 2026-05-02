<?php
$route = function ($handler) {
    try {
        $album_encoded_id = $_GET['album'] ?? null;
        if (isset($album_encoded_id)) {
            $album_id = Chevereto\Legacy\Classes\decodeID($album_encoded_id);
            if ($album_id > 0) {
                $db = Chevereto\Legacy\Classes\DB::getInstance();
                $random_image = $db->queryFetchSingle(
                    "SELECT image_name, image_extension FROM " . Chevereto\Legacy\Classes\DB::getTablePrefix() . "images WHERE image_album_id = :album_id ORDER BY RAND() LIMIT 1",
                    ['album_id' => $album_id]
                );
                if ($random_image) {
                    $image_url = Chevereto\Legacy\Classes\Image::getUrl($random_image['image_name'] . '.' . $random_image['image_extension']);
                    header("Location: " . $image_url);
                    die();
                }
            }
        }
        $handler->issue404();
    } catch (Exception $e) {
        $handler->issue404();
    }
};
