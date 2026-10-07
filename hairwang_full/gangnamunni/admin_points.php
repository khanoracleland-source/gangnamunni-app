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

    // 포인트 지급 일시 중지(includes/points_pause.php): 수동 지급/차감·데모 생성 차단
    require_once __DIR__ . '/includes/points_pause.php';
    if (POINTS_ADMIN_GRANT_PAUSED && !in_array($act, ['save_settings', 'save_v2'], true)) {
        header('Location: admin_points.php?msg=paused'); exit;
    }

    // 포인트 지급 기준 설정 저장
    if ($act === 'save_settings') {
        points_save_settings($connect, [
            'enabled'              => isset($_POST['enabled']) ? '1' : '0',
            'signup_enabled'       => isset($_POST['signup_enabled']) ? '1' : '0',
            'review_enabled'       => isset($_POST['review_enabled']) ? '1' : '0',
            'booking_enabled'      => isset($_POST['booking_enabled']) ? '1' : '0',
            'signup_point'         => max(0, (int)($_POST['signup_point'] ?? 0)),
            'booking_percent'      => max(0, (float)($_POST['booking_percent'] ?? 0)),
            'review_default_point' => max(0, (int)($_POST['review_default_point'] ?? 0)),
            'expire_days'          => max(0, (int)($_POST['expire_days'] ?? 0)),
        ]);
        header('Location: admin_points.php?msg=settings'); exit;
    }

    // 포인트 제도 v2 설정 저장
    if ($act === 'save_v2' && points_v2_schema_ready($connect)) {
        $sw = ['v2_policy_enabled', 'v2_expire_job_enabled', 'v2_point_use_enabled', 'v2_progress_enabled', 'v2_first_review_enabled'];
        $nums = ['v2_expire_days', 'v2_monthly_cap', 'v2_review_photo_point', 'v2_review_receipt_point', 'v2_review_first_point',
            'v2_first_yearly_cap', 'v2_progress_procedure_point', 'v2_progress_surgery_point', 'v2_progress_procedure_entries',
            'v2_progress_surgery_entries', 'v2_progress_resubmit_days', 'v2_pay_tier1_under', 'v2_pay_tier1_point',
            'v2_pay_tier2_under', 'v2_pay_tier2_point', 'v2_pay_tier3_point', 'v2_point_use_min_pay', 'v2_hold_ttl_minutes'];
        $pairs = [];
        foreach ($sw as $k) $pairs[$k] = isset($_POST[$k]) ? '1' : '0';
        foreach ($nums as $k) if (isset($_POST[$k])) $pairs[$k] = (string)max(0, (int)$_POST[$k]);
        $hol = array_filter(array_map('trim', explode(',', (string)($_POST['v2_holidays'] ?? ''))), fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d));
        $pairs['v2_holidays'] = implode(',', $hol);
        // 소멸·결제 사용은 lot 재구성(lots_ready=1) 전에는 켤 수 없다
        if ((int)points_setting($connect, 'lots_ready', '0') !== 1) { $pairs['v2_expire_job_enabled'] = '0'; $pairs['v2_point_use_enabled'] = '0'; }
        points_save_settings($connect, $pairs);
        header('Location: admin_points.php?msg=settings#v2'); exit;
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
    <?php require_once __DIR__ . '/includes/points_pause.php'; if (POINTS_ADMIN_GRANT_PAUSED): ?>
    <span class="badge bg-secondary"><i class="bi bi-pause-circle"></i> 포인트 지급 일시 중지</span>
    <?php else: ?>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#grantModal"><i class="bi bi-plus-lg"></i> 포인트 지급/차감</button>
    <?php endif; ?>
