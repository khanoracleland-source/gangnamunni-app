<?php
// Turbo Drive (new app versions only): injects the Turbo tags into <head>. See includes/turbo_boot.php
if (is_file(__DIR__ . '/includes/turbo_boot.php')) { require_once __DIR__ . '/includes/turbo_boot.php'; sb_turbo_boot(); }
/**
 * ========================================
 * 서울미녀 스타일 꿀팁 상세 페이지
 * (공통 header/footer 레이아웃 사용)
 * ========================================
 */

// 데이터베이스 연결
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/config.php';

$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);

if (!$connect) {
    die("MySQL 연결 실패: " . mysqli_connect_error());
}

mysqli_set_charset($connect, 'utf8mb4');

// 꿀팁 ID 가져오기
$tip_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($tip_id <= 0) {
    header('Location: index.php');
    exit;
}

// 꿀팁 정보 조회
$query = "SELECT * FROM gangnamunni_tips WHERE is_active=1 AND id = {$tip_id}";
$result = mysqli_query($connect, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    die("꿀팁 정보를 찾을 수 없습니다.");
}

$tip = mysqli_fetch_assoc($result);

// 조회수 증가
mysqli_query($connect, "UPDATE gangnamunni_tips SET views = views + 1 WHERE id = {$tip_id}");

// 관련 꿀팁 (같은 카테고리)
$related_query = "SELECT * FROM gangnamunni_tips WHERE is_active=1 AND category = '" . mysqli_real_escape_string($connect, $tip['category']) . "' AND id != {$tip_id} ORDER BY created_at DESC LIMIT 3";
$related_result = mysqli_query($connect, $related_query);
$related_tips = [];
if ($related_result) {
    while ($row = mysqli_fetch_assoc($related_result)) {
        $related_tips[] = $row;
    }
}

