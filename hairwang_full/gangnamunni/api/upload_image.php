<?php
/**
 * 이미지 업로드 API
 * POST multipart/form-data
 * 응답: { success, url, thumbnail_url, file_size }
 */
require_once __DIR__ . '/bootstrap.php';

requireMethod('POST');
apiRateLimit('upload', 30, 60);
getAuthUser(true);

// 파일 확인
if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
    jsonError('이미지 파일이 없습니다.');
}

$file = $_FILES['image'];

// 유효성 검사
$errors = validateImageFile($file);
if (!empty($errors)) {
    jsonError(implode(' ', $errors));
}

// 업로드 디렉토리
$upload_dir = dirname(__DIR__) . '/uploads/reviews/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$thumb_dir = $upload_dir . 'thumbs/';
if (!is_dir($thumb_dir)) {
    mkdir($thumb_dir, 0755, true);
}

// 파일 확장자
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$ext_map = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];
$ext = $ext_map[$mime] ?? 'jpg';

// 유니크 파일명
$filename = generateUniqueFilename($ext);
$filepath = $upload_dir . $filename;
$thumb_filename = 'thumb_' . $filename;
$thumb_filepath = $thumb_dir . $thumb_filename;

// 임시 파일 이동
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    jsonError('파일 저장에 실패했습니다.');
}

// EXIF 회전 보정
fixExifOrientation($filepath);

// 리사이즈 (1920px 초과 시)
resizeImage($filepath, $filepath, 1920);

// 썸네일 생성
createThumbnail($filepath, $thumb_filepath, 300);

// WebP 변환본 생성
$webp_filename = pathinfo($filename, PATHINFO_FILENAME) . '.webp';
$webp_filepath = $upload_dir . $webp_filename;
convertToWebp($filepath, $webp_filepath, 80);

// URL 생성 (상대 경로)
$base_url = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
$image_url = $base_url . '/uploads/reviews/' . $filename;
$thumb_url = $base_url . '/uploads/reviews/thumbs/' . $thumb_filename;

$file_size = filesize($filepath);

jsonSuccess([
    'url' => $image_url,
    'thumbnail_url' => $thumb_url,
    'webp_url' => $base_url . '/uploads/reviews/' . $webp_filename,
    'file_size' => $file_size,
    'filename' => $filename
]);
