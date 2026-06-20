<?php
$sub_menu = "200100";
require_once "./_common.php";
require_once G5_LIB_PATH . "/register.lib.php";
require_once G5_LIB_PATH . '/thumbnail.lib.php';

if ($w == 'u') {
   check_demo();
}

auth_check_menu($auth, $sub_menu, 'w');

check_admin_token();

$mb_id          = isset($_POST['mb_id']) ? trim($_POST['mb_id']) : '';
$mb_password    = isset($_POST['mb_password']) ? trim($_POST['mb_password']) : '';
$mb_certify_case = isset($_POST['mb_certify_case']) ? preg_replace('/[^0-9a-z_]/i', '', $_POST['mb_certify_case']) : '';
$mb_certify     = isset($_POST['mb_certify']) ? preg_replace('/[^0-9a-z_]/i', '', $_POST['mb_certify']) : '';
$mb_zip         = isset($_POST['mb_zip']) ? preg_replace('/[^0-9a-z_]/i', '', $_POST['mb_zip']) : '';

// 관리자가 자동등록방지를 사용해야 할 경우 (회원의 비밀번호 변경시 캡챠 체크)
if ($mb_password && function_exists('get_admin_captcha_by') && get_admin_captcha_by()) {
   include_once(G5_CAPTCHA_PATH . '/captcha.lib.php');
   if (!chk_captcha()) {
       alert('자동등록방지 숫자가 틀렸습니다.');
   }
}

// 휴대폰번호 체크
$mb_hp = hyphen_hp_number($_POST['mb_hp']);
if ($mb_hp) {
   $result = exist_mb_hp($mb_hp, $mb_id);
   if ($result) {
       alert($result);
   }
}

// 인증정보 처리
if ($mb_certify_case && $mb_certify) {
   $mb_certify = isset($_POST['mb_certify_case']) ? preg_replace('/[^0-9a-z_]/i', '', (string)$_POST['mb_certify_case']) : '';
   $mb_adult = isset($_POST['mb_adult']) ? preg_replace('/[^0-9a-z_]/i', '', (string)$_POST['mb_adult']) : '';
} else {
   $mb_certify = '';
   $mb_adult = 0;
}

$mb_zip1 = substr($mb_zip, 0, 3);
$mb_zip2 = substr($mb_zip, 3);

$mb_email = isset($_POST['mb_email']) ? get_email_address(trim($_POST['mb_email'])) : '';
$mb_nick = isset($_POST['mb_nick']) ? trim(strip_tags($_POST['mb_nick'])) : '';

if ($msg = valid_mb_nick($mb_nick)) {
   alert($msg, "", true, true);
}

$posts = array();
$check_keys = array(
   'mb_name',
   'mb_homepage',
   'mb_tel',
   'mb_addr1',
   'mb_addr2',
   'mb_addr3',
   'mb_addr_jibeon',
   'mb_signature',
   'mb_leave_date',
   'mb_intercept_date',
   'mb_mailling',
   'mb_sms',
   'mb_open',
   'mb_profile',
   'mb_level'
);

// 여분 필드는 회원 유형에 따라 다르게 처리
for ($i = 1; $i <= 10; $i++) {
   $check_keys[] = 'mb_' . $i;
}

foreach ($check_keys as $key) {
   if (in_array($key, array('mb_signature', 'mb_profile'))) {
       // 서명, 소개글은 HTML태그 필터 처리
       $posts[$key] = isset($_POST[$key]) ? clean_xss_tags($_POST[$key], 1, 1, 0, 0) : '';
   } else {
       $posts[$key] = isset($_POST[$key]) ? clean_xss_tags($_POST[$key], 1, 1) : '';
   }
}

$mb_memo = isset($_POST['mb_memo']) ? $_POST['mb_memo'] : '';

// 입점사 회원 전용 필드 처리
$mb_bank = isset($_POST['mb_bank']) ? clean_xss_tags($_POST['mb_bank'], 1, 1) : '';

