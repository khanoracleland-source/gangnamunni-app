<?php
/**
 * 메인 페이지 콘텐츠 관리
 * @package AddonAppAdmin
 * @version 1.0.0
 */

include_once('./_common.php');

// 권한 체크 제거됨
// if (!addon_app_admin_check_permission()) {
//     addon_app_admin_permission_denied();
// }

// 메인 콘텐츠 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_content') {
    check_admin_token();

    $content_key = isset($_POST['content_key']) ? $_POST['content_key'] : '';
    $title = isset($_POST['title']) ? $_POST['title'] : '';
    $subtitle = isset($_POST['subtitle']) ? $_POST['subtitle'] : '';
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    $button_text = isset($_POST['button_text']) ? $_POST['button_text'] : '';
    $button_link = isset($_POST['button_link']) ? $_POST['button_link'] : '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;
    $content_type = isset($_POST['content_type']) ? $_POST['content_type'] : 'banner';

    if ($content_key && $title) {
        $data = array(
            'content_key' => $content_key,
            'content_type' => $content_type,
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description,
            'button_text' => $button_text,
            'button_link' => $button_link,
            'is_active' => $is_active,
            'sort_order' => $sort_order
        );

        if (addon_app_admin_save_main_content($data)) {
            addon_app_admin_log('main_content_update', $content_key, '메인 콘텐츠 업데이트: ' . $title);
            alert('메인 콘텐츠가 저장되었습니다.', './main.php');
        } else {
            alert('저장 중 오류가 발생했습니다.');
        }
    } else {
        alert('필수 항목을 입력해주세요.');
    }
}

// 메인 콘텐츠 삭제
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['content_key'])) {
    check_admin_token();

    $content_key = $_GET['content_key'];

    $sql = " DELETE FROM {$addon_app_admin_table_main_content} WHERE content_key = '{$content_key}' ";
    sql_query($sql);

    addon_app_admin_log('main_content_delete', $content_key, '메인 콘텐츠 삭제');
    alert('메인 콘텐츠가 삭제되었습니다.', './main.php');
}

// 배너 이미지 업로드
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_banner') {
    check_admin_token();

    $content_key = isset($_POST['content_key']) ? $_POST['content_key'] : '';

    if ($content_key && isset($_FILES['banner_image'])) {
        $upload_dir = 'banners';

        $result = addon_app_admin_upload_image($_FILES['banner_image'], $upload_dir);

        if ($result['success']) {
            $data = array(
                'media_type' => 'banner',
                'media_key' => $content_key,
                'file_name' => $result['file_name'],
                'file_path' => $result['file_path'],
                'file_size' => $_FILES['banner_image']['size'],
                'file_type' => $_FILES['banner_image']['type'],
                'width' => $result['width'],
                'height' => $result['height'],
                'is_active' => 1
            );

            if (addon_app_admin_save_media($data)) {
                addon_app_admin_log('banner_upload', $content_key, '배너 이미지 업로드');
                alert('배너 이미지가 업로드되었습니다.', './main.php');
            }
        } else {
            alert($result['message']);
        }
    }
}

// 배너 이미지 삭제
if (isset($_GET['action']) && $_GET['action'] === 'delete_banner' && isset($_GET['media_key'])) {
    check_admin_token();

    $media_key = $_GET['media_key'];
    $media = addon_app_admin_get_media($media_key);

    if ($media && file_exists($media['file_path'])) {
        @unlink($media['file_path']);
    }

    $sql = " UPDATE {$addon_app_admin_table_media} SET is_active = 0 WHERE media_key = '{$media_key}' ";
    sql_query($sql);

    addon_app_admin_log('banner_delete', $media_key, '배너 이미지 삭제');
    alert('배너 이미지가 삭제되었습니다.', './main.php');
}

// 사이트 이름 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_site_name') {
    check_admin_token();

    $site_name = isset($_POST['site_name']) ? trim($_POST['site_name']) : '';

    if ($site_name) {
        addon_app_admin_set_config('site_name', $site_name, 'text', 'site');
        addon_app_admin_log('site_name_update', 'site_name', '사이트 이름 변경: ' . $site_name);
        alert('사이트 이름이 저장되었습니다.', './main.php');
    } else {
        alert('사이트 이름을 입력해주세요.');
    }
}

// 메인 로고는 G5 관리자 페이지에서 관리
// data/logos/pc, mo, pc_w, mo_w 파일로 직접 서빙됨

// 레이아웃 헤더
$g5['title'] = '메인 페이지 관리';
include_once('./layout_header.php');

// 현재 사이트 이름 가져오기
$site_name = addon_app_admin_get_config('site_name', 'HAIR WANG');

