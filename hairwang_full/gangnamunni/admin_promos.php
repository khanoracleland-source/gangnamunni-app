<?php
/**
 * 프로모션 배너 관리
 */
session_start();
$admin_page_title = '프로모션 관리';
$admin_current = 'promos';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('promos');

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_GET['id']) ? 'edit' : 'list');
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = '';

// 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = mysqli_real_escape_string($connect, trim($_POST['title'] ?? ''));
    $subtitle = mysqli_real_escape_string($connect, trim($_POST['subtitle'] ?? ''));
    $gradient = mysqli_real_escape_string($connect, trim($_POST['background_gradient'] ?? 'linear-gradient(135deg, #FF6B35, #FF8C42)'));
    $link = mysqli_real_escape_string($connect, trim($_POST['link_url'] ?? ''));
    $order = (int)($_POST['display_order'] ?? 0);
    $active = isset($_POST['is_active']) ? 1 : 0;

    if (isset($_POST['id']) && (int)$_POST['id'] > 0) {
        $uid = (int)$_POST['id'];
        mysqli_query($connect, "UPDATE gangnamunni_promos SET title='{$title}', subtitle='{$subtitle}', background_gradient='{$gradient}', link_url='{$link}', display_order={$order}, is_active={$active} WHERE id={$uid}");
        $msg = '프로모션이 수정되었습니다.';
    } else {
        mysqli_query($connect, "INSERT INTO gangnamunni_promos (title, subtitle, background_gradient, link_url, display_order, is_active) VALUES ('{$title}', '{$subtitle}', '{$gradient}', '{$link}', {$order}, {$active})");
        $msg = '프로모션이 등록되었습니다.';
    }
    $action = 'list';
}

// 삭제
if ($action === 'delete' && $id > 0) {
    mysqli_query($connect, "DELETE FROM gangnamunni_promos WHERE id = {$id}");
    $msg = '삭제되었습니다.';
    $action = 'list';
}

// 로드
$promos = [];
if ($action === 'list') {
    $result = mysqli_query($connect, "SELECT * FROM gangnamunni_promos ORDER BY display_order, id");
    if ($result) { while ($row = mysqli_fetch_assoc($result)) { $promos[] = $row; } }
}

$edit_data = null;
if ($id > 0) {
    $result = mysqli_query($connect, "SELECT * FROM gangnamunni_promos WHERE id = {$id}");
    if ($result) $edit_data = mysqli_fetch_assoc($result);
}

$page_title = '프로모션 관리';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <?php if ($msg): ?>
            <div class="alert alert-success"><?php echo $msg; ?></div>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
            <div class="d-flex justify-content-between mb-3">
                <h2>프로모션 배너 관리</h2>
                <a href="admin_promos.php?action=new" class="btn btn-primary">+ 새 프로모션</a>
            </div>

            <div class="row g-3 mb-4">
                <?php foreach ($promos as $p): ?>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-body p-0">
                            <div style="<?php echo htmlspecialchars($p['background_gradient']); ?>;padding:24px;border-radius:6px 6px 0 0;color:#fff;">
                                <div style="font-size:18px;font-weight:bold;"><?php echo htmlspecialchars($p['title']); ?></div>
                                <div style="font-size:13px;opacity:0.9;margin-top:4px;"><?php echo htmlspecialchars($p['subtitle'] ?? ''); ?></div>
                            </div>
                            <div class="p-3 d-flex justify-content-between align-items-center">
                                <span class="badge <?php echo $p['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $p['is_active'] ? 'ON' : 'OFF'; ?>
                                </span>
                                <div>
                                    <a href="admin_promos.php?id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-primary">편집</a>
                                    <a href="admin_promos.php?action=delete&id=<?php echo $p['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('삭제?')">삭제</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($promos)): ?>
                <div class="col-12 text-center py-5 text-muted">등록된 프로모션이 없습니다.</div>
                <?php endif; ?>
            </div>

            <?php else: ?>
            <h2><?php echo $edit_data ? '프로모션 수정' : '새 프로모션'; ?></h2>
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <?php if ($edit_data): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">제목 *</label>
                            <input type="text" class="form-control" name="title" required value="<?php echo htmlspecialchars($edit_data['title'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">부제목</label>
                            <input type="text" class="form-control" name="subtitle" value="<?php echo htmlspecialchars($edit_data['subtitle'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">배경 그라데이션</label>
                            <input type="text" class="form-control" name="background_gradient" value="<?php echo htmlspecialchars($edit_data['background_gradient'] ?? 'linear-gradient(135deg, #FF6B35, #FF8C42)'); ?>">
                            <div class="form-text">예: linear-gradient(135deg, #FF6B35, #FF8C42)</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">링크 URL</label>
                            <input type="text" class="form-control" name="link_url" value="<?php echo htmlspecialchars($edit_data['link_url'] ?? ''); ?>">
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">표시 순서</label>
                                <input type="number" class="form-control" name="display_order" value="<?php echo $edit_data['display_order'] ?? 0; ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_active" <?php echo ($edit_data['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label">활성</label>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">저장</button>
                            <a href="admin_promos.php" class="btn btn-secondary">목록</a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>