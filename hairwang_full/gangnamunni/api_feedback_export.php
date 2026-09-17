<?php
/**
 * 피드백 내보내기 API
 * GET ?format=csv|html_pdf|html_word
 * @created 2026-04-07
 */
require_once __DIR__ . '/dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('DB 연결 실패');
mysqli_set_charset($connect, 'utf8mb4');

$format = $_GET['format'] ?? 'csv';
$status_filter = $_GET['status'] ?? '';

$where = '1=1';
if ($status_filter) $where .= " AND status='" . mysqli_real_escape_string($connect, $status_filter) . "'";

$result = mysqli_query($connect, "SELECT * FROM gangnamunni_dev_feedback WHERE {$where} ORDER BY FIELD(priority,'urgent','high','medium','low'), created_at DESC");
$items = [];
while ($row = mysqli_fetch_assoc($result)) $items[] = $row;

// ===== CSV (엑셀 호환) =====
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="feedback_' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM for Excel

    $fp = fopen('php://output', 'w');
    fputcsv($fp, ['ID', '페이지', '페이지URL', '섹션', '유형', '우선순위', '내용', '스크린샷', '상태', '작성자', '개발자메모', '작성일']);
    foreach ($items as $fb) {
        $ss_url = $fb['screenshot_url'] ? 'https://hms.seoulbeauty.app/hairwang/app/' . $fb['screenshot_url'] : '';
        fputcsv($fp, [
            $fb['id'], $fb['page_id'], $fb['page_url'], $fb['section'],
            $fb['feedback_type'], $fb['priority'], $fb['content'],
            $ss_url, $fb['status'], $fb['submitted_by'], $fb['dev_note'], $fb['created_at']
        ]);
    }
    fclose($fp);
    exit;
}

// ===== HTML (PDF/Word 공용) =====
$type_labels = ['bug'=>'버그','improve'=>'개선','feature'=>'기능','question'=>'질문','other'=>'기타'];
$pri_labels = ['urgent'=>'긴급','high'=>'높음','medium'=>'보통','low'=>'낮음'];
$stat_labels = ['open'=>'대기','in_progress'=>'진행중','done'=>'완료','rejected'=>'반려'];
$stat_colors = ['open'=>'#ffc107','in_progress'=>'#0dcaf0','done'=>'#198754','rejected'=>'#6c757d'];

$is_word = ($format === 'html_word');
$title = '서울미녀 피드백 보고서';
$filename = 'feedback_report_' . date('Ymd');

if ($is_word) {
    header('Content-Type: application/msword; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.doc"');
} else {
    // PDF용 HTML (브라우저에서 인쇄→PDF 저장)
    header('Content-Type: text/html; charset=UTF-8');
}
?>
<!DOCTYPE html>
<html><head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>
<meta charset="UTF-8">
<title><?= $title ?></title>
<style>
body { font-family: 'Malgun Gothic', sans-serif; font-size: 12px; color: #333; margin: 20px; }
h1 { font-size: 20px; border-bottom: 2px solid #AE1932; padding-bottom: 8px; }
.summary { display: flex; gap: 20px; margin: 15px 0; }
.summary-item { text-align: center; padding: 10px 20px; border: 1px solid #ddd; border-radius: 8px; }
.summary-num { font-size: 24px; font-weight: bold; }
.fb-card { border: 1px solid #ddd; border-radius: 8px; margin-bottom: 16px; page-break-inside: avoid; }
.fb-header { background: #f8f9fa; padding: 10px 16px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; }
.fb-body { padding: 12px 16px; }
.fb-body img { max-width: 100%; max-height: 300px; border: 1px solid #ddd; border-radius: 4px; margin-top: 8px; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 4px; color: #fff; font-size: 11px; }
.meta { color: #888; font-size: 11px; margin-top: 6px; }
@media print { .no-print { display: none; } body { margin: 0; } }
</style>
</head><body>
<h1><?= $title ?></h1>
<p>생성일: <?= date('Y-m-d H:i') ?> | 총 <?= count($items) ?>건</p>

<div class="summary">
    <?php
    $stats = ['open'=>0,'in_progress'=>0,'done'=>0,'rejected'=>0];
    foreach ($items as $fb) $stats[$fb['status']]++;
    foreach ($stats as $s => $c):
    ?>
    <div class="summary-item">
        <div class="summary-num" style="color:<?= $stat_colors[$s] ?>"><?= $c ?></div>
        <div><?= $stat_labels[$s] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (!$is_word): ?>
<div class="no-print" style="margin:10px 0;">
    <button onclick="window.print()" style="padding:8px 20px;background:#AE1932;color:#fff;border:none;border-radius:6px;cursor:pointer;">PDF로 저장 (Ctrl+P)</button>
</div>
<?php endif; ?>

<?php foreach ($items as $fb):
    $type = $type_labels[$fb['feedback_type']] ?? $fb['feedback_type'];
    $pri = $pri_labels[$fb['priority']] ?? $fb['priority'];
    $stat = $stat_labels[$fb['status']] ?? $fb['status'];
    $sc = $stat_colors[$fb['status']] ?? '#888';
?>
<div class="fb-card">
    <div class="fb-header">
        <div>
            <strong>#<?= $fb['id'] ?></strong>
            <span class="badge" style="background:<?= $sc ?>"><?= $stat ?></span>
            <span class="badge" style="background:#6c757d"><?= $type ?></span>
            <span class="badge" style="background:<?= $fb['priority']==='urgent'?'#dc3545':($fb['priority']==='high'?'#fd7e14':'#6c757d') ?>"><?= $pri ?></span>
        </div>
        <small><?= $fb['created_at'] ?></small>
    </div>
    <div class="fb-body">
        <div><strong>페이지:</strong> <?= htmlspecialchars($fb['page_title'] ?: $fb['page_id']) ?></div>
        <?php if ($fb['section']): ?><div><strong>섹션:</strong> <?= htmlspecialchars($fb['section']) ?></div><?php endif; ?>
        <div style="margin-top:8px;white-space:pre-wrap;"><?= htmlspecialchars($fb['content']) ?></div>
        <?php if ($fb['screenshot_url']): ?>
        <div><img src="https://hms.seoulbeauty.app/hairwang/app/<?= htmlspecialchars($fb['screenshot_url']) ?>" alt="스크린샷"></div>
        <?php endif; ?>
        <?php if ($fb['dev_note']): ?>
        <div style="margin-top:8px;padding:8px;background:#e8f5e9;border-radius:4px;"><strong>개발자 메모:</strong> <?= htmlspecialchars($fb['dev_note']) ?></div>
        <?php endif; ?>
        <div class="meta">작성자: <?= htmlspecialchars($fb['submitted_by'] ?? '') ?> | <?= htmlspecialchars($fb['page_url'] ?? '') ?></div>
    </div>
</div>
<?php endforeach; ?>

</body></html>
<?php
mysqli_close($connect);
