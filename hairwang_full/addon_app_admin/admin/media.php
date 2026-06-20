<?php
/**
 * 미디어 파일 관리
 * @package AddonAppAdmin
 * @version 1.0.0
 */

include_once('./_common.php');

// 권한 체크 제거됨
// if (!addon_app_admin_check_permission()) {
//     addon_app_admin_permission_denied();
// }

// 미디어 삭제
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['media_id'])) {
    check_admin_token();

    $media_id = intval($_GET['media_id']);

    $sql = " SELECT * FROM {$addon_app_admin_table_media} WHERE id = {$media_id} ";
    $media = sql_fetch($sql);

    if ($media) {
        // 파일 삭제
        if (file_exists($media['file_path'])) {
            @unlink($media['file_path']);
        }

        // DB에서 삭제
        $sql = " DELETE FROM {$addon_app_admin_table_media} WHERE id = {$media_id} ";
        sql_query($sql);

        addon_app_admin_log('media_delete', $media['media_key'], '미디어 삭제: ' . $media['file_name']);
        alert('미디어가 삭제되었습니다.', './media.php');
    }
}

// 미디어 타입 필터
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';

// 레이아웃 헤더
$g5['title'] = '미디어 파일 관리';
include_once('./layout_header.php');

// 미디어 목록 가져오기
$where = " WHERE is_active = 1 ";
if ($filter_type !== 'all') {
    $where .= " AND media_type = '{$filter_type}' ";
}

$sql = " SELECT * FROM {$addon_app_admin_table_media} {$where} ORDER BY created_at DESC ";
$result = sql_query($sql);
$media_list = array();
while ($row = sql_fetch_array($result)) {
    $media_list[] = $row;
}

// 타입별 통계
$sql = " SELECT media_type, COUNT(*) as cnt FROM {$addon_app_admin_table_media} WHERE is_active = 1 GROUP BY media_type ";
$result = sql_query($sql);
$stats = array();
while ($row = sql_fetch_array($result)) {
    $stats[$row['media_type']] = $row['cnt'];
}

$total_count = array_sum($stats);

// 페이지 디버그 정보
$GLOBALS['page_debug'] = array(
    'page' => '미디어 파일 관리',
    'total_count' => $total_count,
    'stats_by_type' => $stats,
    'media_list_count' => count($media_list),
    'sql_query' => $sql
);

?>

<link rel="stylesheet" href="<?php echo ADDON_APP_ADMIN_URL; ?>/assets/css/admin.css">

<div class="addon-app-admin-wrap">

    <div class="addon-header">
        <div class="addon-container">
            <div class="addon-header-inner">
                <h1 class="addon-logo">
                    <i class="fa fa-photo"></i> 미디어 파일 관리
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
                업로드된 모든 미디어 파일을 관리합니다. 로고, 아이콘, 배너 등을 한 곳에서 확인할 수 있습니다.
            </p>
        </div>

        <!-- 통계 카드 -->
        <div class="addon-section addon-mt-30">
            <div class="stats-grid">
                <div class="stat-card <?php echo $filter_type === 'all' ? 'active' : ''; ?>">
                    <a href="?type=all">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <i class="fa fa-photo"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo number_format($total_count); ?></div>
                            <div class="stat-label">전체 미디어</div>
                        </div>
                    </a>
                </div>

                <div class="stat-card <?php echo $filter_type === 'logo_pc' || $filter_type === 'logo_mobile' ? 'active' : ''; ?>">
                    <a href="?type=logo_pc">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <i class="fa fa-image"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo number_format(isset($stats['logo_pc']) ? $stats['logo_pc'] : 0); ?></div>
                            <div class="stat-label">PC 로고</div>
                        </div>
                    </a>
                </div>

                <div class="stat-card <?php echo $filter_type === 'favicon' ? 'active' : ''; ?>">
                    <a href="?type=favicon">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <i class="fa fa-star"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo number_format(isset($stats['favicon']) ? $stats['favicon'] : 0); ?></div>
                            <div class="stat-label">파비콘</div>
                        </div>
                    </a>
                </div>

                <div class="stat-card <?php echo $filter_type === 'banner' ? 'active' : ''; ?>">
                    <a href="?type=banner">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                            <i class="fa fa-picture-o"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo number_format(isset($stats['banner']) ? $stats['banner'] : 0); ?></div>
                            <div class="stat-label">배너</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- 미디어 목록 -->
        <div class="addon-section addon-mt-30">
            <h2 class="addon-section-title">
                <i class="fa fa-th"></i> 미디어 목록
                <?php if ($filter_type !== 'all'): ?>
                    <span style="font-size: 14px; color: #999; font-weight: normal; margin-left: 10px;">
                        (<?php echo strtoupper($filter_type); ?> 필터링 중)
                    </span>
                <?php endif; ?>
            </h2>

            <?php if (empty($media_list)): ?>
                <div style="text-align: center; padding: 60px 20px; color: #999;">
                    <i class="fa fa-photo" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;"></i>
                    <h3>미디어 파일이 없습니다</h3>
                    <p>업로드된 미디어 파일이 없습니다.</p>
                </div>
            <?php else: ?>

                <div class="media-grid">
                    <?php foreach ($media_list as $media): ?>
                    <div class="media-card">
                        <div class="media-card-image">
                            <?php
                            $web_url = '';
                            if ($media['media_type'] === 'logo_pc' || $media['media_type'] === 'logo_mobile') {
                                $web_url = ADDON_APP_ADMIN_DATA_URL . '/uploads/logos/' . $media['file_name'];
                            } elseif ($media['media_type'] === 'favicon') {
                                $web_url = ADDON_APP_ADMIN_DATA_URL . '/uploads/icons/' . $media['file_name'];
                            } elseif ($media['media_type'] === 'banner') {
                                $web_url = ADDON_APP_ADMIN_DATA_URL . '/uploads/banners/' . $media['file_name'];
                            }
                            ?>
                            <?php if ($web_url && file_exists($media['file_path'])): ?>
                                <img src="<?php echo $web_url; ?>?v=<?php echo time(); ?>" alt="<?php echo htmlspecialchars($media['file_name']); ?>">
                            <?php else: ?>
                                <div class="media-no-image">
                                    <i class="fa fa-image"></i>
                                    <p>파일 없음</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="media-card-body">
                            <h3><?php echo htmlspecialchars($media['file_name']); ?></h3>
                            <div class="media-meta">
                                <span class="badge badge-<?php echo $media['media_type']; ?>"><?php echo strtoupper($media['media_type']); ?></span>
                                <p><i class="fa fa-expand"></i> <?php echo $media['width']; ?> x <?php echo $media['height']; ?>px</p>
                                <p><i class="fa fa-hdd-o"></i> <?php echo number_format($media['file_size'] / 1024, 1); ?> KB</p>
                                <p><i class="fa fa-clock-o"></i> <?php echo date('Y-m-d H:i', strtotime($media['created_at'])); ?></p>
                            </div>
                        </div>
                        <div class="media-card-actions">
                            <button type="button" class="addon-btn addon-btn-sm addon-btn-primary" onclick="viewImage('<?php echo $web_url; ?>', '<?php echo htmlspecialchars($media['file_name']); ?>')">
                                <i class="fa fa-search"></i> 보기
                            </button>
                            <a href="<?php echo $web_url; ?>" download="<?php echo $media['file_name']; ?>" class="addon-btn addon-btn-sm addon-btn-success">
                                <i class="fa fa-download"></i> 다운로드
                            </a>
                            <a href="?action=delete&media_id=<?php echo $media['id']; ?>&token=<?php echo get_admin_token(); ?>" class="addon-btn addon-btn-sm addon-btn-danger" onclick="return confirm('정말 삭제하시겠습니까?');">
                                <i class="fa fa-trash"></i> 삭제
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>
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

