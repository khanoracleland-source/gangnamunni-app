<?php
/**
 * ========================================
 * 커뮤니티 / 뷰티팁 통합 페이지
 * ========================================
 * ?type=community  → 바비톡 스타일 커뮤니티 게시판
 * ?type=beauty     → 뷰티 꿀팁 (기존)
 * (기본)           → 뷰티 꿀팁
 */

// 초기화 (그누보드 세션 + DB 연결)
require_once __DIR__ . '/includes/init.php';

// 페이지 타입 결정
$page_type = isset($_GET['type']) ? trim($_GET['type']) : 'beauty';

// ============================
// 커뮤니티 타입
// ============================
if ($page_type === 'community') {

    // 커뮤니티 카테고리 필터
    $community_category = isset($_GET['category']) ? trim($_GET['category']) : '';
    $community_categories = ['성형', '뼈때/피부', '일상', '의사 Q&A', '뷰티랩'];

    // 커뮤니티 게시글 테이블 확인
    $community_table_check = mysqli_query($connect, "SHOW TABLES LIKE 'gangnamunni_community_posts'");
    $community_table_exists = $community_table_check && mysqli_num_rows($community_table_check) > 0;

    $community_posts = [];

    if ($community_table_exists) {
        $escaped_cat = mysqli_real_escape_string($connect, $community_category);
        if (!empty($community_category)) {
            $cq = "SELECT * FROM gangnamunni_community_posts WHERE status='active' AND category = '{$escaped_cat}' ORDER BY created_at DESC LIMIT 50";
        } else {
            $cq = "SELECT * FROM gangnamunni_community_posts WHERE status='active' ORDER BY created_at DESC LIMIT 50";
        }
        $cr = mysqli_query($connect, $cq);
        if ($cr) {
            while ($row = mysqli_fetch_assoc($cr)) {
                $community_posts[] = $row;
            }
        }
    }

    // 데이터가 없으면 데모 데이터 사용
    if (empty($community_posts)) {
        $community_posts = [
            ['id'=>1, 'category'=>'성형', 'title'=>'상담 예약 완료!', 'preview'=>'눈,코 수술 상담 예약 잡아뒀다 제일 가보고 싶었던 병원을 제일 먼저 갈지 나중에 ...', 'author'=>'눈이부셔메이', 'comments'=>0, 'time_ago'=>'5분 전', 'thumbnail'=>''],
            ['id'=>2, 'category'=>'성형', 'title'=>'마른체형 가슴성형', 'preview'=>'마른체형이에요..상체만..ㅋㅋㅋ 굉장히 빈약해보이거든요 하체는 튼실하구요 하체...', 'author'=>'비통통이얌', 'comments'=>0, 'time_ago'=>'5분 전', 'thumbnail'=>''],
            ['id'=>3, 'category'=>'성형', 'title'=>'눈재랑 눈밑지 같이 생각하고 있어요', 'preview'=>'눈이 점점 짝짝이처럼 보이면서 사진 찍는 것도 싫어져서 결국 눈재수술이랑 눈밑지...', 'author'=>'빗소리무드등', 'comments'=>3, 'time_ago'=>'7분 전', 'thumbnail'=>''],
            ['id'=>4, 'category'=>'성형', 'title'=>'짧은코 자려 직반', 'preview'=>'더도어 김 에이비 김 중 누가 더 괜찮을까요', 'author'=>'여우wjJDO', 'comments'=>1, 'time_ago'=>'9분 전', 'thumbnail'=>''],
            ['id'=>5, 'category'=>'성형', 'title'=>'프레쉬홍닥터 눈밑지방재배치 후기', 'preview'=>'눈 밑이 꺼지고 다크서클도 심해서 항상 피곤해보인다는 말을 많이 들었어요 그리하...', 'author'=>'삐삐닝닝', 'comments'=>0, 'time_ago'=>'9분 전', 'thumbnail'=>'has'],
            ['id'=>6, 'category'=>'성형', 'title'=>'남은 상담도 끝', 'preview'=>'어제 남아있던 코 상담도 잘 받고 왔음 ㅋㅋ 상담 시간이 짧았다거나, 소통이 안됐다...', 'author'=>'뷰두까가', 'comments'=>1, 'time_ago'=>'10분 전', 'thumbnail'=>''],
            ['id'=>7, 'category'=>'성형', 'title'=>"넓은 모공 ..( ;'∧`)", 'preview'=>'모공이 정말 계속 신경쓰여서 화장품도 바껴보고 모공으로 좋다고 하는것중에서 집...', 'author'=>'세금내는다람쥐', 'comments'=>0, 'time_ago'=>'11분 전', 'thumbnail'=>''],
            ['id'=>8, 'category'=>'성형', 'title'=>'눈,코 재수술', 'preview'=>'코는 내가 한번 했는데 코끝만 비중격 하고 콧대는 안했어 그래서 약간 휘어있음 코...', 'author'=>'모찌s', 'comments'=>1, 'time_ago'=>'12분 전', 'thumbnail'=>'has'],
            ['id'=>9, 'category'=>'성형', 'title'=>'가슴 볼 때 마다 뿌듯해요', 'preview'=>'역시 습부심이란 말은 괜히 있는게 아니라는걸 수술 후에 깨달았네요 ㅎㅎ 디에이에...', 'author'=>'뿅메론', 'comments'=>1, 'time_ago'=>'13분 전', 'thumbnail'=>''],
            ['id'=>10, 'category'=>'성형', 'title'=>'이마패임 ㅠㅠ', 'preview'=>'가운데가 빛 보이는 데서 보면 패여있어 검정에 선으로? 화장하면 좀 감; 근데 볼록...', 'author'=>'냥이Cfuq2', 'comments'=>0, 'time_ago'=>'14분 전', 'thumbnail'=>'has'],
            ['id'=>11, 'category'=>'성형', 'title'=>'니즈 반영잘해주는곳', 'preview'=>'눈 수술 상담 받았는데 내가 원하는 방향으로 잘 들어주고 설명도 꼼꼼하게 해주셔서...', 'author'=>'예쁨이쏭', 'comments'=>2, 'time_ago'=>'15분 전', 'thumbnail'=>''],
            ['id'=>12, 'category'=>'성형', 'title'=>'하이봄 성형외과 눈밑지 수술 후기', 'preview'=>'눈밑지방 재배치 수술 받고 2주 지났어요 붓기도 거의 빠지고 다크서클도 확실히...', 'author'=>'봄날햇살', 'comments'=>5, 'time_ago'=>'16분 전', 'thumbnail'=>'has'],
        ];

        // 카테고리 필터 적용
        if (!empty($community_category)) {
            $community_posts = array_filter($community_posts, function($post) use ($community_category) {
                return $post['category'] === $community_category;
            });
        }
    }

    // 헤더 변수 설정
    $page_title = '커뮤니티';
    $show_search = false;

    $page_css = '
/* 커뮤니티 페이지 스타일 */

/* 카테고리 탭 */
.community-tabs {
    display: flex;
    justify-content: center;
    gap: 12px;
    padding: 20px 16px;
    flex-wrap: wrap;
}

.community-tab {
    padding: 10px 24px;
    border-radius: 25px;
    font-size: 15px;
    font-weight: 500;
    text-decoration: none;
    color: #333;
    background: #F5F5F5;
    border: 1px solid #EEEEEE;
    transition: all 0.2s;
    white-space: nowrap;
}

.community-tab:hover {
    background: #E8E8E8;
}

.community-tab.active {
    background: #5B5FC7;
    color: white;
    border-color: #5B5FC7;
}

/* 뷰 전환 버튼 */
.community-view-toggle {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    padding: 0 16px 16px;
}
.comm-view-btn {
    padding: 8px 16px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s;
}
.comm-view-btn.active {
    background: #5B5FC7;
    color: white;
    border-color: #5B5FC7;
}

/* ========== 커뮤니티 게시글 목록 ========== */
.community-list {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0;
    border-top: 1px solid #F0F0F0;
}

/* 리스트형 (1열) */
.community-list.single-column {
    grid-template-columns: 1fr;
}
.community-list.single-column .community-post:nth-child(odd) {
    border-right: none;
}

.community-post {
    padding: 20px 16px;
    border-bottom: 1px solid #F0F0F0;
    cursor: pointer;
    transition: background 0.15s;
    position: relative;
}

.community-post:nth-child(odd) {
    border-right: 1px solid #F0F0F0;
}

.community-post:hover {
    background: #FAFAFA;
}

.post-category {
    display: inline-block;
    font-size: 13px;
    color: #888;
    margin-bottom: 8px;
}

.post-body {
    display: flex;
    gap: 12px;
}

.post-text {
    flex: 1;
    min-width: 0;
}

.post-title {
    font-size: 16px;
    font-weight: 700;
    color: #222;
    margin-bottom: 6px;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.post-preview {
    font-size: 14px;
    color: #777;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 10px;
}

.post-thumbnail {
    width: 72px;
    height: 72px;
    border-radius: 8px;
    object-fit: cover;
    flex-shrink: 0;
    background: #F0F0F0;
}

.post-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #999;
}

.post-author {
    color: #5B5FC7;
    font-weight: 500;
}

.post-meta-separator {
    color: #DDD;
}

.post-comments {
    color: #999;
}

.post-time {
    color: #BBB;
    margin-left: auto;
}

/* 모바일 반응형 - 카드형(2열)도 모바일에서는 1열 */
@media (max-width: 480px) {
    .community-list {
        grid-template-columns: 1fr;
    }
    .community-post:nth-child(odd) {
        border-right: none;
    }
}
';

    // 공통 헤더 include
    include __DIR__ . '/includes/header.php';
?>

    <!-- 커뮤니티 카테고리 탭 -->
    <div class="community-tabs">
        <a href="tips.php?type=community"
           class="community-tab <?php echo empty($community_category) ? 'active' : ''; ?>">성형</a>
        <?php foreach ($community_categories as $cat): ?>
            <?php if ($cat === '성형') continue; ?>
            <a href="tips.php?type=community&category=<?php echo urlencode($cat); ?>"
               class="community-tab <?php echo $community_category === $cat ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($cat); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- 뷰 전환 버튼 -->
    <div class="community-view-toggle">
        <button class="comm-view-btn active" onclick="switchCommunityView('card')" id="comm-view-card">🔲 카드형</button>
        <button class="comm-view-btn" onclick="switchCommunityView('list')" id="comm-view-list">📋 리스트형</button>
    </div>

    <!-- 게시글 목록 (카드형=2열, 리스트형=1열) -->
    <div class="community-list" id="comm-list-view">
        <?php foreach ($community_posts as $post): ?>
        <div class="community-post" onclick="location.href='detail_community.php?id=<?php echo $post['id']; ?>'">
            <div class="post-category"><?php echo htmlspecialchars($post['category']); ?></div>
            <div class="post-body">
                <div class="post-text">
                    <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>
                    <div class="post-preview"><?php echo htmlspecialchars($post['preview'] ?? $post['description'] ?? ''); ?></div>
                </div>
                <?php if (!empty($post['thumbnail']) && $post['thumbnail'] !== ''): ?>
                <img class="post-thumbnail"
                     src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='72' height='72'%3E%3Crect fill='%23e0e0e0' width='72' height='72' rx='8'/%3E%3Ctext x='50%25' y='50%25' fill='%23999' text-anchor='middle' dominant-baseline='middle' font-size='20'%3E%F0%9F%93%B7%3C/text%3E%3C/svg%3E"
                     alt=""
                     onerror="this.style.display='none'">
                <?php endif; ?>
            </div>
            <div class="post-meta">
                <span class="post-author"><?php echo htmlspecialchars($post['author']); ?></span>
                <span class="post-meta-separator">|</span>
                <span class="post-comments">댓글 <?php echo intval($post['comments'] ?? $post['comment_count'] ?? 0); ?></span>
                <span class="post-time"><?php echo htmlspecialchars($post['time_ago'] ?? ''); ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($community_posts)): ?>
    <div style="text-align: center; padding: 60px 20px; color: #999;">
        <div style="font-size: 48px; margin-bottom: 20px;">💬</div>
        <p>게시글이 없습니다.</p>
    </div>
    <?php endif; ?>

