<?php 
global $g5;
include_once(__DIR__ . '/_common.php');

$dm_type = $_POST['dm_type'] ?? 'normal';
$dm_count = $_POST['dm_count'] ?? 0;
$dm_userid = $_POST['dm_userid'] ?? '';
$dm_userid_value = $_POST['dm_userid_value'] ?? '';
$dm_password = $_POST['dm_password'] ?? '';

$user_id_length = 0;
$user_id = '';
$success_count = 0;
$fail_count = 0;
$fail_reasons = array();

// 한국 이름 생성용 데이터
$last_names = array('김', '이', '박', '최', '정', '강', '조', '윤', '장', '임', '한', '오', '서', '신', '권', '황', '안', '송', '전', '홍');
$first_names_male = array('민준', '서준', '도윤', '예준', '시우', '하준', '주원', '지호', '지후', '준우', '준서', '도현', '건우', '현우', '우진', '지훈', '선우', '재민', '재윤', '서진');
$first_names_female = array('서연', '서윤', '지우', '서현', '하은', '하윤', '민서', '지유', '채원', '지민', '수아', '다은', '예은', '지아', '수빈', '소율', '예린', '소은', '지원', '시은');

// 헤어 관련 닉네임 생성용 데이터
$hair_prefix = array('매직', '레이어드', '펌', '컬', '웨이브', '스트레이트', '투톤', '애쉬', '브라운', '블랙', '레드', '골드', '실버', '바이올렛', '핑크');
$hair_suffix = array('헤어', '스타일', '살롱', '디자이너', '아티스트', '마스터', '전문가', '크리에이터', '미용사', '헤어샵', '뷰티', '케어', '클리닉', '트렌드', '매니아');

// 학생회원용 데이터
$schools = array(
    '서울미용고등학교', '부산미용고등학교', '인천미용고등학교', '대구미용고등학교', '광주미용고등학교',
    '한국미용대학교', '서울미용대학교', '중앙미용대학교', '동국미용대학교', '경희미용대학교',
    '미용예술고등학교', '뷰티아트고등학교', '헤어디자인고등학교', '미용전문학교', '뷰티전문학교'
);

// 헤어디자이너용 데이터
$hair_shops = array(
    '준오헤어', '이철헤어커커', '박준뷰티랩', '리안헤어', '차홍아르더',
    '라뷰헤어', '블루클럽', '레드클럽', '헤어팰리스', '헤어갤러리',
    '살롱드라피네', '아베다살롱', '토니앤가이', '준헤어', '박승철헤어스튜디오',
    '헤어플러스', '헤어라인', '미가헤어', '수헤어', '헤어스케치'
);

$careers = array('1년미만', '1-3년', '3-5년', '5-10년', '10년이상');

// 랜덤 한국 이름 생성 함수
function generate_korean_name() {
    global $last_names, $first_names_male, $first_names_female;
    
    $gender = rand(0, 1); // 0: 여성, 1: 남성
    $last_name = $last_names[array_rand($last_names)];
    
    if ($gender == 1) {
        $first_name = $first_names_male[array_rand($first_names_male)];
    } else {
        $first_name = $first_names_female[array_rand($first_names_female)];
    }
    
    return $last_name . $first_name;
}

// 헤어 관련 닉네임 생성 함수
function generate_hair_nickname() {
    global $hair_prefix, $hair_suffix;
    
    $prefix = $hair_prefix[array_rand($hair_prefix)];
    $suffix = $hair_suffix[array_rand($hair_suffix)];
    $number = rand(1, 999);
    
    return $prefix . $suffix . $number;
}

// 미용사 자격증 번호 생성 함수
function generate_license_number() {
    $year = rand(2010, 2023);
    $region = rand(10, 99);
    $number = rand(10000, 99999);
    return $year . '-' . $region . '-' . $number;
}

// 아이디 생성
if($dm_userid == 'on'){
    $user_id_length = rand(5, 20);
    $user_id = chr(rand(97, 122)); // 첫 글자는 영문 소문자로 시작
    $user_id .= substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyz0123456789', ceil(($user_id_length-1) / 36))), 0, $user_id_length-1);
}else{
    $user_id = $dm_userid_value;
}

