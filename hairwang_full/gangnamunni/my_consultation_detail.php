<?php
/**
 * 마이페이지 > 상담 상세 (07-41) + 다시 요청
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/consultation_view.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
if (!is_gangnamunni_logged_in()) {
    header('Location: auth/login.php?url=' . urlencode('my_consultations.php'));
    exit;
}
$mb_id = (string)($member['mb_id'] ?? '');
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (empty($_SESSION['my_consult_csrf'])) $_SESSION['my_consult_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['my_consult_csrf'];

function consult_load(mysqli $db, int $id, string $mb_id): ?array {
    if ($id <= 0 || $mb_id === '') return null;
    // kakao_channel_url 컬럼이 없는 환경에서는 예외가 날 수 있어 단계적으로 시도한다.
    $queries = [
        "SELECT c.*, h.phone AS h_phone, h.kakao_channel_url AS h_kakao_channel_url FROM gangnamunni_consultations c LEFT JOIN gangnamunni_hospitals h ON h.id = c.hospital_id WHERE c.id = ? AND c.mb_id = ? LIMIT 1",
        "SELECT c.*, h.phone AS h_phone FROM gangnamunni_consultations c LEFT JOIN gangnamunni_hospitals h ON h.id = c.hospital_id WHERE c.id = ? AND c.mb_id = ? LIMIT 1",
    ];
    foreach ($queries as $q) {
        try {
            $st = mysqli_prepare($db, $q);
            if (!$st) continue;
            mysqli_stmt_bind_param($st, 'is', $id, $mb_id);
            mysqli_stmt_execute($st);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
            mysqli_stmt_close($st);
            return $row ?: null;
        } catch (Throwable $e) { continue; }
    }
    return null;
}

$c = consult_load($connect, $id, $mb_id);
if (!$c) { header('Location: my_consultations.php'); exit; }
$slots = consult_slot_labels();
$error = '';

// 다시 요청: 연락 실패 상태에서만, 선택한 시간대와 함께 다시 접수한다.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $slot = (string)($_POST['slot'] ?? '');
    if (empty($_POST['csrf']) || !hash_equals($csrf, (string)$_POST['csrf'])) {
        $error = '요청이 만료되었어요. 새로고침 후 다시 시도해주세요.';
    } elseif (($c['status'] ?? '') !== 'contact_failed') {
        $error = '지금은 다시 요청할 수 없는 상태예요.';
    } elseif (!isset($slots[$slot])) {
        $error = '다시 연락받을 시간을 선택해주세요.';
    } else {
        $u = mysqli_prepare($connect, "UPDATE gangnamunni_consultations SET status = 'pending', callback_slot = ? WHERE id = ? AND mb_id = ? AND status = 'contact_failed'");
        if ($u) {
            mysqli_stmt_bind_param($u, 'sis', $slot, $id, $mb_id);
            mysqli_stmt_execute($u);
            $changed = mysqli_stmt_affected_rows($u) === 1;
            mysqli_stmt_close($u);
            if ($changed) {
                $ev = mysqli_prepare($connect, "INSERT INTO gangnamunni_consultation_events (consultation_id, kind) VALUES (?, 'rerequest')");
                if ($ev) { mysqli_stmt_bind_param($ev, 'i', $id); mysqli_stmt_execute($ev); mysqli_stmt_close($ev); }
            }
        }
        header('Location: my_consultations.php');
        exit;
    }
    $c = consult_load($connect, $id, $mb_id) ?: $c;
}

$events = [];
$er = @mysqli_query($connect, "SELECT kind, created_at FROM gangnamunni_consultation_events WHERE consultation_id = " . (int)$id . " ORDER BY id ASC");
if ($er) while ($e = mysqli_fetch_assoc($er)) $events[] = $e;

$st = (string)($c['status'] ?? 'pending');
[$lbl, $bg, $fg] = consult_status_view($st);
$type_label = consult_type_label($c['consultation_type'] ?? '');
$slot_now = (string)($c['callback_slot'] ?? '');
$sub = $type_label . (isset($slots[$slot_now]) && $slot_now !== 'any' ? ' · ' . $slots[$slot_now] : '')
     . ' · ' . trim((string)($c['name'] ?? '')) . ' ' . trim((string)($c['phone'] ?? ''));
$phone = trim((string)($c['h_phone'] ?? ''));
$kakao = consult_safe_url(trim((string)($c['h_kakao_channel_url'] ?? '')));
$ev_labels = ['no_answer' => '병원 연락 · 받지 않음', 'rerequest' => '다시 요청', 'completed' => '상담 완료'];
$final_done = ($st === 'completed');
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>상담 상세 - <?php echo site_name(); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>

:root { --primary: #AC1830; }
* { margin: 0; padding: 0; box-sizing: border-box; }
html { background:#3f3f3f; }
body { font-family:'Noto Sans KR',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; background:#fff; color:#1A1718; max-width:640px; min-height:100vh; margin:0 auto; padding-bottom:90px; letter-spacing:-.01em; }
a { color:inherit; text-decoration:none; }
.page-header { height:52px; padding:0 8px; display:grid; grid-template-columns:44px 1fr 44px; align-items:center; background:#fff; position:sticky; top:0; z-index:10; }
.page-header .back-btn { width:44px; height:44px; border:0; background:none; font-size:24px; cursor:pointer; color:#1A1718; display:flex; align-items:center; justify-content:center; }
.page-header h1 { text-align:center; font-size:20px; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.chip { height:28px; padding:0 9px; display:inline-flex; align-items:center; font-size:15px; font-weight:700; }
.btn { flex:1 1 0; display:flex; align-items:center; justify-content:center; border-radius:4px; font-family:inherit; font-weight:500; background:#fff; color:#AC1830; border:1px solid #AC1830; cursor:pointer; text-align:center; }
.btn.pri { background:#AC1830; color:#fff; }

.top { padding:8px 20px 0; }
.top .name { font-size:21px; font-weight:700; margin-top:10px; word-break:break-word; }
.top .sub { font-size:16px; color:#4A4446; margin-top:4px; word-break:break-word; }
.timeline { margin:18px 20px 0; padding:16px; border:1px solid #DCD6D8; display:flex; flex-direction:column; gap:12px; }
.tl { display:flex; gap:10px; font-size:16px; }
.tl .dot { width:10px; height:10px; border-radius:5px; background:#AC1830; flex-shrink:0; margin-top:7px; }
.tl.off .dot { background:#DCD6D8; }
.tl b { font-weight:700; }
.tl.off b { font-weight:500; }
.tl small { display:block; font-size:15px; color:#6B6466; margin-top:2px; }
.sec { padding:20px 20px 8px; font-size:17px; font-weight:700; }
.msg { margin:0 20px; padding:14px; background:#FBEFF1; font-size:16px; line-height:1.65; white-space:pre-wrap; word-break:break-word; }
.msg small { display:block; font-size:14px; color:#6B6466; margin-top:6px; }
.slots { padding:0 20px; display:flex; flex-wrap:wrap; gap:6px; }
.slot { height:38px; padding:0 14px; display:flex; align-items:center; border-radius:19px; font-size:16px; border:1px solid #DCD6D8; background:#fff; font-family:inherit; color:#1A1718; cursor:pointer; }
.slot.on { border-color:#AC1830; color:#AC1830; background:#FBEFF1; font-weight:700; }
.slots.ro .slot { cursor:default; }
.foot { padding:24px 20px 34px; display:flex; gap:10px; }
.foot .btn { height:52px; font-size:19px; }
.err { margin:14px 20px 0; padding:10px 12px; background:#FBEFF1; color:#AC1830; font-size:16px; }
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
<div class="page-header">
    <button class="back-btn" onclick="location.href='my_consultations.php'" aria-label="뒤로 가기"><i class="bi bi-chevron-left"></i></button>
    <h1>상담 상세</h1>
    <span></span>
</div>
<form method="post" action="my_consultation_detail.php?id=<?php echo (int)$id; ?>" id="reForm">
<input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>">
<input type="hidden" name="id" value="<?php echo (int)$id; ?>">
<input type="hidden" name="slot" id="slotInput" value="<?php echo htmlspecialchars($slot_now, ENT_QUOTES); ?>">

<div class="top">
    <span class="chip" style="background:<?php echo $bg; ?>;color:<?php echo $fg; ?>;"><?php echo htmlspecialchars($lbl); ?></span>
    <div class="name"><?php echo htmlspecialchars((string)$c['hospital_name']); ?></div>
    <div class="sub"><?php echo htmlspecialchars($sub); ?></div>
</div>
<?php if ($error !== ''): ?><div class="err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="timeline">
    <div class="tl"><span class="dot"></span><span><b>상담 신청 완료</b><small><?php echo htmlspecialchars(consult_dt($c['created_at'] ?? '')); ?></small></span></div>
<?php foreach ($events as $e):
    $k = (string)$e['kind'];
    if ($k === 'completed') continue; // 마지막 줄에서 표시
    if (!isset($ev_labels[$k])) continue; ?>
    <div class="tl"><span class="dot"></span><span><b><?php echo htmlspecialchars($ev_labels[$k]); ?></b><small><?php echo htmlspecialchars(consult_dt($e['created_at'])); ?></small></span></div>
<?php endforeach; ?>
<?php
$done_at = '';
foreach ($events as $e) { if ($e['kind'] === 'completed') $done_at = consult_dt($e['created_at']); }
?>
    <div class="tl<?php echo $final_done ? '' : ' off'; ?>"><span class="dot"></span><span><b>상담 완료</b><small><?php echo $final_done ? htmlspecialchars($done_at) : '연락이 닿으면 상담이 진행돼요'; ?></small></span></div>
</div>

<?php if (trim((string)($c['hospital_message'] ?? '')) !== ''): ?>
<div class="sec">병원 메시지</div>
<div class="msg"><?php echo htmlspecialchars((string)$c['hospital_message']); ?>
    <small><?php echo htmlspecialchars((string)$c['hospital_name']); ?> · <?php echo htmlspecialchars(preg_replace('/^\d\d\.\d\d \(.\) /u', '', consult_dt($c['hospital_message_at'] ?? '')) ?: ''); ?></small>
</div>
<?php endif; ?>

<?php if ($st === 'contact_failed' || $slot_now !== ''): ?>
<div class="sec">다시 연락받을 시간</div>
<div class="slots<?php echo $st === 'contact_failed' ? '' : ' ro'; ?>">
<?php foreach ($slots as $k => $v): ?>
    <button type="button" class="slot<?php echo $slot_now === $k ? ' on' : ''; ?>" data-slot="<?php echo $k; ?>"<?php echo $st === 'contact_failed' ? '' : ' tabindex="-1"'; ?>><?php echo htmlspecialchars($v); ?></button>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="foot">
<?php if ($phone !== ''): ?><a class="btn" href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $phone), ENT_QUOTES); ?>">병원에 전화하기</a><?php endif; ?>
<?php if ($st === 'contact_failed'): ?>
    <button type="submit" class="btn pri" id="reBtn">다시 요청</button>
<?php elseif ($st === 'completed'): ?>
    <a class="btn pri" href="booking.php?id=<?php echo (int)$c['hospital_id']; ?>"><?php echo htmlspecialchars(term('booking', '예약')); ?>하기</a>
<?php elseif ($kakao !== '' && ($c['consultation_type'] ?? '') === 'kakao'): ?>
    <a class="btn pri" href="<?php echo htmlspecialchars($kakao, ENT_QUOTES); ?>" target="_blank" rel="noopener">카카오톡 채널 열기</a>
<?php endif; ?>
</div>
</form>
<script>
(function () {
    var slots = document.querySelectorAll(".slots:not(.ro) .slot");
    var input = document.getElementById("slotInput");
    slots.forEach(function (b) {
        b.addEventListener("click", function () {
            slots.forEach(function (x) { x.classList.remove("on"); });
            b.classList.add("on");
            input.value = b.getAttribute("data-slot");
        });
    });
    var form = document.getElementById("reForm");
    form.addEventListener("submit", function (e) {
        if (document.getElementById("reBtn") && !input.value) {
            e.preventDefault();
            alert("다시 연락받을 시간을 선택해주세요.");
        }
    });
})();
</script>
<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
</body>
</html>
