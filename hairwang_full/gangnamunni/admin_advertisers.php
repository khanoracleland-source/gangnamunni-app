<?php
/**
 * 광고주(병원) 관리 페이지
 * Bootstrap 5.3 + 공통 관리자 레이아웃
 */

session_start();


$admin_page_title = '광고주 관리';
$admin_current = 'advertisers';

require_once __DIR__ . '/includes/admin_auth.php';
require_permission('ads');

require_once __DIR__ . '/../dbconfig.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
require_once __DIR__ . '/theme_helper.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
loadThemeSettings($connect);

$message = '';
$error = '';

// 광고주 추가
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_advertiser'])) {
    $hospital_name = mysqli_real_escape_string($connect, trim($_POST['hospital_name']));
    $contact_name = mysqli_real_escape_string($connect, trim($_POST['contact_name']));
    $contact_email = mysqli_real_escape_string($connect, trim($_POST['contact_email']));
    $contact_phone = mysqli_real_escape_string($connect, trim($_POST['contact_phone']));
    $business_number = mysqli_real_escape_string($connect, trim($_POST['business_number']));
    $address = mysqli_real_escape_string($connect, trim($_POST['address']));
    $memo = mysqli_real_escape_string($connect, trim($_POST['memo']));

    if (empty($hospital_name)) {
        $error = '병원명은 필수입니다.';
    } else {
        $sql = "INSERT INTO gangnamunni_advertisers (hospital_name, contact_name, contact_email, contact_phone, business_number, address, memo)
                VALUES ('$hospital_name', '$contact_name', '$contact_email', '$contact_phone', '$business_number', '$address', '$memo')";
        if (mysqli_query($connect, $sql)) {
            $message = '광고주가 등록되었습니다.';
        } else {
            $error = '등록 실패: ' . mysqli_error($connect);
        }
    }
}

// 광고주 수정
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_advertiser') {
    $id = intval($_POST['id']);
    $hospital_name = mysqli_real_escape_string($connect, trim($_POST['hospital_name']));
    $contact_name = mysqli_real_escape_string($connect, trim($_POST['contact_name']));
    $contact_email = mysqli_real_escape_string($connect, trim($_POST['contact_email']));
    $contact_phone = mysqli_real_escape_string($connect, trim($_POST['contact_phone']));
    $business_number = mysqli_real_escape_string($connect, trim($_POST['business_number']));
    $address = mysqli_real_escape_string($connect, trim($_POST['address']));
    $memo = mysqli_real_escape_string($connect, trim($_POST['memo']));
    $status = mysqli_real_escape_string($connect, $_POST['status'] ?? 'active');

    $sql = "UPDATE gangnamunni_advertisers SET
            hospital_name='$hospital_name', contact_name='$contact_name', contact_email='$contact_email',
            contact_phone='$contact_phone', business_number='$business_number', address='$address',
            memo='$memo', status='$status' WHERE id=$id";
    if (mysqli_query($connect, $sql)) {
        $message = '광고주 정보가 수정되었습니다.';
    } else {
        $error = '수정 실패: ' . mysqli_error($connect);
    }
}

// 광고주 삭제
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // 연결된 광고가 있는지 확인
    $r = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_ads WHERE advertiser_id = $id");
    $cnt = mysqli_fetch_assoc($r)['cnt'] ?? 0;
    if ($cnt > 0) {
        $error = "이 광고주에 연결된 광고가 {$cnt}개 있어 삭제할 수 없습니다. 먼저 광고를 삭제해주세요.";
    } else {
        if (mysqli_query($connect, "DELETE FROM gangnamunni_advertisers WHERE id = $id")) {
            $message = '광고주가 삭제되었습니다.';
        }
    }
}

// 검색
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$where = "1=1";
if ($search) {
    $s = mysqli_real_escape_string($connect, $search);
    $where .= " AND (hospital_name LIKE '%$s%' OR contact_name LIKE '%$s%' OR contact_email LIKE '%$s%' OR contact_phone LIKE '%$s%')";
}
if ($status_filter) {
    $where .= " AND status = '" . mysqli_real_escape_string($connect, $status_filter) . "'";
}

// 페이지네이션
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$total = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_advertisers WHERE $where"))['cnt'];
$total_pages = ceil($total / $per_page);

