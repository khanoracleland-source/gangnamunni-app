<?php
/**
 * 파트너 지갑 (병원용)
 * - 현재 잔액, 거래내역, 충전 상품 선택
 * - 독립 페이지 (admin_header 미사용, Bootstrap 5.3 standalone)
 * @created 2026-03-11
 */
session_start();

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/includes/cpv_helper.php';

// DB 연결
$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
if (!$connect) die('DB 연결 실패');
mysqli_set_charset($connect, 'utf8mb4');

// ============================================================================
// [Track A3 - 2026-06-12] 지갑 충전 결제 처리 (AJAX POST)
// - 영카트 주문(g5_shop_order/cart) 기록 + 지갑 적립 + 거래내역 — admin_pay_bridge 패턴 재사용
// - PG 테스트 모드(de_card_test=1)에서만 즉시 충전. 실결제 모드는 PG 모듈 연동 후 개방
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'charge_pay') {
    header('Content-Type: application/json; charset=utf-8');

    $pay_hospital_id = intval($_SESSION['partner_hospital_id'] ?? $_POST['hospital_id'] ?? 0);
    $product_id = intval($_POST['product_id'] ?? 0);
    $pay_method = in_array($_POST['pay_method'] ?? '', ['card', 'bank', 'vbank']) ? $_POST['pay_method'] : 'card';

    if ($pay_hospital_id <= 0) {
        echo json_encode(['success' => false, 'message' => '병원 정보가 없습니다. 다시 로그인해 주세요.']); exit;
    }

    // 상품 검증 (서버측 단가 — 클라이언트 금액 신뢰 안 함)
    $pay_product = null;
    foreach (getCpvChargeProducts() as $p) {
        if ((int)$p['id'] === $product_id) { $pay_product = $p; break; }
    }
    if (!$pay_product) {
        echo json_encode(['success' => false, 'message' => '충전 상품을 찾을 수 없습니다.']); exit;
    }

    // PG 모드 확인 — 실결제 모드에서는 PG 모듈 연동 전까지 차단 (무결제 적립 방지)
    $pg_test = 1;
    $rp = mysqli_query($connect, "SELECT de_card_test FROM g5_shop_default LIMIT 1");
    if ($rp && $rrow = mysqli_fetch_assoc($rp)) $pg_test = (int)$rrow['de_card_test'];
    if ($pg_test !== 1) {
        echo json_encode(['success' => false, 'message' => '실결제 모드입니다. PG 결제창 연동 적용 후 이용 가능합니다. 관리자에게 문의하세요.']); exit;
    }

    $price = (int)$pay_product['price'];
    $bonus = (int)$pay_product['bonus_amount'];
    $total = (int)$pay_product['total'];

    // 병원명
    $stmt = mysqli_prepare($connect, "SELECT name FROM gangnamunni_hospitals WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $pay_hospital_id);
    mysqli_stmt_execute($stmt);
    $hrow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$hrow) {
        echo json_encode(['success' => false, 'message' => '병원을 찾을 수 없습니다.']); exit;
    }
    $h_name = $hrow['name'];

    mysqli_begin_transaction($connect);
    try {
        // 1) 영카트 주문 기록 (od_id는 bigint — 숫자형 생성, Gnuboard 표준 방식)
        $od_id = date('YmdHis') . sprintf('%02d', mt_rand(0, 99));
        $od_memo = '파트너 지갑 충전(테스트) - ' . $h_name . ' / ' . $pay_product['name'];
        $stmt = mysqli_prepare($connect,
            "INSERT INTO g5_shop_order (od_id, mb_id, od_name, od_email, od_tel, od_hp,
             od_zip1, od_zip2, od_addr1, od_addr2, od_addr3, od_addr_jibeon,
             od_b_zip1, od_b_zip2, od_b_addr1, od_b_addr2, od_b_addr3, od_b_addr_jibeon, od_b_name, od_b_tel, od_b_hp,
             od_deposit_name, od_receipt_price, od_send_cost, od_receipt_point,
             od_cart_count, od_status, od_settle_case, od_test, od_memo,
             od_shop_memo, od_mod_history, od_cash, od_cash_no, od_cash_info, od_time)
             VALUES (?, ?, ?, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
             ?, ?, 0, 0, 1, '완료', ?, 1, ?, '', '', 0, '', '', NOW())");
        $od_mb = 'partner_' . $pay_hospital_id;
        mysqli_stmt_bind_param($stmt, 'ssssiss', $od_id, $od_mb, $h_name, $h_name, $price, $pay_method, $od_memo);
        if (!mysqli_stmt_execute($stmt)) throw new Exception('주문 기록 실패: ' . mysqli_error($connect));
        mysqli_stmt_close($stmt);

        $it_id = 'wallet_charge_' . $product_id;
        $stmt = mysqli_prepare($connect,
            "INSERT INTO g5_shop_cart (od_id, mb_id, it_id, it_name, ct_qty, ct_price, ct_point, ct_status, ct_stock_use,
             ct_history, ct_invoice_time, ct_js_time, ct_time)
             VALUES (?, ?, ?, ?, 1, ?, 0, '완료', 1, '', NOW(), NOW(), NOW())");
        $it_name = '지갑 충전 - ' . $pay_product['name'];
        mysqli_stmt_bind_param($stmt, 'ssssi', $od_id, $od_mb, $it_id, $it_name, $price);
        if (!mysqli_stmt_execute($stmt)) throw new Exception('주문 상세 기록 실패: ' . mysqli_error($connect));
        mysqli_stmt_close($stmt);

        // 2) 지갑 적립 (행 보장 + 잔액/누적 증가)
        $stmt = mysqli_prepare($connect,
            "INSERT INTO gangnamunni_cpv_wallets (hospital_id, balance, total_charged)
             VALUES (?, 0, 0) ON DUPLICATE KEY UPDATE hospital_id = hospital_id");
        mysqli_stmt_bind_param($stmt, 'i', $pay_hospital_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($connect,
            "UPDATE gangnamunni_cpv_wallets SET balance = balance + ?, total_charged = total_charged + ? WHERE hospital_id = ?");
        mysqli_stmt_bind_param($stmt, 'iii', $total, $total, $pay_hospital_id);
        if (!mysqli_stmt_execute($stmt)) throw new Exception('지갑 적립 실패: ' . mysqli_error($connect));
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($connect, "SELECT balance FROM gangnamunni_cpv_wallets WHERE hospital_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $pay_hospital_id);
        mysqli_stmt_execute($stmt);
        $new_balance = (float)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['balance'] ?? 0);
        mysqli_stmt_close($stmt);

        // 3) 거래내역 기록
        $tx_desc = '지갑 충전(테스트결제) ' . $pay_product['name'] . ' — 결제 ' . number_format($price) . '원'
                 . ($bonus > 0 ? ' + 보너스 ' . number_format($bonus) . '원' : '') . ' / 주문 ' . $od_id;
        $tx_type = 'charge';
        $stmt = mysqli_prepare($connect,
            "INSERT INTO gangnamunni_cpv_transactions (hospital_id, type, amount, balance_after, description, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, 'isdds', $pay_hospital_id, $tx_type, $total, $new_balance, $tx_desc);
        if (!mysqli_stmt_execute($stmt)) throw new Exception('거래내역 기록 실패: ' . mysqli_error($connect));
        mysqli_stmt_close($stmt);

        mysqli_commit($connect);
        echo json_encode([
            'success' => true,
            'od_id' => $od_id,
            'charged' => $total,
            'new_balance' => $new_balance,
            'message' => number_format($total) . '원이 충전되었습니다. (주문 ' . $od_id . ')'
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        mysqli_rollback($connect);
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 병원 파트너 인증 (세션 or GET 파라미터)
// 실제 운영에서는 파트너 로그인 세션 사용, 현재는 hospital_id 기반
$hospital_id = intval($_SESSION['partner_hospital_id'] ?? $_GET['hospital_id'] ?? 0);
if ($hospital_id <= 0) {
    // 세션/파라미터 둘 다 없으면 로그인 안내
    $show_login_form = true;
} else {
    $show_login_form = false;
}

// 지갑 정보 로드
$wallet = null;
$hospital_name = '';
$transactions = [];
$tx_page = max(1, intval($_GET['tx_page'] ?? 1));
$tx_per_page = 15;
$tx_total = 0;
$tx_total_pages = 1;

if (!$show_login_form) {
    // 병원 이름
    $stmt = mysqli_prepare($connect, "SELECT name FROM gangnamunni_hospitals WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $h_result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    $hospital_name = $h_result['name'] ?? '병원 #' . $hospital_id;
    mysqli_stmt_close($stmt);

    // 지갑 조회
    $stmt = mysqli_prepare($connect, "SELECT * FROM gangnamunni_cpv_wallets WHERE hospital_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $wallet = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    // 거래내역
    if ($wallet) {
        $tx_offset = ($tx_page - 1) * $tx_per_page;

        // 총 건수
        $stmt = mysqli_prepare($connect, "SELECT COUNT(*) as cnt FROM gangnamunni_cpv_transactions WHERE hospital_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
        mysqli_stmt_execute($stmt);
        $tx_total = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'] ?? 0;
        $tx_total_pages = max(1, ceil($tx_total / $tx_per_page));
        mysqli_stmt_close($stmt);

        // 거래내역 데이터
        $stmt = mysqli_prepare($connect,
            "SELECT * FROM gangnamunni_cpv_transactions WHERE hospital_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?");
        mysqli_stmt_bind_param($stmt, 'iii', $hospital_id, $tx_per_page, $tx_offset);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $transactions[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

// 충전 상품 로드
$charge_products = getCpvChargeProducts();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPV 지갑 - <?php echo htmlspecialchars($hospital_name ?: '서울미녀 파트너'); ?></title>
    <!-- 다크모드 플래시 방지 -->
    <style>
    html{background:#fff;color:#212529}
    html[data-bs-theme="dark"]{background:#212529;color:#dee2e6}
    </style>
    <script>
    (function(){
        var t = localStorage.getItem('partner_theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', t);
    })();
    </script>
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans KR', sans-serif;
            font-size: 14px;
            min-height: 100vh;
        }
        .partner-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 20px 0;
        }
        [data-bs-theme="dark"] .partner-header {
            background: linear-gradient(135deg, #4c5ec7 0%, #5c3a82 100%);
        }
        .wallet-balance-card {
            background: linear-gradient(135deg, #2563eb 0%, #AE1932 100%);
            color: #fff;
            border-radius: 16px;
            padding: 32px 24px;
            position: relative;
            overflow: hidden;
        }
        .wallet-balance-card::after {
            content: '';
            position: absolute;
            top: -30px;
            right: -30px;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
        }
        .wallet-balance-card .balance-amount {
            font-size: 36px;
            font-weight: 800;
            letter-spacing: -1px;
        }
        .product-card {
            border: 2px solid var(--bs-border-color);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
            position: relative;
        }
        .product-card:hover {
            border-color: var(--bs-primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .product-card.selected {
            border-color: var(--bs-primary);
            background: rgba(37, 99, 235, 0.05);
        }
        [data-bs-theme="dark"] .product-card.selected {
            background: rgba(37, 99, 235, 0.15);
        }
        .product-card .bonus-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: #fff;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 11px;
            font-weight: 700;
        }
        .product-card .price {
            font-size: 24px;
            font-weight: 800;
            color: var(--bs-primary);
        }
        .tx-type-badge {
            font-size: 11px;
        }
        @media (max-width: 575.98px) {
            .wallet-balance-card .balance-amount { font-size: 28px; }
            .product-card .price { font-size: 20px; }
        }
    </style>
</head>
<body>

<!-- 상단 헤더 -->
<div class="partner-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0 fw-bold">
                    <i class="bi bi-wallet2 me-1"></i>CPV 지갑
                </h5>
                <?php if ($hospital_name): ?>
                <small class="opacity-75"><?php echo htmlspecialchars($hospital_name); ?></small>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-outline-light" id="themeToggle" title="테마 변경">
                    <i class="bi bi-moon-stars" id="themeIcon"></i>
                </button>
                <?php if (!$show_login_form): ?>
                <a href="partner_wallet.php" class="btn btn-sm btn-outline-light">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container py-4">

<?php if ($show_login_form): ?>
    <!-- 로그인 안내 -->
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-lock" style="font-size:48px;" class="text-body-secondary"></i>
                    <h5 class="mt-3 mb-2">파트너 지갑 접속</h5>
                    <p class="text-body-secondary">병원 ID를 입력하여 지갑을 확인하세요.</p>
                    <form method="get" class="mt-4">
                        <div class="input-group" style="max-width:300px;margin:0 auto;">
                            <input type="number" name="hospital_id" class="form-control" placeholder="병원 ID 입력" required min="1">
                            <button class="btn btn-primary"><i class="bi bi-arrow-right"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php elseif (!$wallet): ?>
    <!-- 지갑 미존재 -->
    <div class="text-center py-5">
        <i class="bi bi-wallet" style="font-size:64px;" class="text-body-secondary d-block mb-3"></i>
        <h4>지갑이 아직 생성되지 않았습니다</h4>
        <p class="text-body-secondary">관리자에게 문의하여 CPV 지갑을 개설하세요.</p>
        <a href="partner_wallet.php" class="btn btn-outline-primary mt-2">
            <i class="bi bi-arrow-clockwise me-1"></i>새로고침
        </a>
    </div>

<?php else: ?>
    <!-- ===== 잔액 카드 ===== -->
    <div class="wallet-balance-card mb-4">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="opacity-75 mb-1"><i class="bi bi-wallet2 me-1"></i>현재 잔액</div>
                <div class="balance-amount"><?php echo formatCpvCurrency($wallet['balance']); ?></div>
            </div>
            <div class="text-end">
                <?php if ($wallet['is_paused']): ?>
                    <span class="badge bg-warning text-dark"><i class="bi bi-pause-circle me-1"></i>일시정지</span>
                <?php elseif ($wallet['is_active']): ?>
                    <span class="badge bg-light text-dark"><i class="bi bi-check-circle me-1"></i>활성</span>
                <?php else: ?>
                    <span class="badge bg-secondary">비활성</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="row mt-3 pt-3" style="border-top:1px solid rgba(255,255,255,0.2);">
            <div class="col-4 text-center">
                <small class="opacity-75 d-block">총 충전</small>
                <strong><?php echo formatCpvCurrency($wallet['total_charged']); ?></strong>
            </div>
            <div class="col-4 text-center">
                <small class="opacity-75 d-block">총 소진</small>
                <strong><?php echo formatCpvCurrency($wallet['total_spent']); ?></strong>
            </div>
            <div class="col-4 text-center">
                <small class="opacity-75 d-block">일일 한도</small>
                <strong><?php echo $wallet['daily_cap'] > 0 ? formatCpvCurrency($wallet['daily_cap']) : '무제한'; ?></strong>
            </div>
        </div>
    </div>

    <!-- ===== 충전 상품 ===== -->
    <?php if (!empty($charge_products)): ?>
    <div class="mb-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-plus-circle me-1"></i>충전하기
            <span class="badge bg-warning text-dark ms-2" style="font-size:11px;">PG 테스트 모드 — 실결제 없이 충전됩니다</span>
        </h5>
        <div class="row g-3">
            <?php foreach ($charge_products as $product): ?>
            <div class="col-6 col-md-4 col-lg">
                <div class="product-card" data-product-id="<?php echo $product['id']; ?>"
                     data-price="<?php echo $product['price']; ?>"
                     data-bonus="<?php echo $product['bonus_amount']; ?>"
                     onclick="selectProduct(this)">
                    <?php if ($product['bonus_rate'] > 0): ?>
                    <div class="bonus-badge">+<?php echo (int)$product['bonus_rate']; ?>%</div>
                    <?php endif; ?>
                    <div class="mb-2">
                        <i class="bi bi-coin" style="font-size:28px;color:var(--bs-warning);"></i>
                    </div>
                    <div class="fw-bold mb-1"><?php echo htmlspecialchars($product['name']); ?></div>
                    <div class="price"><?php echo number_format($product['price']); ?>원</div>
                    <?php if ($product['bonus_amount'] > 0): ?>
                    <div class="mt-1">
                        <small class="text-success fw-bold">
                            +<?php echo number_format($product['bonus_amount']); ?>원 보너스
                        </small>
                    </div>
                    <div class="mt-1 text-body-secondary" style="font-size:12px;">
                        실제 충전: <?php echo number_format($product['total']); ?>원
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <!-- 충전 버튼 -->
        <div class="text-center mt-3">
            <button class="btn btn-primary btn-lg px-5" id="chargeBtn" disabled onclick="requestCharge()">
                <i class="bi bi-credit-card me-1"></i>충전하기
            </button>
            <div id="selectedInfo" class="mt-2 text-body-secondary" style="font-size:13px;"></div>
        </div>
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body text-center py-4 text-body-secondary">
            <i class="bi bi-info-circle me-1"></i>현재 등록된 충전 상품이 없습니다. 관리자에게 문의하세요.
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== 거래내역 ===== -->
    <div class="mb-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-receipt me-1"></i>거래내역</h5>
        <div class="card border-0 shadow-sm">
            <?php if (empty($transactions)): ?>
            <div class="card-body text-center py-5 text-body-secondary">
                <i class="bi bi-inbox" style="font-size:32px;" class="d-block mb-2"></i>
                <div>거래내역이 없습니다.</div>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:13px;">
                    <thead class="table-light">
                        <tr>
                            <th>일시</th>
                            <th>유형</th>
                            <th class="text-end">금액</th>
                            <th class="text-end">잔액</th>
                            <th>설명</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $type_labels = ['charge' => '충전', 'deduct' => '차감', 'refund' => '환불', 'adjust' => '조정', 'bonus' => '보너스'];
                        $type_badges = ['charge' => 'success', 'deduct' => 'danger', 'refund' => 'info', 'adjust' => 'warning', 'bonus' => 'primary'];
                        foreach ($transactions as $tx):
                            $is_minus = ($tx['type'] === 'deduct');
                            $sign = $is_minus ? '-' : '+';
                            $amt_class = $is_minus ? 'text-danger' : 'text-success';
                        ?>
                        <tr>
                            <td class="text-nowrap"><?php echo substr($tx['created_at'] ?? '', 0, 16); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $type_badges[$tx['type']] ?? 'secondary'; ?> tx-type-badge">
                                    <?php echo $type_labels[$tx['type']] ?? $tx['type']; ?>
                                </span>
                            </td>
                            <td class="text-end fw-bold <?php echo $amt_class; ?>">
                                <?php echo $sign . formatCpvCurrency($tx['amount']); ?>
                            </td>
                            <td class="text-end"><?php echo formatCpvCurrency($tx['balance_after']); ?></td>
                            <td><?php echo htmlspecialchars($tx['description'] ?? ''); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($tx_total_pages > 1): ?>
            <div class="card-footer">
                <nav>
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <?php for ($i = 1; $i <= $tx_total_pages; $i++): ?>
                        <li class="page-item <?php echo $i === $tx_page ? 'active' : ''; ?>">
                            <a class="page-link" href="?hospital_id=<?php echo $hospital_id; ?>&tx_page=<?php echo $i; ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

</div><!-- /.container -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// 테마 토글
(function() {
    var html = document.documentElement;
    var toggleBtn = document.getElementById('themeToggle');
    var icon = document.getElementById('themeIcon');

    function updateIcon(theme) {
        if (icon) icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
    }
    updateIcon(html.getAttribute('data-bs-theme') || 'light');

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            var current = html.getAttribute('data-bs-theme');
            var next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', next);
            localStorage.setItem('partner_theme', next);
            updateIcon(next);
        });
    }
})();

// 충전 상품 선택
var selectedProduct = null;

function selectProduct(el) {
    // 기존 선택 해제
    document.querySelectorAll('.product-card').forEach(function(c) {
        c.classList.remove('selected');
    });
    el.classList.add('selected');

    selectedProduct = {
        id: el.getAttribute('data-product-id'),
        price: parseInt(el.getAttribute('data-price')),
        bonus: parseInt(el.getAttribute('data-bonus'))
    };

    var btn = document.getElementById('chargeBtn');
    btn.disabled = false;

    var info = document.getElementById('selectedInfo');
    var total = selectedProduct.price + selectedProduct.bonus;
    info.textContent = '결제 금액: ' + selectedProduct.price.toLocaleString('ko-KR') + '원' +
        (selectedProduct.bonus > 0 ? ' (보너스 +' + selectedProduct.bonus.toLocaleString('ko-KR') + '원, 총 ' + total.toLocaleString('ko-KR') + '원 충전)' : '');
}

// [Track A3 - 2026-06-12] 실제 충전 결제 호출 (영카트 주문 + 지갑 적립)
function requestCharge() {
    if (!selectedProduct) {
        alert('충전 상품을 선택하세요.');
        return;
    }

    var total = selectedProduct.price + selectedProduct.bonus;
    var msg = '결제 금액: ' + selectedProduct.price.toLocaleString('ko-KR') + '원\n';
    if (selectedProduct.bonus > 0) {
        msg += '보너스: +' + selectedProduct.bonus.toLocaleString('ko-KR') + '원\n';
        msg += '총 충전: ' + total.toLocaleString('ko-KR') + '원\n';
    }
    msg += '\n신용카드(테스트 결제)로 충전을 진행하시겠습니까?';

    if (!confirm(msg)) return;

    var btn = document.getElementById('chargeBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>결제 처리 중...';

    var fd = new FormData();
    fd.append('action', 'charge_pay');
    fd.append('product_id', selectedProduct.id);
    fd.append('pay_method', 'card');
    fd.append('hospital_id', '<?php echo (int)$hospital_id; ?>');

    fetch('partner_wallet.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                alert('✅ ' + res.message + '\n현재 잔액: ' + Number(res.new_balance).toLocaleString('ko-KR') + '원');
                location.reload();
            } else {
                alert('충전 실패: ' + (res.message || '알 수 없는 오류'));
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-credit-card me-1"></i>충전하기';
            }
        })
        .catch(function(e) {
            alert('통신 오류: ' + e);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-credit-card me-1"></i>충전하기';
        });
}
</script>
</body>
</html>
