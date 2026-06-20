<?php
/**
 * ========================================
 * 서울미녀 스타일 병원 상세 페이지
 * ========================================
 */

// 데이터베이스 연결
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';

$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);

if (!$connect) {
    die("MySQL 연결 실패: " . mysqli_connect_error());
}

mysqli_set_charset($connect, 'utf8mb4');

// 테마 설정 로드
loadThemeSettings($connect);

// 병원 ID 가져오기
$hospital_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($hospital_id <= 0) {
    header('Location: index.php');
    exit;
}

// 병원 정보 조회
$query = "SELECT * FROM gangnamunni_hospitals WHERE id = {$hospital_id}";
$result = mysqli_query($connect, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    die("병원 정보를 찾을 수 없습니다.");
}

$hospital = mysqli_fetch_assoc($result);

// 병원 이미지 갤러리 조회
$images_query = "SELECT * FROM hospital_images WHERE hospital_id = {$hospital_id} AND is_active = 1 ORDER BY sort_order ASC, id ASC";
$images_result = mysqli_query($connect, $images_query);
$hospital_images = [];
while ($img_row = mysqli_fetch_assoc($images_result)) {
    $hospital_images[] = $img_row;
}

// 이미지가 없으면 기본 이미지 추가
if (empty($hospital_images)) {
    if (!empty($hospital['main_image_url'])) {
        $hospital_images[] = ['image_url' => $hospital['main_image_url'], 'image_type' => 'main'];
    }
    if (!empty($hospital['profile_image_url']) && $hospital['profile_image_url'] !== $hospital['main_image_url']) {
        $hospital_images[] = ['image_url' => $hospital['profile_image_url'], 'image_type' => 'main'];
    }
}

