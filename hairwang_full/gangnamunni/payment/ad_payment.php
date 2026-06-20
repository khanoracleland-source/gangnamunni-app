<?php
session_start();
require_once __DIR__ . '/../../dbconfig.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('MySQL');
mysqli_set_charset($connect, 'utf8mb4');
$ad_id = intval($_GET['ad_id'] ?? 0);
$pricing_id = intval($_GET['pricing_id'] ?? $_GET['product_id'] ?? 0);
$message = '';
$error = '';

// 광고 정보
$ad = null;
if ($ad_id > 0) {
    $r = mysqli_query($connect, "
        SELECT a.*, adv.hospital_name as advertiser_name,
               p.code as current_pricing_code, p.name as current_pricing_name
        FROM gangnamunni_ads a
        LEFT JOIN gangnamunni_advertisers adv ON a.advertiser_id = adv.id
        LEFT JOIN gangnamunni_ad_pricing p ON a.pricing_id = p.id
        WHERE a.id = {$ad_id}
    ");
    $ad = mysqli_fetch_assoc($r);
}

if (!$ad) {
    echo '<script>alert("광고를 찾을 수 없습니다."); history.back();</script>';
    exit;
}

// 활성 단가 목록
$pricing_list = [];
$pr = mysqli_query($connect, "SELECT * FROM gangnamunni_ad_pricing WHERE is_active = 1 ORDER BY display_order ASC");
while ($row = mysqli_fetch_assoc($pr)) {
    $pricing_list[] = $row;
}

// 결제 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $selected_pricing = intval($_POST['pricing_id']);
    $payment_method = mysqli_real_escape_string($connect, $_POST['payment_method'] ?? 'card');

    // 단가 정보 조회
    $pr = mysqli_query($connect, "SELECT * FROM gangnamunni_ad_pricing WHERE id = {$selected_pricing} AND is_active = 1");
    $pricing = mysqli_fetch_assoc($pr);

    if (!$pricing) {
        $error = "선택한 단가를 찾을 수 없습니다.";
    } else {
        // 결제 기록
        $amount = $pricing['total_price'];
        $supply = $pricing['supply_price'];
        $vat = $pricing['vat_amount'];
        $pricing_code = mysqli_real_escape_string($connect, $pricing['code']);
        $duration = $pricing['duration_days'];

        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+{$duration} days"));

        // 결제 레코드 삽입
        $merchant_uid = 'AD_' . date('YmdHis') . '_' . $ad_id . '_' . rand(1000,9999);
        $product_id_val = $pricing['code'];
        $product_name_val = mysqli_real_escape_string($connect, $pricing['display_name'] ?: $pricing['name']);
        $buyer_name_val = mysqli_real_escape_string($connect, $ad['advertiser_name'] ?: '관리자');

        $pay_sql = "INSERT INTO gangnamunni_ad_payments
                    (merchant_uid, ad_id, pricing_id, pricing_code, product_id, product_name,
                     amount, supply_price, vat_amount, paid_amount,
                     buyer_name, payment_method, status, paid_at)
                    VALUES ('{$merchant_uid}', {$ad_id}, {$selected_pricing}, '{$pricing_code}',
                            '{$product_id_val}', '{$product_name_val}',
                            {$amount}, {$supply}, {$vat}, {$amount},
                            '{$buyer_name_val}', '{$payment_method}', 'paid', NOW())";
        if (!mysqli_query($connect, $pay_sql)) {
            $error = '결제 기록 실패: ' . mysqli_error($connect);
        }

        // 광고 업데이트: 단가 연결 + 기간 설정 + 활성화
        $upd_sql = "UPDATE gangnamunni_ads SET
                    pricing_id = {$selected_pricing},
                    ad_type = '{$pricing['ad_type']}',
                    start_date = '{$start_date}',
                    end_date = '{$end_date}',
                    is_active = 1
                    WHERE id = {$ad_id}";
        mysqli_query($connect, $upd_sql);

        // 광고주 매출 업데이트
        if ($ad['advertiser_id']) {
            mysqli_query($connect, "UPDATE gangnamunni_advertisers SET total_spent = total_spent + {$amount} WHERE id = {$ad['advertiser_id']}");
        }

        $message = "결제 완료! {$pricing['display_name']} ({$pricing['code']}) - " . number_format($amount) . "원";
    }
}

