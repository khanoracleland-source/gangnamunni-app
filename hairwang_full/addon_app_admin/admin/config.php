<?php
/**
 * 사이트 설정 관리
 * @package AddonAppAdmin
 * @version 1.0.0
 */

include_once('./_common.php');

// 권한 체크 제거됨
// if (!addon_app_admin_check_permission()) {
//     addon_app_admin_permission_denied();
// }

// 설정 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    check_admin_token();

    // 일반 설정
    if (isset($_POST['site_title'])) {
        addon_app_admin_set_config('site_title', $_POST['site_title'], 'text', 'general');
    }

    if (isset($_POST['site_description'])) {
        addon_app_admin_set_config('site_description', $_POST['site_description'], 'text', 'general');
    }

    if (isset($_POST['site_keywords'])) {
        addon_app_admin_set_config('site_keywords', $_POST['site_keywords'], 'text', 'general');
    }

    // 기능 설정
    if (isset($_POST['enable_search'])) {
        addon_app_admin_set_config('enable_search', 1, 'boolean', 'features');
    } else {
        addon_app_admin_set_config('enable_search', 0, 'boolean', 'features');
    }

    if (isset($_POST['enable_notifications'])) {
        addon_app_admin_set_config('enable_notifications', 1, 'boolean', 'features');
    } else {
        addon_app_admin_set_config('enable_notifications', 0, 'boolean', 'features');
    }

    if (isset($_POST['enable_comments'])) {
        addon_app_admin_set_config('enable_comments', 1, 'boolean', 'features');
    } else {
        addon_app_admin_set_config('enable_comments', 0, 'boolean', 'features');
    }

    // 디스플레이 설정
    if (isset($_POST['items_per_page'])) {
        addon_app_admin_set_config('items_per_page', intval($_POST['items_per_page']), 'number', 'display');
    }

    if (isset($_POST['theme_color'])) {
        addon_app_admin_set_config('theme_color', $_POST['theme_color'], 'color', 'display');
    }

    if (isset($_POST['enable_dark_mode'])) {
        addon_app_admin_set_config('enable_dark_mode', 1, 'boolean', 'display');
    } else {
        addon_app_admin_set_config('enable_dark_mode', 0, 'boolean', 'display');
    }

    // SNS 설정
    if (isset($_POST['facebook_url'])) {
        addon_app_admin_set_config('facebook_url', $_POST['facebook_url'], 'text', 'sns');
    }

    if (isset($_POST['instagram_url'])) {
        addon_app_admin_set_config('instagram_url', $_POST['instagram_url'], 'text', 'sns');
    }

    if (isset($_POST['youtube_url'])) {
        addon_app_admin_set_config('youtube_url', $_POST['youtube_url'], 'text', 'sns');
    }

    if (isset($_POST['twitter_url'])) {
        addon_app_admin_set_config('twitter_url', $_POST['twitter_url'], 'text', 'sns');
    }

    addon_app_admin_log('config_update', 'site_config', '사이트 설정 업데이트');
    alert('설정이 저장되었습니다.', './config.php');
}

// 레이아웃 헤더
$g5['title'] = '사이트 설정';
include_once('./layout_header.php');

// 현재 설정 가져오기
$site_title = addon_app_admin_get_config('site_title', 'Hair Wang');
$site_description = addon_app_admin_get_config('site_description', '');
$site_keywords = addon_app_admin_get_config('site_keywords', '');

$enable_search = addon_app_admin_get_config('enable_search', 1);
$enable_notifications = addon_app_admin_get_config('enable_notifications', 1);
$enable_comments = addon_app_admin_get_config('enable_comments', 1);

$items_per_page = addon_app_admin_get_config('items_per_page', 20);
$theme_color = addon_app_admin_get_config('theme_color', '#aa20ff');
$enable_dark_mode = addon_app_admin_get_config('enable_dark_mode', 0);

$facebook_url = addon_app_admin_get_config('facebook_url', '');
$instagram_url = addon_app_admin_get_config('instagram_url', '');
$youtube_url = addon_app_admin_get_config('youtube_url', '');

