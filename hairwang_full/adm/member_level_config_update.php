<?php
$sub_menu = "200400";
include_once('./_common.php');

check_demo();

// auth 배열에 키가 있는지 확인 후 권한 체크
if(isset($auth[$sub_menu])) {
    auth_check($auth[$sub_menu], 'w');
} else {
    // 권한이 정의되지 않은 경우 관리자 권한 확인
    if($member['mb_level'] < 10) {
        alert('접근 권한이 없습니다.');
    }
}

check_admin_token();

$level_icon_dir = G5_DATA_PATH.'/member_level';
if(!is_dir($level_icon_dir)) {
    @mkdir($level_icon_dir, G5_DIR_PERMISSION);
    @chmod($level_icon_dir, G5_DIR_PERMISSION);
}

// 캐시 파일 삭제 (중요!)
function clear_level_cache() {
    global $g5;
    
    // 파일 캐시 삭제
    $cache_file = G5_DATA_PATH.'/cache/latest-config-*';
    foreach(glob($cache_file) as $file) {
        @unlink($file);
    }
    
    // DB 캐시 삭제
    sql_query("DELETE FROM {$g5['cache_table']} WHERE ca_name LIKE 'config%'", false);
    sql_query("DELETE FROM {$g5['cache_table']} WHERE ca_name LIKE 'level%'", false);
}

// 일괄 업데이트 처리
if(isset($_POST['act_button']) && $_POST['act_button'] == '일괄업데이트') {
    include_once(G5_LIB_PATH.'/member_level.lib.php');
    
    // 전체 회원 수 가져오기
    $sql = "SELECT COUNT(*) as cnt FROM {$g5['member_table']} WHERE mb_level >= 1 AND mb_level <= 5";
    $row = sql_fetch($sql);
    $total_members = $row['cnt'];
    
    // 회원 레벨 업데이트
    $updated = 0;
    $sql = "SELECT mb_id FROM {$g5['member_table']} WHERE mb_level >= 1 AND mb_level <= 5 ORDER BY mb_no";
    $result = sql_query($sql);
    
    while($row = sql_fetch_array($result)) {
        if(update_member_level($row['mb_id'])) {
            $updated++;
        }
    }
    
    // 캐시 클리어
    clear_level_cache();
    
    alert($updated.'명의 회원 레벨이 업데이트되었습니다. (전체 대상: '.$total_members.'명)', './member_level_config.php');
}

// 설정 저장 처리
if(isset($_POST['act_button']) && $_POST['act_button'] == '설정저장') {
    // 레벨별 설정 저장
    for($i=1; $i<=10; $i++) {
        // POST 데이터 확인 및 검증
        $name = isset($_POST['level_name'][$i]) ? trim(strip_tags($_POST['level_name'][$i])) : '';
        $posts = isset($_POST['level_posts'][$i]) ? (int)$_POST['level_posts'][$i] : 0;
        $comments = isset($_POST['level_comments'][$i]) ? (int)$_POST['level_comments'][$i] : 0;
        $likes = isset($_POST['level_likes'][$i]) ? (int)$_POST['level_likes'][$i] : 0;
        $desc = isset($_POST['level_desc'][$i]) ? trim(strip_tags($_POST['level_desc'][$i])) : '';
        
        // 빈 이름 방지
        if(empty($name)) {
            $name = 'Level '.$i;
        }
        
        $data = array(
            'name' => $name,
            'posts' => max(0, $posts), // 음수 방지
            'comments' => max(0, $comments),
            'likes' => max(0, $likes),
            'desc' => $desc
        );
        
        // JSON 인코딩
        $json_data = json_encode($data, JSON_UNESCAPED_UNICODE);
        
        // 이스케이프 처리
        $cf_value = sql_real_escape_string($json_data);
        
        // 기존 설정 확인
        $sql = "SELECT * FROM {$g5['config_table']} WHERE cf_name = 'level_requirement_{$i}'";
        $row = sql_fetch($sql);
        
        if($row) {
            // UPDATE
            $sql = "UPDATE {$g5['config_table']} 
                    SET cf_value = '{$cf_value}' 
                    WHERE cf_name = 'level_requirement_{$i}'";
            sql_query($sql);
        } else {
            // INSERT
            $sql = "INSERT INTO {$g5['config_table']} 
                    SET cf_name = 'level_requirement_{$i}', 
                        cf_value = '{$cf_value}'";
            sql_query($sql);
        }
    }
    
    // 캐시 클리어
    clear_level_cache();
    
    $msg = '레벨 설정이 저장되었습니다.';
}

