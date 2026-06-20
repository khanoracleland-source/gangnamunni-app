<?php
// 그누보드 환경 파일 포함
include_once('../../../../../common.php');

// 파일 저장 경로 설정
$upload_dir = G5_DATA_PATH . '/namecard/cp'; 

// 디렉토리가 없으면 생성
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, G5_DIR_PERMISSION, true);
}

// 업로드된 Base64 인코딩 이미지 처리
if (isset($_POST['image'])) {
    $data = $_POST['image'];

    // "data:image/jpeg;base64," 부분 제거
    $data = str_replace('data:image/jpeg;base64,', '', $data);
    $data = str_replace(' ', '+', $data);
    $imageData = base64_decode($data);

    // 고유한 파일 이름 생성 (유닉한 ID 사용)
    $file_name = 'namecard_' . uniqid() . '.jpg'; // 유니크한 파일명 생성
    $file_path = $upload_dir . '/' . $file_name;

    // 이미지 파일 저장
    if (file_put_contents($file_path, $imageData)) {
        // 성공 시 JSON 형식으로 파일명 반환
        echo json_encode(['filename' => $file_name]);
    } else {
        echo "이미지 업로드 실패.";
    }
} else {
    echo "이미지가 업로드되지 않았습니다.";
}