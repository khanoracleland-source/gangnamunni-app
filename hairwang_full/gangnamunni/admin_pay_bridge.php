<?php
session_start();
$admin_page_title = '결제하기';
$admin_current = 'test_payment';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();

$product_code = $_GET['product_code'] ?? '';
$ad_id = intval($_GET['ad_id'] ?? 0);
$hospital_id = intval($_GET['hospital_id'] ?? 0);

// 상품 목록
$items = [];
$ri = mysqli_query($connect, "SELECT it_id, it_name, it_price, ca_id, it_explan FROM g5_shop_item WHERE it_use = 1 ORDER BY ca_id, it_price ASC");
while ($ri && $row = mysqli_fetch_assoc($ri)) $items[] = $row;

// PG 설정
$pg = [];
$rp = mysqli_query($connect, "SELECT de_pg_service, de_inicis_mid, de_card_test, de_card_use, de_bank_use FROM g5_shop_default LIMIT 1");
if ($rp) $pg = mysqli_fetch_assoc($rp);

$message = '';
$msg_type = '';

// 결제 처리 (테스트 모드 - 실제 PG 호출 없이 DB 기록)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_payment'])) {
    $sel_code = mysqli_real_escape_string($connect, $_POST['selected_product'] ?? '');
    $pay_method = mysqli_real_escape_string($connect, $_POST['pay_method'] ?? 'card');

    // 상품 확인
    $pr = mysqli_query($connect, "SELECT * FROM g5_shop_item WHERE it_id = '{$sel_code}' AND it_use = 1");
    $product = mysqli_fetch_assoc($pr);

    if (!$product) {
        $message = '상품을 찾을 수 없습니다.';
        $msg_type = 'danger';
    } else {
        // 주문번호 생성
        $od_id = date('YmdHis') . '_' . mt_rand(1000, 9999);
        $admin = function_exists('get_current_admin') ? get_current_admin() : null;
        $mb_id = $admin ? $admin['user_id'] : 'admin';
        $od_name = $admin ? $admin['name'] : '관리자';

        // g5_shop_order에 INSERT
        $sql = "INSERT INTO g5_shop_order (od_id, mb_id, od_name, od_email, od_tel, od_hp,
                od_zip1, od_zip2, od_addr1, od_addr2, od_addr3, od_addr_jibeon,
                od_b_zip1, od_b_zip2, od_b_addr1, od_b_addr2, od_b_addr3, od_b_addr_jibeon, od_b_name, od_b_tel, od_b_hp,
                od_deposit_name, od_receipt_price, od_send_cost, od_receipt_point,
                od_cart_count, od_status, od_settle_case, od_test, od_memo, od_time)
                VALUES ('{$od_id}', '{$mb_id}', '{$od_name}', 'admin@seoulbeauty.app', '', '',
                '', '', '', '', '', '',
                '', '', '', '', '', '', '', '', '',
                '{$od_name}', {$product['it_price']}, 0, 0,
                1, '완료', '{$pay_method}', 1, '서울미녀 테스트 결제 - {$product['it_name']}', NOW())";

        if (mysqli_query($connect, $sql)) {
            // 장바구니 기록
            $ct_id = $od_id . '_01';
            mysqli_query($connect, "INSERT INTO g5_shop_cart (od_id, mb_id, it_id, it_name, ct_id, ct_qty, ct_price, ct_point, ct_status, ct_stock_use, ct_time)
                VALUES ('{$od_id}', '{$mb_id}', '{$sel_code}', '{$product['it_name']}', '{$ct_id}', 1, {$product['it_price']}, 0, '완료', 1, NOW())");

            // 광고 연동
            if ($ad_id > 0) {
                mysqli_query($connect, "UPDATE gangnamunni_ads SET status = 'active', updated_at = NOW() WHERE id = {$ad_id}");
                mysqli_query($connect, "INSERT INTO gangnamunni_ad_payments (ad_id, amount, status, payment_method, od_id, created_at)
                    VALUES ({$ad_id}, {$product['it_price']}, 'paid', '{$pay_method}', '{$od_id}', NOW())
                    ON DUPLICATE KEY UPDATE amount = {$product['it_price']}, status = 'paid', od_id = '{$od_id}'");
            }

            $message = "결제 완료! 주문번호: {$od_id} / {$product['it_name']} / " . number_format($product['it_price']) . "원";
            $msg_type = 'success';
        } else {
            $message = '주문 처리 실패: ' . mysqli_error($connect);
            $msg_type = 'danger';
        }
    }
}