// 페이지 디버그 정보
$GLOBALS['page_debug'] = array(
    'page' => '사이트 설정',
    'site_info' => array(
        'title' => $site_title,
        'description' => $site_description,
        'keywords' => $site_keywords
    ),
    'features' => array(
        'search' => $enable_search,
        'notifications' => $enable_notifications,
        'comments' => $enable_comments,
        'dark_mode' => $enable_dark_mode
    ),
    'sns' => array(
        'facebook' => !empty($facebook_url),
        'instagram' => !empty($instagram_url),
        'youtube' => !empty($youtube_url)
    )
);
$twitter_url = addon_app_admin_get_config('twitter_url', '');

?>

<link rel="stylesheet" href="<?php echo ADDON_APP_ADMIN_URL; ?>/assets/css/admin.css">

<div class="addon-app-admin-wrap">

    <div class="addon-header">
        <div class="addon-container">
            <div class="addon-header-inner">
                <h1 class="addon-logo">
                    <i class="fa fa-cog"></i> 사이트 설정
                </h1>
                <div class="addon-header-right">
                    <a href="./index.php" class="addon-btn addon-btn-sm addon-btn-secondary">
                        <i class="fa fa-arrow-left"></i> 대시보드로
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="addon-container">

        <!-- 설명 -->
        <div class="addon-section addon-mt-30">
            <p style="margin: 0; color: #666; line-height: 1.6;">
                <i class="fa fa-info-circle" style="color: var(--primary-color);"></i>
                사이트 전반적인 설정을 관리합니다. 변경사항은 즉시 적용됩니다.
            </p>
        </div>

        <form method="post" action="">
            <?php echo get_admin_token_field(); ?>
            <input type="hidden" name="action" value="save">

            <!-- 일반 설정 -->
            <div class="addon-section addon-mt-30">
                <h2 class="addon-section-title">
                    <i class="fa fa-info-circle"></i> 일반 설정
                </h2>

                <div class="addon-form-group">
                    <label class="addon-form-label">사이트 제목</label>
                    <input type="text" name="site_title" class="addon-form-input" value="<?php echo htmlspecialchars($site_title); ?>" placeholder="Hair Wang">
                    <small class="addon-form-help">브라우저 탭에 표시되는 제목</small>
                </div>

                <div class="addon-form-group">
                    <label class="addon-form-label">사이트 설명</label>
                    <textarea name="site_description" class="addon-form-input" rows="3" placeholder="서울 최고의 헤어 살롱"><?php echo htmlspecialchars($site_description); ?></textarea>
                    <small class="addon-form-help">검색 엔진에 표시되는 설명 (SEO)</small>
                </div>

                <div class="addon-form-group">
                    <label class="addon-form-label">검색 키워드</label>
                    <input type="text" name="site_keywords" class="addon-form-input" value="<?php echo htmlspecialchars($site_keywords); ?>" placeholder="헤어살롱, 미용실, 서울 헤어">
                    <small class="addon-form-help">쉼표로 구분된 검색 키워드 (SEO)</small>
                </div>
            </div>

            <!-- 기능 설정 -->
            <div class="addon-section addon-mt-30">
                <h2 class="addon-section-title">
                    <i class="fa fa-puzzle-piece"></i> 기능 설정
                </h2>

                <div class="feature-toggles">
                    <div class="feature-toggle">
                        <div class="feature-toggle-info">
                            <h3><i class="fa fa-search"></i> 검색 기능</h3>
                            <p>사이트 내 검색 기능을 활성화합니다.</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="enable_search" value="1" <?php echo $enable_search ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="feature-toggle">
                        <div class="feature-toggle-info">
                            <h3><i class="fa fa-bell"></i> 알림 기능</h3>
                            <p>회원에게 알림을 보낼 수 있습니다.</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="enable_notifications" value="1" <?php echo $enable_notifications ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="feature-toggle">
                        <div class="feature-toggle-info">
                            <h3><i class="fa fa-comment"></i> 댓글 기능</h3>
                            <p>게시글에 댓글을 달 수 있습니다.</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="enable_comments" value="1" <?php echo $enable_comments ? 'checked' : ''; ?>>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- 디스플레이 설정 -->
            <div class="addon-section addon-mt-30">
                <h2 class="addon-section-title">
                    <i class="fa fa-desktop"></i> 디스플레이 설정
                </h2>

                <div class="addon-form-row">
                    <div class="addon-form-group">
                        <label class="addon-form-label">페이지당 항목 수</label>
                        <input type="number" name="items_per_page" class="addon-form-input" value="<?php echo htmlspecialchars($items_per_page); ?>" min="5" max="100">
                        <small class="addon-form-help">목록에 표시할 항목 개수</small>
                    </div>

                    <div class="addon-form-group">
                        <label class="addon-form-label">테마 색상</label>
                        <input type="color" name="theme_color" class="addon-form-input" value="<?php echo htmlspecialchars($theme_color); ?>" style="height: 60px;">
                        <small class="addon-form-help">사이트 메인 색상</small>
                    </div>
                </div>

                <div class="feature-toggle">
                    <div class="feature-toggle-info">
                        <h3><i class="fa fa-moon-o"></i> 다크 모드</h3>
                        <p>다크 모드를 지원합니다.</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="enable_dark_mode" value="1" <?php echo $enable_dark_mode ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>

            <!-- SNS 설정 -->
            <div class="addon-section addon-mt-30">
                <h2 class="addon-section-title">
                    <i class="fa fa-share-alt"></i> SNS 설정
                </h2>

                <div class="addon-form-group">
                    <label class="addon-form-label"><i class="fa fa-facebook" style="color: #1877F2;"></i> Facebook URL</label>
                    <input type="url" name="facebook_url" class="addon-form-input" value="<?php echo htmlspecialchars($facebook_url); ?>" placeholder="https://facebook.com/yourpage">
                </div>

                <div class="addon-form-group">
                    <label class="addon-form-label"><i class="fa fa-instagram" style="color: #E4405F;"></i> Instagram URL</label>
                    <input type="url" name="instagram_url" class="addon-form-input" value="<?php echo htmlspecialchars($instagram_url); ?>" placeholder="https://instagram.com/yourpage">
                </div>

                <div class="addon-form-group">
                    <label class="addon-form-label"><i class="fa fa-youtube-play" style="color: #FF0000;"></i> YouTube URL</label>
                    <input type="url" name="youtube_url" class="addon-form-input" value="<?php echo htmlspecialchars($youtube_url); ?>" placeholder="https://youtube.com/@yourchannel">
                </div>

                <div class="addon-form-group">
                    <label class="addon-form-label"><i class="fa fa-twitter" style="color: #1DA1F2;"></i> Twitter (X) URL</label>
                    <input type="url" name="twitter_url" class="addon-form-input" value="<?php echo htmlspecialchars($twitter_url); ?>" placeholder="https://twitter.com/yourhandle">
                </div>
            </div>

            <!-- 저장 버튼 -->
            <div class="addon-section addon-mt-30" style="text-align: center;">
                <button type="submit" class="addon-btn addon-btn-primary addon-btn-lg">
                    <i class="fa fa-save"></i> 설정 저장
                </button>
            </div>

        </form>

    </div>

    <div class="addon-footer">
        <div class="addon-container">
            <div class="addon-footer-content">
                <p>&copy; 2026 Hair Wang App Admin. All rights reserved.</p>
            </div>
        </div>
    </div>

</div>

<style>
.feature-toggles {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.feature-toggle {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 10px;
    border: 2px solid #eee;
    transition: all 0.3s;
}

.feature-toggle:hover {
    border-color: var(--primary-color);
    background: #f5f0ff;
}

.feature-toggle-info {
    flex: 1;
}

.feature-toggle-info h3 {
    margin: 0 0 8px 0;
    font-size: 16px;
    color: #333;
}

.feature-toggle-info h3 i {
    margin-right: 8px;
    color: var(--primary-color);
}

.feature-toggle-info p {
    margin: 0;
    font-size: 13px;
    color: #666;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 60px;
    height: 34px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .4s;
    border-radius: 34px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 26px;
    width: 26px;
    left: 4px;
    bottom: 4px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}

input:checked + .toggle-slider {
    background-color: var(--primary-color);
}

input:focus + .toggle-slider {
    box-shadow: 0 0 1px var(--primary-color);
}

input:checked + .toggle-slider:before {
    transform: translateX(26px);
}

.addon-btn-lg {
    padding: 15px 40px;
    font-size: 16px;
}

@media (max-width: 768px) {
    .feature-toggle {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
}
</style>

<?php
include_once('./layout_footer.php');
?>
