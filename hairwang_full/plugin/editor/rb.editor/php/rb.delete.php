<?php
include_once("./_common.php");

// 삭제 요청된 파일 URL 가져오기
$file_url = isset($_POST['file']) ? $_POST['file'] : '';

if (!$file_url) {
    echo json_encode(["error" => "파일 경로가 제공되지 않았습니다."]);
    exit;
}

// 업로드된 파일 경로에서 /data/editor/YYYY/MM/ 추출
$pattern = "#/data/editor/(\d{4})/([\w\d_-]+\.\w+)$#"; // 2502/파일명.확장자 패턴
if (!preg_match($pattern, $file_url, $matches)) {
    echo json_encode(["error" => "잘못된 파일 경로 형식입니다."]);
    exit;
}

$ym = $matches[1]; // 2502 (연월)
$file_name = $matches[2]; // 1738844872_c13aee53489a52b7_...png

// 업로드된 디렉토리 경로
$data_dir = G5_DATA_PATH . "/editor/" . $ym . "/";
$file_path = $data_dir . $file_name;

// 파일 존재 여부 확인 후 삭제
if (file_exists($file_path)) {
    unlink($file_path);
    echo json_encode(["success" => "파일이 삭제되었습니다.", "file" => $file_name]);
} else {
    echo json_encode(["error" => "파일을 찾을 수 없습니다."]);
}
?>