// gangnamunni_ad_payments에 필요한 컬럼 추가 (없으면)
mysqli_query($connect, "ALTER TABLE gangnamunni_ad_payments ADD COLUMN IF NOT EXISTS pricing_id INT DEFAULT NULL AFTER ad_id");
mysqli_query($connect, "ALTER TABLE gangnamunni_ad_payments ADD COLUMN IF NOT EXISTS pricing_code VARCHAR(20) DEFAULT NULL AFTER pricing_id");
mysqli_query($connect, "ALTER TABLE gangnamunni_ad_payments ADD COLUMN IF NOT EXISTS supply_price INT DEFAULT 0 AFTER amount");
mysqli_query($connect, "ALTER TABLE gangnamunni_ad_payments ADD COLUMN IF NOT EXISTS vat_amount INT DEFAULT 0 AFTER supply_price");
mysqli_query($connect, "ALTER TABLE gangnamunni_ad_payments ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT 'card' AFTER vat_amount");


// 관리자 공통 레이아웃 사용
$admin_page_title = '광고 결제';
$admin_current = 'ads';
$admin_extra_css = '';

// 경로 조정 (payment/ 하위이므로)
$_admin_base = __DIR__ . '/..';
require_once $_admin_base . '/../dbconfig.php';

include $_admin_base . '/includes/admin_header.php';
?>