// ======================================================
// 수정 모드일 때 회원 유형 처리 (데이터 유실 방지)
// ======================================================
if ($w == 'u') {
   $original_mb = get_member($mb_id);
   
   // 회원 유형이 있는 경우 변경 방지 (강제 변경 옵션이 없는 경우)
   if ($original_mb['mb_1'] && in_array($original_mb['mb_1'], array('student', 'designer', 'partner'))) {
       // 관리자가 명시적으로 변경하려는 경우가 아니면 원래 값 유지
       if (!isset($_POST['mb_type_force_change'])) {
           $posts['mb_1'] = $original_mb['mb_1'];
       }
   }
   
   // ======================================================
   // 데이터 유실 방지: 기존 데이터 보존
   // ======================================================
   
   // 회원 유형별 필드 처리 - hidden 필드나 기존 값 사용
   if ($posts['mb_1'] == 'student') {
       // 학생회원 필드는 입력값 사용
       $posts['mb_2'] = isset($_POST['mb_2']) && $_POST['mb_2'] !== '' ? $_POST['mb_2'] : (isset($_POST['mb_2_hidden']) ? $_POST['mb_2_hidden'] : '');
       $posts['mb_3'] = isset($_POST['mb_3']) && $_POST['mb_3'] !== '' ? $_POST['mb_3'] : (isset($_POST['mb_3_hidden']) ? $_POST['mb_3_hidden'] : '');
       // mb_4는 파일명이므로 그대로 유지
       $posts['mb_4'] = $original_mb['mb_4'];
   } else {
       // 학생이 아닌 경우에도 기존 학생 데이터는 유지
       $posts['mb_2'] = $original_mb['mb_2'];
       $posts['mb_3'] = $original_mb['mb_3'];
       $posts['mb_4'] = $original_mb['mb_4'];
   }
   
   if ($posts['mb_1'] == 'designer') {
       // 헤어디자이너 필드는 입력값 사용
       $posts['mb_5'] = isset($_POST['mb_5']) && $_POST['mb_5'] !== '' ? $_POST['mb_5'] : (isset($_POST['mb_5_hidden']) ? $_POST['mb_5_hidden'] : '');
       $posts['mb_6'] = isset($_POST['mb_6']) && $_POST['mb_6'] !== '' ? $_POST['mb_6'] : (isset($_POST['mb_6_hidden']) ? $_POST['mb_6_hidden'] : '');
       $posts['mb_7'] = isset($_POST['mb_7']) && $_POST['mb_7'] !== '' ? $_POST['mb_7'] : (isset($_POST['mb_7_hidden']) ? $_POST['mb_7_hidden'] : '');
       // mb_8은 파일명이므로 그대로 유지
       $posts['mb_8'] = $original_mb['mb_8'];
   } else {
       // 디자이너가 아닌 경우에도 기존 디자이너 데이터는 유지
       $posts['mb_5'] = $original_mb['mb_5'];
       $posts['mb_6'] = $original_mb['mb_6'];
       $posts['mb_7'] = $original_mb['mb_7'];
       $posts['mb_8'] = $original_mb['mb_8'];
   }
   
   // mb_9, mb_10은 특별한 용도로 사용 중이므로 별도 처리
   $posts['mb_9'] = $original_mb['mb_9'];  // 기존 값 유지
   
   // 레벨 변경 시 mb_10 필드에 변경일시 기록
   if ($original_mb['mb_level'] != $posts['mb_level']) {
       $posts['mb_10'] = G5_TIME_YMDHIS;
   } else {
       $posts['mb_10'] = $original_mb['mb_10'];  // 기존 값 유지
   }
} else {
   // 신규 가입인 경우에는 입력된 값만 처리
   // mb_4, mb_8은 파일 업로드 처리 후 설정됨
   $posts['mb_4'] = '';
   $posts['mb_8'] = '';
}