<?php
    $page_js = '
function switchCommunityView(viewType) {
    var listView = document.getElementById("comm-list-view");
    var cardBtn = document.getElementById("comm-view-card");
    var listBtn = document.getElementById("comm-view-list");
    if (!listView) return;

    if (viewType === "list") {
        listView.classList.add("single-column");
        cardBtn.classList.remove("active");
        listBtn.classList.add("active");
    } else {
        listView.classList.remove("single-column");
        cardBtn.classList.add("active");
        listBtn.classList.remove("active");
    }
    localStorage.setItem("community_view", viewType);
}
document.addEventListener("DOMContentLoaded", function() {
    var saved = localStorage.getItem("community_view");
    if (saved === "list") switchCommunityView("list");
});
';

    // 공통 푸터 include
    echo '<div style="height:70px;"></div>';
include_once __DIR__ . '/includes/bottom_nav.php';
include __DIR__ . '/includes/footer.php';
    mysqli_close($connect);
    exit;
}

// ============================
// 뷰티 꿀팁 타입 (기존 로직)
// ============================

// 카테고리 필터
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$escaped_category = mysqli_real_escape_string($connect, $category);

// 꿀팁 데이터 가져오기
$tips = [];
if (!empty($category)) {
    $query = "SELECT * FROM gangnamunni_tips WHERE is_active=1 AND category = '{$escaped_category}' ORDER BY created_at DESC LIMIT 50";
} else {
    $query = "SELECT * FROM gangnamunni_tips WHERE is_active=1 ORDER BY created_at DESC LIMIT 50";
}

