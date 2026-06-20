<?php
// 이 파일은 새로운 파일 생성시 반드시 포함되어야 함
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

$g5_debug['php']['begin_time'] = $begin_time = get_microtime();

if (!isset($g5['title'])) {
    $g5['title'] = $config['cf_title'];
    $g5_head_title = $g5['title'];
}
else {
    // 상태바에 표시될 제목
    $g5_head_title = implode(' | ', array_filter(array($g5['title'], $config['cf_title'])));
}

$g5['title'] = strip_tags($g5['title']);
$g5_head_title = strip_tags($g5_head_title);

// 현재 접속자
// 게시판 제목에 ' 포함되면 오류 발생
$g5['lo_location'] = addslashes($g5['title']);
if (!$g5['lo_location'])
    $g5['lo_location'] = addslashes(clean_xss_tags($_SERVER['REQUEST_URI']));
$g5['lo_url'] = addslashes(clean_xss_tags($_SERVER['REQUEST_URI']));
if (strstr($g5['lo_url'], '/'.G5_ADMIN_DIR.'/') || $is_admin == 'super') $g5['lo_url'] = '';

/*
// 만료된 페이지로 사용하시는 경우
header("Cache-Control: no-cache"); // HTTP/1.1
header("Expires: 0"); // rfc2616 - Section 14.21
header("Pragma: no-cache"); // HTTP/1.0
*/
?>
<!doctype html>
<html lang="ko">
<head>
<!-- 푸시 알림 스크립트 -->
<script src="<?php echo G5_JS_URL ?>/push_notification.js?ver=<?php echo G5_JS_VER ?>"></script>
<meta charset="utf-8">

<!-- viewport { -->
<?php if(isset($rb_builder['bu_viewport']) && $rb_builder['bu_viewport']) { ?>
<meta name="viewport" content="width=device-width,initial-scale=<?php echo $rb_builder['bu_viewport'] ?>,minimum-scale=<?php echo $rb_builder['bu_viewport'] ?>,maximum-scale=<?php echo $rb_builder['bu_viewport'] ?>,user-scalable=no" />
<?php } else { ?>
<meta name="viewport" content="width=device-width,initial-scale=0.9,minimum-scale=0.9,maximum-scale=0.9,user-scalable=no" />
<?php } ?>
<meta name="HandheldFriendly" content="true" />
<meta http-equiv="imagetoolbar" content="no" />
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<!-- } -->

<?php if(isset($seo['se_title']) && $seo['se_title'] || isset($seo['se_keywords']) && $seo['se_keywords'] || isset($seo['se_description']) && $seo['se_description']) { ?>
<!-- META { -->
<meta name="title" content="<?php echo $seo['se_title'] ?>" />
<meta name="keywords" content="<?php echo $seo['se_keywords'] ?>" />
<meta name="description" content="<?php echo $seo['se_description'] ?>" />
<meta name="robots" content="index,follow" />
<!-- } --
<?php } ?>

<!-- OG { -->
<meta property="og:type" content="website">
<meta property="og:url" content="<?php echo getCurrentUrl() ?>" />
<?php if(isset($bo_table) && $bo_table && $wr_id) { ?>
   
    <?php                     
        //게시물 정보
        $views = get_view($write, $board, $board_skin_path);
        $meta_title = $views['wr_subject']; 

        if(isset($views['file'][0]['file']) && $views['file'][0]['file']) {
            $meta_img = G5_DATA_URL.'/file/'.$bo_table.'/'.urlencode($views['file'][0]['file']);
        } else { 
            $matches = get_editor_image($views['wr_content']);
            for ($i = 0; $i < count($matches[1]); $i++){
                $img = $matches[1][$i];
                preg_match("/src=[\'\"]?([^>\'\"]+[^>\'\"]+)/i", $img, $m); $src = $m[1];
            }
            $meta_img = isset($src) ? $src : '';
        }
        $meta_description_cut = strip_tags($views['wr_content']);
        $meta_description_cut = preg_replace("/<(.*?)\>/","",$meta_description_cut);
        $meta_description_cut = preg_replace("/&nbsp;/","",$meta_description_cut);
        $meta_description = cut_str($meta_description_cut,100);
    ?>
    <meta property="og:title" content="<?php echo $views['wr_subject'] ?>"/>
    <meta property="og:description" content="<?php echo $meta_description; ?>" />
    <meta property="og:image" content="<?php echo $meta_img ?>?ver=<?php echo G5_TIME_YMDHIS ?>"/>
    
<?php } else { ?>
   
    <?php if(isset($seo['se_og_title']) && $seo['se_og_title']) { ?>
        <meta property="og:title" content="<?php echo $seo['se_og_title'] ?>" />
    <?php } ?>
    <?php if(isset($seo['se_og_description']) && $seo['se_og_description']) { ?>
        <?php if(defined('_INDEX_')) { ?>
            <meta property="og:description" content="<?php echo $seo['se_og_description'] ?>" />
        <?php } else { ?>
            <meta property="og:description" content="<?php echo $g5_head_title; ?>" />
        <?php } ?>
    <?php } ?>
    <?php if(isset($seo['se_og_image']) && $seo['se_og_image']) { ?>
        <meta property="og:image" content="<?php echo G5_URL ?>/data/seo/og_image?ver=<?php echo G5_TIME_YMDHIS ?>" />
    <?php } ?>

<?php } ?>
<!-- } -->

