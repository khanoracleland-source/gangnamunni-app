<?php
/**
 * 매뉴얼 수정요청 관리 목록
 * @created 2026-04-08
 */
session_start();
$admin_page_title   = '매뉴얼 수정요청 관리';
$admin_current      = 'manual_feedback';

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/theme_helper.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();

// ─── 필터 ───
$f_status   = trim($_GET['status']   ?? '');
$f_priority = trim($_GET['priority'] ?? '');
$f_type     = trim($_GET['type']     ?? '');
$f_role     = trim($_GET['role']     ?? '');
$f_keyword  = trim($_GET['keyword']  ?? '');
$page       = max(1, intval($_GET['p'] ?? 1));
$per_page   = 20;
$offset     = ($page - 1) * $per_page;

$allowed_status   = ['requested','prompt_ready','in_progress','done','hold','rejected'];
$allowed_priority = ['low','normal','high','urgent'];
$allowed_types    = ['bug_fix','feature_add','content_edit','update','other'];
$allowed_roles    = ['client','developer','manager','other'];

$where  = [];
$params = [];
$types  = '';

if ($f_status   && in_array($f_status, $allowed_status))   { $where[] = "status=?";            $types .= 's'; $params[] = $f_status; }
if ($f_priority && in_array($f_priority, $allowed_priority)){ $where[] = "priority=?";          $types .= 's'; $params[] = $f_priority; }
if ($f_type     && in_array($f_type, $allowed_types))       { $where[] = "request_type=?";      $types .= 's'; $params[] = $f_type; }
if ($f_role     && in_array($f_role, $allowed_roles))       { $where[] = "created_by_role=?";   $types .= 's'; $params[] = $f_role; }
if ($f_keyword) { $like = '%' . $f_keyword . '%'; $where[] = "(request_title LIKE ? OR request_text LIKE ?)"; $types .= 'ss'; $params[] = $like; $params[] = $like; }

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$count_sql  = "SELECT COUNT(*) FROM manual_feedback_items $where_sql";
$stmt_count = mysqli_prepare($connect, $count_sql);
if ($types) mysqli_stmt_bind_param($stmt_count, $types, ...$params);
mysqli_stmt_execute($stmt_count);
$total = mysqli_stmt_get_result($stmt_count)->fetch_row()[0] ?? 0;
mysqli_stmt_close($stmt_count);

$list_sql  = "SELECT * FROM manual_feedback_items $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$types_p   = $types . 'ii';
$params_p  = array_merge($params, [$per_page, $offset]);
$stmt_list = mysqli_prepare($connect, $list_sql);
mysqli_stmt_bind_param($stmt_list, $types_p, ...$params_p);
mysqli_stmt_execute($stmt_list);
$items = mysqli_stmt_get_result($stmt_list)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt_list);

$total_pages = max(1, ceil($total / $per_page));

