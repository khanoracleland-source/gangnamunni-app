<?php
/**
 * 경과 후기 상세: 회차별 경과 기록 추가 → 작성 완료(검수 요청) (#포인트제도 v2)
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/progress_review_common.php';
require_once __DIR__ . '/includes/photo_upload_helper.php';

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!is_gangnamunni_logged_in()) {
    header('Location: auth/login.php?url=' . urlencode(rtrim(SITE_BASE_PATH, '/') . '/progress_review.php?id=' . $id));
    exit;
}
pr_require_enabled($connect);

$m = get_gangnamunni_member();
$member_no = points_member_no($connect, (string)($m['mb_id'] ?? ''));
$p = pr_load($connect, $id, $member_no);
if (!$p) { header('Location: my_progress_reviews.php'); exit; }

$error = ''; $notice = '';
$need = points_v2_progress_required_entries($connect, $p['kind']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    pr_check_csrf();
    $act = (string)($_POST['action'] ?? '');
    $editable = pr_editable($connect, $p);

    if ($act === 'add_entry') {
        $content = trim((string)($_POST['content'] ?? ''));
        $edate = (string)($_POST['entry_date'] ?? date('Y-m-d'));
        if (!$editable) $error = '지금은 경과를 추가할 수 없습니다.';
        elseif (mb_strlen($content) < 20) $error = '경과 내용은 20자 이상 작성해주세요.';
        elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $edate) || strtotime($edate) > time()) $error = '기록 날짜를 확인해주세요.';
        elseif (!empty($p['procedure_date']) && strtotime($edate) < strtotime($p['procedure_date'])) $error = '기록 날짜는 시술일 이후여야 합니다.';
        else {
            $dup = mysqli_prepare($connect, "SELECT COUNT(*) c FROM gangnamunni_progress_entries WHERE progress_id = ? AND entry_date = ?");
            mysqli_stmt_bind_param($dup, 'is', $id, $edate);
            mysqli_stmt_execute($dup);
            $exists = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($dup))['c'] ?? 0);
            mysqli_stmt_close($dup);
            if ($exists > 0) $error = '같은 날짜의 경과가 이미 있습니다.';
        }
        if (!$error) {
            $st = mysqli_prepare($connect, "INSERT INTO gangnamunni_progress_entries (progress_id, entry_date, content) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($st, 'iss', $id, $edate, $content);
            mysqli_stmt_execute($st);
            $eid = (int)mysqli_insert_id($connect);
            mysqli_stmt_close($st);
            $up = handle_photo_upload($connect, ['type' => 'progress', 'record_id' => $eid, 'table' => 'gangnamunni_progress_entries', 'max_files' => 5]);
            if (empty($up['success'])) error_log('[progress_review] photo upload: ' . ($up['error'] ?? ''));
            header('Location: progress_review.php?id=' . $id . '&msg=added');
            exit;
        }
    } elseif ($act === 'delete_entry') {
        $eid = (int)($_POST['entry_id'] ?? 0);
        if ($editable && $eid > 0) {
            $st = mysqli_prepare($connect, "DELETE FROM gangnamunni_progress_entries WHERE id = ? AND progress_id = ?");
            mysqli_stmt_bind_param($st, 'ii', $eid, $id);
            mysqli_stmt_execute($st);
            mysqli_stmt_close($st);
        }
        header('Location: progress_review.php?id=' . $id);
        exit;
    } elseif ($act === 'submit') {
        if (!$editable) $error = '지금은 작성 완료할 수 없습니다.';
        elseif ((int)$p['entry_count'] < $need) $error = "경과를 {$need}회 이상 기록해야 작성 완료할 수 있습니다.";
        else {
            $st = mysqli_prepare($connect, "UPDATE gangnamunni_progress_reviews SET status='submitted', submitted_at=NOW(), reject_reason=NULL WHERE id = ? AND member_id = ? AND status IN ('writing','rejected')");
            mysqli_stmt_bind_param($st, 'ii', $id, $member_no);
            mysqli_stmt_execute($st);
            mysqli_stmt_close($st);
            header('Location: progress_review.php?id=' . $id . '&msg=submitted');
            exit;
        }
    } elseif ($act === 'delete') {
        $st = mysqli_prepare($connect, "UPDATE gangnamunni_progress_reviews SET status='deleted' WHERE id = ? AND member_id = ?");
        mysqli_stmt_bind_param($st, 'ii', $id, $member_no);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        // 반려 기준: 작성한 후기를 삭제하면 적립 회수
        try { points_revoke_earn($connect, 'progress', $id, '경과 후기 삭제 회수'); } catch (Throwable $e) { error_log('[progress_review] revoke: ' . $e->getMessage()); }
        header('Location: my_progress_reviews.php');
        exit;
    }
}
if (($_GET['msg'] ?? '') === 'added') $notice = '경과가 기록되었습니다.';
if (($_GET['msg'] ?? '') === 'submitted') $notice = '작성 완료되었습니다. 검수 후 영업일 기준 3일 내 적립됩니다.';

$entries = [];
$eq = mysqli_prepare($connect, "SELECT * FROM gangnamunni_progress_entries WHERE progress_id = ? ORDER BY entry_date, id");
mysqli_stmt_bind_param($eq, 'i', $id);
mysqli_stmt_execute($eq);
$r = mysqli_stmt_get_result($eq);
while ($row = mysqli_fetch_assoc($r)) $entries[] = $row;
mysqli_stmt_close($eq);

$editable = pr_editable($connect, $p);
$count = count($entries);
$pct = min(100, (int)round($count / max(1, $need) * 100));
$csrf = pr_csrf();

pr_page_open($p['title'], 'my_progress_reviews.php');
?>
<div class="pr-wrap">
    <div><span class="tag<?php echo $p['status'] === 'approved' ? ' pri' : ''; ?>"><?php echo pr_status_label($p['status']); ?></span><span class="tag"><?php echo pr_kind_label($p['kind']); ?></span></div>
    <div style="margin-top:10px;font-size:calc(14px * var(--fs));color:var(--sub)">
        <?php echo pr_h(trim(($p['hospital_name'] ?? '') . ' · ' . $p['treatment_name'], ' ·')); ?>
        <?php if (!empty($p['procedure_date'])): ?> · 시술일 <?php echo pr_h(date('Y.m.d', strtotime($p['procedure_date']))); ?><?php endif; ?>
    </div>
    <div style="margin-top:14px;font-size:calc(15px * var(--fs));font-weight:500">경과 <?php echo $count; ?> / <?php echo $need; ?>회</div>
    <div class="progress"><i style="width:<?php echo $pct; ?>%"></i></div>

    <?php if ($notice): ?><div class="alert"><?php echo pr_h($notice); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert err"><?php echo pr_h($error); ?></div><?php endif; ?>
    <?php if ($p['status'] === 'rejected'): ?>
        <div class="alert err">반려 사유: <?php echo pr_h($p['reject_reason'] ?: '기준 미충족'); ?><?php if ($editable): ?><br>작성일로부터 <?php echo points_v2_int($connect, 'v2_progress_resubmit_days', 30); ?>일 이내에 수정 후 다시 작성 완료할 수 있습니다.<?php endif; ?></div>
    <?php elseif ($p['status'] === 'approved'): ?>
        <div class="alert"><?php echo number_format((int)$p['awarded_point']); ?>P 적립이 완료되었습니다.</div>
    <?php elseif ($p['status'] === 'submitted'): ?>
        <div class="alert">검수 중입니다. 작성일 기준 3일(영업일) 내 적립됩니다.</div>
    <?php endif; ?>
</div>

<div class="band"></div>
<div class="pr-wrap">
    <?php if (empty($entries)): ?>
        <div class="pr-empty"><p>첫 경과를 기록해보세요.</p></div>
    <?php else: foreach ($entries as $i => $e):
        $photos = function_exists('get_photo_urls') ? get_photo_urls($e['photo_urls'] ?? '') : []; ?>
        <div class="entry">
            <div class="d"><?php echo ($i + 1); ?>회차 · <?php echo pr_h(date('Y.m.d', strtotime($e['entry_date']))); ?>
                <?php if (!empty($p['procedure_date'])): ?> (D+<?php echo max(0, (int)floor((strtotime($e['entry_date']) - strtotime($p['procedure_date'])) / 86400)); ?>)<?php endif; ?></div>
            <div class="c"><?php echo pr_h($e['content']); ?></div>
            <?php if (!empty($photos)): ?><div class="ph"><?php foreach ($photos as $u): ?><img src="<?php echo pr_h($u); ?>" alt="경과 사진" loading="lazy"><?php endforeach; ?></div><?php endif; ?>
            <?php if ($editable): ?>
            <form method="post" onsubmit="return confirm('이 경과를 삭제할까요?')" style="margin-top:8px">
                <input type="hidden" name="csrf_token" value="<?php echo pr_h($csrf); ?>"><input type="hidden" name="id" value="<?php echo (int)$id; ?>">
                <input type="hidden" name="action" value="delete_entry"><input type="hidden" name="entry_id" value="<?php echo (int)$e['id']; ?>">
                <button type="submit" style="border:none;background:none;color:var(--muted);font-size:calc(12.5px * var(--fs));cursor:pointer">삭제</button>
            </form>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
</div>

<?php if ($editable): ?>
<div class="band"></div>
<form class="pr-wrap" method="post" enctype="multipart/form-data">
    <h2 style="font-size:calc(16px * var(--fs));font-weight:700;margin-top:12px">경과 기록하기</h2>
    <input type="hidden" name="csrf_token" value="<?php echo pr_h($csrf); ?>"><input type="hidden" name="id" value="<?php echo (int)$id; ?>">
    <input type="hidden" name="action" value="add_entry">
    <div class="field"><label for="entry_date">날짜</label><input id="entry_date" type="date" name="entry_date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>"<?php if (!empty($p['procedure_date'])) echo ' min="' . pr_h($p['procedure_date']) . '"'; ?>></div>
    <div class="field"><label for="content">경과 내용</label><textarea id="content" name="content" minlength="20" maxlength="3000" placeholder="붓기·통증·변화 등 오늘의 경과를 20자 이상 기록해주세요." required></textarea></div>
    <div class="field"><label for="photos">사진 (최대 5장)</label><input id="photos" type="file" name="photos[]" accept="image/*" multiple>
        <div class="help">경과 확인이 가능한 시술·수술 관련 사진을 첨부해주세요. 선정성 기준을 통과하지 못한 사진은 적립에서 제외됩니다.</div>
        <label style="display:flex;gap:6px;align-items:center;margin-top:8px;font-weight:400"><input type="checkbox" name="photo_consent" value="1" style="width:auto"> 사진을 후기에 공개하는 데 동의합니다</label></div>
    <div style="margin-top:20px"><button class="btn line" type="submit">기록 추가</button></div>
</form>

<form class="pr-wrap" method="post" style="padding-top:0">
    <input type="hidden" name="csrf_token" value="<?php echo pr_h($csrf); ?>"><input type="hidden" name="id" value="<?php echo (int)$id; ?>">
    <input type="hidden" name="action" value="submit">
    <button class="btn" type="submit" <?php echo $count < $need ? 'disabled' : ''; ?>>작성 완료<?php echo $count < $need ? ' (' . ($need - $count) . '회 남음)' : ''; ?></button>
    <div class="help" style="text-align:center">작성 완료 후에는 검수가 끝날 때까지 수정할 수 없습니다.</div>
</form>
<?php endif; ?>

<?php if ($p['status'] !== 'submitted'): ?>
<form class="pr-wrap" method="post" onsubmit="return confirm('경과 후기를 삭제할까요? 적립된 포인트는 회수됩니다.')">
    <input type="hidden" name="csrf_token" value="<?php echo pr_h($csrf); ?>"><input type="hidden" name="id" value="<?php echo (int)$id; ?>">
    <input type="hidden" name="action" value="delete">
    <button type="submit" style="border:none;background:none;color:var(--muted);font-size:calc(13px * var(--fs));cursor:pointer;text-decoration:underline">경과 후기 삭제</button>
</form>
<?php endif; ?>
<?php
pr_page_close($connect);
