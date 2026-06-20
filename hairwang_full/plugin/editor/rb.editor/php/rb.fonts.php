<?php
include_once("./_common.php");
$g5_editor = isset( $_GET['editor']) ?  $_GET['editor'] : 'rb.editor';
$editor_url = G5_PATH.'/plugin/editor/'.$g5_editor;

header('Content-Type: application/json');

// 대상 폴더 (fonts 경로)
$folderPath = $editor_url.'/fonts'; 

if (is_dir($folderPath)) {
    $folders = array_filter(scandir($folderPath), function($item) use ($folderPath) {
        return $item !== '.' && $item !== '..' && is_dir($folderPath . DIRECTORY_SEPARATOR . $item);
    });
    echo json_encode(["folders" => array_values($folders)]);
} else {
    echo json_encode(["error" => "폰트없음"]);
}