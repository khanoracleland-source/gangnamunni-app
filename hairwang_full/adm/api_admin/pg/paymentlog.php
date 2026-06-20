<?php
/**
 * PG 결제 로그 페이지
 * PG 결제 API 로그를 조회하는 기능 제공
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 관리자 공통 파일 포함
include_once './../_common.php';

// 검색 조건
$ak_id = isset($_GET['ak_id']) ? (int)$_GET['ak_id'] : 0;
$pg_code = isset($_GET['pg_code']) ? trim($_GET['pg_code']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$fr_date = isset($_GET['fr_date']) ? trim($_GET['fr_date']) : '';
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

// WHERE 조건 구성
$where = array();
if ($ak_id) {
    $where[] = "po.ak_id = '" . sql_real_escape_string($ak_id) . "'";
}
if ($pg_code) {
    $where[] = "po.po_pg_code = '" . sql_real_escape_string($pg_code) . "'";
}
if ($status) {
    $where[] = "po.po_status = '" . sql_real_escape_string($status) . "'";
}
if ($fr_date) {
    $where[] = "po.po_created_at >= '" . sql_real_escape_string($fr_date) . " 00:00:00'";
}
if ($to_date) {
    $where[] = "po.po_created_at <= '" . sql_real_escape_string($to_date) . " 23:59:59'";
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// 페이징 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// 총 개수 조회
$sql = "SELECT COUNT(*) as cnt FROM {$g5['pg_api_orders_table']} po $where_sql";
$count_result = sql_fetch($sql);
$total_count = $count_result ? (int)$count_result['cnt'] : 0;
$total_pages = $total_count > 0 ? ceil($total_count / $limit) : 1;

// 결제 로그 목록 조회
$sql = "
    SELECT
        po.*,
        ak.ak_name
    FROM {$g5['pg_api_orders_table']} po
    LEFT JOIN {$g5['api_key_table']} ak ON po.ak_id = ak.ak_id
    $where_sql
    ORDER BY po.po_id DESC
    LIMIT $limit OFFSET $offset
";

$result = sql_query($sql);

// PG별 통계 조회
$pg_stats_sql = "
    SELECT
        po_pg_code,
        po_status,
        COUNT(*) as cnt,
        SUM(po_amount) as total_amount
    FROM {$g5['pg_api_orders_table']} po
    $where_sql
    GROUP BY po_pg_code, po_status
    ORDER BY po_pg_code, po_status
";
$pg_stats_result = sql_query($pg_stats_sql);

include_once G5_ADMIN_PATH . '/admin.head.php';
?>

<div class="local_ov01 local_ov">
    <a href="./../keylist.php" class="ov_list">API 키 목록</a>
    <a href="./orderlist.php" class="ov_list">PG 주문 목록</a>
    <span class="btn_ov01"><span class="ov_txt">총 결제 로그</span><span class="ov_num"> <?php echo number_format($total_count); ?>건</span></span>
</div>

<form name="fsearch" id="fsearch" method="get" class="local_sch01 local_sch">
    <label for="ak_id" class="sound_only">API 키</label>
    <select name="ak_id" id="ak_id">
        <option value="">전체</option>
        <?php
        $ak_result = sql_query("SELECT ak_id, ak_name FROM {$g5['api_key_table']} ORDER BY ak_id DESC");
        if ($ak_result && sql_num_rows($ak_result) > 0) {
            while ($ak_row = sql_fetch_array($ak_result)) {
                ?>
                <option value="<?php echo $ak_row['ak_id']; ?>" <?php echo $ak_id == $ak_row['ak_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($ak_row['ak_name']); ?>
                </option>
                <?php
            }
        }
        ?>
    </select>
    
    <label for="pg_code" class="sound_only">PG 사</label>
    <select name="pg_code" id="pg_code">
        <option value="">전체</option>
        <option value="inicis" <?php echo $pg_code === 'inicis' ? 'selected' : ''; ?>>이니시스</option>
        <option value="lg" <?php echo $pg_code === 'lg' ? 'selected' : ''; ?>>LG U+</option>
        <option value="kcp" <?php echo $pg_code === 'kcp' ? 'selected' : ''; ?>>KCP</option>
        <option value="kakaopay" <?php echo $pg_code === 'kakaopay' ? 'selected' : ''; ?>>카카오페이</option>
        <option value="nicepay" <?php echo $pg_code === 'nicepay' ? 'selected' : ''; ?>>나이스페이</option>
    </select>
    
    <label for="status" class="sound_only">결제 상태</label>
    <select name="status" id="status">
        <option value="">전체</option>
        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>대기</option>
        <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>결제 완료</option>
        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>취소</option>
        <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>실패</option>
        <option value="refunded" <?php echo $status === 'refunded' ? 'selected' : ''; ?>>환불</option>
    </select>
    
    <label for="fr_date" class="sound_only">시작일</label>
    <input type="text" name="fr_date" id="fr_date" value="<?php echo htmlspecialchars($fr_date); ?>" class="frm_input" size="10" placeholder="YYYY-MM-DD" autocomplete="off">
    
    <label for="to_date" class="sound_only">종료일</label>
    <input type="text" name="to_date" id="to_date" value="<?php echo htmlspecialchars($to_date); ?>" class="frm_input" size="10" placeholder="YYYY-MM-DD" autocomplete="off">
    
    <input type="submit" value="검색" class="btn_submit">
</form>

<div class="local_desc02 local_desc">
    <h4>PG별 통계</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>PG 사</th>
                <th>결제 상태</th>
                <th>건수</th>
                <th>총 금액</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $pg_stats_data = array();
            $total_stats_amount = 0;
            if ($pg_stats_result && sql_num_rows($pg_stats_result) > 0) {
                while ($pg_stat = sql_fetch_array($pg_stats_result)) {
                    $pg_stats_data[] = $pg_stat;
                    $total_stats_amount += $pg_stat['total_amount'];
                ?>
                <tr>
                    <td><?php echo strtoupper(htmlspecialchars($pg_stat['po_pg_code'])); ?></td>
                    <td>
                        <?php
                        $status_class = '';
                        switch ($pg_stat['po_status']) {
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
                        <strong style="<?php echo $status_class; ?>"><?php echo strtoupper(htmlspecialchars($pg_stat['po_status'])); ?></strong>
                    </td>
                    <td><?php echo number_format($pg_stat['cnt']); ?></td>
                    <td><?php echo number_format($pg_stat['total_amount']); ?>원</td>
                </tr>
                <?php
                }
            }
            if (empty($pg_stats_data)) {
                ?>
                <tr>
                    <td colspan="4">데이터가 없습니다.</td>
                </tr>
                <?php
            } else {
                ?>
                <tr style="font-weight:bold;">
                    <td colspan="2">합계</td>
                    <td><?php echo number_format(array_sum(array_column($pg_stats_data, 'cnt'))); ?></td>
                    <td><?php echo number_format($total_stats_amount); ?>원</td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>

<div class="tbl_head01 tbl_wrap">
    <table>
        <caption>PG 결제 로그 목록</caption>
        <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">API 키</th>
                <th scope="col">외부 주문 ID</th>
                <th scope="col">그누보드 주문 ID</th>
                <th scope="col">상품명</th>
                <th scope="col">금액</th>
                <th scope="col">상태</th>
                <th scope="col">PG 사</th>
                <th scope="col">PG TID</th>
                <th scope="col">생성일</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result && sql_num_rows($result) > 0) {
                while ($row = sql_fetch_array($result)) {
                    ?>
                    <tr>
                        <td class="td_num"><?php echo $row['po_id']; ?></td>
                        <td><?php echo htmlspecialchars($row['ak_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['po_external_order_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['po_g5_order_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['po_item_name']); ?></td>
                        <td><?php echo number_format($row['po_amount']); ?>원</td>
                        <td>
                            <?php
                            $status_class = '';
                            switch ($row['po_status']) {
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
                            <strong style="<?php echo $status_class; ?>"><?php echo strtoupper(htmlspecialchars($row['po_status'])); ?></strong>
                        </td>
                        <td><?php echo strtoupper(htmlspecialchars($row['po_pg_code'])); ?></td>
                        <td><?php echo htmlspecialchars($row['po_pg_tid']); ?></td>
                        <td><?php echo $row['po_created_at']; ?></td>
                    </tr>
                    <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="10" class="empty_table">데이터가 없습니다.</td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>

<?php
// 페이징
echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_pages, $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
?>

<?php
include_once G5_ADMIN_PATH . '/admin.tail.php';
?>