<!-- 이미지 뷰어 모달 -->
<div id="imageViewerModal" class="modal">
    <div class="modal-content" style="max-width: 90%; width: auto;">
        <div class="modal-header">
            <h2 id="viewerImageName"></h2>
            <span class="modal-close" onclick="closeImageViewer()">&times;</span>
        </div>
        <div style="padding: 20px; text-align: center;">
            <img id="viewerImage" src="" alt="" style="max-width: 100%; height: auto; border-radius: 8px;">
        </div>
    </div>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
}

.stat-card {
    background: white;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
}

.stat-card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-3px);
}

.stat-card.active {
    box-shadow: 0 4px 20px rgba(170, 32, 255, 0.3);
    border: 2px solid var(--primary-color);
}

.stat-card a {
    display: flex;
    align-items: center;
    padding: 20px;
    text-decoration: none;
    color: inherit;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.stat-icon i {
    font-size: 28px;
    color: white;
}

.stat-info {
    flex: 1;
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 13px;
    color: #666;
}

.media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.media-card {
    background: white;
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
}

.media-card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-2px);
}

.media-card-image {
    width: 100%;
    height: 200px;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.media-card-image img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.media-no-image {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #ddd;
}

.media-no-image i {
    font-size: 48px;
    margin-bottom: 10px;
}

.media-card-body {
    padding: 15px;
}

.media-card-body h3 {
    margin: 0 0 12px 0;
    font-size: 14px;
    color: #333;
    font-weight: 600;
    word-break: break-all;
}

.media-meta {
    font-size: 12px;
    color: #666;
}

.media-meta p {
    margin: 6px 0;
}

.media-meta i {
    width: 16px;
    margin-right: 6px;
    color: #999;
}

.media-card-actions {
    display: flex;
    gap: 6px;
    padding: 12px 15px;
    border-top: 1px solid #eee;
    background: #f9f9f9;
}

.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
    margin-bottom: 8px;
}

.badge-logo_pc {
    background: #007bff;
    color: white;
}

.badge-logo_mobile {
    background: #6f42c1;
    color: white;
}

.badge-favicon {
    background: #17a2b8;
    color: white;
}

.badge-banner {
    background: #fd7e14;
    color: white;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.8);
}

.modal-content {
    background-color: white;
    margin: 3% auto;
    border-radius: var(--border-radius);
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
    font-size: 18px;
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

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .media-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// 이미지 뷰어
function viewImage(url, name) {
    document.getElementById('viewerImageName').textContent = name;
    document.getElementById('viewerImage').src = url;
    document.getElementById('imageViewerModal').style.display = 'block';
}

// 이미지 뷰어 닫기
function closeImageViewer() {
    document.getElementById('imageViewerModal').style.display = 'none';
}

// 모달 외부 클릭 시 닫기
window.onclick = function(event) {
    const modal = document.getElementById('imageViewerModal');
    if (event.target === modal) {
        closeImageViewer();
    }
}
</script>

<?php
include_once('./layout_footer.php');
?>