$advertisers = [];
$result = mysqli_query($connect, "
    SELECT a.*,
        (SELECT COUNT(*) FROM gangnamunni_ads WHERE advertiser_id = a.id) as ad_count,
        (SELECT SUM(impression_count) FROM gangnamunni_ads WHERE advertiser_id = a.id) as total_impressions,
        (SELECT SUM(click_count) FROM gangnamunni_ads WHERE advertiser_id = a.id) as total_clicks
    FROM gangnamunni_advertisers a
    WHERE $where
    ORDER BY a.created_at DESC
    LIMIT $per_page OFFSET $offset
");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $advertisers[] = $row;
    }
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-building"></i> 광고주 관리 <small class="text-muted">(<?= $total ?>명)</small></h4>
    <div class="btn-group btn-group-sm me-2">
        <button class="btn btn-outline-secondary" onclick="toggleAllAds(true)" title="전체 펼치기"><i class="bi bi-arrows-expand"></i></button>
        <button class="btn btn-outline-secondary" onclick="toggleAllAds(false)" title="전체 접기"><i class="bi bi-arrows-collapse"></i></button>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg"></i> 광고주 등록
    </button>
</div>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    <?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- 검색/필터 -->
<div class="admin-toolbar">
    <form class="d-flex gap-2" method="GET">
        <input type="text" name="search" class="form-control form-control-sm search-box" placeholder="병원명, 담당자, 이메일 검색..." value="<?= htmlspecialchars($search) ?>">
        <select name="status" class="form-select form-select-sm" style="width:auto;">
            <option value="">전체 상태</option>
            <option value="active" <?= $status_filter==='active'?'selected':'' ?>>활성</option>
            <option value="inactive" <?= $status_filter==='inactive'?'selected':'' ?>>비활성</option>
            <option value="suspended" <?= $status_filter==='suspended'?'selected':'' ?>>정지</option>
        </select>
        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
    </form>
</div>

