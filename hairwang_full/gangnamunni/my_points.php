<?php
/**
 * 내 포인트 내역 페이지
 */
require_once __DIR__ . '/includes/init.php';

if (!is_gangnamunni_logged_in()) {
    header('Location: auth/login.php?url=' . urlencode('/hairwang/app/my_points.php'));
    exit;
}

$m = get_gangnamunni_member();
$mb_no = (int)($m['mb_no'] ?? 0);

// 포인트 원장(#포인트제도): 잔액은 마지막 거래 행의 스냅샷을 사용(SUM 미사용).
// 원장 미설치 등으로 조회 실패 시에도 페이지는 0P/빈 내역으로 정상 렌더.
require_once __DIR__ . '/includes/points_ledger.php';
$balance = points_balance_safe($connect, $mb_no);

$CREDIT_TYPES = "'earn','refund','admin_add','admin'";
$DEBIT_TYPES  = "'use','expire','admin_sub','earn_cancel'";

// 내역 목록
$logs = [];
$lq = mysqli_prepare($connect, "SELECT * FROM gangnamunni_points WHERE member_id=? ORDER BY created_at DESC, id DESC LIMIT 50");
if ($lq) { mysqli_stmt_bind_param($lq,'i',$mb_no); mysqli_stmt_execute($lq); $r=mysqli_stmt_get_result($lq); while($row=mysqli_fetch_assoc($r)) $logs[]=$row; mysqli_stmt_close($lq); }

