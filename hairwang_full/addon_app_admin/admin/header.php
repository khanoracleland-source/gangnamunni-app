<?php
/**
 * 사이트 헤더 관리
 * @package AddonAppAdmin
 * @version 1.0.0
 */

include_once('./_common.php');

// 권한 체크 제거됨
// if (!addon_app_admin_check_permission()) {
//     addon_app_admin_permission_denied();
// }

// 저장 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    check_admin_token();

    // 헤더 배경색
    if (isset($_POST['header_bg_color'])) {
        addon_app_admin_set_config('header_bg_color', $_POST['header_bg_color'], 'color', 'header');
    }

    // 헤더 텍스트 색상
    if (isset($_POST['header_text_color'])) {
        addon_app_admin_set_config('header_text_color', $_POST['header_text_color'], 'color', 'header');
    }

    // 사이트 이름
    if (isset($_POST['site_name'])) {
        addon_app_admin_set_config('site_name', $_POST['site_name'], 'text', 'header');
    }

    addon_app_admin_log('header_update', 'header_config', '헤더 설정 업데이트');
    alert('헤더 설정이 저장되었습니다.', './header.php');
}

// 파일 업로드 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    check_admin_token();

    $media_type = isset($_POST['media_type']) ? $_POST['media_type'] : '';
    $media_key = isset($_POST['media_key']) ? $_POST['media_key'] : '';

    if ($media_type && $media_key && isset($_FILES['media_file'])) {
        $upload_dir = 'logos';
        if ($media_type === 'favicon') {
            $upload_dir = 'icons';
        }

        $result = addon_app_admin_upload_image($_FILES['media_file'], $upload_dir);

        if ($result['success']) {
            $data = array(
                'media_type' => $media_type,
                'media_key' => $media_key,
                'file_name' => $result['file_name'],
                'file_path' => $result['file_path'],
                'file_size' => $_FILES['media_file']['size'],
                'file_type' => $_FILES['media_file']['type'],
                'width' => $result['width'],
                'height' => $result['height'],
                'is_active' => 1
            );

            if (addon_app_admin_save_media($data)) {
                addon_app_admin_log('media_upload', $media_key, $media_type . ' 업로드');
                alert('이미지가 업로드되었습니다.', './header.php');
            }
        } else {
            alert($result['message']);
        }
    }
}

// 미디어 삭제
if (isset($_GET['action']) && $_GET['action'] === 'delete_media' && isset($_GET['media_key'])) {
    check_admin_token();

    $media_key = $_GET['media_key'];
    $media = addon_app_admin_get_media($media_key);

    if ($media && file_exists($media['file_path'])) {
        @unlink($media['file_path']);
    }

    $sql = " UPDATE {$addon_app_admin_table_media} SET is_active = 0 WHERE media_key = '{$media_key}' ";
    sql_query($sql);

    addon_app_admin_log('media_delete', $media_key, '미디어 삭제');
    alert('이미지가 삭제되었습니다.', './header.php');
}

// 레이아웃 헤더
$g5['title'] = '사이트 헤더 관리';
include_once('./layout_header.php');

// 현재 설정 가져오기
$header_bg_color = addon_app_admin_get_config('header_bg_color', '#ffffff');
$header_text_color = addon_app_admin_get_config('header_text_color', '#000000');
$site_name = addon_app_admin_get_config('site_name', 'Hair Wang');

// 미디어 가져오기
$logo_pc = addon_app_admin_get_media('logo_pc');
$logo_mobile = addon_app_admin_get_media('logo_mobile');
$favicon = addon_app_admin_get_media('favicon');