</div>
<?php if (!empty($_GET['msg'])):
    $msg_map = ['paused'=>'포인트 지급이 일시 중지되어 있습니다.','ok'=>'포인트가 처리되었습니다.','settings'=>'지급 기준 설정이 저장되었습니다.','demo_created'=>'데모 데이터가 생성되었습니다.','nomember'=>'해당 회원을 찾을 수 없습니다.','insufficient'=>'차감 금액이 보유 잔액을 초과합니다.','fail'=>'처리에 실패했습니다.'];
    $is_err = in_array($_GET['msg'], ['nomember','fail','insufficient','paused'], true);
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
        <div class="col-md-2">
            <label class="form-label d-flex justify-content-between align-items-center mb-1">
                <span>가입 지급(P)</span>
                <span class="form-check form-switch m-0" title="가입 적립 사용">
                    <input class="form-check-input" type="checkbox" name="signup_enabled" value="1" <?php echo ((int)($pset['signup_enabled'] ?? 1)===1)?'checked':''; ?>>
                </span>
            </label>
            <input type="number" min="0" name="signup_point" class="form-control" value="<?php echo (int)($pset['signup_point'] ?? 0); ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label d-flex justify-content-between align-items-center mb-1">
                <span>후기 승인 기본(P)</span>
                <span class="form-check form-switch m-0" title="후기 적립 사용">
                    <input class="form-check-input" type="checkbox" name="review_enabled" value="1" <?php echo ((int)($pset['review_enabled'] ?? 1)===1)?'checked':''; ?>>
                </span>
            </label>
            <input type="number" min="0" name="review_default_point" class="form-control" value="<?php echo (int)($pset['review_default_point'] ?? 0); ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label d-flex justify-content-between align-items-center mb-1">
                <span>예약 적립(%)</span>
                <span class="form-check form-switch m-0" title="예약 적립 사용">
                    <input class="form-check-input" type="checkbox" name="booking_enabled" value="1" <?php echo ((int)($pset['booking_enabled'] ?? 0)===1)?'checked':''; ?>>
                </span>
            </label>
            <input type="number" min="0" step="0.1" name="booking_percent" class="form-control" value="<?php echo htmlspecialchars($pset['booking_percent'] ?? '0'); ?>">
        </div>
        <div class="col-md-2"><label class="form-label">유효기간(일, 0=무기한)</label><input type="number" min="0" name="expire_days" class="form-control" value="<?php echo (int)($pset['expire_days'] ?? 0); ?>"></div>
        <div class="col-md-2"><button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-1"></i>저장</button></div>
    </form>
</div></div>

<?php if (points_v2_schema_ready($connect)):
    $v2 = $pset; $v2v = function ($k, $d) use ($v2) { return htmlspecialchars((string)($v2[$k] ?? $d), ENT_QUOTES); };
    $lots_ready = (int)($v2['lots_ready'] ?? 0) === 1; ?>
