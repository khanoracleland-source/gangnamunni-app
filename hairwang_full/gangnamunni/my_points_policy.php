<?php
/**
 * 포인트 정책 페이지 (내 포인트 > 포인트 정책 보기)
 *
 * 탭: 적립 / 회수 / 소멸 / 사용 / 환불  (?tab=earn|revoke|expire|use|refund 로 바로 열기 가능)
 * 문구·금액은 아래 $POLICY 배열에서 관리한다(운영 정책 변경 시 이 배열만 수정).
 */
require_once __DIR__ . '/includes/init.php';

// 포인트 메뉴 일시 중지(includes/points_pause.php): 주소로 직접 들어와도 마이페이지로 돌려보낸다.
require_once __DIR__ . '/includes/points_pause.php';
if (POINTS_MENU_HIDDEN) { header('Location: mypage.php'); exit; }
require_once __DIR__ . '/includes/points_ledger.php';

require_once __DIR__ . '/includes/points_policy_engine.php';
$expire_days = points_v2_on($connect, 'v2_policy_enabled')
    ? (int)points_setting_safe($connect, 'v2_expire_days', '90')
    : (int)points_setting_safe($connect, 'expire_days', '365');

// 적립 유형(상세 페이지 링크): includes/points_policy_content.php 공용
$EARN_ITEMS = [];
foreach ((require __DIR__ . '/includes/points_policy_content.php') as $k => $e) {
    $EARN_ITEMS[] = [$e['label'], $e['summary'], '', 'my_points_policy_detail.php?type=' . $k];
}

$POLICY = [
    'earn' => [
        'tab'   => '적립',
        'title' => '포인트 적립',
        'desc'  => '후기 작성, 앱 결제 등 상황에 따라 포인트가 적립됩니다. 적립 기준과 상황은 별도 고지 없이 추가·변경될 수 있습니다.',
        'sub'   => '적립 유형별 세부 정책',
        'items' => $EARN_ITEMS,
    ],
    'revoke' => [
        'tab'   => '회수',
        'title' => '포인트 회수',
        'desc'  => '적립 사유가 취소되면 해당 건으로 적립된 포인트가 회수됩니다.',
        'sub'   => '회수 사유',
        'items' => [
            ['후기 삭제·거부', '적립 금액 전액', '포인트가 적립된 후기를 삭제하거나, 운영 정책 위반으로 후기가 거부되면 해당 후기로 적립된 포인트가 회수됩니다.'],
            ['예약 취소',      '적립 금액 전액', '포인트가 적립된 예약이 취소되면 해당 예약으로 적립된 포인트가 회수됩니다.'],
        ],
    ],
    'expire' => [
        'tab'   => '소멸',
        'title' => '포인트 소멸',
        'desc'  => '사용 기한이 지난 포인트는 자동으로 소멸되며, 소멸된 포인트는 복구되지 않습니다.',
        'sub'   => '소멸 기준',
        'items' => [
            ['사용 기한', $expire_days > 0 ? '적립일로부터 ' . number_format($expire_days) . '일' : '적립건별로 상이', '포인트는 적립건마다 사용 기한이 있으며, 기한이 지나면 남은 포인트가 소멸됩니다. 건별 기한은 포인트 내역에서 확인할 수 있습니다.'],
            ['소멸 순서',  '먼저 적립된 순',  '사용 기한이 먼저 도래하는 포인트부터 소멸됩니다.'],
        ],
    ],
    'use' => [
        'tab'   => '사용',
        'title' => '포인트 사용',
        'desc'  => '보유 포인트는 앱 결제 시 현금처럼 사용할 수 있습니다.',
        'sub'   => '사용 기준',
        'items' => [
            ['사용 단위', '1P = 1원',        '결제 금액에서 사용한 포인트만큼 차감됩니다.'],
            ['사용 순서', '기한 임박 순',    '사용 기한이 먼저 도래하는 포인트부터 사용됩니다.'],
        ],
    ],
    'refund' => [
        'tab'   => '환불',
        'title' => '포인트 환불',
        'desc'  => '포인트를 사용한 결제가 취소되면 사용한 포인트가 다시 적립됩니다.',
        'sub'   => '환불 기준',
        'items' => [
            ['결제 취소', '사용 포인트 복구', '결제 취소 시 사용한 포인트가 보유 포인트로 복구됩니다. 단, 사용 기한이 지난 포인트는 복구 즉시 소멸될 수 있습니다.'],
            ['현금 환불', '불가',             '포인트는 현금으로 환불되지 않습니다.'],
        ],
    ],
];

$tab = $_GET['tab'] ?? 'earn';
if (!isset($POLICY[$tab])) $tab = 'earn';

