<?php
/**
 * 응모 관리 / 당첨자 추첨 (admin_event_entries.php)
 */
session_start();

$admin_page_title = '응모 관리';
$admin_current = 'event_entries';

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/hospital_filter.php';
require_admin_login();
require_permission('event_entries');
$event_hospital_ids = get_hospital_filter_ids($connect);
$event_hospital_in = $event_hospital_ids === null ? null : (empty($event_hospital_ids) ? '0' : implode(',', array_map('intval', $event_hospital_ids)));
$event_scope = $event_hospital_in === null ? '' : " AND ev.hospital_id IN ({$event_hospital_in})";

// ===== Action: 당첨 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'draw_winner') {
        $event_id = intval($_POST['event_id'] ?? 0);
        $count = intval($_POST['winner_count'] ?? 1);
        // 미당첨 응모자 중 랜덤 추첨
        $r = mysqli_query($connect, "SELECT e.id FROM gangnamunni_event_entries e INNER JOIN gangnamunni_events ev ON ev.id=e.event_id WHERE e.event_id=" . $event_id . " AND (e.is_winner IS NULL OR e.is_winner=0){$event_scope} ORDER BY RAND() LIMIT " . $count);
        $drawn = 0;
        if ($r) while ($row = mysqli_fetch_assoc($r)) {
            mysqli_query($connect, "UPDATE gangnamunni_event_entries SET is_winner=1, status='won' WHERE id=" . $row['id']);
            $drawn++;
        }
        $msg = $drawn . '명 당첨 처리되었습니다.';
        $msg_type = 'success';
    } elseif ($action === 'update_status') {
        $id = intval($_POST['id'] ?? 0);
        $status = mysqli_real_escape_string($connect, $_POST['status'] ?? '');
        if ($id > 0 && in_array($status, ['pending','won','claimed','rejected'])) {
            mysqli_query($connect, "UPDATE gangnamunni_event_entries e INNER JOIN gangnamunni_events ev ON ev.id=e.event_id SET e.status='" . $status . "', e.is_winner=" . ($status === 'won' ? 1 : 0) . " WHERE e.id=" . $id . $event_scope);
            $msg = '상태가 변경되었습니다.';
            $msg_type = 'success';
        }
    }
}

// ===== Filters =====
$event_id = intval($_GET['event_id'] ?? 0);
$filter_winner = $_GET['winner'] ?? '';
$search = $_GET['q'] ?? '';

$where = "1=1";
$where .= $event_scope;
if ($event_id) $where .= " AND e.event_id=" . $event_id;
if ($filter_winner === '1') $where .= " AND e.is_winner=1";
if ($filter_winner === '0') $where .= " AND (e.is_winner IS NULL OR e.is_winner=0)";
if ($search) $where .= " AND (e.user_name LIKE '%" . mysqli_real_escape_string($connect, $search) . "%' OR e.user_phone LIKE '%" . mysqli_real_escape_string($connect, $search) . "%')";

$entries = [];
$r = mysqli_query($connect, "SELECT e.*, ev.title as event_title FROM gangnamunni_event_entries e LEFT JOIN gangnamunni_events ev ON e.event_id=ev.id WHERE " . $where . " ORDER BY e.created_at DESC LIMIT 100");
if ($r) while ($row = mysqli_fetch_assoc($r)) $entries[] = $row;

// Events list for filter
$events = [];
$er_scope = $event_hospital_in === null ? '' : " AND hospital_id IN ({$event_hospital_in})";
$er = mysqli_query($connect, "SELECT id, title FROM gangnamunni_events WHERE raffle_end_date IS NOT NULL{$er_scope} ORDER BY created_at DESC LIMIT 50");
if ($er) while ($ev = mysqli_fetch_assoc($er)) $events[] = $ev;