// 페이지 디버그 정보
$GLOBALS['page_debug'] = array(
    'page' => '사이트 헤더 관리',
    'settings' => array(
        'header_bg_color' => $header_bg_color,
        'header_text_color' => $header_text_color,
        'site_name' => $site_name
    ),
    'media' => array(
        'logo_pc' => array(
            'status' => $logo_pc ? 'uploaded' : 'none',
            'file_name' => $logo_pc ? $logo_pc['file_name'] : null,
            'file_path' => $logo_pc ? $logo_pc['file_path'] : null,
            'size' => $logo_pc && isset($logo_pc['file_size']) ? round($logo_pc['file_size'] / 1024, 1) . ' KB' : null
        ),
        'logo_mobile' => array(
            'status' => $logo_mobile ? 'uploaded' : 'none',
            'file_name' => $logo_mobile ? $logo_mobile['file_name'] : null,
            'file_path' => $logo_mobile ? $logo_mobile['file_path'] : null,
            'size' => $logo_mobile && isset($logo_mobile['file_size']) ? round($logo_mobile['file_size'] / 1024, 1) . ' KB' : null
        ),
        'favicon' => array(
            'status' => $favicon ? 'uploaded' : 'none',
            'file_name' => $favicon ? $favicon['file_name'] : null,
            'file_path' => $favicon ? $favicon['file_path'] : null,
            'size' => $favicon && isset($favicon['file_size']) ? round($favicon['file_size'] / 1024, 1) . ' KB' : null
        )
    ),
    'paths' => array(
        'ADDON_APP_ADMIN_DATA_PATH' => defined('ADDON_APP_ADMIN_DATA_PATH') ? ADDON_APP_ADMIN_DATA_PATH : 'NOT DEFINED',
        'ADDON_APP_ADMIN_DATA_URL' => defined('ADDON_APP_ADMIN_DATA_URL') ? ADDON_APP_ADMIN_DATA_URL : 'NOT DEFINED',
        'G5_DATA_PATH' => defined('G5_DATA_PATH') ? G5_DATA_PATH : 'NOT DEFINED',
        'G5_DATA_URL' => defined('G5_DATA_URL') ? G5_DATA_URL : 'NOT DEFINED'
    )
);

?>

<link rel="stylesheet" href="<?php echo ADDON_APP_ADMIN_URL; ?>/assets/css/admin.css">

