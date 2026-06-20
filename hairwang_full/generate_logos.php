<?php
/**
 * 로고 이미지 리사이즈 생성기
 * 실행 후 삭제할 것
 */

$source = __DIR__ . '/logo.png';
$dest_dir = __DIR__ . '/gangnamunni/assets/images/';

if (!file_exists($source)) {
    die("원본 로고 파일을 찾을 수 없습니다: $source");
}

// 원본 이미지 로드
$src_image = imagecreatefrompng($source);
if (!$src_image) {
    die("PNG 이미지를 로드할 수 없습니다.");
}

$src_width = imagesx($src_image);
$src_height = imagesy($src_image);

echo "<h2>로고 생성 시작</h2>";
echo "<p>원본 크기: {$src_width} x {$src_height}</p>";

// 생성할 사이즈 목록 (가로 기준, 비율 유지)
$sizes = [
    'logo_512.png' => 512,
    'logo_256.png' => 256,
    'logo_128.png' => 128,
    'logo_64.png' => 64,
    'logo_32.png' => 32,
];

foreach ($sizes as $filename => $width) {
    // 비율 유지하여 높이 계산
    $ratio = $width / $src_width;
    $height = (int)($src_height * $ratio);

    // 새 이미지 생성 (투명 배경)
    $new_image = imagecreatetruecolor($width, $height);

    // 투명 배경 설정
    imagealphablending($new_image, false);
    imagesavealpha($new_image, true);
    $transparent = imagecolorallocatealpha($new_image, 0, 0, 0, 127);
    imagefill($new_image, 0, 0, $transparent);
    imagealphablending($new_image, true);

    // 리사이즈
    imagecopyresampled($new_image, $src_image, 0, 0, 0, 0, $width, $height, $src_width, $src_height);

    // 저장
    $dest_path = $dest_dir . $filename;
    if (imagepng($new_image, $dest_path)) {
        echo "<p style='color:green;'>✅ 생성됨: {$filename} ({$width}x{$height})</p>";
    } else {
        echo "<p style='color:red;'>❌ 실패: {$filename}</p>";
    }

    imagedestroy($new_image);
}

// favicon 생성 (32x32)
$fav_width = 32;
$fav_ratio = $fav_width / $src_width;
$fav_height = (int)($src_height * $fav_ratio);

$fav_image = imagecreatetruecolor($fav_width, $fav_height);
imagealphablending($fav_image, false);
imagesavealpha($fav_image, true);
$transparent = imagecolorallocatealpha($fav_image, 0, 0, 0, 127);
imagefill($fav_image, 0, 0, $transparent);
imagealphablending($fav_image, true);
imagecopyresampled($fav_image, $src_image, 0, 0, 0, 0, $fav_width, $fav_height, $src_width, $src_height);

// favicon.png 저장
if (imagepng($fav_image, $dest_dir . 'favicon.png')) {
    echo "<p style='color:green;'>✅ 생성됨: favicon.png</p>";
}
if (imagepng($fav_image, $dest_dir . 'favicon-32.png')) {
    echo "<p style='color:green;'>✅ 생성됨: favicon-32.png</p>";
}

imagedestroy($fav_image);
imagedestroy($src_image);

// 원본도 복사
copy($source, $dest_dir . 'logo_original.png');
echo "<p style='color:green;'>✅ 복사됨: logo_original.png</p>";

echo "<hr>";
echo "<h2 style='color:blue;'>✅ 완료!</h2>";
echo "<p>생성된 파일 위치: <strong>gangnamunni/assets/images/</strong></p>";
echo "<p style='color:red;'><strong>⚠️ 이 파일(generate_logos.php)을 서버에서 삭제하세요!</strong></p>";
?>
