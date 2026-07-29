<?php
/**
 * FAQ 관리 페이지 (admin_faq.php)
 * - FAQ 목록, 등록, 수정, 삭제, 활성/비활성 토글, 카테고리 필터
 * Bootstrap 5.3 + 공통 관리자 레이아웃
 */
session_start();

$admin_page_title = 'FAQ 관리';
$admin_current = 'faq';

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
require_admin_login();
require_permission('faq');

// 카테고리 목록
$faq_categories = ['일반', '예약', '결제', '회원', '이벤트', '후기', '시술', '기타'];

$msg = '';
$msg_type = 'success';

// ===== POST Actions =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 등록
    if ($action === 'create') {
        $category = $_POST['category'] ?? '일반';
        $question = trim($_POST['question'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);

        if ($question === '' || $answer === '') {
            $msg = '질문과 답변을 모두 입력해주세요.';
            $msg_type = 'danger';
        } else {
            $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_faq (category, question, answer, sort_order, is_active) VALUES (?, ?, ?, ?, 1)");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssi', $category, $question, $answer, $sort_order);
                if (mysqli_stmt_execute($stmt)) {
                    header('Location: admin_faq.php?msg=' . urlencode('FAQ가 등록되었습니다.'));
                    exit;
                } else {
                    $msg = 'FAQ 등록 실패: ' . mysqli_error($connect);
                    $msg_type = 'danger';
                }
                mysqli_stmt_close($stmt);
            }
        }
    }

    // 수정
    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        $category = $_POST['category'] ?? '일반';
        $question = trim($_POST['question'] ?? '');
        $answer = trim($_POST['answer'] ?? '');
        $sort_order = intval($_POST['sort_order'] ?? 0);

        if ($id <= 0 || $question === '' || $answer === '') {
            $msg = '필수 항목을 모두 입력해주세요.';
            $msg_type = 'danger';
        } else {
            $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_faq SET category = ?, question = ?, answer = ?, sort_order = ? WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssii', $category, $question, $answer, $sort_order, $id);
                if (mysqli_stmt_execute($stmt)) {
                    header('Location: admin_faq.php?msg=' . urlencode('FAQ가 수정되었습니다.'));
                    exit;
                } else {
                    $msg = 'FAQ 수정 실패: ' . mysqli_error($connect);
                    $msg_type = 'danger';
                }
                mysqli_stmt_close($stmt);
            }
        }
    }

    // 삭제
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_faq WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            header('Location: admin_faq.php?msg=' . urlencode('FAQ가 삭제되었습니다.'));
            exit;
        }
    }

    // 활성/비활성 토글
    if ($action === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_faq SET is_active = IF(is_active = 1, 0, 1) WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
            header('Location: admin_faq.php?msg=' . urlencode('FAQ 상태가 변경되었습니다.'));
            exit;
        }
    }
}

// GET ?msg= 파라미터 처리
if (!empty($_GET['msg'])) {
    $msg = $_GET['msg'];
    $msg_type = 'success';
}

