<?php
/**
 * PG 결제 통계 페이지
 * PG 결제 API 통계 정보를 제공하는 기능
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 관리자 공통 파일 포함
include_once './../_common.php';

// 기간 설정
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$this_month = date('Y-m');
$last_month = date('Y-m', strtotime('-1 month'));
$last_7_days = date('Y-m-d', strtotime('-7 days'));
$last_30_days = date('Y-m-d', strtotime('-30 days'));

// 기본 검색 기간
$fr_date = isset($_GET['fr_date']) ? trim($_GET['fr_date']) : $last_30_days;
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : $today;

// WHERE 조건 구성
$where = array();
$where[] = "po.po_created_at >= '" . sql_real_escape_string($fr_date) . " 00:00:00'";
$where[] = "po.po_created_at <= '" . sql_real_escape_string($to_date) . " 23:59:59'";

$where_sql = 'WHERE ' . implode(' AND ', $where);

// 기간별 통계
$period_stats = array(
    'today' => array(
        'fr_date' => $today,
        'to_date' => $today
    ),
    'yesterday' => array(
        'fr_date' => $yesterday,
        'to_date' => $yesterday
    ),
    'this_month' => array(
        'fr_date' => $this_month . '-01',
        'to_date' => $to_date
    ),
    'last_month' => array(
        'fr_date' => $last_month . '-01',
        'to_date' => date('Y-m-t', strtotime($last_month . '-01'))
    )
);

// 기간별 총 주문 수 및 금액
foreach ($period_stats as $key => $period) {
    $period_where = "WHERE po.po_created_at >= '" . sql_real_escape_string($period['fr_date']) . " 00:00:00' AND po.po_created_at <= '" . sql_real_escape_string($period['to_date']) . " 23:59:59'";

    $sql = "SELECT COUNT(*) as cnt FROM {$g5['pg_api_orders_table']} po $period_where";
    $total_result = sql_fetch($sql);
    $period_stats[$key]['total'] = $total_result ? (int)$total_result['cnt'] : 0;

    $sql = "SELECT COUNT(*) as cnt FROM {$g5['pg_api_orders_table']} po $period_where AND po.po_status = 'paid'";
    $paid_result = sql_fetch($sql);
    $period_stats[$key]['paid'] = $paid_result ? (int)$paid_result['cnt'] : 0;

    $sql = "SELECT COUNT(*) as cnt FROM {$g5['pg_api_orders_table']} po $period_where AND po.po_status = 'failed'";
    $failed_result = sql_fetch($sql);
    $period_stats[$key]['failed'] = $failed_result ? (int)$failed_result['cnt'] : 0;

    $sql = "SELECT SUM(po_amount) as amount FROM {$g5['pg_api_orders_table']} po $period_where AND po.po_status = 'paid'";
    $result = sql_fetch($sql);
    $period_stats[$key]['amount'] = ($result && isset($result['amount'])) ? (int)$result['amount'] : 0;
}

// PG별 통계
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

// PG별 통계 데이터 구성
$pg_stats = array();
$pg_codes = array('inicis', 'lg', 'kcp', 'kakaopay', 'nicepay');
$statuses = array('pending', 'paid', 'cancelled', 'failed', 'refunded');

foreach ($pg_codes as $pg_code) {
    $pg_stats[$pg_code] = array();
    foreach ($statuses as $status) {
        $pg_stats[$pg_code][$status]['cnt'] = 0;
        $pg_stats[$pg_code][$status]['amount'] = 0;
    }
}

if ($pg_stats_result && sql_num_rows($pg_stats_result) > 0) {
    while ($stat = sql_fetch_array($pg_stats_result)) {
        $pg_code = $stat['po_pg_code'];
        $status = $stat['po_status'];

        if (isset($pg_stats[$pg_code])) {
            $pg_stats[$pg_code][$status]['cnt'] = $stat['cnt'];
            $pg_stats[$pg_code][$status]['amount'] = $stat['total_amount'];
        }
    }
}

// 시간별 통계 (최근 7일)
$daily_stats_sql = "
    SELECT
        DATE(po.po_created_at) as date,
        COUNT(*) as cnt,
        SUM(CASE WHEN po.po_status = 'paid' THEN 1 ELSE 0 END) as paid_cnt,
        SUM(CASE WHEN po.po_status = 'paid' THEN po_amount ELSE 0 END) as paid_amount,
        SUM(CASE WHEN po.po_status = 'failed' THEN 1 ELSE 0 END) as failed_cnt
    FROM {$g5['pg_api_orders_table']} po
    WHERE po.po_created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(po.po_created_at)
    ORDER BY date DESC
";
$daily_stats_result = sql_query($daily_stats_sql);

$daily_stats = array();
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_stats[$date] = array(
        'date' => $date,
        'cnt' => 0,
        'paid_cnt' => 0,
        'paid_amount' => 0,
        'failed_cnt' => 0
    );
}

if ($daily_stats_result && sql_num_rows($daily_stats_result) > 0) {
    while ($stat = sql_fetch_array($daily_stats_result)) {
        $date = $stat['date'];
        if (isset($daily_stats[$date])) {
            $daily_stats[$date]['cnt'] = $stat['cnt'];
            $daily_stats[$date]['paid_cnt'] = $stat['paid_cnt'];
            $daily_stats[$date]['paid_amount'] = $stat['paid_amount'];
            $daily_stats[$date]['failed_cnt'] = $stat['failed_cnt'];
        }
    }
}

// API 키별 통계
$api_key_stats_sql = "
    SELECT
        ak_id,
        ak_name,
        COUNT(*) as cnt,
        SUM(CASE WHEN po.po_status = 'paid' THEN po_amount ELSE 0 END) as paid_amount,
        SUM(CASE WHEN po.po_status = 'paid' THEN 1 ELSE 0 END) as paid_cnt,
        SUM(CASE WHEN po.po_status = 'failed' THEN 1 ELSE 0 END) as failed_cnt
    FROM {$g5['pg_api_orders_table']} po
    LEFT JOIN {$g5['api_key_table']} ak ON po.ak_id = ak.ak_id
    $where_sql
    GROUP BY po.ak_id, ak.ak_name
    ORDER BY cnt DESC
    LIMIT 10
";
$api_key_stats_result = sql_query($api_key_stats_sql);

// 상품별 통계
$item_stats_sql = "
    SELECT
        po_item_name,
        COUNT(*) as cnt,
        SUM(po_amount) as total_amount
    FROM {$g5['pg_api_orders_table']} po
    $where_sql
    GROUP BY po_item_name
    ORDER BY cnt DESC
    LIMIT 10
";
$item_stats_result = sql_query($item_stats_sql);

include_once G5_ADMIN_PATH . '/admin.head.php';
?>

<div class="local_ov01 local_ov">
    <a href="./../keylist.php" class="ov_list">API 키 목록</a>
    <a href="./orderlist.php" class="ov_list">PG 주문 목록</a>
    <a href="./paymentlog.php" class="ov_list">결제 로그</a>
    <span class="btn_ov01"><span class="ov_txt">통계</span></span>
</div>

<form name="fperiod" id="fperiod" method="get" class="local_sch01 local_sch">
    <label for="fr_date" class="sound_only">시작일</label>
    <input type="text" name="fr_date" id="fr_date" value="<?php echo htmlspecialchars($fr_date); ?>" class="frm_input" size="10" placeholder="YYYY-MM-DD" autocomplete="off">
    
    <label for="to_date" class="sound_only">종료일</label>
    <input type="text" name="to_date" id="to_date" value="<?php echo htmlspecialchars($to_date); ?>" class="frm_input" size="10" placeholder="YYYY-MM-DD" autocomplete="off">
    
    <input type="submit" value="조회" class="btn_submit">
</form>

<h2>기간별 통계</h2>
<div class="tbl_wrap tbl_wrap01">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>기간</th>
                <th>총 주문</th>
                <th>결제 완료</th>
                <th>결제 실패</th>
                <th>총 매출</th>
                <th>결제 완료율</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $period_names = array(
                'today' => '오늘',
                'yesterday' => '어제',
                'this_month' => '이번 달',
                'last_month' => '지난 달'
            );
            
            foreach ($period_stats as $key => $period) {
                $success_rate = $period['total'] > 0 ? round(($period['paid'] / $period['total']) * 100, 2) : 0;
                ?>
                <tr>
                    <td><?php echo $period_names[$key]; ?></td>
                    <td><?php echo number_format($period['total']); ?></td>
                    <td style="color:green;"><?php echo number_format($period['paid']); ?></td>
                    <td style="color:red;"><?php echo number_format($period['failed']); ?></td>
                    <td><?php echo number_format($period['amount']); ?>원</td>
                    <td><?php echo $success_rate; ?>%</td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>

<h2>PG별 통계 (<?php echo $fr_date; ?> ~ <?php echo $to_date; ?>)</h2>
<div class="tbl_wrap tbl_wrap01">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>PG 사</th>
                <th>대기</th>
                <th></th>
                <th>결제 완료</th>
                <th></th>
                <th>취소</th>
                <th></th>
                <th>실패</th>
                <th></th>
                <th>환불</th>
                <th></th>
                <th>합계</th>
            </tr>
            <tr>
                <th></th>
                <th>건수</th>
                <th>금액</th>
                <th>건수</th>
                <th>금액</th>
                <th>건수</th>
                <th>금액</th>
                <th>건수</th>
                <th>금액</th>
                <th>건수</th>
                <th>금액</th>
                <th>건수</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($pg_codes as $pg_code) {
                $total_cnt = 0;
                $total_amount = 0;
                
                foreach ($statuses as $status) {
                    $total_cnt += $pg_stats[$pg_code][$status]['cnt'];
                    $total_amount += $pg_stats[$pg_code][$status]['amount'];
                }
                ?>
                <tr>
                    <td><?php echo strtoupper(htmlspecialchars($pg_code)); ?></td>
                    <td><?php echo number_format($pg_stats[$pg_code]['pending']['cnt']); ?></td>
                    <td><?php echo number_format($pg_stats[$pg_code]['pending']['amount']); ?>원</td>
                    <td style="color:green;"><?php echo number_format($pg_stats[$pg_code]['paid']['cnt']); ?></td>
                    <td style="color:green;"><?php echo number_format($pg_stats[$pg_code]['paid']['amount']); ?>원</td>
                    <td style="color:gray;"><?php echo number_format($pg_stats[$pg_code]['cancelled']['cnt']); ?></td>
                    <td style="color:gray;"><?php echo number_format($pg_stats[$pg_code]['cancelled']['amount']); ?>원</td>
                    <td style="color:red;"><?php echo number_format($pg_stats[$pg_code]['failed']['cnt']); ?></td>
                    <td style="color:red;"><?php echo number_format($pg_stats[$pg_code]['failed']['amount']); ?>원</td>
                    <td style="color:purple;"><?php echo number_format($pg_stats[$pg_code]['refunded']['cnt']); ?></td>
                    <td style="color:purple;"><?php echo number_format($pg_stats[$pg_code]['refunded']['amount']); ?>원</td>
                    <td><strong><?php echo number_format($total_cnt); ?></strong></td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>

<h2>일별 통계 (최근 7일)</h2>
<div class="tbl_wrap tbl_wrap01">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>날짜</th>
                <th>총 주문</th>
                <th>결제 완료</th>
                <th>매출</th>
                <th>결제 실패</th>
                <th>결제 완료율</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($daily_stats as $date => $stat) {
                $success_rate = $stat['cnt'] > 0 ? round(($stat['paid_cnt'] / $stat['cnt']) * 100, 2) : 0;
                ?>
                <tr>
                    <td><?php echo $date; ?></td>
                    <td><?php echo number_format($stat['cnt']); ?></td>
                    <td style="color:green;"><?php echo number_format($stat['paid_cnt']); ?></td>
                    <td><?php echo number_format($stat['paid_amount']); ?>원</td>
                    <td style="color:red;"><?php echo number_format($stat['failed_cnt']); ?></td>
                    <td><?php echo $success_rate; ?>%</td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>

<h2>API 키별 통계 (Top 10)</h2>
<div class="tbl_wrap tbl_wrap01">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>API 키</th>
                <th>총 주문</th>
                <th>결제 완료</th>
                <th>매출</th>
                <th>결제 실패</th>
                <th>결제 완료율</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($api_key_stats_result && sql_num_rows($api_key_stats_result) > 0) {
                while ($stat = sql_fetch_array($api_key_stats_result)) {
                    $success_rate = $stat['cnt'] > 0 ? round(($stat['paid_cnt'] / $stat['cnt']) * 100, 2) : 0;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($stat['ak_name']); ?></td>
                        <td><?php echo number_format($stat['cnt']); ?></td>
                        <td style="color:green;"><?php echo number_format($stat['paid_cnt']); ?></td>
                        <td><?php echo number_format($stat['paid_amount']); ?>원</td>
                        <td style="color:red;"><?php echo number_format($stat['failed_cnt']); ?></td>
                        <td><?php echo $success_rate; ?>%</td>
                    </tr>
                    <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="6" class="empty_table">데이터가 없습니다.</td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>

<h2>상품별 통계 (Top 10)</h2>
<div class="tbl_wrap tbl_wrap01">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>상품명</th>
                <th>주문 건수</th>
                <th>총 매출</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($item_stats_result && sql_num_rows($item_stats_result) > 0) {
                while ($stat = sql_fetch_array($item_stats_result)) {
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($stat['po_item_name']); ?></td>
                        <td><?php echo number_format($stat['cnt']); ?></td>
                        <td><?php echo number_format($stat['total_amount']); ?>원</td>
                    </tr>
                    <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="3" class="empty_table">데이터가 없습니다.</td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>

<?php
include_once G5_ADMIN_PATH . '/admin.tail.php';
?>
