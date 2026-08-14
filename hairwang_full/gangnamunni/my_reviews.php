<?php
require_once __DIR__ . '/includes/init.php';
if (!is_gangnamunni_logged_in()) { header('Location: auth/login.php?url='.urlencode($_SERVER['REQUEST_URI'])); exit; }
$member_id = get_gangnamunni_member_id();

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
        $stmt = mysqli_prepare($connect, "UPDATE hospital_reviews SET is_active=0 WHERE id=? AND user_id=?");
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
$res = mysqli_query($connect, "SELECT r.id, r.hospital_id, r.content, r.rating, r.treatments, r.view_count, r.like_count, r.created_at, h.name as hospital_name, h.main_image_url, h.address, 'review' as source_type FROM hospital_reviews r LEFT JOIN gangnamunni_hospitals h ON r.hospital_id=h.id WHERE r.user_id='{$safe_member}' AND r.is_active=1 ORDER BY r.created_at DESC");
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
          SELECT 1 FROM hospital_reviews r WHERE r.user_id COLLATE utf8mb4_unicode_ci = b.mb_id AND r.hospital_id=b.hospital_id AND r.is_active=1
      )
    ORDER BY b.booking_date DESC
");
if ($res3) while ($row = mysqli_fetch_assoc($res3)) $writable[] = $row;

$tab = ($_GET['tab'] ?? 'done') === 'writable' ? 'writable' : 'done';
?>
<!DOCTYPE html><html lang="ko" data-bs-theme="light"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>내 후기 - 서울미녀</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{--pri:#AE1932;}*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:640px;margin:0 auto;background:var(--bs-body-bg,#f8f9fa);color:var(--bs-body-color);padding-bottom:calc(77px + env(safe-area-inset-bottom,0px));}
.header{padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);position:relative;}
.header .back{width:36px;height:36px;border:none;background:none;font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;position:relative;z-index:1;}
.header h1{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;white-space:nowrap;}
.filter-tabs{display:flex;background:var(--bs-body-bg,#fff);border-bottom:1px solid var(--bs-border-color,#eee);}
.filter-tab{flex:1;padding:12px;text-align:center;font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;cursor:pointer;color:var(--bs-secondary-color,#888);border-bottom:2px solid transparent;text-decoration:none;}
.filter-tab.active{color:var(--pri);border-bottom-color:var(--pri);}
.writable-card{background:var(--bs-body-bg,#fff);border-bottom:1px solid var(--bs-border-color,#eee);padding:16px;display:flex;gap:12px;}
.writable-card .wc-img{width:100px;height:130px;object-fit:cover;flex-shrink:0;background:var(--bs-tertiary-bg,#eee);}
.writable-card .wc-noimg{width:72px;height:72px;border-radius:10px;background:var(--bs-tertiary-bg,#eee);display:flex;align-items:center;justify-content:center;color:var(--bs-secondary-color,#bbb);font-size:calc(26px * var(--fs-scale,1) * var(--fs-page,1));flex-shrink:0;}
.writable-card .wc-info{flex:1;min-width:0;display:flex;flex-direction:column;}
.writable-card .wc-hospital{font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-body-color);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px;}
.writable-card .wc-treatment{font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px;}
.writable-card .wc-address{font-size:calc(11px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-secondary-color,#888);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:2px;}
.writable-card .wc-meta{font-size:calc(12px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-secondary-color,#888);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:8px;}
.writable-card .wc-info > .wc-write{margin-top:auto;}
.writable-card .wc-write{width:100%;display:block;text-align:center;padding:7px 16px;background: #FFEEEE;color:#AE1932;text-decoration:none;font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;white-space:nowrap;}
.writable-card .wc-rating{color:#f59e0b;}
.writable-card .wc-btnrow{display:flex;gap:8px;margin-top:auto;}
.writable-card .wc-btnrow > *{flex:1 1 0;min-width:0;}
.writable-card .wc-btnrow .wc-write{flex:1 1 0;width:auto;min-width:0;box-sizing:border-box;border:none;cursor:pointer;font-family:inherit;}
.writable-card .wc-btnrow .wc-write:not(.del){background:#fff;border:1px solid #AE1932;color:#AE1932;}
.writable-card .wc-btnrow .wc-write.del{background:#fff;border:1px solid #000;color:#000;}
.empty{text-align:center;padding:60px 20px;color:var(--bs-secondary-color);}
</style><?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head><body>
<div class="header"><button class="back" onclick="location.href='mypage.php'"><i class="bi bi-chevron-left"></i></button><h1>내가 작성한 후기</h1></div>

<div class="filter-tabs">
    <a href="?tab=writable" class="filter-tab <?php echo $tab === 'writable' ? 'active' : ''; ?>">작성가능</a>
    <a href="?tab=done" class="filter-tab <?php echo $tab === 'done' ? 'active' : ''; ?>">작성완료</a>
</div>

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
            <div class="wc-address"> <?php echo htmlspecialchars($w['address']); ?></div>
            <?php endif; ?>
            <div class="wc-meta"><?php echo htmlspecialchars($w['doctor_name'] ?? ''); ?><?php echo !empty($w['doctor_name']) ? ' | ' : ''; ?><?php echo !empty($w['booking_date']) ? htmlspecialchars(str_replace('-', '.', $w['booking_date'])) : ''; ?></div>
            <a href="review_write.php?hospital_id=<?php echo (int)$w['hospital_id']; ?>&booking_id=<?php echo (int)$w['id']; ?>" class="wc-write">후기 작성</a>
        </div>
    </div>
    <?php endforeach; endif; ?>
<?php else: ?>
    <?php if (empty($reviews)): ?>
    <div class="empty"><i class="bi bi-chat-square-text" style="font-size:48px;display:block;margin-bottom:12px;"></i>작성한 후기가 없습니다<br><a href="review_write.php" style="color:var(--pri);">후기 작성하기</a></div>
    <?php else: foreach ($reviews as $r): ?>
    <div class="writable-card">
        <?php if (!empty($r['main_image_url'])): ?>
        <img class="wc-img" src="<?php echo htmlspecialchars($r['main_image_url']); ?>" alt="">
        <?php else: ?>
        <div class="wc-noimg"><i class="bi bi-building"></i></div>
        <?php endif; ?>
        <div class="wc-info">
            <div class="wc-hospital"><?php echo htmlspecialchars($r['hospital_name'] ?? ''); ?></div>
            <?php if (!empty($r['treatments'])): ?>
            <div class="wc-treatment"><?php echo htmlspecialchars($r['treatments']); ?></div>
            <?php elseif ($r['rating'] !== null): ?>
            <div class="wc-treatment wc-rating"><?php echo str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']); ?></div>
            <?php endif; ?>
            <?php if (!empty($r['address'])): ?>
            <div class="wc-address"> <?php echo htmlspecialchars($r['address']); ?></div>
            <?php endif; ?>
            <div class="wc-btnrow">
                <a href="review_detail.php?id=<?php echo $r['id']; ?>" class="wc-write">보기</a>
                <form method="post" onsubmit="return confirm('삭제하시겠습니까?')" style="flex:1;display:flex;"><input type="hidden" name="action" value="delete"><input type="hidden" name="review_id" value="<?php echo $r['id']; ?>"><button type="submit" class="wc-write del">삭제</button></form>
            </div>
        </div>
    </div>
    <?php endforeach; endif; ?>
<?php endif; ?>
<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
</body></html>
