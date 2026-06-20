<?php
$sub_menu = "200150";
include_once('./_common.php');

// 관리자만 접근 가능
if ($member['mb_level'] < 10) {
    alert('접근 권한이 없습니다.');
}

$g5['title'] = '승인대기 회원 디버깅';
include_once('./admin.head.php');

// 1. 승인대기 회원 조회 (member_type_list.php와 동일한 쿼리)
echo "<h2>1. 승인대기 회원 수 (member_type_list.php 방식)</h2>";
$sql_pending = " select count(*) as cnt from {$g5['member_table']} where mb_9 = '' and mb_1 != '' ";
$row_pending = sql_fetch($sql_pending);
echo "승인대기 회원 수: " . $row_pending['cnt'] . "명<br><br>";

// 2. 실제 승인대기 회원 목록
echo "<h2>2. 승인대기 회원 상세 목록</h2>";
$sql = " select mb_id, mb_name, mb_1, mb_9, mb_level from {$g5['member_table']} where mb_9 = '' and mb_1 != '' ";
$result = sql_query($sql);
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>아이디</th><th>이름</th><th>회원유형(mb_1)</th><th>승인상태(mb_9)</th><th>레벨</th></tr>";
while($row = sql_fetch_array($result)) {
    echo "<tr>";
    echo "<td>{$row['mb_id']}</td>";
    echo "<td>{$row['mb_name']}</td>";
    echo "<td>{$row['mb_1']}</td>";
    echo "<td>'{$row['mb_9']}'</td>";
    echo "<td>{$row['mb_level']}</td>";
    echo "</tr>";
}
echo "</table><br><br>";

// 3. mb_9 필드의 다양한 값 확인
echo "<h2>3. mb_9 필드의 모든 값 종류</h2>";
$sql = " select mb_9, count(*) as cnt from {$g5['member_table']} where mb_1 != '' group by mb_9 ";
$result = sql_query($sql);
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>mb_9 값</th><th>회원 수</th><th>값 길이</th></tr>";
while($row = sql_fetch_array($result)) {
    $mb9_display = $row['mb_9'] === '' ? '(빈값)' : $row['mb_9'];
    $mb9_length = strlen($row['mb_9']);
    echo "<tr>";
    echo "<td>'{$mb9_display}'</td>";
    echo "<td>{$row['cnt']}명</td>";
    echo "<td>{$mb9_length}</td>";
    echo "</tr>";
}
echo "</table><br><br>";

// 4. NULL 값 확인
echo "<h2>4. mb_9가 NULL인 경우 확인</h2>";
$sql = " select count(*) as cnt from {$g5['member_table']} where mb_9 IS NULL and mb_1 != '' ";
$row = sql_fetch($sql);
echo "mb_9가 NULL인 회원 수: " . $row['cnt'] . "명<br><br>";

// 5. 공백 문자 확인
echo "<h2>5. mb_9에 공백이 포함된 경우</h2>";
$sql = " select mb_id, mb_9, LENGTH(mb_9) as len from {$g5['member_table']} where mb_1 != '' and (mb_9 LIKE '% %' OR mb_9 = ' ') ";
$result = sql_query($sql);
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>아이디</th><th>mb_9 값</th><th>길이</th></tr>";
while($row = sql_fetch_array($result)) {
    echo "<tr>";
    echo "<td>{$row['mb_id']}</td>";
    echo "<td>'{$row['mb_9']}'</td>";
    echo "<td>{$row['len']}</td>";
    echo "</tr>";
}
echo "</table><br><br>";

// 6. 모든 조건을 포함한 승인대기 확인
echo "<h2>6. 포괄적인 승인대기 조건</h2>";
$sql = " select count(*) as cnt from {$g5['member_table']} 
         where mb_1 != '' 
         and (mb_9 = '' OR mb_9 IS NULL OR mb_9 = ' ' OR LENGTH(TRIM(mb_9)) = 0) ";
$row = sql_fetch($sql);
echo "포괄적 조건의 승인대기 회원 수: " . $row['cnt'] . "명<br><br>";

include_once('./admin.tail.php');
?>