<div class="addon-app-admin-wrap">

    <div class="addon-header">
        <div class="addon-container">
            <div class="addon-header-inner">
                <h1 class="addon-logo">
                    <i class="fa fa-header"></i> 사이트 헤더 관리
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
                사이트 헤더의 로고, 파비콘, 색상 등을 관리합니다. 변경사항은 실시간으로 미리보기에 반영됩니다.
            </p>
        </div>

        <!-- 로고 관리 -->
        <div class="addon-section addon-mt-30">
            <h2 class="addon-section-title">
                <i class="fa fa-image"></i> 로고 관리
            </h2>

            <div class="addon-form-group">
                <label class="addon-form-label">PC용 로고</label>
                <div class="media-upload-box">
                    <?php if ($logo_pc): ?>
                        <div class="media-preview">
                            <?php
                            // file_path가 있으면 사용, 없으면 file_name으로 경로 생성
                            $logo_pc_url = !empty($logo_pc['file_path']) && file_exists($logo_pc['file_path'])
                                ? str_replace(G5_PATH, G5_URL, $logo_pc['file_path'])
                                : ADDON_APP_ADMIN_DATA_URL . '/uploads/logos/' . $logo_pc['file_name'];
                            ?>
                            <img src="<?php echo $logo_pc_url; ?>" alt="PC 로고" onerror="console.error('로고 로드 실패:', this.src);">
                            <div class="media-info">
                                <p><?php echo isset($logo_pc['width']) ? $logo_pc['width'] : 0; ?> x <?php echo isset($logo_pc['height']) ? $logo_pc['height'] : 0; ?>px</p>
                                <p><?php echo isset($logo_pc['file_size']) ? number_format($logo_pc['file_size'] / 1024, 1) : 0; ?> KB</p>
                                <p style="font-size: 11px; color: #999;">파일: <?php echo isset($logo_pc['file_name']) ? $logo_pc['file_name'] : 'N/A'; ?></p>
                            </div>
                            <div class="media-actions">
                                <a href="?action=delete_media&media_key=logo_pc&token=<?php echo get_admin_token(); ?>" class="addon-btn addon-btn-sm addon-btn-danger" onclick="return confirm('정말 삭제하시겠습니까?');">
                                    <i class="fa fa-trash"></i> 삭제
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data" class="media-upload-form">
                        <?php echo get_admin_token_field(); ?>
                        <input type="hidden" name="action" value="upload">
                        <input type="hidden" name="media_type" value="logo_pc">
                        <input type="hidden" name="media_key" value="logo_pc">
                        <input type="file" name="media_file" accept="image/*" required class="addon-form-input">
                        <button type="submit" class="addon-btn addon-btn-primary addon-mt-10">
                            <i class="fa fa-upload"></i> 업로드
                        </button>
                        <small class="addon-form-help">권장: PNG 형식, 투명 배경, 가로 200px</small>
                    </form>
                </div>
            </div>

            <div class="addon-form-group addon-mt-30">
                <label class="addon-form-label">모바일용 로고</label>
                <div class="media-upload-box">
                    <?php if ($logo_mobile): ?>
                        <div class="media-preview">
                            <?php
                            $logo_mobile_url = !empty($logo_mobile['file_path']) && file_exists($logo_mobile['file_path'])
                                ? str_replace(G5_PATH, G5_URL, $logo_mobile['file_path'])
                                : ADDON_APP_ADMIN_DATA_URL . '/uploads/logos/' . $logo_mobile['file_name'];
                            ?>
                            <img src="<?php echo $logo_mobile_url; ?>" alt="모바일 로고" onerror="console.error('로고 로드 실패:', this.src);">
                            <div class="media-info">
                                <p><?php echo isset($logo_mobile['width']) ? $logo_mobile['width'] : 0; ?> x <?php echo isset($logo_mobile['height']) ? $logo_mobile['height'] : 0; ?>px</p>
                                <p><?php echo isset($logo_mobile['file_size']) ? number_format($logo_mobile['file_size'] / 1024, 1) : 0; ?> KB</p>
                                <p style="font-size: 11px; color: #999;">파일: <?php echo isset($logo_mobile['file_name']) ? $logo_mobile['file_name'] : 'N/A'; ?></p>
                            </div>
                            <div class="media-actions">
                                <a href="?action=delete_media&media_key=logo_mobile&token=<?php echo get_admin_token(); ?>" class="addon-btn addon-btn-sm addon-btn-danger" onclick="return confirm('정말 삭제하시겠습니까?');">
                                    <i class="fa fa-trash"></i> 삭제
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data" class="media-upload-form">
                        <?php echo get_admin_token_field(); ?>
                        <input type="hidden" name="action" value="upload">
                        <input type="hidden" name="media_type" value="logo_mobile">
                        <input type="hidden" name="media_key" value="logo_mobile">
                        <input type="file" name="media_file" accept="image/*" required class="addon-form-input">
                        <button type="submit" class="addon-btn addon-btn-primary addon-mt-10">
                            <i class="fa fa-upload"></i> 업로드
                        </button>
                        <small class="addon-form-help">권장: PNG 형식, 투명 배경, 가로 120px</small>
                    </form>
                </div>
            </div>

            <div class="addon-form-group addon-mt-30">
                <label class="addon-form-label">파비콘 (Favicon)</label>
                <div class="media-upload-box">
                    <?php if ($favicon): ?>
                        <div class="media-preview">
                            <?php
                            $favicon_url = !empty($favicon['file_path']) && file_exists($favicon['file_path'])
                                ? str_replace(G5_PATH, G5_URL, $favicon['file_path'])
                                : ADDON_APP_ADMIN_DATA_URL . '/uploads/icons/' . $favicon['file_name'];
                            ?>
                            <img src="<?php echo $favicon_url; ?>" alt="파비콘" style="max-width: 64px;" onerror="console.error('파비콘 로드 실패:', this.src);">
                            <div class="media-info">
                                <p><?php echo isset($favicon['width']) ? $favicon['width'] : 0; ?> x <?php echo isset($favicon['height']) ? $favicon['height'] : 0; ?>px</p>
                                <p><?php echo isset($favicon['file_size']) ? number_format($favicon['file_size'] / 1024, 1) : 0; ?> KB</p>
                                <p style="font-size: 11px; color: #999;">파일: <?php echo isset($favicon['file_name']) ? $favicon['file_name'] : 'N/A'; ?></p>
                            </div>
                            <div class="media-actions">
                                <a href="?action=delete_media&media_key=favicon&token=<?php echo get_admin_token(); ?>" class="addon-btn addon-btn-sm addon-btn-danger" onclick="return confirm('정말 삭제하시겠습니까?');">
                                    <i class="fa fa-trash"></i> 삭제
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data" class="media-upload-form">
                        <?php echo get_admin_token_field(); ?>
                        <input type="hidden" name="action" value="upload">
                        <input type="hidden" name="media_type" value="favicon">
                        <input type="hidden" name="media_key" value="favicon">
                        <input type="file" name="media_file" accept="image/*" required class="addon-form-input">
                        <button type="submit" class="addon-btn addon-btn-primary addon-mt-10">
                            <i class="fa fa-upload"></i> 업로드
                        </button>
                        <small class="addon-form-help">권장: ICO 또는 PNG, 32x32px 또는 64x64px</small>
                    </form>
                </div>
            </div>
        </div>

        <!-- 헤더 설정 -->
        <div class="addon-section addon-mt-30">
            <h2 class="addon-section-title">
                <i class="fa fa-cog"></i> 헤더 설정
            </h2>

            <form method="post" action="">
                <?php echo get_admin_token_field(); ?>
                <input type="hidden" name="action" value="save">

                <div class="addon-form-group">
                    <label class="addon-form-label">사이트 이름</label>
                    <input type="text" name="site_name" class="addon-form-input" value="<?php echo htmlspecialchars($site_name); ?>" placeholder="Hair Wang">
                    <small class="addon-form-help">사이트 이름 (로고가 없을 때 표시)</small>
                </div>

                <div class="addon-form-group">
                    <label class="addon-form-label">헤더 배경색</label>
                    <input type="color" name="header_bg_color" class="addon-form-input" value="<?php echo htmlspecialchars($header_bg_color); ?>" style="height: 60px;">
                    <small class="addon-form-help">헤더 영역의 배경 색상</small>
                </div>

                <div class="addon-form-group">
                    <label class="addon-form-label">헤더 텍스트 색상</label>
                    <input type="color" name="header_text_color" class="addon-form-input" value="<?php echo htmlspecialchars($header_text_color); ?>" style="height: 60px;">
                    <small class="addon-form-help">헤더 메뉴 및 텍스트 색상</small>
                </div>

                <button type="submit" class="addon-btn addon-btn-primary">
                    <i class="fa fa-save"></i> 설정 저장
                </button>
            </form>
        </div>

        <!-- 미리보기 -->
        <div class="addon-section addon-mt-30">
            <h2 class="addon-section-title">
                <i class="fa fa-eye"></i> 미리보기
            </h2>
            <div class="header-preview" style="background-color: <?php echo htmlspecialchars($header_bg_color); ?>; color: <?php echo htmlspecialchars($header_text_color); ?>; padding: 20px; border-radius: 10px;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <?php if ($logo_pc): ?>
                            <img src="<?php echo ADDON_APP_ADMIN_DATA_URL . '/uploads/logos/' . $logo_pc['file_name']; ?>" alt="로고" style="max-height: 50px;">
                        <?php else: ?>
                            <span style="font-size: 24px; font-weight: bold;"><?php echo htmlspecialchars($site_name); ?></span>
                        <?php endif; ?>
                    </div>
                    <div style="display: flex; gap: 20px;">
                        <span>메뉴1</span>
                        <span>메뉴2</span>
                        <span>메뉴3</span>
                    </div>
                </div>
            </div>
        </div>

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
.media-upload-box {
    border: 2px dashed #ddd;
    border-radius: 10px;
    padding: 20px;
    background: #f9f9f9;
}

.media-preview {
    display: flex;
    gap: 20px;
    align-items: center;
    padding: 15px;
    background: white;
    border-radius: 8px;
    margin-bottom: 15px;
}

.media-preview img {
    max-width: 200px;
    max-height: 100px;
    object-fit: contain;
}

.media-info {
    flex: 1;
}

.media-info p {
    margin: 5px 0;
    font-size: 14px;
    color: #666;
}

.media-upload-form {
    padding: 15px;
}

.header-preview {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
</style>

<?php
include_once('./layout_footer.php');
?>
