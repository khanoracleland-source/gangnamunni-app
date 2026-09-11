<?php
session_start();
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_admin_login();
require_permission('community_moderation');
if (!in_array($_SESSION['ad_admin_role'] ?? '', ['super_admin', 'admin', 'editor'], true)
    || !empty($_SESSION['ad_admin_hospital_id'])) {
    http_response_code(403);
    exit('플랫폼 관리자만 심사할 수 있습니다.');
}
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
$admin_page_title = '커뮤니티 심사';
$admin_current = 'community_moderation';
if (empty($_SESSION['community_moderation_csrf'])) $_SESSION['community_moderation_csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['community_moderation_csrf'];
function cm_escape($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function cm_revision($post) {
    return hash('sha256', json_encode([$post['category'], $post['title'], $post['content'], $post['photos']], JSON_UNESCAPED_UNICODE));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); exit('요청이 만료되었습니다.'); }
    $action = $_POST['action'] ?? '';
    if (!in_array($action, ['approve', 'reject'], true)) { http_response_code(400); exit('잘못된 요청입니다.'); }
    $id = (int)($_POST['id'] ?? 0);
    mysqli_begin_transaction($connect);
    try {
        $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_community_posts WHERE id=? AND status='pending' FOR UPDATE");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $post = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$post || !hash_equals(cm_revision($post), (string)($_POST['revision'] ?? ''))) {
            mysqli_rollback($connect);
            http_response_code(409);
            exit('게시글이 변경되었거나 이미 처리되었습니다. 목록을 새로고침한 후 다시 검토해주세요.');
        }
        $status = $action === 'approve' ? 'active' : 'rejected';
        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_community_posts SET status=? WHERE id=? AND status='pending'");
        mysqli_stmt_bind_param($stmt, 'si', $status, $id);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('Moderation update failed');
        mysqli_stmt_close($stmt);
        mysqli_commit($connect);
    } catch (Throwable $error) {
        mysqli_rollback($connect);
        error_log('Community moderation: ' . $error->getMessage());
        http_response_code(500);
        exit('처리에 실패했습니다. 다시 시도해주세요.');
    }
    header('Location: admin_community_posts.php?done=1');
    exit;
}
$filter = $_GET['status'] ?? 'pending';
$labels = ['pending' => '검토 대기', 'active' => '승인', 'rejected' => '반려'];
if (!isset($labels[$filter])) $filter = 'pending';
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * 20;
$stmt = mysqli_prepare($connect, 'SELECT * FROM gangnamunni_community_posts WHERE status=? ORDER BY created_at DESC, id DESC LIMIT 21 OFFSET ?');
mysqli_stmt_bind_param($stmt, 'si', $filter, $offset);
mysqli_stmt_execute($stmt);
$posts = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
$has_more = count($posts) > 20;
$posts = array_slice($posts, 0, 20);
include __DIR__ . '/includes/admin_header.php';
?>
<div class="container-fluid py-4">
    <h1 class="h4">커뮤니티 심사</h1>
    <p class="text-muted">작성 및 수정한 게시글은 승인 후 공개됩니다.</p>
    <?php if (isset($_GET['done'])): ?><div class="alert alert-success" role="status">처리되었습니다.</div><?php endif; ?>
    <nav class="d-flex gap-2 mb-4" aria-label="심사 상태">
        <?php foreach ($labels as $state => $label): ?>
        <a class="btn btn-sm <?= $state === $filter ? 'btn-primary' : 'btn-outline-secondary' ?>" href="?status=<?= $state ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </nav>
    <?php if (!$posts): ?><p class="alert alert-light">해당 게시글이 없습니다.</p><?php endif; ?>
    <?php foreach ($posts as $post): ?>
    <article class="card mb-3"><div class="card-body">
        <div class="text-muted mb-2"><?= cm_escape($post['category']) ?> · <?= cm_escape($post['author_name']) ?> · <?= cm_escape($post['created_at']) ?></div>
        <h2 class="h5"><?= cm_escape($post['title']) ?></h2>
        <div style="white-space:pre-wrap;overflow-wrap:anywhere"><?= cm_escape($post['content']) ?></div>
        <div class="d-flex flex-wrap gap-2 my-3">
        <?php foreach ((array)json_decode($post['photos'] ?? '[]', true) as $photo): ?>
            <?php if (!is_string($photo) || !preg_match('~^(?:/(?!/)|https?://)~i', $photo)) continue; ?>
            <a href="<?= cm_escape($photo) ?>" target="_blank" rel="noopener"><img src="<?= cm_escape($photo) ?>" alt="첨부 사진" loading="lazy" style="width:160px;height:160px;object-fit:contain"></a>
        <?php endforeach; ?>
        </div>
        <?php if ($filter === 'pending'): ?>
        <form method="post" class="d-flex gap-2">
            <input type="hidden" name="csrf_token" value="<?= cm_escape($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
            <input type="hidden" name="revision" value="<?= cm_revision($post) ?>">
            <button class="btn btn-success" name="action" value="approve">승인 및 공개</button>
            <button class="btn btn-outline-danger" name="action" value="reject">반려</button>
        </form>
        <?php endif; ?>
    </div></article>
    <?php endforeach; ?>
    <nav class="d-flex gap-3" aria-label="페이지">
        <?php if ($page > 1): ?><a href="?status=<?= $filter ?>&page=<?= $page - 1 ?>">이전</a><?php endif; ?>
        <?php if ($has_more): ?><a href="?status=<?= $filter ?>&page=<?= $page + 1 ?>">다음</a><?php endif; ?>
    </nav>
</div>
<?php include __DIR__ . '/includes/admin_footer.php'; ?>
