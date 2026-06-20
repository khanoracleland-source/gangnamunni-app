<?php
session_start();
$admin_page_title = '광고 단가 관리';
$admin_current = 'ad_pricing';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_permission('ads');

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('MySQL 연결 실패');
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
$message = '';
$error = '';

// ======================================================================
// POST 처리
// ======================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 단가 추가
    if (isset($_POST['add_pricing'])) {
        $code = mysqli_real_escape_string($connect, trim($_POST['code']));
        $name = mysqli_real_escape_string($connect, trim($_POST['name']));
        $display_name = mysqli_real_escape_string($connect, trim($_POST['display_name']));
        $description = mysqli_real_escape_string($connect, trim($_POST['description']));
        $ad_type = mysqli_real_escape_string($connect, $_POST['ad_type']);
        $duration_days = intval($_POST['duration_days']);
        $total_price = intval($_POST['total_price']);
        $is_taxable = isset($_POST['is_taxable']) ? 1 : 0;
        $vat_rate = $is_taxable ? floatval($_POST['vat_rate'] ?? 10) : 0;
        $tax_invoice_type = mysqli_real_escape_string($connect, $_POST['tax_invoice_type'] ?? 'electronic');
        $commission_rate = floatval($_POST['commission_rate'] ?? 0);
        $display_order = intval($_POST['display_order'] ?? 0);

        // 세금 자동 계산
        if ($is_taxable && $vat_rate > 0) {
            $supply_price = round($total_price / (1 + $vat_rate / 100));
            $vat_amount = $total_price - $supply_price;
        } else {
            $supply_price = $total_price;
            $vat_amount = 0;
        }

        // 중복 코드 체크
        $chk = mysqli_query($connect, "SELECT id FROM gangnamunni_ad_pricing WHERE code = '{$code}'");
        if (mysqli_num_rows($chk) > 0) {
            $error = "이미 존재하는 단가 코드입니다: {$code}";
        } else {
            $sql = "INSERT INTO gangnamunni_ad_pricing
                    (code, name, display_name, description, ad_type, duration_days,
                     supply_price, vat_rate, vat_amount, total_price,
                     is_taxable, tax_invoice_type, commission_rate, display_order)
                    VALUES ('{$code}', '{$name}', '{$display_name}', '{$description}',
                            '{$ad_type}', {$duration_days},
                            {$supply_price}, {$vat_rate}, {$vat_amount}, {$total_price},
                            {$is_taxable}, '{$tax_invoice_type}', {$commission_rate}, {$display_order})";
            if (mysqli_query($connect, $sql)) {
                $message = "단가 '{$code}' 등록 완료";
            } else {
                $error = "등록 실패: " . mysqli_error($connect);
            }
        }
    }

    // 단가 수정
    if (isset($_POST['edit_pricing'])) {
        $id = intval($_POST['id']);
        $code = mysqli_real_escape_string($connect, trim($_POST['code']));
        $name = mysqli_real_escape_string($connect, trim($_POST['name']));
        $display_name = mysqli_real_escape_string($connect, trim($_POST['display_name']));
        $description = mysqli_real_escape_string($connect, trim($_POST['description']));
        $ad_type = mysqli_real_escape_string($connect, $_POST['ad_type']);
        $duration_days = intval($_POST['duration_days']);
        $total_price = intval($_POST['total_price']);
        $is_taxable = isset($_POST['is_taxable']) ? 1 : 0;
        $vat_rate = $is_taxable ? floatval($_POST['vat_rate'] ?? 10) : 0;
        $tax_invoice_type = mysqli_real_escape_string($connect, $_POST['tax_invoice_type'] ?? 'electronic');
        $commission_rate = floatval($_POST['commission_rate'] ?? 0);
        $display_order = intval($_POST['display_order'] ?? 0);

        if ($is_taxable && $vat_rate > 0) {
            $supply_price = round($total_price / (1 + $vat_rate / 100));
            $vat_amount = $total_price - $supply_price;
        } else {
            $supply_price = $total_price;
            $vat_amount = 0;
        }

        // 코드 중복 체크 (자신 제외)
        $chk = mysqli_query($connect, "SELECT id FROM gangnamunni_ad_pricing WHERE code = '{$code}' AND id != {$id}");
        if (mysqli_num_rows($chk) > 0) {
            $error = "이미 존재하는 단가 코드입니다: {$code}";
        } else {
            $sql = "UPDATE gangnamunni_ad_pricing SET
                    code = '{$code}', name = '{$name}', display_name = '{$display_name}',
                    description = '{$description}', ad_type = '{$ad_type}',
                    duration_days = {$duration_days},
                    supply_price = {$supply_price}, vat_rate = {$vat_rate},
                    vat_amount = {$vat_amount}, total_price = {$total_price},
                    is_taxable = {$is_taxable}, tax_invoice_type = '{$tax_invoice_type}',
                    commission_rate = {$commission_rate}, display_order = {$display_order}
                    WHERE id = {$id}";
            if (mysqli_query($connect, $sql)) {
                $message = "단가 '{$code}' 수정 완료";
            } else {
                $error = "수정 실패: " . mysqli_error($connect);
            }
        }
    }

    // 삭제
    if (isset($_POST['delete_pricing'])) {
        $id = intval($_POST['id']);
        // 사용 중인 광고 확인
        $used = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_ads WHERE pricing_id = {$id}");
        $used_cnt = (int)mysqli_fetch_assoc($used)['cnt'];
        if ($used_cnt > 0) {
            $error = "이 단가를 사용 중인 광고가 {$used_cnt}건 있어 삭제할 수 없습니다.";
        } else {
            if (mysqli_query($connect, "DELETE FROM gangnamunni_ad_pricing WHERE id = {$id}")) {
                $message = "단가 삭제 완료";
            } else {
                $error = "삭제 실패: " . mysqli_error($connect);
            }
        }
    }
}

