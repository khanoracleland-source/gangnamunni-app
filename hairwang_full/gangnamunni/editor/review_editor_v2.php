<?php
/**
 * 후기 에디터 v2 - 경쟁사 수준 업그레이드
 * 병원 연동 + 다축 평가 + 의사 선택 + 비용/날짜 + 비포/애프터
 */
require_once dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/functions.php';

// 파라미터
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'create';
$hospital_id = isset($_GET['hospital_id']) ? intval($_GET['hospital_id']) : 0;
$review_id = isset($_GET['review_id']) ? intval($_GET['review_id']) : 0;
$user_id = isset($_SESSION['ss_mb_id']) ? $_SESSION['ss_mb_id'] : '';

// 병원 정보 조회 (확장)
$hospital = null;
$hospital_doctors = [];
$hospital_departments = [];
if ($hospital_id > 0 && isset($connect) && $connect) {
    $stmt = mysqli_prepare($connect, "SELECT h.*,
        (SELECT COUNT(*) FROM hospital_reviews WHERE hospital_id = h.id AND is_active = 1) as review_count,
        (SELECT ROUND(AVG(rating),1) FROM hospital_reviews WHERE hospital_id = h.id AND is_active = 1) as avg_rating
        FROM gangnamunni_hospitals h WHERE h.id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $hospital = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    // 의사 목록 (HMS users 테이블에서 해당 병원 소속 의사)
    if ($hospital && !empty($hospital['hms_hospital_id'])) {
        $hms_id = intval($hospital['hms_hospital_id']);
        $doc_sql = "SELECT u.id, u.first_name, u.last_name, u.email, u.image_url,
                    d.name as department_name, u.qualification, u.specialist
                    FROM users u
                    LEFT JOIN departments d ON u.department_id = d.id
                    WHERE u.department_id IS NOT NULL
                    AND u.tenant_id = ?
                    AND u.status = 1
                    ORDER BY u.first_name";
        $stmt2 = mysqli_prepare($connect, $doc_sql);
        mysqli_stmt_bind_param($stmt2, 'i', $hms_id);
        mysqli_stmt_execute($stmt2);
        $result2 = mysqli_stmt_get_result($stmt2);
        while ($doc = mysqli_fetch_assoc($result2)) {
            $hospital_doctors[] = $doc;
        }
        mysqli_stmt_close($stmt2);
    }
}

// 카테고리 로드 (시술 태그용)
$categories = [];
if (isset($connect) && $connect) {
    $cat_result = mysqli_query($connect, "SELECT id, name, icon, emoji FROM gangnamunni_categories WHERE is_active = 1 ORDER BY sort_order, name");
    if ($cat_result) {
        while ($cat = mysqli_fetch_assoc($cat_result)) {
            $categories[] = $cat;
        }
    }
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
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3.0.6/dist/purify.min.js"></script>
    <link rel="stylesheet" href="assets/editor_v2.css">
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

    <!-- 병원 정보 카드 -->
    <?php if ($hospital): ?>
    <div class="hospital-card">
        <div class="hospital-card-main">
            <?php if (!empty($hospital['image'])): ?>
            <img src="<?php echo htmlspecialchars($hospital['image']); ?>" alt="" class="hospital-thumb">
            <?php else: ?>
            <div class="hospital-thumb hospital-thumb-placeholder">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5"><path d="M3 21h18M9 8h1M9 12h1M9 16h1M14 8h1M14 12h1M14 16h1M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16"/></svg>
            </div>
            <?php endif; ?>
            <div class="hospital-info">
                <h2 class="hospital-name"><?php echo htmlspecialchars($hospital['name']); ?></h2>
                <?php if (!empty($hospital['address'])): ?>
                <p class="hospital-address"><?php echo htmlspecialchars($hospital['address']); ?></p>
                <?php endif; ?>
                <div class="hospital-meta">
                    <?php if ($hospital['avg_rating']): ?>
                    <span class="meta-badge rating-badge">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="#fbbf24" stroke="#fbbf24" stroke-width="1"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <?php echo $hospital['avg_rating']; ?>
                    </span>
                    <?php endif; ?>
                    <span class="meta-badge review-count-badge">후기 <?php echo intval($hospital['review_count']); ?>건</span>
                </div>
            </div>
        </div>
        <?php
        // 시설/서비스 태그
        $facilities = [];
        if (!empty($hospital['facilities'])) {
            $facilities = array_filter(array_map('trim', explode(',', $hospital['facilities'])));
        }
        if (!empty($hospital['parking'])) $facilities[] = '주차 가능';
        if (!empty($hospital['night_service'])) $facilities[] = '야간진료';
        if (!empty($hospital['weekend_service'])) $facilities[] = '주말진료';
        if (!empty($facilities)):
        ?>
        <div class="hospital-facilities">
            <?php foreach ($facilities as $f): ?>
            <span class="facility-tag"><?php echo htmlspecialchars($f); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php elseif ($hospital_id > 0): ?>
    <div class="hospital-badge">
        <span class="hospital-icon">&#x1F3E5;</span>
        <span class="hospital-badge-name">Hospital #<?php echo $hospital_id; ?></span>
    </div>
    <?php else: ?>
    <div class="hospital-badge hospital-badge-warn">
        <span class="hospital-icon">&#x26A0;</span>
        <span class="hospital-badge-name">병원 정보 없음 - 병원 페이지에서 후기를 작성해주세요</span>
    </div>
    <?php endif; ?>

    <!-- 담당 의사 선택 -->
    <?php if (!empty($hospital_doctors)): ?>
    <div class="doctor-section">
        <p class="section-label">담당 의사를 선택해주세요 <span class="optional-badge">선택</span></p>
        <div class="doctor-grid" id="doctorGrid">
            <button type="button" class="doctor-card selected" data-doctor-id="0" onclick="selectDoctor(this)">
                <div class="doctor-avatar doctor-avatar-none">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </div>
                <span class="doctor-name">선택 안함</span>
            </button>
            <?php foreach ($hospital_doctors as $doc):
                $doc_name = trim($doc['first_name'] . ' ' . $doc['last_name']);
                if (empty($doc_name)) $doc_name = $doc['email'];
            ?>
            <button type="button" class="doctor-card" data-doctor-id="<?php echo $doc['id']; ?>" data-doctor-name="<?php echo htmlspecialchars($doc_name); ?>" onclick="selectDoctor(this)">
                <?php if (!empty($doc['image_url'])): ?>
                <img src="<?php echo htmlspecialchars($doc['image_url']); ?>" alt="" class="doctor-avatar">
                <?php else: ?>
                <div class="doctor-avatar doctor-avatar-placeholder">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="1.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <?php endif; ?>
                <span class="doctor-name"><?php echo htmlspecialchars($doc_name); ?></span>
                <?php if (!empty($doc['department_name'])): ?>
                <span class="doctor-dept"><?php echo htmlspecialchars($doc['department_name']); ?></span>
                <?php endif; ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 시술 정보 -->
    <div class="procedure-section">
        <p class="section-label">시술 정보 <span class="optional-badge">선택</span></p>
        <div class="procedure-fields">
            <div class="field-group">
                <label class="field-label">시술 날짜</label>
                <input type="date" id="procedureDate" class="field-input" value="<?php echo htmlspecialchars($review['procedure_date'] ?? ''); ?>" max="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="field-group">
                <label class="field-label">시술 비용 (원)</label>
                <input type="text" id="procedureCost" class="field-input" placeholder="예: 500,000" inputmode="numeric" value="<?php echo $review['procedure_cost'] ?? ''; ?>">
            </div>
        </div>
    </div>

    <!-- 다축 만족도 평가 -->
    <div class="rating-section">
        <p class="section-label">만족도를 평가해주세요</p>
        <div class="rating-summary" id="ratingSummary">
            <span class="rating-avg-label">종합 평점</span>
            <span class="rating-avg-value" id="ratingAvg">0.0</span>
            <span class="rating-avg-stars" id="ratingAvgStars"></span>
        </div>
        <div class="multi-rating" id="multiRating">
            <?php
            $axes = [
                ['key' => 'result', 'label' => '시술 결과', 'icon' => '&#x1F48E;', 'desc' => '시술/수술 결과에 만족하시나요?'],
                ['key' => 'consultation', 'label' => '상담 품질', 'icon' => '&#x1F4AC;', 'desc' => '상담이 충분하고 친절했나요?'],
                ['key' => 'facility', 'label' => '시설/청결', 'icon' => '&#x1F3E5;', 'desc' => '병원 시설이 깔끔했나요?'],
                ['key' => 'wait', 'label' => '대기 시간', 'icon' => '&#x23F0;', 'desc' => '대기 시간이 적당했나요?'],
                ['key' => 'cost', 'label' => '가격 만족', 'icon' => '&#x1F4B0;', 'desc' => '가격 대비 만족하시나요?'],
                ['key' => 'recovery', 'label' => '통증/회복', 'icon' => '&#x1FA79;', 'desc' => '통증 및 회복이 예상 범위 내였나요?']
            ];
            foreach ($axes as $ax):
            ?>
            <div class="rating-axis" data-axis="<?php echo $ax['key']; ?>">
                <div class="axis-header">
                    <span class="axis-icon"><?php echo $ax['icon']; ?></span>
                    <span class="axis-label"><?php echo $ax['label']; ?></span>
                    <span class="axis-value" id="axisValue_<?php echo $ax['key']; ?>">-</span>
                </div>
                <div class="axis-stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button type="button" class="axis-star" data-axis="<?php echo $ax['key']; ?>" data-rating="<?php echo $i; ?>" onclick="setAxisRating('<?php echo $ax['key']; ?>', <?php echo $i; ?>)">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="1.5"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                    </button>
                    <?php endfor; ?>
                </div>
                <p class="axis-desc"><?php echo $ax['desc']; ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 시술 태그 -->
    <div class="treatment-section">
        <p class="section-label">어떤 시술을 받으셨나요? <span class="optional-badge">선택</span></p>
        <div class="treatment-tags" id="treatmentTags">
            <?php if (!empty($categories)):
                foreach ($categories as $cat):
                    $emoji = !empty($cat['emoji']) ? $cat['emoji'] : '';
            ?>
            <button type="button" class="treatment-tag" onclick="toggleTreatment(this)" data-treatment="<?php echo htmlspecialchars($cat['name']); ?>"><?php echo $emoji; ?> <?php echo htmlspecialchars($cat['name']); ?></button>
            <?php endforeach;
            else:
                $defaults = ['눈성형','코성형','지방흡입','가슴성형','리프팅','필러','보톡스','피부','치아','모발이식','안면윤곽','레이저'];
                foreach ($defaults as $t):
            ?>
            <button type="button" class="treatment-tag" onclick="toggleTreatment(this)" data-treatment="<?php echo $t; ?>"><?php echo $t; ?></button>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- 제목 -->
    <div class="title-section">
        <input type="text" id="reviewTitle" class="title-input" placeholder="후기 제목을 입력하세요" maxlength="200"
               value="<?php echo htmlspecialchars($review['title'] ?? ''); ?>">
    </div>

    <!-- Quill 에디터 -->
    <div class="editor-section">
        <div id="quillEditor"></div>
    </div>

    <!-- 비포/애프터 사진 -->
    <div class="photo-section">
        <p class="section-label">시술 전/후 사진 <span class="optional-badge">선택</span></p>
        <p class="section-desc">시술 전후 사진을 업로드하면 다른 분들에게 큰 도움이 됩니다.</p>

        <div class="ba-photo-grid">
            <!-- Before -->
            <div class="ba-zone" id="beforeZone">
                <h4 class="ba-label ba-before-label">시술 전 (Before)</h4>
                <div class="ba-images" id="beforeImages">
                    <label class="ba-add-btn" id="beforeAddBtn">
                        <input type="file" class="ba-file-input" data-category="before" accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                        <span>추가 <span class="ba-count">(0/5)</span></span>
                    </label>
                </div>
            </div>
            <!-- After -->
            <div class="ba-zone" id="afterZone">
                <h4 class="ba-label ba-after-label">시술 후 (After)</h4>
                <div class="ba-images" id="afterImages">
                    <label class="ba-add-btn" id="afterAddBtn">
                        <input type="file" class="ba-file-input" data-category="after" accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                        <span>추가 <span class="ba-count">(0/5)</span></span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- 일반 사진 -->
    <div class="image-section">
        <p class="section-label">추가 사진 <span class="image-count">(<span id="imageCount">0</span>/10)</span></p>
        <div class="image-grid" id="imageGrid">
            <label class="image-add-btn" id="imageAddBtn">
                <input type="file" id="imageInput" accept="image/jpeg,image/png,image/gif,image/webp" multiple hidden>
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                <span>사진 추가</span>
            </label>
        </div>
    </div>

    <!-- 개인정보 안내 -->
    <div class="privacy-notice">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
        <p>사진에 개인정보(얼굴, 이름 등)가 포함되지 않도록 주의해주세요.<br>부적절한 내용의 후기는 관리자 검토 후 비공개 처리될 수 있습니다.</p>
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
        <button class="preview-close" onclick="closePreview()">&times;</button>
        <img id="previewImage" src="" alt="미리보기">
    </div>
</div>

<!-- 로딩 오버레이 -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
    <p class="loading-text">업로드 중...</p>
</div>

<script>
const CONFIG = {
    mode: '<?php echo $mode; ?>',
    hospitalId: <?php echo $hospital_id; ?>,
    reviewId: <?php echo $review_id; ?>,
    userId: '<?php echo addslashes($user_id); ?>',
    csrfToken: '<?php echo $csrf_token; ?>',
    baseUrl: '<?php echo $base_url; ?>',
    maxImages: 10,
    maxBAImages: 5,
    autosaveInterval: 30000
};
const existingReview = <?php echo json_encode($review, JSON_UNESCAPED_UNICODE) ?: 'null'; ?>;
const existingImages = <?php echo json_encode($review_images, JSON_UNESCAPED_UNICODE) ?: '[]'; ?>;
</script>
<script src="assets/editor_v2.js"></script>
</body>
</html>
