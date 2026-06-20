<?php
/**
 * SNS 로그인 오류 로그 페이지
 * SNS 로그인 API 오류 로그를 조회하는 기능 제공
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 관리자 공통 파일 포함
include_once './../_common.php';

// 검색 조건
$ak_id = isset($_GET['ak_id']) ? (int)$_GET['ak_id'] : 0;
$provider = isset($_GET['provider']) ? trim($_GET['provider']) : '';
$error_code = isset($_GET['error_code']) ? trim($_GET['error_code']) : '';
$fr_date = isset($_GET['fr_date']) ? trim($_GET['fr_date']) : '';
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

// WHERE 조건 구성
$where = array();
$where[] = "sl.sl_success = 0"; // 실패한 로그만

if ($ak_id) {
    $where[] = "sl.ak_id = '" . sql_real_escape_string($ak_id) . "'";
}
if ($provider) {
    $where[] = "sl.sl_provider = '" . sql_real_escape_string($provider) . "'";
}
if ($error_code) {
    $where[] = "sl.sl_error_code LIKE '%" . sql_real_escape_string($error_code) . "%'";
}
if ($fr_date) {
    $where[] = "sl.sl_created_at >= '" . sql_real_escape_string($fr_date) . " 00:00:00'";
}
if ($to_date) {
    $where[] = "sl.sl_created_at <= '" . sql_real_escape_string($to_date) . " 23:59:59'";
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

// 페이징 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// 총 개수 조회
$sql = "SELECT COUNT(*) as cnt FROM {$g5['sns_api_log_table']} sl $where_sql";
$count_result = sql_fetch($sql);
$total_count = $count_result ? (int)$count_result['cnt'] : 0;
$total_pages = $total_count > 0 ? ceil($total_count / $limit) : 1;

// 로그 목록 조회
$sql = "
    SELECT
        sl.*,
        ak.ak_name
    FROM {$g5['sns_api_log_table']} sl
    LEFT JOIN {$g5['api_key_table']} ak ON sl.ak_id = ak.ak_id
    $where_sql
    ORDER BY sl.sl_id DESC
    LIMIT $limit OFFSET $offset
";

$result = sql_query($sql);
if (!$result) {
    $result = false;
    $total_count = 0;
}

// 에러 코드별 통계 조회
$error_stats_sql = "
    SELECT
        sl_error_code,
        COUNT(*) as cnt
    FROM {$g5['sns_api_log_table']} sl
    $where_sql
    GROUP BY sl_error_code
    ORDER BY cnt DESC
    LIMIT 20
";
$error_stats_result = sql_query($error_stats_sql);
if (!$error_stats_result) {
    $error_stats_result = false;
}

include_once G5_ADMIN_PATH . '/admin.head.php';
?>

<div class="local_ov01 local_ov">
    <a href="./../keylist.php" class="ov_list">API 키 목록</a>
    <a href="./loginlog.php" class="ov_list">SNS 로그인 로그</a>
    <span class="btn_ov01"><span class="ov_txt">총 오류</span><span class="ov_num"> <?php echo number_format($total_count); ?>건</span></span>
</div>

<form name="fsearch" id="fsearch" method="get" class="local_sch01 local_sch">
    <label for="ak_id" class="sound_only">API 키</label>
    <select name="ak_id" id="ak_id">
        <option value="">전체</option>
        <?php
        $ak_result = sql_query("SELECT ak_id, ak_name FROM {$g5['api_key_table']} ORDER BY ak_id DESC");
        while ($ak_row = sql_fetch_array($ak_result)) {
            ?>
            <option value="<?php echo $ak_row['ak_id']; ?>" <?php echo $ak_id == $ak_row['ak_id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($ak_row['ak_name']); ?>
            </option>
            <?php
        }
        ?>
    </select>
    
    <label for="provider" class="sound_only">SNS 제공자</label>
    <select name="provider" id="provider">
        <option value="">전체</option>
        <option value="naver" <?php echo $provider === 'naver' ? 'selected' : ''; ?>>네이버</option>
        <option value="kakao" <?php echo $provider === 'kakao' ? 'selected' : ''; ?>>카카오</option>
        <option value="google" <?php echo $provider === 'google' ? 'selected' : ''; ?>>구글</option>
        <option value="facebook" <?php echo $provider === 'facebook' ? 'selected' : ''; ?>>페이스북</option>
        <option value="twitter" <?php echo $provider === 'twitter' ? 'selected' : ''; ?>>트위터</option>
        <option value="payco" <?php echo $provider === 'payco' ? 'selected' : ''; ?>>페이코</option>
    </select>
    
    <label for="error_code" class="sound_only">에러 코드</label>
    <input type="text" name="error_code" id="error_code" value="<?php echo htmlspecialchars($error_code); ?>" class="frm_input" size="20" placeholder="에러 코드">
    
    <label for="fr_date" class="sound_only">시작일</label>
    <input type="text" name="fr_date" id="fr_date" value="<?php echo htmlspecialchars($fr_date); ?>" class="frm_input" size="10" placeholder="YYYY-MM-DD" autocomplete="off">
    
    <label for="to_date" class="sound_only">종료일</label>
    <input type="text" name="to_date" id="to_date" value="<?php echo htmlspecialchars($to_date); ?>" class="frm_input" size="10" placeholder="YYYY-MM-DD" autocomplete="off">
    
    <input type="submit" value="검색" class="btn_submit">
</form>

<div class="local_desc02 local_desc">
    <h4>에러 코드별 통계 (Top 20)</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>에러 코드</th>
                <th>건수</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $has_error_stats = false;
            if ($error_stats_result) {
                while ($error_stat = sql_fetch_array($error_stats_result)) {
                    $has_error_stats = true;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($error_stat['sl_error_code']); ?></td>
                        <td><?php echo number_format($error_stat['cnt']); ?></td>
                    </tr>
                    <?php
                }
            }
            if (!$has_error_stats) {
                ?>
                <tr>
                    <td colspan="2" class="empty_table">데이터가 없습니다.</td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>

<div class="tbl_head01 tbl_wrap">
    <table>
        <caption>SNS 로그인 오류 로그 목록</caption>
        <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">API 키</th>
                <th scope="col">SNS 제공자</th>
                <th scope="col">액션</th>
                <th scope="col">회원 ID</th>
                <th scope="col">식별자</th>
                <th scope="col">에러 코드</th>
                <th scope="col">에러 메시지</th>
                <th scope="col">생성일</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result && sql_num_rows($result) > 0) {
                while ($row = sql_fetch_array($result)) {
                    ?>
                    <tr>
                        <td class="td_num"><?php echo $row['sl_id']; ?></td>
                        <td><?php echo htmlspecialchars($row['ak_name']); ?></td>
                        <td><?php echo strtoupper(htmlspecialchars($row['sl_provider'])); ?></td>
                        <td><?php echo htmlspecialchars($row['sl_action']); ?></td>
                        <td><?php echo htmlspecialchars($row['sl_mb_id']); ?></td>
                        <td><?php echo htmlspecialchars(substr($row['sl_identifier'], 0, 30)) . (strlen($row['sl_identifier']) > 30 ? '...' : ''); ?></td>
                        <td><code style="color:red;"><?php echo htmlspecialchars($row['sl_error_code']); ?></code></td>
                        <td><?php echo htmlspecialchars($row['sl_error_message']); ?></td>
                        <td><?php echo $row['sl_created_at']; ?></td>
                    </tr>
                    <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="9" class="empty_table">데이터가 없습니다.</td>
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
