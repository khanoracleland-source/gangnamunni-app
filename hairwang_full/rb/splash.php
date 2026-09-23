<?php
// /rb/splash.php
define('_INDEX_', true);
include_once('../common.php');

// 앱에서 접속했는지 확인 (User-Agent 또는 파라미터로 구분)
$is_app = false;
if (isset($_GET['app']) && $_GET['app'] == '1') {
    $is_app = true;
}
// 또는 User-Agent로 확인
if (strpos($_SERVER['HTTP_USER_AGENT'], 'AppName') !== false) {
    $is_app = true;
}

// 스플래시 설정 가져오기
$sql = "SELECT * FROM g5_splash_config WHERE sp_use = 1 LIMIT 1";
$splash = sql_fetch($sql);

// 스플래시 사용 안함 또는 설정 없음
if (!$splash || !$splash['sp_use']) {
    goto_url(G5_URL);
    exit;
}

// 기간 체크
if ($splash['sp_start_date'] && $splash['sp_end_date']) {
    $today = date('Y-m-d');
    if ($today < $splash['sp_start_date'] || $today > $splash['sp_end_date']) {
        goto_url(G5_URL);
        exit;
    }
}

// 파일 경로
$splash_url = G5_DATA_URL.'/splash';
$is_mobile = G5_IS_MOBILE;

// 콘텐츠 URL 및 타입 결정
$content_url = '';
$content_type = $splash['sp_type'] ?: 'image';

if ($content_type == 'lottie') {
    // Lottie 파일
    if ($is_mobile && $splash['sp_lottie_mobile']) {
        $content_url = $splash_url.'/'.$splash['sp_lottie_mobile'];
    } else if ($splash['sp_lottie_pc']) {
        $content_url = $splash_url.'/'.$splash['sp_lottie_pc'];
    } else if ($splash['sp_lottie_mobile']) {
        $content_url = $splash_url.'/'.$splash['sp_lottie_mobile'];
    }
} else {
    // 이미지 파일
    if ($is_mobile && $splash['sp_image_mobile']) {
        $content_url = $splash_url.'/'.$splash['sp_image_mobile'];
    } else if ($splash['sp_image_pc']) {
        $content_url = $splash_url.'/'.$splash['sp_image_pc'];
    } else if ($splash['sp_image_mobile']) {
        $content_url = $splash_url.'/'.$splash['sp_image_mobile'];
    }
}

if (!$content_url) {
    goto_url(G5_URL);
    exit;
}

// 크기 및 위치 설정
if ($is_mobile) {
    $width = $splash['sp_mobile_width'] ?: '80%';  // 기본값을 더 크게
    $height = $splash['sp_mobile_height'] ?: 'auto';
    $top = $splash['sp_mobile_top'];
    $left = $splash['sp_mobile_left'];
} else {
    $width = $splash['sp_pc_width'] ?: '600px';  // 기본값을 더 크게
    $height = $splash['sp_pc_height'] ?: '600px';
    $top = $splash['sp_pc_top'];
    $left = $splash['sp_pc_left'];
}

// 위치 스타일 계산
$position_style = '';
if ($splash['sp_position'] == 'custom') {
    $position_style = "top: {$top}%; left: {$left}%; transform: translate(-50%, -50%);";
} else {
    switch($splash['sp_position']) {
        case 'top-left':
            $position_style = "top: 0; left: 0;";
            break;
        case 'top-center':
            $position_style = "top: 0; left: 50%; transform: translateX(-50%);";
            break;
        case 'top-right':
            $position_style = "top: 0; right: 0;";
            break;
        case 'center-left':
            $position_style = "top: 50%; left: 0; transform: translateY(-50%);";
            break;
        case 'center':
            $position_style = "top: 50%; left: 50%; transform: translate(-50%, -50%);";
            break;
        case 'center-right':
            $position_style = "top: 50%; right: 0; transform: translateY(-50%);";
            break;
        case 'bottom-left':
            $position_style = "bottom: 0; left: 0;";
            break;
        case 'bottom-center':
            $position_style = "bottom: 0; left: 50%; transform: translateX(-50%);";
            break;
        case 'bottom-right':
            $position_style = "bottom: 0; right: 0;";
            break;
        default:
            $position_style = "top: 50%; left: 50%; transform: translate(-50%, -50%);";
    }
}

// 크기 스타일
$size_style = '';
if ($width != 'auto') {
    $size_style .= "width: {$width};";
}
if ($height != 'auto') {
    $size_style .= "height: {$height};";
}

// 다음 페이지 URL
$next_url = isset($_GET['url']) ? urldecode($_GET['url']) : G5_URL;

// ✅ 무한 반복 방지: splash에서 돌아갈 때 &from=splash 파라미터 추가
if ($is_app) {
    $separator = (strpos($next_url, '?') !== false) ? '&' : '?';
    $next_url .= $separator . 'from=splash';
}

// 앱에서는 3초 고정, 웹에서는 설정값 사용
$display_duration = $is_app ? 3 : (int)$splash['sp_duration'];

?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?php echo $config['cf_title']; ?> - 스플래시</title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html, body {
    width: 100%;
    height: 100%;
    overflow: hidden;
}

