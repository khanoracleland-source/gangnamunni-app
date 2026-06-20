<?php
/**
 * ============================================
 * 헤어왕 앱 시작 페이지 설정
 * ============================================
 *
 * Android/iOS 앱에서 시작 시 호출하는 페이지
 * URL: https://hairwang.com/rb/start_app.php
 *
 * ============================================
 * 설정 변수 (아래 값들을 수정하세요)
 * ============================================
 */

// Splash 화면 URL
$splash_url = "https://hairwang.com/rb/splash.php";

// 메인 페이지 URL
$main_url = "https://hairwang.com/";

// Splash 표시 시간 (초)
// 예: 2 = 2초, 3 = 3초
$splash_duration = 2;

// Splash 재표시 간격 (초)
// 이 시간이 지나면 다시 Splash를 보여줍니다
// 예시:
//   60 = 1분
//   300 = 5분
//   600 = 10분
//   1800 = 30분
//   3600 = 1시간
//   7200 = 2시간
//   86400 = 24시간
$cookie_lifetime = 3600;

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<title>헤어왕 앱 시작</title>
<style>
body {
    margin: 0;
    padding: 0;
    overflow: hidden;
}
#splash-container {
    width: 100%;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
}
#splash-frame {
    width: 100%;
    height: 100%;
    border: none;
}
</style>
</head>
<body>
<div id="splash-container">
    <iframe id="splash-frame" src="<?php echo $splash_url; ?>"></iframe>
</div>
<script>
// ============================================
// 쿠키 관리 함수
// ============================================
function getCookie(name) {
    var value = "; " + document.cookie;
    var parts = value.split("; " + name + "=");
    if (parts.length == 2) return parts.pop().split(";").shift();
    return null;
}

function setCookie(name, value, seconds) {
    var expires = "";
    if (seconds) {
        var date = new Date();
        date.setTime(date.getTime() + (seconds * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + value + expires + "; path=/";
}

// ============================================
// Splash 표시 로직
// ============================================
var lastVisit = getCookie('hairwang_last_visit');
var now = new Date().getTime();
var showSplash = false;

if (!lastVisit) {
    // 처음 방문
    console.log('[start_app.php] 처음 방문 - Splash 표시');
    showSplash = true;
} else {
    var timeDiff = (now - parseInt(lastVisit)) / 1000; // 초 단위
    console.log('[start_app.php] 마지막 방문 후 경과 시간:', Math.floor(timeDiff), '초');

    if (timeDiff > <?php echo $cookie_lifetime; ?>) {
        console.log('[start_app.php] <?php echo $cookie_lifetime; ?>초 경과 - Splash 표시');
        showSplash = true;
    } else {
        console.log('[start_app.php] <?php echo $cookie_lifetime; ?>초 미경과 - Splash 건너뛰기');
        showSplash = false;
    }
}

if (showSplash) {
    // Splash 표시
    console.log('[start_app.php] Splash 표시 시작');
    console.log('[start_app.php] <?php echo $splash_duration; ?>초 후 메인 페이지로 이동:', '<?php echo $main_url; ?>');

    // 쿠키 저장
    setCookie('hairwang_last_visit', now, <?php echo $cookie_lifetime; ?>);

    // 지정 시간 후 메인 페이지로 이동
    setTimeout(function() {
        console.log('[start_app.php] 메인 페이지로 이동 중...');
        window.location.replace('<?php echo $main_url; ?>');
    }, <?php echo $splash_duration * 1000; ?>);
} else {
    // Splash 건너뛰고 바로 메인으로
    console.log('[start_app.php] 바로 메인 페이지로 이동');
    document.getElementById('splash-container').style.display = 'none';
    window.location.replace('<?php echo $main_url; ?>');
}
</script>
</body>
</html>