// GET: 토글 활성화
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    mysqli_query($connect, "UPDATE gangnamunni_ad_pricing SET is_active = NOT is_active WHERE id = {$id}");
    header('Location: admin_ad_pricing.php');
    exit;
}

// ======================================================================
// 데이터 조회
// ======================================================================
$pricing_list = [];
$result = mysqli_query($connect, "
    SELECT p.*,
           (SELECT COUNT(*) FROM gangnamunni_ads WHERE pricing_id = p.id) as ad_count
    FROM gangnamunni_ad_pricing p
    ORDER BY p.display_order ASC, p.id ASC
");
while ($row = mysqli_fetch_assoc($result)) {
    $pricing_list[] = $row;
}

// 수정 모드
$edit_item = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    foreach ($pricing_list as $p) {
        if ($p['id'] == $edit_id) { $edit_item = $p; break; }
    }
}

// 통계
$total_count = count($pricing_list);
$active_count = 0;
$total_revenue_potential = 0;
foreach ($pricing_list as $p) {
    if ($p['is_active']) $active_count++;
    $total_revenue_potential += $p['total_price'] * $p['ad_count'];
}

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
                <div class="text-muted small">총 단가 상품</div>
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
                <div class="text-muted small">사용 중 광고</div>
                <div class="fs-4 fw-bold text-info"><?= array_sum(array_column($pricing_list, 'ad_count')) ?>건</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-3 text-center">
                <div class="text-muted small">매출 잠재액</div>
                <div class="fs-5 fw-bold text-warning"><?= number_format($total_revenue_potential) ?>원</div>
            </div>
        </div>
    </div>
</div>

<!-- 도구 모음 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-currency-won me-1"></i>광고 단가 목록</h5>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg me-1"></i>단가 추가
    </button>
</div>

