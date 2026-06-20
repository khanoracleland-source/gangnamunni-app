<?php
session_start();
$admin_page_title = '회원 등급 관리';
$admin_current = 'membership';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (!$connect) die('DB 연결 실패');
loadThemeSettings($connect);
check_admin_login();
check_permission('membership');

// POST: 등급 수정
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_membership_levels SET name=?, min_points=?, benefits=?, discount_rate=?, color=? WHERE id=?");
        $name = $_POST['name'] ?? '';
        $pts = intval($_POST['min_points'] ?? 0);
        $ben = $_POST['benefits'] ?? '';
        $disc = floatval($_POST['discount_rate'] ?? 0);
        $color = $_POST['color'] ?? '#888';
        mysqli_stmt_bind_param($stmt, 'sisdsi', $name, $pts, $ben, $disc, $color, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header('Location: admin_membership.php?msg=ok'); exit;
    }
}

$levels = [];
$res = mysqli_query($connect, "SELECT * FROM gangnamunni_membership_levels ORDER BY sort_order");
if ($res) while ($r = mysqli_fetch_assoc($res)) $levels[] = $r;

// 등급별 회원 수
$member_counts = [];
$mc_res = mysqli_query($connect, "SELECT member_id, COALESCE(SUM(CASE WHEN type='earn' THEN amount ELSE -amount END),0) as bal FROM gangnamunni_points GROUP BY member_id");
if ($mc_res) {
    while ($m = mysqli_fetch_assoc($mc_res)) {
        $bal = (int)$m['bal'];
        foreach (array_reverse($levels) as $l) {
            if ($bal >= $l['min_points']) { $member_counts[$l['id']] = ($member_counts[$l['id']] ?? 0) + 1; break; }
        }
    }
}

require_once __DIR__ . '/includes/admin_header.php';
?>
<h4 class="mb-3">회원 등급 관리</h4>
<?php if (!empty($_GET['msg'])): ?><div class="alert alert-success alert-dismissible fade show">저장 완료<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="row">
<?php foreach ($levels as $l): ?>
<div class="col-md-6 mb-3">
    <div class="card">
        <div class="card-header" style="background:<?php echo $l['color']; ?>20;border-left:4px solid <?php echo $l['color']; ?>;">
            <strong><i class="bi <?php echo $l['icon']; ?>" style="color:<?php echo $l['color']; ?>;"></i> <?php echo htmlspecialchars($l['name']); ?></strong>
            <span class="badge bg-secondary float-end"><?php echo $member_counts[$l['id']] ?? 0; ?>명</span>
        </div>
        <div class="card-body">
            <form method="post"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?php echo $l['id']; ?>">
            <div class="mb-2"><label class="form-label" style="font-size:12px;">등급명</label><input type="text" name="name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($l['name']); ?>"></div>
            <div class="mb-2"><label class="form-label" style="font-size:12px;">필요 포인트</label><input type="number" name="min_points" class="form-control form-control-sm" value="<?php echo $l['min_points']; ?>"></div>
            <div class="mb-2"><label class="form-label" style="font-size:12px;">할인율 (%)</label><input type="number" name="discount_rate" class="form-control form-control-sm" step="0.1" value="<?php echo $l['discount_rate']; ?>"></div>
            <div class="mb-2"><label class="form-label" style="font-size:12px;">혜택 설명</label><textarea name="benefits" class="form-control form-control-sm" rows="2"><?php echo htmlspecialchars($l['benefits'] ?? ''); ?></textarea></div>
            <div class="mb-2"><label class="form-label" style="font-size:12px;">색상</label><input type="color" name="color" value="<?php echo $l['color']; ?>" class="form-control form-control-color form-control-sm"></div>
            <button type="submit" class="btn btn-primary btn-sm w-100">저장</button>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
