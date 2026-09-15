<?php
session_start();
$admin_page_title = '단위 관리';
$admin_current = 'treatment_units';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/treatment_units.php';
if (!isset($connect) || !$connect) {
    $connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (!$connect) die('DB 연결 실패');
loadThemeSettings($connect);
require_admin_login();
require_permission('treatment_units');
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];
$error = '';
$units = [];
$ready = false;
$notice = $_SESSION['treatment_units_notice'] ?? '';
unset($_SESSION['treatment_units_notice']);
try {
    treatment_units_ensure_schema($connect);
    $ready = true;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($csrf_token, $token)) {
            throw new InvalidArgumentException('요청이 만료되었습니다. 페이지를 새로고침해주세요.');
        }
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            $name = $_POST['name'] ?? '';
            if (!is_string($name)) throw new InvalidArgumentException('단위를 입력해주세요.');
            treatment_units_add($connect, $name);
            $_SESSION['treatment_units_notice'] = '단위가 추가되었습니다. 이미 등록된 단위는 중복 추가되지 않습니다.';
        } elseif ($action === 'remove') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (!$id) throw new InvalidArgumentException('삭제할 단위를 선택해주세요.');
            treatment_units_remove($connect, $id);
            $_SESSION['treatment_units_notice'] = '단위가 삭제되었습니다. 기존 시술 가격의 단위는 유지됩니다.';
        } else {
            throw new InvalidArgumentException('올바르지 않은 요청입니다.');
        }
        header('Location: admin_treatment_units.php');
        exit;
    }
} catch (InvalidArgumentException $e) {
    $error = $e->getMessage();
} catch (Throwable $e) {
    error_log('[treatment_units] ' . $e->getMessage());
    $error = '단위를 불러오거나 저장하지 못했습니다. 잠시 후 다시 시도해주세요.';
}
if ($ready) {
    try {
        $result = mysqli_query($connect, 'SELECT id, name FROM gangnamunni_treatment_units WHERE is_active = 1 ORDER BY id');
        if (!$result) throw new RuntimeException('단위 조회 실패');
        $units = mysqli_fetch_all($result, MYSQLI_ASSOC);
    } catch (Throwable $e) {
        $ready = false;
        $error = '단위 목록을 불러오지 못했습니다. 잠시 후 다시 시도해주세요.';
    }
}
require_once __DIR__ . '/includes/admin_header.php';
?>
<style>
.tu-page{--tu-accent:#6554c0;--tu-soft:#f3f0ff;max-width:1120px;margin:0 auto;padding:32px 16px 48px;color:var(--bs-body-color,#202536)}
.tu-page *{box-sizing:border-box}.tu-page [hidden]{display:none!important}
.tu-heading{display:flex;align-items:center;gap:16px;margin-bottom:28px}.tu-mark{display:grid;place-items:center;width:52px;height:52px;border-radius:16px;background:var(--tu-soft);color:var(--tu-accent);font-size:23px;flex-shrink:0}
.tu-heading h1{font-size:26px;letter-spacing:-1px;font-weight:750;margin:0 0 7px}.tu-heading p{margin:0;color:var(--bs-secondary-color,#73798a);font-size:14px;line-height:1.6}
.tu-panel{border:1px solid var(--bs-border-color,#e5e7ee);border-radius:20px;background:var(--bs-body-bg,#fff);overflow:hidden;box-shadow:0 4px 24px #18234005}
.tu-add{padding:26px 28px;background:var(--bs-tertiary-bg,#f8f9fc);border-bottom:1px solid var(--bs-border-color,#e5e7ee);display:grid;grid-template-columns:1fr minmax(280px,1.25fr);gap:24px;align-items:center}
.tu-page h2{font-size:16px;font-weight:700;margin:0 0 8px}.tu-sub{font-size:13px;color:var(--bs-secondary-color,#73798a);margin:0;line-height:1.7}.tu-entry{display:flex;gap:8px}.tu-input{width:100%;min-width:0;border:1px solid var(--bs-border-color,#dedfe8);background:var(--bs-body-bg,#fff);color:inherit;border-radius:10px;padding:11px 13px;font:inherit;font-size:14px}.tu-page :is(input,button):focus-visible{outline:3px solid #a89be9;outline-offset:3px}
.tu-primary{border:0;background:var(--tu-accent);color:#fff;border-radius:10px;padding:11px 19px;font-size:14px;font-weight:650;white-space:nowrap;cursor:pointer}.tu-primary:hover{background:#5140a7}.tu-input-hint{font-size:12px;color:var(--bs-secondary-color,#73798a);margin-top:8px}
.tu-library{padding:28px}.tu-toolbar{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:22px}.tu-toolbar h2{margin:0;display:flex;align-items:center;gap:9px}.tu-count{font-size:12px;background:var(--tu-soft);color:var(--tu-accent);padding:4px 9px;border-radius:7px;font-weight:700}.tu-search{position:relative;width:220px}.tu-search i{position:absolute;left:12px;top:12px;color:var(--bs-secondary-color,#73798a)}.tu-search input{padding-left:35px;font-size:13px}
.tu-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;list-style:none;padding:0;margin:0}.tu-unit{display:flex;align-items:center;justify-content:space-between;gap:8px;border:1px solid var(--bs-border-color,#e5e7ee);border-radius:12px;padding:14px 10px 14px 17px;min-height:66px;transition:border-color .15s,background .15s}.tu-unit:hover{border-color:#b9afe1;background:var(--bs-tertiary-bg,#faf9fe)}.tu-name{font-size:16px;font-weight:650;overflow-wrap:anywhere;min-width:0}.tu-unit form{margin:0;flex-shrink:0}.tu-delete{display:grid;place-items:center;width:36px;height:36px;border:0;border-radius:9px;background:transparent;color:var(--bs-secondary-color,#7e8494);cursor:pointer;font-size:16px}.tu-delete:hover{color:#c63850;background:#fff0f2}
.tu-foot{display:flex;gap:9px;align-items:flex-start;padding:18px 28px;border-top:1px solid var(--bs-border-color,#e5e7ee);font-size:12px;line-height:1.7;color:var(--bs-secondary-color,#73798a)}.tu-foot i{color:var(--tu-accent)}.tu-empty{text-align:center;padding:44px 16px;color:var(--bs-secondary-color,#73798a)}.tu-empty i{display:block;font-size:28px;margin-bottom:12px}.tu-empty p{margin:0;font-size:14px}
.tu-dialog{border:1px solid var(--bs-border-color,#e5e7ee);border-radius:18px;padding:28px;width:calc(100% - 32px);max-width:410px;background:var(--bs-body-bg,#fff);color:var(--bs-body-color,#202536);box-shadow:0 20px 80px #11182730}.tu-dialog::backdrop{background:#11182766}.tu-dialog h2{font-size:19px;line-height:1.5;overflow-wrap:anywhere;margin:0 0 10px}.tu-dialog p{font-size:14px;color:var(--bs-secondary-color,#73798a);line-height:1.7}.tu-dialog-actions{display:flex;justify-content:flex-end;gap:9px;margin-top:24px}.tu-dialog button{border-radius:9px;padding:10px 20px;cursor:pointer;font-size:14px}.tu-cancel{border:1px solid var(--bs-border-color,#e5e7ee);background:var(--bs-body-bg,#fff);color:inherit}.tu-confirm{border:1px solid #c63850;background:#c63850;color:white}
[data-bs-theme="dark"] .tu-page{--tu-accent:#b4a4ff;--tu-soft:#332c4a}[data-bs-theme="dark"] .tu-primary{background:#7963cf;color:#fff}
@media(max-width:900px){.tu-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.tu-add{grid-template-columns:1fr;gap:16px}}
@media(max-width:575px){.tu-page{padding:20px 4px 32px}.tu-heading{gap:12px;margin-bottom:22px}.tu-heading h1{font-size:23px}.tu-heading p{font-size:12px}.tu-add,.tu-library{padding:20px}.tu-toolbar{align-items:stretch;flex-direction:column}.tu-search{width:100%}.tu-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.tu-unit{padding-left:12px}.tu-foot{padding:16px 20px}.tu-primary{padding:11px 15px}}
</style>
<section class="tu-page" aria-labelledby="unitsTitle">
    <header class="tu-heading">
        <div class="tu-mark" aria-hidden="true"><i class="bi bi-rulers"></i></div>
        <div><h1 id="unitsTitle">단위 관리</h1><p>시술에 필요한 단위를 추가하고 한곳에서 관리하세요.</p></div>
    </header>
    <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($notice): ?><div class="alert alert-success" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($ready): ?>
    <div class="tu-panel">
        <div class="tu-add">
            <div><h2><label for="unitName">새 단위 추가</label></h2><p class="tu-sub">추가한 단위는 모든 시술 등록 화면에 적용됩니다.</p></div>
            <form method="post" action="admin_treatment_units.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="add">
                <div class="tu-entry"><input id="unitName" name="name" class="tu-input" maxlength="20" required placeholder="예: 시린지, Box" aria-describedby="unitHelp" autocomplete="off"><button class="tu-primary" type="submit"><i class="bi bi-plus-lg" aria-hidden="true"></i> 추가</button></div>
                <div id="unitHelp" class="tu-input-hint">최대 20자 · 삭제한 단위도 다시 추가할 수 있어요.</div>
            </form>
        </div>
        <div class="tu-library">
            <div class="tu-toolbar">
                <h2>등록된 단위 <span class="tu-count"><?= count($units) ?></span></h2>
                <div class="tu-search"><i class="bi bi-search" aria-hidden="true"></i><input type="search" id="unitSearch" class="tu-input" placeholder="단위 검색" aria-label="단위 검색" autocomplete="off"></div>
            </div>
            <ul class="tu-grid" aria-label="등록된 단위">
                <?php foreach ($units as $unit): ?>
                <li class="tu-unit" data-unit-name="<?= htmlspecialchars($unit['name'], ENT_QUOTES, 'UTF-8') ?>">
                    <span class="tu-name"><?= htmlspecialchars($unit['name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <form method="post" action="admin_treatment_units.php" class="tu-remove-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="id" value="<?= (int)$unit['id'] ?>">
                        <button type="submit" class="tu-delete" title="<?= htmlspecialchars($unit['name'], ENT_QUOTES, 'UTF-8') ?> 삭제" aria-label="<?= htmlspecialchars($unit['name'], ENT_QUOTES, 'UTF-8') ?> 삭제"><i class="bi bi-trash3" aria-hidden="true"></i></button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <div class="tu-empty" id="unitEmpty" role="status" <?= $units ? 'hidden' : '' ?>><i class="bi bi-collection" aria-hidden="true"></i><p><?= $units ? '검색 결과가 없습니다. 다른 단위명으로 검색해주세요.' : '아직 등록된 단위가 없습니다. 첫 단위를 추가해보세요.' ?></p></div>
        </div>
        <div class="tu-foot"><i class="bi bi-info-circle" aria-hidden="true"></i><span>단위를 삭제해도 기존 시술에 저장된 가격과 단위는 그대로 유지됩니다.</span></div>
    </div>
    <dialog id="unitDeleteDialog" class="tu-dialog" aria-labelledby="unitDeleteTitle" aria-describedby="unitDeleteDescription">
        <h2 id="unitDeleteTitle">단위를 삭제할까요?</h2><p id="unitDeleteDescription">새 시술의 선택 목록에서 제외됩니다.<br>기존 시술의 가격과 단위는 유지됩니다.</p>
        <div class="tu-dialog-actions"><button type="button" class="tu-cancel" id="unitDeleteCancel" autofocus>취소</button><button type="button" class="tu-confirm" id="unitDeleteConfirm">삭제</button></div>
    </dialog>
    <script>
    (function () {
        var search = document.getElementById('unitSearch');
        var rows = Array.from(document.querySelectorAll('.tu-unit'));
        search.addEventListener('input', function () {
            var query = search.value.trim().toLocaleLowerCase();
            var visible = 0;
            rows.forEach(function (row) {
                row.hidden = !row.dataset.unitName.toLocaleLowerCase().includes(query);
                if (!row.hidden) visible++;
            });
            document.getElementById('unitEmpty').hidden = visible > 0;
        });
        var dialog = document.getElementById('unitDeleteDialog');
        var pendingForm = null;
        document.querySelectorAll('.tu-remove-form').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (typeof dialog.showModal !== 'function') {
                    if (!confirm('이 단위를 삭제하시겠습니까? 기존 시술 가격은 유지됩니다.')) event.preventDefault();
                    return;
                }
                event.preventDefault();
                pendingForm = form;
                document.getElementById('unitDeleteTitle').textContent = '“' + form.closest('.tu-unit').dataset.unitName + '” 단위를 삭제할까요?';
                dialog.showModal();
            });
        });
        document.getElementById('unitDeleteCancel').addEventListener('click', function () { dialog.close(); });
        dialog.addEventListener('close', function () { pendingForm = null; });
        document.getElementById('unitDeleteConfirm').addEventListener('click', function () {
            if (!pendingForm) return;
            this.disabled = true;
            this.textContent = '삭제 중…';
            pendingForm.submit();
        });
    })();
    </script>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
