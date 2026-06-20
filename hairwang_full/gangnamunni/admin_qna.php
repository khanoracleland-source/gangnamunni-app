<?php
/**
 * Q&A 관리 (admin_qna.php)
 * - Q&A 목록 조회, 답변 등록/수정, 삭제, 공개/비공개 토글, 필터
 */
session_start();

$admin_page_title = 'Q&A 관리';
$admin_current = 'qna';

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
check_permission('qna');

// ===== POST 액션 처리 =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $id = intval($_POST['id'] ?? 0);

    // viewer 역할은 변경 불가
    $admin_role = $_SESSION['ad_admin_role'] ?? 'viewer';
    if ($admin_role === 'viewer') {
        header('Location: admin_qna.php?msg=' . urlencode('뷰어 역할은 변경 권한이 없습니다.') . '&msg_type=danger');
        exit;
    }

    if ($id <= 0) {
        header('Location: admin_qna.php?msg=' . urlencode('잘못된 요청입니다.') . '&msg_type=danger');
        exit;
    }

    // 답변 등록/수정
    if ($action === 'answer') {
        $answer = trim($_POST['answer'] ?? '');
        if ($answer === '') {
            header('Location: admin_qna.php?msg=' . urlencode('답변 내용을 입력해 주세요.') . '&msg_type=warning');
            exit;
        }
        $answered_by = $_SESSION['ad_admin_username'] ?? 'admin';
        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_qna SET answer = ?, answered_by = ?, answered_at = NOW() WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssi', $answer, $answered_by, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        header('Location: admin_qna.php?msg=' . urlencode('답변이 저장되었습니다.') . '&msg_type=success');
        exit;
    }

    // 삭제
    if ($action === 'delete') {
        $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_qna WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        header('Location: admin_qna.php?msg=' . urlencode('Q&A가 삭제되었습니다.') . '&msg_type=success');
        exit;
    }

    // 공개/비공개 토글
    if ($action === 'toggle_public') {
        $stmt = mysqli_prepare($connect, "UPDATE gangnamunni_qna SET is_public = IF(is_public = 1, 0, 1) WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        header('Location: admin_qna.php?msg=' . urlencode('공개 상태가 변경되었습니다.') . '&msg_type=success');
        exit;
    }

    // 알 수 없는 액션
    header('Location: admin_qna.php');
    exit;
}