// SQL common 부분 구성
$sql_common = "  mb_name = '{$posts['mb_name']}',
                mb_nick = '{$mb_nick}',
                mb_email = '{$mb_email}',
                mb_homepage = '{$posts['mb_homepage']}',
                mb_tel = '{$posts['mb_tel']}',
                mb_hp = '{$mb_hp}',
                mb_certify = '{$mb_certify}',
                mb_adult = '{$mb_adult}',
                mb_zip1 = '$mb_zip1',
                mb_zip2 = '$mb_zip2',
                mb_addr1 = '{$posts['mb_addr1']}',
                mb_addr2 = '{$posts['mb_addr2']}',
                mb_addr3 = '{$posts['mb_addr3']}',
                mb_addr_jibeon = '{$posts['mb_addr_jibeon']}',
                mb_signature = '{$posts['mb_signature']}',
                mb_leave_date = '{$posts['mb_leave_date']}',
                mb_intercept_date = '{$posts['mb_intercept_date']}',
                mb_memo = '{$mb_memo}',
                mb_mailling = '{$posts['mb_mailling']}',
                mb_sms = '{$posts['mb_sms']}',
                mb_open = '{$posts['mb_open']}',
                mb_profile = '{$posts['mb_profile']}',
                mb_level = '{$posts['mb_level']}',
                mb_1 = '{$posts['mb_1']}',
                mb_2 = '{$posts['mb_2']}',
                mb_3 = '{$posts['mb_3']}',
                mb_4 = '{$posts['mb_4']}',
                mb_5 = '{$posts['mb_5']}',
                mb_6 = '{$posts['mb_6']}',
                mb_7 = '{$posts['mb_7']}',
                mb_8 = '{$posts['mb_8']}',
                mb_9 = '{$posts['mb_9']}',
                mb_10 = '{$posts['mb_10']}' ";

// 입점사 회원인 경우 mb_bank 필드 처리
if ($posts['mb_1'] == 'partner') {
   $mb_bank = isset($_POST['mb_bank']) && $_POST['mb_bank'] !== '' ? $_POST['mb_bank'] : (isset($_POST['mb_bank_hidden']) ? $_POST['mb_bank_hidden'] : '');
   $sql_common .= ", mb_bank = '{$mb_bank}' ";
} else if ($w == 'u') {
   // 입점사가 아닌 경우에도 기존 mb_bank 값 유지
   $sql_common .= ", mb_bank = '{$original_mb['mb_bank']}' ";
}

