<?php
include_once('./_common.php');

// 관리자만 접근 가능
if (!$is_admin) {
    alert('관리자만 접근 가능합니다.');
}

$g5['title'] = '회원 테이블 구조 확인';
include_once(G5_PATH.'/head.sub.php');
?>

<style>
    body { padding: 20px; font-family: 'Malgun Gothic', sans-serif; }
    .container { max-width: 1200px; margin: 0 auto; }
    h2 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f5f5f5; font-weight: bold; }
    tr:nth-child(even) { background-color: #f9f9f9; }
    .highlight { background-color: #ffffcc; }
    .mb-fields { background-color: #e6f3ff; }
    pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style>

<div class="container">
    <h2><?php echo $g5['title']; ?></h2>
    
    <h3>1. 회원 테이블 전체 구조</h3>
    <?php
    $sql = "DESCRIBE {$g5['member_table']}";
    $result = sql_query($sql);
    ?>
    <table>
        <thead>
            <tr>
                <th>필드명</th>
                <th>타입</th>
                <th>Null 허용</th>
                <th>키</th>
                <th>기본값</th>
                <th>추가정보</th>
            </tr>
        </thead>
        <tbody>
            <?php
            while ($row = sql_fetch_array($result)) {
                $highlight = '';
                if (strpos($row['Field'], 'mb_') === 0 && is_numeric(substr($row['Field'], 3))) {
                    $highlight = 'mb-fields';
                }
                echo '<tr class="'.$highlight.'">';
                echo '<td>'.$row['Field'].'</td>';
                echo '<td>'.$row['Type'].'</td>';
                echo '<td>'.$row['Null'].'</td>';
                echo '<td>'.$row['Key'].'</td>';
                echo '<td>'.$row['Default'].'</td>';
                echo '<td>'.$row['Extra'].'</td>';
                echo '</tr>';
            }
            ?>
        </tbody>
    </table>
    
    <h3>2. 여분 필드 (mb_1 ~ mb_10) 상세 정보</h3>
    <?php
    $sql = "DESCRIBE {$g5['member_table']}";
    $result = sql_query($sql);
    ?>
    <table>
        <thead>
            <tr>
                <th>필드명</th>
                <th>타입</th>
                <th>최대 길이</th>
                <th>용도 (회원가입 폼 기준)</th>
            </tr>
        </thead>
        <tbody>
            <?php
            while ($row = sql_fetch_array($result)) {
                if (strpos($row['Field'], 'mb_') === 0 && is_numeric(substr($row['Field'], 3))) {
                    $field_num = substr($row['Field'], 3);
                    $usage = '';
                    
                    // 회원가입 폼에서의 용도
                    switch($field_num) {
                        case '1': $usage = '회원 유형 (student/designer/partner)'; break;
                        case '2': $usage = '학교명 (학생회원)'; break;
                        case '3': $usage = '학년 (학생회원)'; break;
                        case '4': $usage = '학생증 파일명 (학생회원)'; break;
                        case '5': $usage = '미용사 자격증 번호 (디자이너)'; break;
                        case '6': $usage = '경력 (디자이너)'; break;
                        case '7': $usage = '근무 매장 (디자이너)'; break;
                        case '8': $usage = '자격증 파일명 (디자이너)'; break;
                        default: $usage = '미사용'; break;
                    }
                    
                    echo '<tr>';
                    echo '<td><strong>'.$row['Field'].'</strong></td>';
                    echo '<td>'.$row['Type'].'</td>';
                    echo '<td>';
                    if (preg_match('/varchar\((\d+)\)/', $row['Type'], $matches)) {
                        echo $matches[1].'자';
                    } else {
                        echo '-';
                    }
                    echo '</td>';
                    echo '<td>'.$usage.'</td>';
                    echo '</tr>';
                }
            }
            ?>
        </tbody>
    </table>
    
    <h3>3. 테이블 생성 SQL (참고용)</h3>
    <?php
    $sql = "SHOW CREATE TABLE {$g5['member_table']}";
    $result = sql_fetch($sql);
    ?>
    <pre><?php echo htmlspecialchars($result['Create Table']); ?></pre>
    
    <h3>4. 현재 회원 데이터 샘플 (최근 5명)</h3>
    <?php
    $sql = "SELECT mb_id, mb_name, mb_nick, mb_level, mb_1, mb_2, mb_3, mb_4, mb_5, mb_6, mb_7, mb_8, mb_datetime 
            FROM {$g5['member_table']} 
            ORDER BY mb_datetime DESC 
            LIMIT 5";
    $result = sql_query($sql);
    ?>
    <table>
        <thead>
            <tr>
                <th>아이디</th>
                <th>이름</th>
                <th>닉네임</th>
                <th>레벨</th>
                <th>mb_1<br>(회원유형)</th>
                <th>mb_2<br>(학교명)</th>
                <th>mb_3<br>(학년)</th>
                <th>mb_5<br>(자격증번호)</th>
                <th>mb_6<br>(경력)</th>
                <th>mb_7<br>(근무매장)</th>
                <th>가입일</th>
            </tr>
        </thead>
        <tbody>
            <?php
            while ($row = sql_fetch_array($result)) {
                echo '<tr>';
                echo '<td>'.$row['mb_id'].'</td>';
                echo '<td>'.$row['mb_name'].'</td>';
                echo '<td>'.$row['mb_nick'].'</td>';
                echo '<td>'.$row['mb_level'].'</td>';
                echo '<td>'.$row['mb_1'].'</td>';
                echo '<td>'.$row['mb_2'].'</td>';
                echo '<td>'.$row['mb_3'].'</td>';
                echo '<td>'.$row['mb_5'].'</td>';
                echo '<td>'.$row['mb_6'].'</td>';
                echo '<td>'.$row['mb_7'].'</td>';
                echo '<td>'.substr($row['mb_datetime'], 0, 10).'</td>';
                echo '</tr>';
            }
            ?>
        </tbody>
    </table>
    
    <h3>5. 더미 회원 생성 테스트 SQL</h3>
    <pre>
-- 학생 회원 삽입 예제
INSERT INTO <?php echo $g5['member_table']; ?> SET
    mb_id = 'test_student_1',
    mb_name = '김학생',
    mb_nick = '헤어공부중',
    mb_password = '<?php echo get_encrypt_string('1234'); ?>',
    mb_email = 'student@test.com',
    mb_level = '<?php echo $config['cf_register_level']; ?>',
    mb_datetime = NOW(),
    mb_ip = '127.0.0.1',
    mb_email_certify = NOW(),
    mb_certify = 'admin',
    mb_1 = 'student',
    mb_2 = '서울미용고등학교',
    mb_3 = '2',
    mb_4 = 'student_cert_test.jpg';

-- 디자이너 회원 삽입 예제  
INSERT INTO <?php echo $g5['member_table']; ?> SET
    mb_id = 'test_designer_1',
    mb_name = '이디자이너',
    mb_nick = '펌전문가',
    mb_password = '<?php echo get_encrypt_string('1234'); ?>',
    mb_email = 'designer@test.com',
    mb_level = '<?php echo $config['cf_register_level']; ?>',
    mb_datetime = NOW(),
    mb_ip = '127.0.0.1',
    mb_email_certify = NOW(),
    mb_certify = 'admin',
    mb_1 = 'designer',
    mb_5 = '2023-12-12345',
    mb_6 = '3-5년',
    mb_7 = '준오헤어 강남점',
    mb_8 = 'designer_cert_test.jpg';
    </pre>
    
    <div style="margin-top: 30px; padding: 20px; background: #f0f0f0; border-radius: 5px;">
        <p><strong>참고사항:</strong></p>
        <ul>
            <li>mb_1 ~ mb_10 필드는 보통 VARCHAR(255) 타입입니다.</li>
            <li>mb_4, mb_8 필드는 파일 경로가 아닌 파일명만 저장합니다.</li>
            <li>실제 파일 업로드는 별도 처리가 필요합니다.</li>
            <li>mb_memo 필드에 '더미회원' 표시를 추가할 수 있습니다.</li>
        </ul>
    </div>
</div>

<?php
include_once(G5_PATH.'/tail.sub.php');
?>