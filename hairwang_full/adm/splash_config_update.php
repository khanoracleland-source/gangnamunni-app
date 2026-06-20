<?php
// /adm/splash_config_update.php
$sub_menu = "100590";
include_once('./_common.php');

check_demo();

// auth 배열이 정의되어 있는지 확인
if(isset($auth) && isset($auth[$sub_menu])) {
    auth_check($auth[$sub_menu], 'w');
}

check_admin_token();

// 업로드 디렉토리
$splash_dir = G5_DATA_PATH.'/splash';
$splash_url = G5_DATA_URL.'/splash';

@mkdir($splash_dir, G5_DIR_PERMISSION);
@chmod($splash_dir, G5_DIR_PERMISSION);

// 현재 설정 가져오기
$sql = "SELECT * FROM g5_splash_config LIMIT 1";
$splash = sql_fetch($sql);

// splash 데이터가 없을 경우 초기화
if(!$splash) {
    $sql = "INSERT INTO g5_splash_config SET
            sp_use = '0',
            sp_duration = '3',
            sp_type = 'image',
            sp_image_pc = '',
            sp_image_mobile = '',
            sp_lottie_pc = '',
            sp_lottie_mobile = '',
            sp_link_url = '',
            sp_link_target = '_self',
            sp_start_date = '',
            sp_end_date = '',
            sp_skip_today = '0',
            sp_show_countdown = '1',
            sp_bgcolor = '#000000',
            sp_position = 'center',
            sp_pc_width = '600px',
            sp_pc_height = '600px',
            sp_mobile_width = '80%',
            sp_mobile_height = 'auto',
            sp_pc_top = '50',
            sp_pc_left = '50',
            sp_mobile_top = '50',
            sp_mobile_left = '50',
            sp_datetime = '".G5_TIME_YMDHIS."'";
    sql_query($sql);
    
    $splash = array(
        'sp_id' => sql_insert_id(),
        'sp_image_pc' => '',
        'sp_image_mobile' => '',
        'sp_lottie_pc' => '',
        'sp_lottie_mobile' => ''
    );
}

// 파일 타입
$sp_type = isset($_POST['sp_type']) ? $_POST['sp_type'] : 'image';

// PC 이미지 처리
$sp_image_pc = isset($splash['sp_image_pc']) ? $splash['sp_image_pc'] : '';
if (isset($_POST['del_sp_image_pc']) && $_POST['del_sp_image_pc']) {
    if($sp_image_pc && file_exists($splash_dir.'/'.$sp_image_pc)) {
        @unlink($splash_dir.'/'.$sp_image_pc);
    }
    $sp_image_pc = '';
}

if (isset($_FILES['sp_image_pc']) && !empty($_FILES['sp_image_pc']['name'])) {
    $file = $_FILES['sp_image_pc'];
    if ($file['error'] == 0) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif'))) {
            $filename = 'splash_pc_'.time().'.'.$ext;
            if (move_uploaded_file($file['tmp_name'], $splash_dir.'/'.$filename)) {
                if ($sp_image_pc && file_exists($splash_dir.'/'.$sp_image_pc)) {
                    @unlink($splash_dir.'/'.$sp_image_pc);
                }
                $sp_image_pc = $filename;
            }
        }
    }
}

// 모바일 이미지 처리
$sp_image_mobile = isset($splash['sp_image_mobile']) ? $splash['sp_image_mobile'] : '';
if (isset($_POST['del_sp_image_mobile']) && $_POST['del_sp_image_mobile']) {
    if($sp_image_mobile && file_exists($splash_dir.'/'.$sp_image_mobile)) {
        @unlink($splash_dir.'/'.$sp_image_mobile);
    }
    $sp_image_mobile = '';
}

if (isset($_FILES['sp_image_mobile']) && !empty($_FILES['sp_image_mobile']['name'])) {
    $file = $_FILES['sp_image_mobile'];
    if ($file['error'] == 0) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif'))) {
            $filename = 'splash_mobile_'.time().'.'.$ext;
            if (move_uploaded_file($file['tmp_name'], $splash_dir.'/'.$filename)) {
                if ($sp_image_mobile && file_exists($splash_dir.'/'.$sp_image_mobile)) {
                    @unlink($splash_dir.'/'.$sp_image_mobile);
                }
                $sp_image_mobile = $filename;
            }
        }
    }
}

// PC Lottie 처리
$sp_lottie_pc = isset($splash['sp_lottie_pc']) ? $splash['sp_lottie_pc'] : '';
if (isset($_POST['del_sp_lottie_pc']) && $_POST['del_sp_lottie_pc']) {
    if($sp_lottie_pc && file_exists($splash_dir.'/'.$sp_lottie_pc)) {
        @unlink($splash_dir.'/'.$sp_lottie_pc);
    }
    $sp_lottie_pc = '';
}

if (isset($_FILES['sp_lottie_pc']) && !empty($_FILES['sp_lottie_pc']['name'])) {
    $file = $_FILES['sp_lottie_pc'];
    if ($file['error'] == 0) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, array('lottie', 'json'))) {
            $filename = 'splash_lottie_pc_'.time().'.'.$ext;
            if (move_uploaded_file($file['tmp_name'], $splash_dir.'/'.$filename)) {
                if ($sp_lottie_pc && file_exists($splash_dir.'/'.$sp_lottie_pc)) {
                    @unlink($splash_dir.'/'.$sp_lottie_pc);
                }
                $sp_lottie_pc = $filename;
            }
        }
    }
}

