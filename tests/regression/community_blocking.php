<?php
// Local-only end-to-end check. All fixture rows and the temporary session are removed.
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__,2).'/hairwang_full';
$_SERVER['HTTP_USER_AGENT'] = 'BlockRegression';
$_SERVER['SCRIPT_NAME'] = '/gangnamunni/community.php';
require dirname(__DIR__, 2) . '/hairwang_full/gangnamunni/includes/init.php';
require_once dirname(__DIR__, 2) . '/hairwang_full/gangnamunni/includes/user_blocking.php';
if (G5_MYSQL_HOST !== 'mysql') throw new RuntimeException('Local Docker DB required');
$id = 'bt_' . bin2hex(random_bytes(5));
$target = 'bt_' . bin2hex(random_bytes(5));
function check_block($ok, $label) { if (!$ok) throw new RuntimeException($label); }
try {
    $email = $id.'@example.invalid';
    $password = bin2hex(random_bytes(12));
    $hash = get_encrypt_string($password);
    $stmt = $connect->prepare("INSERT INTO g5_member (mb_id,mb_name,mb_nick,mb_email,mb_password,mb_level,mb_datetime,mb_email_certify) VALUES (?,?,?,?,?,2,NOW(),NOW())");
    $stmt->bind_param('sssss', $id, $id, $id, $email, $hash);
    $stmt->execute();
    session_write_close();
    $jar = tempnam(sys_get_temp_dir(), 'block-test-');
    $curl = curl_init('http://localhost/gangnamunni/auth/login.php');
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_COOKIEJAR=>$jar, CURLOPT_COOKIEFILE=>$jar, CURLOPT_TIMEOUT=>15]);
    $html = curl_exec($curl);
    check_block(preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $match), 'Login CSRF missing');
    curl_setopt_array($curl, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>http_build_query(['mode'=>'email','mb_email'=>$email,'mb_password'=>$password,'csrf_token'=>$match[1]])]);
    curl_exec($curl);
    check_block(curl_getinfo($curl,CURLINFO_HTTP_CODE)===302, 'Fixture login failed');
    $request = function($method, $data) use ($curl) {
        curl_setopt_array($curl, [CURLOPT_URL=>'http://localhost/gangnamunni/api_block_user.php', CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_HTTPHEADER=>['Content-Type: application/json', 'Referer: http://localhost/gangnamunni/community.php'], CURLOPT_POSTFIELDS=>json_encode($data)]);
        $body = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $json = json_decode($body,true);
        if (!$json) throw new RuntimeException('Non-JSON API response: '.substr($body,0,350));
        return [$code,$json];
    };
    // Old clients may still submit choices; all protections must always apply.
    foreach ([null, [], ['silent'], ['hide_content'], ['block_messages'], ['hide_content','block_messages','silent']] as $options) {
        $payload=['blocked_id'=>$target,'blocked_name'=>'Local test'];
        if ($options !== null) $payload['options']=$options;
        $before=$connect->query("SELECT COUNT(*) n FROM gangnamunni_user_notifications WHERE member_id='$target'")->fetch_assoc()['n'];
        [$code,$result]=$request('POST',$payload);
        check_block($code===200 && $result['success'],'API save failed: '.json_encode($result));
        check_block(in_array($target,community_blocked_ids($connect,$id),true),'Content must be hidden');
        check_block(in_array($id,community_blocked_ids($connect,$target),true),'Reverse content must be hidden');
        check_block(community_message_blocked($connect,$target,$id),'Incoming messages must be blocked');
        check_block(!community_message_blocked($connect,$id,$target),'Message direction mismatch');
        $condition=community_content_condition($connect,'mb_id',$target);
        check_block($connect->query("SELECT mb_id FROM g5_member WHERE mb_id='$id' AND $condition")->num_rows===0,'Author/profile visibility condition failed');
        $after=$connect->query("SELECT COUNT(*) n FROM gangnamunni_user_notifications WHERE member_id='$target'")->fetch_assoc()['n'];
        check_block((int)$after===(int)$before,'Blocking must never notify the target');
    }
    [$code,$result]=$request('DELETE',['blocked_id'=>$target]);
    check_block($code===200 && $result['success'],'Unblock failed');
    check_block(community_blocked_ids($connect,$id)===[],'Unblock content mismatch');
    check_block(!community_message_blocked($connect,$target,$id),'Unblock message mismatch');
    echo "PASS: unconditional blocking, legacy payloads, mutual content/profile hiding, incoming messages, no notifications, unblock\n";
} finally {
    $connect->query("DELETE FROM gangnamunni_blocked_users WHERE blocker_id='$id'");
    $connect->query("DELETE FROM gangnamunni_user_notifications WHERE member_id='$target'");
    $connect->query("DELETE FROM g5_member WHERE mb_id='$id'");
    if (isset($curl)) { curl_setopt_array($curl, [CURLOPT_URL=>'http://localhost/gangnamunni/auth/logout.php',CURLOPT_CUSTOMREQUEST=>'GET',CURLOPT_POSTFIELDS=>null]); curl_exec($curl); curl_close($curl); }
    if (isset($jar)) unlink($jar);
    if (session_status()!==PHP_SESSION_ACTIVE) session_start();
    session_destroy();
}