$result = mysqli_query($connect, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $tips[] = $row;
    }
}

// 카테고리 목록
$categories_query = "SELECT DISTINCT category FROM gangnamunni_tips WHERE is_active=1 ORDER BY category";
$categories_result = mysqli_query($connect, $categories_query);
$categories = [];
if ($categories_result) {
    while ($row = mysqli_fetch_assoc($categories_result)) {
        $categories[] = $row['category'];
    }
}

// 헤더 변수 설정
$page_title = '뷰티 꿀팁';
$show_search = false;

$page_css = '
/* 페이지 헤더 (글로벌 네비 아래) */
.page-header {
    padding: 12px 20px;
}

.page-header-content {
    max-width: 640px;
    margin: 0 auto;
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

.page-title {
    font-size: 18px;
    font-weight: bold;
}

/* 카테고리 필터 */
.category-filter {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    margin-bottom: 30px;
    padding-bottom: 10px;
}

.filter-btn {
    padding: 10px 20px;
    border: 1px solid #F5F5F5;
    border-radius: 20px;
    background: white;
    font-size: 14px;
    white-space: nowrap;
    cursor: pointer;
    text-decoration: none;
    color: #000000;
}

.filter-btn.active {
    background: #AE1932;
    color: white;
    border-color: #AE1932;
}

/* 뷰 전환 버튼 */
.view-toggle {
    display: flex;
    gap: 8px;
    margin-bottom: 15px;
    justify-content: flex-end;
}
.view-btn {
    padding: 8px 16px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s;
}
.view-btn.active {
    background: #AE1932;
    color: white;
    border-color: #AE1932;
}

/* 꿀팁 그리드 */
.tips-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-bottom: 40px;
}
.tips-grid.list-view {
    grid-template-columns: 1fr;
}
.tips-grid.list-view .tip-card {
    display: flex;
    flex-direction: row;
}
.tips-grid.list-view .tip-image {
    width: 120px;
    height: 120px;
    flex-shrink: 0;
}
.tips-grid.list-view .tip-overlay {
    position: relative;
    background: transparent;
    color: #333;
    padding: 16px;
    flex: 1;
}

