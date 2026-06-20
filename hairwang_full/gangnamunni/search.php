<?php
/**
 * ========================================
 * 서울미녀 스타일 검색 페이지
 * 공통 header/footer 레이아웃 사용
 * ========================================
 */

// 초기화 (그누보드 세션 + DB 연결)
require_once __DIR__ . '/includes/init.php';

// 검색어 가져오기
$keyword = isset($_GET['q']) ? trim($_GET['q']) : '';
$escaped_keyword = mysqli_real_escape_string($connect, $keyword);

// 병원 검색
$hospitals = [];
if (!empty($keyword)) {
    $query = "SELECT * FROM gangnamunni_hospitals
              WHERE name LIKE '%{$escaped_keyword}%'
              OR address LIKE '%{$escaped_keyword}%'
              OR treatment_tags LIKE '%{$escaped_keyword}%'
              ORDER BY rating DESC, review_count DESC
              LIMIT 50";
    $result = mysqli_query($connect, $query);

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $hospitals[] = $row;
        }
    }
}

// --- 레이아웃 변수 설정 ---
$page_title = '검색';
$show_search = false; // 이 페이지는 자체 검색 바를 사용
$page_css = '
/* 검색 헤더 (back 버튼 + 검색 입력) */
.search-header {
    padding: 12px 0;
}

.search-header-content {
    max-width: 640px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 0 16px;
}

.back-btn {
    font-size: 24px;
    cursor: pointer;
    text-decoration: none;
    color: #000;
}

.search-bar {
    flex: 1;
}

.search-input-page {
    width: 100%;
    padding: 10px 16px;
    border: 1px solid #F5F5F5;
    border-radius: 8px;
    font-size: 14px;
    background: #F5F5F5;
}

.search-input-page:focus {
    outline: none;
    border-color: #AE1932;
}

/* 검색 결과 헤더 */
.results-header {
    margin-bottom: 20px;
}

.results-header h1 {
    font-size: 20px;
    margin-bottom: 8px;
}

.search-count {
    color: #666666;
    font-size: 14px;
}

.search-keyword {
    color: #AE1932;
    font-weight: bold;
}

/* 뷰 전환 버튼 */
.view-toggle {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
}

.view-btn {
    padding: 6px 14px;
    border: 1px solid #E5E7EB;
    border-radius: 20px;
    background: #FFFFFF;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
}

.view-btn.active {
    background: #AE1932;
    color: #FFFFFF;
    border-color: #AE1932;
}

/* 병원 카드 그리드 */
.hospital-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 40px;
}

.hospital-grid.list-view {
    grid-template-columns: 1fr;
}

.hospital-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    cursor: pointer;
    transition: all 0.3s;
}

.hospital-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}

.hospital-image {
    width: 100%;
    height: 160px;
    object-fit: cover;
    background: #F5F5F5;
}

.hospital-info {
    padding: 16px;
}

.hospital-name {
    font-size: 16px;
    font-weight: bold;
    margin-bottom: 8px;
    color: #000000;
}

.hospital-address {
    font-size: 12px;
    color: #999999;
    margin-bottom: 12px;
}

.rating-section {
    display: flex;
    align-items: center;
    gap: 8px;
}

.rating-score {
    font-size: 18px;
    font-weight: bold;
    color: #AE1932;
}

.review-count {
    font-size: 12px;
    color: #666666;
}

.badge-group {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
    margin-top: 8px;
}

.badge {
    padding: 4px 8px;
    background: #FFF3E0;
    color: #AE1932;
    font-size: 11px;
    border-radius: 4px;
}

/* 결과 없음 */
.no-result {
    text-align: center;
    padding: 80px 20px;
    color: #999999;
}

.no-result-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.no-result-text {
    font-size: 18px;
    margin-bottom: 12px;
}

.no-result-subtext {
    font-size: 14px;
}

@media (max-width: 768px) {
    .hospital-grid {
        grid-template-columns: 1fr;
    }
}
';

$page_js = '
function switchView(viewType) {
    var grid = document.getElementById("hospital-grid");
    var cardBtn = document.getElementById("view-card");
    var listBtn = document.getElementById("view-list");

    // 요소가 없으면 함수 종료
    if (!grid || !cardBtn || !listBtn) return;

    if (viewType === "list") {
        grid.classList.add("list-view");
        cardBtn.classList.remove("active");
        listBtn.classList.add("active");
    } else {
        grid.classList.remove("list-view");
        cardBtn.classList.add("active");
        listBtn.classList.remove("active");
    }
    localStorage.setItem("hospital_view", viewType);
}