require_once __DIR__ . '/includes/admin_header.php';
$ca_names = ['100'=>'광고 상품','200'=>'병원 등록','300'=>'구독 상품'];
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="bi bi-cart-check me-2"></i>결제하기</h4>
        <a href="../admin_payment.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>결제 관리로</a>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
        <i class="bi bi-<?= $msg_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-1"></i>
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- PG 상태 배너 -->
    <div class="alert alert-<?= ($pg['de_card_test'] ?? 1) ? 'warning' : 'success' ?> py-2 mb-4" style="font-size:13px;">
        <i class="bi bi-shield-check me-1"></i>
        PG: <strong><?= strtoupper($pg['de_pg_service'] ?? '-') ?></strong> |
        MID: <code><?= $pg['de_inicis_mid'] ?? '-' ?></code> |
        모드: <strong><?= ($pg['de_card_test'] ?? 1) ? '테스트' : '실결제' ?></strong>
    </div>

    <form method="post" id="payForm">
        <input type="hidden" name="do_payment" value="1">
        <?php if ($ad_id): ?><input type="hidden" name="ad_id" value="<?= $ad_id ?>"><?php endif; ?>
        <?php if ($hospital_id): ?><input type="hidden" name="hospital_id" value="<?= $hospital_id ?>"><?php endif; ?>
        <input type="hidden" name="selected_product" id="selectedProduct" value="<?= htmlspecialchars($product_code) ?>">

        <!-- 상품 선택 -->
        <h5 class="mb-3"><i class="bi bi-box-seam me-1"></i>상품 선택</h5>
        <div class="row g-3 mb-4">
            <?php
            $current_ca = '';
            foreach ($items as $it):
                if ($it['ca_id'] !== $current_ca):
                    $current_ca = $it['ca_id'];
                    if ($current_ca !== $items[0]['ca_id']): ?></div><div class="row g-3 mb-4"><?php endif; ?>
                    <div class="col-12"><h6 class="text-muted mb-0"><i class="bi bi-tag me-1"></i><?= $ca_names[$current_ca] ?? $current_ca ?></h6></div>
                <?php endif; ?>
            <div class="col-md-4">
                <div class="card h-100 product-card <?= $product_code === $it['it_id'] ? 'border-primary bg-primary bg-opacity-10' : '' ?>"
                     style="cursor:pointer;transition:all 0.2s;border-width:2px;"
                     onclick="selectProduct('<?= $it['it_id'] ?>', this)">
                    <div class="card-body">
                        <h6 class="card-title"><?= htmlspecialchars($it['it_name']) ?></h6>
                        <div class="fs-4 fw-bold text-primary mb-2"><?= number_format($it['it_price']) ?>원</div>
                        <p class="text-muted mb-0" style="font-size:12px;"><?= htmlspecialchars(mb_substr($it['it_explan'] ?? '', 0, 50)) ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- 결제 수단 -->
        <h5 class="mb-3"><i class="bi bi-credit-card me-1"></i>결제 수단</h5>
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="form-check card p-3">
                    <input class="form-check-input" type="radio" name="pay_method" value="card" id="payCard" checked>
                    <label class="form-check-label" for="payCard"><i class="bi bi-credit-card me-1"></i>신용카드</label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check card p-3">
                    <input class="form-check-input" type="radio" name="pay_method" value="bank" id="payBank">
                    <label class="form-check-label" for="payBank"><i class="bi bi-bank me-1"></i>무통장입금</label>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-check card p-3">
                    <input class="form-check-input" type="radio" name="pay_method" value="vbank" id="payVbank">
                    <label class="form-check-label" for="payVbank"><i class="bi bi-wallet2 me-1"></i>가상계좌</label>
                </div>
            </div>
        </div>

        <!-- 결제 요약 -->
        <div class="card border-primary mb-4" id="paymentSummary" style="display:none;">
            <div class="card-body">
                <h5 class="card-title"><i class="bi bi-receipt me-1"></i>결제 요약</h5>
                <table class="table table-sm mb-0" style="font-size:14px;">
                    <tr><th>상품</th><td id="summaryName">-</td></tr>
                    <tr><th>금액</th><td id="summaryPrice" class="fw-bold text-primary">-</td></tr>
                    <tr><th>결제수단</th><td id="summaryMethod">신용카드</td></tr>
                </table>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg" id="payBtn" disabled>
            <i class="bi bi-lock me-1"></i>결제하기 (테스트)
        </button>
    </form>
</div>

<script>
var products = <?= json_encode($items, JSON_UNESCAPED_UNICODE) ?>;

function selectProduct(code, el) {
    document.getElementById('selectedProduct').value = code;
    document.querySelectorAll('.product-card').forEach(function(c) {
        c.classList.remove('border-primary', 'bg-primary', 'bg-opacity-10');
    });
    el.classList.add('border-primary', 'bg-primary', 'bg-opacity-10');

    var p = products.find(function(x) { return x.it_id === code; });
    if (p) {
        document.getElementById('summaryName').textContent = p.it_name;
        document.getElementById('summaryPrice').textContent = Number(p.it_price).toLocaleString() + '원';
        document.getElementById('paymentSummary').style.display = 'block';
        document.getElementById('payBtn').disabled = false;
    }
}

document.querySelectorAll('input[name="pay_method"]').forEach(function(r) {
    r.addEventListener('change', function() {
        var labels = {card: '신용카드', bank: '무통장입금', vbank: '가상계좌'};
        document.getElementById('summaryMethod').textContent = labels[this.value] || this.value;
    });
});

// 초기 선택
var initCode = '<?= addslashes($product_code) ?>';
if (initCode) {
    var el = document.querySelector('.product-card.border-primary');
    if (el) selectProduct(initCode, el);
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
