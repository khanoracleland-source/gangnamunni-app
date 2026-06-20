<?php
/**
 * 활동 로그 관리
 * @package AddonAppAdmin
 * @version 1.0.0
 */

include_once('./_common.php');

// 권한 체크 제거됨
// if (!addon_app_admin_check_permission()) {
//     addon_app_admin_permission_denied();
// }

// 로그 삭제
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['log_id'])) {
    check_admin_token();

    $log_id = intval($_GET['log_id']);

    $sql = " DELETE FROM {$addon_app_admin_table_logs} WHERE id = {$log_id} ";
    sql_query($sql);

    alert('로그가 삭제되었습니다.', './logs.php');
}

// 전체 로그 삭제
if (isset($_GET['action']) && $_GET['action'] === 'delete_all') {
    check_admin_token();

    $sql = " TRUNCATE TABLE {$addon_app_admin_table_logs} ";
    sql_query($sql);

    alert('모든 로그가 삭제되었습니다.', './logs.php');
}

// 필터 및 검색
$filter_action = isset($_GET['filter_action']) ? $_GET['filter_action'] : 'all';
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$items_per_page = 30;
$offset = ($page - 1) * $items_per_page;

// 레이아웃 헤더
$g5['title'] = '활동 로그';
include_once('./layout_header.php');

// WHERE 조건
$where = " WHERE 1=1 ";

if ($filter_action !== 'all') {
    $where .= " AND log_action = '{$filter_action}' ";
}

if ($search_keyword) {
    $where .= " AND (log_message LIKE '%" . sql_real_escape_string($search_keyword) . "%'
                     OR target_key LIKE '%" . sql_real_escape_string($search_keyword) . "%') ";
}

// 전체 개수
$sql = " SELECT COUNT(*) as cnt FROM {$addon_app_admin_table_logs} {$where} ";
$row = sql_fetch($sql);
$total_count = $row['cnt'];
$total_pages = ceil($total_count / $items_per_page);

// 로그 목록
$sql = " SELECT * FROM {$addon_app_admin_table_logs} {$where} ORDER BY created_at DESC LIMIT {$offset}, {$items_per_page} ";
$result = sql_query($sql);
$logs = array();
while ($row = sql_fetch_array($result)) {
    $logs[] = $row;
}

// 액션별 통계
$sql = " SELECT log_action, COUNT(*) as cnt FROM {$addon_app_admin_table_logs} GROUP BY log_action ORDER BY cnt DESC ";
$result = sql_query($sql);
$action_stats = array();
while ($row = sql_fetch_array($result)) {
    $action_stats[$row['log_action']] = $row['cnt'];
}

// 페이지 디버그 정보
$GLOBALS['page_debug'] = array(
    'page' => '활동 로그',
    'filter' => array(
        'action' => $filter_action,
        'keyword' => $search_keyword
    ),
    'pagination' => array(
        'page' => $page,
        'total_count' => $total_count,
        'total_pages' => $total_pages,
        'items_per_page' => $items_per_page
    ),
    'logs_count' => count($logs),
    'action_stats' => $action_stats
);

?>

<link rel="stylesheet" href="<?php echo ADDON_APP_ADMIN_URL; ?>/assets/css/admin.css">

