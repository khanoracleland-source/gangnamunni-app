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
if (empty($_SESSION['admin_points_csrf'])) {
    $_SESSION['admin_points_csrf'] = bin2hex(random_bytes(32));
}
$admin_points_csrf = $_SESSION['admin_points_csrf'];

// 포인트 원장(#포인트제도): 모든 증감은 ledger 를 단일 진입점으로 사용
require_once __DIR__ . '/includes/points_ledger.php';
try {
    points_ensure_schema($connect);
} catch (Throwable $e) {
    error_log('[admin_points] point schema unavailable: ' . $e->getMessage());
    http_response_code(503);
    require_once __DIR__ . '/includes/admin_header.php';
    ?>
    <div class="alert alert-danger" role="alert">
        <h5 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-1"></i> 포인트 DB 설정이 필요합니다</h5>
        <p class="mb-2">포인트 원장 스키마가 설치되지 않았거나 최신 버전이 아닙니다.</p>
        <p class="mb-0">
            배포 관리자에게 <code>sql/points_ledger.sql</code> 마이그레이션 실행을 요청한 뒤
            이 페이지를 새로고침해 주세요.
        </p>
    </div>
    <?php
    require_once __DIR__ . '/includes/admin_footer.php';
    exit;
}

// POST: 설정 저장 / 수동 지급·차감 / 데모
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['admin_csrf']) || !hash_equals($admin_points_csrf, (string)$_POST['admin_csrf'])) {
        http_response_code(403);
        exit('요청이 만료되었습니다. 페이지를 새로고침해주세요.');
    }
    $act = $_POST['action'] ?? '';

    // 포인트 지급 기준 설정 저장
    if ($act === 'save_settings') {
        points_save_settings($connect, [
            'enabled'              => isset($_POST['enabled']) ? '1' : '0',
            'signup_point'         => max(0, (int)($_POST['signup_point'] ?? 0)),
            'booking_percent'      => max(0, (float)($_POST['booking_percent'] ?? 0)),
            'review_default_point' => max(0, (int)($_POST['review_default_point'] ?? 0)),
            'expire_days'          => max(0, (int)($_POST['expire_days'] ?? 0)),
        ]);
        header('Location: admin_points.php?msg=settings'); exit;
    }

    // 데모 포인트 데이터 생성 (원장 경유)
    if ($act === 'generate_demo' && isDevMode($connect)) {
        $demo_members = [1, 3, 15, 16, 17]; // g5_member.mb_no
        $reasons_earn = ['회원가입 보너스','후기 작성 적립','예약 완료 적립','이벤트 참여 보너스','출석 체크 적립','추천인 보너스'];
        $reasons_use = ['쿠폰 사용','포인트 결제','이벤트 응모'];
        $cnt = 0;
        foreach ($demo_members as $mid) {
            for ($i = 0; $i < rand(3, 5); $i++) {
                $r = points_earn($connect, $mid, rand(1, 10) * 500, $reasons_earn[array_rand($reasons_earn)]);
                if (!empty($r['ok'])) $cnt++;
            }
            for ($i = 0; $i < rand(1, 2); $i++) {
                $r = points_use($connect, $mid, rand(1, 3) * 500, $reasons_use[array_rand($reasons_use)]);
                if (!empty($r['ok'])) $cnt++;
            }
        }
        header('Location: admin_points.php?msg=demo_created&cnt=' . $cnt); exit;
    }

    // 수동 지급/차감
    $member_in = trim($_POST['member_id'] ?? '');   // mb_id(문자열) 또는 mb_no(숫자) 허용
    $amount = intval($_POST['amount'] ?? 0);
    $reason = trim($_POST['reason'] ?? '관리자 수동');
    $type = ($_POST['type'] ?? 'earn') === 'use' ? 'admin_sub' : 'admin_add';
    if ($member_in !== '' && $amount > 0) {
        $mb_no = points_member_no($connect, $member_in);
        if ($mb_no <= 0) { header('Location: admin_points.php?msg=nomember'); exit; }
        // 차감(admin_sub)은 보유 잔액을 초과할 수 없다(음수 방지).
        if ($type === 'admin_sub' && $amount > points_balance($connect, $mb_no)) {
            header('Location: admin_points.php?msg=insufficient'); exit;
        }
        $r = points_txn($connect, $mb_no, $type, $amount, $reason, 'admin', null);
        header('Location: admin_points.php?msg=' . (!empty($r['ok']) ? 'ok' : 'fail')); exit;
    }
}

// 현재 설정 로드(설정 폼 표시용)
$pset = points_settings($connect);

// 최근 포인트 내역
$history = [];
$res = mysqli_query($connect, "SELECT * FROM gangnamunni_points ORDER BY created_at DESC LIMIT 100");
if ($res) while ($r = mysqli_fetch_assoc($res)) $history[] = $r;