<!-- ICO { -->
<?php if(isset($seo['se_favicon']) && $seo['se_favicon']) { ?>
<link rel="shortcut icon" href="<?php echo G5_URL ?>/data/seo/favicon?ver=<?php echo G5_TIME_YMDHIS ?>" type="image/x-icon">
<link rel="icon" href="<?php echo G5_URL ?>/data/seo/favicon?ver=<?php echo G5_TIME_YMDHIS ?>" type="image/x-icon">
<?php } ?>
<link rel="manifest" href="/manifest.json">
<!-- } -->

<?php 
//소유권 확인 메타
if(isset($seo['se_naver_meta']) && $seo['se_naver_meta']) { 
    echo $seo['se_naver_meta'];
}
    
if(isset($seo['se_google_meta']) && $seo['se_google_meta']) { 
    echo $seo['se_google_meta'];
}
?>


<?php
if(isset($config['cf_add_meta']) && $config['cf_add_meta'])
    echo $config['cf_add_meta'].PHP_EOL;
?>

<title><?php echo $g5_head_title; ?></title>

<?php
$shop_css = '';
if (defined('_SHOP_')) $shop_css = '_shop';
echo '<link rel="stylesheet" href="'.run_replace('head_css_url', G5_THEME_CSS_URL.'/'.(G5_IS_MOBILE?'mobile':'default').$shop_css.'.css?ver='.G5_CSS_VER, G5_THEME_URL).'">'.PHP_EOL;
?>
<!--[if lte IE 8]>
<script src="<?php echo G5_JS_URL ?>/html5.js"></script>
<![endif]-->

<script>
// ============================================
// 그누보드 전역 변수 선언
// ============================================
// 목적: JavaScript에서 PHP 변수를 사용하기 위한 전역 상수 정의
const g5_url       = "<?php echo G5_URL ?>";           // 사이트 URL
const g5_bbs_url   = "<?php echo G5_BBS_URL ?>";       // 게시판 URL
const g5_is_member = "<?php echo isset($is_member)?$is_member:''; ?>";  // 회원 여부 (1 또는 '')
const g5_is_admin  = "<?php echo isset($is_admin)?$is_admin:''; ?>";    // 관리자 여부
const g5_is_mobile = "<?php echo G5_IS_MOBILE ?>";     // 모바일 여부
const g5_bo_table  = "<?php echo isset($bo_table)?$bo_table:''; ?>";    // 게시판 테이블명
const g5_sca       = "<?php echo isset($sca)?$sca:''; ?>";               // 쇼핑몰 카테고리
const g5_editor    = "<?php echo ($config['cf_editor'] && $board['bo_use_dhtml_editor'])?$config['cf_editor']:''; ?>";  // 에디터 종류
const g5_cookie_domain = "<?php echo G5_COOKIE_DOMAIN ?>";  // 쿠키 도메인

// ============================================
// ✅ 추가: 회원 ID 변수 (FCM 토큰 저장용)
// ============================================
// 목적: push_notification.js에서 사용자 식별을 위해 필요
// 세션에서 추출한 회원 ID (로그인 상태에서만 값 존재)
const g5_member_id = "<?php echo isset($member['mb_id'])?$member['mb_id']:''; ?>";

<?php if (defined('G5_USE_SHOP') && G5_USE_SHOP) { ?>
const g5_theme_shop_url = "<?php echo G5_THEME_SHOP_URL; ?>";
const g5_shop_url = "<?php echo G5_SHOP_URL; ?>";
<?php } ?>
<?php if(defined('G5_IS_ADMIN')) { ?>
const g5_admin_url = "<?php echo G5_ADMIN_URL; ?>";
<?php } ?>

// ============================================
// ✅ 추가: 앱 연동 - FCM 토큰 브리지
// ============================================
// 목적: Android/iOS 네이티브 앱에서 FCM 토큰을 웹뷰로 전달받기 위한 브리지 함수
// 작동 방식:
//   1. 앱 실행 시 DOMContentLoaded 이벤트에서 네이티브에 토큰 요청
//   2. 네이티브 앱이 onTokenReceived() 콜백 호출
//   3. 웹뷰에서 receiveFCMToken()으로 토큰 처리
// 참고: push_notification.js의 receiveFCMToken() 함수와 연동