// ===== 필터 / 검색 / 페이징 =====
$filter = $_GET['filter'] ?? '';       // answered / unanswered / ''
$search = $_GET['q'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 동적 WHERE 절 (prepared statement)
$where_clauses = ["1=1"];
$bind_types = '';
$bind_values = [];

if ($filter === 'answered') {
    $where_clauses[] = "answer IS NOT NULL AND answer != ''";
} elseif ($filter === 'unanswered') {
    $where_clauses[] = "(answer IS NULL OR answer = '')";
}

if ($search !== '') {
    $where_clauses[] = "(user_name LIKE ? OR title LIKE ? OR content LIKE ?)";
    $bind_types .= 'sss';
    $search_like = '%' . $search . '%';
    $bind_values[] = $search_like;
    $bind_values[] = $search_like;
    $bind_values[] = $search_like;
}

$where = implode(' AND ', $where_clauses);

// 총 건수
$count_sql = "SELECT COUNT(*) as cnt FROM gangnamunni_qna WHERE " . $where;
$stmt_c = mysqli_prepare($connect, $count_sql);
if ($bind_types && $stmt_c) {
    mysqli_stmt_bind_param($stmt_c, $bind_types, ...$bind_values);
}
if ($stmt_c) {
    mysqli_stmt_execute($stmt_c);
    $count_r = mysqli_stmt_get_result($stmt_c);
    $total = $count_r ? (int)mysqli_fetch_assoc($count_r)['cnt'] : 0;
    mysqli_stmt_close($stmt_c);
} else {
    $total = 0;
}
$total_pages = max(1, ceil($total / $per_page));

// 데이터 조회
$data_sql = "SELECT q.*, h.name AS hospital_name FROM gangnamunni_qna q LEFT JOIN gangnamunni_hospitals h ON q.hospital_id = h.id WHERE " . $where . " ORDER BY q.created_at DESC LIMIT ? OFFSET ?";
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
$stats = ['total' => 0, 'answered' => 0, 'unanswered' => 0];
$sr = mysqli_query($connect, "SELECT COUNT(*) as total, SUM(CASE WHEN answer IS NOT NULL AND answer != '' THEN 1 ELSE 0 END) as answered, SUM(CASE WHEN answer IS NULL OR answer = '' THEN 1 ELSE 0 END) as unanswered FROM gangnamunni_qna");
if ($sr) {
    $st = mysqli_fetch_assoc($sr);
    $stats['total'] = (int)($st['total'] ?? 0);
    $stats['answered'] = (int)($st['answered'] ?? 0);
    $stats['unanswered'] = (int)($st['unanswered'] ?? 0);
}

// GET 메시지
$msg = $_GET['msg'] ?? '';
$msg_type = $_GET['msg_type'] ?? 'info';

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <?php if ($msg): ?>
    <div class="alert alert-<?php echo htmlspecialchars($msg_type); ?> alert-dismissible fade show">
        <?php echo htmlspecialchars($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="bi bi-question-circle me-2"></i>Q&A 관리</h4>
        <span class="badge bg-primary"><?php echo number_format($stats['total']); ?>건</span>
    </div>

    <!-- 통계 카드 -->
    <div class="row g-3 mb-4">
        <?php foreach ([
            ['전체', $stats['total'], 'primary', ''],
            ['답변완료', $stats['answered'], 'success', 'answered'],
            ['미답변', $stats['unanswered'], 'danger', 'unanswered'],
        ] as $sc): ?>
        <div class="col-6 col-md">
            <a href="?filter=<?php echo $sc[3]; ?>" class="text-decoration-none">
                <div class="card text-center border-0 shadow-sm <?php echo $filter === $sc[3] ? 'border-' . $sc[2] . ' border-2' : ''; ?>">
                    <div class="card-body py-2">
                        <h5 class="mb-0"><?php echo number_format($sc[1]); ?></h5>
                        <small class="text-<?php echo $sc[2]; ?>"><?php echo $sc[0]; ?></small>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 필터 / 검색 -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <select name="filter" class="form-select form-select-sm">
                        <option value="">전체</option>
                        <option value="answered" <?php echo $filter === 'answered' ? 'selected' : ''; ?>>답변완료</option>
                        <option value="unanswered" <?php echo $filter === 'unanswered' ? 'selected' : ''; ?>>미답변</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="작성자, 제목, 내용 검색" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-search"></i> 검색</button>
                </div>
                <?php if ($filter || $search): ?>
                <div class="col-md-2">
                    <a href="admin_qna.php" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-x-circle"></i> 초기화</a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Q&A 목록 -->
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px;">#</th>
                        <th>작성자</th>
                        <th>제목</th>
                        <th style="width:100px;">답변상태</th>
                        <th style="width:80px;">공개</th>
                        <th style="width:80px;">조회</th>
                        <th style="width:140px;">등록일</th>
                        <th style="width:130px;">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox" style="font-size:2rem;"></i><br>Q&A 항목이 없습니다.
                        </td>
                    </tr>
                    <?php else: foreach ($rows as $q): ?>
                    <?php
                        $is_answered = !empty($q['answer']);
                    ?>
                    <tr>
                        <td><?php echo $q['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($q['user_name'] ?? ''); ?></strong>
                            <?php if (!empty($q['hospital_name'])): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($q['hospital_name']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="#" class="text-decoration-none" onclick="showDetail(<?php echo $q['id']; ?>); return false;">
                                <?php echo htmlspecialchars(mb_substr($q['title'], 0, 40, 'UTF-8') . (mb_strlen($q['title'], 'UTF-8') > 40 ? '...' : '')); ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($is_answered): ?>
                            <span class="badge bg-success">답변완료</span>
                            <?php else: ?>
                            <span class="badge bg-danger">미답변</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="toggle_public">
                                <input type="hidden" name="id" value="<?php echo $q['id']; ?>">
                                <?php if ($q['is_public']): ?>
                                <button type="submit" class="btn btn-sm btn-outline-success" title="공개 (클릭하면 비공개로 전환)"><i class="bi bi-eye"></i></button>
                                <?php else: ?>
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="비공개 (클릭하면 공개로 전환)"><i class="bi bi-eye-slash"></i></button>
                                <?php endif; ?>
                            </form>
                        </td>
                        <td><?php echo number_format($q['view_count'] ?? 0); ?></td>
                        <td><small><?php echo substr($q['created_at'] ?? '', 0, 16); ?></small></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="showDetail(<?php echo $q['id']; ?>)" title="상세보기/답변">
                                <i class="bi bi-chat-left-text"></i>
                            </button>
                            <form method="post" class="d-inline" onsubmit="return confirm('정말 삭제하시겠습니까?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $q['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="삭제"><i class="bi bi-trash"></i></button>
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
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&filter=<?php echo urlencode($filter); ?>&q=<?php echo urlencode($search); ?>">&laquo;</a>
                    </li>
                    <?php endif; ?>
                    <?php
                    // 페이지 번호 범위 계산 (최대 10개 표시)
                    $start_page = max(1, $page - 4);
                    $end_page = min($total_pages, $start_page + 9);
                    if ($end_page - $start_page < 9) $start_page = max(1, $end_page - 9);
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&filter=<?php echo urlencode($filter); ?>&q=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&filter=<?php echo urlencode($filter); ?>&q=<?php echo urlencode($search); ?>">&raquo;</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Q&A 상세/답변 모달 -->
<div class="modal fade" id="qnaDetailModal" tabindex="-1" aria-labelledby="qnaDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qnaDetailModalLabel"><i class="bi bi-question-circle me-2"></i>Q&A 상세</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="닫기"></button>
            </div>
            <div class="modal-body" id="qnaDetailBody">
                <div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
// Q&A 데이터를 JS 객체로 전달 (모달 렌더링용)
var qnaData = {};
<?php foreach ($rows as $q): ?>
qnaData[<?php echo $q['id']; ?>] = {
    id: <?php echo $q['id']; ?>,
    user_name: <?php echo json_encode($q['user_name'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
    hospital_name: <?php echo json_encode($q['hospital_name'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
    title: <?php echo json_encode($q['title'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
    content: <?php echo json_encode($q['content'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
    answer: <?php echo json_encode($q['answer'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
    answered_by: <?php echo json_encode($q['answered_by'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
    answered_at: <?php echo json_encode($q['answered_at'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
    is_public: <?php echo $q['is_public'] ? 1 : 0; ?>,
    view_count: <?php echo intval($q['view_count'] ?? 0); ?>,
    created_at: <?php echo json_encode($q['created_at'] ?? '', JSON_UNESCAPED_UNICODE); ?>
};
<?php endforeach; ?>

function escapeHtml(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

function nl2br(str) {
    if (!str) return '';
    return escapeHtml(str).replace(/\n/g, '<br>');
}

function showDetail(id) {
    var q = qnaData[id];
    if (!q) return;

    var isAnswered = q.answer && q.answer.trim() !== '';
    var statusBadge = isAnswered
        ? '<span class="badge bg-success">답변완료</span>'
        : '<span class="badge bg-danger">미답변</span>';
    var publicBadge = q.is_public
        ? '<span class="badge bg-info">공개</span>'
        : '<span class="badge bg-secondary">비공개</span>';

    var html = '';

    // 질문 영역
    html += '<div class="mb-4">';
    html += '<div class="d-flex justify-content-between align-items-start mb-2">';
    html += '<h5 class="mb-0">' + escapeHtml(q.title) + '</h5>';
    html += '<div>' + statusBadge + ' ' + publicBadge + '</div>';
    html += '</div>';
    html += '<div class="text-muted small mb-2">';
    html += '<i class="bi bi-person me-1"></i>' + escapeHtml(q.user_name);
    if (q.hospital_name) {
        html += ' &middot; <i class="bi bi-hospital me-1"></i>' + escapeHtml(q.hospital_name);
    }
    html += ' &middot; <i class="bi bi-clock me-1"></i>' + escapeHtml(q.created_at);
    html += ' &middot; <i class="bi bi-eye me-1"></i>' + q.view_count + '회';
    html += '</div>';
    html += '<div class="card"><div class="card-body bg-light">' + nl2br(q.content) + '</div></div>';
    html += '</div>';

    // 기존 답변 표시
    if (isAnswered) {
        html += '<div class="mb-4">';
        html += '<h6 class="text-success"><i class="bi bi-chat-left-dots me-1"></i>답변</h6>';
        html += '<div class="card border-success"><div class="card-body">' + nl2br(q.answer) + '</div></div>';
        html += '<div class="text-muted small mt-1">';
        html += '<i class="bi bi-person-badge me-1"></i>' + escapeHtml(q.answered_by);
        html += ' &middot; ' + escapeHtml(q.answered_at);
        html += '</div>';
        html += '</div>';
    }

    // 답변 작성/수정 폼
    html += '<hr>';
    html += '<h6><i class="bi bi-pencil-square me-1"></i>' + (isAnswered ? '답변 수정' : '답변 작성') + '</h6>';
    html += '<form method="post" action="admin_qna.php">';
    html += '<input type="hidden" name="action" value="answer">';
    html += '<input type="hidden" name="id" value="' + q.id + '">';
    html += '<div class="mb-3">';
    html += '<textarea name="answer" class="form-control" rows="5" placeholder="답변을 입력하세요..." required>' + escapeHtml(q.answer) + '</textarea>';
    html += '</div>';
    html += '<div class="d-flex justify-content-end gap-2">';
    html += '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>';
    html += '<button type="submit" class="btn btn-primary"><i class="bi bi-send me-1"></i>' + (isAnswered ? '답변 수정' : '답변 등록') + '</button>';
    html += '</div>';
    html += '</form>';

    document.getElementById('qnaDetailBody').innerHTML = html;
    var modal = new bootstrap.Modal(document.getElementById('qnaDetailModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
