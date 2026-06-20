<?php
/**
 * 병원 소식 관리 (admin_hospital_news.php)
 * - 병원 소식/프로모션/공지 목록, 등록, 수정, 삭제, 활성/비활성 토글
 */
session_start();

$admin_page_title = '병원 소식 관리';
$admin_current = 'hospital_news';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

// DB 연결
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (!$connect) die('DB 연결 실패');

loadThemeSettings($connect);
check_admin_login();
check_permission('hospital_news');

// ===== POST Actions =====
$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // === 신규 등록 ===
    if ($action === 'create') {
        $hospital_id = intval($_POST['hospital_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        $news_type = $_POST['news_type'] ?? 'news';

        if ($hospital_id <= 0 || $title === '') {
            $msg = '병원과 제목은 필수 입력입니다.';
            $msg_type = 'danger';
        } else {
            // news_type 유효성 검사
            if (!in_array($news_type, ['news', 'promo', 'notice'])) {
                $news_type = 'news';
            }
            $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_hospital_news (hospital_id, title, content, image_url, news_type, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'issss', $hospital_id, $title, $content, $image_url, $news_type);
                if (mysqli_stmt_execute($stmt)) {
                    $msg = '소식이 등록되었습니다.';
                    $msg_type = 'success';
                } else {
                    $msg = '등록 실패: ' . mysqli_error($connect);
                    $msg_type = 'danger';
                }
                mysqli_stmt_close($stmt);
            }
            header('Location: admin_hospital_news.php?msg=' . urlencode($msg) . '&msg_type=' . urlencode($msg_type));
            exit;
        }
    }

    // === 수정 ===
    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $hospital_id = intval($_POST['hospital_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        $news_type = $_POST['news_type'] ?? 'news';

        if ($id <= 0 || $hospital_id <= 0 || $title === '') {
            $msg = '필수 항목이 누락되었습니다.';
            $msg_type = 'danger';
        } else {
            if (!in_array($news_type, ['news', 'promo', 'notice'])) {
                $news_type = 'news';
            }
            $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_hospital_news SET hospital_id = ?, title = ?, content = ?, image_url = ?, news_type = ? WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'issssi', $hospital_id, $title, $content, $image_url, $news_type, $id);
                if (mysqli_stmt_execute($stmt)) {
                    $msg = '소식이 수정되었습니다.';
                    $msg_type = 'success';
                } else {
                    $msg = '수정 실패: ' . mysqli_error($connect);
                    $msg_type = 'danger';
                }
                mysqli_stmt_close($stmt);
            }
            header('Location: admin_hospital_news.php?msg=' . urlencode($msg) . '&msg_type=' . urlencode($msg_type));
            exit;
        }
    }

    // === 삭제 ===
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_hospital_news WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $msg = '소식이 삭제되었습니다.';
                $msg_type = 'success';
            }
        }
        header('Location: admin_hospital_news.php?msg=' . urlencode($msg) . '&msg_type=' . urlencode($msg_type));
        exit;
    }

    // === 활성/비활성 토글 ===
    if ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_hospital_news SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $msg = '상태가 변경되었습니다.';
                $msg_type = 'success';
            }
        }
        header('Location: admin_hospital_news.php?msg=' . urlencode($msg) . '&msg_type=' . urlencode($msg_type));
        exit;
    }
}

// GET 파라미터 메시지 처리
if (isset($_GET['msg']) && $_GET['msg'] !== '') {
    $msg = $_GET['msg'];
    $msg_type = $_GET['msg_type'] ?? 'success';
}

// ===== 병원 목록 (드롭다운용) =====
$hospitals = [];
$hr = mysqli_query($connect, "SELECT id, name FROM gangnamunni_hospitals ORDER BY name ASC");
if ($hr) {
    while ($h = mysqli_fetch_assoc($hr)) {
        $hospitals[] = $h;
    }
}

