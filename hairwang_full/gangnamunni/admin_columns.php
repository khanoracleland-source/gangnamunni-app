<?php
/**
 * 칼럼 / 의학뉴스 관리
 */
session_start();
$admin_page_title = '칼럼/뉴스 관리';
$admin_current = 'columns';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('columns');

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_GET['id']) ? 'edit' : 'list');
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = '';

// 저장 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $content_text = trim($_POST['content'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $source_name = trim($_POST['source_name'] ?? '');
    $source_url = trim($_POST['source_url'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $is_external = isset($_POST['is_external']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (isset($_POST['id']) && (int)$_POST['id'] > 0) {
        $uid = (int)$_POST['id'];
        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_columns SET title=?, subtitle=?, content=?, author=?, source_name=?, source_url=?, category=?, is_external=?, is_active=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'sssssssiii', $title, $subtitle, $content_text, $author, $source_name, $source_url, $category, $is_external, $is_active, $uid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $msg = '칼럼이 수정되었습니다.';
    } else {
        $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_columns (title, subtitle, content, author, source_name, source_url, category, is_external, is_active, published_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, 'sssssssii', $title, $subtitle, $content_text, $author, $source_name, $source_url, $category, $is_external, $is_active);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $msg = '칼럼이 등록되었습니다.';
    }
    $action = 'list';
}

// 삭제
if ($action === 'delete' && $id > 0) {
    $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_columns WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $msg = '삭제되었습니다.';
    $action = 'list';
}

// 데이터 로드
$columns = [];
if ($action === 'list') {
    $result = mysqli_query($connect, "SELECT * FROM gangnamunni_columns ORDER BY published_at DESC");
    if ($result) { while ($row = mysqli_fetch_assoc($result)) { $columns[] = $row; } }
}

$edit_data = null;
if (($action === 'edit' || $action === 'new') && $id > 0) {
    $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_columns WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) $edit_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

$page_title = '칼럼 관리';
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
                <h2>칼럼 / 의학뉴스 관리</h2>
                <a href="admin_columns.php?action=new" class="btn btn-primary">+ 새 칼럼 작성</a>
            </div>
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-dark">
                            <tr><th>ID</th><th>제목</th><th>카테고리</th><th>출처</th><th>유형</th><th>상태</th><th>날짜</th><th>관리</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($columns as $c): ?>
                            <tr>
                                <td><?php echo $c['id']; ?></td>
                                <td><?php echo htmlspecialchars(mb_substr($c['title'], 0, 40)); ?></td>
                                <td><span class="badge bg-info"><?php echo htmlspecialchars($c['category']); ?></span></td>
                                <td><?php echo htmlspecialchars($c['source_name'] ?? '-'); ?></td>
                                <td><?php echo $c['is_external'] ? '<span class="badge bg-warning">외부</span>' : '<span class="badge bg-primary">내부</span>'; ?></td>
                                <td><?php echo $c['is_active'] ? '<span class="badge bg-success">활성</span>' : '<span class="badge bg-secondary">비활성</span>'; ?></td>
                                <td><?php echo $c['published_at'] ? date('m/d', strtotime($c['published_at'])) : '-'; ?></td>
                                <td>
                                    <a href="admin_columns.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-primary">편집</a>
                                    <a href="admin_columns.php?action=delete&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('삭제하시겠습니까?')">삭제</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($columns)): ?>
                            <tr><td colspan="8" class="text-center py-4">등록된 칼럼이 없습니다.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php else: ?>
            <h2><?php echo $edit_data ? '칼럼 수정' : '새 칼럼 작성'; ?></h2>
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
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">카테고리</label>
                                <select class="form-select" name="category">
                                    <option value="건강정보" <?php echo ($edit_data['category'] ?? '') === '건강정보' ? 'selected' : ''; ?>>건강정보</option>
                                    <option value="피부과" <?php echo ($edit_data['category'] ?? '') === '피부과' ? 'selected' : ''; ?>>피부과</option>
                                    <option value="성형외과" <?php echo ($edit_data['category'] ?? '') === '성형외과' ? 'selected' : ''; ?>>성형외과</option>
                                    <option value="미용" <?php echo ($edit_data['category'] ?? '') === '미용' ? 'selected' : ''; ?>>미용</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">작성자/출처</label>
                                <input type="text" class="form-control" name="author" value="<?php echo htmlspecialchars($edit_data['author'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">출처명</label>
                                <input type="text" class="form-control" name="source_name" placeholder="의학신문, 메디게이트 등" value="<?php echo htmlspecialchars($edit_data['source_name'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">원문 URL (외부 기사인 경우)</label>
                            <input type="url" class="form-control" name="source_url" value="<?php echo htmlspecialchars($edit_data['source_url'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">내용</label>
                            <textarea class="form-control" name="content" rows="10"><?php echo htmlspecialchars($edit_data['content'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_external" <?php echo ($edit_data['is_external'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label">외부 기사 (의학뉴스)</label>
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
                            <a href="admin_columns.php" class="btn btn-secondary">목록으로</a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>