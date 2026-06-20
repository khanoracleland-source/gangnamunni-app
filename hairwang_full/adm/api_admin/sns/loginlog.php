<?php
/**
 * SNS 로그인 로그 조회 페이지
 * SNS 로그인 API 로그를 조회하는 기능 제공
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 관리자 공통 파일 포함
include_once './../_common.php';

// 검색 조건
$ak_id = isset($_GET['ak_id']) ? (int)$_GET['ak_id'] : 0;
$provider = isset($_GET['provider']) ? trim($_GET['provider']) : '';
$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$success = isset($_GET['success']) ? trim($_GET['success']) : '';
$mb_id = isset($_GET['mb_id']) ? trim($_GET['mb_id']) : '';
$fr_date = isset($_GET['fr_date']) ? trim($_GET['fr_date']) : '';
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

// WHERE 조건 구성
$where = array();
if ($ak_id) {
    $where[] = "sl.ak_id = '" . sql_real_escape_string($ak_id) . "'";
}
if ($provider) {
    $where[] = "sl.sl_provider = '" . sql_real_escape_string($provider) . "'";
}
if ($action) {
    $where[] = "sl.sl_action = '" . sql_real_escape_string($action) . "'";
}
if ($success !== '') {
    $where[] = "sl.sl_success = '" . sql_real_escape_string($success) . "'";
}
if ($mb_id) {
    $where[] = "sl.sl_mb_id LIKE '%" . sql_real_escape_string($mb_id) . "%'";
}
if ($fr_date) {
    $where[] = "sl.sl_created_at >= '" . sql_real_escape_string($fr_date) . " 00:00:00'";
}
if ($to_date) {
    $where[] = "sl.sl_created_at <= '" . sql_real_escape_string($to_date) . " 23:59:59'";
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

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

// 통계 정보 조회
$stats_sql = "
    SELECT
        sl_provider,
        sl_action,
        sl_success,
        COUNT(*) as cnt
    FROM {$g5['sns_api_log_table']} sl
    $where_sql
    GROUP BY sl_provider, sl_action, sl_success
";
$stats_result = sql_query($stats_sql);
if (!$stats_result) {
    $stats_result = false;
}

include_once G5_ADMIN_PATH . '/admin.head.php';
?>

<div class="local_ov01 local_ov">
    <a href="./keylist.php" class="ov_list">API 키 목록</a>
    <span class="btn_ov01"><span class="ov_txt">총 로그</span><span class="ov_num"> <?php echo number_format($total_count); ?>건</span></span>
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
    
    <label for="action" class="sound_only">액션</label>
    <select name="action" id="action">
        <option value="">전체</option>
        <option value="login" <?php echo $action === 'login' ? 'selected' : ''; ?>>로그인</option>
        <option value="register" <?php echo $action === 'register' ? 'selected' : ''; ?>>회원가입</option>
        <option value="error" <?php echo $action === 'error' ? 'selected' : ''; ?>>에러</option>
    </select>
    
    <label for="success" class="sound_only">성공 여부</label>
    <select name="success" id="success">
        <option value="">전체</option>
        <option value="1" <?php echo $success === '1' ? 'selected' : ''; ?>>성공</option>
        <option value="0" <?php echo $success === '0' ? 'selected' : ''; ?>>실패</option>
    </select>
    
    <label for="mb_id" class="sound_only">회원 ID</label>
    <input type="text" name="mb_id" id="mb_id" value="<?php echo htmlspecialchars($mb_id); ?>" class="frm_input" size="20" placeholder="회원 ID">
    
    <label for="fr_date" class="sound_only">시작일</label>
    <input type="text" name="fr_date" id="fr_date" value="<?php echo htmlspecialchars($fr_date); ?>" class="frm_input" size="10" placeholder="YYYY-MM-DD" autocomplete="off">
    
    <label for="to_date" class="sound_only">종료일</label>
    <input type="text" name="to_date" id="to_date" value="<?php echo htmlspecialchars($to_date); ?>" class="frm_input" size="10" placeholder="YYYY-MM-DD" autocomplete="off">
    
    <input type="submit" value="검색" class="btn_submit">
</form>

<div class="local_desc02 local_desc">
    <h4>통계</h4>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>SNS 제공자</th>
                <th>액션</th>
                <th>성공</th>
                <th>건수</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stats_data = array();
            if ($stats_result) {
                while ($stat = sql_fetch_array($stats_result)) {
                    $stats_data[] = $stat;
                ?>
                <tr>
                    <td><?php echo strtoupper(htmlspecialchars($stat['sl_provider'])); ?></td>
                    <td><?php echo htmlspecialchars($stat['sl_action']); ?></td>
                    <td>
                        <?php if ($stat['sl_success']) { ?>
                            <span style="color:green;">성공</span>
                        <?php } else { ?>
                            <span style="color:red;">실패</span>
                        <?php } ?>
                    </td>
                    <td><?php echo number_format($stat['cnt']); ?></td>
                </tr>
                <?php
                }
            }
            if (empty($stats_data)) {
                ?>
                <tr>
                    <td colspan="4">데이터가 없습니다.</td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>

<div class="tbl_head01 tbl_wrap">
    <table>
        <caption>SNS 로그인 로그 목록</caption>
        <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">API 키</th>
                <th scope="col">SNS 제공자</th>
                <th scope="col">액션</th>
                <th scope="col">회원 ID</th>
                <th scope="col">식별자</th>
                <th scope="col">성공 여부</th>
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
                        <td>
                            <?php if ($row['sl_success']) { ?>
                                <span style="color:green;">성공</span>
                            <?php } else { ?>
                                <span style="color:red;">실패</span>
                            <?php } ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['sl_error_code']); ?></td>
                        <td><?php echo htmlspecialchars($row['sl_error_message']); ?></td>
                        <td><?php echo $row['sl_created_at']; ?></td>
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
