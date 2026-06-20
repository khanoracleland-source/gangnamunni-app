<?php
$sub_menu = "200150";
include_once('./_common.php');

// 디버깅을 위한 에러 표시
error_reporting(E_ALL);
ini_set('display_errors', 1);

check_demo();

// auth 배열 키 존재 확인 후 권한 체크
if (isset($auth[$sub_menu])) {
    auth_check($auth[$sub_menu], 'w');
} else {
    // 권한 정보가 없을 경우 기본 처리 (관리자만 접근 가능하도록)
    if (!isset($member['mb_level']) || $member['mb_level'] < 10) {
        alert('접근 권한이 없습니다.');
    }
}

// check_token(); // 토큰 체크 임시 제거

// 변수 초기화
$msg = '';
$act_button = isset($_POST['act_button']) ? $_POST['act_button'] : '';

// 승인대기 전체삭제 처리
if ($act_button == "승인대기 전체삭제") {
    // 승인대기 회원 직접 삭제 (mb_9가 빈 값이고 mb_1이 빈 값이 아닌 경우)
    // get_member() 함수를 사용하지 않고 직접 처리
    $sql = " select mb_id, mb_no, mb_level from {$g5['member_table']} where mb_9 = '' and mb_1 != '' ";
    $result = sql_query($sql);
    
    $deleted_count = 0;
    $current_mb_id = isset($member['mb_id']) ? $member['mb_id'] : '';
    
    while($row = sql_fetch_array($result)) {
        // 현재 로그인한 관리자는 삭제하지 않음
        if ($current_mb_id && $current_mb_id == $row['mb_id']) continue;
        
        // 최고관리자는 삭제하지 않음
        if (is_admin($row['mb_id']) == 'super') continue;
        
        // 회원 인증서류 삭제
        $cert_dir = G5_DATA_PATH.'/member_cert/'.$row['mb_id'];
        if(is_dir($cert_dir)) {
            @rm_rf($cert_dir);
        }
        
        // 관련 테이블에서 회원 데이터 삭제
        // 포인트 삭제
        if (isset($g5['point_table'])) {
            sql_query(" delete from {$g5['point_table']} where mb_id = '{$row['mb_id']}' ", false);
        }
        
        // 메모 삭제
        if (isset($g5['memo_table'])) {
            sql_query(" delete from {$g5['memo_table']} where me_recv_mb_id = '{$row['mb_id']}' or me_send_mb_id = '{$row['mb_id']}' ", false);
        }
        
        // 스크랩 삭제
        if (isset($g5['scrap_table'])) {
            sql_query(" delete from {$g5['scrap_table']} where mb_id = '{$row['mb_id']}' ", false);
        }
        
        // 그룹 멤버 삭제
        if (isset($g5['group_member_table'])) {
            sql_query(" delete from {$g5['group_member_table']} where mb_id = '{$row['mb_id']}' ", false);
        }
        
        // 로그인 기록 삭제
        if (isset($g5['login_table'])) {
            sql_query(" delete from {$g5['login_table']} where mb_id = '{$row['mb_id']}' ", false);
        }
        
        // 회원 삭제
        sql_query(" delete from {$g5['member_table']} where mb_id = '{$row['mb_id']}' ");
        
        $deleted_count++;
    }
    
    alert("승인대기 회원 {$deleted_count}명이 삭제되었습니다.", './member_type_list.php');
    exit;
}

// 체크박스 선택 확인
if ($act_button != "승인대기 전체삭제") {
    if (!isset($_POST['chk']) || !is_array($_POST['chk']) || count($_POST['chk']) == 0) {
        alert($act_button." 하실 항목을 하나 이상 체크하세요.");
    }
    
    // mb_id 배열 존재 확인
    if (!isset($_POST['mb_id']) || !is_array($_POST['mb_id'])) {
        alert("회원 정보가 없습니다.");
    }
}

