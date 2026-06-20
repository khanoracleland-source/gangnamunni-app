<?php
/**
 * 앱 이미지 편집기
 * @package AddonAppAdmin
 * @version 1.0.0
 */

include_once('./_common.php');

// 권한 체크 제거됨
// if (!addon_app_admin_check_permission()) {
//     addon_app_admin_permission_denied();
// }

$app_key = isset($_GET['app_key']) ? $_GET['app_key'] : '';

if (!$app_key) {
    alert('앱을 선택해주세요.', './app-package.php');
}

// 앱 소스 정보 가져오기
$sql = " SELECT * FROM {$addon_app_admin_table_app_sources} WHERE app_key = '{$app_key}' ";
$app_source = sql_fetch($sql);

if (!$app_source) {
    alert('앱 소스를 찾을 수 없습니다.', './app-package.php');
}

// 앱 이미지 목록 가져오기
$sql = " SELECT * FROM {$addon_app_admin_table_app_images} WHERE app_key = '{$app_key}' ORDER BY image_type ASC, image_path ASC ";
$result = sql_query($sql);
$app_images = array();
while ($row = sql_fetch_array($result)) {
    $app_images[] = $row;
}

// 이미지 타입별로 분류
$images_by_type = array();
foreach ($app_images as $img) {
    $type = $img['image_type'];
    if (!isset($images_by_type[$type])) {
        $images_by_type[$type] = array();
    }
    $images_by_type[$type][] = $img;
}

// 이미지 업데이트
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_image') {
    check_admin_token();

    $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;

    if ($image_id && isset($_FILES['new_image'])) {
        // 기존 이미지 정보 가져오기
        $sql = " SELECT * FROM {$addon_app_admin_table_app_images} WHERE id = {$image_id} ";
        $image_info = sql_fetch($sql);

        if ($image_info) {
            $upload_file = $_FILES['new_image'];

            // 파일 유효성 검사
            $allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'webp');
            $file_ext = strtolower(pathinfo($upload_file['name'], PATHINFO_EXTENSION));

            if (!in_array($file_ext, $allowed_ext)) {
                alert('허용되지 않는 파일 형식입니다.');
            }

            if ($upload_file['size'] > ADDON_APP_ADMIN_MAX_UPLOAD_SIZE) {
                alert('파일 크기가 너무 큽니다.');
            }

            // 원본 이미지 크기 확인
            list($orig_width, $orig_height) = getimagesize($image_info['full_path']);

            // 새 이미지 크기 확인
            list($new_width, $new_height) = getimagesize($upload_file['tmp_name']);

            // 크기 검증 (원본과 동일한 크기만 허용)
            if ($new_width != $orig_width || $new_height != $orig_height) {
                alert("이미지 크기가 일치하지 않습니다. 원본: {$orig_width}x{$orig_height}px, 업로드: {$new_width}x{$new_height}px");
            }

            // 파일 복사 (원본 파일 덮어쓰기)
            if (move_uploaded_file($upload_file['tmp_name'], $image_info['full_path'])) {
                // 데이터베이스 업데이트
                $sql = " UPDATE {$addon_app_admin_table_app_images}
                         SET updated_at = NOW()
                         WHERE id = {$image_id} ";
                sql_query($sql);

                addon_app_admin_log('image_update', $app_key, '이미지 업데이트: ' . $image_info['image_path']);
                alert('이미지가 성공적으로 업데이트되었습니다.', './app-image-editor.php?app_key=' . $app_key);
            } else {
                alert('파일 업로드 중 오류가 발생했습니다.');
            }
        }
    }
}

// 레이아웃 헤더
$g5['title'] = '앱 이미지 편집 - ' . $app_source['app_name'];
include_once('./layout_header.php');

?>

<link rel="stylesheet" href="<?php echo ADDON_APP_ADMIN_URL; ?>/assets/css/admin.css">

<style>
.image-editor-wrap {
    background: #f5f5f5;
    min-height: 100vh;
}

