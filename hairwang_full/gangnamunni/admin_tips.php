<?php
/**
 * ============================================================================
 * [관리자 #15 - 2026-05-28] 메인 화면 '서울미녀 꿀팁' 관리 기능 신규
 * ============================================================================
 * 목적 : 메인 화면 '서울미녀 꿀팁' 섹션의 게시글을 관리자가 직접 CRUD
 * 신규 : 본 파일 + gangnamunni_tips 테이블 자동 생성 (없을 시)
 * 영향 범위 : 신규 페이지 + 신규 테이블. 기존 데이터 영향 없음.
 *             기존 detail_tip.php는 그대로 유지 (꿀팁 상세 페이지).
 * ============================================================================
 */
session_start();
$admin_page_title = '서울미녀 꿀팁 관리';
$admin_current = 'tips';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('DB 연결 실패');
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('content');

// 테이블 자동 생성
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_tips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    summary VARCHAR(500) DEFAULT NULL,
    content TEXT,
    thumbnail_url VARCHAR(500) DEFAULT NULL,
    category VARCHAR(50) DEFAULT NULL,
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    view_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active_order (is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$message = ''; $error = '';
// CRUD 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_tips (title, summary, content, thumbnail_url, category, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $title = trim($_POST['title'] ?? '');
        $summary = trim($_POST['summary'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $thumb = trim($_POST['thumbnail_url'] ?? '');
        $cat = trim($_POST['category'] ?? '');
        $ord = intval($_POST['display_order'] ?? 0);
        $act = isset($_POST['is_active']) ? 1 : 0;
        mysqli_stmt_bind_param($stmt, 'sssssii', $title, $summary, $content, $thumb, $cat, $ord, $act);
        if (mysqli_stmt_execute($stmt)) $message = '꿀팁이 추가되었습니다.';
        else $error = mysqli_error($connect);
        mysqli_stmt_close($stmt);
    } elseif ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_tips SET title=?, summary=?, content=?, thumbnail_url=?, category=?, display_order=?, is_active=? WHERE id=?");
        $title = trim($_POST['title'] ?? '');
        $summary = trim($_POST['summary'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $thumb = trim($_POST['thumbnail_url'] ?? '');
        $cat = trim($_POST['category'] ?? '');
        $ord = intval($_POST['display_order'] ?? 0);
        $act = isset($_POST['is_active']) ? 1 : 0;
        mysqli_stmt_bind_param($stmt, 'sssssiii', $title, $summary, $content, $thumb, $cat, $ord, $act, $id);
        if (mysqli_stmt_execute($stmt)) $message = '꿀팁이 수정되었습니다.';
        else $error = mysqli_error($connect);
        mysqli_stmt_close($stmt);
    } elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            mysqli_query($connect, "DELETE FROM gangnamunni_tips WHERE id = {$id}");
            $message = '꿀팁이 삭제되었습니다.';
        }
    }
}

// 목록 조회
$tips = [];
$res = mysqli_query($connect, "SELECT * FROM gangnamunni_tips ORDER BY display_order DESC, id DESC LIMIT 100");
if ($res) while ($r = mysqli_fetch_assoc($res)) $tips[] = $r;

include __DIR__ . '/includes/admin_header.php';
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="bi bi-lightbulb-fill text-warning"></i> 서울미녀 꿀팁 관리</h4>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addTipModal"><i class="bi bi-plus"></i> 새 꿀팁 추가</button>
    </div>
    <?php if ($message): ?><div class="alert alert-success py-2"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="alert alert-info py-2 small">메인 화면 '서울미녀 꿀팁' 섹션에 노출할 게시글을 관리합니다.</div>

    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead class="table-light"><tr><th>ID</th><th>제목</th><th>카테고리</th><th>조회수</th><th>순서</th><th>활성</th><th>등록일</th><th>작업</th></tr></thead>
            <tbody>
                <?php foreach ($tips as $t): ?>
                <tr>
                    <td><?= $t['id'] ?></td>
                    <td><?= htmlspecialchars($t['title']) ?></td>
                    <td><?= htmlspecialchars($t['category'] ?? '-') ?></td>
                    <td><?= intval($t['view_count']) ?></td>
                    <td><?= intval($t['display_order']) ?></td>
                    <td><?= $t['is_active'] ? '✅' : '❌' ?></td>
                    <td class="small"><?= $t['created_at'] ?></td>
                    <td>
                        <form method="POST" class="d-inline" onsubmit="return confirm('삭제?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $t['id'] ?>">
                            <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($tips)): ?>
                <tr><td colspan="8" class="text-center py-4 text-muted">등록된 꿀팁이 없습니다. '+ 새 꿀팁 추가' 버튼으로 등록하세요.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 추가 모달 -->
<div class="modal fade" id="addTipModal" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
<form method="POST">
<input type="hidden" name="action" value="create">
<div class="modal-header"><h5>새 꿀팁 추가</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-2"><label>제목 *</label><input type="text" name="title" class="form-control" required></div>
    <div class="mb-2"><label>요약</label><input type="text" name="summary" class="form-control"></div>
    <div class="mb-2"><label>본문</label><textarea name="content" class="form-control" rows="5"></textarea></div>
    <div class="mb-2"><label>썸네일 URL</label><input type="text" name="thumbnail_url" class="form-control" placeholder="https://..."></div>
    <div class="mb-2"><label>카테고리</label><input type="text" name="category" class="form-control" placeholder="예: 시술전 / 시술후 / 예방"></div>
    <div class="row">
        <div class="col"><label>순서</label><input type="number" name="display_order" class="form-control" value="0"></div>
        <div class="col"><label class="d-block">활성</label><input type="checkbox" name="is_active" value="1" checked class="form-check-input"></div>
    </div>
</div>
<div class="modal-footer"><button class="btn btn-primary">저장</button></div>
</form>
</div></div></div>
<?php
mysqli_close($connect);
include __DIR__ . '/includes/admin_footer.php';
?>