if ($w == '') {
   // 회원가입(관리자 직접 추가)
   $mb = get_member($mb_id);
   if (isset($mb['mb_id']) && $mb['mb_id']) {
       alert('이미 존재하는 회원아이디입니다.\\nＩＤ : ' . $mb['mb_id'] . '\\n이름 : ' . $mb['mb_name'] . '\\n닉네임 : ' . $mb['mb_nick'] . '\\n메일 : ' . $mb['mb_email']);
   }
   // 닉네임 중복 체크
   $sql = " select mb_id, mb_name, mb_nick, mb_email from {$g5['member_table']} where mb_nick = '{$mb_nick}' ";
   $row = sql_fetch($sql);
   if (isset($row['mb_id']) && $row['mb_id']) {
       alert('이미 존재하는 닉네임입니다.\\nＩＤ : ' . $row['mb_id'] . '\\n이름 : ' . $row['mb_name'] . '\\n닉네임 : ' . $row['mb_nick'] . '\\n메일 : ' . $row['mb_email']);
   }
   // 이메일 중복 체크
   $sql = " select mb_id, mb_name, mb_nick, mb_email from {$g5['member_table']} where mb_email = '{$mb_email}' ";
   $row = sql_fetch($sql);
   if (isset($row['mb_id']) && $row['mb_id']) {
       alert('이미 존재하는 이메일입니다.\\nＩＤ : ' . $row['mb_id'] . '\\n이름 : ' . $row['mb_name'] . '\\n닉네임 : ' . $row['mb_nick'] . '\\n메일 : ' . $row['mb_email']);
   }
   sql_query(" insert into {$g5['member_table']} 
                   set mb_id = '{$mb_id}', 
                       mb_password = '" . get_encrypt_string($mb_password) . "', 
                       mb_datetime = '" . G5_TIME_YMDHIS . "', 
                       mb_ip = '{$_SERVER['REMOTE_ADDR']}', 
                       mb_email_certify = '" . G5_TIME_YMDHIS . "', 
                       {$sql_common} ");
} elseif ($w == 'u') {
   // 회원정보 수정
   $mb = get_member($mb_id);
   if (!(isset($mb['mb_id']) && $mb['mb_id'])) {
       alert('존재하지 않는 회원자료입니다.');
   }
   if ($is_admin != 'super' && $mb['mb_level'] >= $member['mb_level']) {
       alert('자신보다 권한이 높거나 같은 회원은 수정할 수 없습니다.');
   }
   if ($is_admin !== 'super' && is_admin($mb['mb_id']) === 'super') {
       alert('최고관리자의 비밀번호를 수정할 수 없습니다.');
   }
   if ($mb_id === $member['mb_id'] && $_POST['mb_level'] != $mb['mb_level']) {
       alert($mb['mb_id'] . ' : 로그인 중인 관리자 레벨은 수정할 수 없습니다.');
   }
   if ($posts['mb_leave_date'] || $posts['mb_intercept_date']){
       if ($member['mb_id'] === $mb['mb_id'] || is_admin($mb['mb_id']) === 'super'){
           alert('해당 관리자의 탈퇴 일자 또는 접근 차단 일자를 수정할 수 없습니다.');
       }
   }
   // 닉네임 중복 체크
   $sql = " select mb_id, mb_name, mb_nick, mb_email 
              from {$g5['member_table']} 
             where mb_nick = '{$mb_nick}' 
               and mb_id <> '$mb_id' ";
   $row = sql_fetch($sql);
   if (isset($row['mb_id']) && $row['mb_id']) {
       alert('이미 존재하는 닉네임입니다.\\nＩＤ : ' . $row['mb_id'] . '\\n이름 : ' . $row['mb_name'] . '\\n닉네임 : ' . $row['mb_nick'] . '\\n메일 : ' . $row['mb_email']);
   }
   // 이메일 중복 체크
   $sql = " select mb_id, mb_name, mb_nick, mb_email 
              from {$g5['member_table']} 
             where mb_email = '{$mb_email}' 
               and mb_id <> '$mb_id' ";
   $row = sql_fetch($sql);
   if (isset($row['mb_id']) && $row['mb_id']) {
       alert('이미 존재하는 이메일입니다.\\nＩＤ : ' . $row['mb_id'] . '\\n이름 : ' . $row['mb_name'] . '\\n닉네임 : ' . $row['mb_nick'] . '\\n메일 : ' . $row['mb_email']);
   }
   if ($mb_password) {
       $sql_password = " , mb_password = '" . get_encrypt_string($mb_password) . "' ";
   } else {
       $sql_password = "";
   }
   if (isset($_POST['passive_certify']) && $_POST['passive_certify']) {
       $sql_certify = " , mb_email_certify = '" . G5_TIME_YMDHIS . "' ";
   } else {
       $sql_certify = "";
   }
   $sql = " update {$g5['member_table']}
               set {$sql_common}
                    {$sql_password}
                    {$sql_certify}
             where mb_id = '{$mb_id}' ";
   sql_query($sql);
} else {
   alert('제대로 된 값이 넘어오지 않았습니다.');
}

// ======================================================
// 파일 업로드 처리
// ======================================================

if ($w == '' || $w == 'u') {
   // 회원 아이콘/이미지 처리
   $mb_dir = substr($mb_id, 0, 2);
   $mb_icon_img = get_mb_icon_name($mb_id) . '.gif';

   // 회원 아이콘 삭제
   if (isset($_POST['del_mb_icon']) && $_POST['del_mb_icon']) {
       @unlink(G5_DATA_PATH . '/member/' . $mb_dir . '/' . $mb_icon_img);
   }
   $image_regex = "/(\.(gif|jpe?g|png))$/i";
   // 아이콘 업로드
   if (isset($_FILES['mb_icon']) && is_uploaded_file($_FILES['mb_icon']['tmp_name'])) {
       if (!preg_match($image_regex, $_FILES['mb_icon']['name'])) {
           alert($_FILES['mb_icon']['name'] . '은(는) 이미지 파일이 아닙니다.');
       }
       if (preg_match($image_regex, $_FILES['mb_icon']['name'])) {
           $mb_icon_dir = G5_DATA_PATH . '/member/' . $mb_dir;
           @mkdir($mb_icon_dir, G5_DIR_PERMISSION);
           @chmod($mb_icon_dir, G5_DIR_PERMISSION);
           $dest_path = $mb_icon_dir . '/' . $mb_icon_img;
           move_uploaded_file($_FILES['mb_icon']['tmp_name'], $dest_path);
           chmod($dest_path, G5_FILE_PERMISSION);
           if (file_exists($dest_path)) {
               $size = @getimagesize($dest_path);
               if ($size) {
                   if ($size[0] > $config['cf_member_icon_width'] || $size[1] > $config['cf_member_icon_height']) {
                       $thumb = null;
                       if ($size[2] === 2 || $size[2] === 3) {
                           $thumb = thumbnail($mb_icon_img, $mb_icon_dir, $mb_icon_dir, $config['cf_member_icon_width'], $config['cf_member_icon_height'], true, true);
                           if ($thumb) {
                               @unlink($dest_path);
                               rename($mb_icon_dir . '/' . $thumb, $dest_path);
                           }
                       }
                       if (!$thumb) {
                           @unlink($dest_path);
                       }
                   }
               }
           }
       }
   }
   $mb_img_dir = G5_DATA_PATH . '/member_image/';
   if (!is_dir($mb_img_dir)) {
       @mkdir($mb_img_dir, G5_DIR_PERMISSION);
       @chmod($mb_img_dir, G5_DIR_PERMISSION);
   }
   $mb_img_dir .= $mb_dir;
   // 회원이미지 삭제
   if (isset($_POST['del_mb_img']) && $_POST['del_mb_img']) {
       @unlink($mb_img_dir . '/' . $mb_icon_img);
   }
   // 회원이미지 업로드
   if (isset($_FILES['mb_img']) && is_uploaded_file($_FILES['mb_img']['tmp_name'])) {
       if (!preg_match($image_regex, $_FILES['mb_img']['name'])) {
           alert($_FILES['mb_img']['name'] . '은(는) 이미지 파일이 아닙니다.');
       }
       if (preg_match($image_regex, $_FILES['mb_img']['name'])) {
           @mkdir($mb_img_dir, G5_DIR_PERMISSION);
           @chmod($mb_img_dir, G5_DIR_PERMISSION);
           $dest_path = $mb_img_dir . '/' . $mb_icon_img;
           move_uploaded_file($_FILES['mb_img']['tmp_name'], $dest_path);
           chmod($dest_path, G5_FILE_PERMISSION);
           if (file_exists($dest_path)) {
               $size = @getimagesize($dest_path);
               if ($size) {
                   if ($size[0] > $config['cf_member_img_width'] || $size[1] > $config['cf_member_img_height']) {
                       $thumb = null;
                       if ($size[2] === 2 || $size[2] === 3) {
                           $thumb = thumbnail($mb_icon_img, $mb_img_dir, $mb_img_dir, $config['cf_member_img_width'], $config['cf_member_img_height'], true, true);
                           if ($thumb) {
                               @unlink($dest_path);
                               rename($mb_img_dir . '/' . $thumb, $dest_path);
                           }
                       }
                       if (!$thumb) {
                           @unlink($dest_path);
                       }
                   }
               }
           }
       }
   }

   // ======================================================
   // 회원 유형별 파일 처리 (mb_1 필드 기준)
   // ======================================================
   $mb2 = get_member($mb_id); // 회원 정보 재조회
   
   // 상위 디렉토리 먼저 생성
   $member_cert_dir = G5_DATA_PATH.'/member_cert';
   if(!is_dir($member_cert_dir)) {
       @mkdir($member_cert_dir, G5_DIR_PERMISSION);
       @chmod($member_cert_dir, G5_DIR_PERMISSION);
   }
   
   $upload_dir = G5_DATA_PATH.'/member_cert/'.$mb_id;
   
   // 회원별 디렉토리 생성
   if(!is_dir($upload_dir)) {
       @mkdir($upload_dir, G5_DIR_PERMISSION);
       @chmod($upload_dir, G5_DIR_PERMISSION);
   }
   
   // 학생회원 파일 처리
   if ($mb2['mb_1'] == 'student') {
       // 학생증 파일 삭제
       if (isset($_POST['del_mb_student_cert']) && $_POST['del_mb_student_cert']) {
           if ($mb2['mb_4']) {
               @unlink($upload_dir.'/'.$mb2['mb_4']);
               $sql = "UPDATE {$g5['member_table']} SET mb_4 = '' WHERE mb_id = '{$mb_id}'";
               sql_query($sql);
           }
       }
       
       // 학생증 파일 업로드
       if (isset($_FILES['mb_student_cert']) && is_uploaded_file($_FILES['mb_student_cert']['tmp_name'])) {
           $file = $_FILES['mb_student_cert'];
           $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
           
           // 허용 확장자 검사
           $allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'pdf');
           if(in_array($ext, $allowed_ext)) {
               // 기존 파일 삭제
               if($mb2['mb_4']) {
                   @unlink($upload_dir.'/'.$mb2['mb_4']);
               }
               
               $file_name = 'student_cert_'.time().'.'.$ext;
               $upload_file = $upload_dir.'/'.$file_name;
               
               if(move_uploaded_file($file['tmp_name'], $upload_file)) {
                   @chmod($upload_file, G5_FILE_PERMISSION);
                   $sql = "UPDATE {$g5['member_table']} SET mb_4 = '{$file_name}' WHERE mb_id = '{$mb_id}'";
                   sql_query($sql);
               } else {
                   alert('학생증 파일 업로드에 실패했습니다.');
               }
           } else {
               alert('학생증 파일은 jpg, jpeg, png, gif, pdf 형식만 업로드 가능합니다.');
           }
       }
   }
   
   // 헤어디자이너 파일 처리
   if ($mb2['mb_1'] == 'designer') {
       // 자격증 파일 삭제
       if (isset($_POST['del_mb_designer_cert']) && $_POST['del_mb_designer_cert']) {
           if ($mb2['mb_8']) {
               @unlink($upload_dir.'/'.$mb2['mb_8']);
               $sql = "UPDATE {$g5['member_table']} SET mb_8 = '' WHERE mb_id = '{$mb_id}'";
               sql_query($sql);
           }
       }
       
       // 자격증 파일 업로드
       if (isset($_FILES['mb_designer_cert']) && is_uploaded_file($_FILES['mb_designer_cert']['tmp_name'])) {
           $file = $_FILES['mb_designer_cert'];
           $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
           
           // 허용 확장자 검사
           $allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'pdf');
           if(in_array($ext, $allowed_ext)) {
               // 기존 파일 삭제
               if($mb2['mb_8']) {
                   @unlink($upload_dir.'/'.$mb2['mb_8']);
               }
               
               // 디렉토리 쓰기 권한 확인
               if(!is_writable($upload_dir)) {
                   alert('디렉토리에 쓰기 권한이 없습니다. 경로: '.$upload_dir);
               }
               
               $file_name = 'designer_cert_'.time().'.'.$ext;
               $upload_file = $upload_dir.'/'.$file_name;
               
               // 파일 업로드 시도
               if(move_uploaded_file($file['tmp_name'], $upload_file)) {
                   @chmod($upload_file, G5_FILE_PERMISSION);
                   $sql = "UPDATE {$g5['member_table']} SET mb_8 = '{$file_name}' WHERE mb_id = '{$mb_id}'";
                   sql_query($sql);
               } else {
                   // 상세한 에러 정보 출력
                   $upload_error = '';
                   switch($file['error']) {
                       case UPLOAD_ERR_INI_SIZE:
                           $upload_error = 'php.ini의 upload_max_filesize 제한 초과';
                           break;
                       case UPLOAD_ERR_FORM_SIZE:
                           $upload_error = 'HTML 폼의 MAX_FILE_SIZE 제한 초과';
                           break;
                       case UPLOAD_ERR_PARTIAL:
                           $upload_error = '파일이 부분적으로만 업로드됨';
                           break;
                       case UPLOAD_ERR_NO_FILE:
                           $upload_error = '파일이 업로드되지 않음';
                           break;
                       case UPLOAD_ERR_NO_TMP_DIR:
                           $upload_error = '임시 폴더가 없음';
                           break;
                       case UPLOAD_ERR_CANT_WRITE:
                           $upload_error = '디스크에 파일 쓰기 실패';
                           break;
                       case UPLOAD_ERR_EXTENSION:
                           $upload_error = 'PHP 확장에 의해 파일 업로드 중단됨';
                           break;
                       default:
                           $upload_error = '알 수 없는 오류 (코드: '.$file['error'].')';
                   }
                   
                   alert('자격증 파일 업로드에 실패했습니다.\n에러: '.$upload_error.'\n대상 경로: '.$upload_file);
               }
           } else {
               alert('자격증 파일은 jpg, jpeg, png, gif, pdf 형식만 업로드 가능합니다.');
           }
       }
   }
}

if (function_exists('get_admin_captcha_by')) {
   get_admin_captcha_by('remove');
}

run_event('admin_member_form_update', $w, $mb_id);

goto_url('./member_form.php?' . $qstr . '&amp;w=u&amp;mb_id=' . $mb_id, false);
?>