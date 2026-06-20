<?php
/**
 * IP 화이트리스트 관리 페이지
 * API 키별 IP 화이트리스트 관리 기능 제공
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 관리자 공통 파일 포함
include_once './_common.php';

// 액션 처리
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

if ($action === 'create') {
    // IP 화이트리스트 추가
    $ak_id = isset($_POST['ak_id']) ? (int)$_POST['ak_id'] : 0;
    $ip = isset($_POST['ip']) ? trim($_POST['ip']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    if ($ak_id === 0) {
        alert('API 키 ID가 유효하지 않습니다.');
    }
    
    if (empty($ip)) {
        alert('IP 주소를 입력해주세요.');
    }
    
    // IP 형식 검증
    if (!filter_var($ip, FILTER_VALIDATE_IP) && !preg_match('/^(\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/', $ip)) {
        alert('올바른 IP 주소 또는 CIDR 형식이 아닙니다.');
    }
    
    // 데이터베이스에 저장
    $sql = "
        INSERT INTO {$g5['api_ip_table']}
        SET ak_id = '" . sql_real_escape_string($ak_id) . "',
            ai_ip = '" . sql_real_escape_string($ip) . "',
            ai_description = '" . sql_real_escape_string($description) . "',
            ai_status = 1,
            ai_created_at = NOW()
    ";
    
    sql_query($sql);
    
    alert('IP가 화이트리스트에 추가되었습니다.', './iplist.php?ak_id=' . $ak_id);
    
} elseif ($action === 'update') {
    // IP 화이트리스트 수정
    $ai_id = isset($_POST['ai_id']) ? (int)$_POST['ai_id'] : 0;
    $ip = isset($_POST['ip']) ? trim($_POST['ip']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 1;
    
    if ($ai_id === 0) {
        alert('IP ID가 유효하지 않습니다.');
    }
    
    if (empty($ip)) {
        alert('IP 주소를 입력해주세요.');
    }
    
    // IP 형식 검증
    if (!filter_var($ip, FILTER_VALIDATE_IP) && !preg_match('/^(\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/', $ip)) {
        alert('올바른 IP 주소 또는 CIDR 형식이 아닙니다.');
    }
    
    // 데이터베이스 업데이트
    $sql = "
        UPDATE {$g5['api_ip_table']}
        SET ai_ip = '" . sql_real_escape_string($ip) . "',
            ai_description = '" . sql_real_escape_string($description) . "',
            ai_status = '" . sql_real_escape_string($status) . "'
        WHERE ai_id = '" . sql_real_escape_string($ai_id) . "'
    ";
    
    sql_query($sql);
    
    alert('IP 화이트리스트가 수정되었습니다.', './iplist.php?ak_id=' . $_POST['ak_id']);
    
} elseif ($action === 'delete') {
    // IP 화이트리스트 삭제
    $ai_id = isset($_POST['ai_id']) ? (int)$_POST['ai_id'] : 0;
    $ak_id = isset($_POST['ak_id']) ? (int)$_POST['ak_id'] : 0;
    
    if ($ai_id === 0) {
        alert('IP ID가 유효하지 않습니다.');
    }
    
    // 삭제
    sql_query("DELETE FROM {$g5['api_ip_table']} WHERE ai_id = '" . sql_real_escape_string($ai_id) . "'");
    
    alert('IP가 화이트리스트에서 삭제되었습니다.', './iplist.php?ak_id=' . $ak_id);
}

// API 키 ID
$ak_id = isset($_GET['ak_id']) ? (int)$_GET['ak_id'] : 0;

if ($ak_id === 0) {
    alert('API 키 ID가 유효하지 않습니다.', './keylist.php');
}

// API 키 정보 조회
$api_key = sql_fetch("SELECT * FROM {$g5['api_key_table']} WHERE ak_id = '" . sql_real_escape_string($ak_id) . "'");

if (!$api_key) {
    alert('API 키를 찾을 수 없습니다.', './keylist.php');
}

// 페이징 설정
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// 총 개수 조회
$sql = "SELECT COUNT(*) as cnt FROM {$g5['api_ip_table']} WHERE ak_id = '" . sql_real_escape_string($ak_id) . "'";
$total_count = sql_fetch($sql)['cnt'];
$total_pages = ceil($total_count / $limit);

// IP 화이트리스트 목록 조회
$sql = "
    SELECT *
    FROM {$g5['api_ip_table']}
    WHERE ak_id = '" . sql_real_escape_string($ak_id) . "'
    ORDER BY ai_id DESC
    LIMIT $limit OFFSET $offset
";

$result = sql_query($sql);

include_once G5_ADMIN_PATH . '/admin.head.php';
?>

<div class="local_ov01 local_ov">
    <a href="./keylist.php" class="ov_list">API 키 목록</a>
    <a href="./iplist.php?ak_id=<?php echo $ak_id; ?>" class="ov_list">IP 화이트리스트</a>
    <span class="btn_ov01"><span class="ov_txt">IP 화이트리스트</span><span class="ov_num"> <?php echo number_format($total_count); ?>개</span></span>
</div>

<div class="local_desc02 local_desc">
    <p>API 키: <strong><?php echo htmlspecialchars($api_key['ak_name']); ?></strong> (<?php echo htmlspecialchars($api_key['ak_key']); ?>)</p>
</div>

<form name="fapi_ip" id="fapi_ip" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="ak_id" value="<?php echo $ak_id; ?>">
    
    <div class="tbl_frm01 tbl_wrap">
        <table>
            <caption>IP 화이트리스트 추가</caption>
            <colgroup>
                <col class="grid_4">
                <col>
            </colgroup>
            <tbody>
                <tr>
                    <th scope="row"><label for="ip">IP 주소</label></th>
                    <td>
                        <input type="text" name="ip" id="ip" class="frm_input required" size="50" required>
                        <span class="frm_info">IPv4, IPv6 또는 CIDR 형식 (예: 192.168.1.1, 192.168.1.0/24)</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="description">설명</label></th>
                    <td>
                        <input type="text" name="description" id="description" class="frm_input" size="50">
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="btn_fixed_top">
        <a href="./keylist.php" class="btn btn_02">API 키 목록</a>
        <input type="submit" value="IP 추가" class="btn_submit btn">
    </div>
</form>

<div class="tbl_head01 tbl_wrap">
    <table>
        <caption>IP 화이트리스트 목록</caption>
        <thead>
            <tr>
                <th scope="col">ID</th>
                <th scope="col">IP 주소</th>
                <th scope="col">설명</th>
                <th scope="col">상태</th>
                <th scope="col">등록일</th>
                <th scope="col">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (sql_num_rows($result) > 0) {
                while ($row = sql_fetch_array($result)) {
                    ?>
                    <tr>
                        <td class="td_num"><?php echo $row['ai_id']; ?></td>
                        <td><code><?php echo htmlspecialchars($row['ai_ip']); ?></code></td>
                        <td><?php echo htmlspecialchars($row['ai_description']); ?></td>
                        <td>
                            <?php if ($row['ai_status'] == 1) { ?>
                                <span class="btn_confirm btn">활성</span>
                            <?php } else { ?>
                                <span class="btn_confirm" style="background:#999;">비활성</span>
                            <?php } ?>
                        </td>
                        <td><?php echo $row['ai_created_at']; ?></td>
                        <td class="td_mng">
                            <button type="button" class="btn btn_02" onclick="confirm_delete(<?php echo $row['ai_id']; ?>);">삭제</button>
                        </td>
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
// 페이징
echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_pages, $_SERVER['PHP_SELF'] . '?ak_id=' . $ak_id . '&page=');
?>

<script>
function confirm_delete(ai_id) {
    if (confirm('정말 삭제하시겠습니까?')) {
        document.getElementById('fapi_ip').action.value = 'delete';
        var ai_id_input = document.createElement('input');
        ai_id_input.type = 'hidden';
        ai_id_input.name = 'ai_id';
        ai_id_input.value = ai_id;
        document.getElementById('fapi_ip').appendChild(ai_id_input);
        document.getElementById('fapi_ip').submit();
    }
}
</script>

<?php
include_once G5_ADMIN_PATH . '/admin.tail.php';
?>
