<?php
// CORS 허용
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

// 요청된 이미지 URL 가져오기
if (!isset($_GET['url'])) {
    http_response_code(400);
    echo "URL이 잘못 되었습니다.";
    exit;
}

$image_url = filter_var($_GET['url'], FILTER_SANITIZE_URL);

// URL 유효성 검사
if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo "URL이 잘못 되었습니다.";
    exit;
}

// CURL을 사용하여 이미지 다운로드
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $image_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$image_data = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$mime_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($http_code !== 200 || !$image_data) {
    http_response_code(500);
    echo "이미지 파일 저장에 실패 하였습니다.";
    exit;
}

// 이미지 타입 허용 (JPG, PNG, WEBP, GIF만 허용)
$allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mime_type, $allowed_types)) {
    http_response_code(400);
    echo "이미지 파일만 가능합니다.";
    exit;
}

// 올바른 Content-Type 설정 후 출력
header("Content-Type: " . $mime_type);
echo $image_data;
?>