// --- 헤더 설정 ---
$page_title = '커뮤니티 상세';
$show_search = false;
$page_css = '
    /* 페이지 헤더 (글로벌 네비 아래) */
    .page-header {
        background: #FFFFFF;
        border-bottom: 1px solid #F5F5F5;
        padding: 12px 20px;
        position: sticky;
        top: 56px;
        z-index: 90;
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

    /* 썸네일 */
    .tip-thumbnail {
        width: 100%;
        height: 400px;
        background: linear-gradient(135deg, #FFE0B2, #FFCC80);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 80px;
        margin-bottom: 30px;
        border-radius: 12px;
    }

    /* 제목 */
    .tip-title {
        font-size: 28px;
        font-weight: bold;
        margin-bottom: 16px;
        line-height: 1.4;
    }

    /* 메타 정보 */
    .tip-meta {
        display: flex;
        gap: 20px;
        margin-bottom: 24px;
        padding-bottom: 24px;
        border-bottom: 1px solid #F5F5F5;
        color: #666666;
        font-size: 14px;
    }

    .meta-item {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    /* 카테고리 배지 */
    .category-badge {
        display: inline-block;
        padding: 6px 12px;
        background: #FFF3E0;
        color: #AC1830;
        font-size: 13px;
        border-radius: 6px;
        font-weight: 500;
        margin-bottom: 20px;
    }

    /* 내용 */
    .tip-content {
        line-height: 1.8;
        font-size: 16px;
        margin-bottom: 40px;
        white-space: pre-wrap;
    }

    /* 액션 버튼 */
    .action-buttons {
        display: flex;
        gap: 12px;
        margin-bottom: 40px;
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

    .btn-like {
        background: #AC1830;
        color: white;
    }

    .btn-like:hover {
        background: #8A1428;
    }

    .btn-share {
        background: #F5F5F5;
        color: #000000;
    }

    .btn-share:hover {
        background: #E0E0E0;
    }

    /* 관련 꿀팁 */
    .related-section {
        margin-top: 60px;
    }

    .section-title {
        font-size: 20px;
        font-weight: bold;
        margin-bottom: 20px;
    }

    .related-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }

    .related-card {
        border-radius: 12px;
        overflow: hidden;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .related-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        transition: all 0.3s;
    }

    .related-image {
        width: 100%;
        height: 150px;
        background: linear-gradient(135deg, #C5E1A5, #AED581);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 40px;
    }

    .related-title {
        padding: 16px;
        font-size: 14px;
        font-weight: 500;
    }

    @media (max-width: 768px) {
        .related-grid {
            grid-template-columns: 1fr;
        }
    }
';

include __DIR__ . '/includes/header.php';
?>

    <!-- 페이지 헤더 -->
    <div class="page-header">
        <a href="tips.php" class="back-btn">←</a>
        <div class="page-title">서울미녀 꿀팁</div>
    </div>

    <!-- 썸네일 -->
    <div class="tip-thumbnail">💡</div>

    <!-- 카테고리 -->
    <span class="category-badge"><?php echo htmlspecialchars($tip['category']); ?></span>

    <!-- 제목 -->
    <h1 class="tip-title"><?php echo htmlspecialchars($tip['title']); ?></h1>

    <!-- 메타 정보 -->
    <div class="tip-meta">
        <div class="meta-item">
            <span>✍️</span>
            <span><?php echo htmlspecialchars($tip['author']); ?></span>
        </div>
        <div class="meta-item">
            <span>👁️</span>
            <span><?php echo number_format($tip['views']); ?></span>
        </div>
        <div class="meta-item">
            <span>❤️</span>
            <span><?php echo number_format($tip['likes']); ?></span>
        </div>
        <div class="meta-item">
            <span>📅</span>
            <span><?php echo date('Y-m-d', strtotime($tip['created_at'])); ?></span>
        </div>
    </div>

    <!-- 설명 -->
    <?php if (!empty($tip['description'])): ?>
    <p style="font-size: 18px; color: #666666; margin-bottom: 30px; line-height: 1.6;">
        <?php echo nl2br(htmlspecialchars($tip['description'])); ?>
    </p>
    <?php endif; ?>

    <!-- 내용 -->
    <div class="tip-content">
        <?php echo nl2br(htmlspecialchars($tip['content'])); ?>
    </div>

    <!-- 액션 버튼 -->
    <div class="action-buttons">
        <button class="btn btn-like" onclick="likeTip(<?php echo $tip_id; ?>)">
            ❤️ 좋아요 (<?php echo number_format($tip['likes']); ?>)
        </button>
        <button class="btn btn-share" onclick="shareTip()">
            🔗 공유하기
        </button>
    </div>

    <!-- 관련 꿀팁 -->
    <?php if (!empty($related_tips)): ?>
    <div class="related-section">
        <h2 class="section-title">같은 카테고리의 다른 꿀팁</h2>
        <div class="related-grid">
            <?php
            $related_colors = [
                ['#FFE0B2', '#FFCC80', '📝'],
                ['#F8BBD0', '#F48FB1', '💄'],
                ['#C5E1A5', '#AED581', '🌟']
            ];
            $idx = 0;
            foreach ($related_tips as $related):
                $colors = $related_colors[$idx % 3];
                $idx++;
            ?>
            <div class="related-card" onclick="location.href='detail_tip.php?id=<?php echo $related['id']; ?>'">
                <div class="related-image" style="background: linear-gradient(135deg, <?php echo $colors[0]; ?>, <?php echo $colors[1]; ?>);">
                    <?php echo $colors[2]; ?>
                </div>
                <div class="related-title"><?php echo htmlspecialchars($related['title']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

<?php
// --- 푸터 설정 ---
$page_js = '
    function likeTip(tipId) {
        // AJAX로 좋아요 처리
        fetch(\'like_tip.php\', {
            method: \'POST\',
            headers: {\'Content-Type\': \'application/x-www-form-urlencoded\'},
            body: \'tip_id=\' + tipId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(\'좋아요를 눌렀습니다!\');
                location.reload();
            } else {
                alert(data.message || \'오류가 발생했습니다.\');
            }
        })
        .catch(error => {
            alert(\'네트워크 오류가 발생했습니다.\');
        });
    }

    function shareTip() {
        const url = window.location.href;
        const title = \'' . addslashes($tip['title']) . '\';

        if (navigator.share) {
            navigator.share({
                title: title,
                url: url
            });
        } else {
            // 클립보드 복사
            navigator.clipboard.writeText(url).then(() => {
                alert(\'링크가 복사되었습니다!\');
            });
        }
    }
';

echo '<div style="height:70px;"></div>';
include_once __DIR__ . '/includes/bottom_nav.php';
include __DIR__ . '/includes/footer.php';

mysqli_close($connect);
?>