// 메인 로고는 G5의 data/logos/pc 파일에서 직접 가져옴

// 메인 콘텐츠 가져오기
$sql = " SELECT * FROM {$addon_app_admin_table_main_content} ORDER BY content_type ASC, sort_order ASC, content_key ASC ";
$result = sql_query($sql);
$main_contents = array();
while ($row = sql_fetch_array($result)) {
    $main_contents[] = $row;
}

// 타입별로 분류
$banners = array();
$sections = array();
foreach ($main_contents as $content) {
    if ($content['content_type'] === 'banner') {
        $banners[] = $content;
    } else {
        $sections[] = $content;
    }
}

// 페이지 디버그 정보
$GLOBALS['page_debug'] = array(
    'page' => '메인 페이지 관리',
    'sql_query' => $sql,
    'total_contents' => count($main_contents),
    'banners_count' => count($banners),
    'sections_count' => count($sections),
    'site_name' => $site_name,
    'main_logo_path' => G5_PATH . '/data/logos/pc',
    'main_logo_exists' => file_exists(G5_PATH . '/data/logos/pc') ? 'YES' : 'NO',
    'main_logo_url' => G5_URL . '/data/logos/pc?ver=' . G5_SERVER_TIME
);

?>

<div class="admin-content-header">
    <h1 class="admin-content-title">메인 페이지 관리</h1>
    <p class="admin-content-desc">
        <i class="fa fa-info-circle"></i>
        메인 페이지의 사이트 이름, 로고, 배너, 콘텐츠를 관리합니다.
    </p>
</div>

<!-- 사이트 설정 -->
<div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 30px;">
    <h2 style="font-size: 20px; color: #333; margin-bottom: 20px;">
        <i class="fa fa-cog"></i> 사이트 기본 설정
    </h2>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
        <!-- 사이트 이름 -->
        <div>
            <h3 style="font-size: 16px; color: #333; margin-bottom: 15px;">사이트 이름</h3>
            <form method="post" action="">
                <?php echo get_admin_token_field(); ?>
                <input type="hidden" name="action" value="save_site_name">
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="site_name" value="<?php echo htmlspecialchars($site_name); ?>" placeholder="예: HAIR WANG" style="flex: 1; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 15px;">
                    <button type="submit" style="padding: 12px 24px; background: #aa20ff; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        <i class="fa fa-save"></i> 저장
                    </button>
                </div>
            </form>
        </div>

        <!-- 메인 로고 -->
        <div>
            <h3 style="font-size: 16px; color: #333; margin-bottom: 15px;">메인 로고 (PC용)</h3>
            <?php
            // Gnuboard5 기본 로고 사용 (data/logos/pc)
            // 이 로고는 G5 기본 설정에서 관리되며, 확장자 없이 서빙됨
            $main_logo_url = G5_URL . '/data/logos/pc?ver=' . G5_SERVER_TIME;
            $main_logo_path = G5_PATH . '/data/logos/pc';
            $logo_exists = file_exists($main_logo_path);

            // 로고 파일이 존재하면 크기 정보 가져오기
            $logo_width = 0;
            $logo_height = 0;
            if ($logo_exists) {
                $image_info = @getimagesize($main_logo_path);
                if ($image_info) {
                    $logo_width = $image_info[0];
                    $logo_height = $image_info[1];
                }
            }
            ?>
            <div style="display: flex; gap: 15px; align-items: flex-start;">
                <div>
                    <?php if ($logo_exists): ?>
                        <div style="width: 160px; height: 100px; border: 2px solid #e0e0e0; border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center; background: #f9f9f9; margin-bottom: 8px; position: relative;">
                            <img src="<?php echo $main_logo_url; ?>" alt="메인 로고" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                        </div>
                        <?php if ($logo_width > 0 && $logo_height > 0): ?>
                            <div style="font-size: 12px; color: #666; text-align: center; margin-bottom: 8px;">
                                <?php echo number_format($logo_width); ?> × <?php echo number_format($logo_height); ?> px
                            </div>
                        <?php endif; ?>
                        <div style="font-size: 11px; color: #999; text-align: center; margin-bottom: 8px;">
                            <?php echo G5_URL ?>/data/logos/pc
                        </div>
                    <?php else: ?>
                        <div style="width: 160px; height: 100px; border: 2px dashed #ccc; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #999; margin-bottom: 8px;">
                            <i class="fa fa-image" style="font-size: 32px;"></i>
                        </div>
                        <div style="font-size: 12px; color: #999; text-align: center;">로고 없음</div>
                    <?php endif; ?>
                </div>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="<?php echo G5_ADMIN_URL; ?>/admin.php?dir=rb.config"
                       target="_blank"
                       style="padding: 12px 24px; background: #aa20ff; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; white-space: nowrap; text-decoration: none; text-align: center;">
                        <i class="fa fa-cog"></i> 로고 설정 관리
                    </a>
                    <div style="font-size: 11px; color: #666; line-height: 1.4;">
                        <i class="fa fa-info-circle"></i> G5 관리자 > 테마 > 사이트 환경설정에서 로고를 변경할 수 있습니다.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 메인 배너 관리 -->
