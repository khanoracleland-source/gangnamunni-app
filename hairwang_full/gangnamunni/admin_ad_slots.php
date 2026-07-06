<?php
session_start();

$admin_page_title = '광고 슬롯 관리';
$admin_current = 'ad_slots';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('MySQL 연결 실패: ' . mysqli_connect_error());
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

require_admin_login();
require_permission('ads');

$admin_name = $_SESSION['ad_admin_name'] ?? 'admin';
$message = isset($_GET['msg']) ? trim($_GET['msg']) : '';
$error = '';

// ======================================================================
// POST/GET 처리
// ======================================================================

// 슬롯 상품 추가
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $keyword_type = ($_POST['keyword_type'] ?? '') === 'region' ? 'region' : 'treatment';
    $rank_position = max(1, min(5, intval($_POST['rank_position'] ?? 1)));
    $monthly_price = max(0, intval($_POST['monthly_price'] ?? 0));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $treatment_id = $keyword_type === 'treatment' ? intval($_POST['treatment_id'] ?? 0) : 0;
    $region_name = $keyword_type === 'region' ? trim($_POST['region_name'] ?? '') : '';
    $region_name_esc = mysqli_real_escape_string($connect, $region_name);

    if ($keyword_type === 'treatment' && $treatment_id <= 0) {
        $error = '시술을 선택해주세요.';
    } elseif ($keyword_type === 'region' && $region_name === '') {
        $error = '지역명을 입력해주세요.';
    } else {
        // 중복(같은 키워드+순위) 체크
        if ($keyword_type === 'treatment') {
            $dup_r = mysqli_query($connect, "SELECT id FROM gangnamunni_ad_slot_products WHERE keyword_type='treatment' AND treatment_id={$treatment_id} AND rank_position={$rank_position}");
        } else {
            $dup_r = mysqli_query($connect, "SELECT id FROM gangnamunni_ad_slot_products WHERE keyword_type='region' AND region_name='{$region_name_esc}' AND rank_position={$rank_position}");
        }
        if ($dup_r && mysqli_num_rows($dup_r) > 0) {
            $error = '이미 동일한 키워드+순위 상품이 존재합니다.';
        } else {
            $tid_sql = $treatment_id > 0 ? $treatment_id : 'NULL';
            $region_sql = $region_name !== '' ? "'{$region_name_esc}'" : 'NULL';
            mysqli_query($connect, "
                INSERT INTO gangnamunni_ad_slot_products (keyword_type, treatment_id, region_name, rank_position, monthly_price, is_active)
                VALUES ('{$keyword_type}', {$tid_sql}, {$region_sql}, {$rank_position}, {$monthly_price}, {$is_active})
            ");
            $message = '슬롯 상품이 추가되었습니다.';
        }
    }
}

// 슬롯 상품 수정
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_product') {
    $id = intval($_POST['id'] ?? 0);
    $rank_position = max(1, min(5, intval($_POST['rank_position'] ?? 1)));
    $monthly_price = max(0, intval($_POST['monthly_price'] ?? 0));
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    if ($id > 0) {
        mysqli_query($connect, "UPDATE gangnamunni_ad_slot_products SET rank_position={$rank_position}, monthly_price={$monthly_price}, is_active={$is_active} WHERE id={$id}");
        $message = '슬롯 상품이 수정되었습니다.';
    }
}

// 슬롯 상품 삭제 (관련 배정 내역은 FK CASCADE로 함께 삭제됨)
if (isset($_GET['delete_product'])) {
    $id = intval($_GET['delete_product']);
    mysqli_query($connect, "DELETE FROM gangnamunni_ad_slot_products WHERE id={$id}");
    header('Location: admin_ad_slots.php?msg=' . urlencode('슬롯 상품이 삭제되었습니다.'));
    exit;
}

// 슬롯 상품 활성/비활성 토글
if (isset($_GET['toggle_product'])) {
    $id = intval($_GET['toggle_product']);
    mysqli_query($connect, "UPDATE gangnamunni_ad_slot_products SET is_active = 1 - is_active WHERE id={$id}");
    header('Location: admin_ad_slots.php');
    exit;
}