<!-- 단가 테이블 -->
<div class="table-responsive">
    <table class="table table-hover table-bordered mb-0">
        <thead class="table-light">
            <tr>
                <th style="width:70px;">코드</th>
                <th>상품명</th>
                <th>인식이름</th>
                <th style="width:80px;">유형</th>
                <th style="width:60px;">기간</th>
                <th style="width:120px;" class="text-end">공급가액</th>
                <th style="width:80px;" class="text-end">부가세</th>
                <th style="width:120px;" class="text-end fw-bold">총 금액</th>
                <th style="width:60px;">과세</th>
                <th style="width:60px;">광고수</th>
                <th style="width:60px;">상태</th>
                <th style="width:120px;">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pricing_list)): ?>
            <tr><td colspan="12" class="text-center text-muted py-4"><i class="bi bi-inbox me-1"></i>등록된 단가가 없습니다.</td></tr>
            <?php else: ?>
            <?php foreach ($pricing_list as $p): ?>
            <tr class="<?= !$p['is_active'] ? 'table-secondary opacity-75' : '' ?>">
                <td><span class="badge bg-dark font-monospace"><?= htmlspecialchars($p['code']) ?></span></td>
                <td>
                    <strong><?= htmlspecialchars($p['name']) ?></strong>
                    <?php if ($p['description']): ?>
                    <br><small class="text-muted"><?= mb_strimwidth(htmlspecialchars($p['description']), 0, 50, '...') ?></small>
                    <?php endif; ?>
                </td>
                <td><span class="text-primary"><?= htmlspecialchars($p['display_name'] ?: '-') ?></span></td>
                <td>
                    <?php if ($p['ad_type'] === 'vip'): ?>
                    <span class="badge bg-danger">VIP</span>
                    <?php elseif ($p['ad_type'] === 'premium'): ?>
                    <span class="badge bg-warning text-dark">프리미엄</span>
                    <?php else: ?>
                    <span class="badge bg-info">배너</span>
                    <?php endif; ?>
                </td>
                <td class="text-center"><?= $p['duration_days'] ?>일</td>
                <td class="text-end font-monospace small"><?= number_format($p['supply_price']) ?>원</td>
                <td class="text-end font-monospace small text-muted"><?= number_format($p['vat_amount']) ?>원</td>
                <td class="text-end fw-bold"><?= number_format($p['total_price']) ?>원</td>
                <td class="text-center">
                    <?php if ($p['is_taxable']): ?>
                    <span class="badge bg-outline-success border text-success">과세</span>
                    <?php else: ?>
                    <span class="badge bg-outline-secondary border text-secondary">면세</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if ($p['ad_count'] > 0): ?>
                    <span class="badge bg-primary"><?= $p['ad_count'] ?></span>
                    <?php else: ?>
                    <span class="text-muted">-</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?= $p['is_active']
                        ? '<span class="badge bg-success">활성</span>'
                        : '<span class="badge bg-danger">비활성</span>' ?>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="?edit=<?= $p['id'] ?>" class="btn btn-outline-primary" title="수정"><i class="bi bi-pencil"></i></a>
                        <a href="?toggle=<?= $p['id'] ?>" class="btn btn-outline-<?= $p['is_active'] ? 'warning' : 'success' ?>" title="<?= $p['is_active'] ? '비활성화' : '활성화' ?>">
                            <i class="bi bi-<?= $p['is_active'] ? 'pause' : 'play' ?>-fill"></i>
                        </a>
                        <form method="POST" class="d-inline" onsubmit="return confirm('정말 삭제하시겠습니까?')">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button type="submit" name="delete_pricing" class="btn btn-outline-danger" title="삭제"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 세금 안내 -->
<div class="card mt-3 border-info">
    <div class="card-body py-2">
        <div class="row small text-muted">
            <div class="col-md-4">
                <i class="bi bi-info-circle text-info me-1"></i>
                <strong>공급가액</strong> = 총 금액 &divide; 1.1 (부가세 포함가 기준)
            </div>
            <div class="col-md-4">
                <i class="bi bi-calculator text-info me-1"></i>
                <strong>부가세</strong> = 총 금액 - 공급가액 (10%)
            </div>
            <div class="col-md-4">
                <i class="bi bi-receipt text-info me-1"></i>
                <strong>세금계산서</strong>: 전자/종이/미발행 선택 가능
            </div>
        </div>
    </div>
</div>

<!-- 단가 추가 모달 -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-primary bg-opacity-10">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-1"></i>광고 단가 추가</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">단가 코드 <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control" required placeholder="예: a10, a20" pattern="[a-zA-Z0-9_]+" title="영문, 숫자, _ 만 사용">
                            <div class="form-text">고유 식별자 (영문+숫자)</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">광고 유형</label>
                            <select name="ad_type" class="form-select">
                                <option value="banner">배너</option>
                                <option value="premium">프리미엄</option>
                                <option value="vip">VIP</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">광고 기간 (일)</label>
                            <input type="number" name="duration_days" class="form-control" value="30" min="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">상품명 (실이름) <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="예: 기본 배너 30일">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">인식이름 (표시명)</label>
                            <input type="text" name="display_name" class="form-control" placeholder="예: A30 스탠다드">
                            <div class="form-text">결제 화면에서 보이는 이름</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">설명</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="상품 설명..."></textarea>
                        </div>
                        <div class="col-12"><hr class="my-1"></div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">총 결제금액 (원) <span class="text-danger">*</span></label>
                            <input type="number" name="total_price" id="add_total" class="form-control" required min="0" step="1000" oninput="calcTax('add')">
                            <div class="form-text">부가세 포함 총액</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">부가세율 (%)</label>
                            <input type="number" name="vat_rate" id="add_vat_rate" class="form-control" value="10" min="0" max="100" step="0.5" oninput="calcTax('add')">
                        </div>
                        <div class="col-md-5">
                            <div class="card bg-light border-0 h-100">
                                <div class="card-body py-2">
                                    <div class="small text-muted">자동 계산</div>
                                    <div>공급가액: <strong id="add_supply">0</strong>원</div>
                                    <div>부가세: <strong id="add_vat">0</strong>원</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-2">
                                <input type="checkbox" name="is_taxable" class="form-check-input" id="add_taxable" checked>
                                <label class="form-check-label" for="add_taxable">과세 상품</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">세금계산서</label>
                            <select name="tax_invoice_type" class="form-select form-select-sm">
                                <option value="electronic">전자 세금계산서</option>
                                <option value="paper">종이 세금계산서</option>
                                <option value="none">미발행</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">수수료율 (%)</label>
                            <input type="number" name="commission_rate" class="form-control form-control-sm" value="0" min="0" max="100" step="0.1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">표시 순서</label>
                            <input type="number" name="display_order" class="form-control form-control-sm" value="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" name="add_pricing" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>등록</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 단가 수정 모달 -->
