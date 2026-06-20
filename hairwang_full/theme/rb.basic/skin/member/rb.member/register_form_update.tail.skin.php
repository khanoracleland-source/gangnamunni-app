<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

//----------------------------------------------------------
// 회원 유형별 추가 정보 저장
//----------------------------------------------------------
if($w == "" || $_POST['re'] == "re") {
    // 회원 유형 저장 (mb_1 필드 사용)
    $mb_type = isset($_POST['mb_type']) ? $_POST['mb_type'] : 'general';
    
    // 파일 업로드 처리
    $upload_dir = G5_DATA_PATH.'/member_cert/'.$mb_id;
    
    // 디렉토리 생성
    if(!is_dir($upload_dir)) {
        @mkdir($upload_dir, G5_DIR_PERMISSION);
        @chmod($upload_dir, G5_DIR_PERMISSION);
    }
    
    $mb_4 = ''; // (일반사용자용 - 사용안함)
    $mb_8 = ''; // 병원 인증서류 파일 경로

    // 일반사용자는 파일 업로드 없음
    // if($mb_type == 'general') { }

    // 병원관계자 파일 업로드 처리
    if($mb_type == 'hospital' && isset($_FILES['mb_hospital_cert']) && $_FILES['mb_hospital_cert']['name']) {
        $file = $_FILES['mb_hospital_cert'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // 허용 확장자 검사
        $allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'pdf');
        if(in_array($ext, $allowed_ext)) {
            $file_name = 'hospital_cert_'.time().'.'.$ext;
            $upload_file = $upload_dir.'/'.$file_name;

            if(move_uploaded_file($file['tmp_name'], $upload_file)) {
                $mb_8 = $file_name;
            }
        }
    }
    
    // 회원 정보 업데이트
    $mb_2 = isset($_POST['mb_2']) ? $_POST['mb_2'] : '';
    $mb_3 = isset($_POST['mb_3']) ? $_POST['mb_3'] : '';
    $mb_5 = isset($_POST['mb_5']) ? $_POST['mb_5'] : '';
    $mb_6 = isset($_POST['mb_6']) ? $_POST['mb_6'] : '';
    $mb_7 = isset($_POST['mb_7']) ? $_POST['mb_7'] : '';
    
    $sql_update = "UPDATE {$g5['member_table']} SET 
                    mb_1 = '{$mb_type}',
                    mb_2 = '{$mb_2}',
                    mb_3 = '{$mb_3}',
                    mb_4 = '{$mb_4}',
                    mb_5 = '{$mb_5}',
                    mb_6 = '{$mb_6}',
                    mb_7 = '{$mb_7}',
                    mb_8 = '{$mb_8}'
                    WHERE mb_id = '{$mb_id}'";
    sql_query($sql_update);
    
    // 회원 유형별 추가 처리
    if($mb_type == 'partner') {
        // 입점사 회원 처리 (기존 코드 유지)
        if(isset($pa['pa_is']) && $pa['pa_is'] == 1 && isset($pa['pa_use']) && $pa['pa_use'] == 1) {

            if(isset($pa['pa_add_use']) && $pa['pa_add_use'] == 1) {
                if(isset($pa['pa_level']) && $pa['pa_level']) { 
                    $re_level = $pa['pa_level'];
                } else { 
                    $re_level = 4; // 입점사 기본 레벨 4
                }
                
                memo_auto_send('입점 신청이 승인 되었습니다.', '', $mb_id, "system-msg");

            } else { 
                $re_level = 1; // 승인 전까지는 레벨 1
            }

            $sqls = "UPDATE {$g5['member_table']} 
                        SET mb_partner = '1',
                            mb_partner_add_time = '" . G5_TIME_YMDHIS . "',
                            mb_bank = '{$_POST['mb_bank']}',
                            mb_level = '{$re_level}' 
                            WHERE mb_id = '{$mb_id}' ";
            sql_query($sqls);
            
            memo_auto_send('입점 신청이 접수 되었습니다.', '', $config['cf_admin'], "system-msg");
        }
    } else {
        // 일반사용자, 병원관계자는 레벨 1로 설정
        $sql = "UPDATE {$g5['member_table']} SET mb_level = '1' WHERE mb_id = '{$mb_id}'";
        sql_query($sql);
    }
    
    //----------------------------------------------------------
    // 추천인 포인트 지급 처리 (신규 가입시에만)
    //----------------------------------------------------------
    if ($w == "" && $mb_recommend) {
        // 추천인이 실제 존재하는지 확인
        $sql = "SELECT mb_id, mb_nick, mb_email FROM {$g5['member_table']} WHERE mb_id = '".sql_real_escape_string($mb_recommend)."'";
        $recommend_member = sql_fetch($sql);
        
        if ($recommend_member['mb_id']) {
            // 1. 추천인에게 포인트 지급 (1,000 포인트)
            $recommend_point = 1000;
            insert_point(
                $recommend_member['mb_id'], 
                $recommend_point, 
                $mb_id.'님 추천', 
                '@member', 
                $mb_id, 
                '회원추천'
            );
            
            // 2. 신규가입자에게 추천 보너스 포인트 지급 (500 포인트)
            $new_member_point = 500;
            insert_point(
                $mb_id, 
                $new_member_point, 
                $recommend_member['mb_nick'].'님 추천으로 가입', 
                '@member', 
                $recommend_member['mb_id'], 
                '추천가입보너스'
            );
            
            // 3. 추천인에게 쪽지 알림
            $memo_content = "[추천인 포인트 지급 알림]\n\n";
            $memo_content .= "{$mb_nick}님이 회원님의 추천으로 가입하였습니다.\n";
            $memo_content .= "추천 포인트 ".number_format($recommend_point)."P가 지급되었습니다.\n\n";
            $memo_content .= "감사합니다.";
            
            sql_query("INSERT INTO {$g5['memo_table']} SET 
                        me_recv_mb_id = '{$recommend_member['mb_id']}',
                        me_send_mb_id = '{$config['cf_admin']}',
                        me_send_datetime = '".G5_TIME_YMDHIS."',
                        me_memo = '".sql_real_escape_string($memo_content)."',
                        me_type = 'recv'
                    ");
            
            // 4. 신규가입자에게 쪽지 알림
            $memo_content_new = "[추천 가입 보너스 지급]\n\n";
            $memo_content_new .= "{$recommend_member['mb_nick']}님의 추천으로 가입해주셔서 감사합니다.\n";
            $memo_content_new .= "추천 가입 보너스 ".number_format($new_member_point)."P가 지급되었습니다.\n\n";
            $memo_content_new .= "헤어왕을 이용해 주셔서 감사합니다.";
            
            sql_query("INSERT INTO {$g5['memo_table']} SET 
                        me_recv_mb_id = '{$mb_id}',
                        me_send_mb_id = '{$config['cf_admin']}',
                        me_send_datetime = '".G5_TIME_YMDHIS."',
                        me_memo = '".sql_real_escape_string($memo_content_new)."',
                        me_type = 'recv'
                    ");
            
            // 5. 사용한 쿠키와 세션 정리
            set_cookie('reg_mb_recommend', '', 0);
            set_session('ss_mb_recommend', '');
        }
    }
    
} else if ($w == "u") {
    // 회원정보 수정 시
    
    // 파일 삭제 처리 (병원관계자 인증서류)
    if(isset($_POST['del_mb_hospital_cert']) && $_POST['del_mb_hospital_cert']) {
        $upload_dir = G5_DATA_PATH.'/member_cert/'.$mb_id;
        @unlink($upload_dir.'/'.$member['mb_8']);

        $sql = "UPDATE {$g5['member_table']} SET mb_8 = '' WHERE mb_id = '{$mb_id}'";
        sql_query($sql);
    }
    
    // 새로운 파일 업로드 처리
    $upload_dir = G5_DATA_PATH.'/member_cert/'.$mb_id;
    
    if(!is_dir($upload_dir)) {
        @mkdir($upload_dir, G5_DIR_PERMISSION);
        @chmod($upload_dir, G5_DIR_PERMISSION);
    }
    
    // 병원 인증서류 업로드
    if(isset($_FILES['mb_hospital_cert']) && $_FILES['mb_hospital_cert']['name']) {
        $file = $_FILES['mb_hospital_cert'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        $allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'pdf');
        if(in_array($ext, $allowed_ext)) {
            // 기존 파일 삭제
            if($member['mb_8']) {
                @unlink($upload_dir.'/'.$member['mb_8']);
            }

            $file_name = 'hospital_cert_'.time().'.'.$ext;
            $upload_file = $upload_dir.'/'.$file_name;

            if(move_uploaded_file($file['tmp_name'], $upload_file)) {
                $sql = "UPDATE {$g5['member_table']} SET mb_8 = '{$file_name}' WHERE mb_id = '{$mb_id}'";
                sql_query($sql);
            }
        }
    }
    
    // 회원 정보 업데이트
    $mb_2 = isset($_POST['mb_2']) ? $_POST['mb_2'] : '';
    $mb_3 = isset($_POST['mb_3']) ? $_POST['mb_3'] : '';
    $mb_5 = isset($_POST['mb_5']) ? $_POST['mb_5'] : '';
    $mb_6 = isset($_POST['mb_6']) ? $_POST['mb_6'] : '';
    $mb_7 = isset($_POST['mb_7']) ? $_POST['mb_7'] : '';
    
    $sql_update = "UPDATE {$g5['member_table']} SET 
                    mb_2 = '{$mb_2}',
                    mb_3 = '{$mb_3}',
                    mb_5 = '{$mb_5}',
                    mb_6 = '{$mb_6}',
                    mb_7 = '{$mb_7}'
                    WHERE mb_id = '{$mb_id}'";
    sql_query($sql_update);
    
    // 입점사 정보 업데이트
    if(isset($pa['pa_is']) && $pa['pa_is'] == 1 && isset($pa['pa_use']) && $pa['pa_use'] == 1) {
        $mb_bank = isset($_POST['mb_bank']) ? $_POST['mb_bank'] : '';
        $sqls = "UPDATE {$g5['member_table']} 
                    SET mb_bank = '{$mb_bank}' 
                    WHERE mb_id = '{$mb_id}' ";
        sql_query($sqls);
    }
}

//----------------------------------------------------------
// SMS 문자전송 시작
//----------------------------------------------------------
$sms_contents = $default['de_sms_cont1'];
$sms_contents = str_replace("{이름}", $mb_name, $sms_contents);
$sms_contents = str_replace("{회원아이디}", $mb_id, $sms_contents);
$sms_contents = str_replace("{회사명}", $default['de_admin_company_name'], $sms_contents);

// 핸드폰번호에서 숫자만 취한다
$receive_number = preg_replace("/[^0-9]/", "", $mb_hp);  // 수신자번호 (회원님의 핸드폰번호)
$send_number = preg_replace("/[^0-9]/", "", $default['de_admin_company_tel']); // 발신자번호

if ($w == "" && $default['de_sms_use1'] && $receive_number)
{
	if ($config['cf_sms_use'] == 'icode')
	{
		if($config['cf_sms_type'] == 'LMS') {
            include_once(G5_LIB_PATH.'/icode.lms.lib.php');

            $port_setting = get_icode_port_type($config['cf_icode_id'], $config['cf_icode_pw']);

            // SMS 모듈 클래스 생성
            if($port_setting !== false) {
                $SMS = new LMS;
                $SMS->SMS_con($config['cf_icode_server_ip'], $config['cf_icode_id'], $config['cf_icode_pw'], $port_setting);

                $strDest     = array();
                $strDest[]   = $receive_number;
                $strCallBack = $send_number;
                $strCaller   = iconv_euckr(trim($default['de_admin_company_name']));
                $strSubject  = '';
                $strURL      = '';
                $strData     = iconv_euckr($sms_contents);
                $strDate     = '';
                $nCount      = count($strDest);

                $res = $SMS->Add($strDest, $strCallBack, $strCaller, $strSubject, $strURL, $strData, $strDate, $nCount);

                $SMS->Send();
                $SMS->Init(); // 보관하고 있던 결과값을 지웁니다.
            }
        } else {
            include_once(G5_LIB_PATH.'/icode.sms.lib.php');

            $SMS = new SMS; // SMS 연결
            $SMS->SMS_con($config['cf_icode_server_ip'], $config['cf_icode_id'], $config['cf_icode_pw'], $config['cf_icode_server_port']);
            $SMS->Add($receive_number, $send_number, $config['cf_icode_id'], iconv_euckr(stripslashes($sms_contents)), "");
            $SMS->Send();
            $SMS->Init(); // 보관하고 있던 결과값을 지웁니다.
        }
	}
}
//----------------------------------------------------------
// SMS 문자전송 끝
//----------------------------------------------------------
?>