<?php
// /rb/settings.php
include_once('../common.php');

// 로그인 체크
if (!$member['mb_id']) {
    alert('로그인 후 이용해 주세요.', G5_BBS_URL.'/login.php');
}

// 알림 설정 테이블 생성
$sql = "CREATE TABLE IF NOT EXISTS `g5_notification_settings` (
    `ns_id` int(11) NOT NULL AUTO_INCREMENT,
    `mb_id` varchar(20) NOT NULL DEFAULT '',
    `ns_all_notification` tinyint(1) NOT NULL DEFAULT '1',
    `ns_comment` tinyint(1) NOT NULL DEFAULT '1',
    `ns_like` tinyint(1) NOT NULL DEFAULT '1',
    `ns_mention` tinyint(1) NOT NULL DEFAULT '1',
    `ns_follow` tinyint(1) NOT NULL DEFAULT '1',
    `ns_marketing` tinyint(1) NOT NULL DEFAULT '1',
    `ns_datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    `ns_ip` varchar(25) NOT NULL DEFAULT '',
    PRIMARY KEY (`ns_id`),
    KEY `mb_id` (`mb_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";
sql_query($sql, false);

// 현재 사용자의 알림 설정 가져오기
$sql = "SELECT * FROM g5_notification_settings WHERE mb_id = '{$member['mb_id']}'";
$notification = sql_fetch($sql);

// 설정이 없으면 기본값으로 생성
if (!$notification) {
    $sql = "INSERT INTO g5_notification_settings 
            SET mb_id = '{$member['mb_id']}',
                ns_all_notification = '1',
                ns_comment = '1',
                ns_like = '1',
                ns_mention = '1',
                ns_follow = '1',
                ns_marketing = '1',
                ns_datetime = '".G5_TIME_YMDHIS."',
                ns_ip = '{$_SERVER['REMOTE_ADDR']}'";
    sql_query($sql);
    
    // 다시 조회
    $notification = sql_fetch("SELECT * FROM g5_notification_settings WHERE mb_id = '{$member['mb_id']}'");
}

// 푸시 파라미터 처리
$push = isset($_GET['push']) ? $_GET['push'] : '';
if ($push == 'on' || $push == 'off') {
    $push_value = ($push == 'on') ? 1 : 0;
    
    // 푸시 설정 업데이트
    $sql = "UPDATE g5_notification_settings 
            SET ns_all_notification = '{$push_value}',
                ns_datetime = '".G5_TIME_YMDHIS."',
                ns_ip = '{$_SERVER['REMOTE_ADDR']}'
            WHERE mb_id = '{$member['mb_id']}'";
    sql_query($sql);
    
    // 설정 다시 로드
    $notification = sql_fetch("SELECT * FROM g5_notification_settings WHERE mb_id = '{$member['mb_id']}'");
    
    // 앱에 결과 전달 (JSON 응답)
    if (strpos($_SERVER['HTTP_USER_AGENT'], 'hairwang-app') !== false || isset($_GET['app'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'push' => $push,
            'message' => '푸시 알림이 ' . ($push == 'on' ? '활성화' : '비활성화') . '되었습니다.'
        ]);
        exit;
    }
}

// 현재 푸시 상태를 URL에 반영
$current_push_state = $notification['ns_all_notification'] ? 'on' : 'off';

$g5['title'] = '설정';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $g5['title']; ?></title>
<link rel="stylesheet" href="<?php echo G5_THEME_CSS_URL; ?>/<?php echo G5_IS_MOBILE ? 'mobile' : 'default'; ?>.css?ver=<?php echo G5_CSS_VER; ?>">
<script src="<?php echo G5_JS_URL ?>/jquery-1.12.4.min.js"></script>
</head>
<body style="margin:0;padding:0;">

<style>
/* 기존 스타일 동일 */
body, html {
    margin: 0 !important;
    padding: 0 !important;
}

.settings_wrapper {
    background: #f5f5f5;
    min-height: 100vh;
    padding-top: 0 !important;
    margin-top: 0 !important;
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
}

.settings_container {
    max-width: 600px;
    margin: 0 auto;
    background: #fff;
    padding-top: 0 !important;
    padding-bottom: 0 !important;
}

.settings_header {
    position: sticky;
    top: 0;
    background: #fff;
    display: flex;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e5e5e5;
    z-index: 100;
    margin-top: 0 !important;
}

.settings_header h1 {
    font-size: 17px;
    font-weight: 600;
    margin: 0;
    flex: 1;
    text-align: center;
    color: #333;
}

.back_btn {
    position: absolute;
    left: 15px;
    display: flex;
    align-items: center;
    padding: 5px;
    color: #333;
}

.notification_section {
    background: #fff;
    margin-bottom: 10px;
    margin-top: 0;
}

.section_header {
    padding: 15px 20px;
    background: #fff;
}

.section_title {
    font-size: 14px;
    font-weight: 600;
    color: #333;
    margin: 0;
}

.setting_group {
    background: #fff;
}

.setting_item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    background: #fff;
    border-bottom: 1px solid #f0f0f0;
    position: relative;
}

