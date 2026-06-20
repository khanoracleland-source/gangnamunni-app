<?php
/**
 * 후기 에디터 페이지
 * Quill.js 리치 텍스트 에디터 + 이미지 업로드 + 별점
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// 파라미터
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'create';
$hospital_id = isset($_GET['hospital_id']) ? intval($_GET['hospital_id']) : 0;
$review_id = isset($_GET['review_id']) ? intval($_GET['review_id']) : 0;
$user_id = isset($_GET['user_id']) ? sanitizeInput($_GET['user_id']) : '';

// 병원 정보 조회
$hospital = null;
if ($hospital_id > 0 && isset($connect) && $connect) {
    $stmt = mysqli_prepare($connect, "SELECT id, name FROM gangnamunni_hospitals WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $hospital = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// 수정 모드: 기존 후기 데이터 로드
$review = null;
$review_images = [];
if ($mode === 'edit' && $review_id > 0 && isset($connect) && $connect) {
    $stmt = mysqli_prepare($connect, "SELECT * FROM hospital_reviews WHERE id = ? AND is_active = 1");
    mysqli_stmt_bind_param($stmt, 'i', $review_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $review = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($review) {
        $stmt = mysqli_prepare($connect, "SELECT * FROM review_images WHERE review_id = ? ORDER BY image_order ASC");
        mysqli_stmt_bind_param($stmt, 'i', $review_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($img = mysqli_fetch_assoc($result)) {
            $review_images[] = $img;
        }
        mysqli_stmt_close($stmt);
    }
}

$csrf_token = generateCsrfToken();
$base_url = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $mode === 'edit' ? '후기 수정' : '후기 작성'; ?> | 서울미녀</title>
    <?php if (function_exists('generateFaviconLinks')) echo generateFaviconLinks(); ?>
    <!-- Quill.js -->
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
    <!-- DOMPurify -->
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js"></script>
    <!-- Editor CSS -->
    <link rel="stylesheet" href="assets/editor.css">
<link rel="stylesheet" href="../css/ai_writing_helper.css">
</head>
<body>
    <div class="editor-container">
        <!-- 헤더 -->
        <header class="editor-header">
            <button type="button" class="header-btn back-btn" onclick="handleBack()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            </button>
            <h1 class="header-title"><?php echo $mode === 'edit' ? '후기 수정' : '후기 작성'; ?></h1>
            <button type="button" class="header-btn submit-btn" id="submitBtn" onclick="submitReview()">
                <?php echo $mode === 'edit' ? '수정' : '등록'; ?>
            </button>
        </header>

        <!-- 병원 정보 -->
        <?php if ($hospital): ?>
        <div class="hospital-badge">
            <span class="hospital-icon">🏥</span>
            <span class="hospital-name"><?php echo htmlspecialchars($hospital['name']); ?></span>
        </div>
        <?php endif; ?>

        <!-- 별점 -->
        <div class="rating-section">
            <p class="rating-label">시술 만족도를 선택해주세요</p>
            <div class="star-rating" id="starRating">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <button type="button" class="star-btn" data-rating="<?php echo $i; ?>" onclick="setRating(<?php echo $i; ?>)">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="1.5">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                    </svg>
                </button>
                <?php endfor; ?>
            </div>
            <span class="rating-text" id="ratingText">별점을 선택하세요</span>
        </div>

        <!-- 시술 태그 -->
        <div class="treatment-section">
            <p class="section-label">어떤 시술을 받으셨나요? (선택)</p>
            <div class="treatment-tags" id="treatmentTags">
                <?php
                $treatments = ['눈성형', '코성형', '지방흡입', '가슴성형', '리프팅', '필러', '보톡스', '피부', '치아', '모발이식'];
                foreach ($treatments as $t):
                ?>
                <button type="button" class="treatment-tag" onclick="toggleTreatment(this)" data-treatment="<?php echo $t; ?>"><?php echo $t; ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 제목 -->
        <div class="title-section">
            <input type="text" id="reviewTitle" class="title-input" placeholder="제목을 입력하세요" maxlength="200"
                   value="<?php echo htmlspecialchars($review['title'] ?? ''); ?>">
        </div>

        <!-- Quill 에디터 -->
        <div class="editor-section">
            <div id="quillEditor"></div>
        </div>

        <!-- 이미지 업로드 -->
        <div class="image-section">
            <p class="section-label">사진 첨부 <span class="image-count">(<span id="imageCount">0</span>/10)</span></p>
            <div class="image-grid" id="imageGrid">
                <label class="image-add-btn" id="imageAddBtn">
                    <input type="file" id="imageInput" accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden>
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                    <span>사진 추가</span>
                </label>
            </div>
        </div>

        <!-- 자동저장 알림 -->
        <div class="autosave-notice" id="autosaveNotice">
            <span id="autosaveText">자동저장됨</span>
        </div>
    </div>

    <!-- 이미지 미리보기 모달 -->
    <div class="preview-modal" id="previewModal">
        <div class="preview-backdrop" onclick="closePreview()"></div>
        <div class="preview-content">
            <button class="preview-close" onclick="closePreview()">×</button>
            <img id="previewImage" src="" alt="미리보기">
        </div>
    </div>

    <!-- 로딩 오버레이 -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <p class="loading-text">업로드 중...</p>
    </div>

    <script>
        // 설정
        const CONFIG = {
            mode: '<?php echo $mode; ?>',
            hospitalId: <?php echo $hospital_id; ?>,
            reviewId: <?php echo $review_id; ?>,
            userId: '<?php echo addslashes($user_id); ?>',
            csrfToken: '<?php echo $csrf_token; ?>',
            baseUrl: '<?php echo $base_url; ?>',
            maxImages: 10,
            autosaveInterval: 30000
        };

        // 기존 후기 데이터 (수정 모드)
        const existingReview = <?php echo json_encode($review, JSON_UNESCAPED_UNICODE) ?: 'null'; ?>;
        const existingImages = <?php echo json_encode($review_images, JSON_UNESCAPED_UNICODE) ?: '[]'; ?>;
    </script>
    <script src="assets/editor.js"></script>

<!-- AI 작성 도우미 -->
<div id="aiSuggestions"></div>
<script src="../js/ai_writing_helper.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    AiHelper.init({
        contentSelector: ".ql-editor",
        titleSelector: "#reviewTitle",
        panelSelector: "#aiSuggestions",
        context: "review",
        apiUrl: "../api/ai_suggest.php",
    });
});
</script>
</body>
</html>