<!-- 광고주 목록 -->
<div class="table-responsive">
<table class="table table-hover">
    <thead>
        <tr>
            <th>ID</th>
            <th>병원명</th>
            <th>담당자</th>
            <th>연락처</th>
            <th>광고수</th>
            <th>총 노출</th>
            <th>총 클릭</th>
            <th>상태</th>
            <th>등록일</th>
            <th>관리</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($advertisers)): ?>
        <tr><td colspan="10" class="text-center text-muted py-4">등록된 광고주가 없습니다.</td></tr>
    <?php else: ?>
        <?php
        // 광고주별 광고 목록 미리 로드
        $adv_ads = [];
        $ads_r = mysqli_query($connect, "SELECT id, title, ad_type, is_active, impression_count, click_count, start_date, end_date, advertiser_id FROM gangnamunni_ads WHERE advertiser_id IS NOT NULL ORDER BY display_order ASC");
        if ($ads_r) {
            while ($arow = mysqli_fetch_assoc($ads_r)) {
                $adv_ads[$arow['advertiser_id']][] = $arow;
            }
        }
        ?>
        <?php foreach ($advertisers as $adv):
            $my_ads = $adv_ads[$adv['id']] ?? [];
            $ad_count = count($my_ads);
            $collapse_id = 'ads_' . $adv['id'];
        ?>
        <tr class="<?= $ad_count > 0 ? 'cursor-pointer' : '' ?>"
            <?= $ad_count > 0 ? 'onclick="toggleAds(\'' . $collapse_id . '\')"' : '' ?>
            style="<?= $ad_count > 0 ? 'cursor:pointer;' : '' ?>">
            <td><?= $adv['id'] ?></td>
            <td>
                <strong><?= htmlspecialchars($adv['hospital_name']) ?></strong>
                <?php if ($ad_count > 0): ?>
                <i class="bi bi-chevron-down small text-muted ms-1 chevron-icon" id="chevron_<?= $collapse_id ?>"></i>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($adv['contact_name'] ?? '-') ?></td>
            <td>
                <small><?= htmlspecialchars($adv['contact_email'] ?? '') ?></small><br>
                <small class="text-muted"><?= htmlspecialchars($adv['contact_phone'] ?? '') ?></small>
            </td>
            <td>
                <?php if ($ad_count > 0): ?>
                <span class="badge bg-primary"><?= $ad_count ?>개</span>
                <?php else: ?>
                <span class="badge bg-secondary">0개</span>
                <?php endif; ?>
            </td>
            <td><?= number_format($adv['total_impressions'] ?? 0) ?></td>
            <td><?= number_format($adv['total_clicks'] ?? 0) ?></td>
            <td>
                <?php
                $status_badges = ['active'=>'success', 'inactive'=>'secondary', 'suspended'=>'danger'];
                $status_labels = ['active'=>'활성', 'inactive'=>'비활성', 'suspended'=>'정지'];
                ?>
                <span class="badge bg-<?= $status_badges[$adv['status']] ?? 'secondary' ?>">
                    <?= $status_labels[$adv['status']] ?? $adv['status'] ?>
                </span>
            </td>
            <td><small><?= date('Y-m-d', strtotime($adv['created_at'])) ?></small></td>
            <td onclick="event.stopPropagation()">
                <button class="btn btn-sm btn-outline-primary edit-btn"
                    data-id="<?= $adv['id'] ?>"
                    data-hospital="<?= htmlspecialchars($adv['hospital_name']) ?>"
                    data-contact="<?= htmlspecialchars($adv['contact_name'] ?? '') ?>"
                    data-email="<?= htmlspecialchars($adv['contact_email'] ?? '') ?>"
                    data-phone="<?= htmlspecialchars($adv['contact_phone'] ?? '') ?>"
                    data-biz="<?= htmlspecialchars($adv['business_number'] ?? '') ?>"
                    data-address="<?= htmlspecialchars($adv['address'] ?? '') ?>"
                    data-memo="<?= htmlspecialchars($adv['memo'] ?? '') ?>"
                    data-status="<?= $adv['status'] ?>"
                    data-bs-toggle="modal" data-bs-target="#editModal">
                    <i class="bi bi-pencil"></i>
                </button>
                <a href="?delete=<?= $adv['id'] ?>" class="btn btn-sm btn-outline-danger"
                   onclick="event.stopPropagation();return confirm('삭제하시겠습니까?')"><i class="bi bi-trash"></i></a>
            </td>
        </tr>
        <?php if ($ad_count > 0): ?>
        <tr class="ads-detail-row" id="<?= $collapse_id ?>" style="display:none;">
            <td colspan="10" class="p-0">
                <div class="bg-body-secondary">
                    <table class="table table-sm mb-0" style="font-size:12px;">
                        <thead>
                            <tr class="table-light">
                                <th style="width:50px;padding-left:24px;">ID</th>
                                <th>광고 제목</th>
                                <th style="width:70px;">유형</th>
                                <th style="width:60px;">상태</th>
                                <th style="width:80px;">노출수</th>
                                <th style="width:80px;">클릭수</th>
                                <th style="width:60px;">CTR</th>
                                <th style="width:100px;">기간</th>
                                <th style="width:60px;">관리</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($my_ads as $mad): ?>
                            <tr>
                                <td style="padding-left:24px;"><?= $mad['id'] ?></td>
                                <td><?= htmlspecialchars($mad['title']) ?></td>
                                <td><span class="badge bg-<?= $mad['ad_type']==='premium'?'warning':'info' ?>"><?= $mad['ad_type'] ?></span></td>
                                <td><?= $mad['is_active'] ? '<span class="badge bg-success">ON</span>' : '<span class="badge bg-secondary">OFF</span>' ?></td>
                                <td><?= number_format($mad['impression_count'] ?? 0) ?></td>
                                <td><?= number_format($mad['click_count'] ?? 0) ?></td>
                                <td>
                                    <?php $ctr = ($mad['impression_count'] > 0) ? round($mad['click_count'] / $mad['impression_count'] * 100, 1) : 0; ?>
                                    <?= $ctr ?>%
                                </td>
                                <td>
                                    <?php if ($mad['start_date']): ?>
                                    <small><?= date('m/d', strtotime($mad['start_date'])) ?> ~ <?= $mad['end_date'] ? date('m/d', strtotime($mad['end_date'])) : '무기한' ?></small>
                                    <?php else: ?>
                                    <small class="text-muted">미설정</small>
                                    <?php endif; ?>
                                </td>
                                <td><a href="admin_ads.php?search=<?= urlencode($mad['title']) ?>" class="btn btn-sm btn-outline-secondary py-0 px-1"><i class="bi bi-pencil-square"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
</div>