<div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 30px;">
    <div class="section-header">
        <h2 style="font-size: 20px; color: #333;">
            <i class="fa fa-image"></i> 현재 메인 배너
        </h2>
        <button type="button" class="btn btn-primary" onclick="addContent('banner')">
            <i class="fa fa-plus"></i> 배너 추가
        </button>
    </div>

            <div class="content-grid" id="bannerGrid">
                <?php foreach ($banners as $banner): ?>
                <?php $banner_image = addon_app_admin_get_media($banner['content_key']); ?>
                <div class="content-card" data-content-key="<?php echo $banner['content_key']; ?>">
                    <div class="content-card-image">
                        <?php if ($banner_image): ?>
                            <img src="<?php echo ADDON_APP_ADMIN_DATA_URL . '/uploads/banners/' . $banner_image['file_name']; ?>" alt="<?php echo htmlspecialchars($banner['title']); ?>">
                            <div class="content-card-image-overlay">
                                <button type="button" class="btn-icon" onclick="deleteBanner('<?php echo $banner['content_key']; ?>')" title="이미지 삭제">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="content-card-no-image">
                                <i class="fa fa-image" style="font-size: 48px; color: #ddd;"></i>
                                <p>이미지 없음</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="content-card-body">
                        <h3><?php echo htmlspecialchars($banner['title']); ?></h3>
                        <?php if ($banner['subtitle']): ?>
                            <p class="subtitle"><?php echo htmlspecialchars($banner['subtitle']); ?></p>
                        <?php endif; ?>
                        <?php if ($banner['description']): ?>
                            <p class="description"><?php echo nl2br(htmlspecialchars($banner['description'])); ?></p>
                        <?php endif; ?>
                        <?php if ($banner['button_text']): ?>
                            <p><strong>버튼:</strong> <?php echo htmlspecialchars($banner['button_text']); ?></p>
                        <?php endif; ?>
                        <p style="margin-top: 10px;">
                            <span class="badge <?php echo $banner['is_active'] ? 'badge-success' : 'badge-secondary'; ?>">
                                <?php echo $banner['is_active'] ? '활성' : '비활성'; ?>
                            </span>
                            <span class="badge badge-info">순서: <?php echo $banner['sort_order']; ?></span>
                        </p>
                    </div>
                    <div class="content-card-actions">
                        <button type="button" class="addon-btn addon-btn-sm addon-btn-secondary" onclick="uploadBannerImage('<?php echo $banner['content_key']; ?>')">
                            <i class="fa fa-upload"></i> 이미지
                        </button>
                        <button type="button" class="addon-btn addon-btn-sm addon-btn-primary" onclick="editContent('<?php echo $banner['content_key']; ?>')">
                            <i class="fa fa-edit"></i> 수정
                        </button>
                        <a href="?action=delete&content_key=<?php echo $banner['content_key']; ?>&token=<?php echo get_admin_token(); ?>" class="addon-btn addon-btn-sm addon-btn-danger" onclick="return confirm('정말 삭제하시겠습니까?');">
                            <i class="fa fa-trash"></i> 삭제
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>