.splash_container {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: <?php echo $splash['sp_bgcolor']; ?>;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.splash_content {
    position: absolute;
    <?php echo $position_style; ?>
    <?php echo $size_style; ?>
    max-width: 90%;  /* 최대 크기 제한 */
    max-height: 90%;
    cursor: <?php echo $splash['sp_link_url'] ? 'pointer' : 'default'; ?>;
}

.splash_image {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.splash_lottie {
    width: 100%;
    height: 100%;
}

/* 모바일에서는 기본적으로 더 크게 */
@media (max-width: 768px) {
    .splash_content {
        min-width: 300px;
        min-height: 300px;
    }
}

<?php if (!$is_app && $splash['sp_skip_today']) { ?>
.splash_controls {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: flex-end;
    align-items: center;
    padding: 15px 20px;
    z-index: 10;
}

.skip_button {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.5);
    color: white;
    padding: 8px 20px;
    border-radius: 4px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s;
}

.skip_button:hover {
    background: rgba(255, 255, 255, 0.3);
}
<?php } ?>

<?php if (!$is_app && $splash['sp_show_countdown']) { ?>
.countdown {
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 10px 15px;
    border-radius: 25px;
    font-size: 14px;
    z-index: 10;
}
<?php } ?>

/* 로딩 효과 */
.loading {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #666;
    font-size: 16px;
}

/* 페이드인 효과 */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.splash_container {
    animation: fadeIn 0.5s ease-in;
}

/* FCM 알림 권한 요청 UI */
.fcm-permission-ui {
    position: absolute;
    bottom: 100px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(255, 255, 255, 0.95);
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    display: none;
    z-index: 100;
    text-align: center;
}

.fcm-permission-ui h3 {
    margin-bottom: 10px;
    font-size: 16px;
    color: #333;
}

.fcm-permission-ui p {
    margin-bottom: 15px;
    font-size: 14px;
    color: #666;
}

.fcm-permission-ui button {
    background: #4CAF50;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    font-size: 14px;
    cursor: pointer;
    margin: 0 5px;
}

.fcm-permission-ui button.deny {
    background: #ccc;
}

.fcm-permission-ui button:hover {
    opacity: 0.9;
}
</style>
<?php if ($content_type == 'lottie') { ?>
<!-- Lottie Player -->
<script src="https://unpkg.com/@dotlottie/player-component@latest/dist/dotlottie-player.mjs" type="module"></script>
<?php } ?>
</head>
<body>
<div class="splash_container">
    <div class="loading" id="loading"></div>
    
    <?php if ($splash['sp_link_url']) { ?>
    <a href="<?php echo $splash['sp_link_url']; ?>" target="<?php echo $splash['sp_link_target']; ?>" class="splash_content" id="splashContent" style="display: none;">
    <?php } else { ?>
    <div class="splash_content" id="splashContent" style="display: none;" onclick="closeSplash()">
    <?php } ?>
        
        <?php if ($content_type == 'lottie') { ?>
            <dotlottie-player 
                src="<?php echo $content_url; ?>" 
                background="transparent" 
                speed="1" 
                class="splash_lottie"
                loop 
                autoplay>
            </dotlottie-player>
        <?php } else { ?>
            <img src="<?php echo $content_url; ?>" class="splash_image" alt="스플래시 이미지">
        <?php } ?>
        
    <?php if ($splash['sp_link_url']) { ?>
    </a>
    <?php } else { ?>
    </div>
    <?php } ?>
    
    <?php if (!$is_app && $splash['sp_show_countdown']) { ?>
    <div class="countdown" id="countdown"></div>
    <?php } ?>
    
    <?php if (!$is_app && $splash['sp_skip_today']) { ?>
    <div class="splash_controls">
        <button class="skip_button" onclick="skipToday()">오늘 하루 보지 않기</button>
    </div>
    <?php } ?>
</div>

<script>
var duration = <?php echo $display_duration; ?>;
var countdown = duration;
var timer;

// 콘텐츠 로드 완료 시
window.onload = function() {
    // Lottie 파일의 경우 약간의 지연 필요
    <?php if ($content_type == 'lottie') { ?>
    setTimeout(function() {
        showContent();
    }, 500);
    <?php } else { ?>
    showContent();
    <?php } ?>
};

function showContent() {
    document.getElementById('loading').style.display = 'none';
    document.getElementById('splashContent').style.display = 'block';

    updateCountdown();
    timer = setInterval(function() {
        countdown--;
        updateCountdown();
        
        if (countdown <= 0) {
            closeSplash();
        }
    }, 1000);
}

function updateCountdown() {
    <?php if (!$is_app && $splash['sp_show_countdown']) { ?>
    var countdownEl = document.getElementById('countdown');
    if (countdownEl) {
        countdownEl.innerText = countdown + '초 후 자동으로 닫힙니다';
    }
    <?php } ?>
}

function closeSplash() {
    clearInterval(timer);

    <?php if ($is_app) { ?>
    // 앱: replace로 이동 (히스토리에 남지 않음)
    window.location.replace('<?php echo $next_url; ?>');
    <?php } else { ?>
    // 웹: 일반 이동
    window.location.href = '<?php echo $next_url; ?>';
    <?php } ?>
}

function skipToday() {
    // 쿠키 설정 (24시간)
    setCookie('splash_skip_today', '1', 1);
    closeSplash();
}

function setCookie(name, value, days) {
    var expires = "";
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + (value || "") + expires + "; path=/";
}

function getCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for(var i=0;i < ca.length;i++) {
        var c = ca[i];
        while (c.charAt(0)==' ') c = c.substring(1,c.length);
        if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
    }
    return null;
}

<?php if (!$is_app) { ?>
// ESC 키로 닫기 (웹에서만)
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSplash();
    }
});
<?php } ?>

// 뒤로가기 방지
history.pushState(null, null, location.href);
window.onpopstate = function() {
    history.go(1);
};
</script>
</body>
</html>
