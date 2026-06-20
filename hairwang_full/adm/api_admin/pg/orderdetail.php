<?php
/**
 * PG 주문 상세 페이지 (AJAX)
 * PG 주문 상세 내용을 반환하는 페이지
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 관리자 공통 파일 포함
include_once './../_common.php';

// 주문 ID
$po_id = isset($_GET['po_id']) ? (int)$_GET['po_id'] : 0;

if ($po_id === 0) {
    echo '<p style="color:red;">주문 ID가 유효하지 않습니다.</p>';
    exit;
}

// 주문 정보 조회
$sql = "
    SELECT
        po.*,
        ak.ak_name
    FROM {$g5['pg_api_orders_table']} po
    LEFT JOIN {$g5['api_key_table']} ak ON po.ak_id = ak.ak_id
    WHERE po.po_id = '" . sql_real_escape_string($po_id) . "'
";

$order = sql_fetch($sql);

if (!$order) {
    echo '<p style="color:red;">주문을 찾을 수 없습니다.</p>';
    exit;
}

// 주문 상세 정보 조회
$detail_sql = "
    SELECT *
    FROM {$g5['prefix']}shop_order_detail
    WHERE od_id = '" . sql_real_escape_string($order['po_g5_order_id']) . "'
";

$detail_result = sql_query($detail_sql);

$order_details = array();
while ($detail_row = sql_fetch_array($detail_result)) {
    $order_details[] = $detail_row;
}

// 쇼핑몰 주문 정보 조회
$shop_order_sql = "
    SELECT *
    FROM {$g5['prefix']}shop_order
    WHERE od_id = '" . sql_real_escape_string($order['po_g5_order_id']) . "'
";

$shop_order = sql_fetch($shop_order_sql);
?>

<table class="table table-bordered">
    <thead>
        <tr>
            <th style="width:30%;">항목</th>
            <th>내용</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <th>PG 주문 ID</th>
            <td><?php echo $order['po_id']; ?></td>
        </tr>
        <tr>
            <th>API 키</th>
            <td><?php echo htmlspecialchars($order['ak_name']); ?></td>
        </tr>
        <tr>
            <th>외부 주문 ID</th>
            <td><?php echo htmlspecialchars($order['po_external_order_id']); ?></td>
        </tr>
        <tr>
            <th>그누보드 주문 ID</th>
            <td><?php echo htmlspecialchars($order['po_g5_order_id']); ?></td>
        </tr>
        <tr>
            <th>상품명</th>
            <td><?php echo htmlspecialchars($order['po_item_name']); ?></td>
        </tr>
        <tr>
            <th>금액</th>
            <td><?php echo number_format($order['po_amount']); ?>원</td>
        </tr>
        <tr>
            <th>상태</th>
            <td>
                <?php
                $status_class = '';
                switch ($order['po_status']) {
                    case 'pending':
                        $status_class = 'color:orange';
                        break;
                    case 'paid':
                        $status_class = 'color:green';
                        break;
                    case 'cancelled':
                        $status_class = 'color:gray';
                        break;
                    case 'failed':
                        $status_class = 'color:red';
                        break;
                    case 'refunded':
                        $status_class = 'color:purple';
                        break;
                }
                ?>
                <strong style="<?php echo $status_class; ?>"><?php echo strtoupper(htmlspecialchars($order['po_status'])); ?></strong>
            </td>
        </tr>
        <tr>
            <th>PG 사</th>
            <td><?php echo strtoupper(htmlspecialchars($order['po_pg_code'])); ?></td>
        </tr>
        <tr>
            <th>PG TID</th>
            <td><?php echo htmlspecialchars($order['po_pg_tid']); ?></td>
        </tr>
        <tr>
            <th>콜백 URL</th>
            <td><?php echo htmlspecialchars($order['po_callback_url']); ?></td>
        </tr>
        <tr>
            <th>커스텀 필드</th>
            <td>
                <pre style="background:#f5f5f5; padding:10px; border:1px solid #ddd; max-height:300px; overflow:auto;"><?php echo htmlspecialchars($order['po_custom_fields']); ?></pre>
            </td>
        </tr>
        <tr>
            <th>PG 응답</th>
            <td>
                <pre style="background:#f5f5f5; padding:10px; border:1px solid #ddd; max-height:300px; overflow:auto;"><?php echo htmlspecialchars($order['po_pg_response']); ?></pre>
            </td>
        </tr>
        <tr>
            <th>생성일</th>
            <td><?php echo $order['po_created_at']; ?></td>
        </tr>
        <tr>
            <th>수정일</th>
            <td><?php echo $order['po_updated_at']; ?></td>
        </tr>
    </tbody>
</table>

<h4>주문 상세</h4>

<?php if (!empty($order_details)) { ?>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>상품명</th>
                <th>옵션</th>
                <th>수량</th>
                <th>금액</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($order_details as $detail) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($detail['it_name']); ?></td>
                    <td><?php echo htmlspecialchars($detail['ct_option']); ?></td>
                    <td><?php echo number_format($detail['ct_qty']); ?></td>
                    <td><?php echo number_format($detail['ct_amount']); ?>원</td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
<?php } else { ?>
    <p>주문 상세 정보가 없습니다.</p>
<?php } ?>

<?php if ($shop_order) { ?>
    <h4>쇼핑몰 주문 정보</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th style="width:30%;">항목</th>
                <th>내용</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <th>수신자</th>
                <td><?php echo htmlspecialchars($shop_order['od_name']); ?></td>
            </tr>
            <tr>
                <th>연락처</th>
                <td><?php echo htmlspecialchars($shop_order['od_tel']); ?></td>
            </tr>
            <tr>
                <th>주소</th>
                <td>
                    (<?php echo $shop_order['od_zip1']; ?>-<?php echo $shop_order['od_zip2']; ?>)<br>
                    <?php echo htmlspecialchars($shop_order['od_addr1']); ?><br>
                    <?php echo htmlspecialchars($shop_order['od_addr2']); ?> <?php echo htmlspecialchars($shop_order['od_addr3']); ?>
                </td>
            </tr>
            <tr>
                <th>주문 상태</th>
                <td><?php echo htmlspecialchars($shop_order['od_status']); ?></td>
            </tr>
            <tr>
                <th>결제 시간</th>
                <td><?php echo $shop_order['od_receipt_time']; ?></td>
            </tr>
            <tr>
                <th>결제 금액</th>
                <td><?php echo number_format($shop_order['od_receipt_price']); ?>원</td>
            </tr>
            <tr>
                <th>미수금</th>
                <td><?php echo number_format($shop_order['od_misu']); ?>원</td>
            </tr>
            <tr>
                <th>메모</th>
                <td><?php echo nl2br(htmlspecialchars($shop_order['od_shop_memo'])); ?></td>
            </tr>
        </tbody>
    </table>
<?php } ?>