.editor-header {
    background: white;
    border-bottom: 1px solid #e0e0e0;
    padding: 15px 0;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.editor-header-inner {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.editor-title {
    margin: 0;
    font-size: 20px;
    color: #333;
}

.editor-title .platform-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    margin-left: 10px;
    font-weight: 500;
}

.editor-title .platform-badge.android {
    background: #3DDC84;
    color: white;
}

.editor-title .platform-badge.ios {
    background: #007AFF;
    color: white;
}

.editor-content {
    padding: 30px 0;
}

.image-type-section {
    background: white;
    border-radius: var(--border-radius);
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: var(--shadow-sm);
}

.image-type-title {
    margin: 0 0 20px 0;
    font-size: 18px;
    color: #333;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--primary-color);
}

.image-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

.image-item {
    background: #f9f9f9;
    border-radius: 8px;
    padding: 15px;
    border: 2px solid #e0e0e0;
    transition: all 0.3s ease;
}

.image-item:hover {
    border-color: var(--primary-color);
    box-shadow: 0 4px 12px rgba(170, 32, 255, 0.15);
}

.image-preview {
    position: relative;
    width: 100%;
    background: #fff;
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.image-preview img {
    width: 100%;
    height: auto;
    display: block;
}

.image-preview-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s;
}

.image-preview:hover .image-preview-overlay {
    opacity: 1;
}

.image-info {
    padding: 10px 0;
}

.image-info h4 {
    margin: 0 0 8px 0;
    font-size: 14px;
    color: #333;
    font-weight: 600;
}

.image-info p {
    margin: 4px 0;
    font-size: 12px;
    color: #666;
}

.image-info .size-badge {
    display: inline-block;
    background: var(--primary-color);
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 500;
}

.image-actions {
    display: flex;
    gap: 8px;
    margin-top: 10px;
}

.btn-replace {
    flex: 1;
    background: var(--primary-color);
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn-replace:hover {
    background: #9010dd;
    transform: translateY(-1px);
}

.btn-download {
    background: #28a745;
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-download:hover {
    background: #218838;
    transform: translateY(-1px);
}

.stats-bar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px 25px;
    border-radius: var(--border-radius);
    margin-bottom: 25px;
    display: flex;
    justify-content: space-around;
    align-items: center;
}

.stat-item {
    text-align: center;
}

.stat-item .stat-value {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-item .stat-label {
    font-size: 13px;
    opacity: 0.9;
}

.upload-modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.7);
}

.upload-modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 30px;
    border-radius: var(--border-radius);
    width: 90%;
    max-width: 500px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
}

.upload-modal-header {
    margin-bottom: 25px;
}

.upload-modal-header h3 {
    margin: 0 0 10px 0;
    font-size: 20px;
    color: #333;
}

.upload-modal-header p {
    margin: 0;
    font-size: 14px;
    color: #666;
}

.upload-modal-header .required-size {
    background: #fff3cd;
    border: 1px solid #ffc107;
    color: #856404;
    padding: 10px;
    border-radius: 6px;
    margin-top: 10px;
    font-weight: 500;
}

.upload-zone {
    border: 2px dashed #ddd;
    border-radius: 8px;
    padding: 30px;
    text-align: center;
    background: #f9f9f9;
    transition: all 0.3s;
    cursor: pointer;
}

.upload-zone:hover {
    border-color: var(--primary-color);
    background: #f5f0ff;
}

.upload-zone.dragover {
    border-color: var(--primary-color);
    background: #f5f0ff;
}

