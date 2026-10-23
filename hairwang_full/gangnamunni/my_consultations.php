<?php
/**
 * 마이페이지 > 상담 내역 (07-40)
 * gangnamunni_consultations.mb_id 로 연결된 회원의 상담 신청 목록.
 * 컬럼: sql/consultation_member_20261023.sql, sql/consultation_history_20261023.sql
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/consultation_view.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
if (!is_gangnamunni_logged_in()) {
    header('Location: auth/login.php?url=' . urlencode('my_consultations.php'));
    exit;
}
$mb_id = (string)($member['mb_id'] ?? '');

$has_col = false;
$cc = @mysqli_query($connect, "SHOW COLUMNS FROM gangnamunni_consultations LIKE 'mb_id'");
if ($cc && mysqli_num_rows($cc) > 0) $has_col = true;

$items = [];
if ($has_col && $mb_id !== '') {
    $stmt = mysqli_prepare($connect, "SELECT c.id, c.hospital_id, c.hospital_name, c.consultation_type, c.status, c.created_at,
            c.message, h.phone AS h_phone
        FROM gangnamunni_consultations c LEFT JOIN gangnamunni_hospitals h ON h.id = c.hospital_id
        WHERE c.mb_id = ? ORDER BY c.created_at DESC, c.id DESC LIMIT 100");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $mb_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($res && ($row = mysqli_fetch_assoc($res))) $items[] = $row;
        mysqli_stmt_close($stmt);
    }
}
$any_phone = '';
foreach ($items as $it) { if (trim((string)$it['h_phone']) !== '') { $any_phone = trim((string)$it['h_phone']); break; } }
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>상담 내역 - <?php echo site_name(); ?></title>
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

.wrap { background:#F4F2F3; min-height:calc(100vh - 52px); padding:10px 0 30px; display:flex; flex-direction:column; gap:10px; }
.card { margin:0 16px; padding:16px; background:#fff; }
.card-top { display:flex; align-items:center; gap:8px; }
.card-date { margin-left:auto; font-size:15px; color:#9A9395; }
.card-name { margin-top:10px; font-size:19px; font-weight:700; word-break:break-word; }
.card-sub { margin-top:3px; font-size:16px; color:#4A4446; }
.card-box { margin-top:12px; padding:12px; background:#FBEFF1; font-size:16px; line-height:1.6; }
.card-hint { margin-top:10px; font-size:16px; color:#6B6466; }
.card-actions { display:flex; gap:8px; margin-top:14px; }
.card-actions .btn { height:44px; font-size:17px; }
.notice { margin:4px 16px 0; padding:12px 14px; display:flex; gap:8px; font-size:15px; line-height:1.6; color:#4A4446; background:#fff; }
.notice i { color:#AC1830; flex-shrink:0; margin-top:2px; }
.empty-state { text-align:center; padding:80px 20px; color:#777; font-size:18px; line-height:1.6; background:#fff; }
.empty-state i { font-size:48px; display:block; margin-bottom:12px; }
.migrate { margin:16px; padding:14px; background:#FBEFF1; font-size:16px; line-height:1.6; color:#4A4446; }
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
<div class="page-header">
    <button class="back-btn" onclick="location.href='mypage.php'" aria-label="뒤로 가기"><i class="bi bi-chevron-left"></i></button>
    <h1>상담 내역</h1>
    <span></span>
</div>
<?php if (!$has_col): ?>
<div class="migrate">상담 내역 기능이 아직 준비되지 않았어요. (관리자: sql/consultation_member_20261023.sql 실행 필요)</div>
<?php endif; ?>
<div class="wrap">
<?php if (empty($items)): ?>
    <div class="empty-state"><i class="bi bi-chat-dots"></i>상담 내역이 없어요<br><span style="font-size:16px;">궁금한 <?php echo term('business', '병원'); ?>에 상담을 신청해보세요</span></div>
<?php else: foreach ($items as $it):
    $st = (string)($it['status'] ?? 'pending');
    [$lbl, $bg, $fg] = consult_status_view($st);
    $detail = 'my_consultation_detail.php?id=' . (int)$it['id'];
    $phone = trim((string)$it['h_phone']);
    $type_label = consult_type_label($it['consultation_type'] ?? '');
?>
    <div class="card">
        <a href="<?php echo $detail; ?>" style="display:block;">
            <div class="card-top">
                <span class="chip" style="background:<?php echo $bg; ?>;color:<?php echo $fg; ?>;"><?php echo htmlspecialchars($lbl); ?></span>
                <span class="card-date"><?php echo htmlspecialchars(date('m.d', strtotime((string)$it['created_at']))); ?> 신청</span>
            </div>
            <div class="card-name"><?php echo htmlspecialchars((string)$it['hospital_name']); ?></div>
            <div class="card-sub"><?php echo htmlspecialchars($type_label); ?></div>
            <?php if ($st === 'contact_failed'): ?>
            <div class="card-box">병원에서 연락드렸지만 받지 않으셨어요.<br>편한 시간을 골라 다시 요청해주세요.</div>
            <?php elseif ($st === 'pending' && ($it['consultation_type'] ?? '') === 'kakao'): ?>
            <div class="card-hint">카카오톡 채널에서 대화를 시작하면 병원이 답변해드려요</div>
            <?php endif; ?>
        </a>
        <?php if ($st === 'contact_failed'): ?>
        <div class="card-actions">
            <a class="btn pri" href="<?php echo $detail; ?>">다시 요청</a>
            <?php if ($phone !== ''): ?><a class="btn" href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $phone), ENT_QUOTES); ?>">병원에 전화</a><?php endif; ?>
        </div>
        <?php elseif ($st === 'completed'): ?>
        <div class="card-actions"><a class="btn" href="booking.php?id=<?php echo (int)$it['hospital_id']; ?>"><?php echo htmlspecialchars(term('booking', '예약')); ?>하기</a></div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
    <div class="notice"><i class="bi bi-info-circle"></i><span>모르는 번호 차단 · 스팸 차단을 켜두셨다면 병원 번호<?php echo $any_phone !== '' ? '(' . htmlspecialchars($any_phone) . ')' : ''; ?>를 저장해주세요. 연락이 닿지 않으면 이곳과 앱 알림으로 알려드려요.</span></div>
<?php endif; ?>
</div>
<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
</body>
</html>