.tip-card {
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    position: relative;
    background: #F5F5F5;
    transition: all 0.3s;
}

.tip-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
}

.tip-image {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.tip-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);
    color: white;
    padding: 16px;
    font-size: 14px;
    font-weight: 500;
}

.tip-meta {
    position: absolute;
    top: 12px;
    right: 12px;
    display: flex;
    gap: 8px;
}

.meta-badge {
    background: rgba(0,0,0,0.6);
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
}

@media (max-width: 768px) {
    .tips-grid {
        grid-template-columns: 1fr;
    }
}
';

// 공통 헤더 include
include __DIR__ . '/includes/header.php';
?>

    <!-- 페이지 헤더 -->
    <div class="page-header">
        <div class="page-header-content">
            <a href="index.php" class="back-btn">←</a>
            <div class="page-title">서울미녀 꿀팁</div>
        </div>
    </div>

    <!-- 카테고리 필터 -->
    <div class="category-filter">
        <a href="tips.php?type=beauty" class="filter-btn <?php echo empty($category) ? 'active' : ''; ?>">전체</a>
        <?php foreach ($categories as $cat): ?>
        <a href="tips.php?type=beauty&category=<?php echo urlencode($cat); ?>"
           class="filter-btn <?php echo $category === $cat ? 'active' : ''; ?>">
            <?php echo htmlspecialchars($cat); ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- 뷰 전환 버튼 -->
    <div class="view-toggle">
        <button class="view-btn active" onclick="switchView('card')" id="view-card">🔲 카드형</button>
        <button class="view-btn" onclick="switchView('list')" id="view-list">📋 리스트형</button>
    </div>
    <!-- 꿀팁 그리드 -->
    <div class="tips-grid" id="tips-grid">
        <?php
        $gradient_colors = [
            ['#FFE0B2', '#FFCC80', '📝'],
            ['#F8BBD0', '#F48FB1', '💄'],
            ['#C5E1A5', '#AED581', '🌟'],
            ['#B2EBF2', '#80DEEA', '💊']
        ];
        $color_index = 0;
        foreach ($tips as $tip):
            $colors = $gradient_colors[$color_index % 4];
            $color_index++;
        ?>
        <div class="tip-card" onclick="location.href='detail_tip.php?id=<?php echo $tip['id']; ?>'">
            <div class="tip-image" style="background: linear-gradient(135deg, <?php echo $colors[0]; ?>, <?php echo $colors[1]; ?>); display: flex; align-items: center; justify-content: center; font-size: 48px;">
                <?php echo $colors[2]; ?>
            </div>
            <div class="tip-meta">
                <span class="meta-badge">👁️ <?php echo number_format($tip['views']); ?></span>
                <span class="meta-badge">❤️ <?php echo number_format($tip['likes']); ?></span>
            </div>
            <div class="tip-overlay"><?php echo htmlspecialchars($tip['title']); ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($tips)): ?>
    <div style="text-align: center; padding: 60px 20px; color: #999;">
        <div style="font-size: 48px; margin-bottom: 20px;">💡</div>
        <p>꿀팁이 없습니다.</p>
    </div>
    <?php endif; ?>

<?php
// 페이지 JS 설정
$page_js = '
function switchView(viewType) {
    var grid = document.getElementById("tips-grid");
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
    localStorage.setItem("tip_view", viewType);
}
document.addEventListener("DOMContentLoaded", function() {
    var savedView = localStorage.getItem("tip_view");
    if (savedView === "list") switchView("list");
});

// 네비게이션 아이템 클릭
document.querySelectorAll(".nav-item").forEach(function(item) {
    item.addEventListener("click", function() {
        var page = this.getAttribute("data-page");
        if (page && page !== "#") {
            location.href = page;
        }
    });
});
';

// 공통 푸터 include
include __DIR__ . '/includes/footer.php';

mysqli_close($connect);
?>