<!-- 메인 섹션 관리 -->
<div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 30px;">
    <div class="section-header">
        <h2 style="font-size: 20px; color: #333;">
            <i class="fa fa-th-large"></i> 메인 섹션
        </h2>
        <button type="button" class="btn btn-primary" onclick="addContent('section')">
            <i class="fa fa-plus"></i> 섹션 추가
        </button>
    </div>

            <div class="content-grid" id="sectionGrid">
                <?php foreach ($sections as $section): ?>
                <div class="content-card" data-content-key="<?php echo $section['content_key']; ?>">
                    <div class="content-card-body">
                        <h3><?php echo htmlspecialchars($section['title']); ?></h3>
                        <?php if ($section['subtitle']): ?>
                            <p class="subtitle"><?php echo htmlspecialchars($section['subtitle']); ?></p>
                        <?php endif; ?>
                        <?php if ($section['description']): ?>
                            <p class="description"><?php echo nl2br(htmlspecialchars($section['description'])); ?></p>
                        <?php endif; ?>
                        <?php if ($section['button_text']): ?>
                            <p><strong>버튼:</strong> <?php echo htmlspecialchars($section['button_text']); ?> → <?php echo htmlspecialchars($section['button_link']); ?></p>
                        <?php endif; ?>
                        <p style="margin-top: 10px;">
                            <span class="badge <?php echo $section['is_active'] ? 'badge-success' : 'badge-secondary'; ?>">
                                <?php echo $section['is_active'] ? '활성' : '비활성'; ?>
                            </span>
                            <span class="badge badge-info">순서: <?php echo $section['sort_order']; ?></span>
                        </p>
                    </div>
                    <div class="content-card-actions">
                        <button type="button" class="addon-btn addon-btn-sm addon-btn-primary" onclick="editContent('<?php echo $section['content_key']; ?>')">
                            <i class="fa fa-edit"></i> 수정
                        </button>
                        <a href="?action=delete&content_key=<?php echo $section['content_key']; ?>&token=<?php echo get_admin_token(); ?>" class="addon-btn addon-btn-sm addon-btn-danger" onclick="return confirm('정말 삭제하시겠습니까?');">
                            <i class="fa fa-trash"></i> 삭제
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- 콘텐츠 수정/추가 모달 -->
<div id="contentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">콘텐츠 편집</h2>
            <span class="modal-close" onclick="closeContentModal()">&times;</span>
        </div>
        <form method="post" action="" id="contentForm">
            <?php echo get_admin_token_field(); ?>
            <input type="hidden" name="action" value="save_content">
            <input type="hidden" name="content_key" id="content_key">
            <input type="hidden" name="content_type" id="content_type">

            <div class="addon-form-group">
                <label class="addon-form-label">제목 *</label>
                <input type="text" name="title" id="content_title" class="addon-form-input" required placeholder="배너 제목">
            </div>

            <div class="addon-form-group">
                <label class="addon-form-label">부제목</label>
                <input type="text" name="subtitle" id="content_subtitle" class="addon-form-input" placeholder="부제목 (선택사항)">
            </div>

            <div class="addon-form-group">
                <label class="addon-form-label">설명</label>
                <textarea name="description" id="content_description" class="addon-form-input" rows="4" placeholder="상세 설명 (선택사항)"></textarea>
            </div>

            <div class="addon-form-row">
                <div class="addon-form-group">
                    <label class="addon-form-label">버튼 텍스트</label>
                    <input type="text" name="button_text" id="content_button_text" class="addon-form-input" placeholder="자세히 보기">
                </div>

                <div class="addon-form-group">
                    <label class="addon-form-label">버튼 링크</label>
                    <input type="url" name="button_link" id="content_button_link" class="addon-form-input" placeholder="https://example.com">
                </div>
            </div>

            <div class="addon-form-row">
                <div class="addon-form-group">
                    <label class="addon-form-label">정렬 순서</label>
                    <input type="number" name="sort_order" id="content_sort_order" class="addon-form-input" value="0" min="0">
                </div>

                <div class="addon-form-group">
                    <label class="addon-form-label">
                        <input type="checkbox" name="is_active" id="content_is_active" value="1" checked>
                        활성화
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="addon-btn addon-btn-secondary" onclick="closeContentModal()">취소</button>
                <button type="submit" class="addon-btn addon-btn-primary">
                    <i class="fa fa-save"></i> 저장
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 배너 이미지 업로드 모달 -->
<div id="bannerUploadModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>배너 이미지 업로드</h2>
            <span class="modal-close" onclick="closeBannerUploadModal()">&times;</span>
        </div>
        <form method="post" action="" enctype="multipart/form-data" id="bannerUploadForm">
            <?php echo get_admin_token_field(); ?>
            <input type="hidden" name="action" value="upload_banner">
            <input type="hidden" name="content_key" id="banner_content_key">

            <div class="addon-form-group">
                <label class="addon-form-label">배너 이미지</label>
                <input type="file" name="banner_image" accept="image/*" required class="addon-form-input">
                <small class="addon-form-help">권장: JPG 또는 PNG, 1920x600px, 최대 5MB</small>
            </div>

            <div class="modal-footer">
                <button type="button" class="addon-btn addon-btn-secondary" onclick="closeBannerUploadModal()">취소</button>
                <button type="submit" class="addon-btn addon-btn-primary">
                    <i class="fa fa-upload"></i> 업로드
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}
.btn-primary {
    background: #aa20ff;
    color: white;
}
.btn-primary:hover {
    background: #8919d9;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(170, 32, 255, 0.3);
}

.content-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}