$total_entries = 0;
$total_winners = 0;
$tc_where = $event_id ? "e.event_id={$event_id}" : '1=1';
$tc = mysqli_query($connect, "SELECT COUNT(*) as cnt, SUM(CASE WHEN e.is_winner=1 THEN 1 ELSE 0 END) as winners FROM gangnamunni_event_entries e INNER JOIN gangnamunni_events ev ON ev.id=e.event_id WHERE {$tc_where}{$event_scope}");
if ($tc) { $t = mysqli_fetch_assoc($tc); $total_entries = $t['cnt']; $total_winners = $t['winners']; }

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (isset($connect) && $connect) loadThemeSettings($connect);
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <?php if (!empty($msg)): ?>
    <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show"><?php echo $msg; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="bi bi-trophy me-2"></i>응모 관리 / 당첨 추첨</h4>
        <span class="text-muted">총 <?php echo number_format($total_entries); ?>건 / 당첨 <?php echo number_format($total_winners); ?>명</span>
    </div>

    <!-- 추첨 카드 -->
    <?php if ($event_id && count($entries) > 0): ?>
    <div class="card border-warning shadow-sm mb-4">
        <div class="card-body">
            <h6 class="card-title"><i class="bi bi-dice-5 me-1 text-warning"></i>랜덤 당첨 추첨</h6>
            <form method="post" class="row g-2 align-items-end">
                <input type="hidden" name="action" value="draw_winner">
                <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                <div class="col-auto">
                    <label class="form-label mb-0 small">당첨자 수</label>
                    <input type="number" name="winner_count" class="form-control form-control-sm" value="1" min="1" max="<?php echo count($entries); ?>" style="width:100px;">
                </div>
                <div class="col-auto">
                    <button class="btn btn-warning btn-sm"><i class="bi bi-shuffle me-1"></i>추첨 실행</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- 필터 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <select name="event_id" class="form-select form-select-sm">
                        <option value="">전체 이벤트</option>
                        <?php foreach ($events as $ev): ?>
                        <option value="<?php echo $ev['id']; ?>" <?php echo $event_id == $ev['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($ev['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="winner" class="form-select form-select-sm">
                        <option value="">전체</option>
                        <option value="1" <?php echo $filter_winner === '1' ? 'selected' : ''; ?>>당첨자만</option>
                        <option value="0" <?php echo $filter_winner === '0' ? 'selected' : ''; ?>>미당첨</option>
                    </select>
                </div>
                <div class="col-md-3"><input type="text" name="q" class="form-control form-control-sm" placeholder="이름/전화번호" value="<?php echo htmlspecialchars($search); ?>"></div>
                <div class="col-md-2"><button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-search"></i></button></div>
            </form>
        </div>
    </div>

    <!-- 목록 -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>#</th><th>이벤트</th><th>응모자</th><th>전화번호</th><th>이메일</th><th>당첨</th><th>상태</th><th>응모일</th><th>관리</th></tr></thead>
                <tbody>
                    <?php if (empty($entries)): ?>
                    <tr><td colspan="9" class="text-center py-5 text-muted"><i class="bi bi-inbox" style="font-size:2rem;"></i><br>응모 내역이 없습니다.</td></tr>
                    <?php else: foreach ($entries as $e): ?>
                    <tr<?php echo ($e['is_winner'] ?? 0) ? ' class="table-warning"' : ''; ?>>
                        <td><?php echo $e['id']; ?></td>
                        <td><small><?php echo htmlspecialchars($e['event_title'] ?? ''); ?></small></td>
                        <td><strong><?php echo htmlspecialchars($e['user_name'] ?? ''); ?></strong></td>
                        <td><?php echo htmlspecialchars($e['user_phone'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($e['user_email'] ?? ''); ?></td>
                        <td><?php echo ($e['is_winner'] ?? 0) ? '<i class="bi bi-trophy-fill text-warning"></i>' : '-'; ?></td>
                        <td>
                            <?php
                            $sb = ['pending'=>'secondary','won'=>'warning','claimed'=>'success','rejected'=>'danger'];
                            $sl = ['pending'=>'대기','won'=>'당첨','claimed'=>'수령','rejected'=>'탈락'];
                            $es = $e['status'] ?? 'pending';
                            ?>
                            <span class="badge bg-<?php echo $sb[$es] ?? 'secondary'; ?>"><?php echo $sl[$es] ?? $es; ?></span>
                        </td>
                        <td><small><?php echo substr($e['created_at'] ?? '', 0, 16); ?></small></td>
                        <td>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="id" value="<?php echo $e['id']; ?>">
                                <select name="status" onchange="this.form.submit()" class="form-select form-select-sm" style="width:80px;">
                                    <?php foreach (['pending'=>'대기','won'=>'당첨','claimed'=>'수령','rejected'=>'탈락'] as $k => $v): ?>
                                    <option value="<?php echo $k; ?>" <?php echo $es === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
