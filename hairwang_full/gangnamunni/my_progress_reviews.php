<?php
/**
 * 내 경과 후기 목록 + 새 경과 후기 시작 (#포인트제도 v2)
 * 스위치: v2_progress_enabled (꺼져 있으면 '준비 중')
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/progress_review_common.php';

if (!is_gangnamunni_logged_in()) {
    header('Location: auth/login.php?url=' . urlencode(rtrim(SITE_BASE_PATH, '/') . '/my_progress_reviews.php'));
    exit;
}
pr_require_enabled($connect);

$m = get_gangnamunni_member();
$mb_id = (string)($m['mb_id'] ?? '');
$member_no = points_member_no($connect, $mb_id);
$error = '';

// 시술 완료된 내 예약(경과 후기 대상)
$bookings = [];
$bq = mysqli_prepare($connect, "SELECT id, hospital_id, hospital_name, treatment, booking_date FROM gangnamunni_bookings
    WHERE mb_id = ? AND status = 'completed' ORDER BY booking_date DESC, id DESC LIMIT 50");
if ($bq) {
    mysqli_stmt_bind_param($bq, 's', $mb_id);
    mysqli_stmt_execute($bq);
    $r = mysqli_stmt_get_result($bq);
    while ($row = mysqli_fetch_assoc($r)) $bookings[(int)$row['id']] = $row;
    mysqli_stmt_close($bq);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    pr_check_csrf();
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $kind = ($_POST['kind'] ?? '') === 'surgery' ? 'surgery' : 'procedure';
    $title = trim(mb_substr((string)($_POST['title'] ?? ''), 0, 100));
    $treatment = trim(mb_substr((string)($_POST['treatment_name'] ?? ''), 0, 100));
    $pdate = (string)($_POST['procedure_date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $pdate) || strtotime($pdate) > time()) $pdate = '';
    $hospital_id = null;
    if ($booking_id > 0) {
        if (!isset($bookings[$booking_id])) $error = '선택한 예약을 찾을 수 없습니다.';
        else {
            $hospital_id = (int)$bookings[$booking_id]['hospital_id'];
            if ($treatment === '') $treatment = (string)$bookings[$booking_id]['treatment'];
            if ($pdate === '') $pdate = (string)$bookings[$booking_id]['booking_date'];
        }
    }
    if (!$error && $treatment === '') $error = '시술·수술명을 입력해주세요.';
    if (!$error && $pdate === '') $error = '시술일을 입력해주세요.';
    if (!$error && $member_no <= 0) $error = '회원 정보를 확인할 수 없습니다.';
    if (!$error) {
        if ($title === '') $title = $treatment . ' 경과 후기';
        $bid = $booking_id > 0 ? $booking_id : null;
        $st = mysqli_prepare($connect, "INSERT INTO gangnamunni_progress_reviews
            (member_id, mb_id, hospital_id, booking_id, treatment_name, kind, procedure_date, title, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'writing')");
        mysqli_stmt_bind_param($st, 'isiissss', $member_no, $mb_id, $hospital_id, $bid, $treatment, $kind, $pdate, $title);
        if (mysqli_stmt_execute($st)) {
            $nid = (int)mysqli_insert_id($connect);
            mysqli_stmt_close($st);
            header('Location: progress_review.php?id=' . $nid);
            exit;
        }
        $error = '저장하지 못했습니다. 잠시 후 다시 시도해주세요.';
    }
}

// 내 목록
$list = [];
$lq = mysqli_prepare($connect, "SELECT p.*, h.name AS hospital_name,
        (SELECT COUNT(*) FROM gangnamunni_progress_entries e WHERE e.progress_id = p.id) AS entry_count
    FROM gangnamunni_progress_reviews p LEFT JOIN gangnamunni_hospitals h ON h.id = p.hospital_id
    WHERE p.member_id = ? AND p.status <> 'deleted' ORDER BY p.id DESC");
mysqli_stmt_bind_param($lq, 'i', $member_no);
mysqli_stmt_execute($lq);
$r = mysqli_stmt_get_result($lq);
while ($row = mysqli_fetch_assoc($r)) $list[] = $row;
mysqli_stmt_close($lq);

$need_p = points_v2_progress_required_entries($connect, 'procedure');
$need_s = points_v2_progress_required_entries($connect, 'surgery');
$pt_p = points_v2_int($connect, 'v2_progress_procedure_point', 6000);
$pt_s = points_v2_int($connect, 'v2_progress_surgery_point', 15000);

pr_page_open('경과 후기');
?>
<div class="pr-wrap">
    <div class="alert">시술 후기는 경과를 <b><?php echo $need_p; ?>회</b> 이상, 수술 후기는 <b><?php echo $need_s; ?>회</b> 이상 기록하고 작성 완료하면
        검수 후 <b><?php echo number_format($pt_p); ?>P / <?php echo number_format($pt_s); ?>P</b>가 적립됩니다.
        <a href="my_points_policy_detail.php?type=progress" style="color:var(--pri)">자세히</a></div>

    <?php if ($error): ?><div class="alert err"><?php echo pr_h($error); ?></div><?php endif; ?>

    <?php if (empty($list)): ?>
        <div class="pr-empty"><div class="circle"><i class="bi bi-journal-text"></i></div><p>아직 작성한 경과 후기가 없습니다.</p></div>
    <?php else: foreach ($list as $p):
        $need = points_v2_progress_required_entries($connect, $p['kind']);
        $pct = min(100, (int)round(((int)$p['entry_count'] / max(1, $need)) * 100)); ?>
        <a class="pr-card" href="progress_review.php?id=<?php echo (int)$p['id']; ?>">
            <div><span class="tag<?php echo $p['status'] === 'approved' ? ' pri' : ''; ?>"><?php echo pr_status_label($p['status']); ?></span><span class="tag"><?php echo pr_kind_label($p['kind']); ?></span></div>
            <div class="t" style="margin-top:8px"><?php echo pr_h($p['title']); ?></div>
            <div class="m"><?php echo pr_h(trim(($p['hospital_name'] ?? '') . ' · ' . $p['treatment_name'], ' ·')); ?> · 경과 <?php echo (int)$p['entry_count']; ?>/<?php echo $need; ?>회</div>
            <div class="progress"><i style="width:<?php echo $pct; ?>%"></i></div>
        </a>
    <?php endforeach; endif; ?>
</div>

<div class="band" style="margin-top:20px"></div>

<form class="pr-wrap" method="post">
    <h2 style="font-size:calc(16px * var(--fs));font-weight:700;margin-top:12px">새 경과 후기 시작</h2>
    <input type="hidden" name="csrf_token" value="<?php echo pr_h(pr_csrf()); ?>">
    <input type="hidden" name="action" value="create">
    <?php if (!empty($bookings)): ?>
    <div class="field"><label for="booking_id">시술받은 예약</label>
        <select id="booking_id" name="booking_id">
            <option value="0">선택 안 함(직접 입력)</option>
            <?php foreach ($bookings as $b): ?>
            <option value="<?php echo (int)$b['id']; ?>"><?php echo pr_h($b['booking_date'] . ' ' . $b['hospital_name'] . ' · ' . $b['treatment']); ?></option>
            <?php endforeach; ?>
        </select></div>
    <?php endif; ?>
    <div class="field"><label>구분</label>
        <select name="kind"><option value="procedure">시술 (경과 <?php echo $need_p; ?>회)</option><option value="surgery">수술 (경과 <?php echo $need_s; ?>회)</option></select></div>
    <div class="field"><label for="treatment_name">시술·수술명</label><input id="treatment_name" name="treatment_name" maxlength="100" placeholder="예약을 선택하면 비워두어도 됩니다"></div>
    <div class="field"><label for="procedure_date">시술일</label><input id="procedure_date" type="date" name="procedure_date" max="<?php echo date('Y-m-d'); ?>"></div>
    <div class="field"><label for="title">제목 (선택)</label><input id="title" name="title" maxlength="100"></div>
    <div style="margin-top:20px"><button class="btn" type="submit">시작하기</button></div>
</form>
<?php
pr_page_close($connect);
