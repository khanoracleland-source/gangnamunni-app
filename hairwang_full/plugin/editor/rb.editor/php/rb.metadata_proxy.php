<?php
include_once("./_common.php");
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$url = $data['url'];

if (!$url) {
    echo json_encode(['error' => 'Invalid URL']);
    exit;
}

$editor_url = G5_EDITOR_URL.'/'.$config['cf_editor'];

// 🛠️ 실제 메타데이터 스크래핑을 수행하는 파일 호출
$metadata_url = $editor_url.'/php/rb.metadata.php?url=' . urlencode($url);

$response = file_get_contents($metadata_url);
echo $response;
?>