<div class="addon-app-admin-wrap">

    <div class="addon-header">
        <div class="addon-container">
            <div class="addon-header-inner">
                <h1 class="addon-logo">
                    <i class="fa fa-history"></i> 활동 로그
                </h1>
                <div class="addon-header-right">
                    <a href="?action=delete_all&token=<?php echo get_admin_token(); ?>" class="addon-btn addon-btn-sm addon-btn-danger" onclick="return confirm('모든 로그를 삭제하시겠습니까?');">
                        <i class="fa fa-trash"></i> 전체 삭제
                    </a>
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
                시스템의 모든 활동을 기록합니다. 보안 및 추적 목적으로 사용됩니다.
            </p>
        </div>

        <!-- 통계 -->
        <div class="addon-section addon-mt-30">
            <div class="stats-grid">
                <div class="stat-card <?php echo $filter_action === 'all' ? 'active' : ''; ?>">
                    <a href="?filter_action=all">
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <i class="fa fa-list"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo number_format($total_count); ?></div>
                            <div class="stat-label">전체 로그</div>
                        </div>
                    </a>
                </div>

                <?php
                $action_icons = array(
                    'board_update' => array('icon' => 'th-large', 'label' => '게시판 업데이트', 'color' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)'),
                    'header_update' => array('icon' => 'header', 'label' => '헤더 업데이트', 'color' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)'),
                    'footer_update' => array('icon' => 'mail-footer', 'label' => '푸터 업데이트', 'color' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)'),
                    'app_upload' => array('icon' => 'mobile', 'label' => '앱 업로드', 'color' => 'linear-gradient(135deg, #30cfd0 0%, #330867 100%)'),
                    'media_upload' => array('icon' => 'photo', 'label' => '미디어 업로드', 'color' => 'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)'),
                );

                foreach ($action_icons as $action => $info):
                    if (isset($action_stats[$action])):
                ?>
                <div class="stat-card <?php echo $filter_action === $action ? 'active' : ''; ?>">
                    <a href="?filter_action=<?php echo $action; ?>">
                        <div class="stat-icon" style="background: <?php echo $info['color']; ?>;">
                            <i class="fa fa-<?php echo $info['icon']; ?>"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo number_format($action_stats[$action]); ?></div>
                            <div class="stat-label"><?php echo $info['label']; ?></div>
                        </div>
                    </a>
                </div>
                <?php
                    endif;
                endforeach;
                ?>
            </div>
        </div>

        <!-- 검색 -->
        <div class="addon-section addon-mt-30">
            <form method="get" action="">
                <?php if ($filter_action !== 'all'): ?>
                    <input type="hidden" name="filter_action" value="<?php echo $filter_action; ?>">
                <?php endif; ?>
                <div class="search-box">
                    <input type="text" name="search" class="search-input" value="<?php echo htmlspecialchars($search_keyword); ?>" placeholder="로그 메시지 또는 대상 키워드로 검색...">
                    <button type="submit" class="addon-btn addon-btn-primary">
                        <i class="fa fa-search"></i> 검색
                    </button>
                    <?php if ($search_keyword): ?>
                        <a href="?<?php echo $filter_action !== 'all' ? 'filter_action=' . $filter_action : ''; ?>" class="addon-btn addon-btn-secondary">
                            <i class="fa fa-times"></i> 초기화
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- 로그 목록 -->
        <div class="addon-section addon-mt-30">
            <h2 class="addon-section-title">
                <i class="fa fa-list"></i> 로그 목록
                <?php if ($total_count > 0): ?>
                    <span style="font-size: 14px; color: #999; font-weight: normal; margin-left: 10px;">
                        (총 <?php echo number_format($total_count); ?>개)
                    </span>
                <?php endif; ?>
            </h2>

            <?php if (empty($logs)): ?>
                <div style="text-align: center; padding: 60px 20px; color: #999;">
                    <i class="fa fa-inbox" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;"></i>
                    <h3>로그가 없습니다</h3>
                    <p>검색 조건과 일치하는 로그가 없습니다.</p>
                </div>
            <?php else: ?>

                <div class="logs-table-container">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th width="50">ID</th>
                                <th width="120">액션</th>
                                <th width="150">대상</th>
                                <th>메시지</th>
                                <th width="120">사용자</th>
                                <th width="160">일시</th>
                                <th width="80">작업</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo $log['id']; ?></td>
                                <td>
                                    <span class="action-badge action-<?php echo $log['log_action']; ?>">
                                        <?php echo htmlspecialchars($log['log_action']); ?>
                                    </span>
                                </td>
                                <td class="text-truncate"><?php echo htmlspecialchars($log['target_key']); ?></td>
                                <td class="text-truncate"><?php echo htmlspecialchars($log['log_message']); ?></td>
                                <td><?php echo htmlspecialchars($log['user_id']); ?></td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                <td>
                                    <a href="?action=delete&log_id=<?php echo $log['id']; ?>&token=<?php echo get_admin_token(); ?>" class="btn-delete" onclick="return confirm('이 로그를 삭제하시겠습니까?');" title="삭제">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- 페이징 -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php
                    $query_string = '';
                    if ($filter_action !== 'all') {
                        $query_string .= '&filter_action=' . $filter_action;
                    }
                    if ($search_keyword) {
                        $query_string .= '&search=' . urlencode($search_keyword);
                    }

                    // 이전 페이지
                    if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1) . $query_string; ?>" class="pagination-btn">
                            <i class="fa fa-angle-left"></i> 이전
                        </a>
                    <?php endif; ?>

                    <!-- 페이지 번호 -->
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <a href="?page=<?php echo $i . $query_string; ?>" class="pagination-number <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <!-- 다음 페이지 -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo ($page + 1) . $query_string; ?>" class="pagination-btn">
                            다음 <i class="fa fa-angle-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

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

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
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
    padding: 15px;
    text-decoration: none;
    color: inherit;
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
}

.stat-icon i {
    font-size: 22px;
    color: white;
}

.stat-info {
    flex: 1;
}

.stat-value {
    font-size: 22px;
    font-weight: bold;
    color: #333;
    margin-bottom: 3px;
}

.stat-label {
    font-size: 12px;
    color: #666;
}

.search-box {
    display: flex;
    gap: 10px;
    align-items: center;
}

.search-input {
    flex: 1;
    padding: 12px 16px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s;
}

.search-input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(170, 32, 255, 0.1);
}

.logs-table-container {
    overflow-x: auto;
}

.logs-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.logs-table thead {
    background: #f8f9fa;
}

.logs-table th {
    padding: 12px 15px;
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    color: #333;
    border-bottom: 2px solid #e0e0e0;
}

.logs-table td {
    padding: 12px 15px;
    font-size: 13px;
    color: #666;
    border-bottom: 1px solid #f0f0f0;
}

.logs-table tbody tr:hover {
    background: #f9f9f9;
}

.text-truncate {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.action-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 500;
    background: #e0e0e0;
    color: #666;
}

.action-badge.action-board_update {
    background: #e7f3ff;
    color: #0066cc;
}

.action-badge.action-header_update {
    background: #fff3e0;
    color: #e65100;
}

.action-badge.action-footer_update {
    background: #f3e5f5;
    color: #7b1fa2;
}

.action-badge.action-app_upload {
    background: #e8f5e9;
    color: #2e7d32;
}

.action-badge.action-media_upload {
    background: #fce4ec;
    color: #c2185b;
}

.btn-delete {
    color: #dc3545;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-delete:hover {
    color: #bd2130;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
    margin-top: 30px;
}

.pagination-btn,
.pagination-number {
    padding: 8px 16px;
    border: 1px solid #ddd;
    border-radius: 6px;
    text-decoration: none;
    color: #666;
    transition: all 0.3s;
    font-size: 14px;
}

.pagination-btn:hover,
.pagination-number:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
    background: #f5f0ff;
}

.pagination-number.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }

    .search-box {
        flex-direction: column;
    }

    .logs-table {
        font-size: 12px;
    }

    .logs-table th,
    .logs-table td {
        padding: 8px 10px;
    }
}
</style>

<?php
include_once('./layout_footer.php');
?>
