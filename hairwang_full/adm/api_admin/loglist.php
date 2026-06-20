<?php
/**
 * API 로그 조회 페이지
 * API 요청 로그를 조회하는 기능 제공
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 관리자 공통 파일 포함
include_once './_common.php';

// 검색 조건
$ak_id = isset($_GET['ak_id']) ? (int)$_GET['ak_id'] : 0;
$endpoint = isset($_GET['endpoint']) ? trim($_GET['endpoint']) : '';
$method = isset($_GET['method']) ? trim($_GET['method']) : '';
$response_code = isset($_GET['response_code']) ? trim($_GET['response_code']) : '';
$fr_date = isset($_GET['fr_date']) ? trim($_GET['fr_date']) : '';
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

// WHERE 조건 구성
$where = array();
if ($ak_id) {
    $where[] = "al.ak_id = '" . sql_real_escape_string($ak_id) . "'";
}
if ($endpoint) {
    $where[] = "al.al_endpoint LIKE '%" . sql_real_escape_string($endpoint) . "%'";
}
if ($method) {
    $where[] = "al.al_method = '" . sql_real_escape_string($method) . "'";
}
if ($response_code) {
    $where[] = "al.al_response_code = '" . sql_real_escape_string($response_code) . "'";
}
if ($fr_date) {
    $where[] = "al.al_created_at >= '" . sql_real_escape_string($fr_date) . " 00:00:00'";
}
if ($to_date) {
    $where[] = "al.al_created_at <= '" . sql_real_escape_string($to_date) . " 23:59:59'";
}

$where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// 페이징 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// 총 개수 조회
$sql = "SELECT COUNT(*) as cnt FROM {$g5['api_log_table']} al $where_sql";
$total_count = sql_fetch($sql)['cnt'];
$total_pages = ceil($total_count / $limit);

// 로그 목록 조회
$sql = "
    SELECT
        al.*,
        ak.ak_name,
        ak.ak_key
    FROM {$g5['api_log_table']} al
    LEFT JOIN {$g5['api_key_table']} ak ON al.ak_id = ak.ak_id
    $where_sql
    ORDER BY al.al_id DESC
    LIMIT $limit OFFSET $offset
";

$result = sql_query($sql);

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
    
    <label for="endpoint" class="sound_only">엔드포인트</label>
    <input type="text" name="endpoint" id="endpoint" value="<?php echo htmlspecialchars($endpoint); ?>" class="frm_input" size="30" placeholder="엔드포인트">
    
    <label for="method" class="sound_only">메서드</label>
    <select name="method" id="method">
        <option value="">전체</option>
        <option value="GET" <?php echo $method === 'GET' ? 'selected' : ''; ?>>GET</option>
        <option value="POST" <?php echo $method === 'POST' ? 'selected' : ''; ?>>POST</option>
        <option value="PUT" <?php echo $method === 'PUT' ? 'selected' : ''; ?>>PUT</option>
        <option value="DELETE" <?php echo $method === 'DELETE' ? 'selected' : ''; ?>>DELETE</option>
    </select>
    
    <label for="response_code" class="sound_only">응답 코드</label>
    <input type="text" name="response_code" id="response_code" value="<?php echo htmlspecialchars($response_code); ?>" class="frm_input" size="10" placeholder="200">
    
    <label for="fr_date" class="sound_only">시작일</label>
    <input type="text" name="fr_date" id="fr_date" value="<?php echo htmlspecialchars($fr_date); ?>" class="frm_input" size="10" placeholder="YYYY-MM-DD" autocomplete="off">
    
    <label for="to_date" class="sound_only">종료일</label>
    <input type="text" name="to_date" id="to_date" value="<?php echo htmlspecialchars($to_date); ?>" class="frm_input" size="10" placeholder="YYYY-MM-DD" autocomplete="off">
    
    <input type="submit" value="검색" class="btn_submit">
</form>

<div class="tbl_head01 tbl_wrap">
    <table>
        <caption>API 로그 목록</caption>
        <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">API 키</th>
                <th scope="col">엔드포인트</th>
                <th scope="col">메서드</th>
                <th scope="col">요청 IP</th>
                <th scope="col">응답 코드</th>
                <th scope="col">실행 시간</th>
                <th scope="col">생성일</th>
                <th scope="col">상세</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (sql_num_rows($result) > 0) {
                while ($row = sql_fetch_array($result)) {
                    ?>
                    <tr>
                        <td class="td_num"><?php echo $row['al_id']; ?></td>
                        <td>
                            <?php echo htmlspecialchars($row['ak_name']); ?>
                            <?php if ($row['ak_key']) { ?>
                                <br><small><code><?php echo htmlspecialchars($row['ak_key']); ?></code></small>
                            <?php } ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['al_endpoint']); ?></td>
                        <td>
                            <?php
                            $method_class = '';
                            switch ($row['al_method']) {
                                case 'GET':
                                    $method_class = 'color:green';
                                    break;
                                case 'POST':
                                    $method_class = 'color:blue';
                                    break;
                                case 'PUT':
                                    $method_class = 'color:orange';
                                    break;
                                case 'DELETE':
                                    $method_class = 'color:red';
                                    break;
                            }
                            ?>
                            <strong style="<?php echo $method_class; ?>"><?php echo htmlspecialchars($row['al_method']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($row['al_request_ip']); ?></td>
                        <td>
                            <?php if ($row['al_response_code'] == 200) { ?>
                                <span style="color:green"><?php echo $row['al_response_code']; ?></span>
                            <?php } else { ?>
                                <span style="color:red"><?php echo $row['al_response_code']; ?></span>
                            <?php } ?>
                        </td>
                        <td><?php echo $row['al_execution_time'] ? number_format($row['al_execution_time'], 4) . '초' : '-'; ?></td>
                        <td><?php echo $row['al_created_at']; ?></td>
                        <td class="td_mng">
                            <button type="button" class="btn btn_03" onclick="show_detail(<?php echo $row['al_id']; ?>);">상세</button>
                        </td>
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

<div id="log_detail" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); background:white; padding:20px; border-radius:5px; max-width:80%; max-height:80%; overflow:auto;">
        <h3>API 로그 상세</h3>
        <div id="log_detail_content"></div>
        <button type="button" class="btn btn_02" onclick="close_detail();">닫기</button>
    </div>
</div>

<script>
function show_detail(al_id) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', './logdetail.php?al_id=' + al_id, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            document.getElementById('log_detail_content').innerHTML = xhr.responseText;
            document.getElementById('log_detail').style.display = 'block';
        }
    };
    xhr.send();
}

function close_detail() {
    document.getElementById('log_detail').style.display = 'none';
}
</script>

<?php
include_once G5_ADMIN_PATH . '/admin.tail.php';
?>