// ===== Filters =====
$filter_type = $_GET['type'] ?? '';
$search = $_GET['q'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 동적 WHERE 절 (prepared statement)
$where_clauses = ["1=1"];
$bind_types = '';
$bind_values = [];

if ($filter_type !== '' && in_array($filter_type, ['news', 'promo', 'notice'])) {
    $where_clauses[] = "n.news_type = ?";
    $bind_types .= 's';
    $bind_values[] = $filter_type;
}
if ($search !== '') {
    $where_clauses[] = "(n.title LIKE ? OR h.name LIKE ?)";
    $bind_types .= 'ss';
    $search_like = '%' . $search . '%';
    $bind_values[] = $search_like;
    $bind_values[] = $search_like;
}

$where = implode(' AND ', $where_clauses);

// 총 건수
$count_sql = "SELECT COUNT(*) as cnt FROM gangnamunni_hospital_news n LEFT JOIN gangnamunni_hospitals h ON n.hospital_id = h.id WHERE " . $where;
$stmt_c = mysqli_prepare($connect, $count_sql);
if ($bind_types && $stmt_c) {
    mysqli_stmt_bind_param($stmt_c, $bind_types, ...$bind_values);
}
if ($stmt_c) {
    mysqli_stmt_execute($stmt_c);
    $count_r = mysqli_stmt_get_result($stmt_c);
    $total = $count_r ? mysqli_fetch_assoc($count_r)['cnt'] : 0;
    mysqli_stmt_close($stmt_c);
} else {
    $total = 0;
}
$total_pages = max(1, ceil($total / $per_page));

// 데이터 조회
$data_sql = "SELECT n.*, h.name AS hospital_name FROM gangnamunni_hospital_news n LEFT JOIN gangnamunni_hospitals h ON n.hospital_id = h.id WHERE " . $where . " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
$stmt_d = mysqli_prepare($connect, $data_sql);
$data_bind_types = $bind_types . 'ii';
$data_bind_values = array_merge($bind_values, [$per_page, $offset]);
if ($stmt_d) {
    mysqli_stmt_bind_param($stmt_d, $data_bind_types, ...$data_bind_values);
    mysqli_stmt_execute($stmt_d);
    $r = mysqli_stmt_get_result($stmt_d);
    $rows = [];
    if ($r) while ($row = mysqli_fetch_assoc($r)) $rows[] = $row;
    mysqli_stmt_close($stmt_d);
} else {
    $rows = [];
}

// 통계
$stats = ['total' => 0, 'news' => 0, 'promo' => 0, 'notice' => 0, 'active' => 0, 'inactive' => 0];
$sr = mysqli_query($connect, "SELECT news_type, COUNT(*) as cnt FROM gangnamunni_hospital_news GROUP BY news_type");
if ($sr) {
    while ($s = mysqli_fetch_assoc($sr)) {
        $stats[$s['news_type']] = (int)$s['cnt'];
        $stats['total'] += (int)$s['cnt'];
    }
}
$ar = mysqli_query($connect, "SELECT is_active, COUNT(*) as cnt FROM gangnamunni_hospital_news GROUP BY is_active");
if ($ar) {
    while ($a = mysqli_fetch_assoc($ar)) {
        if ((int)$a['is_active'] === 1) $stats['active'] = (int)$a['cnt'];
        else $stats['inactive'] = (int)$a['cnt'];
    }
}

// 수정용 데이터 (edit 모달 프리로드)
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt_e = mysqli_prepare($connect, "SELECT * FROM gangnamunni_hospital_news WHERE id = ?");
    if ($stmt_e) {
        mysqli_stmt_bind_param($stmt_e, 'i', $edit_id);
        mysqli_stmt_execute($stmt_e);
        $er = mysqli_stmt_get_result($stmt_e);
        if ($er) $edit_data = mysqli_fetch_assoc($er);
        mysqli_stmt_close($stmt_e);
    }
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <?php if (!empty($msg)): ?>
    <div class="alert alert-<?php echo htmlspecialchars($msg_type); ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="bi bi-newspaper me-2"></i>병원 소식 관리</h4>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModal"><i class="bi bi-plus-lg me-1"></i>소식 등록</button>
    </div>

    <!-- 통계 -->
    <div class="row g-3 mb-4">
        <?php foreach ([
            ['전체', $stats['total'], 'primary'],
            ['소식', $stats['news'], 'info'],
            ['프로모션', $stats['promo'], 'warning'],
            ['공지', $stats['notice'], 'danger'],
            ['활성', $stats['active'], 'success'],
            ['비활성', $stats['inactive'], 'secondary'],
        ] as $sc): ?>
        <div class="col-6 col-md-2">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <h5 class="mb-0"><?php echo number_format($sc[1]); ?></h5>
                    <small class="text-<?php echo $sc[2]; ?>"><?php echo $sc[0]; ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 필터 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <select name="type" class="form-select form-select-sm">
                        <option value="">전체 유형</option>
                        <option value="news" <?php echo $filter_type === 'news' ? 'selected' : ''; ?>>소식</option>
                        <option value="promo" <?php echo $filter_type === 'promo' ? 'selected' : ''; ?>>프로모션</option>
                        <option value="notice" <?php echo $filter_type === 'notice' ? 'selected' : ''; ?>>공지</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="제목, 병원명 검색" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-search"></i> 검색</button>
                </div>
                <?php if ($filter_type !== '' || $search !== ''): ?>
                <div class="col-md-2">
                    <a href="admin_hospital_news.php" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-x-circle"></i> 초기화</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- 목록 -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px;">#</th>
                        <th>병원</th>
                        <th>제목</th>
                        <th style="width:90px;">유형</th>
                        <th style="width:80px;">상태</th>
                        <th style="width:140px;">등록일</th>
                        <th style="width:160px;">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-inbox" style="font-size:2rem;"></i><br>등록된 소식이 없습니다.</td></tr>
                    <?php else: foreach ($rows as $n): ?>
                    <tr>
                        <td><?php echo $n['id']; ?></td>
                        <td><small><?php echo htmlspecialchars($n['hospital_name'] ?? '-'); ?></small></td>
                        <td>
                            <?php echo htmlspecialchars($n['title']); ?>
                            <?php if (!empty($n['image_url'])): ?>
                            <i class="bi bi-image text-muted ms-1" title="<?php echo htmlspecialchars($n['image_url']); ?>"></i>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $type_badge = ['news' => 'primary', 'promo' => 'warning', 'notice' => 'danger'];
                            $type_label = ['news' => '소식', 'promo' => '프로모션', 'notice' => '공지'];
                            $nt = $n['news_type'] ?? 'news';
                            ?>
                            <span class="badge bg-<?php echo $type_badge[$nt] ?? 'secondary'; ?>"><?php echo $type_label[$nt] ?? $nt; ?></span>
                        </td>
                        <td>
                            <?php if ((int)$n['is_active'] === 1): ?>
                            <span class="badge bg-success">활성</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">비활성</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?php echo substr($n['created_at'] ?? '', 0, 16); ?></small></td>
                        <td>
                            <!-- 토글 -->
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                                <button type="submit" class="btn btn-<?php echo (int)$n['is_active'] === 1 ? 'outline-secondary' : 'outline-success'; ?> btn-sm" title="<?php echo (int)$n['is_active'] === 1 ? '비활성화' : '활성화'; ?>">
                                    <i class="bi bi-<?php echo (int)$n['is_active'] === 1 ? 'toggle-on' : 'toggle-off'; ?>"></i>
                                </button>
                            </form>
                            <!-- 수정 -->
                            <a href="?edit=<?php echo $n['id']; ?>" class="btn btn-outline-primary btn-sm" title="수정">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <!-- 삭제 -->
                            <form method="post" class="d-inline" onsubmit="return confirm('정말 삭제하시겠습니까?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $n['id']; ?>">
                                <button type="submit" class="btn btn-outline-danger btn-sm" title="삭제">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white">
            <nav><ul class="pagination pagination-sm justify-content-center mb-0">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&type=<?php echo urlencode($filter_type); ?>&q=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 등록 모달 -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>소식 등록</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">병원 <span class="text-danger">*</span></label>
                        <select name="hospital_id" class="form-select" required>
                            <option value="">-- 병원 선택 --</option>
                            <?php foreach ($hospitals as $h): ?>
                            <option value="<?php echo $h['id']; ?>"><?php echo htmlspecialchars($h['name']); ?> (ID: <?php echo $h['id']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">제목 <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" maxlength="300" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">내용</label>
                        <textarea name="content" class="form-control" rows="5" placeholder="소식 내용을 입력하세요"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">이미지 URL</label>
                            <input type="url" name="image_url" class="form-control" placeholder="https://example.com/image.jpg">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">소식 유형</label>
                            <select name="news_type" class="form-select">
                                <option value="news">소식</option>
                                <option value="promo">프로모션</option>
                                <option value="notice">공지</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>등록</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 수정 모달 (edit 파라미터가 있으면 자동 오픈) -->
<?php if ($edit_data): ?>
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>소식 수정 (ID: <?php echo $edit_data['id']; ?>)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">병원 <span class="text-danger">*</span></label>
                        <select name="hospital_id" class="form-select" required>
                            <option value="">-- 병원 선택 --</option>
                            <?php foreach ($hospitals as $h): ?>
                            <option value="<?php echo $h['id']; ?>" <?php echo (int)$edit_data['hospital_id'] === (int)$h['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($h['name']); ?> (ID: <?php echo $h['id']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">제목 <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" maxlength="300" required value="<?php echo htmlspecialchars($edit_data['title']); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">내용</label>
                        <textarea name="content" class="form-control" rows="5"><?php echo htmlspecialchars($edit_data['content'] ?? ''); ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">이미지 URL</label>
                            <input type="url" name="image_url" class="form-control" value="<?php echo htmlspecialchars($edit_data['image_url'] ?? ''); ?>">
                            <?php if (!empty($edit_data['image_url'])): ?>
                            <div class="mt-2">
                                <img src="<?php echo htmlspecialchars($edit_data['image_url']); ?>" alt="미리보기" style="max-width:200px;max-height:120px;border-radius:6px;object-fit:cover;" class="border">
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">소식 유형</label>
                            <select name="news_type" class="form-select">
                                <option value="news" <?php echo ($edit_data['news_type'] ?? '') === 'news' ? 'selected' : ''; ?>>소식</option>
                                <option value="promo" <?php echo ($edit_data['news_type'] ?? '') === 'promo' ? 'selected' : ''; ?>>프로모션</option>
                                <option value="notice" <?php echo ($edit_data['news_type'] ?? '') === 'notice' ? 'selected' : ''; ?>>공지</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="admin_hospital_news.php" class="btn btn-secondary">취소</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>수정</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
// 수정 모달 자동 오픈
document.addEventListener('DOMContentLoaded', function() {
    var editModal = new bootstrap.Modal(document.getElementById('editModal'));
    editModal.show();
    // 모달 닫힐 때 URL에서 edit 파라미터 제거
    document.getElementById('editModal').addEventListener('hidden.bs.modal', function() {
        if (window.history.replaceState) {
            var url = new URL(window.location.href);
            url.searchParams.delete('edit');
            window.history.replaceState({}, '', url.toString());
        }
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
