<?php
/**
 * 관리자 레이아웃 헤더
 * @package AddonAppAdmin
 */

if (!defined('ADDON_APP_ADMIN_PATH')) exit;

$current_page = basename($_SERVER['PHP_SELF']);

// 사이트 이름 가져오기
$layout_site_name = addon_app_admin_get_config('site_name', 'HAIR WANG');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<?php require_once __DIR__ . '/../../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($g5['title']) ? $g5['title'] : '앱 관리자'; ?> - <?php echo htmlspecialchars($layout_site_name); ?></title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="<?php echo ADDON_APP_ADMIN_URL; ?>/assets/css/admin.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: #f5f7fa; }

        /* 상단 헤더 */
        .admin-topbar {
            background: #fff;
            border-bottom: 1px solid #e0e0e0;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 60px;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .admin-topbar-left { display: flex; align-items: center; gap: 20px; }
        .admin-logo { font-size: 20px; font-weight: bold; color: #aa20ff; text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .admin-logo i { font-size: 24px; }
        .admin-topbar-right { display: flex; align-items: center; gap: 20px; }
        .admin-user-info { display: flex; align-items: center; gap: 10px; padding: 8px 15px; background: #f5f0ff; border-radius: 20px; }
        .admin-user-info i { color: #aa20ff; }
        .admin-user-name { font-weight: 500; color: #333; }
        .admin-btn-logout { padding: 8px 15px; background: #fff; border: 1px solid #ddd; border-radius: 6px; color: #666; text-decoration: none; transition: all 0.3s; }
        .admin-btn-logout:hover { background: #f5f5f5; border-color: #aa20ff; color: #aa20ff; }

        /* 사이드바 + 컨텐츠 영역 */
        .admin-layout { display: flex; margin-top: 60px; min-height: calc(100vh - 60px); }

        /* 사이드바 */
        .admin-sidebar {
            width: 260px;
            background: #fff;
            border-right: 1px solid #e0e0e0;
            position: fixed;
            left: 0;
            top: 60px;
            bottom: 0;
            overflow-y: auto;
            padding: 20px 0;
        }
        .admin-sidebar-menu { list-style: none; }
        .admin-sidebar-menu li { margin-bottom: 5px; }
        .admin-sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: #666;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        .admin-sidebar-menu a:hover {
            background: #f5f0ff;
            color: #aa20ff;
            border-left-color: #aa20ff;
        }
        .admin-sidebar-menu a.active {
            background: #f5f0ff;
            color: #aa20ff;
            font-weight: 600;
            border-left-color: #aa20ff;
        }
        .admin-sidebar-menu a i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        /* 메인 컨텐츠 */
        .admin-content {
            margin-left: 260px;
            flex: 1;
            padding: 30px;
            background: #f5f7fa;
        }
        .admin-content-header {
            margin-bottom: 30px;
        }
        .admin-content-title {
            font-size: 28px;
            color: #333;
            margin-bottom: 8px;
        }
        .admin-content-desc {
            color: #666;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .admin-sidebar { width: 200px; }
            .admin-content { margin-left: 200px; padding: 20px; }
        }
    </style>
</head>
<body>
<script>
// ============================================
// _common.php 디버그 정보 콘솔 출력
// ============================================
console.log('%c========================================', 'color: #667eea; font-weight: bold; font-size: 16px;');
console.log('%c🔧 _common.php 로드 정보', 'color: #667eea; font-weight: bold; font-size: 14px;');
console.log('%c========================================', 'color: #667eea; font-weight: bold; font-size: 16px;');

var commonDebug = <?php echo json_encode($GLOBALS['addon_debug'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?>;
console.log('%c1️⃣ 전체 공통 정보:', 'color: #9c27b0; font-weight: bold;');
console.table(commonDebug);

console.log('%c2️⃣ G5_PATH:', 'color: #9c27b0; font-weight: bold;', commonDebug.g5_path);
console.log('%c3️⃣ 관리자 여부 ($is_admin):', 'color: #9c27b0; font-weight: bold;', commonDebug.is_admin);
console.log('%c4️⃣ 회원 정보 존재:', 'color: #9c27b0; font-weight: bold;', commonDebug.member_exists ? '✅ 존재' : '❌ 없음');
console.log('%c5️⃣ 회원 정보 배열:', 'color: #9c27b0; font-weight: bold;', commonDebug.member_is_array ? '✅ 배열' : '❌ 배열 아님');

if (commonDebug.member_is_array && commonDebug.member_data) {
    console.log('%c6️⃣ 회원 상세 정보:', 'color: #9c27b0; font-weight: bold;');
    console.table(commonDebug.member_data);
} else {
    console.warn('%c⚠️ 회원 정보 없음', 'color: #ff9800; font-weight: bold;');
}

console.log('%c7️⃣ G5 설정:', 'color: #9c27b0; font-weight: bold;');
console.table(commonDebug.g5_config);

console.log('%c8️⃣ 애드온 테이블:', 'color: #9c27b0; font-weight: bold;');
console.table(commonDebug.addon_tables);

console.log('%c========================================', 'color: #667eea; font-weight: bold; font-size: 16px;');
</script>
    <!-- 상단 헤더 -->
    <div class="admin-topbar">
        <div class="admin-topbar-left">
            <a href="./index.php" class="admin-logo">
                <i class="fa fa-mobile"></i>
                <span><?php echo htmlspecialchars($layout_site_name); ?> 앱 관리자</span>
            </a>
        </div>
        <div class="admin-topbar-right">
            <div class="admin-user-info">
                <i class="fa fa-user-circle"></i>
                <?php
                // 디버그: 사용자 정보 확인
                error_log('[LAYOUT] $member isset: ' . (isset($member) ? 'YES' : 'NO'));
                error_log('[LAYOUT] $member is_array: ' . (isset($member) && is_array($member) ? 'YES' : 'NO'));
                if (isset($member) && is_array($member)) {
                    error_log('[LAYOUT] mb_name: ' . (isset($member['mb_name']) ? $member['mb_name'] : 'NOT SET'));
                    error_log('[LAYOUT] mb_id: ' . (isset($member['mb_id']) ? $member['mb_id'] : 'NOT SET'));
                }

                // 사용자 정보 표시 (우선순위: mb_name > mb_id > 게스트)
                if (isset($member) && is_array($member)) {
                    if (!empty($member['mb_name'])) {
                        echo '<span class="admin-user-name">' . htmlspecialchars($member['mb_name']) . '</span>';
                        if (!empty($member['mb_id'])) {
                            echo '<span style="color: #999; font-size: 12px;">(' . htmlspecialchars($member['mb_id']) . ')</span>';
                        }
                    } else if (!empty($member['mb_id'])) {
                        echo '<span class="admin-user-name">' . htmlspecialchars($member['mb_id']) . '</span>';
                    } else {
                        echo '<span class="admin-user-name">게스트 (member 배열 비어있음)</span>';
                    }
                } else {
                    echo '<span class="admin-user-name">게스트 (member 없음)</span>';
                }
                ?>
            </div>
            <a href="<?php echo G5_BBS_URL; ?>/logout.php" class="admin-btn-logout">
                <i class="fa fa-sign-out"></i> 로그아웃
            </a>
        </div>
    </div>

    <div class="admin-layout">
        <!-- 사이드바 -->
        <div class="admin-sidebar">
            <ul class="admin-sidebar-menu">
                <li>
                    <a href="./index.php" class="<?php echo $current_page === 'index.php' ? 'active' : ''; ?>">
                        <i class="fa fa-dashboard"></i>
                        <span>대시보드</span>
                    </a>
                </li>
                <li>
                    <a href="./boards.php" class="<?php echo $current_page === 'boards.php' || $current_page === 'board-edit.php' ? 'active' : ''; ?>">
                        <i class="fa fa-th-list"></i>
                        <span>게시판 관리</span>
                    </a>
                </li>
                <li>
                    <a href="./header.php" class="<?php echo $current_page === 'header.php' ? 'active' : ''; ?>">
                        <i class="fa fa-header"></i>
                        <span>사이트 헤더</span>
                    </a>
                </li>
                <li>
                    <a href="./footer.php" class="<?php echo $current_page === 'footer.php' ? 'active' : ''; ?>">
                        <i class="fa fa-info-circle"></i>
                        <span>사이트 푸터</span>
                    </a>
                </li>
                <li>
                    <a href="./main.php" class="<?php echo $current_page === 'main.php' ? 'active' : ''; ?>">
                        <i class="fa fa-home"></i>
                        <span>메인 화면</span>
                    </a>
                </li>
                <li>
                    <a href="./app-package.php" class="<?php echo in_array($current_page, ['app-package.php', 'app-image-editor.php']) ? 'active' : ''; ?>">
                        <i class="fa fa-mobile"></i>
                        <span>앱 패키징</span>
                    </a>
                </li>
                <li>
                    <a href="./media.php" class="<?php echo $current_page === 'media.php' ? 'active' : ''; ?>">
                        <i class="fa fa-image"></i>
                        <span>미디어 관리</span>
                    </a>
                </li>
                <li>
                    <a href="./config.php" class="<?php echo $current_page === 'config.php' ? 'active' : ''; ?>">
                        <i class="fa fa-cog"></i>
                        <span>사이트 설정</span>
                    </a>
                </li>
                <li>
                    <a href="./logs.php" class="<?php echo $current_page === 'logs.php' ? 'active' : ''; ?>">
                        <i class="fa fa-history"></i>
                        <span>활동 로그</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- 메인 컨텐츠 -->
        <div class="admin-content">
