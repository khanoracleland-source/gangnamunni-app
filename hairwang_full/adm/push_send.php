<?php
$sub_menu = "300910";
include_once('./_common.php');

auth_check($auth[$sub_menu], 'w');

// 테이블 존재 확인 및 자동 생성
$sql = "SHOW TABLES LIKE 'g5_push_tokens'";
$result = sql_fetch($sql);

if(!$result) {
    // 테이블이 없으면 자동 생성
    $create_table_sql = "
    CREATE TABLE IF NOT EXISTS `g5_push_tokens` (
      `pt_id` int(11) NOT NULL AUTO_INCREMENT,
      `mb_id` varchar(20) NOT NULL DEFAULT '',
      `pt_token` varchar(255) NOT NULL,
      `pt_platform` varchar(10) NOT NULL DEFAULT '',
      `pt_device_info` text,
      `pt_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `pt_use` tinyint(4) NOT NULL DEFAULT '1',
      PRIMARY KEY (`pt_id`),
      KEY `mb_id` (`mb_id`),
      UNIQUE KEY `pt_token` (`pt_token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
    
    sql_query($create_table_sql, true);
    
    // 테이블 생성 성공 메시지 (선택사항)
    echo '<script>console.log("푸시 토큰 테이블이 자동 생성되었습니다.");</script>';
}

// 푸시 발송 처리
if($_POST['act_button'] == '선택발송') {
    $title = strip_tags($_POST['push_title']);
    $message = strip_tags($_POST['push_message']);
    $send_type = $_POST['send_type'];
    
    $success_count = 0;
    $fail_count = 0;
    
    if($send_type == 'all') {
        // 전체 발송
        $sql = "SELECT DISTINCT pt_token, mb_id FROM g5_push_tokens WHERE pt_use = 1";
        $result = sql_query($sql);
        
        while($row = sql_fetch_array($result)) {
            // 여기에 실제 FCM 발송 코드 추가
            // $fcm_result = send_fcm_push($row['pt_token'], $title, $message);
            // if($fcm_result) $success_count++; else $fail_count++;
            
            // 테스트용 - 실제로는 FCM 발송 함수 호출
            $success_count++;
        }
    } else if($send_type == 'selected' && isset($_POST['mb_ids'])) {
        // 선택 발송
        $mb_ids = $_POST['mb_ids'];
        foreach($mb_ids as $mb_id) {
            $mb_id = sql_real_escape_string($mb_id);
            $sql = "SELECT pt_token FROM g5_push_tokens 
                    WHERE mb_id = '{$mb_id}' AND pt_use = 1";
            $result = sql_query($sql);
            
            while($row = sql_fetch_array($result)) {
                // 여기에 실제 FCM 발송 코드 추가
                $success_count++;
            }
        }
    }
    
    // 발송 이력 저장 (선택사항)
    $log_sql = "INSERT INTO g5_push_tokens_log 
                SET title = '{$title}',
                    message = '{$message}',
                    success_count = '{$success_count}',
                    fail_count = '{$fail_count}',
                    send_datetime = NOW()";
    @sql_query($log_sql);
    
    alert("푸시 발송 완료\\n성공: {$success_count}건\\n실패: {$fail_count}건", './push_send.php');
}

$g5['title'] = '푸시 알림 발송';
include_once('./admin.head.php');

// 등록된 토큰 통계
$sql = "SELECT 
        COUNT(DISTINCT mb_id) as member_count,
        COUNT(*) as token_count,
        SUM(CASE WHEN pt_platform = 'android' THEN 1 ELSE 0 END) as android_count,
        SUM(CASE WHEN pt_platform = 'ios' THEN 1 ELSE 0 END) as ios_count
        FROM g5_push_tokens WHERE pt_use = 1";
$stats = sql_fetch($sql);
?>

<style>
.push_stats { background:#f7f7f7; padding:15px; margin-bottom:20px; border-radius:5px; }
.push_stats span { display:inline-block; margin-right:20px; }
.push_stats strong { color:#e8180c; }
</style>

<div class="local_desc01 local_desc">
    <p>
        앱 푸시 알림을 발송합니다.<br>
        토큰이 등록된 회원에게만 발송되며, 앱이 설치되어 있어야 수신 가능합니다.
    </p>
</div>

<div class="push_stats">
    <span>등록회원: <strong><?php echo number_format($stats['member_count']); ?></strong>명</span>
    <span>전체토큰: <strong><?php echo number_format($stats['token_count']); ?></strong>개</span>
    <span>Android: <strong><?php echo number_format($stats['android_count']); ?></strong></span>
    <span>iOS: <strong><?php echo number_format($stats['ios_count']); ?></strong></span>
</div>

<form name="fpushsend" method="post" onsubmit="return fpushsend_submit(this);">
<input type="hidden" name="act_button" value="선택발송">

<div class="tbl_frm01 tbl_wrap">
    <table>
    <colgroup>
        <col class="grid_4">
        <col>
    </colgroup>
    <tbody>
    <tr>
        <th scope="row"><label for="push_title">알림 제목</label></th>
        <td>
            <input type="text" name="push_title" id="push_title" class="frm_input required" size="70" maxlength="100" required>
            <span class="frm_info">푸시 알림의 제목입니다</span>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="push_message">알림 내용</label></th>
        <td>
            <textarea name="push_message" id="push_message" rows="5" class="frm_input required" required></textarea>
            <span class="frm_info">푸시 알림 메시지 내용 (최대 200자)</span>
        </td>
    </tr>
    <tr>
        <th scope="row">발송 대상</th>
        <td>
            <label><input type="radio" name="send_type" value="all" checked> 전체 회원 (토큰 보유자)</label>
            <label><input type="radio" name="send_type" value="selected"> 회원 선택</label>
            
            <div id="member_select_box" style="display:none; margin-top:10px;">
                <div style="max-height:200px; overflow-y:auto; border:1px solid #ddd; padding:10px;">
                    <?php
                    $sql = "SELECT DISTINCT t.mb_id, m.mb_name, m.mb_nick, COUNT(t.pt_token) as token_cnt
                            FROM g5_push_tokens t
                            LEFT JOIN {$g5['member_table']} m ON t.mb_id = m.mb_id
                            WHERE t.pt_use = 1
                            GROUP BY t.mb_id
                            ORDER BY m.mb_name";
                    $result = sql_query($sql);
                    while($row = sql_fetch_array($result)) {
                        echo '<label style="display:block; margin:5px 0;">';
                        echo '<input type="checkbox" name="mb_ids[]" value="'.$row['mb_id'].'"> ';
                        echo $row['mb_name'].' ('.$row['mb_id'].') - 토큰 '.$row['token_cnt'].'개';
                        echo '</label>';
                    }
                    ?>
                </div>
            </div>
        </td>
    </tr>
    <tr>
        <th scope="row">발송 옵션</th>
        <td>
            <label><input type="checkbox" name="test_mode" value="1"> 테스트 모드 (실제 발송하지 않음)</label>
        </td>
    </tr>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <input type="submit" value="푸시 발송" class="btn_submit btn" accesskey="s">
    <a href="./push_list.php" class="btn btn_02">토큰 목록</a>
</div>
</form>

<script>
// 발송 대상 선택 표시/숨기기
$(document).ready(function() {
    $('input[name="send_type"]').change(function() {
        if($(this).val() == 'selected') {
            $('#member_select_box').show();
        } else {
            $('#member_select_box').hide();
        }
    });
});

function fpushsend_submit(f) {
    if(!f.push_title.value) {
        alert("알림 제목을 입력하세요.");
        f.push_title.focus();
        return false;
    }
    
    if(!f.push_message.value) {
        alert("알림 내용을 입력하세요.");
        f.push_message.focus();
        return false;
    }
    
    if(f.send_type.value == 'selected') {
        var checked = $('input[name="mb_ids[]"]:checked').length;
        if(checked < 1) {
            alert("발송할 회원을 선택하세요.");
            return false;
        }
    }
    
    var msg = "푸시 알림을 발송하시겠습니까?";
    if(f.test_mode && f.test_mode.checked) {
        msg = "[테스트 모드] " + msg;
    }
    
    return confirm(msg);
}
</script>

<?php
// 최근 발송 이력 표시 (선택사항)
if(sql_fetch("SHOW TABLES LIKE 'g5_push_tokens_log'")) {
?>
<h2>최근 발송 이력</h2>
<div class="tbl_head01 tbl_wrap">
    <table>
    <thead>
    <tr>
        <th>발송일시</th>
        <th>제목</th>
        <th>내용</th>
        <th>성공/실패</th>
    </tr>
    </thead>
    <tbody>
    <?php
    $sql = "SELECT * FROM g5_push_tokens_log ORDER BY send_datetime DESC LIMIT 10";
    $result = sql_query($sql);
    while($row = sql_fetch_array($result)) {
        echo '<tr>';
        echo '<td class="td_datetime">'.substr($row['send_datetime'],0,16).'</td>';
        echo '<td>'.get_text($row['title']).'</td>';
        echo '<td>'.get_text($row['message']).'</td>';
        echo '<td>'.$row['success_count'].' / '.$row['fail_count'].'</td>';
        echo '</tr>';
    }
    ?>
    </tbody>
    </table>
</div>
<?php } ?>

<?php
include_once('./admin.tail.php');
?>