if (!function_exists('term')) require_once __DIR__ . '/includes/industry_terms.php';
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>포인트 정책 — <?php echo htmlspecialchars(site_name()); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{
  --pri:#AC1830;
  --bg:#fff;--band:#f2f2f2;--line:#e5e5e5;--line-2:#b9b9b9;--text:#111;--sub:#444;--muted:#9a9a9a;
  --fs:calc(var(--fs-scale,1) * var(--fs-page,1));
}
[data-bs-theme="dark"]{
  --pri:#e0566c;
  --bg:#16181b;--band:#0d0e10;--line:#2c2f34;--line-2:#4a4e55;--text:#ececec;--sub:#c4c4c4;--muted:#8a8f96;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Noto Sans KR',-apple-system,BlinkMacSystemFont,'Apple SD Gothic Neo',sans-serif;max-width:640px;margin:0 auto;background:var(--bg);color:var(--text);padding-bottom:80px;-webkit-font-smoothing:antialiased;letter-spacing:-.2px;word-break:keep-all;overflow-wrap:anywhere;}
button,a{font-family:inherit;color:inherit;}

.header{position:sticky;top:0;z-index:10;height:56px;display:flex;align-items:center;justify-content:center;background:var(--bg);}
.header .back{position:absolute;left:8px;top:8px;width:40px;height:40px;border:none;background:none;font-size:calc(22px * var(--fs));cursor:pointer;}
.header h1{font-size:calc(18px * var(--fs));font-weight:500;}

.tabs{position:sticky;top:56px;z-index:9;background:var(--bg);display:grid;grid-template-columns:repeat(5,1fr);gap:14px;padding:14px 24px 24px;}
.tab{height:30px;border:1px solid var(--line-2);border-radius:4px;background:var(--bg);color:var(--muted);font-size:calc(14px * var(--fs));cursor:pointer;}
.tab.active{border-color:var(--pri);color:var(--pri);font-weight:500;}

.band{height:12px;background:var(--band);}

.pane{display:none;padding:22px 24px 26px;}
.pane.active{display:block;}
.pane h2{font-size:calc(16px * var(--fs));font-weight:700;}
.pane .desc{margin-top:8px;font-size:calc(12.5px * var(--fs));color:var(--sub);line-height:1.6;}
.pane h3{margin:28px 12px 0;font-size:calc(16px * var(--fs));font-weight:500;}

.rows{padding:0 12px;}
.row{border:none;}
.row .summary{text-decoration:none;color:inherit;list-style:none;cursor:pointer;display:flex;align-items:center;gap:26px;min-height:70px;font-size:calc(16px * var(--fs));}
.row:first-child .summary{min-height:50px;}
.row summary::-webkit-details-marker{display:none;}
.row .summary .v{color:var(--pri);}
.row .summary i{margin-left:auto;font-size:calc(18px * var(--fs));transition:transform .2s;}
.row[open] .summary i{transform:rotate(90deg);}
.row p{margin:-6px 0 14px;padding:12px 14px;background:var(--band);font-size:calc(13px * var(--fs));color:var(--sub);line-height:1.6;}

.facts{margin-top:18px;padding:0 12px;}
.fact{display:flex;justify-content:space-between;align-items:center;min-height:52px;font-size:calc(16px * var(--fs));font-weight:500;}
.facts .note{margin-top:6px;font-size:calc(12.5px * var(--fs));color:var(--sub);line-height:1.6;}

.link-row{display:flex;align-items:center;justify-content:space-between;padding:22px 36px;font-size:calc(18px * var(--fs));font-weight:500;text-decoration:none;}
.link-row i{font-size:calc(18px * var(--fs));}
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
<div class="header">
    <button class="back" onclick="if(history.length>1){history.back();}else{location.href='my_points.php';}" aria-label="뒤로가기"><i class="bi bi-chevron-left"></i></button>
    <h1>포인트 정책</h1>
</div>

<div class="tabs" role="tablist">
<?php foreach ($POLICY as $key => $p): ?>
    <button class="tab<?php echo $key === $tab ? ' active' : ''; ?>" data-tab="<?php echo $key; ?>" role="tab"><?php echo htmlspecialchars($p['tab']); ?></button>
<?php endforeach; ?>
</div>

<div class="band"></div>

<?php foreach ($POLICY as $key => $p): ?>
<section class="pane<?php echo $key === $tab ? ' active' : ''; ?>" id="pane-<?php echo $key; ?>" role="tabpanel">
    <h2><?php echo htmlspecialchars($p['title']); ?></h2>
    <p class="desc"><?php echo htmlspecialchars($p['desc']); ?></p>

    <h3><?php echo htmlspecialchars($p['sub']); ?></h3>
    <div class="rows">
    <?php foreach ($p['items'] as $it): ?>
        <?php if (!empty($it[3])): ?>
        <div class="row"><a class="summary" href="<?php echo htmlspecialchars($it[3]); ?>"><span><?php echo htmlspecialchars($it[0]); ?></span><span class="v"><?php echo htmlspecialchars($it[1]); ?></span><i class="bi bi-chevron-right"></i></a></div>
        <?php else: ?>
        <details class="row">
            <summary class="summary"><span><?php echo htmlspecialchars($it[0]); ?></span><span class="v"><?php echo htmlspecialchars($it[1]); ?></span><i class="bi bi-chevron-right"></i></summary>
            <p><?php echo htmlspecialchars($it[2]); ?></p>
        </details>
        <?php endif; ?>
    <?php endforeach; ?>
    </div>

    <div class="facts">
        <div class="fact"><span>충전·출금</span><span>불가</span></div>
        <div class="fact"><span>사용 기한</span><span>적립건별로 상이</span></div>
        <p class="note">적립건별 사용 기한은 포인트 내역에서 확인할 수 있습니다. 그 외 적립 방법은 고객센터로 문의해 주세요.</p>
    </div>
</section>
<?php endforeach; ?>

<div class="band"></div>
<a class="link-row" href="qna.php">포인트 문의하기<i class="bi bi-chevron-right"></i></a>

<script>
(function(){
  var tabs=document.querySelectorAll('.tab');
  tabs.forEach(function(t){t.addEventListener('click',function(){
    var k=t.getAttribute('data-tab');
    tabs.forEach(function(x){x.classList.toggle('active',x===t);});
    document.querySelectorAll('.pane').forEach(function(p){p.classList.toggle('active',p.id==='pane-'+k);});
    try{history.replaceState(null,'','?tab='+k);}catch(e){}
  });});
})();
</script>

<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
</body>
</html>
<?php if (isset($connect) && $connect) mysqli_close($connect); ?>