// 이메일 도메인
$domain_endings = array("naver.com", "daum.net", "gmail.com", "hanmail.net", "nate.com", "kakao.com");

// IP 생성
$ip = rand(1, 255) . "." . rand(1, 255) . "." . rand(1, 255) . "." . rand(1, 255);

// 회원 생성 루프
$base_user_id = $user_id;
for($i = 0; $i < $dm_count; $i++){
    $current_user_id = $base_user_id . '_' . ($i + 1);
    $korean_name = generate_korean_name();
    $hair_nickname = generate_hair_nickname();
    $domain = $domain_endings[array_rand($domain_endings)];
    $email = $current_user_id . "@" . $domain;
    
    // 추가 회원 정보
    $mb_hp = '010' . rand(1000, 9999) . rand(1000, 9999);
    $mb_tel = '02' . rand(100, 999) . rand(1000, 9999);
    
    // 우편번호를 zip1, zip2로 분리 (구버전 그누보드용)
    $zip_code = sprintf("%05d", rand(1000, 99999));
    $mb_zip1 = substr($zip_code, 0, 3);
    $mb_zip2 = substr($zip_code, 3, 3);
    
    $mb_addr1 = '서울특별시 강남구 테헤란로 ' . rand(1, 500);
    $mb_addr2 = rand(1, 20) . '층 ' . rand(101, 2010) . '호';
    $mb_birth = rand(1970, 2000) . sprintf("%02d", rand(1, 12)) . sprintf("%02d", rand(1, 28));
    
    // 회원 유형별 추가 정보
    $mb_1 = ''; // 회원 유형
    $mb_2 = ''; // 학교명 (학생)
    $mb_3 = ''; // 학년 (학생)
    $mb_4 = ''; // 학생증 파일명 (학생)
    $mb_5 = ''; // 자격증 번호 (디자이너)
    $mb_6 = ''; // 경력 (디자이너)
    $mb_7 = ''; // 근무 매장 (디자이너)
    $mb_8 = ''; // 자격증 파일명 (디자이너)
    
    if ($dm_type == 'student') {
        $mb_1 = 'student';
        $mb_2 = $schools[array_rand($schools)];
        $mb_3 = strval(rand(1, 4)); // 문자열로 변환
        $mb_4 = 'student_cert_' . $current_user_id . '_' . time() . '.jpg'; // 더미 파일명
    } elseif ($dm_type == 'designer') {
        $mb_1 = 'designer';
        $mb_5 = generate_license_number();
        $mb_6 = $careers[array_rand($careers)];
        $mb_7 = $hair_shops[array_rand($hair_shops)];
        $mb_8 = 'designer_cert_' . $current_user_id . '_' . time() . '.jpg'; // 더미 파일명
    }
    
    $current_user = array(
        'user_id' => sql_real_escape_string($current_user_id),
        'name' => sql_real_escape_string($korean_name),
        'nick' => sql_real_escape_string($hair_nickname),
        'password' => sql_real_escape_string($dm_password),
        'email' => sql_real_escape_string($email),
        'hp' => sql_real_escape_string($mb_hp),
        'tel' => sql_real_escape_string($mb_tel),
        'zip1' => sql_real_escape_string($mb_zip1),
        'zip2' => sql_real_escape_string($mb_zip2),
        'addr1' => sql_real_escape_string($mb_addr1),
        'addr2' => sql_real_escape_string($mb_addr2),
        'birth' => sql_real_escape_string($mb_birth),
        'ip' => sql_real_escape_string($ip),
        'mb_1' => sql_real_escape_string($mb_1),
        'mb_2' => sql_real_escape_string($mb_2),
        'mb_3' => sql_real_escape_string($mb_3),
        'mb_4' => sql_real_escape_string($mb_4),
        'mb_5' => sql_real_escape_string($mb_5),
        'mb_6' => sql_real_escape_string($mb_6),
        'mb_7' => sql_real_escape_string($mb_7),
        'mb_8' => sql_real_escape_string($mb_8)
    );
    
    // 중복 체크
    $row = sql_fetch("select count(*) as cnt from {$g5['member_table']} where mb_id = '{$current_user['user_id']}' ");
    if ($row['cnt']) {
        $fail_count++;
        $fail_reasons[] = $current_user['user_id'] . ": 이미 존재하는 회원 아이디";
        continue;
    }
    
    // 닉네임 중복 체크
    $row = sql_fetch("select count(*) as cnt from {$g5['member_table']} where mb_nick = '{$current_user['nick']}' ");
    if ($row['cnt']) {
        // 닉네임이 중복되면 숫자 추가
        $current_user['nick'] = $current_user['nick'] . '_' . rand(1000, 9999);
    }
    
    $register_level = isset($config['cf_register_level']) ? $config['cf_register_level'] : 2;
    
    $sql = " 
        insert into {$g5['member_table']} 
            set mb_id           = '{$current_user['user_id']}',
                mb_name         = '{$current_user['name']}',
                mb_nick         = '{$current_user['nick']}',
                mb_password     = '".get_encrypt_string($current_user['password'])."',
                mb_email        = '{$current_user['email']}',
                mb_hp           = '{$current_user['hp']}',
                mb_tel          = '{$current_user['tel']}',
                mb_zip1         = '{$current_user['zip1']}',
                mb_zip2         = '{$current_user['zip2']}',
                mb_addr1        = '{$current_user['addr1']}',
                mb_addr2        = '{$current_user['addr2']}',
                mb_addr3        = '',
                mb_addr_jibeon  = '',
                mb_birth        = '{$current_user['birth']}',
                mb_sex          = '',
                mb_level        = '{$register_level}',
                mb_datetime     = NOW(),
                mb_ip           = '{$current_user['ip']}',
                mb_email_certify = NOW(),
                mb_mailling     = '1',
                mb_sms          = '1',
                mb_open         = '1',
                mb_certify      = 'admin',
                mb_adult        = '1',
                mb_1            = '{$current_user['mb_1']}',
                mb_2            = '{$current_user['mb_2']}',
                mb_3            = '{$current_user['mb_3']}',
                mb_4            = '{$current_user['mb_4']}',
                mb_5            = '{$current_user['mb_5']}',
                mb_6            = '{$current_user['mb_6']}',
                mb_7            = '{$current_user['mb_7']}',
                mb_8            = '{$current_user['mb_8']}',
                mb_9            = '',
                mb_10           = '',
                mb_partner      = '0',
                mb_partner_add_time = '0000-00-00 00:00:00',
                mb_bank         = '',
                mb_pa_point     = '0',
                mb_memo         = '더미회원' 
    ";

    if(sql_query($sql)){
        $success_count++;
    }else{
        $fail_count++;
        // MySQL 에러 직접 확인
        global $g5;
        $link = $g5['connect_db'];
        $error_msg = mysqli_error($link);
        $fail_reasons[] = $current_user['user_id'] . ": DB 입력 실패 - " . $error_msg;
    }
}

$type_name = '';
switch($dm_type) {
    case 'student':
        $type_name = '학생';
        break;
    case 'designer':
        $type_name = '헤어디자이너';
        break;
    default:
        $type_name = '일반';
        break;
}

$message = "{$type_name} 회원 총 {$dm_count}명 중 성공: {$success_count}명, 실패: {$fail_count}명";
if($fail_count > 0){
    $message .= "<br><br>실패 사유:<br>" . implode("<br>", $fail_reasons);
}

echo '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;text-align:center;font-size:14px;line-height:1.6;">
    <div style="padding:30px 0; width: 100%; background:#f8f8f9;">
        '.$message.'
        <p style="margin-top:20px;color:#666;">3초 뒤 회원 목록으로 이동합니다.</p>
    </div>
</div>';
echo '<script>
setTimeout(function() {
    location.href = "'.G5_ADMIN_URL.'/member_list.php";
}, 3000);
</script>';