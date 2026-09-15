<?php
require_once __DIR__ . '/includes/init.php';
if (!is_gangnamunni_logged_in()) { header('Location: auth/login.php?url='.urlencode($_SERVER['REQUEST_URI'])); exit; }
$member_id = get_gangnamunni_member_id();
require_once __DIR__ . '/includes/review_booking.php';
ensureReviewBookingColumn($connect);

if (!function_exists('my_review_district')) {
    function my_review_district($address) {
        if (preg_match('/([^\s]+)[구군](?:\s|$)/u', trim((string)$address), $match)) {
            return $match[1];
        }
        return '';
    }
}

// 삭제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $rid = intval($_POST['review_id'] ?? 0);
    if ($rid > 0) {
        $deleted_review = null;
        $lookup = mysqli_prepare($connect, "SELECT hospital_id, is_active FROM hospital_reviews WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($lookup, 'is', $rid, $member_id);
        mysqli_stmt_execute($lookup);
        $deleted_review = mysqli_fetch_assoc(mysqli_stmt_get_result($lookup));
        mysqli_stmt_close($lookup);
        $stmt = mysqli_prepare($connect, "UPDATE hospital_reviews SET is_active=0, review_status='deleted' WHERE id=? AND user_id=?");
        mysqli_stmt_bind_param($stmt, 'is', $rid, $member_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        if (!empty($deleted_review['is_active'])) {
            $hid = (int)$deleted_review['hospital_id'];
            mysqli_query($connect, "UPDATE gangnamunni_hospitals SET
                review_count=(SELECT COUNT(*) FROM hospital_reviews WHERE hospital_id={$hid} AND is_active=1),
                rating=(SELECT COALESCE(AVG(LEAST(5, GREATEST(1, rating))),0) FROM hospital_reviews WHERE hospital_id={$hid} AND is_active=1)
                WHERE id={$hid}");
        }
    }
}

// 병원 상세에서 작성한 후기 (hospital_reviews)
$reviews = [];
$safe_member = mysqli_real_escape_string($connect, $member_id);
$res = mysqli_query($connect, "SELECT r.id, r.hospital_id, r.content, r.rating, r.treatments, r.doctor_name, r.booking_id, r.view_count, r.like_count, r.created_at, r.review_status, r.is_active, h.name as hospital_name, h.main_image_url, h.address, b.booking_date, b.treatment AS booking_treatment, 'review' as source_type FROM hospital_reviews r LEFT JOIN gangnamunni_hospitals h ON r.hospital_id=h.id LEFT JOIN gangnamunni_bookings b ON r.booking_id=b.id WHERE r.user_id='{$safe_member}' AND (r.is_active=1 OR r.review_status IN ('pending','rejected')) ORDER BY r.created_at DESC");
if ($res) while ($row = mysqli_fetch_assoc($res)) $reviews[] = $row;

// 작성가능 목록 — 시술완료(status='completed') 예약 중, 해당 병원에 아직 후기를 남기지 않은 건
$writable = [];
$res3 = mysqli_query($connect, "
    SELECT b.id, b.hospital_id, b.hospital_name, b.treatment, b.booking_date,
           h.main_image_url, h.address,
           TRIM(CONCAT(u.first_name, ' ', COALESCE(u.last_name, ''))) as doctor_name
    FROM gangnamunni_bookings b
    LEFT JOIN gangnamunni_hospitals h ON b.hospital_id = h.id
    LEFT JOIN appointments ap ON b.hms_appointment_id = ap.id
    LEFT JOIN doctors doc ON ap.doctor_id = doc.id
    LEFT JOIN users u ON doc.user_id = u.id
    WHERE b.mb_id='{$safe_member}' AND b.status='completed'
      AND NOT EXISTS (
          SELECT 1 FROM hospital_reviews r
          WHERE r.user_id COLLATE utf8mb4_unicode_ci = b.mb_id AND r.booking_id=b.id
      )
    ORDER BY b.booking_date DESC
");
if ($res3) while ($row = mysqli_fetch_assoc($res3)) $writable[] = $row;

$tab = ($_GET['tab'] ?? 'writable') === 'done' ? 'done' : 'writable';
?>
<!DOCTYPE html><html lang="ko" data-bs-theme="light"><head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>내 후기 - 서울미녀</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{--pri:#AE1932;}*{margin:0;padding:0;box-sizing:border-box;}
html{background:#3f3f3f;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;width:100%;max-width:640px;min-height:100vh;margin:0 auto;background:#fff;color:#111;overflow-x:hidden;}
.header{height:64px;padding:0 14px;display:flex;align-items:center;background:#fff;position:relative;}
.header .back{width:40px;height:40px;border:0;background:transparent;font-size:28px;cursor:pointer;color:#111;position:relative;z-index:1;display:flex;align-items:center;justify-content:center;}
.header h1{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);font-size:18px;font-weight:500;white-space:nowrap;}
.filter-tabs{height:60px;margin:0 20px;display:flex;background:#fff;border-bottom:1px solid #cfcfcf;}
.filter-tab{flex:1;display:flex;align-items:center;justify-content:center;text-align:center;font-size:16px;font-weight:500;cursor:pointer;color:#111;border-bottom:1px solid transparent;text-decoration:none;}
.filter-tab.active{color:var(--pri);border-bottom-color:var(--pri);}
.review-list{padding:0 20px;background:#fff;}
.writable-card{background:#fff;border-bottom:1px solid #d7d7d7;padding:34px 0 40px;display:flex;align-items:stretch;gap:20px;}
.writable-card .wc-img,.writable-card .wc-noimg{display:block;width:120px;height:160px;max-width:120px;object-fit:contain;object-position:center;overflow:hidden;flex:0 0 120px;background:#fff;}
.writable-card .wc-noimg{background:#d9d9d9;}
.writable-card .wc-noimg{display:flex;align-items:center;justify-content:center;color:#aaa;font-size:28px;}
.writable-card .wc-info{flex:1;min-width:0;display:flex;flex-direction:column;padding-top:0;}
.writable-card .wc-hospital{font-size:15px;color:#111;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:8px;line-height:1.35;}
.writable-card .wc-treatment{font-size:20px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:8px;line-height:1.35;letter-spacing:-.03em;}
.writable-card .wc-address{font-size:15px;color:#111;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:8px;line-height:1.35;}
.writable-card .wc-meta{font-size:15px;color:#111;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:10px;line-height:1.35;}
.wc-separator{display:inline-block;margin:0 10px;color:#bbb;}
.writable-card .wc-info > .wc-write{margin-top:auto;}
.writable-card .wc-write{width:100%;min-height:40px;display:flex;align-items:center;justify-content:center;text-align:center;padding:8px 12px;background:#fff0f1;color:#AE1932;text-decoration:none;font-size:14px;font-weight:500;white-space:nowrap;}
.writable-card .wc-rating{color:#f59e0b;}
.writable-card .wc-btnrow{display:flex;gap:8px;margin-top:auto;}
.writable-card .wc-btnrow > *{flex:1 1 0;min-width:0;}
.writable-card .wc-btnrow .wc-write{flex:1 1 0;width:auto;min-width:0;box-sizing:border-box;border:none;cursor:pointer;font-family:inherit;}
.writable-card .wc-btnrow .wc-write:not(.del){background:#fff;border:1px solid #AE1932;color:#AE1932;}
.writable-card .wc-btnrow .wc-write.del{background:#fff;border:1px solid #000;color:#000;}
.done-card{padding:34px 0 40px;display:flex;align-items:stretch;gap:20px;border-bottom:1px solid #d7d7d7;background:#fff;}
.done-thumb{width:120px;height:162px;flex:0 0 120px;object-fit:cover;background:#d9d9d9;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:28px;}
.done-copy{flex:1;min-width:0;display:flex;flex-direction:column;}
.done-head{display:flex;align-items:center;gap:12px;margin-bottom:8px;}
.done-hospital{min-width:0;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px;}
.done-rating{flex:none;display:flex;align-items:center;gap:5px;white-space:nowrap;font-size:13px;}
.done-stars{color:#f6a12a;letter-spacing:1px;font-size:15px;}
.done-treatment{margin-bottom:8px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:17px;font-weight:700;line-height:1.35;letter-spacing:-.03em;}
.done-address,.done-meta{margin-bottom:8px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px;line-height:1.35;}
.done-actions{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:auto;}
.done-action{height:42px;border:1px solid #bbb;background:#fff;color:#111;display:flex;align-items:center;justify-content:center;text-decoration:none;font:inherit;font-size:14px;cursor:pointer;}
.done-action.primary{border-color:#AE1932;color:#AE1932;}
.done-action.pending{grid-column:1 / -1;border:0;background:#fff0f1;color:#AE1932;cursor:default;}
.done-action-form{display:flex;}
.done-action-form .done-action{width:100%;}
.delete-modal{position:fixed;inset:0;z-index:20000;display:none;align-items:flex-end;justify-content:center;background:rgba(0,0,0,.5);}
.delete-modal.open{display:flex;}
.delete-sheet{width:100%;max-width:640px;padding:16px 24px calc(26px + env(safe-area-inset-bottom,0px));background:#fff;text-align:center;}
.delete-handle{width:48px;height:5px;margin:0 auto 38px;border-radius:4px;background:#AE1932;}
.delete-title{font-size:21px;font-weight:700;margin-bottom:12px;}
.delete-copy{font-size:14px;color:#777;line-height:1.55;}
.delete-actions{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:34px;}
.delete-button{height:50px;border:1px solid #AE1932;background:#fff;color:#AE1932;font-size:15px;font-weight:700;cursor:pointer;}
.delete-button.danger{background:#AE1932;color:#fff;}
.delete-actions form,.delete-actions form .delete-button{width:100%;}
.empty{text-align:center;padding:80px 20px;color:#888;}
@media(max-width:420px){.filter-tabs,.review-list{margin-left:0;margin-right:0}.writable-card,.done-card{gap:14px}.writable-card .wc-img,.writable-card .wc-noimg{width:120px;height:160px;max-width:120px;flex-basis:120px}.done-thumb{width:104px;height:146px;flex-basis:104px}.done-treatment{font-size:15px}.done-address,.done-meta{font-size:12px}.wc-separator{margin:0 6px}.done-head{align-items:flex-start;gap:6px}.done-rating{font-size:12px}.done-stars{font-size:13px;letter-spacing:0}.done-actions{gap:8px}}
</style><?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head><body>
<div class="header">
    <button class="back" onclick="location.href='mypage.php'"><i class="bi bi-chevron-left"></i></button>
    <h1>내가 작성한 후기</h1>
</div>

<div class="filter-tabs">
    <a href="?tab=writable" class="filter-tab <?php echo $tab === 'writable' ? 'active' : ''; ?>">작성가능</a>
    <a href="?tab=done" class="filter-tab <?php echo $tab === 'done' ? 'active' : ''; ?>">작성완료</a>
</div>

<main class="review-list">
<?php if ($tab === 'writable'): ?>
    <?php if (empty($writable)): ?>
    <div class="empty"><i class="bi bi-pencil-square" style="font-size:48px;display:block;margin-bottom:12px;"></i>작성 가능한 후기가 없습니다<br><span style="font-size:13px;">시술 완료 후 후기를 작성할 수 있어요</span></div>
    <?php else: foreach ($writable as $w): ?>
    <div class="writable-card">
        <?php if (!empty($w['main_image_url'])): ?>
        <img class="wc-img" src="<?php echo htmlspecialchars($w['main_image_url']); ?>" alt="">
        <?php else: ?>
        <div class="wc-noimg"><i class="bi bi-building"></i></div>
        <?php endif; ?>
        <div class="wc-info">
            <div class="wc-hospital"><?php echo htmlspecialchars($w['hospital_name'] ?? ''); ?></div>
            <div class="wc-treatment"><?php echo htmlspecialchars($w['treatment'] ?? ''); ?></div>
            <?php if (!empty($w['address'])): ?>
            <div class="wc-address"><?php $_district = my_review_district($w['address']); ?><?php if ($_district !== ''): ?><span><?php echo htmlspecialchars($_district); ?></span><span class="wc-separator">|</span><?php endif; ?><?php echo htmlspecialchars($w['address']); ?></div>
            <?php endif; ?>
            <div class="wc-meta"><?php if (!empty($w['doctor_name'])): ?><?php echo htmlspecialchars($w['doctor_name']); ?><span class="wc-separator">|</span><?php endif; ?><?php echo !empty($w['booking_date']) ? '시술일 ' . htmlspecialchars(str_replace('-', '.', $w['booking_date'])) : ''; ?></div>
            <a href="review_write.php?hospital_id=<?php echo (int)$w['hospital_id']; ?>&booking_id=<?php echo (int)$w['id']; ?>" class="wc-write">후기를 작성해볼까요?</a>
        </div>
    </div>
    <?php endforeach; endif; ?>
<?php else: ?>
    <?php if (empty($reviews)): ?>
    <div class="empty"><i class="bi bi-chat-square-text" style="font-size:48px;display:block;margin-bottom:12px;"></i>작성한 후기가 없습니다<br><a href="review_write.php" style="color:var(--pri);">후기 작성하기</a></div>
    <?php else: foreach ($reviews as $r): ?>
    <article class="done-card">
        <?php if (!empty($r['main_image_url'])): ?>
        <img class="done-thumb" src="<?php echo htmlspecialchars($r['main_image_url']); ?>" alt="">
        <?php else: ?>
        <div class="done-thumb"><i class="bi bi-building"></i></div>
        <?php endif; ?>
        <div class="done-copy">
            <div class="done-head">
                <div class="done-hospital"><?php echo htmlspecialchars($r['hospital_name'] ?? ''); ?></div>
                <?php $_rating = max(0, min(5, (int)($r['rating'] ?? 0))); ?>
                <div class="done-rating" aria-label="평점 <?php echo $_rating; ?>점"><span class="done-stars"><?php echo str_repeat('★', $_rating) . str_repeat('☆', 5 - $_rating); ?></span><span><?php echo number_format((float)($r['rating'] ?? 0), 1); ?></span></div>
            </div>
            <div class="done-treatment"><?php echo htmlspecialchars($r['booking_treatment'] ?: ($r['treatments'] ?: '-')); ?></div>
            <?php if (!empty($r['address'])): ?>
            <div class="done-address"><?php $_district = my_review_district($r['address']); ?><?php if ($_district !== ''): ?><span><?php echo htmlspecialchars($_district); ?></span><span class="wc-separator">|</span><?php endif; ?><?php echo htmlspecialchars($r['address']); ?></div>
            <?php endif; ?>
            <div class="done-meta"><?php if (!empty($r['doctor_name'])): ?><?php echo htmlspecialchars($r['doctor_name']); ?><span class="wc-separator">|</span><?php endif; ?><?php echo !empty($r['booking_date']) ? '시술일 ' . htmlspecialchars(str_replace('-', '.', $r['booking_date'])) : ''; ?></div>
            <div class="done-actions">
                <?php if (($r['review_status'] ?? '') === 'pending' && empty($r['is_active'])): ?>
                <span class="done-action pending">승인 대기중</span>
                <?php else: ?>
                <a href="review_detail.php?id=<?php echo (int)$r['id']; ?>" class="done-action primary">보기</a>
                <button type="button" class="done-action" onclick="openDeleteModal(<?php echo (int)$r['id']; ?>)">삭제</button>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php endforeach; endif; ?>
<?php endif; ?>
</main>

<div class="delete-modal" id="deleteReviewModal" aria-hidden="true" onclick="if(event.target===this)closeDeleteModal()">
    <section class="delete-sheet" role="dialog" aria-modal="true" aria-labelledby="deleteReviewTitle">
        <div class="delete-handle" aria-hidden="true"></div>
        <h2 class="delete-title" id="deleteReviewTitle">후기를 삭제할까요?</h2>
        <p class="delete-copy">삭제한 후기는 다시 복구할 수 없어요.</p>
        <div class="delete-actions">
            <button type="button" class="delete-button" onclick="closeDeleteModal()">취소</button>
            <form method="post" id="deleteReviewForm"><input type="hidden" name="action" value="delete"><input type="hidden" name="review_id" id="deleteReviewId" value=""><button type="submit" class="delete-button danger">삭제할게요</button></form>
        </div>
    </section>
</div>
<script>
function openDeleteModal(reviewId){document.getElementById('deleteReviewId').value=String(reviewId);var modal=document.getElementById('deleteReviewModal');modal.classList.add('open');modal.setAttribute('aria-hidden','false');document.body.style.overflow='hidden';}
function closeDeleteModal(){var modal=document.getElementById('deleteReviewModal');modal.classList.remove('open');modal.setAttribute('aria-hidden','true');document.body.style.overflow='';}
document.addEventListener('keydown',function(event){if(event.key==='Escape')closeDeleteModal();});
</script>
</body></html>
