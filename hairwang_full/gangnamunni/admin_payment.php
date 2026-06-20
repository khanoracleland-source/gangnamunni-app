<?php
session_start();
$admin_page_title = '결제 관리';
$_tab = $_GET['tab'] ?? 'orders';
$_tab_keys = ['orders'=>'payment_status','products'=>'payment_products','stats'=>'payment_stats','pg_settings'=>'pg_settings'];
$admin_current = $_tab_keys[$_tab] ?? 'payment_status';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_admin_login();
require_permission('payment_status');

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

$tab = $_GET['tab'] ?? 'orders';

// 통계
$stats = ['total_orders' => 0, 'total_revenue' => 0, 'paid_count' => 0, 'pending_count' => 0];
$r = mysqli_query($connect, "SELECT COUNT(*) as cnt, COALESCE(SUM(od_receipt_price),0) as rev FROM g5_shop_order");
if ($row = mysqli_fetch_assoc($r)) { $stats['total_orders'] = $row['cnt']; $stats['total_revenue'] = $row['rev']; }
$r2 = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM g5_shop_order WHERE od_status = '완료'");
if ($row2 = mysqli_fetch_assoc($r2)) $stats['paid_count'] = $row2['cnt'];
$r3 = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM g5_shop_order WHERE od_status IN ('주문','입금대기','입금')");
if ($row3 = mysqli_fetch_assoc($r3)) $stats['pending_count'] = $row3['cnt'];

// 상품 목록
$items = [];
$ri = mysqli_query($connect, "SELECT it_id, it_name, it_price, ca_id, it_stock_qty, it_use FROM g5_shop_item ORDER BY ca_id, it_id");
while ($ri && $row = mysqli_fetch_assoc($ri)) $items[] = $row;

// 주문 목록
$orders = [];
$ro = mysqli_query($connect, "SELECT * FROM g5_shop_order ORDER BY od_id DESC LIMIT 50");
while ($ro && $row = mysqli_fetch_assoc($ro)) $orders[] = $row;