<?php if ($edit_item): ?>
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="id" value="<?= $edit_item['id'] ?>">
                <div class="modal-header bg-warning bg-opacity-10">
                    <h5 class="modal-title"><i class="bi bi-pencil me-1"></i>단가 수정: <?= htmlspecialchars($edit_item['code']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">단가 코드 <span class="text-danger">*</span></label>
                            <input type="text" name="code" class="form-control" required value="<?= htmlspecialchars($edit_item['code']) ?>" pattern="[a-zA-Z0-9_]+">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">광고 유형</label>
                            <select name="ad_type" class="form-select">
                                <option value="banner" <?= $edit_item['ad_type']==='banner'?'selected':'' ?>>배너</option>
                                <option value="premium" <?= $edit_item['ad_type']==='premium'?'selected':'' ?>>프리미엄</option>
                                <option value="vip" <?= $edit_item['ad_type']==='vip'?'selected':'' ?>>VIP</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">광고 기간 (일)</label>
                            <input type="number" name="duration_days" class="form-control" value="<?= $edit_item['duration_days'] ?>" min="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">상품명 (실이름) <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($edit_item['name']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">인식이름 (표시명)</label>
                            <input type="text" name="display_name" class="form-control" value="<?= htmlspecialchars($edit_item['display_name'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">설명</label>
                            <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($edit_item['description'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12"><hr class="my-1"></div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">총 결제금액 (원) <span class="text-danger">*</span></label>
                            <input type="number" name="total_price" id="edit_total" class="form-control" required value="<?= $edit_item['total_price'] ?>" min="0" step="1000" oninput="calcTax('edit')">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">부가세율 (%)</label>
                            <input type="number" name="vat_rate" id="edit_vat_rate" class="form-control" value="<?= $edit_item['vat_rate'] ?>" min="0" max="100" step="0.5" oninput="calcTax('edit')">
                        </div>
                        <div class="col-md-5">
                            <div class="card bg-light border-0 h-100">
                                <div class="card-body py-2">
                                    <div class="small text-muted">자동 계산</div>
                                    <div>공급가액: <strong id="edit_supply"><?= number_format($edit_item['supply_price']) ?></strong>원</div>
                                    <div>부가세: <strong id="edit_vat"><?= number_format($edit_item['vat_amount']) ?></strong>원</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-2">
                                <input type="checkbox" name="is_taxable" class="form-check-input" id="edit_taxable" <?= $edit_item['is_taxable'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="edit_taxable">과세 상품</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">세금계산서</label>
                            <select name="tax_invoice_type" class="form-select form-select-sm">
                                <option value="electronic" <?= ($edit_item['tax_invoice_type']??'')==='electronic'?'selected':'' ?>>전자 세금계산서</option>
                                <option value="paper" <?= ($edit_item['tax_invoice_type']??'')==='paper'?'selected':'' ?>>종이 세금계산서</option>
                                <option value="none" <?= ($edit_item['tax_invoice_type']??'')==='none'?'selected':'' ?>>미발행</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">수수료율 (%)</label>
                            <input type="number" name="commission_rate" class="form-control form-control-sm" value="<?= $edit_item['commission_rate'] ?? 0 ?>" min="0" max="100" step="0.1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">표시 순서</label>
                            <input type="number" name="display_order" class="form-control form-control-sm" value="<?= $edit_item['display_order'] ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="admin_ad_pricing.php" class="btn btn-secondary">취소</a>
                    <button type="submit" name="edit_pricing" class="btn btn-warning"><i class="bi bi-pencil me-1"></i>수정</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>document.addEventListener('DOMContentLoaded', function(){ new bootstrap.Modal(document.getElementById('editModal')).show(); });</script>
<?php endif; ?>

<?php
$page_js = '
function calcTax(prefix) {
    var total = parseInt(document.getElementById(prefix + "_total").value) || 0;
    var rate = parseFloat(document.getElementById(prefix + "_vat_rate").value) || 0;
    var supply = Math.round(total / (1 + rate / 100));
    var vat = total - supply;
    document.getElementById(prefix + "_supply").textContent = supply.toLocaleString();
    document.getElementById(prefix + "_vat").textContent = vat.toLocaleString();
}
';

include __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>
