<?php
/**
 * API 관리 홈
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 관리자 공통 파일 포함
include_once '_common.php';

// 헤더 출력
include_once G5_ADMIN_PATH.'/admin.head.php';

// 통계 데이터 가져오기
$sql = "SELECT 
    COUNT(*) as total_keys,
    SUM(CASE WHEN ak_status = 1 THEN 1 ELSE 0 END) as active_keys,
    SUM(CASE WHEN ak_type = 'sns' THEN 1 ELSE 0 END) as sns_keys,
    SUM(CASE WHEN ak_type = 'pg' THEN 1 ELSE 0 END) as pg_keys
FROM {$g5['api_key_table']}";
$key_stats = sql_fetch($sql);

$sql = "SELECT 
    COUNT(*) as total_ips,
    SUM(CASE WHEN ai_status = 1 THEN 1 ELSE 0 END) as active_ips
FROM {$g5['api_ip_table']}";
$ip_stats = sql_fetch($sql);

$sql = "SELECT 
    COUNT(*) as total_logs,
    COUNT(CASE WHEN DATE(al_created_at) = CURDATE() THEN 1 END) as today_logs
FROM {$g5['api_log_table']}";
$log_stats = sql_fetch($sql);

$sql = "SELECT
    COUNT(*) as total_orders,
    SUM(CASE WHEN po_status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
    IFNULL(SUM(po_amount), 0) as total_amount
FROM {$g5['pg_api_orders_table']}";
$order_stats = sql_fetch($sql);

// 빈 결과 처리
if (!$order_stats) {
    $order_stats = array(
        'total_orders' => 0,
        'paid_orders' => 0,
        'total_amount' => 0
    );
}

// 최근 로그 가져오기
$sql = "SELECT * FROM {$g5['api_log_table']}
ORDER BY al_created_at DESC
LIMIT 10";
$recent_logs = sql_query($sql);

// 최근 주문 가져오기
$sql = "SELECT * FROM {$g5['pg_api_orders_table']}
ORDER BY po_created_at DESC
LIMIT 10";
$recent_orders = sql_query($sql);
?>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}

.stat-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-card h3 {
    font-size: 14px;
    color: #666;
    margin: 0 0 10px 0;
    font-weight: normal;
}

.stat-card .stat-value {
    font-size: 32px;
    font-weight: bold;
    color: #333;
    margin: 10px 0;
}

.stat-card .stat-label {
    font-size: 12px;
    color: #999;
}

@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="stats-grid">
    <div class="stat-card">
        <h3>🔑 전체 API 키</h3>
        <div class="stat-value"><?php echo number_format($key_stats['total_keys']); ?></div>
        <div class="stat-label">활성: <?php echo number_format($key_stats['active_keys']); ?>개</div>
    </div>

    <div class="stat-card">
        <h3>📱 SNS 로그인 키</h3>
        <div class="stat-value"><?php echo number_format($key_stats['sns_keys']); ?></div>
        <div class="stat-label">SNS 인증용</div>
    </div>

    <div class="stat-card">
        <h3>💳 PG 결제 키</h3>
        <div class="stat-value"><?php echo number_format($key_stats['pg_keys']); ?></div>
        <div class="stat-label">결제 연동용</div>
    </div>

    <div class="stat-card">
        <h3>🌐 IP 화이트리스트</h3>
        <div class="stat-value"><?php echo number_format($ip_stats['total_ips']); ?></div>
        <div class="stat-label">활성: <?php echo number_format($ip_stats['active_ips']); ?>개</div>
    </div>
</div>

<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
    <div class="stat-card">
        <h3>📊 API 로그</h3>
        <div class="stat-value"><?php echo number_format($log_stats['total_logs']); ?></div>
        <div class="stat-label">오늘: <?php echo number_format($log_stats['today_logs']); ?>건</div>
    </div>

    <div class="stat-card">
        <h3>💰 PG 주문</h3>
        <div class="stat-value"><?php echo number_format($order_stats['total_orders']); ?></div>
        <div class="stat-label">결제완료: <?php echo number_format($order_stats['paid_orders']); ?>건</div>
    </div>

    <div class="stat-card">
        <h3>💵 총 결제 금액</h3>
        <div class="stat-value"><?php echo number_format($order_stats['total_amount']); ?></div>
        <div class="stat-label">원</div>
    </div>
</div>

<div class="btn_fixed_top">
    <a href="<?php echo G5_API_ADMIN_URL; ?>/keylist.php" class="btn btn_01">API 키 관리</a>
    <a href="<?php echo G5_API_ADMIN_URL; ?>/iplist.php" class="btn btn_02">IP 화이트리스트</a>
    <a href="<?php echo G5_API_ADMIN_URL; ?>/loglist.php" class="btn btn_03">API 로그</a>
</div>

<div class="tbl_head01 tbl_wrap" style="margin-top: 20px;">
    <h2>📋 최근 API 로그 (10건)</h2>
    <table>
        <thead>
            <tr>
                <th>번호</th>
                <th>엔드포인트</th>
                <th>메서드</th>
                <th>요청 IP</th>
                <th>상태 코드</th>
                <th>실행 시간</th>
                <th>생성일</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($recent_logs && sql_num_rows($recent_logs) > 0) {
                while ($row = sql_fetch_array($recent_logs)) {
                    ?>
                    <tr>
                        <td><?php echo number_format($row['al_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['al_endpoint']); ?></td>
                        <td><?php echo htmlspecialchars($row['al_method']); ?></td>
                        <td><?php echo htmlspecialchars($row['al_request_ip']); ?></td>
                        <td><?php echo number_format($row['al_response_code']); ?></td>
                        <td><?php echo number_format($row['al_execution_time'], 4); ?>초</td>
                        <td><?php echo $row['al_created_at']; ?></td>
                    </tr>
                    <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="7" class="empty_table">로그가 없습니다.</td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>

<div class="tbl_head01 tbl_wrap" style="margin-top: 20px;">
    <h2>🛒 최근 PG 주문 (10건)</h2>
    <table>
        <thead>
            <tr>
                <th>주문 ID</th>
                <th>외부 주문 ID</th>
                <th>상품명</th>
                <th>금액</th>
                <th>상태</th>
                <th>PG</th>
                <th>생성일</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($recent_orders && sql_num_rows($recent_orders) > 0) {
                while ($row = sql_fetch_array($recent_orders)) {
                    $status_class = '';
                    if ($row['po_status'] == 'paid') {
                        $status_class = 'success';
                    } elseif ($row['po_status'] == 'failed') {
                        $status_class = 'error';
                    } elseif ($row['po_status'] == 'cancelled') {
                        $status_class = 'warning';
                    }
                    ?>
                    <tr>
                        <td><?php echo number_format($row['po_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['po_external_order_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['po_item_name']); ?></td>
                        <td><?php echo number_format($row['po_amount']); ?>원</td>
                        <td class="<?php echo $status_class; ?>"><?php echo strtoupper($row['po_status']); ?></td>
                        <td><?php echo strtoupper($row['po_pg_code']); ?></td>
                        <td><?php echo $row['po_created_at']; ?></td>
                    </tr>
                    <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="7" class="empty_table">주문이 없습니다.</td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>

<div class="btn_fixed_top" style="margin-top: 20px;">
    <a href="<?php echo G5_API_ADMIN_URL; ?>/pg/orderlist.php" class="btn btn_01">주문 목록</a>
    <a href="<?php echo G5_API_ADMIN_URL; ?>/pg/statistics.php" class="btn btn_02">통계</a>
</div>

<style>
.success { color: #4caf50; font-weight: bold; }
.error { color: #f44336; font-weight: bold; }
.warning { color: #ff9800; font-weight: bold; }
.empty_table { text-align: center; color: #999; padding: 20px; }

/* API 관리 버튼 스타일 개선 */
.btn_fixed_top {
    position: static !important;
    display: flex;
    gap: 10px;
    margin: 20px 0;
    justify-content: flex-start;
    flex-wrap: wrap;
}

.btn_fixed_top .btn {
    padding: 10px 20px;
    border-radius: 5px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s;
}

.btn_fixed_top .btn_01 {
    background: #4CAF50;
    color: white !important;
}

.btn_fixed_top .btn_01:hover {
    background: #45a049;
}

.btn_fixed_top .btn_02 {
    background: #2196F3;
    color: white !important;
}

.btn_fixed_top .btn_02:hover {
    background: #0b7dda;
}

.btn_fixed_top .btn_03 {
    background: #FF9800;
    color: white !important;
}

.btn_fixed_top .btn_03:hover {
    background: #e68900;
}
</style>

<?php
// 푸터 출력
include_once G5_ADMIN_PATH.'/admin.tail.php';
?>
