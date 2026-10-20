<?php
/**
 * 포인트 적립 유형별 상세 정책 (포인트 정책 > 적립 유형별 세부 정책 > 각 항목)
 * ?type=progress|photo|receipt|first|payment
 */
require_once __DIR__ . '/includes/init.php';

// 포인트 메뉴 일시 중지(includes/points_pause.php): 주소로 직접 들어와도 마이페이지로 돌려보낸다.
require_once __DIR__ . '/includes/points_pause.php';
if (POINTS_MENU_HIDDEN) { header('Location: mypage.php'); exit; }

$EARN_POLICY = require __DIR__ . '/includes/points_policy_content.php';
$type = (string)($_GET['type'] ?? '');
if (!isset($EARN_POLICY[$type])) {
    header('Location: my_points_policy.php');
    exit;
}
$p = $EARN_POLICY[$type];

if (!function_exists('term')) require_once __DIR__ . '/includes/industry_terms.php';
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title><?php echo htmlspecialchars($p['title']); ?> — <?php echo htmlspecialchars(site_name()); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{
  --pri:#AC1830;
  --bg:#fff;--line:#e2e2e2;--text:#111;--sub:#333;--muted:#9a9a9a;
  --fs:calc(var(--fs-scale,1) * var(--fs-page,1));
}
[data-bs-theme="dark"]{
  --pri:#e0566c;
  --bg:#16181b;--line:#2c2f34;--text:#ececec;--sub:#c4c4c4;--muted:#8a8f96;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Noto Sans KR',-apple-system,BlinkMacSystemFont,'Apple SD Gothic Neo',sans-serif;max-width:640px;margin:0 auto;background:var(--bg);color:var(--text);padding-bottom:80px;-webkit-font-smoothing:antialiased;letter-spacing:-.2px;word-break:keep-all;overflow-wrap:anywhere;}
a,button{font-family:inherit;color:inherit;}

.header{position:sticky;top:0;z-index:10;height:56px;display:flex;align-items:center;justify-content:center;background:var(--bg);padding:0 52px;}
.header .back{position:absolute;left:8px;top:8px;width:40px;height:40px;border:none;background:none;font-size:calc(22px * var(--fs));cursor:pointer;}
.header h1{font-size:calc(18px * var(--fs));font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}

.wrap{padding:0 20px;}
.point{padding:30px 0 16px;border-bottom:1px solid var(--line);}
.point .k{font-size:calc(15px * var(--fs));font-weight:500;}
.point .v{margin-top:6px;color:var(--pri);font-size:calc(21px * var(--fs));font-weight:700;letter-spacing:-.4px;}

.facts{padding:10px 0;border-bottom:1px solid var(--line);}
.fact{display:flex;justify-content:space-between;gap:16px;padding:9px 0;font-size:calc(15px * var(--fs));}
.fact b{font-weight:500;text-align:right;}

.sec{padding:0 10px;}
.sec h2{margin-top:28px;font-size:calc(15px * var(--fs));font-weight:500;}
.sec ul{margin-top:8px;list-style:none;}
.sec li{position:relative;padding:7px 0 7px 18px;font-size:calc(12.5px * var(--fs));color:var(--sub);line-height:1.75;}
.sec li::before{content:"";position:absolute;left:7px;top:calc(7px + .85em);width:3px;height:3px;border-radius:50%;background:currentColor;}

.notice{margin-top:30px;padding:14px 0;border-top:1px solid var(--line);border-bottom:1px solid var(--line);font-size:calc(14px * var(--fs));color:var(--sub);}
.link-row{display:flex;align-items:center;justify-content:space-between;padding:18px 12px;font-size:calc(17px * var(--fs));font-weight:500;text-decoration:none;}
.link-row i{font-size:calc(17px * var(--fs));}
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
<div class="header">
    <button class="back" onclick="if(history.length>1){history.back();}else{location.href='my_points_policy.php';}" aria-label="뒤로가기"><i class="bi bi-chevron-left"></i></button>
    <h1><?php echo htmlspecialchars($p['title']); ?></h1>
</div>

<div class="wrap">
    <div class="point">
        <div class="k">적립 포인트</div>
        <div class="v"><?php echo htmlspecialchars($p['point']); ?></div>
    </div>

    <div class="facts">
    <?php foreach ($p['facts'] as $f): ?>
        <div class="fact"><span><?php echo htmlspecialchars($f[0]); ?></span><b><?php echo htmlspecialchars($f[1]); ?></b></div>
    <?php endforeach; ?>
    </div>

    <?php foreach ($p['sections'] as $s): ?>
    <section class="sec">
        <h2><?php echo htmlspecialchars($s[0]); ?></h2>
        <ul><?php foreach ($s[1] as $li): ?><li><?php echo htmlspecialchars($li); ?></li><?php endforeach; ?></ul>
    </section>
    <?php endforeach; ?>

    <p class="notice">적립 금액과 기준은 사전 안내 없이 변경될 수 있습니다.</p>
    <a class="link-row" href="my_points_policy.php">포인트 정책 전체 보기<i class="bi bi-chevron-right"></i></a>
</div>

<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
</body>
</html>
<?php if (isset($connect) && $connect) mysqli_close($connect); ?>
