<?php
/**
 * 그누보드 리빌더 - 관리자 비밀번호 리셋
 * 사용 후 반드시 삭제할 것!
 */

// 그누보드 공용 파일 로드
include_once('./_common.php');

// PBKDF2 create_hash 함수 사용을 위해 lib 로드 확인
if (!function_exists('create_hash')) {
    die('create_hash 함수를 찾을 수 없습니다.');
}

$new_password = 'admin1234';
$admin_id = 'admin';

// create_hash 사용 (PBKDF2)
$hashed_password = create_hash($new_password);

echo "<h2>비밀번호 해시 생성 완료</h2>";
echo "<p>새 비밀번호: " . htmlspecialchars($new_password) . "</p>";
echo "<p>해시 길이: " . strlen($hashed_password) . "</p>";
echo "<p>해시 값: " . htmlspecialchars(substr($hashed_password, 0, 50)) . "...</p>";

// DB 업데이트
$sql = "UPDATE {$g5['member_table']} SET mb_password = '{$hashed_password}' WHERE mb_id = '{$admin_id}'";
$result = sql_query($sql);

if ($result) {
    echo "<h2 style='color:green;'>✅ 비밀번호 변경 완료!</h2>";
    echo "<p>아이디: <strong>{$admin_id}</strong></p>";
    echo "<p>새 비밀번호: <strong>{$new_password}</strong></p>";
    echo "<hr>";
    echo "<p style='color:red;'><strong>⚠️ 이 파일을 서버에서 삭제하세요!</strong></p>";
} else {
    echo "<h2 style='color:red;'>❌ 비밀번호 변경 실패</h2>";
}
?>
