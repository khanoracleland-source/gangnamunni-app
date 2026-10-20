<?php
/**
 * 바비톡 스타일 병원 상세 페이지
 */

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';

$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);

if (!$connect) {
    die("MySQL 연결 실패: " . mysqli_connect_error());
}

mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

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
}

// JSON 데이터 파싱
$categories = json_decode($hospital['categories'] ?? '[]', true);
$treatment_tags = json_decode($hospital['treatment_tags'] ?? '[]', true);
$subway_names = json_decode($hospital['subway_names'] ?? '[]', true);

// 현재 탭
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'home';

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($hospital['name']); ?> | 서울미녀</title>
    <?php echo generateFaviconLinks(); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Malgun Gothic', sans-serif;
            background: #F7F7F9;
            color: #333;
            line-height: 1.6;
        }

        /* PC 중앙 정렬 컨테이너 */
        .page-container {
            max-width: 640px;
            margin: 0 auto;
            background: #FFFFFF;
            min-height: 100vh;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }

        /* 헤더 */
        .header {
            position: fixed;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 640px;
            height: 56px;
            background: white;
            border-bottom: 1px solid #F0F0F0;
            display: flex;
            align-items: center;
            padding: 0 16px;
            z-index: 100;
        }

        .back-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: calc(24px * var(--fs-scale,1) * var(--fs-page,1));
            color: #333;
            text-decoration: none;
            margin-right: 8px;
        }

        .hospital-name-header {
            flex: 1;
            font-size: calc(16px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* 이미지 갤러리 */
        .image-gallery-container {
            position: relative;
            width: 100%;
            height: 420px;
            background: #F5F5F5;
            overflow: hidden;
            margin-top: 56px;
        }

        .image-gallery {
            display: flex;
            height: 100%;
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .gallery-item {
            min-width: 100%;
            height: 100%;
        }

        .gallery-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .gallery-counter {
            position: absolute;
            bottom: 16px;
            right: 16px;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            padding: 6px 12px;
            border-radius: 16px;
            font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 500;
            backdrop-filter: blur(4px);
        }

        .gallery-nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 50%;
            font-size: calc(20px * var(--fs-scale,1) * var(--fs-page,1));
            color: #333;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 10;
        }

        .image-gallery-container:hover .gallery-nav-btn {
            opacity: 1;
        }

        .gallery-nav-btn.prev { left: 16px; }
        .gallery-nav-btn.next { right: 16px; }
        .gallery-nav-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        /* 병원 정보 */
        .hospital-info-section {
            padding: 20px;
            background: white;
        }

        .hospital-name {
            font-size: calc(22px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: bold;
            margin-bottom: 8px;
        }

        .hospital-specialty {
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
            color: #666;
            margin-bottom: 12px;
        }

        .rating-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 12px;
        }

        .star-icon {
            color: #FFB800;
            font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1));
        }

        .rating-score {
            font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: bold;
            color: #333;
        }

        .review-count {
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
            color: #666;
        }

        /* 태그 */
        .tags-section {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 16px;
        }

        .tag {
            padding: 6px 12px;
            background: #F0F4FF;
            color: #4A90E2;
            border-radius: 4px;
            font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 500;
        }

        /* 영업시간 & 주소 */
        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #F5F5F5;
        }

        .info-icon {
            width: 24px;
            font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1));
            margin-right: 12px;
        }

        .info-content {
            flex: 1;
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
            color: #666;
        }

        .info-content strong {
            color: #333;
        }

        /* 병원 특징 아이콘 */
        .features-section {
            display: flex;
            padding: 16px 20px;
            background: white;
            border-top: 8px solid #F8F8F8;
            gap: 16px;
            overflow-x: auto;
        }

        .feature-item {
            text-align: center;
            min-width: 60px;
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            background: #F8F8F8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: calc(24px * var(--fs-scale,1) * var(--fs-page,1));
            margin: 0 auto 6px;
        }

        .feature-label {
            font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
            color: #666;
        }

        /* 탭 메뉴 */
        .tab-menu {
            position: sticky;
            top: 56px;
            background: white;
            border-bottom: 1px solid #F0F0F0;
            display: flex;
            z-index: 90;
        }

        .tab-item {
            flex: 1;
            padding: 16px 12px;
            text-align: center;
            font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 500;
            color: #999;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.2s;
        }

        .tab-item.active {
            color: #AC1830;
            border-bottom-color: #AC1830;
        }

        .tab-count {
            font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1));
            color: #999;
            margin-left: 4px;
        }

        /* 탭 컨텐츠 */
        .tab-content {
            padding: 20px;
            background: white;
            min-height: 400px;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }

        /* 하단 버튼 */
        .bottom-bar {
            position: fixed;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 640px;
            background: white;
            border-top: 1px solid #F0F0F0;
            padding: 12px 20px;
            display: flex;
            gap: 12px;
            z-index: 100;
        }

        .btn {
            flex: 1;
            padding: 14px;
            border-radius: 8px;
            font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1));
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-outline {
            background: white;
            border: 1px solid #DDD;
            color: #333;
        }

        .btn-primary {
            background: #AC1830;
            color: white;
        }

        .btn-primary:active {
            background: #6D28D9;
        }

        /* 컨텐츠 여백 */
        .content-spacer {
            height: 80px;
        }
    </style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
    <div class="page-container">
    <!-- 헤더 -->
    <div class="header">
        <a href="hospitals.php" class="back-btn">←</a>
        <div class="hospital-name-header"><?php echo htmlspecialchars($hospital['name']); ?></div>
    </div>

    <!-- 이미지 갤러리 -->
    <div class="image-gallery-container" id="galleryContainer">
        <div class="image-gallery" id="imageGallery">
            <?php if (empty($hospital_images)): ?>
            <div class="gallery-item">
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='800' height='420'%3E%3Crect fill='%23f5f5f5' width='800' height='420'/%3E%3Ctext x='50%25' y='50%25' fill='%23999' text-anchor='middle' dominant-baseline='middle' font-size='24'%3E병원 이미지%3C/text%3E%3C/svg%3E"
                     alt="이미지 없음"
                     class="gallery-image">
            </div>
            <?php else: ?>
                <?php foreach ($hospital_images as $index => $image): ?>
                <div class="gallery-item">
                    <img src="<?php echo htmlspecialchars($image['image_url']); ?>"
                         alt="<?php echo htmlspecialchars($hospital['name']); ?>"
                         class="gallery-image"
                         loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>">
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (count($hospital_images) > 1): ?>
        <div class="gallery-counter">
            <span id="currentIndex">1</span>/<span id="totalImages"><?php echo count($hospital_images); ?></span>
        </div>

        <button class="gallery-nav-btn prev" id="prevBtn" onclick="navigateGallery(-1)">←</button>
        <button class="gallery-nav-btn next" id="nextBtn" onclick="navigateGallery(1)">→</button>
        <?php endif; ?>
    </div>

    <!-- 병원 정보 -->
    <div class="hospital-info-section">
        <h1 class="hospital-name"><?php echo htmlspecialchars($hospital['name']); ?></h1>
        <p class="hospital-specialty">성형외과 전문의</p>

        <div class="rating-row">
            <span class="star-icon">★</span>
            <span class="rating-score"><?php echo number_format($hospital['rating'] ?? 4.8, 1); ?></span>
            <span class="review-count">(<?php echo number_format($hospital['review_count'] ?? 27785); ?>)</span>
        </div>

        <!-- 태그 -->
        <?php if (!empty($treatment_tags)): ?>
        <div class="tags-section">
            <?php foreach (array_slice($treatment_tags, 0, 10) as $tag): ?>
            <?php
            $tag_text = is_array($tag) ? ($tag[0] ?? '') : $tag;
            if (!empty($tag_text) && is_string($tag_text)):
            ?>
            <span class="tag"><?php echo htmlspecialchars($tag_text); ?></span>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- 영업시간 -->
        <div class="info-row">
            <span class="info-icon">🕐</span>
            <div class="info-content">
                <strong>금 10:00~20:00</strong>
            </div>
        </div>

        <!-- 주소 -->
        <div class="info-row">
            <span class="info-icon">📍</span>
            <div class="info-content">
                <?php echo htmlspecialchars($hospital['address'] ?? '서울특별시 강남구'); ?>
                <?php if (!empty($subway_names)): ?>
                <br><small>주소복사</small>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 병원 특징 -->
    <div class="features-section">
        <div class="feature-item">
            <div class="feature-icon">👨‍⚕️</div>
            <div class="feature-label">전문의 34명</div>
        </div>
        <div class="feature-item">
            <div class="feature-icon">🏥</div>
            <div class="feature-label">진료실부족</div>
        </div>
        <div class="feature-item">
            <div class="feature-icon">👤</div>
            <div class="feature-label">의사상담</div>
        </div>
        <div class="feature-item">
            <div class="feature-icon">🅿️</div>
            <div class="feature-label">주차가능</div>
        </div>
        <div class="feature-item">
            <div class="feature-icon">📺</div>
            <div class="feature-label">CCTV실시</div>
        </div>
    </div>

    <!-- 탭 메뉴 -->
    <div class="tab-menu">
        <div class="tab-item <?php echo $current_tab === 'home' ? 'active' : ''; ?>" onclick="switchTab('home')">
            홈
        </div>
        <div class="tab-item <?php echo $current_tab === 'events' ? 'active' : ''; ?>" onclick="switchTab('events')">
            이벤트<span class="tab-count">(94)</span>
        </div>
        <div class="tab-item <?php echo $current_tab === 'reviews' ? 'active' : ''; ?>" onclick="switchTab('reviews')">
            후기<span class="tab-count">(27,785)</span>
        </div>
        <div class="tab-item <?php echo $current_tab === 'doctors' ? 'active' : ''; ?>" onclick="switchTab('doctors')">
            의사<span class="tab-count">(39)</span>
        </div>
        <div class="tab-item <?php echo $current_tab === 'youtube' ? 'active' : ''; ?>" onclick="switchTab('youtube')">
            유튜브<span class="tab-count">(1,197)</span>
        </div>
    </div>

    <!-- 탭 컨텐츠 -->
    <div class="tab-content">
        <div class="tab-pane <?php echo $current_tab === 'home' ? 'active' : ''; ?>" id="tab-home">
            <h3>병원 소개</h3>
            <p><?php echo nl2br(htmlspecialchars($hospital['introduction'] ?? '병원 소개 내용이 없습니다.')); ?></p>
        </div>

        <div class="tab-pane <?php echo $current_tab === 'events' ? 'active' : ''; ?>" id="tab-events">
            <h3>이벤트</h3>
            <p>이벤트 목록이 여기에 표시됩니다.</p>
        </div>

        <div class="tab-pane <?php echo $current_tab === 'reviews' ? 'active' : ''; ?>" id="tab-reviews">
            <h3>후기</h3>
            <p>후기 목록이 여기에 표시됩니다.</p>
        </div>

        <div class="tab-pane <?php echo $current_tab === 'doctors' ? 'active' : ''; ?>" id="tab-doctors">
            <h3>의사</h3>
            <p>의사 목록이 여기에 표시됩니다.</p>
        </div>

        <div class="tab-pane <?php echo $current_tab === 'youtube' ? 'active' : ''; ?>" id="tab-youtube">
            <h3>유튜브</h3>
            <p>유튜브 영상 목록이 여기에 표시됩니다.</p>
        </div>
    </div>

    <div class="content-spacer"></div>

    <!-- 하단 버튼 -->
    <div class="bottom-bar">
        <button class="btn btn-outline" onclick="location.href='tel:<?php echo $hospital['phone'] ?? ''; ?>'">
            병원 상담신청
        </button>
        <button class="btn btn-primary" onclick="alert('의사 상담 기능')">
            의사 상담신청
        </button>
    </div>

    </div><!-- /.page-container -->

    <script>
        // 이미지 갤러리
        const gallery = document.getElementById('imageGallery');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const currentIndexEl = document.getElementById('currentIndex');

        let currentIndex = 0;
        let totalImages = <?php echo count($hospital_images); ?>;

        function navigateGallery(direction) {
            currentIndex += direction;
            if (currentIndex < 0) currentIndex = 0;
            if (currentIndex >= totalImages) currentIndex = totalImages - 1;
            updateGallery();
        }

        function updateGallery() {
            gallery.style.transform = `translateX(-${currentIndex * 100}%)`;
            if (currentIndexEl) currentIndexEl.textContent = currentIndex + 1;
            if (prevBtn) prevBtn.disabled = currentIndex === 0;
            if (nextBtn) nextBtn.disabled = currentIndex === totalImages - 1;
        }

        // 탭 전환
        function switchTab(tab) {
            // URL 변경 없이 탭만 전환
            document.querySelectorAll('.tab-item').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));

            event.target.classList.add('active');
            document.getElementById('tab-' + tab).classList.add('active');
        }

        updateGallery();
    </script>
</body>
</html>
<?php mysqli_close($connect); ?>
