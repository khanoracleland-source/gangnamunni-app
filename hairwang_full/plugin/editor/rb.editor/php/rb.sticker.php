<?php
include_once("./_common.php");
$g5_editor = isset( $_GET['editor']) ?  $_GET['editor'] : 'rb.editor';
$baseDir = G5_PATH.'/plugin/editor/'.$g5_editor.'/image/sticker/';

header('Content-Type: application/json');

if (isset($_GET['folder'])) {
    // 폴더의 파일 조회 (이미지 파일만)
    $folder = preg_replace('/[^a-zA-Z0-9-_ ]/', '', $_GET['folder']); // 보안 필터링
    $path = $baseDir . $folder;

    if (is_dir($path)) {
        $files = array_values(array_filter(scandir($path), function ($file) use ($path) {
            return preg_match('/\.(png|jpe?g|gif|svg|webp)$/i', $file) && is_file("$path/$file");
        }));

        echo json_encode(["files" => $files]);
    } else {
        echo json_encode(["error" => "폴더가 존재하지 않습니다."]);
    }
} else {
    // 전체 폴더 목록 조회
    $folders = array_filter(glob($baseDir . '*'), 'is_dir');
    $folderNames = array_map('basename', $folders);

    echo json_encode(["folders" => $folderNames]);
}

?>