// 월별 매출
$monthly = [];
$rm = mysqli_query($connect, "SELECT DATE_FORMAT(od_time, '%Y-%m') as month, COUNT(*) as cnt, SUM(od_receipt_price) as rev FROM g5_shop_order GROUP BY month ORDER BY month DESC LIMIT 12");
while ($rm && $row = mysqli_fetch_assoc($rm)) $monthly[] = $row;

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="bi bi-credit-card-2-front me-2"></i>결제 관리</h4>
        <a href="admin_pay_bridge.php" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-cart-plus me-1"></i>테스트 결제</a>
    </div>

    <!-- 통계 카드 -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted mb-1"><i class="bi bi-receipt me-1"></i>전체 주문</div>
                    <div class="fs-3 fw-bold text-primary"><?= number_format($stats['total_orders']) ?>건</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted mb-1"><i class="bi bi-cash-stack me-1"></i>총 매출</div>
                    <div class="fs-3 fw-bold text-success"><?= number_format($stats['total_revenue']) ?>원</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted mb-1"><i class="bi bi-check-circle me-1"></i>결제 완료</div>
                    <div class="fs-3 fw-bold text-info"><?= number_format($stats['paid_count']) ?>건</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body text-center">
                    <div class="text-muted mb-1"><i class="bi bi-hourglass-split me-1"></i>대기 중</div>
                    <div class="fs-3 fw-bold text-warning"><?= number_format($stats['pending_count']) ?>건</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 탭 -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><a class="nav-link <?= $tab==='orders' ? 'active' : '' ?>" href="?tab=orders"><i class="bi bi-list-ul me-1"></i>주문 목록</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='products' ? 'active' : '' ?>" href="?tab=products"><i class="bi bi-box-seam me-1"></i>상품 관리</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='stats' ? 'active' : '' ?>" href="?tab=stats"><i class="bi bi-graph-up me-1"></i>매출 통계</a></li>
        <li class="nav-item"><a class="nav-link <?= $tab==='pg_settings' ? 'active' : '' ?>" href="?tab=pg_settings"><i class="bi bi-gear me-1"></i>PG 설정</a></li>
    </ul>

    <?php if ($tab === 'orders'): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent"><strong><i class="bi bi-receipt me-1"></i>최근 주문 (최대 50건)</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle" style="font-size:13px;">
                    <thead class="table-light">
                        <tr><th>주문번호</th><th>주문자</th><th>상품</th><th>금액</th><th>상태</th><th>결제일</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">주문 내역이 없습니다.</td></tr>
                        <?php else: foreach ($orders as $o): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($o['od_id']) ?></code></td>
                            <td><?= htmlspecialchars($o['od_name'] ?? $o['mb_id'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($o['od_cart_count'] ?? '-') ?>건</td>
                            <td class="fw-bold"><?= number_format($o['od_receipt_price'] ?? 0) ?>원</td>
                            <td><?php
                                $st = $o['od_status'] ?? '주문';
                                $badge = 'secondary';
                                if ($st === '완료') $badge = 'success';
                                elseif ($st === '입금') $badge = 'info';
                                elseif ($st === '취소') $badge = 'danger';
                                ?><span class="badge bg-<?= $badge ?>"><?= $st ?></span>
                            </td>
                            <td><?= $o['od_time'] ?? '-' ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'products'): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent"><strong><i class="bi bi-box-seam me-1"></i>등록 상품 (<?= count($items) ?>개)</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle" style="font-size:13px;">
                    <thead class="table-light">
                        <tr><th>상품코드</th><th>상품명</th><th>카테고리</th><th>가격</th><th>재고</th><th>상태</th><th>결제</th></tr>
                    </thead>
                    <tbody>
                        <?php
                        $ca_names = ['100'=>'광고','200'=>'병원등록','300'=>'구독','10'=>'클리닉','20'=>'앰푸','30'=>'다부진'];
                        foreach ($items as $it): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($it['it_id']) ?></code></td>
                            <td><?= htmlspecialchars($it['it_name']) ?></td>
                            <td><span class="badge bg-secondary"><?= $ca_names[$it['ca_id']] ?? $it['ca_id'] ?></span></td>
                            <td class="fw-bold"><?= number_format($it['it_price']) ?>원</td>
                            <td><?= $it['it_stock_qty'] ?></td>
                            <td><?= $it['it_use'] ? '<span class="badge bg-success">판매중</span>' : '<span class="badge bg-danger">중지</span>' ?></td>
                            <td><a href="admin_pay_bridge.php?product_code=<?= urlencode($it['it_id']) ?>" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-credit-card"></i></a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'stats'): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent"><strong><i class="bi bi-graph-up me-1"></i>월별 매출 통계</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size:13px;">
                    <thead class="table-light"><tr><th>월</th><th>주문 수</th><th>매출</th></tr></thead>
                    <tbody>
                        <?php if (empty($monthly)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">데이터가 없습니다.</td></tr>
                        <?php else: foreach ($monthly as $m): ?>
                        <tr>
                            <td><strong><?= $m['month'] ?></strong></td>
                            <td><?= number_format($m['cnt']) ?>건</td>
                            <td class="fw-bold text-success"><?= number_format($m['rev']) ?>원</td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'pg_settings'): ?>
    <?php
    $pg = [];
    $rp = mysqli_query($connect, "SELECT de_pg_service, de_inicis_mid, de_card_test, de_card_use, de_bank_use, de_vbank_use, de_admin_company_name FROM g5_shop_default LIMIT 1");
    if ($rp) $pg = mysqli_fetch_assoc($rp);
    ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent"><strong><i class="bi bi-gear me-1"></i>PG 설정 현황</strong></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <table class="table table-bordered" style="font-size:13px;">
                        <tr><th style="width:40%">PG사</th><td><span class="badge bg-primary"><?= strtoupper($pg['de_pg_service'] ?? '-') ?></span></td></tr>
                        <tr><th>MID</th><td><code><?= htmlspecialchars($pg['de_inicis_mid'] ?? '-') ?></code></td></tr>
                        <tr><th>테스트모드</th><td><?= ($pg['de_card_test'] ?? 0) ? '<span class="badge bg-warning text-dark">테스트</span>' : '<span class="badge bg-success">실결제</span>' ?></td></tr>
                        <tr><th>카드결제</th><td><?= ($pg['de_card_use'] ?? 0) ? '<span class="badge bg-success">활성화</span>' : '<span class="badge bg-danger">비활성화</span>' ?></td></tr>
                        <tr><th>무통장입금</th><td><?= ($pg['de_bank_use'] ?? 0) ? '<span class="badge bg-success">활성화</span>' : '<span class="badge bg-danger">비활성화</span>' ?></td></tr>
                        <tr><th>가상계좌</th><td><?= ($pg['de_vbank_use'] ?? 0) ? '<span class="badge bg-success">활성화</span>' : '<span class="badge bg-danger">비활성화</span>' ?></td></tr>
                        <tr><th>상호명</th><td><?= htmlspecialchars($pg['de_admin_company_name'] ?? '-') ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-info" style="font-size:13px;">
                        <i class="bi bi-info-circle me-1"></i><strong>안내</strong>
                        <ul class="mb-0 mt-2">
                            <li>현재 <strong>이니시스 테스트 MID (INIpayTest)</strong>로 설정되어 있습니다.</li>
                            <li>테스트 결제는 실제 과금되지 않습니다.</li>
                            <li>실서비스 전환 시 PG사 가맹점 계약 후 MID를 변경해야 합니다.</li>
                            <li>Gnuboard 관리자(<a href="/adm" target="_blank">/adm</a>) &gt; 쇼핑몰설정에서도 변경 가능합니다.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