// ===== Filters & Pagination =====
$filter_category = $_GET['category'] ?? '';
$search = $_GET['q'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 동적 WHERE 절 (prepared statement)
$where_clauses = ["1=1"];
$bind_types = '';
$bind_values = [];

if ($filter_category !== '') {
    $where_clauses[] = "category = ?";
    $bind_types .= 's';
    $bind_values[] = $filter_category;
}

if ($search !== '') {
    $where_clauses[] = "(question LIKE ? OR answer LIKE ?)";
    $bind_types .= 'ss';
    $search_like = '%' . $search . '%';
    $bind_values[] = $search_like;
    $bind_values[] = $search_like;
}

$where = implode(' AND ', $where_clauses);

// 총 건수
$count_sql = "SELECT COUNT(*) as cnt FROM gangnamunni_faq WHERE " . $where;
$stmt_c = mysqli_prepare($connect, $count_sql);
if ($bind_types && $stmt_c) {
    mysqli_stmt_bind_param($stmt_c, $bind_types, ...$bind_values);
}
mysqli_stmt_execute($stmt_c);
$count_r = mysqli_stmt_get_result($stmt_c);
$total = $count_r ? mysqli_fetch_assoc($count_r)['cnt'] : 0;
$total_pages = max(1, ceil($total / $per_page));
mysqli_stmt_close($stmt_c);

// 데이터 조회
$data_sql = "SELECT * FROM gangnamunni_faq WHERE " . $where . " ORDER BY sort_order ASC, id DESC LIMIT ? OFFSET ?";
$stmt_d = mysqli_prepare($connect, $data_sql);
$data_bind_types = $bind_types . 'ii';
$data_bind_values = array_merge($bind_values, [$per_page, $offset]);
mysqli_stmt_bind_param($stmt_d, $data_bind_types, ...$data_bind_values);
mysqli_stmt_execute($stmt_d);
$r = mysqli_stmt_get_result($stmt_d);
$rows = [];
if ($r) while ($row = mysqli_fetch_assoc($r)) $rows[] = $row;
mysqli_stmt_close($stmt_d);

// 카테고리별 통계
$cat_stats = [];
$sr = mysqli_query($connect, "SELECT category, COUNT(*) as cnt FROM gangnamunni_faq GROUP BY category ORDER BY cnt DESC");
if ($sr) while ($s = mysqli_fetch_assoc($sr)) $cat_stats[$s['category']] = (int)$s['cnt'];
$total_active = 0;
$total_inactive = 0;
$sr2 = mysqli_query($connect, "SELECT is_active, COUNT(*) as cnt FROM gangnamunni_faq GROUP BY is_active");
if ($sr2) while ($s2 = mysqli_fetch_assoc($sr2)) {
    if ($s2['is_active'] == 1) $total_active = (int)$s2['cnt'];
    else $total_inactive = (int)$s2['cnt'];
}
$total_all = $total_active + $total_inactive;

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <?php if (!empty($msg)): ?>
    <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="bi bi-question-circle me-2"></i>FAQ 관리</h4>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="bi bi-plus-lg me-1"></i>FAQ 등록
        </button>
    </div>

    <!-- 통계 카드 -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <h5 class="mb-0"><?php echo number_format($total_all); ?></h5>
                    <small class="text-primary">전체</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <h5 class="mb-0"><?php echo number_format($total_active); ?></h5>
                    <small class="text-success">활성</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <h5 class="mb-0"><?php echo number_format($total_inactive); ?></h5>
                    <small class="text-danger">비활성</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md">
            <div class="card text-center border-0 shadow-sm">
                <div class="card-body py-2">
                    <h5 class="mb-0"><?php echo count($cat_stats); ?></h5>
                    <small class="text-info">카테고리</small>
                </div>
            </div>
        </div>
    </div>

    <!-- 필터 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <select name="category" class="form-select form-select-sm">
                        <option value="">전체 카테고리</option>
                        <?php foreach ($faq_categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filter_category === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                            <?php if (isset($cat_stats[$cat])): ?>(<?php echo $cat_stats[$cat]; ?>)<?php endif; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="질문 또는 답변 검색" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-search"></i> 검색</button>
                </div>
                <?php if ($filter_category !== '' || $search !== ''): ?>
                <div class="col-md-2">
                    <a href="admin_faq.php" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-x-lg"></i> 초기화</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- FAQ 목록 -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px;">#</th>
                        <th style="width:90px;">카테고리</th>
                        <th>질문</th>
                        <th style="width:80px;">정렬순서</th>
                        <th style="width:80px;">상태</th>
                        <th style="width:130px;">등록일</th>
                        <th style="width:140px;">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox" style="font-size:2rem;"></i><br>
                            등록된 FAQ가 없습니다.
                        </td>
                    </tr>
                    <?php else: foreach ($rows as $faq): ?>
                    <tr>
                        <td><?php echo $faq['id']; ?></td>
                        <td>
                            <?php
                            $cat_colors = [
                                '일반' => 'secondary', '예약' => 'primary', '결제' => 'success',
                                '회원' => 'info', '이벤트' => 'warning', '후기' => 'danger',
                                '시술' => 'dark', '기타' => 'secondary'
                            ];
                            $cc = $cat_colors[$faq['category']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $cc; ?>"><?php echo htmlspecialchars($faq['category']); ?></span>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars(mb_substr($faq['question'], 0, 60, 'UTF-8')); ?><?php echo mb_strlen($faq['question'], 'UTF-8') > 60 ? '...' : ''; ?></strong>
                            <br>
                            <small class="text-muted"><?php echo htmlspecialchars(mb_substr(strip_tags($faq['answer']), 0, 80, 'UTF-8')); ?><?php echo mb_strlen(strip_tags($faq['answer']), 'UTF-8') > 80 ? '...' : ''; ?></small>
                        </td>
                        <td class="text-center"><?php echo $faq['sort_order']; ?></td>
                        <td class="text-center">
                            <?php if ($faq['is_active']): ?>
                                <span class="badge bg-success">활성</span>
                            <?php else: ?>
                                <span class="badge bg-danger">비활성</span>
                            <?php endif; ?>
                        </td>
                        <td><small><?php echo substr($faq['created_at'] ?? '', 0, 16); ?></small></td>
                        <td>
                            <!-- 수정 버튼 -->
                            <button class="btn btn-outline-primary btn-sm" title="수정"
                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($faq, JSON_UNESCAPED_UNICODE)); ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <!-- 토글 버튼 -->
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?php echo $faq['id']; ?>">
                                <?php if ($faq['is_active']): ?>
                                <button class="btn btn-outline-warning btn-sm" title="비활성화"><i class="bi bi-eye-slash"></i></button>
                                <?php else: ?>
                                <button class="btn btn-outline-success btn-sm" title="활성화"><i class="bi bi-eye"></i></button>
                                <?php endif; ?>
                            </form>
                            <!-- 삭제 버튼 -->
                            <form method="post" class="d-inline" onsubmit="return confirm('정말 삭제하시겠습니까?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $faq['id']; ?>">
                                <button class="btn btn-outline-danger btn-sm" title="삭제"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white">
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&category=<?php echo urlencode($filter_category); ?>&q=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
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
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>FAQ 등록</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">카테고리</label>
                            <select name="category" class="form-select" required>
                                <?php foreach ($faq_categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">정렬 순서</label>
                            <input type="number" name="sort_order" class="form-control" value="0" min="0">
                            <div class="form-text">숫자가 작을수록 먼저 표시됩니다.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">질문 <span class="text-danger">*</span></label>
                            <input type="text" name="question" class="form-control" required maxlength="500" placeholder="자주 묻는 질문을 입력하세요">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">답변 <span class="text-danger">*</span></label>
                            <textarea name="answer" class="form-control" rows="6" required placeholder="답변을 입력하세요"></textarea>
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

<!-- 수정 모달 -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>FAQ 수정</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">카테고리</label>
                            <select name="category" id="edit_category" class="form-select" required>
                                <?php foreach ($faq_categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">정렬 순서</label>
                            <input type="number" name="sort_order" id="edit_sort_order" class="form-control" value="0" min="0">
                            <div class="form-text">숫자가 작을수록 먼저 표시됩니다.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">질문 <span class="text-danger">*</span></label>
                            <input type="text" name="question" id="edit_question" class="form-control" required maxlength="500">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">답변 <span class="text-danger">*</span></label>
                            <textarea name="answer" id="edit_answer" class="form-control" rows="6" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>저장</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// 수정 모달 열기
function openEditModal(faq) {
    document.getElementById('edit_id').value = faq.id;
    document.getElementById('edit_category').value = faq.category;
    document.getElementById('edit_question').value = faq.question;
    document.getElementById('edit_answer').value = faq.answer;
    document.getElementById('edit_sort_order').value = faq.sort_order;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
