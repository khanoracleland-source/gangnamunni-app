<?php
/**
 * 앱 시작 페이지 리다이렉트
 *
 * Android/iOS 앱에서 시작 시 호출하는 페이지
 * 아래 $start_url 변수만 수정하면 앱 시작 페이지를 변경할 수 있습니다.
 *
 * URL: https://hairwang.com/rb/start_app.php
 */

// ============================================
// 앱 시작 페이지 URL 설정 (이 부분만 수정하세요)
// ============================================
//$start_url = "https://hairwang.com/rb/splash.php";

$start_url = "https://hairwang.com/?app=1";

// 리다이렉트 실행
header("Location: " . $start_url);
exit;
?>