if ($act_button == "선택수정") {

    for ($i=0; $i<count($_POST['chk']); $i++) {
        // 실제 번호를 넘김
        $k = $_POST['chk'][$i];

        // mb_id 존재 확인
        if (!isset($_POST['mb_id'][$k])) {
            continue;
        }

        $mb = get_member($_POST['mb_id'][$k]);

        if (!$mb || !isset($mb['mb_id'])) {
            $msg .= $_POST['mb_id'][$k].' : 회원자료가 존재하지 않습니다.\\n';
        } else {
            // mb_level 배열 존재 확인
            $new_level = isset($_POST['mb_level'][$k]) ? $_POST['mb_level'][$k] : $mb['mb_level'];
            
            $sql = " update {$g5['member_table']}
                        set mb_level = '{$new_level}',
                            mb_10 = '".G5_TIME_YMDHIS."'  
                        where mb_id = '{$_POST['mb_id'][$k]}' ";
            sql_query($sql);
        }
    }

} else if ($act_button == "선택삭제") {

    for ($i=0; $i<count($_POST['chk']); $i++) {
        // 실제 번호를 넘김
        $k = $_POST['chk'][$i];

        // mb_id 존재 확인
        if (!isset($_POST['mb_id'][$k])) {
            continue;
        }

        $mb = get_member($_POST['mb_id'][$k]);

        if (!$mb || !isset($mb['mb_id'])) {
            $msg .= $_POST['mb_id'][$k].' : 회원자료가 존재하지 않습니다.\\n';
        } else if (isset($member['mb_id']) && $member['mb_id'] == $mb['mb_id']) {
            $msg .= $mb['mb_id'].' : 로그인 중인 관리자는 삭제 할 수 없습니다.\\n';
        } else if (is_admin($mb['mb_id']) == 'super') {
            $msg .= $mb['mb_id'].' : 최고 관리자는 삭제할 수 없습니다.\\n';
        } else if ($is_admin != 'super' && $mb['mb_level'] >= $member['mb_level']) {
            $msg .= $mb['mb_id'].' : 자신보다 권한이 높거나 같은 회원은 삭제할 수 없습니다.\\n';
        } else {
            // 회원 인증서류 삭제
            $cert_dir = G5_DATA_PATH.'/member_cert/'.$mb['mb_id'];
            if(is_dir($cert_dir)) {
                @rm_rf($cert_dir);
            }
            
            // 회원자료 삭제
            member_delete($mb['mb_id']);
        }
    }
    
} else if ($act_button == "선택승인") {

    for ($i=0; $i<count($_POST['chk']); $i++) {
        // 실제 번호를 넘김
        $k = $_POST['chk'][$i];

        // mb_id 존재 확인
        if (!isset($_POST['mb_id'][$k])) {
            continue;
        }

        $mb = get_member($_POST['mb_id'][$k]);

        if (!$mb || !isset($mb['mb_id'])) {
            $msg .= $_POST['mb_id'][$k].' : 회원자료가 존재하지 않습니다.\\n';
        } else {
            // 승인 처리 및 레벨 2로 변경
            $sql = " update {$g5['member_table']}
                        set mb_9 = '1',
                            mb_level = '2',
                            mb_10 = '".G5_TIME_YMDHIS."'
                        where mb_id = '{$_POST['mb_id'][$k]}' ";
            sql_query($sql);
            
            // 승인 알림 쪽지 발송
            $mb_msg = '';
            switch($mb['mb_1']) {
                case 'student':
                    $mb_msg = '학생 회원 인증이 승인되었습니다. 이제 학생 회원 전용 서비스를 이용하실 수 있습니다. (레벨이 Lv.2 루키 스타로 상승했습니다!)';
                    break;
                case 'designer':
                    $mb_msg = '헤어디자이너 인증이 승인되었습니다. 이제 전문가 서비스를 이용하실 수 있습니다. (레벨이 Lv.2 루키 스타로 상승했습니다!)';
                    break;
                case 'partner':
                    $mb_msg = '입점사 신청이 승인되었습니다. 이제 판매자 서비스를 이용하실 수 있습니다. (레벨이 Lv.2 루키 스타로 상승했습니다!)';
                    break;
            }
            
            if($mb_msg && function_exists('memo_auto_send')) {
                memo_auto_send($mb_msg, '', $_POST['mb_id'][$k], 'system-msg');
            }
        }
    }
    
} else if ($act_button == "선택거부") {

    for ($i=0; $i<count($_POST['chk']); $i++) {
        // 실제 번호를 넘김
        $k = $_POST['chk'][$i];

        // mb_id 존재 확인
        if (!isset($_POST['mb_id'][$k])) {
            continue;
        }

        $mb = get_member($_POST['mb_id'][$k]);

        if (!$mb || !isset($mb['mb_id'])) {
            $msg .= $_POST['mb_id'][$k].' : 회원자료가 존재하지 않습니다.\\n';
        } else {
            // 거부 처리
            $sql = " update {$g5['member_table']}
                        set mb_9 = '2',
                            mb_10 = '".G5_TIME_YMDHIS."'
                        where mb_id = '{$_POST['mb_id'][$k]}' ";
            sql_query($sql);
            
            // 거부 알림 쪽지 발송
            $mb_msg = '';
            switch($mb['mb_1']) {
                case 'student':
                    $mb_msg = '학생 회원 인증이 거부되었습니다. 제출하신 서류를 다시 확인해주세요.';
                    break;
                case 'designer':
                    $mb_msg = '헤어디자이너 인증이 거부되었습니다. 자격증을 다시 확인해주세요.';
                    break;
                case 'partner':
                    $mb_msg = '입점사 신청이 거부되었습니다. 자세한 사항은 관리자에게 문의해주세요.';
                    break;
            }
            
            if($mb_msg && function_exists('memo_auto_send')) {
                memo_auto_send($mb_msg, '', $_POST['mb_id'][$k], 'system-msg');
            }
        }
    }
}

if ($msg)
    alert($msg);

// POST로 전달된 변수들
$sfl = isset($_POST['sfl']) ? $_POST['sfl'] : '';
$stx = isset($_POST['stx']) ? $_POST['stx'] : '';
$sst = isset($_POST['sst']) ? $_POST['sst'] : '';
$sod = isset($_POST['sod']) ? $_POST['sod'] : '';
$page = isset($_POST['page']) ? $_POST['page'] : '';
$mb_type = isset($_POST['mb_type']) ? $_POST['mb_type'] : '';
$mb_status = isset($_POST['mb_status']) ? $_POST['mb_status'] : '';
$mb_level_param = isset($_POST['mb_level']) ? $_POST['mb_level'] : '';

// 쿼리스트링 생성
$qstr = '';
$qstr .= '&sfl=' . $sfl;
$qstr .= '&stx=' . $stx;
$qstr .= '&sst=' . $sst;
$qstr .= '&sod=' . $sod;
$qstr .= '&page=' . $page;
$qstr .= '&mb_type=' . $mb_type;
$qstr .= '&mb_status=' . $mb_status;
$qstr .= '&mb_level=' . $mb_level_param;

goto_url('./member_type_list.php?'.$qstr);
?>