.content-card {
    background: white;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
}

.content-card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-2px);
}

.content-card-image {
    position: relative;
    width: 100%;
    height: 200px;
    overflow: hidden;
    background: #f5f5f5;
}

.content-card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.content-card-no-image {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: #999;
}

.content-card-image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
}

.content-card-image:hover .content-card-image-overlay {
    opacity: 1;
}

.btn-icon {
    background: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.btn-icon:hover {
    background: var(--danger-color);
    color: white;
}

.content-card-body {
    padding: 20px;
}

.content-card-body h3 {
    margin: 0 0 10px 0;
    font-size: 18px;
    color: #333;
}

.content-card-body .subtitle {
    font-size: 14px;
    color: #666;
    margin: 8px 0;
}

.content-card-body .description {
    font-size: 13px;
    color: #777;
    line-height: 1.6;
    margin: 10px 0;
}

.content-card-actions {
    display: flex;
    gap: 8px;
    padding: 15px 20px;
    border-top: 1px solid #eee;
    background: #f9f9f9;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    overflow-y: auto;
}

.modal-content {
    background-color: white;
    margin: 3% auto;
    padding: 0;
    border-radius: var(--border-radius);
    width: 90%;
    max-width: 700px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 30px;
    border-bottom: 1px solid #eee;
}

.modal-header h2 {
    margin: 0;
    font-size: 20px;
}

.modal-close {
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
    transition: color 0.3s;
}

.modal-close:hover {
    color: #000;
}

.modal-content form {
    padding: 30px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    margin-right: 8px;
}

.badge-success {
    background-color: #28a745;
    color: white;
}

.badge-secondary {
    background-color: #6c757d;
    color: white;
}

.badge-info {
    background-color: #17a2b8;
    color: white;
}

@media (max-width: 768px) {
    .content-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// 콘텐츠 데이터
const contentData = <?php echo json_encode($main_contents); ?>;

// 콘텐츠 수정
function editContent(contentKey) {
    const content = contentData.find(c => c.content_key === contentKey);

    if (content) {
        document.getElementById('modalTitle').textContent = content.content_type === 'banner' ? '배너 수정' : '섹션 수정';
        document.getElementById('content_key').value = content.content_key;
        document.getElementById('content_type').value = content.content_type;
        document.getElementById('content_title').value = content.title || '';
        document.getElementById('content_subtitle').value = content.subtitle || '';
        document.getElementById('content_description').value = content.description || '';
        document.getElementById('content_button_text').value = content.button_text || '';
        document.getElementById('content_button_link').value = content.button_link || '';
        document.getElementById('content_sort_order').value = content.sort_order || 0;
        document.getElementById('content_is_active').checked = content.is_active == 1;
        document.getElementById('contentModal').style.display = 'block';
    }
}

// 새 콘텐츠 추가
function addContent(type) {
    const newKey = type + '_' + Date.now();

    document.getElementById('modalTitle').textContent = type === 'banner' ? '새 배너 추가' : '새 섹션 추가';
    document.getElementById('content_key').value = newKey;
    document.getElementById('content_type').value = type;
    document.getElementById('content_title').value = '';
    document.getElementById('content_subtitle').value = '';
    document.getElementById('content_description').value = '';
    document.getElementById('content_button_text').value = '';
    document.getElementById('content_button_link').value = '';
    document.getElementById('content_sort_order').value = '0';
    document.getElementById('content_is_active').checked = true;
    document.getElementById('contentModal').style.display = 'block';
}

// 콘텐츠 모달 닫기
function closeContentModal() {
    document.getElementById('contentModal').style.display = 'none';
}

// 배너 이미지 업로드
function uploadBannerImage(contentKey) {
    document.getElementById('banner_content_key').value = contentKey;
    document.getElementById('bannerUploadModal').style.display = 'block';
}

// 배너 업로드 모달 닫기
function closeBannerUploadModal() {
    document.getElementById('bannerUploadModal').style.display = 'none';
}

// 배너 이미지 삭제
function deleteBanner(contentKey) {
    if (confirm('배너 이미지를 삭제하시겠습니까?')) {
        location.href = '?action=delete_banner&media_key=' + contentKey + '&token=<?php echo get_admin_token(); ?>';
    }
}

// 모달 외부 클릭 시 닫기
window.onclick = function(event) {
    const contentModal = document.getElementById('contentModal');
    const bannerModal = document.getElementById('bannerUploadModal');

    if (event.target === contentModal) {
        closeContentModal();
    }
    if (event.target === bannerModal) {
        closeBannerUploadModal();
    }
}
</script>

<?php
include_once('./layout_footer.php');
?>