// 병원을 슬롯에 배정 (기존 배정이 있으면 자동 종료 후 교체)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'assign_booking') {
    $product_id = intval($_POST['product_id'] ?? 0);
    $hospital_id = intval($_POST['hospital_id'] ?? 0);
    $start_date = $_POST['start_date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) { $start_date = date('Y-m-d'); }
    $start_date_esc = mysqli_real_escape_string($connect, $start_date);

    if ($product_id <= 0 || $hospital_id <= 0) {
        $error = '상품과 병원을 선택해주세요.';
    } else {
        // 3개월치 예산 확인: 지갑 잔액이 (월 단가 x 3) 이상이어야 배정/활성화 가능
        $mp_r = mysqli_query($connect, "SELECT monthly_price FROM gangnamunni_ad_slot_products WHERE id={$product_id}");
        $monthly_price = ($mp_r && ($mp_row = mysqli_fetch_assoc($mp_r))) ? (int)$mp_row['monthly_price'] : 0;
        $required_3m = $monthly_price * 3;
        $wb_r = mysqli_query($connect, "SELECT balance FROM gangnamunni_cpv_wallets WHERE hospital_id={$hospital_id}");
        $wallet_balance = ($wb_r && ($wb_row = mysqli_fetch_assoc($wb_r))) ? (float)$wb_row['balance'] : 0.0;

        if ($wallet_balance < $required_3m) {
            $error = '해당 병원의 지갑 잔액이 3개월치 광고비에 미달하여 배정할 수 없습니다. '
                   . '(필요: ' . number_format($required_3m) . '원 / 현재 잔액: ' . number_format($wallet_balance) . '원)';
        } else {
            $prev_end = date('Y-m-d', strtotime($start_date_esc . ' -1 day'));
            mysqli_query($connect, "UPDATE gangnamunni_ad_slot_bookings SET status='replaced', end_date='{$prev_end}' WHERE product_id={$product_id} AND status='active'");

            // 병원명으로 광고주 매핑 (있으면 연결, 없으면 NULL)
            $advertiser_id_sql = 'NULL';
            $adv_r = mysqli_query($connect, "
                SELECT a.id FROM gangnamunni_advertisers a
                INNER JOIN gangnamunni_hospitals h ON h.name = a.hospital_name
                WHERE h.id = {$hospital_id} LIMIT 1
            ");
            if ($adv_r && $adv_row = mysqli_fetch_assoc($adv_r)) { $advertiser_id_sql = (int)$adv_row['id']; }

            $admin_name_esc = mysqli_real_escape_string($connect, $admin_name);
            mysqli_query($connect, "
                INSERT INTO gangnamunni_ad_slot_bookings (product_id, hospital_id, advertiser_id, start_date, status, assigned_by)
                VALUES ({$product_id}, {$hospital_id}, {$advertiser_id_sql}, '{$start_date_esc}', 'active', '{$admin_name_esc}')
            ");
            $message = '병원이 슬롯에 배정되었습니다.';
        }
    }
}

// 배정 조기 종료
if (isset($_GET['end_booking'])) {
    $bid = intval($_GET['end_booking']);
    mysqli_query($connect, "UPDATE gangnamunni_ad_slot_bookings SET status='ended', end_date=CURDATE() WHERE id={$bid} AND status='active'");
    header('Location: admin_ad_slots.php?msg=' . urlencode('배정이 종료되었습니다.'));
    exit;
}

// ======================================================================
// 자동 정지: 지갑 잔액이 하루 과금액보다 적은 활성 배정을 즉시 종료
// (cron_ad_slot_daily.php와 동일 기준 — 관리자 페이지 접속 시 실시간 반영)
// ======================================================================
$today = date('Y-m-d');
$days_in_month_now = max(1, (int) date('t'));
$autostop_r = mysqli_query($connect, "
    SELECT b.id AS booking_id, b.hospital_id, p.monthly_price, h.name AS hospital_name,
           COALESCE(w.balance, 0) AS balance
    FROM gangnamunni_ad_slot_bookings b
    INNER JOIN gangnamunni_ad_slot_products p ON b.product_id = p.id
    INNER JOIN gangnamunni_hospitals h ON h.id = b.hospital_id
    LEFT JOIN gangnamunni_cpv_wallets w ON w.hospital_id = b.hospital_id
    WHERE b.status = 'active'
      AND b.start_date <= '{$today}'
      AND (b.end_date IS NULL OR b.end_date >= '{$today}')
");
$autostopped_count = 0;
if ($autostop_r) {
    while ($arow = mysqli_fetch_assoc($autostop_r)) {
        $daily = round(((int)$arow['monthly_price']) / $days_in_month_now, 2);
        if ((float)$arow['balance'] < $daily) {
            $bid = (int)$arow['booking_id'];
            mysqli_query($connect, "UPDATE gangnamunni_ad_slot_bookings SET status='ended', end_date='{$today}' WHERE id={$bid} AND status='active'");

            // 관리자 알림 (cron과 동일 형식)
            $hn = $arow['hospital_name'] ?: ('병원 #' . (int)$arow['hospital_id']);
            $nt = mysqli_real_escape_string($connect, "[광고 슬롯] {$hn} 배정 자동 종료");
            $nm = mysqli_real_escape_string($connect, "{$hn}의 광고 슬롯 배정이 지갑 잔액 부족(" . number_format((float)$arow['balance']) . "원)으로 자동 종료되었습니다. 필요 금액: " . number_format($daily) . "원/일.");
            mysqli_query($connect, "
                INSERT INTO gangnamunni_notifications (admin_id, type, title, message, link, icon, is_read, created_at)
                VALUES (NULL, 'ad_slot', '{$nt}', '{$nm}', 'admin_ad_slots.php', 'bi-exclamation-triangle', 0, NOW())
            ");
            $autostopped_count++;
        }
    }
}
if ($autostopped_count > 0) {
    $message = ($message ? $message . ' / ' : '') . $autostopped_count . '건의 배정이 지갑 잔액 부족으로 자동 종료되었습니다.';
}

// ======================================================================
// 데이터 조회
// ======================================================================

$products = [];
$pr = mysqli_query($connect, "
    SELECT p.*, tm.name AS treatment_name, tc.name AS category_name
    FROM gangnamunni_ad_slot_products p
    LEFT JOIN gangnamunni_treatment_master tm ON p.treatment_id = tm.id
    LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
    ORDER BY p.keyword_type ASC, COALESCE(tm.name, p.region_name) ASC, p.rank_position ASC
");
if ($pr) { while ($row = mysqli_fetch_assoc($pr)) $products[] = $row; }

$active_bookings = []; // product_id => booking+hospital+wallet
if (!empty($products)) {
    $pid_list = implode(',', array_column($products, 'id'));
    $bk_r = mysqli_query($connect, "
        SELECT b.*, h.name AS hospital_name, w.balance AS wallet_balance
        FROM gangnamunni_ad_slot_bookings b
        INNER JOIN gangnamunni_hospitals h ON b.hospital_id = h.id
        LEFT JOIN gangnamunni_cpv_wallets w ON w.hospital_id = b.hospital_id
        WHERE b.product_id IN ({$pid_list}) AND b.status = 'active'
    ");
    if ($bk_r) { while ($brow = mysqli_fetch_assoc($bk_r)) { $active_bookings[(int)$brow['product_id']] = $brow; } }
}

// 이번 달 booking별 누적 과금액 (cron_ad_slot_daily.php가 기록)
$booking_spend = []; // booking_id => ['total' => .., 'last_date' => ..]
if (!empty($active_bookings)) {
    $bid_list = implode(',', array_column($active_bookings, 'id'));
    $sp_r = mysqli_query($connect, "
        SELECT booking_id, SUM(amount) AS total, MAX(charge_date) AS last_date
        FROM gangnamunni_ad_slot_charges
        WHERE booking_id IN ({$bid_list}) AND charge_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
        GROUP BY booking_id
    ");
    if ($sp_r) { while ($sprow = mysqli_fetch_assoc($sp_r)) { $booking_spend[(int)$sprow['booking_id']] = $sprow; } }
}

// 통계
$total_count = count($products);
$active_count = 0;
$booked_count = count($active_bookings);
$monthly_revenue_potential = 0;
foreach ($products as $p) {
    if ($p['is_active']) $active_count++;
    if (isset($active_bookings[$p['id']])) $monthly_revenue_potential += (int)$p['monthly_price'];
}

// 시술 선택용 데이터 (103개 - 클라이언트에서 검색)
$treatment_options = [];
$tr = mysqli_query($connect, "
    SELECT tm.id, tm.name, tc.name AS category_name
    FROM gangnamunni_treatment_master tm
    LEFT JOIN gangnamunni_treatment_categories tc ON tm.category_id = tc.id
    WHERE tm.is_active = 1
    ORDER BY tc.sort_order ASC, tm.name ASC
");
if ($tr) { while ($trow = mysqli_fetch_assoc($tr)) $treatment_options[] = $trow; }

// 병원 선택용 데이터 (배정 모달에서 검색) — 지갑 잔액 포함(3개월치 예산 판단용)
$hospital_options = [];
$hr = mysqli_query($connect, "
    SELECT h.id, h.name, h.address, COALESCE(w.balance, 0) AS wallet_balance
    FROM gangnamunni_hospitals h
    LEFT JOIN gangnamunni_cpv_wallets w ON w.hospital_id = h.id
    WHERE h.is_active = 1 AND COALESCE(h.is_demo, 0) = 0
    ORDER BY h.name
");
if ($hr) { while ($hrow = mysqli_fetch_assoc($hr)) $hospital_options[] = $hrow; }

// 병원별 시술 매핑 (배정 모달 시술 슬롯 매칭용) — public hospitals.php와 동일 기준(gangnamunni_hospital_treatments)
$hospital_treatment_map = []; // hospital_id => [treatment_id, ...]
$htr = mysqli_query($connect, "
    SELECT ht.hospital_id, ht.treatment_id
    FROM gangnamunni_hospital_treatments ht
    INNER JOIN gangnamunni_hospitals h ON h.id = ht.hospital_id
    WHERE h.is_active = 1 AND COALESCE(h.is_demo, 0) = 0
");
if ($htr) { while ($htrow = mysqli_fetch_assoc($htr)) { $hospital_treatment_map[(int)$htrow['hospital_id']][] = (int)$htrow['treatment_id']; } }

// 지역 선택용 데이터 (includes/site_regions.php - 사이트 헤더 지역 필터와 단일 소스 공유)
require __DIR__ . '/includes/site_regions.php';
$region_flat_list = [];
foreach ($region_groups as $_rg) { foreach ($_rg as $_r) { $region_flat_list[] = $_r; } }

include __DIR__ . '/includes/admin_header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show py-2">
    <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show py-2">
    <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- 상단 통계 카드 -->
<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3 text-center">
                <div class="text-muted small">총 슬롯 상품</div>
                <div class="fs-4 fw-bold text-primary"><?= $total_count ?>개</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3 text-center">
                <div class="text-muted small">활성 상품</div>
                <div class="fs-4 fw-bold text-success"><?= $active_count ?>개</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3 text-center">
                <div class="text-muted small">현재 배정된 슬롯</div>
                <div class="fs-4 fw-bold text-info"><?= $booked_count ?>건</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3 text-center">
                <div class="text-muted small">월 매출 잠재액</div>
                <div class="fs-5 fw-bold text-warning"><?= number_format($monthly_revenue_potential) ?>원</div>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info py-2 small mb-3">
    <i class="bi bi-info-circle me-1"></i>
    시술명/지역명 검색 시 순위(1~5위)를 구매한 병원이 목록 최상단에 고정 노출됩니다. 지갑에서 <strong>월 단가의 일할 금액이 매일 자정 자동 차감</strong>됩니다. 배정(활성화)하려면 병원 지갑에 <strong>3개월치 광고비 이상</strong>이 있어야 하며, 잔액이 하루 과금액 미만으로 떨어지면 배정이 <strong>자동 종료</strong>됩니다.
</div>

<!-- 도구 모음 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-trophy me-1"></i>슬롯 상품 목록</h5>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg me-1"></i>슬롯 상품 추가
    </button>
</div>

<div class="table-responsive">
    <table class="table table-hover table-bordered mb-0 align-middle">
        <thead class="table-light">
            <tr>
                <th style="width:80px;">유형</th>
                <th>키워드</th>
                <th style="width:60px;">순위</th>
                <th style="width:120px;" class="text-end">월 단가</th>
                <th style="width:60px;">상태</th>
                <th style="width:280px;">현재 배정</th>
                <th style="width:160px;">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox me-1"></i>등록된 슬롯 상품이 없습니다.</td></tr>
            <?php else: ?>
            <?php foreach ($products as $p):
                $booking = $active_bookings[$p['id']] ?? null;
                $keyword_label = $p['keyword_type'] === 'treatment'
                    ? htmlspecialchars($p['treatment_name'] ?? '(삭제된 시술)')
                    : '지역: ' . htmlspecialchars($p['region_name'] ?? '');
            ?>
            <tr class="<?= !$p['is_active'] ? 'table-secondary opacity-75' : '' ?>">
                <td>
                    <?php if ($p['keyword_type'] === 'treatment'): ?>
                    <span class="badge bg-primary">시술</span>
                    <?php else: ?>
                    <span class="badge bg-purple text-white" style="background:#7c3aed;">지역</span>
                    <?php endif; ?>
                </td>
                <td>
                    <strong><?= $keyword_label ?></strong>
                    <?php if ($p['keyword_type'] === 'treatment' && !empty($p['category_name'])): ?>
                    <br><small class="text-muted"><?= htmlspecialchars($p['category_name']) ?></small>
                    <?php endif; ?>
                </td>
                <td class="text-center"><span class="badge bg-dark"><?= (int)$p['rank_position'] ?>위</span></td>
                <td class="text-end fw-bold"><?= number_format($p['monthly_price']) ?>원</td>
                <td class="text-center">
                    <?= $p['is_active']
                        ? '<span class="badge bg-success">활성</span>'
                        : '<span class="badge bg-danger">비활성</span>' ?>
                </td>
                <td>
                    <?php if ($booking): ?>
                    <div class="small">
                        <strong><?= htmlspecialchars($booking['hospital_name']) ?></strong>
                        <span class="text-muted">(<?= htmlspecialchars($booking['start_date']) ?> ~ <?= $booking['end_date'] ? htmlspecialchars($booking['end_date']) : '진행중' ?>)</span>
                        <br>
                        <span class="text-muted">지갑 잔액: </span>
                        <span class="<?= (float)($booking['wallet_balance'] ?? 0) <= 0 ? 'text-danger fw-bold' : 'text-success' ?>">
                            <?= number_format((float)($booking['wallet_balance'] ?? 0)) ?>원
                        </span>
                        <a href="?end_booking=<?= $booking['id'] ?>" class="btn btn-outline-danger btn-sm ms-1" onclick="return confirm('배정을 종료하시겠습니까?')" title="배정 종료"><i class="bi bi-x-circle"></i></a>
                        <?php $_spend = $booking_spend[$booking['id']] ?? null; ?>
                        <br>
                        <span class="text-muted">이번 달 과금: </span>
                        <?php if ($_spend): ?>
                        <span class="text-primary"><?= number_format((float)$_spend['total']) ?>원</span>
                        <span class="text-muted">(마지막: <?= htmlspecialchars($_spend['last_date']) ?>)</span>
                        <?php else: ?>
                        <span class="text-muted">아직 과금 없음</span>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <span class="text-muted small">배정된 병원 없음</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-success" title="배정/교체"
                            onclick="openAssignModal(<?= $p['id'] ?>, '<?= addslashes($keyword_label) ?> <?= (int)$p['rank_position'] ?>위')">
                            <i class="bi bi-person-plus"></i>
                        </button>
                        <button type="button" class="btn btn-outline-primary" title="수정"
                            onclick="openEditModal(<?= $p['id'] ?>, <?= (int)$p['rank_position'] ?>, <?= (int)$p['monthly_price'] ?>, <?= (int)$p['is_active'] ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <a href="?toggle_product=<?= $p['id'] ?>" class="btn btn-outline-<?= $p['is_active'] ? 'warning' : 'success' ?>" title="<?= $p['is_active'] ? '비활성화' : '활성화' ?>">
                            <i class="bi bi-<?= $p['is_active'] ? 'pause' : 'play' ?>-fill"></i>
                        </a>
                        <a href="?delete_product=<?= $p['id'] ?>" class="btn btn-outline-danger" title="삭제" onclick="return confirm('삭제하면 배정 내역도 함께 삭제됩니다. 계속하시겠습니까?')">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 슬롯 상품 추가 모달 -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-primary bg-opacity-10">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-1"></i>슬롯 상품 추가</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold">키워드 유형</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="keyword_type" id="kt_treatment" value="treatment" checked onchange="toggleKeywordType()">
                                <label class="btn btn-outline-primary" for="kt_treatment">시술 키워드</label>
                                <input type="radio" class="btn-check" name="keyword_type" id="kt_region" value="region" onchange="toggleKeywordType()">
                                <label class="btn btn-outline-primary" for="kt_region">지역 키워드</label>
                            </div>
                        </div>
                        <div class="col-12" id="treatmentPicker">
                            <label class="form-label fw-bold">시술 선택 <span class="text-danger">*</span></label>
                            <select name="treatment_id" class="form-select">
                                <option value="">-- 시술을 선택하세요 --</option>
                                <?php
                                $_cur_cat = null;
                                foreach ($treatment_options as $_to):
                                    if ($_to['category_name'] !== $_cur_cat):
                                        if ($_cur_cat !== null) echo '</optgroup>';
                                        $_cur_cat = $_to['category_name'];
                                        echo '<optgroup label="' . htmlspecialchars($_cur_cat ?: '기타') . '">';
                                    endif;
                                ?>
                                <option value="<?= (int)$_to['id'] ?>"><?= htmlspecialchars($_to['name']) ?></option>
                                <?php endforeach; if ($_cur_cat !== null) echo '</optgroup>'; ?>
                            </select>
                            <div class="form-text">시술명을 입력해 빠르게 찾을 수도 있습니다 (브라우저 드롭다운 검색).</div>
                        </div>
                        <div class="col-12" id="regionPicker" style="display:none;">
                            <label class="form-label fw-bold">지역 선택 <span class="text-danger">*</span></label>
                            <select name="region_name" class="form-select">
                                <option value="">-- 지역을 선택하세요 --</option>
                                <?php foreach ($region_flat_list as $_r): ?>
                                <option value="<?= htmlspecialchars($_r) ?>"><?= htmlspecialchars($_r) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">이 목록은 사이트 헤더의 지역 필터(includes/site_regions.php)와 동일합니다. 새 지역은 그 파일 한 곳에서 추가하면 여기에도 자동 반영됩니다.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">순위</label>
                            <select name="rank_position" class="form-select">
                                <option value="1">1위</option>
                                <option value="2">2위</option>
                                <option value="3">3위</option>
                                <option value="4">4위</option>
                                <option value="5">5위</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">월 단가 (원)</label>
                            <input type="number" name="monthly_price" class="form-control" value="1000000" min="0" step="10000" required>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-4">
                                <input type="checkbox" name="is_active" class="form-check-input" id="add_is_active" checked>
                                <label class="form-check-label" for="add_is_active">즉시 활성화</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" name="add_product" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>등록</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 슬롯 상품 수정 모달 -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update_product">
                <input type="hidden" name="id" id="edit_id" value="">
                <div class="modal-header bg-warning bg-opacity-10">
                    <h5 class="modal-title"><i class="bi bi-pencil me-1"></i>슬롯 상품 수정</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">순위</label>
                            <select name="rank_position" id="edit_rank_position" class="form-select">
                                <option value="1">1위</option>
                                <option value="2">2위</option>
                                <option value="3">3위</option>
                                <option value="4">4위</option>
                                <option value="5">5위</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">월 단가 (원)</label>
                            <input type="number" name="monthly_price" id="edit_monthly_price" class="form-control" min="0" step="10000">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" id="edit_is_active">
                                <label class="form-check-label" for="edit_is_active">활성화</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-pencil me-1"></i>수정</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 병원 배정 모달 -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="assign_booking">
                <input type="hidden" name="product_id" id="assign_product_id" value="">
                <div class="modal-header bg-success bg-opacity-10">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-1"></i>병원 배정: <span id="assignProductLabel"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning py-2 small">
                        <i class="bi bi-exclamation-triangle me-1"></i>이미 배정된 병원이 있으면 자동으로 종료되고 교체됩니다.
                    </div>
                    <div id="assignReqInfo" class="small text-primary fw-bold mb-2"></div>
                    <label class="form-label fw-bold">병원 선택 <span class="text-danger">*</span></label>
                    <select name="hospital_id" id="assign_hospital_id" class="form-select">
                        <option value="">-- 병원을 선택하세요 --</option>
                        <?php foreach ($hospital_options as $_ho): ?>
                        <option value="<?= (int)$_ho['id'] ?>"><?= htmlspecialchars($_ho['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">이 슬롯 키워드(시술/지역)에 해당하는 병원이 목록 상단에 먼저 표시됩니다. 매칭되지 않는 병원도 아래에서 선택할 수 있습니다(강제 배정 가능).</div>
                    <div class="mt-3">
                        <label class="form-label fw-bold">시작일</label>
                        <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1"></i>배정</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// 배정 모달 매칭용 데이터 (클라이언트에서 그룹핑)
$assign_hospitals_json = json_encode(array_map(function ($h) use ($hospital_treatment_map) {
    return [
        'id' => (int)$h['id'],
        'name' => $h['name'],
        'address' => $h['address'] ?? '',
        'balance' => (float)($h['wallet_balance'] ?? 0),
        'tids' => $hospital_treatment_map[(int)$h['id']] ?? [],
    ];
}, $hospital_options), JSON_UNESCAPED_UNICODE);

$assign_products_map = [];
foreach ($products as $p) {
    $assign_products_map[(int)$p['id']] = [
        'type' => $p['keyword_type'],
        'tid' => (int)($p['treatment_id'] ?? 0),
        'region' => $p['region_name'] ?? '',
        'mp' => (int)($p['monthly_price'] ?? 0),
    ];
}
$assign_products_json = json_encode($assign_products_map, JSON_UNESCAPED_UNICODE);

$admin_extra_js = '
var ASSIGN_HOSPITALS = ' . $assign_hospitals_json . ';
var ASSIGN_PRODUCTS = ' . $assign_products_json . ';

function toggleKeywordType() {
    var isRegion = document.getElementById("kt_region").checked;
    document.getElementById("treatmentPicker").style.display = isRegion ? "none" : "block";
    document.getElementById("regionPicker").style.display = isRegion ? "block" : "none";
}

function fmtWon(n) {
    return Math.round(n).toLocaleString("ko-KR");
}

function buildHospitalOptions(product) {
    var sel = document.getElementById("assign_hospital_id");
    sel.innerHTML = "";
    var ph = document.createElement("option");
    ph.value = "";
    ph.textContent = "-- 병원을 선택하세요 --";
    sel.appendChild(ph);

    // 3개월치 필요 예산 (월 단가 x 3)
    var required = (product && product.mp > 0) ? product.mp * 3 : 0;
    var reqInfo = document.getElementById("assignReqInfo");
    if (reqInfo) {
        reqInfo.textContent = required > 0
            ? "이 슬롯 배정 필요 예산(3개월치): " + fmtWon(required) + "원 — 잔액이 부족한 병원은 선택할 수 없습니다."
            : "";
    }

    var matched = [], others = [];
    ASSIGN_HOSPITALS.forEach(function (h) {
        var isMatch = false;
        if (product) {
            if (product.type === "treatment") {
                isMatch = product.tid > 0 && h.tids.indexOf(product.tid) !== -1;
            } else if (product.type === "region") {
                isMatch = !!product.region && !!h.address && h.address.indexOf(product.region) !== -1;
            }
        }
        (isMatch ? matched : others).push(h);
    });

    function addGroup(label, arr) {
        if (!arr.length) return;
        var g = document.createElement("optgroup");
        g.label = label;
        arr.forEach(function (h) {
            var affordable = required <= 0 || h.balance >= required;
            var o = document.createElement("option");
            o.value = h.id;
            o.textContent = h.name + " — 잔액 " + fmtWon(h.balance) + "원" + (affordable ? "" : " (3개월치 부족)");
            if (!affordable) o.disabled = true;
            g.appendChild(o);
        });
        sel.appendChild(g);
    }

    addGroup("✅ 이 키워드 해당 병원 (" + matched.length + ")", matched);
    addGroup("기타 병원 (매칭 안 됨, " + others.length + ")", others);
}

function openAssignModal(productId, label) {
    document.getElementById("assign_product_id").value = productId;
    document.getElementById("assignProductLabel").textContent = label;
    buildHospitalOptions(ASSIGN_PRODUCTS[productId] || null);
    document.getElementById("assign_hospital_id").value = "";
    new bootstrap.Modal(document.getElementById("assignModal")).show();
}

function openEditModal(id, rank, price, isActive) {
    document.getElementById("edit_id").value = id;
    document.getElementById("edit_rank_position").value = rank;
    document.getElementById("edit_monthly_price").value = price;
    document.getElementById("edit_is_active").checked = !!isActive;
    new bootstrap.Modal(document.getElementById("editModal")).show();
}
';

include __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
