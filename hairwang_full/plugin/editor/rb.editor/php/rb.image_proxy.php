<?php
// image_proxy.php
if(isset($_GET['url'])) {
    $url = $_GET['url'];

    // URL 유효성 검사
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        exit('Invalid URL');
    }

    // cURL 설정 및 실행
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // 리디렉션 허용
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5); // 최대 5번 리디렉션 허용
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/97.0.4692.99 Safari/537.36');
    curl_setopt($ch, CURLOPT_REFERER, $url); // 원본 URL을 Referer로 설정
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: image/*', // 이미지 전용 Accept 헤더 추가
        'Cache-Control: no-cache', // 캐시 우회
        'Pragma: no-cache'
    ]);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL 검증 비활성화
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    curl_close($ch);

    // **HTTP 응답이 200이 아닌 경우에도 강제 출력**
    if ($httpCode === 200 && $data) {
        header("Content-Type: $contentType");
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Pragma: no-cache");
        echo $data;
    } else {
        // **cURL이 실패하면 URL 직접 반환**
        header("Content-Type: text/plain");
        echo $url;
    }
} else {
    http_response_code(400);
    exit('URL parameter is required');
}
?>