// JSON 데이터 파싱
$categories = json_decode($hospital['categories'] ?? '[]', true);
$badges = json_decode($hospital['badges'] ?? '[]', true);
$treatment_tags = json_decode($hospital['treatment_tags'] ?? '[]', true);
$subway_names = json_decode($hospital['subway_names'] ?? '[]', true);
$business_hours = json_decode($hospital['business_hours'] ?? '{}', true);

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($hospital['name']); ?> | <?php echo getTheme('site_name'); ?></title>
    <?php echo generateFaviconLinks(); ?>
    <?php echo generateGoogleFontsLink(); ?>
    <?php echo generateThemeCSS(); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #FFFFFF;
            color: #000000;
            padding-bottom: 80px;
        }

        /* 헤더 */
        .header {
            background: #FFFFFF;
            border-bottom: 1px solid #F5F5F5;
            padding: 16px 20px;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .back-btn {
            font-size: 24px;
            cursor: pointer;
            text-decoration: none;
            color: #000;
        }

        .header-title {
            font-size: 18px;
            font-weight: bold;
        }

        /* 이미지 갤러리 */
        .image-gallery-container {
            position: relative;
            width: 100%;
            height: 400px;
            background: #F5F5F5;
            overflow: hidden;
        }

        .image-gallery {
            display: flex;
            height: 100%;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            touch-action: pan-y pinch-zoom;
        }

        .gallery-item {
            min-width: 100%;
            height: 100%;
            position: relative;
        }

        .gallery-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* 좌우 네비게이션 버튼 */
        .gallery-nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #333;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            opacity: 0;
            transition: opacity 0.3s, background 0.2s;
            z-index: 10;
        }

        .gallery-nav-btn:hover {
            background: rgba(255, 255, 255, 1);
        }

        .gallery-nav-btn:active {
            transform: translateY(-50%) scale(0.95);
        }

        .image-gallery-container:hover .gallery-nav-btn {
            opacity: 1;
        }

        .gallery-nav-btn.prev {
            left: 16px;
        }

        .gallery-nav-btn.next {
            right: 16px;
        }

        .gallery-nav-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        /* 이미지 카운터 */
        .gallery-counter {
            position: absolute;
            bottom: 16px;
            right: 16px;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            z-index: 10;
            backdrop-filter: blur(4px);
        }

        /* 이미지 타입 뱃지 */
        .image-type-badge {
            position: absolute;
            top: 16px;
            left: 16px;
            background: rgba(124, 58, 237, 0.9);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            z-index: 10;
        }

        /* 모바일 대응 */
        @media (max-width: 768px) {
            .image-gallery-container {
                height: 300px;
            }

            .gallery-nav-btn {
                width: 40px;
                height: 40px;
                font-size: 18px;
            }

            .gallery-nav-btn.prev {
                left: 8px;
            }

            .gallery-nav-btn.next {
                right: 8px;
            }
        }

        /* 컨테이너 */
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        /* 병원 정보 */
        .hospital-info {
            margin-bottom: 30px;
        }

        .hospital-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .rating-section {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
        }

        .rating-score {
            font-size: 32px;
            font-weight: bold;
            color: #AE1932;
        }

        .review-count {
            font-size: 16px;
            color: #666666;
        }

        .badge-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .badge {
            padding: 6px 12px;
            background: #FFF3E0;
            color: #AE1932;
            font-size: 13px;
            border-radius: 6px;
            font-weight: 500;
        }

        .badge.category {
            background: #E3F2FD;
            color: #1976D2;
        }

        /* 정보 섹션 */
        .info-section {
            background: #F8F9FA;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .info-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 16px;
            color: #000000;
        }

        .info-row {
            display: flex;
            margin-bottom: 12px;
            line-height: 1.6;
        }

        .info-label {
            width: 80px;
            color: #666666;
            flex-shrink: 0;
        }

        .info-value {
            flex: 1;
            color: #000000;
        }

        /* 소개 섹션 */
        .introduction {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #F5F5F5;
            margin-bottom: 20px;
        }

        .introduction-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .introduction-text {
            line-height: 1.8;
            color: #333333;
            white-space: pre-wrap;
        }

        /* 액션 버튼 */
        .action-buttons {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #F5F5F5;
            padding: 16px 20px;
            display: flex;
            gap: 12px;
        }

        .btn {
            flex: 1;
            padding: 16px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #AE1932;
            color: white;
        }

        .btn-primary:hover {
            background: #8A1428;
        }

        .btn-secondary {
            background: #F5F5F5;
            color: #000000;
        }

        .btn-secondary:hover {
            background: #E0E0E0;
        }

        /* 지도 */
        .map-section {
            background: #F5F5F5;
            height: 200px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
        }

        /* 태그 목록 */
        .tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .tag {
            padding: 8px 16px;
            background: white;
            border: 1px solid #E0E0E0;
            border-radius: 20px;
            font-size: 14px;
            color: #333;
        }

        /* 상담 버튼 그룹 */
        .consultation-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin: 20px 0;
        }

        .consultation-btn {
            padding: 16px;
            background: white;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .consultation-btn.active {
            border-color: #AE1932;
            background: #FFF3E0;
        }

        .consultation-btn:hover {
            border-color: #AE1932;
        }

        .consultation-icon {
            font-size: 24px;
            margin-bottom: 8px;
        }

        .consultation-label {
            font-size: 13px;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- 헤더 -->
    <div class="header">
        <a href="index.php" class="back-btn">←</a>
        <div class="header-title"><?php echo htmlspecialchars($hospital['name']); ?></div>
    </div>

    <!-- 이미지 갤러리 -->
    <div class="image-gallery-container" id="galleryContainer">
        <div class="image-gallery" id="imageGallery">
            <?php
            $image_type_labels = [
                'main' => '메인',
                'exterior' => '외관',
                'interior' => '내부',
                'facility' => '시설',
                'etc' => '기타'
            ];

            if (empty($hospital_images)):
            ?>
            <div class="gallery-item">
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='800' height='400'%3E%3Crect fill='%23f5f5f5' width='800' height='400'/%3E%3Ctext x='50%25' y='50%25' fill='%23999' text-anchor='middle' dominant-baseline='middle' font-size='24'%3E병원 이미지%3C/text%3E%3C/svg%3E"
                     alt="이미지 없음"
                     class="gallery-image">
            </div>
            <?php else: ?>
                <?php foreach ($hospital_images as $index => $image): ?>
                <div class="gallery-item" data-index="<?php echo $index; ?>">
                    <img src="<?php echo htmlspecialchars($image['image_url']); ?>"
                         alt="<?php echo htmlspecialchars($hospital['name']); ?> - <?php echo $image_type_labels[$image['image_type']] ?? '이미지'; ?>"
                         class="gallery-image"
                         loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>"
                         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'800\' height=\'400\'%3E%3Crect fill=\'%23f5f5f5\' width=\'800\' height=\'400\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' fill=\'%23999\' text-anchor=\'middle\' dominant-baseline=\'middle\' font-size=\'20\'%3E이미지 로드 실패%3C/text%3E%3C/svg%3E'">

                    <?php if (!empty($image['image_type']) && $image['image_type'] !== 'main'): ?>
                    <span class="image-type-badge">
                        <?php echo $image_type_labels[$image['image_type']] ?? $image['image_type']; ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- 이미지 카운터 -->
        <?php if (count($hospital_images) > 1): ?>
        <div class="gallery-counter" id="galleryCounter">
            <span id="currentIndex">1</span>/<span id="totalImages"><?php echo count($hospital_images); ?></span>
        </div>

        <!-- 이전/다음 버튼 -->
        <button class="gallery-nav-btn prev" id="prevBtn" onclick="navigateGallery(-1)" aria-label="이전 이미지">
            ←
        </button>
        <button class="gallery-nav-btn next" id="nextBtn" onclick="navigateGallery(1)" aria-label="다음 이미지">
            →
        </button>
        <?php endif; ?>
    </div>

    <!-- 컨테이너 -->
    <div class="container">
        <!-- 병원 정보 -->
        <div class="hospital-info">
            <h1 class="hospital-name"><?php echo htmlspecialchars($hospital['name']); ?></h1>

            <div class="rating-section">
                <span class="rating-score"><?php echo number_format($hospital['rating'] ?? 0, 1); ?></span>
                <span class="review-count">(평가 <?php echo number_format($hospital['review_count'] ?? 0); ?>개)</span>
            </div>

            <!-- 카테고리 뱃지 -->
            <?php if (!empty($categories) && is_array($categories)): ?>
            <div class="badge-group">
                <?php foreach (array_slice($categories, 0, 5) as $category): ?>
                    <?php
                    $cat_text = is_array($category) ? (isset($category['name']) ? $category['name'] : $category[0] ?? '') : $category;
                    if (!empty($cat_text) && is_string($cat_text)):
                    ?>
                    <span class="badge category"><?php echo htmlspecialchars($cat_text); ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- 시술 태그 -->
            <?php if (!empty($treatment_tags) && is_array($treatment_tags)): ?>
            <div class="badge-group">
                <?php foreach (array_slice($treatment_tags, 0, 8) as $tag): ?>
                    <?php
                    $tag_text = is_array($tag) ? (isset($tag[0]) ? $tag[0] : '') : $tag;
                    if (!empty($tag_text) && is_string($tag_text)):
                    ?>
                    <span class="badge"><?php echo htmlspecialchars($tag_text); ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- 상담 방법 -->
        <div class="consultation-buttons">
            <div class="consultation-btn <?php echo $hospital['active_phone_consultation'] ? 'active' : ''; ?>">
                <div class="consultation-icon">📞</div>
                <div class="consultation-label">전화상담</div>
            </div>
            <div class="consultation-btn <?php echo $hospital['active_chat_consultation'] ? 'active' : ''; ?>">
                <div class="consultation-icon">💬</div>
                <div class="consultation-label">채팅상담</div>
            </div>
            <div class="consultation-btn <?php echo $hospital['active_general_consultation'] ? 'active' : ''; ?>">
                <div class="consultation-icon">📝</div>
                <div class="consultation-label">일반상담</div>
            </div>
        </div>

        <!-- 기본 정보 -->
        <div class="info-section">
            <h2 class="info-title">📍 병원 정보</h2>

            <div class="info-row">
                <div class="info-label">주소</div>
                <div class="info-value"><?php echo htmlspecialchars($hospital['address'] ?? '-'); ?></div>
            </div>

            <?php if (!empty($hospital['phone'])): ?>
            <div class="info-row">
                <div class="info-label">전화번호</div>
                <div class="info-value">
                    <a href="tel:<?php echo htmlspecialchars($hospital['phone']); ?>" style="color:#AE1932;text-decoration:none;">
                        <?php echo htmlspecialchars($hospital['phone']); ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($subway_names) && is_array($subway_names) && count($subway_names) > 0): ?>
            <div class="info-row">
                <div class="info-label">지하철</div>
                <div class="info-value">
                    <?php
                    foreach ($subway_names as $subway) {
                        $subway_text = is_array($subway) ? (isset($subway[0]) ? $subway[0] : '') : $subway;
                        if (!empty($subway_text) && is_string($subway_text)) {
                            echo htmlspecialchars($subway_text) . ' ';
                        }
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($business_hours) && is_array($business_hours)): ?>
            <div class="info-row">
                <div class="info-label">영업시간</div>
                <div class="info-value">
                    <?php
                    foreach ($business_hours as $day => $hours) {
                        if (is_string($hours)) {
                            echo htmlspecialchars($day) . ': ' . htmlspecialchars($hours) . '<br>';
                        }
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- 지도 -->
        <?php if (!empty($hospital['latitude']) && !empty($hospital['longitude'])): ?>
        <div class="map-section">
            🗺️ 지도 (위도: <?php echo $hospital['latitude']; ?>, 경도: <?php echo $hospital['longitude']; ?>)<br>
            <small>카카오맵 API 연동 예정</small>
        </div>
        <?php endif; ?>

        <!-- 병원 소개 -->
        <?php if (!empty($hospital['introduction']) || !empty($hospital['description'])): ?>
        <div class="introduction">
            <h2 class="introduction-title">🏥 병원 소개</h2>
            <div class="introduction-text">
                <?php echo nl2br(htmlspecialchars($hospital['introduction'] ?? $hospital['description'] ?? '')); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- 액션 버튼 -->
    <div class="action-buttons">
        <button class="btn btn-secondary" onclick="history.back()">← 목록으로</button>
        <button class="btn btn-primary" onclick="location.href='booking.php?id=<?php echo $hospital_id; ?>'"><?php echo getTheme('button_booking'); ?></button>
    </div>

    <script>
        // ========================================
        // 이미지 갤러리
        // ========================================
        const gallery = document.getElementById('imageGallery');
        const galleryContainer = document.getElementById('galleryContainer');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const currentIndexEl = document.getElementById('currentIndex');
        const totalImagesEl = document.getElementById('totalImages');

        let currentIndex = 0;
        let totalImages = <?php echo count($hospital_images); ?>;
        let startX = 0;
        let isDragging = false;
        let currentTranslate = 0;
        let prevTranslate = 0;

        // 갤러리 이동 함수
        function navigateGallery(direction) {
            currentIndex += direction;

            // 범위 제한
            if (currentIndex < 0) currentIndex = 0;
            if (currentIndex >= totalImages) currentIndex = totalImages - 1;

            updateGallery();
        }

        function updateGallery() {
            const translateX = -currentIndex * 100;
            gallery.style.transform = `translateX(${translateX}%)`;

            // 카운터 업데이트
            if (currentIndexEl) {
                currentIndexEl.textContent = currentIndex + 1;
            }

            // 버튼 활성화/비활성화
            if (prevBtn) {
                prevBtn.disabled = currentIndex === 0;
            }
            if (nextBtn) {
                nextBtn.disabled = currentIndex === totalImages - 1;
            }
        }

        // 터치 이벤트 (모바일 스와이프)
        if (galleryContainer && totalImages > 1) {
            galleryContainer.addEventListener('touchstart', (e) => {
                startX = e.touches[0].clientX;
                isDragging = true;
                gallery.style.transition = 'none';
            });

            galleryContainer.addEventListener('touchmove', (e) => {
                if (!isDragging) return;

                const currentX = e.touches[0].clientX;
                const diff = currentX - startX;
                currentTranslate = prevTranslate + diff;

                // 슬라이드 이동
                const translateX = -currentIndex * 100 + (diff / galleryContainer.offsetWidth) * 100;
                gallery.style.transform = `translateX(${translateX}%)`;
            });

            galleryContainer.addEventListener('touchend', (e) => {
                if (!isDragging) return;
                isDragging = false;

                const movedBy = currentTranslate - prevTranslate;

                // 스와이프 거리가 50px 이상이면 페이지 이동
                if (movedBy < -50 && currentIndex < totalImages - 1) {
                    currentIndex++;
                } else if (movedBy > 50 && currentIndex > 0) {
                    currentIndex--;
                }

                gallery.style.transition = 'transform 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                updateGallery();

                prevTranslate = currentTranslate;
            });
        }

        // 키보드 네비게이션
        document.addEventListener('keydown', (e) => {
            if (totalImages <= 1) return;

            if (e.key === 'ArrowLeft') {
                navigateGallery(-1);
            } else if (e.key === 'ArrowRight') {
                navigateGallery(1);
            }
        });

        // 초기화
        updateGallery();

        // ========================================
        // 상담 버튼
        // ========================================
        document.querySelectorAll('.consultation-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const label = this.querySelector('.consultation-label').textContent;
                const hospitalId = <?php echo $hospital_id; ?>;

                if (label === '전화상담') {
                    const phone = '<?php echo addslashes($hospital['phone'] ?? ''); ?>';
                    if (phone) {
                        window.location.href = 'tel:' + phone;
                    } else {
                        alert('전화번호 정보가 없습니다.');
                    }
                } else if (label === '채팅상담') {
                    location.href = 'consultation.php?id=' + hospitalId + '&type=chat';
                } else if (label === '일반상담') {
                    location.href = 'consultation.php?id=' + hospitalId + '&type=general';
                }
            });
        });
    </script>
</body>
</html>
<?php mysqli_close($connect); ?>