// 아이콘 삭제 처리
if(isset($_POST['del_icon']) && is_array($_POST['del_icon'])) {
    $icon_deleted = false;
    
    foreach($_POST['del_icon'] as $level => $val) {
        if($val) {
            $level = (int)$level;
            if($level >= 1 && $level <= 10) {
                // 모든 가능한 확장자의 파일 삭제
                $extensions = array('png', 'jpg', 'jpeg', 'gif');
                foreach($extensions as $ext) {
                    $del_file = $level_icon_dir.'/level_'.$level.'.'.$ext;
                    if(file_exists($del_file)) {
                        @unlink($del_file);
                        $icon_deleted = true;
                    }
                }
            }
        }
    }
    
    if($icon_deleted && !isset($msg)) {
        $msg = '아이콘이 삭제되었습니다.';
    }
}

// 아이콘 업로드 처리
if(isset($_FILES['level_icon']) && isset($_FILES['level_icon']['name']) && is_array($_FILES['level_icon']['name'])) {
    $allowed_ext = array('jpg', 'jpeg', 'png', 'gif');
    $icon_uploaded = false;
    
    foreach($_FILES['level_icon']['name'] as $level => $filename) {
        if(!$filename) continue;
        
        $level = (int)$level;
        if($level < 1 || $level > 10) continue;
        
        // 배열 요소 존재 확인
        if(!isset($_FILES['level_icon']['tmp_name'][$level]) || 
           !isset($_FILES['level_icon']['error'][$level])) {
            continue;
        }
        
        $tmp_name = $_FILES['level_icon']['tmp_name'][$level];
        $error = $_FILES['level_icon']['error'][$level];
        
        if($error != UPLOAD_ERR_OK) continue;
        
        // 확장자 체크
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if(!in_array($ext, $allowed_ext)) {
            alert('레벨 '.$level.' 아이콘: 이미지 파일만 업로드 가능합니다. (jpg, jpeg, png, gif)');
        }
        
        // 이미지 체크
        $img_info = @getimagesize($tmp_name);
        if(!$img_info) {
            alert('레벨 '.$level.' 아이콘: 올바른 이미지 파일이 아닙니다.');
        }
        
        // 이미지 크기 제한 (선택사항)
        if($img_info[0] > 512 || $img_info[1] > 512) {
            alert('레벨 '.$level.' 아이콘: 이미지 크기는 512x512 이하로 해주세요.');
        }
        
        // 기존 파일 삭제
        $extensions = array('png', 'jpg', 'jpeg', 'gif');
        foreach($extensions as $old_ext) {
            $old_file = $level_icon_dir.'/level_'.$level.'.'.$old_ext;
            if(file_exists($old_file)) {
                @unlink($old_file);
            }
        }
        
        // 파일명 설정 (PNG로 통일)
        $save_file = $level_icon_dir.'/level_'.$level.'.png';
        
        // 이미지 타입에 따라 처리
        $image = null;
        switch($img_info[2]) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($tmp_name);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($tmp_name);
                // PNG 투명도 유지
                imagealphablending($image, false);
                imagesavealpha($image, true);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($tmp_name);
                break;
        }
        
        if($image) {
            // 이미지 리사이즈 (선택사항 - 32x32로 리사이즈)
            $new_width = 32;
            $new_height = 32;
            $resized = imagecreatetruecolor($new_width, $new_height);
            
            // 투명 배경 설정
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
            imagefilledrectangle($resized, 0, 0, $new_width, $new_height, $transparent);
            
            // 이미지 리사이즈
            imagecopyresampled($resized, $image, 0, 0, 0, 0, 
                             $new_width, $new_height, $img_info[0], $img_info[1]);
            
            // PNG로 저장
            imagepng($resized, $save_file, 9);
            imagedestroy($image);
            imagedestroy($resized);
            @chmod($save_file, G5_FILE_PERMISSION);
            
            $icon_uploaded = true;
        }
    }
    
    if($icon_uploaded && !isset($msg)) {
        $msg = '아이콘이 업로드되었습니다.';
    }
}

// 성공 메시지 처리
if(isset($msg)) {
    alert($msg, './member_level_config.php?v='.time()); // 타임스탬프 추가로 캐시 방지
} else {
    // 메시지가 없으면 그냥 리다이렉트
    goto_url('./member_level_config.php?v='.time());
}
?>