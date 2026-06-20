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
// Splash 화면 표시 (3초) → 자동으로 메인으로 이동
$splash_url = "https://hairwang.com/rb/splash.php";
$main_url = "https://hairwang.com/";

// Splash에 다음 페이지 URL 전달
$start_url = $splash_url . "?app=1&url=" . urlencode($main_url);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<title>헤어왕 앱 시작</title>
</head>
<body>
<script>
console.log('[start_app.php] 리다이렉트 시작:', '<?php echo $start_url; ?>');
// replace로 리다이렉트 (히스토리에 남지 않음)
window.location.replace('<?php echo $start_url; ?>');
</script>
</body>
</html>