<style>
/* 결제 페이지 전용 스타일 */
.pricing-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; }
.pricing-card {
    cursor: pointer;
    transition: all 0.25s ease;
    border: 2px solid var(--bs-border-color);
    border-radius: 12px;
    overflow: hidden;
    position: relative;
}
.pricing-card:hover {
    border-color: var(--bs-primary);
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(var(--bs-primary-rgb), 0.15);
}
.pricing-card.selected {
    border-color: var(--bs-primary);
    box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb), 0.25);
}
[data-bs-theme="light"] .pricing-card.selected { background: #f0f6ff; }
[data-bs-theme="dark"] .pricing-card.selected { background: rgba(13,110,253,0.1); }

.pricing-card .card-body { padding: 20px; }
.pricing-badge { position: absolute; top: 12px; right: 12px; }
.pricing-code {
    font-family: 'SF Mono', 'Consolas', monospace;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 4px;
    background: var(--bs-dark);
    color: #fff;
    letter-spacing: 0.5px;
}
.pricing-name { font-size: 15px; font-weight: 700; margin-top: 4px; }
.pricing-desc { font-size: 12px; color: var(--bs-secondary-color); line-height: 1.4; min-height: 34px; }
.pricing-price {
    font-size: 28px;
    font-weight: 800;
    background: linear-gradient(135deg, var(--bs-primary), #6610f2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1.2;
}
.pricing-duration { font-size: 13px; color: var(--bs-secondary-color); }
.pricing-tax {
    font-size: 11px;
    color: var(--bs-secondary-color);
    background: var(--bs-tertiary-bg);
    border-radius: 8px;
    padding: 8px 12px;
    margin-top: 12px;
}
.pricing-check {
    position: absolute; top: 12px; left: 12px;
    width: 24px; height: 24px;
    border-radius: 50%;
    background: var(--bs-primary);
    color: #fff;
    display: none;
    align-items: center; justify-content: center;
    font-size: 14px;
}
.pricing-card.selected .pricing-check { display: flex; }

/* 배너 유형별 그라데이션 상단 바 */
.pricing-card .type-bar { height: 4px; }
.type-banner .type-bar { background: linear-gradient(90deg, #0dcaf0, #0d6efd); }
.type-premium .type-bar { background: linear-gradient(90deg, #ffc107, #fd7e14); }
.type-vip .type-bar { background: linear-gradient(90deg, #dc3545, #e91e8a); }

/* 결제 요약 카드 */
.payment-summary {
    border-radius: 12px;
    border: 1px solid var(--bs-border-color);
    overflow: hidden;
}
.payment-summary .summary-header {
    padding: 16px 20px;
    background: var(--bs-tertiary-bg);
    border-bottom: 1px solid var(--bs-border-color);
}
.payment-summary .summary-body { padding: 20px; }
.payment-summary .total-row {
    font-size: 22px; font-weight: 800;
    color: var(--bs-primary);
    border-top: 2px solid var(--bs-border-color);
    padding-top: 12px; margin-top: 12px;
}

/* 결제수단 */
.payment-method-card {
    cursor: pointer;
    border: 2px solid var(--bs-border-color);
    border-radius: 10px;
    padding: 14px;
    text-align: center;
    transition: all 0.2s;
}
.payment-method-card:hover { border-color: var(--bs-primary); }
.payment-method-card.active { border-color: var(--bs-primary); background: rgba(var(--bs-primary-rgb), 0.05); }
.payment-method-card i { font-size: 24px; display: block; margin-bottom: 6px; }
.payment-method-card .method-label { font-size: 13px; font-weight: 600; }

/* 결제 버튼 */
.btn-pay {
    padding: 14px 32px;
    font-size: 18px;
    font-weight: 700;
    border-radius: 12px;
    letter-spacing: 0.5px;
}
</style>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($message) ?>
    <a href="../admin_ads.php?tab=ads" class="alert-link ms-3">광고 목록 보기 <i class="bi bi-arrow-right"></i></a>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- 광고 정보 카드 -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;overflow:hidden;">
    <div class="card-body p-4">
        <div class="d-flex align-items-center gap-3">
            <?php if ($ad['image_url']): ?>
            <img src="<?= htmlspecialchars($ad['image_url']) ?>" alt=""
                 style="width:80px;height:80px;object-fit:cover;border-radius:10px;border:1px solid var(--bs-border-color);"
                 onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2780%27 height=%2780%27 fill=%27%23dee2e6%27%3E%3Crect width=%2780%27 height=%2780%27/%3E%3Ctext x=%2740%27 y=%2744%27 text-anchor=%27middle%27 fill=%27%23adb5bd%27 font-size=%2720%27%3EAD%3C/text%3E%3C/svg%3E'">
            <?php else: ?>
            <div class="d-flex align-items-center justify-content-center rounded-3"
                 style="width:80px;height:80px;background:var(--bs-tertiary-bg);">
                <i class="bi bi-megaphone fs-3 text-muted"></i>
            </div>
            <?php endif; ?>
            <div class="flex-grow-1">
                <h5 class="mb-1"><?= htmlspecialchars($ad['title']) ?></h5>
                <?php if (!empty($ad['subtitle'])): ?>
                <div class="text-muted small"><?= htmlspecialchars($ad['subtitle']) ?></div>
                <?php endif; ?>
                <?php if ($ad['advertiser_name']): ?>
                <div class="mt-1">
                    <span class="badge bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-building me-1"></i><?= htmlspecialchars($ad['advertiser_name']) ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($ad['current_pricing_code']): ?>
            <div class="text-end">
                <div class="small text-muted">현재 단가</div>
                <span class="badge bg-secondary fs-6"><?= $ad['current_pricing_code'] ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 단가 선택 -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <h5 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>단가 상품 선택</h5>
    <a href="../admin_ad_pricing.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-gear me-1"></i>단가 관리
    </a>
</div>

<form method="POST" id="paymentForm">
    <input type="hidden" name="pricing_id" id="selectedPricingId" value="<?= $pricing_id ?>">

    <?php if (empty($pricing_list)): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>등록된 단가 상품이 없습니다.
        <a href="../admin_ad_pricing.php" class="alert-link">단가 등록하기</a>
    </div>
    <?php else: ?>

    <div class="pricing-grid mb-4">
        <?php foreach ($pricing_list as $p):
            $type_class = 'type-' . $p['ad_type'];
        ?>
        <div class="pricing-card <?= $type_class ?> <?= ($pricing_id == $p['id']) ? 'selected' : '' ?>"
             onclick="selectPricing(<?= $p['id'] ?>, this)" data-id="<?= $p['id'] ?>">
            <div class="type-bar"></div>
            <div class="pricing-check"><i class="bi bi-check-lg"></i></div>
            <span class="pricing-badge">
                <?php if ($p['ad_type'] === 'vip'): ?>
                <span class="badge bg-danger">VIP</span>
                <?php elseif ($p['ad_type'] === 'premium'): ?>
                <span class="badge bg-warning text-dark">프리미엄</span>
                <?php else: ?>
                <span class="badge bg-info">배너</span>
                <?php endif; ?>
            </span>
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="pricing-code"><?= $p['code'] ?></span>
                    <span class="pricing-name"><?= htmlspecialchars($p['display_name'] ?: $p['name']) ?></span>
                </div>
                <div class="pricing-desc"><?= htmlspecialchars($p['description'] ?? '') ?></div>
                <div class="d-flex align-items-end justify-content-between mt-3">
                    <div>
                        <div class="pricing-price"><?= number_format($p['total_price']) ?><small style="font-size:14px;-webkit-text-fill-color:var(--bs-secondary-color);">원</small></div>
                        <div class="pricing-duration"><i class="bi bi-calendar3 me-1"></i><?= $p['duration_days'] ?>일</div>
                    </div>
                    <div class="pricing-tax">
                        <div>공급가 <?= number_format($p['supply_price']) ?>원</div>
                        <div>부가세 <?= number_format($p['vat_amount']) ?>원</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- 결제 요약 + 수단 (숨김 -> 선택 시 표시) -->
    <div id="paymentSection" style="display:none;">
        <div class="row g-4">
            <!-- 결제 요약 -->
            <div class="col-lg-7">
                <div class="payment-summary">
                    <div class="summary-header">
                        <h6 class="mb-0"><i class="bi bi-receipt me-2"></i>결제 요약</h6>
                    </div>
                    <div class="summary-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">상품</span>
                            <span class="fw-bold" id="info_name">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">코드</span>
                            <span class="pricing-code" id="info_code">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">유형</span>
                            <span id="info_type">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">기간</span>
                            <span id="info_duration">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">공급가액</span>
                            <span id="info_supply">-</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">부가세 (10%)</span>
                            <span id="info_vat">-</span>
                        </div>
                        <div class="d-flex justify-content-between total-row">
                            <span>총 결제금액</span>
                            <span id="info_total">-</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 결제 수단 -->
            <div class="col-lg-5">
                <h6 class="mb-3"><i class="bi bi-credit-card me-2"></i>결제 수단</h6>
                <div class="row g-2 mb-4">
                    <div class="col-6">
                        <label class="payment-method-card active" onclick="selectMethod(this, 'card')">
                            <input type="radio" name="payment_method" value="card" checked class="d-none">
                            <i class="bi bi-credit-card-2-front text-primary"></i>
                            <div class="method-label">카드 결제</div>
                        </label>
                    </div>
                    <div class="col-6">
                        <label class="payment-method-card" onclick="selectMethod(this, 'transfer')">
                            <input type="radio" name="payment_method" value="transfer" class="d-none">
                            <i class="bi bi-bank text-success"></i>
                            <div class="method-label">계좌이체</div>
                        </label>
                    </div>
                    <div class="col-6">
                        <label class="payment-method-card" onclick="selectMethod(this, 'cash')">
                            <input type="radio" name="payment_method" value="cash" class="d-none">
                            <i class="bi bi-cash-stack text-warning"></i>
                            <div class="method-label">현금</div>
                        </label>
                    </div>
                    <div class="col-6">
                        <label class="payment-method-card" onclick="selectMethod(this, 'invoice')">
                            <input type="radio" name="payment_method" value="invoice" class="d-none">
                            <i class="bi bi-receipt-cutoff text-info"></i>
                            <div class="method-label">후불 청구</div>
                        </label>
                    </div>
                </div>

                <button type="submit" name="process_payment" class="btn btn-primary btn-pay w-100">
                    <i class="bi bi-lock-fill me-2"></i>결제하기
                </button>
                <div class="text-center mt-2">
                    <small class="text-muted"><i class="bi bi-shield-check me-1"></i>안전한 결제가 보장됩니다</small>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</form>

<?php
$page_js = '
var pricingData = ' . json_encode($pricing_list, JSON_UNESCAPED_UNICODE) . ';

function selectPricing(id, el) {
    document.getElementById("selectedPricingId").value = id;
    document.querySelectorAll(".pricing-card").forEach(function(c) { c.classList.remove("selected"); });
    el.classList.add("selected");

    var p = pricingData.find(function(x) { return x.id == id; });
    if (!p) return;

    document.getElementById("info_code").textContent = p.code;
    document.getElementById("info_name").textContent = p.display_name || p.name;
    var types = {banner:"배너", premium:"프리미엄", vip:"VIP"};
    document.getElementById("info_type").textContent = types[p.ad_type] || p.ad_type;
    document.getElementById("info_duration").textContent = p.duration_days + "일";
    document.getElementById("info_supply").textContent = parseInt(p.supply_price).toLocaleString() + "원";
    document.getElementById("info_vat").textContent = parseInt(p.vat_amount).toLocaleString() + "원";
    document.getElementById("info_total").textContent = parseInt(p.total_price).toLocaleString() + "원";

    document.getElementById("paymentSection").style.display = "";
    document.getElementById("paymentSection").scrollIntoView({behavior:"smooth", block:"center"});
}

function selectMethod(el, val) {
    document.querySelectorAll(".payment-method-card").forEach(function(c) { c.classList.remove("active"); });
    el.classList.add("active");
    el.querySelector("input").checked = true;
}

// 초기 선택
var initId = ' . $pricing_id . ';
if (initId > 0) {
    var el = document.querySelector("[data-id=\"" + initId + "\"]");
    if (el) selectPricing(initId, el);
}
';

include $_admin_base . '/includes/admin_footer.php';
mysqli_close($connect);
?>