document.addEventListener("DOMContentLoaded", function() {
    var savedView = localStorage.getItem("hospital_view");
    if (savedView === "list") switchView("list");
});
';

// --- 헤더 포함 ---
include __DIR__ . '/includes/header.php';
?>

<!-- 검색 헤더 (back 버튼 + 검색 입력) -->
<div class="search-header">
    <div class="search-header-content">
        <a href="index.php" class="back-btn">&larr;</a>
        <div class="search-bar">
            <form method="GET" action="search.php">
                <input type="text" name="q" class="search-input-page"
                       placeholder="<?= term('business') ?>, <?= term('service') ?> 검색"
                       value="<?php echo htmlspecialchars($keyword); ?>"
                       autofocus>
            </form>
        </div>
    </div>
</div>

<?php if (!empty($keyword)): ?>
<!-- 검색 결과 헤더 -->
<div class="results-header">
    <h1><span class="search-keyword">'<?php echo htmlspecialchars($keyword); ?>'</span> 검색 결과</h1>
    <p class="search-count">총 <strong><?php echo count($hospitals); ?></strong>개의 병원을 찾았습니다.</p>
</div>

<?php if (!empty($hospitals)): ?>
<!-- 뷰 전환 버튼 -->
<div class="view-toggle">
    <button class="view-btn active" onclick="switchView('card')" id="view-card">카드형</button>
    <button class="view-btn" onclick="switchView('list')" id="view-list">리스트형</button>
</div>
<!-- 병원 카드 그리드 -->
<div class="hospital-grid" id="hospital-grid">
    <?php foreach ($hospitals as $hospital): ?>
    <div class="hospital-card" onclick="location.href='detail.php?id=<?php echo $hospital['id']; ?>'">
        <img
            src="<?php echo htmlspecialchars($hospital['profile_image_url'] ?? ''); ?>"
            alt="<?php echo htmlspecialchars($hospital['name']); ?>"
            class="hospital-image"
            onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'300\' height=\'200\'%3E%3Crect fill=\'%23f5f5f5\' width=\'300\' height=\'200\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' fill=\'%23999\' text-anchor=\'middle\' dominant-baseline=\'middle\' font-size=\'20\'%3E병원 이미지%3C/text%3E%3C/svg%3E'"
        >
        <div class="hospital-info">
            <div class="hospital-name"><?php echo htmlspecialchars($hospital['name']); ?></div>
            <div class="hospital-address"><?php echo htmlspecialchars(mb_substr($hospital['address'] ?? '', 0, 30)); ?></div>
            <div class="rating-section">
                <span class="rating-score"><?php echo number_format($hospital['rating'] ?? 0, 1); ?></span>
                <span class="review-count">(평가 <?php echo number_format($hospital['review_count'] ?? 0); ?>개)</span>
            </div>
            <?php if (!empty($hospital['treatment_tags'])): ?>
            <div class="badge-group">
                <?php
                $tags = json_decode($hospital['treatment_tags'], true);
                if (is_array($tags)) {
                    foreach (array_slice($tags, 0, 3) as $tag) {
                        $tag_text = is_array($tag) ? (isset($tag[0]) ? $tag[0] : '') : $tag;
                        if (!empty($tag_text) && is_string($tag_text)) {
                            echo '<span class="badge">' . htmlspecialchars($tag_text) . '</span>';
                        }
                    }
                }
                ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<!-- 결과 없음 -->
<div class="no-result">
    <div class="no-result-icon">&#128269;</div>
    <div class="no-result-text">검색 결과가 없습니다</div>
    <div class="no-result-subtext">다른 검색어로 시도해보세요.</div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- 검색어 입력 안 함 -->
<div class="no-result">
    <div class="no-result-icon">&#128269;</div>
    <div class="no-result-text">검색어를 입력해주세요</div>
    <div class="no-result-subtext">병원명, 지역, 시술명으로 검색할 수 있습니다.</div>
</div>
<?php endif; ?>

<?php
// --- 푸터 포함 ---
include __DIR__ . '/includes/footer.php';
?>
