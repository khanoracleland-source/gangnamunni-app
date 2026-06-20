<?php
/**
 * SNS 로그인 통계 페이지
 * SNS 로그인 API 통계 정보를 제공하는 기능
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
$where[] = "sl.sl_created_at >= '" . sql_real_escape_string($fr_date) . " 00:00:00'";
$where[] = "sl.sl_created_at <= '" . sql_real_escape_string($to_date) . " 23:59:59'";

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

// 기간별 총 요청 수
foreach ($period_stats as $key => $period) {
    $period_where = "WHERE sl.sl_created_at >= '" . sql_real_escape_string($period['fr_date']) . " 00:00:00' AND sl.sl_created_at <= '" . sql_real_escape_string($period['to_date']) . " 23:59:59'";

    $sql = "SELECT COUNT(*) as cnt FROM {$g5['sns_api_log_table']} sl $period_where";
    $total_result = sql_fetch($sql);
    $period_stats[$key]['total'] = $total_result ? (int)$total_result['cnt'] : 0;

    $sql = "SELECT COUNT(*) as cnt FROM {$g5['sns_api_log_table']} sl $period_where AND sl.sl_success = 1";
    $success_result = sql_fetch($sql);
    $period_stats[$key]['success'] = $success_result ? (int)$success_result['cnt'] : 0;

    $sql = "SELECT COUNT(*) as cnt FROM {$g5['sns_api_log_table']} sl $period_where AND sl.sl_success = 0";
    $failure_result = sql_fetch($sql);
    $period_stats[$key]['failure'] = $failure_result ? (int)$failure_result['cnt'] : 0;
}

// 제공자별 통계
$provider_stats_sql = "
    SELECT
        sl_provider,
        sl_action,
        sl_success,
        COUNT(*) as cnt
    FROM {$g5['sns_api_log_table']} sl
    $where_sql
    GROUP BY sl_provider, sl_action, sl_success
    ORDER BY sl_provider, sl_action, sl_success
";
$provider_stats_result = sql_query($provider_stats_sql);

// 제공자별 통계 데이터 구성
$provider_stats = array();
$providers = array('naver', 'kakao', 'google', 'facebook', 'twitter', 'payco');
$actions = array('login', 'register', 'error');

foreach ($providers as $provider) {
    $provider_stats[$provider] = array();
    foreach ($actions as $action) {
        $provider_stats[$provider][$action]['success'] = 0;
        $provider_stats[$provider][$action]['failure'] = 0;
    }
}

if ($provider_stats_result) {
    while ($stat = sql_fetch_array($provider_stats_result)) {
        $provider = $stat['sl_provider'];
        $action = $stat['sl_action'];
        $success = $stat['sl_success'] ? 'success' : 'failure';

        if (isset($provider_stats[$provider])) {
            $provider_stats[$provider][$action][$success] = $stat['cnt'];
        }
    }
}

// 시간별 통계 (최근 7일)
$daily_stats_sql = "
    SELECT
        DATE(sl.sl_created_at) as date,
        COUNT(*) as cnt,
        SUM(CASE WHEN sl.sl_success = 1 THEN 1 ELSE 0 END) as success_cnt,
        SUM(CASE WHEN sl.sl_success = 0 THEN 1 ELSE 0 END) as failure_cnt
    FROM {$g5['sns_api_log_table']} sl
    WHERE sl.sl_created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(sl.sl_created_at)
    ORDER BY date DESC
";
$daily_stats_result = sql_query($daily_stats_sql);

$daily_stats = array();
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $daily_stats[$date] = array(
        'date' => $date,
        'total' => 0,
        'success' => 0,
        'failure' => 0
    );
}

if ($daily_stats_result) {
    while ($stat = sql_fetch_array($daily_stats_result)) {
        $date = $stat['date'];
        if (isset($daily_stats[$date])) {
            $daily_stats[$date]['total'] = $stat['cnt'];
            $daily_stats[$date]['success'] = $stat['success_cnt'];
            $daily_stats[$date]['failure'] = $stat['failure_cnt'];
        }
    }
}

// API 키별 통계
$api_key_stats_sql = "
    SELECT
        ak_id,
        ak_name,
        COUNT(*) as cnt,
        SUM(CASE WHEN sl.sl_success = 1 THEN 1 ELSE 0 END) as success_cnt,
        SUM(CASE WHEN sl.sl_success = 0 THEN 1 ELSE 0 END) as failure_cnt
    FROM {$g5['sns_api_log_table']} sl
    LEFT JOIN {$g5['api_key_table']} ak ON sl.ak_id = ak.ak_id
    $where_sql
    GROUP BY sl.ak_id, ak.ak_name
    ORDER BY cnt DESC
    LIMIT 10
";
$api_key_stats_result = sql_query($api_key_stats_sql);

// 최근 에러 로그
$recent_errors_sql = "
    SELECT
        sl.*,
        ak.ak_name
    FROM {$g5['sns_api_log_table']} sl
    LEFT JOIN {$g5['api_key_table']} ak ON sl.ak_id = ak.ak_id
    WHERE sl.sl_success = 0
    ORDER BY sl.sl_id DESC
    LIMIT 10
";
$recent_errors_result = sql_query($recent_errors_sql);

include_once G5_ADMIN_PATH . '/admin.head.php';
?>

<div class="local_ov01 local_ov">
    <a href="./../keylist.php" class="ov_list">API 키 목록</a>
    <a href="./loginlog.php" class="ov_list">SNS 로그인 로그</a>
    <a href="./errorlog.php" class="ov_list">오류 로그</a>
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
                <th>총 요청</th>
                <th>성공</th>
                <th>실패</th>
                <th>성공률</th>
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
                $success_rate = $period['total'] > 0 ? round(($period['success'] / $period['total']) * 100, 2) : 0;
                ?>
                <tr>
                    <td><?php echo $period_names[$key]; ?></td>
                    <td><?php echo number_format($period['total']); ?></td>
                    <td style="color:green;"><?php echo number_format($period['success']); ?></td>
                    <td style="color:red;"><?php echo number_format($period['failure']); ?></td>
                    <td><?php echo $success_rate; ?>%</td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>

<h2>SNS 제공자별 통계 (<?php echo $fr_date; ?> ~ <?php echo $to_date; ?>)</h2>
<div class="tbl_wrap tbl_wrap01">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>SNS 제공자</th>
                <th>로그인</th>
                <th></th>
                <th>회원가입</th>
                <th></th>
                <th>에러</th>
                <th></th>
                <th>합계</th>
            </tr>
            <tr>
                <th></th>
                <th>성공</th>
                <th>실패</th>
                <th>성공</th>
                <th>실패</th>
                <th>성공</th>
                <th>실패</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($providers as $provider) {
                $total_success = 0;
                $total_failure = 0;
                
                foreach ($actions as $action) {
                    $total_success += $provider_stats[$provider][$action]['success'];
                    $total_failure += $provider_stats[$provider][$action]['failure'];
                }
                ?>
                <tr>
                    <td><?php echo strtoupper(htmlspecialchars($provider)); ?></td>
                    <td style="color:green;"><?php echo number_format($provider_stats[$provider]['login']['success']); ?></td>
                    <td style="color:red;"><?php echo number_format($provider_stats[$provider]['login']['failure']); ?></td>
                    <td style="color:green;"><?php echo number_format($provider_stats[$provider]['register']['success']); ?></td>
                    <td style="color:red;"><?php echo number_format($provider_stats[$provider]['register']['failure']); ?></td>
                    <td style="color:green;">-</td>
                    <td style="color:red;"><?php echo number_format($provider_stats[$provider]['error']['failure']); ?></td>
                    <td><strong><?php echo number_format($total_success + $total_failure); ?></strong></td>
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
                <th>총 요청</th>
                <th>성공</th>
                <th>실패</th>
                <th>성공률</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach ($daily_stats as $date => $stat) {
                $success_rate = $stat['total'] > 0 ? round(($stat['success'] / $stat['total']) * 100, 2) : 0;
                ?>
                <tr>
                    <td><?php echo $date; ?></td>
                    <td><?php echo number_format($stat['total']); ?></td>
                    <td style="color:green;"><?php echo number_format($stat['success']); ?></td>
                    <td style="color:red;"><?php echo number_format($stat['failure']); ?></td>
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
                <th>총 요청</th>
                <th>성공</th>
                <th>실패</th>
                <th>성공률</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($api_key_stats_result && sql_num_rows($api_key_stats_result) > 0) {
                while ($stat = sql_fetch_array($api_key_stats_result)) {
                    $success_rate = $stat['cnt'] > 0 ? round(($stat['success_cnt'] / $stat['cnt']) * 100, 2) : 0;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($stat['ak_name']); ?></td>
                        <td><?php echo number_format($stat['cnt']); ?></td>
                        <td style="color:green;"><?php echo number_format($stat['success_cnt']); ?></td>
                        <td style="color:red;"><?php echo number_format($stat['failure_cnt']); ?></td>
                        <td><?php echo $success_rate; ?>%</td>
                    </tr>
                    <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="5" class="empty_table">데이터가 없습니다.</td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>

<h2>최근 에러 로그 (Top 10)</h2>
<div class="tbl_wrap tbl_wrap01">
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>생성일</th>
                <th>API 키</th>
                <th>SNS 제공자</th>
                <th>액션</th>
                <th>에러 코드</th>
                <th>에러 메시지</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($recent_errors_result && sql_num_rows($recent_errors_result) > 0) {
                while ($error = sql_fetch_array($recent_errors_result)) {
                    ?>
                    <tr>
                        <td><?php echo $error['sl_created_at']; ?></td>
                        <td><?php echo htmlspecialchars($error['ak_name']); ?></td>
                        <td><?php echo strtoupper(htmlspecialchars($error['sl_provider'])); ?></td>
                        <td><?php echo htmlspecialchars($error['sl_action']); ?></td>
                        <td><code style="color:red;"><?php echo htmlspecialchars($error['sl_error_code']); ?></code></td>
                        <td><?php echo htmlspecialchars($error['sl_error_message']); ?></td>
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

<?php
include_once G5_ADMIN_PATH . '/admin.tail.php';
?>
