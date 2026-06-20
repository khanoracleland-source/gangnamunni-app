<?php
/**
 * 그누보드 관리자 비밀번호 재설정
 * 사용 후 반드시 이 파일을 삭제하세요!
 */

include_once('./_common.php');

echo "<h2>회원 목록 (관리자 레벨 10)</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>아이디</th><th>이름</th><th>레벨</th><th>비밀번호 변경</th></tr>";

// 관리자 레벨(10) 회원 조회
$sql = "SELECT mb_id, mb_name, mb_level FROM {$g5['member_table']} WHERE mb_level >= 9 ORDER BY mb_level DESC";
$result = sql_query($sql);

while ($row = sql_fetch_array($result)) {
    echo "<tr>";
    echo "<td><strong>{$row['mb_id']}</strong></td>";
    echo "<td>{$row['mb_name']}</td>";
    echo "<td>{$row['mb_level']}</td>";
    echo "<td><a href='?action=reset&id={$row['mb_id']}'>비밀번호 초기화</a></td>";
    echo "</tr>";
}
echo "</table>";

// 비밀번호 초기화 실행
if (isset($_GET['action']) && $_GET['action'] == 'reset' && isset($_GET['id'])) {
    $admin_id = $_GET['id'];
    $new_password = 'admin1234';

    // 비밀번호 해시 (그누보드5 방식)
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    $sql = "UPDATE {$g5['member_table']} SET mb_password = '{$hashed_password}' WHERE mb_id = '{$admin_id}'";
    sql_query($sql);

    echo "<hr>";
    echo "<h2 style='color:green;'>✅ 비밀번호 변경 완료!</h2>";
    echo "<p>아이디: <strong>{$admin_id}</strong></p>";
    echo "<p>새 비밀번호: <strong>{$new_password}</strong></p>";
    echo "<p><a href='/adm'>관리자 페이지로 이동</a></p>";
}

echo "<hr>";
echo "<p style='color:red;font-weight:bold;'>⚠️ 보안을 위해 이 파일(reset_pw.php)을 반드시 삭제하세요!</p>";
?>
