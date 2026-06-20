<?php
session_start();
$admin_page_title = '포인트 관리';
$admin_current = 'points';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/dev_mode_helper.php';
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (!$connect) die('DB 연결 실패');
loadThemeSettings($connect);
require_admin_login();
require_permission('points');

// POST: 포인트 수동 지급/차감
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';

    // 데모 포인트 데이터 생성
    if ($act === 'generate_demo' && isDevMode($connect)) {
        // g5_member의 mb_no (int)
        $demo_members = [1, 3, 15, 16, 17];
        $reasons_earn = ['회원가입 보너스','후기 작성 적립','예약 완료 적립','이벤트 참여 보너스','출석 체크 적립','추천인 보너스'];
        $reasons_use = ['쿠폰 사용','포인트 결제','이벤트 응모'];
        $cnt = 0;
        foreach ($demo_members as $mid) {
            $balance = 0;
            // 적립 3~5건
            for ($i = 0; $i < rand(3, 5); $i++) {
                $amt = rand(1, 10) * 500;
                $balance += $amt;
                $reason_t = $reasons_earn[array_rand($reasons_earn)];
                $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_points (member_id,type,amount,balance,reason) VALUES (?,'earn',?,?,?)");
                mysqli_stmt_bind_param($stmt, 'iiis', $mid, $amt, $balance, $reason_t);
                if (mysqli_stmt_execute($stmt)) $cnt++;
                mysqli_stmt_close($stmt);
            }
            // 사용 1~2건
            for ($i = 0; $i < rand(1, 2); $i++) {
                $amt = rand(1, 3) * 500;
                $balance -= $amt;
                if ($balance < 0) $balance = 0;
                $reason_t = $reasons_use[array_rand($reasons_use)];
                $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_points (member_id,type,amount,balance,reason) VALUES (?,'use',?,?,?)");
                mysqli_stmt_bind_param($stmt, 'iiis', $mid, $amt, $balance, $reason_t);
                if (mysqli_stmt_execute($stmt)) $cnt++;
                mysqli_stmt_close($stmt);
            }
        }
        header('Location: admin_points.php?msg=demo_created&cnt=' . $cnt); exit;
    }

    $member_id = trim($_POST['member_id'] ?? '');
    $amount = intval($_POST['amount'] ?? 0);
    $reason = trim($_POST['reason'] ?? '관리자 수동');
    $type = $_POST['type'] ?? 'earn';
    if ($member_id && $amount > 0) {
        // 현재 잔액 조회
        $bal_res = mysqli_query($connect, "SELECT COALESCE(SUM(CASE WHEN type='earn' THEN amount ELSE -amount END),0) as bal FROM gangnamunni_points WHERE member_id='".mysqli_real_escape_string($connect,$member_id)."'");
        $balance = mysqli_fetch_assoc($bal_res)['bal'] ?? 0;
        $new_bal = $type === 'earn' ? $balance + $amount : $balance - $amount;
        $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_points (member_id,type,amount,balance,reason,reference) VALUES (?,?,?,?,?,?)");
        $ref = 'admin_manual';
        mysqli_stmt_bind_param($stmt, 'ssiiss', $member_id, $type, $amount, $new_bal, $reason, $ref);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header('Location: admin_points.php?msg=ok'); exit;
    }
}

// 최근 포인트 내역
$history = [];
$res = mysqli_query($connect, "SELECT * FROM gangnamunni_points ORDER BY created_at DESC LIMIT 100");
if ($res) while ($r = mysqli_fetch_assoc($res)) $history[] = $r;

// 회원별 잔액 TOP
$top_users = [];
$res2 = mysqli_query($connect, "SELECT member_id, SUM(CASE WHEN type='earn' THEN amount ELSE -amount END) as balance, COUNT(*) as tx_count FROM gangnamunni_points GROUP BY member_id ORDER BY balance DESC LIMIT 20");
if ($res2) while ($r = mysqli_fetch_assoc($res2)) $top_users[] = $r;

require_once __DIR__ . '/includes/admin_header.php';
?>
<?php if (isDevMode($connect)): ?>
<div class="dev-mode-bar mb-3 p-2 rounded" style="background:linear-gradient(135deg,#ff6b35,#f7931e);border:2px dashed #fff;">
<div class="d-flex align-items-center justify-content-between">
<span class="text-white fw-bold"><i class="bi bi-gear-fill me-1"></i> DEV MODE</span>
<form method="post" class="d-inline"><input type="hidden" name="action" value="generate_demo">
<button type="submit" class="btn btn-light btn-sm fw-bold"><i class="bi bi-lightning-fill text-warning me-1"></i>테스트 포인트 데이터 생성 (5명 x 적립/사용)</button></form>
</div></div>
<?php endif; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">포인트 관리</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#grantModal"><i class="bi bi-plus-lg"></i> 포인트 지급/차감</button>
</div>
<?php if (!empty($_GET['msg'])): ?><div class="alert alert-success alert-dismissible fade show">처리 완료<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row mb-4">
<div class="col-md-4">
    <div class="card"><div class="card-body text-center">
        <div class="text-muted mb-1">회원별 잔액 TOP</div>
        <table class="table table-sm mt-2" style="font-size:13px;"><thead><tr><th>회원</th><th>잔액</th><th>거래</th></tr></thead><tbody>
        <?php foreach (array_slice($top_users,0,10) as $u): ?>
        <tr><td><?php echo htmlspecialchars($u['member_id']); ?></td><td class="text-primary fw-bold"><?php echo number_format($u['balance']); ?>P</td><td><?php echo $u['tx_count']; ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
    </div></div>
</div>
<div class="col-md-8">
    <div class="card"><div class="card-body">
        <h6>최근 포인트 내역</h6>
        <div class="table-responsive"><table class="table table-sm table-hover" style="font-size:13px;"><thead><tr><th>ID</th><th>회원</th><th>유형</th><th>금액</th><th>잔액</th><th>사유</th><th>일시</th></tr></thead><tbody>
        <?php foreach ($history as $h): ?>
        <tr>
            <td><?php echo $h['id']; ?></td>
            <td><?php echo htmlspecialchars($h['member_id']); ?></td>
            <td><span class="badge <?php echo $h['type']==='earn'?'bg-success':'bg-danger'; ?>"><?php echo $h['type']==='earn'?'적립':'사용'; ?></span></td>
            <td><?php echo number_format($h['amount']); ?>P</td>
            <td><?php echo number_format($h['balance']); ?>P</td>
            <td><?php echo htmlspecialchars($h['reason'] ?? ''); ?></td>
            <td><?php echo $h['created_at']; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div></div>
</div></div>

<div class="modal fade" id="grantModal"><div class="modal-dialog"><div class="modal-content"><form method="post">
<div class="modal-header"><h5 class="modal-title">포인트 지급/차감</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label class="form-label">회원 ID *</label><input type="text" name="member_id" class="form-control" required></div>
    <div class="row mb-3"><div class="col-6"><label class="form-label">유형</label><select name="type" class="form-select"><option value="earn">적립</option><option value="use">차감</option></select></div>
    <div class="col-6"><label class="form-label">금액 *</label><input type="number" name="amount" class="form-control" min="1" required></div></div>
    <div class="mb-3"><label class="form-label">사유</label><input type="text" name="reason" class="form-control" value="관리자 수동"></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button><button type="submit" class="btn btn-primary">실행</button></div>
</form></div></div></div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