// ─── 라벨 ───
$status_labels = ['requested'=>'접수','prompt_ready'=>'프롬프트 준비','in_progress'=>'진행중','done'=>'완료','hold'=>'보류','rejected'=>'거절'];
$status_colors = ['requested'=>'primary','prompt_ready'=>'info','in_progress'=>'warning','done'=>'success','hold'=>'secondary','rejected'=>'danger'];
$priority_labels = ['low'=>'낮음','normal'=>'보통','high'=>'높음','urgent'=>'긴급'];
$priority_colors = ['low'=>'secondary','normal'=>'info','high'=>'warning','urgent'=>'danger'];
$type_labels = ['bug_fix'=>'오류수정','feature_add'=>'기능추가','content_edit'=>'내용수정','update'=>'업데이트','other'=>'기타'];
$role_labels = ['client'=>'클라이언트','developer'=>'개발자','manager'=>'매니저','other'=>'기타'];

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid px-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-chat-left-text me-2"></i>매뉴얼 수정요청 관리</h4>
    <div class="d-flex gap-2">
      <span class="badge bg-secondary"><?= number_format($total) ?> 건</span>
      <a href="https://hms.seoulbeauty.app/hairwang/gangnamunni/manual/index.html" target="_blank" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-box-arrow-up-right me-1"></i>매뉴얼 페이지
      </a>
    </div>
  </div>

  <!-- 필터 -->
  <form method="get" class="card card-body mb-3 p-3">
    <div class="row g-2 align-items-end">
      <div class="col-sm-2">
        <label class="form-label form-label-sm mb-1">상태</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">전체</option>
          <?php foreach ($status_labels as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $f_status===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <label class="form-label form-label-sm mb-1">중요도</label>
        <select name="priority" class="form-select form-select-sm">
          <option value="">전체</option>
          <?php foreach ($priority_labels as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $f_priority===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <label class="form-label form-label-sm mb-1">요청유형</label>
        <select name="type" class="form-select form-select-sm">
          <option value="">전체</option>
          <?php foreach ($type_labels as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $f_type===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <label class="form-label form-label-sm mb-1">작성자구분</label>
        <select name="role" class="form-select form-select-sm">
          <option value="">전체</option>
          <?php foreach ($role_labels as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $f_role===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-3">
        <label class="form-label form-label-sm mb-1">키워드</label>
        <input type="text" name="keyword" class="form-control form-control-sm" value="<?= htmlspecialchars($f_keyword) ?>" placeholder="제목/내용 검색">
      </div>
      <div class="col-sm-1 d-flex gap-1">
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
        <a href="?" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x"></i></a>
      </div>
    </div>
  </form>

  <!-- 목록 -->
  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" style="font-size:0.875rem;">
        <thead class="table-light">
          <tr>
            <th style="width:60px">#</th>
            <th style="width:130px">등록일시</th>
            <th style="width:90px">작성자</th>
            <th style="width:80px">구분</th>
            <th style="width:85px">유형</th>
            <th style="width:75px">중요도</th>
            <th>요청 제목</th>
            <th style="width:120px">페이지</th>
            <th style="width:90px">상태</th>
            <th style="width:60px">상세</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($items)): ?>
          <tr><td colspan="10" class="text-center py-4 text-muted">등록된 요청이 없습니다.</td></tr>
        <?php else: ?>
          <?php $prev_date = ''; ?>
          <?php foreach ($items as $item): ?>
            <?php
              $cur_date = date('Y-m-d', strtotime($item['created_at']));
              if ($cur_date !== $prev_date) {
                $prev_date = $cur_date;
                echo "<tr class='table-secondary'><td colspan='10' class='py-1 px-3' style='font-size:0.8rem;font-weight:600;'><i class='bi bi-calendar3 me-1'></i>{$cur_date}</td></tr>";
              }
            ?>
            <tr>
              <td class="text-muted"><?= $item['id'] ?></td>
              <td class="text-muted" style="font-size:0.78rem;"><?= date('H:i', strtotime($item['created_at'])) ?></td>
              <td><?= htmlspecialchars($item['created_by_name']) ?></td>
              <td><span class="badge bg-light text-dark border"><?= $role_labels[$item['created_by_role']] ?? $item['created_by_role'] ?></span></td>
              <td><span class="badge bg-light text-dark border"><?= $type_labels[$item['request_type']] ?? $item['request_type'] ?></span></td>
              <td>
                <span class="badge bg-<?= $priority_colors[$item['priority']] ?? 'secondary' ?>">
                  <?= $priority_labels[$item['priority']] ?? $item['priority'] ?>
                </span>
              </td>
              <td>
                <div style="max-width:320px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;" title="<?= htmlspecialchars($item['request_title']) ?>">
                  <?= htmlspecialchars($item['request_title']) ?>
                </div>
                <?php if ($item['progress_note']): ?>
                  <div class="text-muted" style="font-size:0.75rem;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;max-width:320px;">
                    <i class="bi bi-chat-right-text me-1"></i><?= htmlspecialchars(mb_substr($item['progress_note'],0,60)) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td class="text-muted" style="font-size:0.75rem;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;max-width:120px;"
                  title="<?= htmlspecialchars($item['page_url']) ?>">
                <?= htmlspecialchars($item['page_path'] ?: $item['page_title'] ?: '-') ?>
              </td>
              <td>
                <span class="badge bg-<?= $status_colors[$item['status']] ?? 'secondary' ?>">
                  <?= $status_labels[$item['status']] ?? $item['status'] ?>
                </span>
              </td>
              <td>
                <a href="admin_manual_feedback_detail.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-2">
                  <i class="bi bi-arrow-right"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 페이징 -->
  <?php if ($total_pages > 1): ?>
  <nav class="mt-3 d-flex justify-content-center">
    <ul class="pagination pagination-sm mb-0">
      <?php for ($i = max(1,$page-4); $i <= min($total_pages,$page+4); $i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['p'=>$i])) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
