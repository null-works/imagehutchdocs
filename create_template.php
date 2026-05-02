<?php
$zip = new ZipArchive();
$filename = "/var/www/html/character_template.zip";

if ($zip->open($filename, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    $zip->addEmptyDir('Character Name');
    $zip->addEmptyDir('Character Name/Portrait');
    $zip->addEmptyDir('Character Name/Square');
    $zip->addEmptyDir('Character Name/Secondary Square');
    $zip->addEmptyDir('Character Name/Avatar URL');
    $zip->close();
    echo "Template zip created successfully.\n";
} else {
    echo "Failed to create template zip.\n";
}