// 이번 달 적립/차감 합계(표시용, 실패 시 0)
$month_earn = 0; $month_use = 0;
// 가장 가까운 소멸 예정일/금액. lot 미구성 시 FIFO 추정(해당일까지 만료되는 적립 합 - 전체 차감 합, 잔액 상한)
$exp_date = null; $exp_amount = 0;
try {
    $month_start = date('Y-m-01 00:00:00');
    $sq = mysqli_prepare($connect, "SELECT
        COALESCE(SUM(CASE WHEN type IN ($CREDIT_TYPES) THEN amount ELSE 0 END),0) AS e,
        COALESCE(SUM(CASE WHEN type IN ($DEBIT_TYPES)  THEN amount ELSE 0 END),0) AS u
        FROM gangnamunni_points WHERE member_id=? AND created_at >= ?");
    if ($sq) {
        mysqli_stmt_bind_param($sq,'is',$mb_no,$month_start); mysqli_stmt_execute($sq);
        $sr = mysqli_fetch_assoc(mysqli_stmt_get_result($sq)) ?: [];
        $month_earn = (int)($sr['e'] ?? 0); $month_use = (int)($sr['u'] ?? 0);
        mysqli_stmt_close($sq);
    }

    // v2: 적립 단위(lot)가 구성돼 있으면 정확한 값 사용
    $nx = function_exists('points_next_expiry') ? points_next_expiry($connect, $mb_no) : null;
    if ($nx !== null) {
        if (!empty($nx['date']) && $nx['amount'] > 0) { $exp_date = $nx['date']; $exp_amount = (int)$nx['amount']; }
    } elseif ($balance > 0) {
        $today_d = date('Y-m-d');
        $nq = mysqli_prepare($connect, "SELECT MIN(expires_at) AS d FROM gangnamunni_points
            WHERE member_id=? AND type IN ($CREDIT_TYPES) AND expires_at IS NOT NULL AND expires_at >= ?");
        if ($nq) {
            mysqli_stmt_bind_param($nq,'is',$mb_no,$today_d); mysqli_stmt_execute($nq);
            $nr = mysqli_fetch_assoc(mysqli_stmt_get_result($nq)) ?: [];
            mysqli_stmt_close($nq);
            if (!empty($nr['d'])) {
                $d = $nr['d'];
                $aq = mysqli_prepare($connect, "SELECT
                    COALESCE(SUM(CASE WHEN type IN ($CREDIT_TYPES) AND expires_at IS NOT NULL AND expires_at <= ? THEN amount ELSE 0 END),0) AS c,
                    COALESCE(SUM(CASE WHEN type IN ($DEBIT_TYPES) THEN amount ELSE 0 END),0) AS u
                    FROM gangnamunni_points WHERE member_id=?");
                if ($aq) {
                    mysqli_stmt_bind_param($aq,'si',$d,$mb_no); mysqli_stmt_execute($aq);
                    $ar = mysqli_fetch_assoc(mysqli_stmt_get_result($aq)) ?: [];
                    mysqli_stmt_close($aq);
                    $exp_amount = min($balance, max(0, (int)($ar['c'] ?? 0) - (int)($ar['u'] ?? 0)));
                    if ($exp_amount > 0) $exp_date = $d;
                }
            }
        }
    }
} catch (\Throwable $e) { error_log('[my_points] summary unavailable: ' . $e->getMessage()); }

// 유형 라벨
$type_label = [
    'earn' => '적립', 'admin_add' => '지급', 'admin' => '지급', 'refund' => '환불',
    'use' => '사용', 'admin_sub' => '차감', 'expire' => '소멸', 'earn_cancel' => '회수',
];

if (!function_exists('term')) require_once __DIR__ . '/includes/industry_terms.php';
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>포인트 — <?php echo htmlspecialchars(site_name()); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{
  --pri:#AE1932;
  --bg:#fff;--band:#f2f2f2;--line:#e5e5e5;--line-2:#cfcfcf;--text:#111;--sub:#444;--muted:#9a9a9a;
  --fs:calc(var(--fs-scale,1) * var(--fs-page,1));
}
[data-bs-theme="dark"]{
  --pri:#e0566c;
  --bg:#16181b;--band:#0d0e10;--line:#2c2f34;--line-2:#3a3e44;--text:#ececec;--sub:#c4c4c4;--muted:#8a8f96;
}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Noto Sans KR',-apple-system,BlinkMacSystemFont,'Apple SD Gothic Neo',sans-serif;max-width:640px;margin:0 auto;background:var(--bg);color:var(--text);padding-bottom:80px;-webkit-font-smoothing:antialiased;letter-spacing:-.2px;word-break:keep-all;overflow-wrap:anywhere;}
button{font-family:inherit;}

/* 헤더 */
.header{position:sticky;top:0;z-index:10;height:56px;display:flex;align-items:center;justify-content:center;background:var(--bg);}
.header .back{position:absolute;left:8px;top:8px;width:40px;height:40px;border:none;background:none;color:inherit;font-size:calc(22px * var(--fs));cursor:pointer;}
.header h1{font-size:calc(18px * var(--fs));font-weight:500;}

/* 요약 */
.summary{padding:28px 24px 26px;}
.summary .cap{font-size:calc(15px * var(--fs));font-weight:500;}
.summary .bal{margin-top:6px;color:var(--pri);font-size:calc(36px * var(--fs));font-weight:700;line-height:1.2;letter-spacing:-.5px;}
.summary .bal small{font-size:.6em;font-weight:500;margin-left:4px;}
.exp{margin-top:34px;display:flex;justify-content:space-between;font-size:calc(15px * var(--fs));font-weight:500;}
.boxes{margin-top:18px;display:grid;grid-template-columns:1fr 1fr;gap:16px;}
.box{border:1px solid var(--line);padding:22px 20px;}
.box .k{font-size:calc(15px * var(--fs));font-weight:500;}
.box .v{margin-top:6px;font-size:calc(22px * var(--fs));font-weight:500;}
.summary .note{margin-top:14px;font-size:calc(14px * var(--fs));color:var(--sub);}

.band{height:12px;background:var(--band);}

/* 탭 */
.tabs{display:flex;margin:0 24px;border-bottom:1px solid var(--line-2);}
.tab{flex:1;height:64px;border:none;background:none;color:var(--text);font-size:calc(18px * var(--fs));font-weight:400;cursor:pointer;position:relative;}
.tab.active{color:var(--pri);font-weight:500;}
.tab.active::after{content:"";position:absolute;left:0;right:0;bottom:-1px;height:2px;background:var(--pri);}

.count{padding:30px 24px 14px;font-size:calc(15px * var(--fs));font-weight:500;}

/* 내역 */
.list{padding:0 48px 12px 48px;}
.item{padding:16px 0 18px;display:flex;gap:12px;}
.item .l{flex:1;min-width:0;}
.item .r{text-align:right;flex-shrink:0;}
.title{display:flex;align-items:center;gap:8px;font-size:calc(16px * var(--fs));}
.title .t{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.tag{flex-shrink:0;display:inline-flex;align-items:center;height:30px;padding:0 6px;border:1px solid var(--line-2);color:var(--muted);font-size:calc(14px * var(--fs));}
.tag.earn{border-color:var(--pri);color:var(--pri);}
.meta{margin-top:10px;font-size:calc(13px * var(--fs));color:var(--muted);}
.amt{height:30px;display:flex;align-items:center;justify-content:flex-end;font-size:calc(16px * var(--fs));}
.amt.earn{color:var(--pri);}
.after{margin-top:10px;font-size:calc(13px * var(--fs));color:var(--muted);}

.empty{padding:56px 24px 64px;text-align:center;color:var(--muted);font-size:calc(14px * var(--fs));}
.more{padding:0 24px 20px;text-align:center;font-size:calc(12px * var(--fs));color:var(--muted);}

/* 정책 */
.policy-row{width:100%;display:flex;align-items:center;justify-content:space-between;padding:22px 24px 22px 36px;border:none;background:var(--bg);color:var(--text);font-size:calc(18px * var(--fs));font-weight:500;cursor:pointer;text-decoration:none;}
.policy-row i{font-size:calc(18px * var(--fs));}
</style>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
<div class="header">
    <button class="back" onclick="history.back()" aria-label="뒤로가기"><i class="bi bi-chevron-left"></i></button>
    <h1>포인트</h1>
</div>

<section class="summary">
    <div class="cap">사용 가능 포인트</div>
    <div class="bal"><?php echo number_format($balance); ?><small>P</small></div>

    <?php if ($exp_date): ?>
    <div class="exp">
        <span><?php echo (int)date('n', strtotime($exp_date)); ?>월 <?php echo (int)date('j', strtotime($exp_date)); ?>일 소멸 예정</span>
        <span><?php echo number_format($exp_amount); ?>P</span>
    </div>
    <?php endif; ?>

    <div class="boxes"<?php if (!$exp_date) echo ' style="margin-top:30px"'; ?>>
        <div class="box"><div class="k">이번 달 적립</div><div class="v">+<?php echo number_format($month_earn); ?>P</div></div>
        <div class="box"><div class="k">이번 달 차감</div><div class="v">-<?php echo number_format($month_use); ?>P</div></div>
    </div>
    <p class="note">사용 기한은 적립건마다 달라요. 내역에서 건별 기한을 확인하세요.</p>
</section>

<div class="band"></div>

<div class="tabs" role="tablist">
    <button class="tab active" data-f="all" role="tab">전체</button>
    <button class="tab" data-f="earn" role="tab">적립</button>
    <button class="tab" data-f="use" role="tab">차감</button>
</div>

<div class="count">총 <span id="cnt"><?php echo count($logs); ?></span>건</div>

<?php if (empty($logs)): ?>
<div class="empty">포인트 내역이 없습니다.</div>
<?php else: ?>
<div class="list" id="list">
<?php foreach ($logs as $log):
    $type = (string)$log['type'];
    $is_earn = points_is_credit($type);   // earn/refund/admin_add(+구 'admin') = 적립
    $label = $type_label[$type] ?? ($is_earn ? '적립' : '사용');
    $reason = trim((string)($log['reason'] ?? '')) ?: $label;
    $meta = date('m.d H:i', strtotime($log['created_at']));
    if ($is_earn && !empty($log['expires_at'])) $meta .= ' · 사용 기한 ' . date('Y.m.d', strtotime($log['expires_at']));
?>
    <div class="item" data-k="<?php echo $is_earn ? 'earn' : 'use'; ?>">
        <div class="l">
            <div class="title"><span class="tag<?php echo $type === 'earn' ? ' earn' : ''; ?>"><?php echo $label; ?></span><span class="t"><?php echo htmlspecialchars($reason); ?></span></div>
            <div class="meta"><?php echo $meta; ?></div>
        </div>
        <div class="r">
            <div class="amt<?php echo $is_earn ? ' earn' : ''; ?>"><?php echo ($is_earn ? '+' : '-') . number_format(abs((int)$log['amount'])); ?></div>
            <?php if (isset($log['balance'])): ?><div class="after"><?php echo number_format((int)$log['balance']); ?>P</div><?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>
<div class="empty" id="filterEmpty" style="display:none">해당 내역이 없습니다.</div>
<?php if (count($logs) >= 50): ?><div class="more">최근 50건까지 표시됩니다.</div><?php endif; ?>
<?php endif; ?>

<div class="band"></div>
<a class="policy-row" href="my_points_policy.php">포인트 정책 보기<i class="bi bi-chevron-right"></i></a>

<script>
(function(){
  var tabs=document.querySelectorAll('.tab'),items=document.querySelectorAll('#list .item'),cnt=document.getElementById('cnt'),empty=document.getElementById('filterEmpty');
  tabs.forEach(function(t){t.addEventListener('click',function(){
    tabs.forEach(function(x){x.classList.toggle('active',x===t);});
    var f=t.getAttribute('data-f'),n=0;
    items.forEach(function(it){var ok=f==='all'||it.getAttribute('data-k')===f;it.style.display=ok?'':'none';if(ok)n++;});
    cnt.textContent=n; if(empty) empty.style.display=(items.length&&!n)?'':'none';
  });});
})();
</script>

<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
</body>
</html>
<?php mysqli_close($connect); ?>