.setting_item:last-child {
    border-bottom: none;
}

.setting_content {
    flex: 1;
    padding-right: 15px;
}

.setting_title {
    font-size: 16px;
    color: #333;
    margin-bottom: 3px;
}

.setting_desc {
    font-size: 13px;
    color: #999;
    line-height: 1.4;
}

.toggle_switch {
    position: relative;
    width: 51px;
    height: 31px;
    flex-shrink: 0;
    cursor: pointer;
}

.toggle_switch input {
    opacity: 0;
    width: 0;
    height: 0;
    position: absolute;
}

.toggle_slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #e5e5e5;
    transition: .3s;
    border-radius: 31px;
    pointer-events: none;
}

.toggle_slider:before {
    position: absolute;
    content: "";
    height: 27px;
    width: 27px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

input:checked + .toggle_slider {
    background-color: #4CD964;
}

input:checked + .toggle_slider:before {
    transform: translateX(20px);
}

.notification_info {
    padding: 12px 20px;
    background: #f9f9f9;
    font-size: 13px;
    color: #666;
    line-height: 1.5;
}

.menu_section {
    margin-top: 10px;
    background: #fff;
    margin-bottom: 0;
    padding-bottom: 0;
}

.menu_item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    background: #fff;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: background 0.1s;
}

.menu_item:active {
    background: #f5f5f5;
}

.menu_item:last-child {
    border-bottom: none;
}

.menu_title {
    font-size: 16px;
    color: #333;
}

.menu_arrow {
    color: #C7C7CC;
    font-size: 20px;
}
</style>

<div class="settings_wrapper">
    <div class="settings_container">
        <!-- 헤더 -->
        <div class="settings_header">
            <a href="javascript:history.back();" class="back_btn">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M15 19l-7-7 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </a>
            <h1>설정</h1>
        </div>

        <!-- 알림 설정 섹션 -->
        <div class="notification_section">
            <div class="section_header">
                <h2 class="section_title">알림 설정</h2>
            </div>
            
            <div class="setting_group">
                <!-- 전체 알림 -->
                <div class="setting_item" id="all_notification_item">
                    <div class="setting_content">
                        <div class="setting_title">
                            푸시 알림 설정
                        </div>
                    </div>
                    <div class="toggle_switch">
                        <input type="checkbox" id="all_notification" <?php echo $notification['ns_all_notification'] ? 'checked' : ''; ?>>
                        <span class="toggle_slider"></span>
                    </div>
                </div>
            </div>

            <div class="notification_info">
                알림을 끄면 새로운 활동이 있어도 푸시 알림을 받지 않습니다.
            </div>
        </div>

        <!-- 설정 메뉴 -->
        <div class="menu_section">
            <div class="menu_item" onclick="location.href='<?php echo G5_BBS_URL ?>/member_confirm.php?url=<?php echo G5_BBS_URL ?>/member_leave.php';">
                <span class="menu_title">회원 탈퇴</span>
                <span class="menu_arrow">›</span>
            </div>
            
            <div class="menu_item" onclick="location.href='<?php echo G5_BBS_URL ?>/faq.php';">
                <span class="menu_title">자주 묻는 질문</span>
                <span class="menu_arrow">›</span>
            </div>
            
            <div class="menu_item" onclick="location.href='<?php echo G5_BBS_URL ?>/content.php?co_id=privacy';">
                <span class="menu_title">개인정보 처리방침</span>
                <span class="menu_arrow">›</span>
            </div>
            
            <div class="menu_item" onclick="location.href='<?php echo G5_BBS_URL ?>/content.php?co_id=provision';">
                <span class="menu_title">이용약관</span>
                <span class="menu_arrow">›</span>
            </div>
            
            <div class="menu_item" onclick="location.href='<?php echo G5_BBS_URL ?>/qalist.php';">
                <span class="menu_title">1:1 문의</span>
                <span class="menu_arrow">›</span>
            </div>
            
            <div class="menu_item" onclick="if(confirm('로그아웃 하시겠습니까?')) location.href='<?php echo G5_BBS_URL ?>/logout.php';">
                <span class="menu_title">로그아웃</span>
                <span class="menu_arrow">›</span>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // 토글 스위치 클릭 이벤트
    $('.toggle_switch').on('click', function(e) {
        e.stopPropagation();
        var checkbox = $(this).find('input[type="checkbox"]');
        
        // 체크박스 상태 토글
        checkbox.prop('checked', !checkbox.prop('checked'));
        checkbox.trigger('change');
    });
    
    // 체크박스 직접 클릭 시 이벤트 중복 방지
    $('.toggle_switch input[type="checkbox"]').on('click', function(e) {
        e.stopPropagation();
    });
    
    // 전체 알림 토글
    $('#all_notification').on('change', function() {
        var isChecked = $(this).is(':checked');
        var pushState = isChecked ? 'on' : 'off';
        
        // URL 변경 (페이지 리로드)
        window.location.href = '<?php echo G5_URL ?>/rb/settings.php?push=' + pushState;
    });
});
</script>

</body>
</html>