/**
 * 네이티브 앱 → 웹뷰 공통 콜백 함수
 *
 * @param {string} token - FCM 토큰 (152자 이상의 문자열)
 * @description
 *   - Android: Android.requestToken() 호출 후 이 함수가 콜백됨
 *   - iOS: webkit.messageHandlers.invokeAction.postMessage('getToken') 호출 후 콜백됨
 *   - 토큰을 받으면 push_notification.js의 receiveFCMToken()에 전달
 */
function onTokenReceived(token) {
    // 토큰이 없으면 종료
    if (!token) {
        console.warn('[앱 브리지] 토큰이 비어있습니다');
        return;
    }

    console.log('[앱 브리지] FCM Token 수신:', token.substring(0, 20) + '...');

    // push_notification.js의 receiveFCMToken() 함수 호출
    // 이 함수가 실제 토큰 저장 로직을 처리함
    if (window.receiveFCMToken) {
        console.log('[앱 브리지] receiveFCMToken() 함수 호출');
        window.receiveFCMToken(token);
    } else {
        // receiveFCMToken이 아직 로드되지 않은 경우 (극히 드문 경우)
        // localStorage에만 임시 저장
        console.warn('[앱 브리지] receiveFCMToken 함수 아직 로드 안됨');
        console.warn('[앱 브리지] localStorage에만 임시 저장');
        localStorage.setItem('fcm_token', token);
    }
}

/**
 * 페이지 로드 완료 후 네이티브 앱에 토큰 요청
 *
 * @description
 *   - DOM 로드가 완료되면 자동으로 실행
 *   - Android와 iOS의 브리지 존재 여부를 확인 후 토큰 요청
 *   - 웹 브라우저에서는 브리지가 없으므로 아무 동작 안함 (정상)
 */
document.addEventListener('DOMContentLoaded', function () {
    console.log('[앱 브리지] DOMContentLoaded - 네이티브 앱 토큰 요청 시작');

    // ----------------------------------------
    // Android 브리지 확인 및 토큰 요청
    // ----------------------------------------
    // Android WebView에서 addJavascriptInterface()로 주입된 'Android' 객체 확인
    if (window.Android && typeof Android.requestToken === 'function') {
        console.log('[앱 브리지] ✅ Android 브리지 감지됨');
        console.log('[앱 브리지] Android.requestToken() 호출');

        try {
            Android.requestToken();  // 네이티브에 토큰 요청
        } catch (e) {
            console.error('[앱 브리지] Android.requestToken() 호출 실패:', e);
        }
    } else {
        console.log('[앱 브리지] ❌ Android 브리지 없음 (웹 브라우저이거나 iOS 앱)');
    }

    // ----------------------------------------
    // iOS 브리지 확인 및 토큰 요청
    // ----------------------------------------
    // iOS WKWebView에서 제공하는 webkit.messageHandlers 확인
    if (
        window.webkit &&
        window.webkit.messageHandlers &&
        window.webkit.messageHandlers.invokeAction
    ) {
        console.log('[앱 브리지] ✅ iOS 브리지 감지됨');
        console.log('[앱 브리지] webkit.messageHandlers.invokeAction.postMessage("getToken") 호출');

        try {
            window.webkit.messageHandlers.invokeAction.postMessage('getToken');  // 네이티브에 토큰 요청
        } catch (e) {
            console.error('[앱 브리지] iOS 토큰 요청 실패:', e);
        }
    } else {
        console.log('[앱 브리지] ❌ iOS 브리지 없음 (웹 브라우저이거나 Android 앱)');
    }

    // ----------------------------------------
    // 웹 브라우저 감지
    // ----------------------------------------
    if (!window.Android && !window.webkit) {
        console.log('[앱 브리지] 💻 웹 브라우저에서 접속 (네이티브 앱 아님)');
    }
});
</script>

<?php
if (isset($rb_core) && isset($rb_core['font'])) {
    $font = $rb_core['font'];
} else {
    $font = 'Pretendard';
}
    
add_javascript('<script src="'.G5_JS_URL.'/jquery-1.12.4.min.js"></script>', 0);
add_javascript('<script src="'.G5_JS_URL.'/jquery-migrate-1.4.1.min.js"></script>', 0);
    