// 모바일 Lottie 처리
$sp_lottie_mobile = isset($splash['sp_lottie_mobile']) ? $splash['sp_lottie_mobile'] : '';
if (isset($_POST['del_sp_lottie_mobile']) && $_POST['del_sp_lottie_mobile']) {
    if($sp_lottie_mobile && file_exists($splash_dir.'/'.$sp_lottie_mobile)) {
        @unlink($splash_dir.'/'.$sp_lottie_mobile);
    }
    $sp_lottie_mobile = '';
}

if (isset($_FILES['sp_lottie_mobile']) && !empty($_FILES['sp_lottie_mobile']['name'])) {
    $file = $_FILES['sp_lottie_mobile'];
    if ($file['error'] == 0) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, array('lottie', 'json'))) {
            $filename = 'splash_lottie_mobile_'.time().'.'.$ext;
            if (move_uploaded_file($file['tmp_name'], $splash_dir.'/'.$filename)) {
                if ($sp_lottie_mobile && file_exists($splash_dir.'/'.$sp_lottie_mobile)) {
                    @unlink($splash_dir.'/'.$sp_lottie_mobile);
                }
                $sp_lottie_mobile = $filename;
            }
        }
    }
}

// POST 데이터 안전하게 가져오기
$sp_use = isset($_POST['sp_use']) ? (int)$_POST['sp_use'] : 0;
$sp_duration = isset($_POST['sp_duration']) ? (int)$_POST['sp_duration'] : 3;
$sp_show_countdown = isset($_POST['sp_show_countdown']) ? (int)$_POST['sp_show_countdown'] : 1;
$sp_link_url = isset($_POST['sp_link_url']) ? trim($_POST['sp_link_url']) : '';
$sp_link_target = isset($_POST['sp_link_target']) ? $_POST['sp_link_target'] : '_self';
$sp_start_date = isset($_POST['sp_start_date']) ? $_POST['sp_start_date'] : '';
$sp_end_date = isset($_POST['sp_end_date']) ? $_POST['sp_end_date'] : '';
$sp_skip_today = isset($_POST['sp_skip_today']) ? (int)$_POST['sp_skip_today'] : 0;
$sp_bgcolor = isset($_POST['sp_bgcolor']) ? $_POST['sp_bgcolor'] : '#000000';

// 위치 관련 데이터
$sp_position = isset($_POST['sp_position']) ? $_POST['sp_position'] : 'center';

// 크기 데이터 - 빈 값이면 기본값 설정
$sp_pc_width = isset($_POST['sp_pc_width']) && $_POST['sp_pc_width'] ? $_POST['sp_pc_width'] : '600px';
$sp_pc_height = isset($_POST['sp_pc_height']) && $_POST['sp_pc_height'] ? $_POST['sp_pc_height'] : '600px';
$sp_mobile_width = isset($_POST['sp_mobile_width']) && $_POST['sp_mobile_width'] ? $_POST['sp_mobile_width'] : '80%';
$sp_mobile_height = isset($_POST['sp_mobile_height']) && $_POST['sp_mobile_height'] ? $_POST['sp_mobile_height'] : 'auto';

// 위치 데이터
$sp_pc_top = isset($_POST['sp_pc_top']) ? $_POST['sp_pc_top'] : '50';
$sp_pc_left = isset($_POST['sp_pc_left']) ? $_POST['sp_pc_left'] : '50';
$sp_mobile_top = isset($_POST['sp_mobile_top']) ? $_POST['sp_mobile_top'] : '50';
$sp_mobile_left = isset($_POST['sp_mobile_left']) ? $_POST['sp_mobile_left'] : '50';

// sp_id가 있는지 확인
if(isset($splash['sp_id']) && $splash['sp_id']) {
    // 데이터 업데이트
    $sql = "UPDATE g5_splash_config SET
            sp_use = '".$sp_use."',
            sp_duration = '".$sp_duration."',
            sp_type = '".sql_real_escape_string($sp_type)."',
            sp_show_countdown = '".$sp_show_countdown."',
            sp_image_pc = '".sql_real_escape_string($sp_image_pc)."',
            sp_image_mobile = '".sql_real_escape_string($sp_image_mobile)."',
            sp_lottie_pc = '".sql_real_escape_string($sp_lottie_pc)."',
            sp_lottie_mobile = '".sql_real_escape_string($sp_lottie_mobile)."',
            sp_link_url = '".sql_real_escape_string($sp_link_url)."',
            sp_link_target = '".sql_real_escape_string($sp_link_target)."',
            sp_start_date = '".sql_real_escape_string($sp_start_date)."',
            sp_end_date = '".sql_real_escape_string($sp_end_date)."',
            sp_skip_today = '".$sp_skip_today."',
            sp_bgcolor = '".sql_real_escape_string($sp_bgcolor)."',
            sp_position = '".sql_real_escape_string($sp_position)."',
            sp_pc_width = '".sql_real_escape_string($sp_pc_width)."',
            sp_pc_height = '".sql_real_escape_string($sp_pc_height)."',
            sp_mobile_width = '".sql_real_escape_string($sp_mobile_width)."',
            sp_mobile_height = '".sql_real_escape_string($sp_mobile_height)."',
            sp_pc_top = '".sql_real_escape_string($sp_pc_top)."',
            sp_pc_left = '".sql_real_escape_string($sp_pc_left)."',
            sp_mobile_top = '".sql_real_escape_string($sp_mobile_top)."',
            sp_mobile_left = '".sql_real_escape_string($sp_mobile_left)."',
            sp_datetime = '".G5_TIME_YMDHIS."'
            WHERE sp_id = '".$splash['sp_id']."'";
    
    sql_query($sql);
}

alert('스플래시 설정이 저장되었습니다.', './splash_config.php');
?>