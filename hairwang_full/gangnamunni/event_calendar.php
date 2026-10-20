<?php
require_once __DIR__ . '/includes/init.php';
$month = intval($_GET['month'] ?? date('n'));
$year = intval($_GET['year'] ?? date('Y'));
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$events = [];
$start = "$year-" . str_pad($month,2,'0',STR_PAD_LEFT) . "-01";
$end = date('Y-m-t', strtotime($start));
$res = mysqli_query($connect, "SELECT * FROM gangnamunni_events WHERE is_active=1 AND ((start_date <= '$end' AND end_date >= '$start') OR (start_date BETWEEN '$start' AND '$end')) ORDER BY start_date");
if ($res) while ($r = mysqli_fetch_assoc($res)) $events[] = $r;

$event_dates = [];
foreach ($events as $e) {
    $sd = max($start, $e['start_date'] ?? $start);
    $ed = min($end, $e['end_date'] ?? $end);
    $d = new DateTime($sd);
    $de = new DateTime($ed);
    while ($d <= $de) { $event_dates[$d->format('j')][] = $e; $d->modify('+1 day'); }
}
$days_in_month = date('t', strtotime($start));
$first_dow = date('w', strtotime($start));
?>
<!DOCTYPE html><html lang="ko" data-bs-theme="light"><head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>이벤트 캘린더 - 서울미녀</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{--pri:#AC1830;}*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:640px;margin:0 auto;background:var(--bs-body-bg,#f8f9fa);color:var(--bs-body-color);}
.header{padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.header .back{width:36px;height:36px;border:none;background:none;font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;}
.header h1{font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;flex:1;}
.month-nav{display:flex;align-items:center;justify-content:center;padding:16px;gap:24px;}
.month-nav a{width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:var(--bs-tertiary-bg,#f0f0f0);text-decoration:none;color:inherit;font-size:calc(18px * var(--fs-scale,1) * var(--fs-page,1));}
.month-nav .current{font-size:calc(18px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;}
.cal-grid{display:grid;grid-template-columns:repeat(7,1fr);text-align:center;padding:0 8px;}
.cal-head{font-size:calc(11px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-secondary-color,#888);padding:8px 0;font-weight:600;}
.cal-day{aspect-ratio:1;display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));border-radius:8px;margin:2px;cursor:default;position:relative;}
.cal-day.today{background:var(--pri);color:#fff;font-weight:700;border-radius:50%;}
.cal-day.has-event{cursor:pointer;font-weight:600;}
.cal-day .dot{width:5px;height:5px;border-radius:50%;background:var(--pri);position:absolute;bottom:4px;}
.event-list{padding:12px 16px;}
.ev-card{padding:12px 14px;background:var(--bs-body-bg,#fff);border-radius:10px;border-left:3px solid var(--pri);margin-bottom:8px;font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));}
.ev-card .ev-title{font-weight:600;margin-bottom:2px;}
.ev-card .ev-date{font-size:calc(11px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-secondary-color,#888);}
</style><?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head><body>
<div class="header"><button class="back" onclick="history.back()"><i class="bi bi-chevron-left"></i></button><h1>이벤트 캘린더</h1></div>
<div class="month-nav">
    <a href="event_calendar.php?year=<?php echo $month==1?$year-1:$year; ?>&month=<?php echo $month==1?12:$month-1; ?>"><i class="bi bi-chevron-left"></i></a>
    <span class="current"><?php echo $year; ?>년 <?php echo $month; ?>월</span>
    <a href="event_calendar.php?year=<?php echo $month==12?$year+1:$year; ?>&month=<?php echo $month==12?1:$month+1; ?>"><i class="bi bi-chevron-right"></i></a>
</div>
<div class="cal-grid">
<?php foreach (['일','월','화','수','목','금','토'] as $d): ?><div class="cal-head"><?php echo $d; ?></div><?php endforeach; ?>
<?php for ($i = 0; $i < $first_dow; $i++): ?><div class="cal-day"></div><?php endfor; ?>
<?php for ($d = 1; $d <= $days_in_month; $d++):
    $is_today = ($d == date('j') && $month == date('n') && $year == date('Y'));
    $has = isset($event_dates[$d]);
?>
<div class="cal-day <?php echo $is_today?'today':''; ?> <?php echo $has?'has-event':''; ?>"><?php echo $d; ?><?php if ($has): ?><span class="dot"></span><?php endif; ?></div>
<?php endfor; ?>
</div>
<div class="event-list">
<h3 style="font-size:15px;font-weight:700;margin-bottom:12px;">이번 달 이벤트 (<?php echo count($events); ?>)</h3>
<?php foreach ($events as $e): ?>
<a href="event_detail.php?id=<?php echo $e['id']; ?>" class="ev-card" style="display:block;text-decoration:none;color:inherit;">
    <div class="ev-title"><?php echo htmlspecialchars($e['title']); ?></div>
    <div class="ev-date"><?php echo ($e['start_date'] ?? '').' ~ '.($e['end_date'] ?? ''); ?></div>
</a>
<?php endforeach; ?>
<?php if (empty($events)): ?><div style="text-align:center;padding:20px;color:var(--bs-secondary-color);">이번 달 이벤트가 없습니다</div><?php endif; ?>
</div>
<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
<?php if (isset($connect) && $connect) mysqli_close($connect); ?>
</body></html>