if(defined('_SHOP_')) {
    if (isset($rb_core['layout_shop'])) {
        add_javascript('<script src="' . G5_THEME_URL . '/rb.js/rb.layout.shop.js?v=2.2.1"></script>', 0);
    }
} else { 
    if (isset($rb_core['layout'])) {
        add_javascript('<script src="' . G5_THEME_URL . '/rb.js/rb.layout.js?v=2.2.1"></script>', 0);
    }
}

    
if (defined('_SHOP_')) {
    if(!G5_IS_MOBILE) {
        add_javascript('<script src="'.G5_JS_URL.'/jquery.shop.menu.js?ver='.G5_JS_VER.'"></script>', 0);
    }
} else {
    add_javascript('<script src="'.G5_JS_URL.'/jquery.menu.js?ver='.G5_JS_VER.'"></script>', 0);
}
add_javascript('<script src="'.G5_JS_URL.'/common.js?ver='.G5_JS_VER.'"></script>', 0);
add_javascript('<script src="'.G5_JS_URL.'/wrest.js?ver='.G5_JS_VER.'"></script>', 0);
add_javascript('<script src="'.G5_JS_URL.'/placeholders.min.js"></script>', 0);
add_stylesheet('<link rel="stylesheet" href="'.G5_JS_URL.'/font-awesome/css/font-awesome.min.css">', 0);
if(G5_IS_MOBILE) {
    add_javascript('<script src="'.G5_JS_URL.'/modernizr.custom.70111.js"></script>', 1); // overflow scroll 감지
}
    
if(defined('_SHOP_')) {
    add_stylesheet('<link rel="stylesheet" href="'.G5_THEME_URL.'/rb.css/style.shop.css?ver='.filemtime(G5_THEME_PATH.'/rb.css/style.shop.css').'" />', 0);
}
    
/** 테마구성 **/  
$rb_css_files = [
    'reset.css',
    'style.css',
    'mobile.css',
    'form.css',
    'swiper.css',
    'custom.css',
];

foreach ($rb_css_files as $rb_css_file) {
    $rb_css_path = G5_THEME_PATH . "/rb.css/$rb_css_file";
    $rb_css_url = G5_THEME_URL . "/rb.css/$rb_css_file";
    $rb_css_ver = file_exists($rb_css_path) ? filemtime($rb_css_path) : time(); // filemtime 호출 최소화
    add_stylesheet("<link rel='stylesheet' href='{$rb_css_url}?ver={$rb_css_ver}' />", 0);
}

add_javascript('<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>', 0);
add_javascript('<script src="'.G5_THEME_URL.'/rb.js/swiper.js"></script>', 0);
add_stylesheet('<link rel="stylesheet" href="'.G5_THEME_URL.'/rb.fonts/'.$font.'/'.$font.'.css?ver='.filemtime(G5_THEME_PATH.'/rb.fonts/'.$font.'/'.$font.'.css').'" />', 0);  
add_stylesheet('<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/base/jquery-ui.css" />', 0);


if(!defined('G5_IS_ADMIN'))
    echo $config['cf_add_script'];
?>

</head>
<body<?php
// ============================================
// ✅ 모바일 전용 속성 추가 (2025-12-21)
// ============================================
// 목적: 모바일에서 접속 시 body에 data-mobile="true" 속성 추가
// 용도: mobile.css에서 body[data-mobile="true"] 선택자로 모바일 전용 스타일 적용
// 감지 방법: 그누보드 내장 변수 G5_IS_MOBILE 사용 (자동 모바일 감지)
//           - 모바일 기기: 스마트폰, 태블릿 등
//           - PC: 데스크톱, 노트북
// body 태그에 속성 추가
echo isset($g5['body_script']) ? $g5['body_script'] : '';
if (G5_IS_MOBILE) echo ' data-mobile="true"';
?>>
<?php
if ($is_member) { // 회원이라면 로그인 중이라는 메세지를 출력해준다.
    $sr_admin_msg = '';
    if ($is_admin == 'super') $sr_admin_msg = "최고관리자 ";
    else if ($is_admin == 'group') $sr_admin_msg = "그룹관리자 ";
    else if ($is_admin == 'board') $sr_admin_msg = "게시판관리자 ";

    echo '<div id="hd_login_msg">'.$sr_admin_msg.get_text($member['mb_nick']).'님 로그인 중 ';
    echo '<a href="'.G5_BBS_URL.'/logout.php">로그아웃</a></div>';
}

?>

<main class="<?php echo $rb_core['color'] ?> <?php echo $rb_core['header'] ?>" id="main">


<?php if (!empty($rb_builder['bu_load'])) { ?>


    <!-- 로더 시작 { -->
    <div id="loadings">
        <div id="loadings_spin"></div>
    </div>

    <script>

        // DOM을 포함한 페이지가 준비가 되면 사라집니다.
        $(window).on("load", function() {
            $('#loadings').delay(1000).fadeOut(500);
        });

    </script>
    <!-- } -->


<?php } ?>