<!-- 포인트 제도 v2 (#포인트제도 v2) -->
<div class="card mb-4" id="v2"><div class="card-body">
    <h6 class="mb-1"><i class="bi bi-toggles me-1"></i>포인트 제도 v2 <small class="text-body-secondary">— 기능별 스위치(기본 꺼짐)</small></h6>
    <div class="small text-body-secondary mb-3">
        적립 단위(lot) 재구성: <?php echo $lots_ready ? '<span class="badge bg-success">완료</span>' : '<span class="badge bg-warning text-dark">필요</span> 서버에서 <code>php gangnamunni/tools/points_v2_backfill.php</code> 실행 후 소멸·결제 사용을 켤 수 있습니다.'; ?>
        · 일일 크론: <code>cron_points_daily.php</code> (00:10 권장)
        <?php if ((int)($v2['enabled'] ?? 0) !== 1): ?> · <span class="text-danger">상단 '제도 사용'이 꺼져 있으면 v2 기능도 모두 꺼집니다.</span><?php endif; ?>
    </div>
    <form method="post">
        <input type="hidden" name="admin_csrf" value="<?php echo htmlspecialchars($admin_points_csrf, ENT_QUOTES); ?>">
        <input type="hidden" name="action" value="save_v2">
        <div class="row g-2 mb-3">
        <?php foreach ([
            'v2_policy_enabled' => ['v2 적립 규칙', '후기 유형별·결제 금액 구간 적립, 90일 기한, 삭제 시 회수'],
            'v2_first_review_enabled' => ['첫 후기 일괄 적립', '매주 화요일(공휴일이면 익영업일) 크론'],
            'v2_progress_enabled' => ['경과 후기', '사용자 화면 노출'],
            'v2_expire_job_enabled' => ['자동 소멸', '기한 지난 포인트 소멸(크론)'],
            'v2_point_use_enabled' => ['결제 시 포인트 사용', 'PortOne 결제 금액에서 차감'],
        ] as $k => [$label, $desc]):
            $locked = in_array($k, ['v2_expire_job_enabled', 'v2_point_use_enabled'], true) && !$lots_ready; ?>
            <div class="col-md-4 col-lg"><div class="border rounded p-2 h-100">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="<?php echo $k; ?>" value="1" id="<?php echo $k; ?>" <?php echo ((int)($v2[$k] ?? 0) === 1) ? 'checked' : ''; ?> <?php echo $locked ? 'disabled' : ''; ?>>
                    <label class="form-check-label fw-semibold" for="<?php echo $k; ?>"><?php echo $label; ?></label>
                </div>
                <div class="small text-body-secondary"><?php echo $desc; ?><?php echo $locked ? ' · lot 재구성 필요' : ''; ?></div>
            </div></div>
        <?php endforeach; ?>
        </div>
        <div class="row g-2">
        <?php foreach ([
            'v2_expire_days' => ['사용 기한(일)', 90], 'v2_monthly_cap' => ['유형별 월 한도(회)', 3],
            'v2_review_photo_point' => ['일반 후기(전·후) P', 3000], 'v2_review_receipt_point' => ['영수증 인증 P', 3000],
            'v2_review_first_point' => ['첫 후기 P', 10000], 'v2_first_yearly_cap' => ['첫 후기 1년 한도(건)', 5],
            'v2_progress_procedure_point' => ['경과(시술) P', 6000], 'v2_progress_surgery_point' => ['경과(수술) P', 15000],
            'v2_progress_procedure_entries' => ['경과(시술) 필요 회수', 3], 'v2_progress_surgery_entries' => ['경과(수술) 필요 회수', 10],
            'v2_progress_resubmit_days' => ['반려 후 수정 가능(일)', 30],
            'v2_pay_tier1_under' => ['결제 구간1 미만(원)', 50000], 'v2_pay_tier1_point' => ['구간1 적립 P', 3000],
            'v2_pay_tier2_under' => ['결제 구간2 미만(원)', 100000], 'v2_pay_tier2_point' => ['구간2 적립 P', 5000],
            'v2_pay_tier3_point' => ['그 이상 적립 P', 10000],
            'v2_point_use_min_pay' => ['포인트 사용 시 최소 결제(원)', 1000], 'v2_hold_ttl_minutes' => ['미결제 포인트 복구(분)', 60],
        ] as $k => [$label, $def]): ?>
            <div class="col-6 col-md-3 col-lg-2"><label class="form-label small mb-1"><?php echo $label; ?></label>
                <input type="number" min="0" class="form-control form-control-sm" name="<?php echo $k; ?>" value="<?php echo $v2v($k, $def); ?>"></div>
        <?php endforeach; ?>
            <div class="col-12 col-lg-6"><label class="form-label small mb-1">공휴일(첫 후기 일괄 적립 순연, YYYY-MM-DD 쉼표 구분)</label>
                <input type="text" class="form-control form-control-sm" name="v2_holidays" value="<?php echo $v2v('v2_holidays', ''); ?>" placeholder="2026-12-25,2027-01-01"></div>
            <div class="col-12 col-lg-2 d-flex align-items-end"><button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-save me-1"></i>v2 설정 저장</button></div>
        </div>
    </form>
</div></div>
<?php endif; ?>

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
                $type_labels = ['earn'=>'적립','use'=>'사용','refund'=>'환불복구','expire'=>'소멸','admin_add'=>'관리자적립','admin_sub'=>'관리자차감','earn_cancel'=>'적립취소','admin'=>'적립'];
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