<!-- 페이지네이션 -->
<?php if ($total_pages > 1): ?>
<nav>
    <ul class="pagination pagination-sm justify-content-center">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>">
            <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<!-- 광고주 등록 모달 -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-building-add"></i> 광고주 등록</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- 병원 검색 -->
                <div class="card mb-3 border-primary">
                    <div class="card-header bg-primary bg-opacity-10 py-2">
                        <i class="bi bi-search"></i> <strong>병원 검색하여 선택</strong>
                    </div>
                    <div class="card-body py-2">
                        <div class="input-group input-group-sm mb-2">
                            <input type="text" id="hospitalSearch" class="form-control" placeholder="병원명 또는 주소로 검색..." autocomplete="off">
                            <button type="button" class="btn btn-outline-primary" onclick="searchHospitals()"><i class="bi bi-search"></i></button>
                        </div>
                        <div id="hospitalResults" style="max-height:220px;overflow-y:auto;"></div>
                    </div>
                </div>
                <hr class="my-2">
                <input type="hidden" name="hospital_id" id="add_hospital_id" value="">
                <div class="mb-3">
                    <label class="form-label fw-bold">병원명 <span class="text-danger">*</span></label>
                    <input type="text" name="hospital_name" id="add_hospital_name" class="form-control" required>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">담당자명</label>
                        <input type="text" name="contact_name" id="add_contact_name" class="form-control">
                    </div>
                    <div class="col-6">
                        <label class="form-label">사업자번호</label>
                        <input type="text" name="business_number" id="add_biz" class="form-control" placeholder="000-00-00000">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">이메일</label>
                        <input type="email" name="contact_email" id="add_email" class="form-control">
                    </div>
                    <div class="col-6">
                        <label class="form-label">연락처</label>
                        <input type="text" name="contact_phone" id="add_phone" class="form-control" placeholder="010-0000-0000">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">주소</label>
                    <input type="text" name="address" id="add_address" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">메모</label>
                    <textarea name="memo" id="add_memo" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="submit" name="add_advertiser" class="btn btn-primary">등록</button>
            </div>
        </form>
    </div>
</div>

<!-- 광고주 수정 모달 -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="update_advertiser">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil"></i> 광고주 수정</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">병원명 <span class="text-danger">*</span></label>
                    <input type="text" name="hospital_name" id="edit_hospital" class="form-control" required>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">담당자명</label>
                        <input type="text" name="contact_name" id="edit_contact" class="form-control">
                    </div>
                    <div class="col-6">
                        <label class="form-label">사업자번호</label>
                        <input type="text" name="business_number" id="edit_biz" class="form-control">
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">이메일</label>
                        <input type="email" name="contact_email" id="edit_email" class="form-control">
                    </div>
                    <div class="col-6">
                        <label class="form-label">연락처</label>
                        <input type="text" name="contact_phone" id="edit_phone" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">주소</label>
                    <input type="text" name="address" id="edit_address" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">메모</label>
                    <textarea name="memo" id="edit_memo" class="form-control" rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">상태</label>
                    <select name="status" id="edit_status" class="form-select">
                        <option value="active">활성</option>
                        <option value="inactive">비활성</option>
                        <option value="suspended">정지</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="submit" class="btn btn-primary">수정</button>
            </div>
        </form>
    </div>
</div>


<style>
.ads-detail-row td { background: var(--bs-tertiary-bg) !important; }
.ads-detail-row .table { border-collapse: collapse; }
.ads-detail-row .table td, .ads-detail-row .table th { border-color: var(--bs-border-color); }
tr[onclick] td:hover { background: var(--bs-tertiary-bg); }
</style>
</div><!-- /admin-main -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// 광고 그룹 확장/축소
function toggleAds(id) {
    var row = document.getElementById(id);
    var chevron = document.getElementById('chevron_' + id);
    if (!row) return;
    if (row.style.display === 'none') {
        row.style.display = '';
        if (chevron) chevron.classList.replace('bi-chevron-down', 'bi-chevron-up');
    } else {
        row.style.display = 'none';
        if (chevron) chevron.classList.replace('bi-chevron-up', 'bi-chevron-down');
    }
}

// 전체 펼치기/접기
function toggleAllAds(expand) {
    document.querySelectorAll('.ads-detail-row').forEach(function(r) {
        r.style.display = expand ? '' : 'none';
    });
    document.querySelectorAll('.chevron-icon').forEach(function(c) {
        if (expand) {
            c.classList.replace('bi-chevron-down', 'bi-chevron-up');
        } else {
            c.classList.replace('bi-chevron-up', 'bi-chevron-down');
        }
    });
}

// 수정 모달 데이터 바인딩
document.querySelectorAll('.edit-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('edit_id').value = this.dataset.id;
        document.getElementById('edit_hospital').value = this.dataset.hospital;
        document.getElementById('edit_contact').value = this.dataset.contact;
        document.getElementById('edit_email').value = this.dataset.email;
        document.getElementById('edit_phone').value = this.dataset.phone;
        document.getElementById('edit_biz').value = this.dataset.biz;
        document.getElementById('edit_address').value = this.dataset.address;
        document.getElementById('edit_memo').value = this.dataset.memo;
        document.getElementById('edit_status').value = this.dataset.status;
    });
});

