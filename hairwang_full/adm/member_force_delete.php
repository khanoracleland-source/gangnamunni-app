<?php
$sub_menu = "200100";
include_once('./_common.php');

auth_check_menu($auth, $sub_menu, 'd');

$mb_id = isset($_GET['mb_id']) ? trim($_GET['mb_id']) : '';

if (!$mb_id) {
    alert('회원아이디가 넘어오지 않았습니다.');
}

$mb = get_member($mb_id);

if (!$mb['mb_id']) {
    alert('존재하지 않는 회원입니다.');
}

if ($mb['mb_id'] == $member['mb_id']) {
    alert('자기 자신을 삭제할 수 없습니다.');
}

if ($is_admin != 'super' && $mb['mb_level'] >= $member['mb_level']) {
    alert('자신보다 권한이 높거나 같은 회원은 삭제할 수 없습니다.');
}

// 회원 삭제 전 관련 데이터 처리
// 1. 게시글 작성자 정보 업데이트 (익명처리)
$sql = "UPDATE {$g5['board_new_table']} SET mb_id = '' WHERE mb_id = '{$mb_id}'";
sql_query($sql);

// 모든 게시판의 게시글 작성자 정보 업데이트
$sql = "SELECT bo_table FROM {$g5['board_table']}";
$result = sql_query($sql);
while ($row = sql_fetch_array($result)) {
    $write_table = $g5['write_prefix'] . $row['bo_table'];
    
    // 테이블이 존재하는지 확인
    $sql_check = "SHOW TABLES LIKE '{$write_table}'";
    $table_exists = sql_num_rows(sql_query($sql_check));
    
    if ($table_exists) {
        // 게시글 작성자 익명처리
        $sql_update = "UPDATE {$write_table} SET mb_id = '', wr_name = '탈퇴회원', wr_email = '', wr_homepage = '' WHERE mb_id = '{$mb_id}'";
        sql_query($sql_update);
        
        // 댓글 작성자 익명처리
        $sql_update = "UPDATE {$write_table} SET mb_id = '', wr_name = '탈퇴회원', wr_email = '', wr_homepage = '' WHERE mb_id = '{$mb_id}' AND wr_is_comment = 1";
        sql_query($sql_update);
    }
}

// 2. 포인트 내역 삭제
$sql = "DELETE FROM {$g5['point_table']} WHERE mb_id = '{$mb_id}'";
sql_query($sql);

// 3. 쪽지 삭제
$sql = "DELETE FROM {$g5['memo_table']} WHERE me_recv_mb_id = '{$mb_id}' OR me_send_mb_id = '{$mb_id}'";
sql_query($sql);

// 4. 스크랩 삭제
$sql = "DELETE FROM {$g5['scrap_table']} WHERE mb_id = '{$mb_id}'";
sql_query($sql);

// 5. 그룹접근가능 삭제
$sql = "DELETE FROM {$g5['group_member_table']} WHERE mb_id = '{$mb_id}'";
sql_query($sql);

// 6. 로그인 기록 삭제
$sql = "DELETE FROM {$g5['login_table']} WHERE mb_id = '{$mb_id}'";
sql_query($sql);

// 7. 쇼핑몰 관련 데이터 삭제 (영카트 사용시)
if (defined('G5_USE_SHOP') && G5_USE_SHOP) {
    // 장바구니 삭제
    $sql = "DELETE FROM {$g5['g5_shop_cart_table']} WHERE mb_id = '{$mb_id}'";
    sql_query($sql);
    
    // 찜하기 삭제
    $sql = "DELETE FROM {$g5['g5_shop_wish_table']} WHERE mb_id = '{$mb_id}'";
    sql_query($sql);
    
    // 주문내역은 보존하되 회원정보만 익명처리
    $sql = "UPDATE {$g5['g5_shop_order_table']} SET mb_id = '', od_name = '탈퇴회원' WHERE mb_id = '{$mb_id}'";
    sql_query($sql);
}

// 8. 회원 아이콘 삭제
$mb_dir = substr($mb_id, 0, 2);
$icon_file = G5_DATA_PATH.'/member/'.$mb_dir.'/'.get_mb_icon_name($mb_id).'.gif';
if (file_exists($icon_file)) {
    @unlink($icon_file);
}

// 9. 회원 이미지 삭제
$mb_img_path = G5_DATA_PATH.'/member_image/'.$mb_dir;
$mb_img_file = $mb_img_path.'/'.$mb_id.'.gif';
if (file_exists($mb_img_file)) {
    @unlink($mb_img_file);
}

// 10. 인증 관련 세션 삭제
if (isset($_SESSION['ss_cert_type']) && $_SESSION['ss_cert_type'] == 'hp') {
    set_session('ss_cert_type', '');
    set_session('ss_cert_no', '');
    set_session('ss_cert_hash', '');
    set_session('ss_cert_birth', '');
    set_session('ss_cert_sex', '');
    set_session('ss_cert_dupinfo', '');
}

// 11. 소셜로그인 연동 정보 삭제
if (function_exists('social_login_link_delete')) {
    social_login_link_delete($mb_id);
}

// 12. 회원 여분필드 파일 삭제 (학생증, 자격증 등)
if ($mb['mb_4']) { // 학생증
    $file_path = G5_DATA_PATH.'/member/'.$mb['mb_4'];
    if (file_exists($file_path)) {
        @unlink($file_path);
    }
}
if ($mb['mb_8']) { // 디자이너 자격증
    $file_path = G5_DATA_PATH.'/member/'.$mb['mb_8'];
    if (file_exists($file_path)) {
        @unlink($file_path);
    }
}

// 13. 회원정보 완전 삭제
$sql = "DELETE FROM {$g5['member_table']} WHERE mb_id = '{$mb_id}'";
sql_query($sql);

// 14. 최신글 캐시 삭제
delete_cache_latest($mb_id);

// 15. 관리자 로그 기록
if (function_exists('insert_log')) {
    insert_log($member['mb_id'].' 관리자가 '.$mb_id.' 회원을 강제 삭제함', 'member');
}

// 삭제 완료 메시지
alert($mb_id.' 회원을 완전히 삭제하였습니다.', './member_list.php?'.$qstr);
?>