// 회원별 잔액 TOP — 원장 스냅샷(회원별 마지막 거래 행의 balance) 사용
$top_users = [];
$res2 = mysqli_query($connect, "SELECT p.member_id, p.balance, c.tx_count
    FROM gangnamunni_points p
    JOIN (SELECT member_id, MAX(id) AS mid, COUNT(*) AS tx_count FROM gangnamunni_points GROUP BY member_id) c
      ON c.member_id = p.member_id AND c.mid = p.id
    ORDER BY p.balance DESC LIMIT 20");
if ($res2) while ($r = mysqli_fetch_assoc($res2)) $top_users[] = $r;

require_once __DIR__ . '/includes/admin_header.php';
?>
<?php if (isDevMode($connect)): ?>
<div class="dev-mode-bar mb-3 p-2 rounded" style="background:linear-gradient(135deg,#ff6b35,#f7931e);border:2px dashed #fff;">
<div class="d-flex align-items-center justify-content-between">
<span class="text-white fw-bold"><i class="bi bi-gear-fill me-1"></i> DEV MODE</span>
<form method="post" class="d-inline"><input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_points_csrf, ENT_QUOTES); ?>"><input type="hidden" name="action" value="generate_demo">
<button type="submit" class="btn btn-light btn-sm fw-bold"><i class="bi bi-lightning-fill text-warning me-1"></i>테스트 포인트 데이터 생성 (5명 x 적립/사용)</button></form>
</div></div>
<?php endif; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">포인트 관리</h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#grantModal"><i class="bi bi-plus-lg"></i> 포인트 지급/차감</button>
</div>
<?php if (!empty($_GET['msg'])):
    $msg_map = ['ok'=>'포인트가 처리되었습니다.','settings'=>'지급 기준 설정이 저장되었습니다.','demo_created'=>'데모 데이터가 생성되었습니다.','nomember'=>'해당 회원을 찾을 수 없습니다.','insufficient'=>'차감 금액이 보유 잔액을 초과합니다.','fail'=>'처리에 실패했습니다.'];
    $is_err = in_array($_GET['msg'], ['nomember','fail','insufficient'], true);
?><div class="alert <?php echo $is_err?'alert-danger':'alert-success'; ?> alert-dismissible fade show"><?php echo htmlspecialchars($msg_map[$_GET['msg']] ?? '처리 완료'); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- 포인트 지급 기준 설정 (#포인트제도) -->
<div class="card mb-4"><div class="card-body">
    <h6 class="mb-3"><i class="bi bi-sliders me-1"></i>포인트 지급 기준 설정</h6>
    <form method="post" class="row g-3 align-items-end">
        <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_points_csrf, ENT_QUOTES); ?>">
        <input type="hidden" name="action" value="save_settings">
        <div class="col-md-2">
            <label class="form-label">제도 사용</label>
            <div class="form-check form-switch mt-2">
                <input class="form-check-input" type="checkbox" name="enabled" value="1" id="pEnabled" <?php echo ((int)($pset['enabled'] ?? 0)===1)?'checked':''; ?>>
                <label class="form-check-label" for="pEnabled">활성화</label>
            </div>
        </div>
        <div class="col-md-2"><label class="form-label">가입 지급(P)</label><input type="number" min="0" name="signup_point" class="form-control" value="<?php echo (int)($pset['signup_point'] ?? 0); ?>"></div>
        <div class="col-md-2"><label class="form-label">후기 승인 기본(P)</label><input type="number" min="0" name="review_default_point" class="form-control" value="<?php echo (int)($pset['review_default_point'] ?? 0); ?>"></div>
        <div class="col-md-2"><label class="form-label">예약 적립(%)</label><input type="number" min="0" step="0.1" name="booking_percent" class="form-control" value="<?php echo htmlspecialchars($pset['booking_percent'] ?? '0'); ?>"></div>
        <div class="col-md-2"><label class="form-label">유효기간(일, 0=무기한)</label><input type="number" min="0" name="expire_days" class="form-control" value="<?php echo (int)($pset['expire_days'] ?? 0); ?>"></div>
        <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-1"></i>저장</button></div>
    </form>
</div></div>

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
            <?php
                $type_labels = ['earn'=>'적립','use'=>'사용','refund'=>'환불복구','expire'=>'만료','admin_add'=>'관리자적립','admin_sub'=>'관리자차감','earn_cancel'=>'적립취소','admin'=>'적립'];
                $is_cr = points_is_credit($h['type']);
            ?>
            <td><span class="badge <?php echo $is_cr?'bg-success':'bg-danger'; ?>"><?php echo $type_labels[$h['type']] ?? htmlspecialchars($h['type']); ?></span></td>
            <td><?php echo ($is_cr?'+':'-') . number_format(abs((int)$h['amount'])); ?>P</td>
            <td><?php echo number_format($h['balance']); ?>P</td>
            <td><?php echo htmlspecialchars($h['reason'] ?? ''); ?></td>
            <td><?php echo $h['created_at']; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div></div>
</div></div>

<div class="modal fade" id="grantModal"><div class="modal-dialog"><div class="modal-content"><form method="post">
<input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_points_csrf, ENT_QUOTES); ?>">
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