.upload-zone i {
    font-size: 48px;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.preview-container {
    margin-top: 20px;
    display: none;
}

.preview-container img {
    max-width: 100%;
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.preview-info {
    margin-top: 15px;
    padding: 15px;
    background: #f0f0f0;
    border-radius: 6px;
}

.preview-info p {
    margin: 5px 0;
    font-size: 13px;
    color: #666;
}

.modal-actions {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.modal-actions button {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-cancel {
    background: #6c757d;
    color: white;
}

.btn-cancel:hover {
    background: #5a6268;
}

.btn-upload {
    background: var(--primary-color);
    color: white;
}

.btn-upload:hover {
    background: #9010dd;
}

.btn-upload:disabled {
    background: #ccc;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .image-grid {
        grid-template-columns: 1fr;
    }

    .stats-bar {
        flex-direction: column;
        gap: 15px;
    }
}
</style>

<div class="image-editor-wrap">

    <div class="editor-header">
        <div class="addon-container">
            <div class="editor-header-inner">
                <h1 class="editor-title">
                    <i class="fa fa-mobile"></i> <?php echo htmlspecialchars($app_source['app_name']); ?>
                    <span class="platform-badge <?php echo strtolower($app_source['platform']); ?>">
                        <?php echo $app_source['platform']; ?>
                    </span>
                </h1>
                <div>
                    <a href="./app-package.php" class="addon-btn addon-btn-sm addon-btn-secondary">
                        <i class="fa fa-arrow-left"></i> 목록으로
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="addon-container">
        <div class="editor-content">

            <!-- 통계 -->
            <div class="stats-bar">
                <div class="stat-item">
                    <div class="stat-value"><?php echo count($app_images); ?></div>
                    <div class="stat-label">전체 이미지</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo count($images_by_type); ?></div>
                    <div class="stat-label">이미지 타입</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format(filesize($app_source['file_path']) / 1024 / 1024, 1); ?> MB</div>
                    <div class="stat-label">앱 크기</div>
                </div>
            </div>

            <?php if (empty($images_by_type)): ?>
                <div class="addon-section">
                    <div style="text-align: center; padding: 60px 20px; color: #999;">
                        <i class="fa fa-image" style="font-size: 64px; margin-bottom: 20px;"></i>
                        <h3>이미지를 찾을 수 없습니다</h3>
                        <p>업로드된 앱 소스에서 이미지를 스캔하지 못했습니다.</p>
                    </div>
                </div>
            <?php else: ?>

                <?php foreach ($images_by_type as $type => $images): ?>
                <div class="image-type-section">
                    <h2 class="image-type-title">
                        <i class="fa fa-folder-open"></i> <?php echo htmlspecialchars(ucfirst($type)); ?>
                        <span style="font-size: 14px; color: #999; font-weight: normal; margin-left: 10px;">(<?php echo count($images); ?>개)</span>
                    </h2>

                    <div class="image-grid">
                        <?php foreach ($images as $img): ?>
                        <div class="image-item">
                            <div class="image-preview">
                                <img src="<?php echo htmlspecialchars($img['web_path']); ?>?v=<?php echo time(); ?>" alt="<?php echo htmlspecialchars(basename($img['image_path'])); ?>" loading="lazy">
                                <div class="image-preview-overlay">
                                    <button type="button" class="btn-download" onclick="downloadImage('<?php echo htmlspecialchars($img['web_path']); ?>', '<?php echo htmlspecialchars(basename($img['image_path'])); ?>')">
                                        <i class="fa fa-download"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="image-info">
                                <h4><?php echo htmlspecialchars(basename($img['image_path'])); ?></h4>
                                <p>
                                    <span class="size-badge"><?php echo $img['width']; ?> x <?php echo $img['height']; ?>px</span>
                                </p>
                                <p style="color: #999; font-size: 11px;"><?php echo htmlspecialchars($img['image_path']); ?></p>
                            </div>
                            <div class="image-actions">
                                <button type="button" class="btn-replace" onclick="openUploadModal(<?php echo $img['id']; ?>, '<?php echo htmlspecialchars(basename($img['image_path'])); ?>', <?php echo $img['width']; ?>, <?php echo $img['height']; ?>)">
                                    <i class="fa fa-upload"></i> 교체
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

            <?php endif; ?>

        </div>
    </div>

</div>

<!-- 이미지 업로드 모달 -->
<div id="uploadModal" class="upload-modal">
    <div class="upload-modal-content">
        <div class="upload-modal-header">
            <h3 id="modalImageName">이미지 교체</h3>
            <p id="modalImagePath"></p>
            <div class="required-size">
                <i class="fa fa-exclamation-triangle"></i> 필수 크기: <strong id="modalRequiredSize"></strong>
            </div>
        </div>

        <form method="post" enctype="multipart/form-data" id="uploadForm">
            <?php echo get_admin_token_field(); ?>
            <input type="hidden" name="action" value="update_image">
            <input type="hidden" name="image_id" id="currentImageId">

            <div class="upload-zone" id="uploadZone">
                <i class="fa fa-cloud-upload"></i>
                <p style="margin: 10px 0; font-size: 16px; color: #333;">이미지를 드래그하거나 클릭하세요</p>
                <p style="margin: 0; font-size: 13px; color: #999;">JPG, PNG, GIF, WebP (최대 10MB)</p>
                <input type="file" name="new_image" id="imageInput" accept="image/*" style="display: none;">
            </div>

            <div class="preview-container" id="previewContainer">
                <img id="previewImage" src="" alt="Preview">
                <div class="preview-info" id="previewInfo"></div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeUploadModal()">취소</button>
                <button type="submit" class="btn-upload" id="uploadButton" disabled>
                    <i class="fa fa-check"></i> 업로드
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let requiredWidth = 0;
let requiredHeight = 0;

// 업로드 모달 열기
function openUploadModal(imageId, imageName, width, height) {
    requiredWidth = width;
    requiredHeight = height;

    document.getElementById('currentImageId').value = imageId;
    document.getElementById('modalImageName').textContent = imageName;
    document.getElementById('modalRequiredSize').textContent = width + ' x ' + height + 'px';
    document.getElementById('uploadModal').style.display = 'block';
    document.getElementById('previewContainer').style.display = 'none';
    document.getElementById('uploadButton').disabled = true;
}

// 업로드 모달 닫기
function closeUploadModal() {
    document.getElementById('uploadModal').style.display = 'none';
    document.getElementById('uploadForm').reset();
    document.getElementById('previewContainer').style.display = 'none';
}

// 업로드 존 클릭
document.getElementById('uploadZone').addEventListener('click', function() {
    document.getElementById('imageInput').click();
});

// 파일 선택
document.getElementById('imageInput').addEventListener('change', function(e) {
    handleFile(e.target.files[0]);
});

// 드래그 앤 드롭
const uploadZone = document.getElementById('uploadZone');

uploadZone.addEventListener('dragover', function(e) {
    e.preventDefault();
    uploadZone.classList.add('dragover');
});

uploadZone.addEventListener('dragleave', function(e) {
    e.preventDefault();
    uploadZone.classList.remove('dragover');
});

uploadZone.addEventListener('drop', function(e) {
    e.preventDefault();
    uploadZone.classList.remove('dragover');

    const files = e.dataTransfer.files;
    if (files.length > 0) {
        handleFile(files[0]);
    }
});

// 파일 처리
function handleFile(file) {
    if (!file) return;

    // 파일 타입 검증
    if (!file.type.match('image.*')) {
        alert('이미지 파일만 업로드 가능합니다.');
        return;
    }

    // 파일 크기 검증
    if (file.size > 10 * 1024 * 1024) {
        alert('파일 크기는 10MB를 초과할 수 없습니다.');
        return;
    }

    // 이미지 미리보기
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = new Image();
        img.onload = function() {
            // 크기 검증
            if (img.width !== requiredWidth || img.height !== requiredHeight) {
                alert(`이미지 크기가 일치하지 않습니다.\n필요: ${requiredWidth}x${requiredHeight}px\n업로드: ${img.width}x${img.height}px`);
                document.getElementById('uploadButton').disabled = true;
                return;
            }

            // 미리보기 표시
            document.getElementById('previewImage').src = e.target.result;
            document.getElementById('previewContainer').style.display = 'block';

            // 정보 표시
            const info = `
                <p><strong>파일명:</strong> ${file.name}</p>
                <p><strong>크기:</strong> ${img.width} x ${img.height}px</p>
                <p><strong>용량:</strong> ${(file.size / 1024).toFixed(2)} KB</p>
                <p style="color: #28a745;"><i class="fa fa-check-circle"></i> 크기가 일치합니다!</p>
            `;
            document.getElementById('previewInfo').innerHTML = info;

            // 업로드 버튼 활성화
            document.getElementById('uploadButton').disabled = false;
        };
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}

// 이미지 다운로드
function downloadImage(url, filename) {
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// 모달 외부 클릭 시 닫기
window.onclick = function(event) {
    const modal = document.getElementById('uploadModal');
    if (event.target === modal) {
        closeUploadModal();
    }
}
</script>

<?php
include_once('./layout_footer.php');
?>