// 병원 검색 데이터 (PHP에서 JSON 주입)
var hospitalData = <?php
    $h_json = [];
    $h_r = mysqli_query($connect, "SELECT id, name, address, phone, email FROM gangnamunni_hospitals ORDER BY name");
    if ($h_r) { while ($h_row = mysqli_fetch_assoc($h_r)) { $h_json[] = $h_row; } }
    echo json_encode($h_json, JSON_UNESCAPED_UNICODE);
?>;

function searchHospitals() {
    var q = document.getElementById('hospitalSearch').value.trim().toLowerCase();
    var box = document.getElementById('hospitalResults');
    if (!q) {
        box.innerHTML = '<div class="text-muted small py-2 text-center">병원명 또는 주소를 입력하세요</div>';
        return;
    }
    var matches = hospitalData.filter(function(h) {
        return h.name.toLowerCase().indexOf(q) !== -1 || (h.address || '').toLowerCase().indexOf(q) !== -1;
    });
    if (matches.length === 0) {
        box.innerHTML = '<div class="text-muted small py-2 text-center">검색 결과가 없습니다</div>';
        return;
    }
    var html = '<div class="list-group list-group-flush">';
    matches.slice(0, 30).forEach(function(h) {
        var eName = h.name.replace(/&/g,'&amp;').replace(/</g,'&lt;');
        var eAddr = (h.address||'').replace(/&/g,'&amp;').replace(/</g,'&lt;');
        var ePhone = (h.phone||'').replace(/&/g,'&amp;').replace(/</g,'&lt;');
        html += '<a href="#" class="list-group-item list-group-item-action py-2 px-3 hospital-item" '
            + 'data-hid="' + h.id + '" data-name="' + eName + '" data-addr="' + eAddr + '" '
            + 'data-phone="' + ePhone + '" data-email="' + (h.email||'') + '">'
            + '<div class="d-flex justify-content-between align-items-center">'
            + '<div><strong class="small">' + eName + '</strong>'
            + '<div class="text-muted" style="font-size:11px;">' + eAddr + '</div></div>'
            + '<span class="badge bg-outline-primary border text-primary small">선택</span>'
            + '</div></a>';
    });
    html += '</div>';
    if (matches.length > 30) {
        html += '<div class="text-muted small text-center py-1">외 ' + (matches.length - 30) + '개 더...</div>';
    }
    box.innerHTML = html;
}

// 이벤트 위임으로 병원 선택
document.getElementById('hospitalResults')?.addEventListener('click', function(e) {
    var item = e.target.closest('.hospital-item');
    if (!item) return;
    e.preventDefault();
    document.getElementById('add_hospital_id').value = item.dataset.hid;
    document.getElementById('add_hospital_name').value = item.dataset.name;
    document.getElementById('add_address').value = item.dataset.addr;
    document.getElementById('add_phone').value = item.dataset.phone;
    document.getElementById('add_email').value = item.dataset.email;
    this.innerHTML = '<div class="alert alert-success py-2 px-3 mb-0 small d-flex align-items-center gap-2">'
        + '<i class="bi bi-check-circle-fill"></i> <strong>' + item.dataset.name + '</strong> 선택 완료'
        + '<button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="clearHospitalSelect()">변경</button></div>';
});

function clearHospitalSelect() {
    document.getElementById('hospitalResults').innerHTML = '';
    document.getElementById('hospitalSearch').value = '';
    document.getElementById('hospitalSearch').focus();
}

// 검색 입력 시 자동 검색 (디바운스 300ms)
var hsTimer;
document.getElementById('hospitalSearch')?.addEventListener('input', function() {
    clearTimeout(hsTimer);
    hsTimer = setTimeout(searchHospitals, 300);
});
document.getElementById('hospitalSearch')?.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') { e.preventDefault(); searchHospitals(); }
});

// 모달 열릴 때 초기화
document.getElementById('addModal')?.addEventListener('show.bs.modal', function() {
    document.getElementById('hospitalSearch').value = '';
    document.getElementById('hospitalResults').innerHTML = '';
    document.getElementById('add_hospital_id').value = '';
});

// 사이드바 토글
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    document.getElementById('adminSidebar').classList.toggle('show');
});

// 테마 토글
document.getElementById('themeToggle')?.addEventListener('click', function() {
    var t = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-bs-theme', t);
    localStorage.setItem('admin_theme', t);
    document.getElementById('themeIcon').className = 'bi ' + (t === 'dark' ? 'bi-sun' : 'bi-moon-stars');
});
(function(){
    var t = localStorage.getItem('admin_theme') || 'light';
    document.getElementById('themeIcon').className = 'bi ' + (t === 'dark' ? 'bi-sun' : 'bi-moon-stars');
})();
</script>
</body>
</html>
<?php mysqli_close($connect); ?>
