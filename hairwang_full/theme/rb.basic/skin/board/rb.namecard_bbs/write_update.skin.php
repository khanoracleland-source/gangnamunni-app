<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가
    @include_once(G5_PATH.'/rb/rb.lib/ajax.upload_write_update.php'); // 파일첨부를 끌어오기 형태로 업로드 하는 경우만 추가해주세요.

$nc_file_name = $_POST['nc_file_name'];

        $wr_21 = $deli1='';

        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex21_1_')===false) continue;
            $wr_21 .= $deli1.$key.'='.$value; $deli1='|';
        }

        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex21_2_')===false) continue;
            $wr_21 .= $deli1.$key.'='.$value; $deli1='|';
        }

        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex21_3_')===false) continue;
            $wr_21 .= $deli1.$key.'='.$value; $deli1='|';
        }

        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex21_4_')===false) continue;
            $wr_21 .= $deli1.$key.'='.$value; $deli1='|';
        }


        
        $wr_22 = $deli2='';

        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex22_1_')===false) continue;
            $wr_22 .= $deli2.$key.'='.$value; $deli2='|';
        }

        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex22_2_')===false) continue;
            $wr_22 .= $deli2.$key.'='.$value; $deli2='|';
        }

        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex22_3_')===false) continue;
            $wr_22 .= $deli2.$key.'='.$value; $deli2='|';
        }


        
        $wr_23 = $deli3='';

        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex23_1_')===false) continue;
            $wr_23 .= $deli3.$key.'='.$value; $deli3='|';
        }
        
        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex23_2_')===false) continue;
            $wr_23 .= $deli3.$key.'='.$value; $deli3='|';
        }
        
        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex23_3_')===false) continue;
            $wr_23 .= $deli3.$key.'='.$value; $deli3='|';
        }
        
        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex23_4_')===false) continue;
            $wr_23 .= $deli3.$key.'='.$value; $deli3='|';
        }
        
        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex23_5_')===false) continue;
            $wr_23 .= $deli3.$key.'='.$value; $deli3='|';
        }
        
        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex23_6_')===false) continue;
            $wr_23 .= $deli3.$key.'='.$value; $deli3='|';
        }
        
        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex23_7_')===false) continue;
            $wr_23 .= $deli3.$key.'='.$value; $deli3='|';
        }
        
        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex23_8_')===false) continue;
            $wr_23 .= $deli3.$key.'='.$value; $deli3='|';
        }
        
        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex23_9_')===false) continue;
            $wr_23 .= $deli3.$key.'='.$value; $deli3='|';
        }



        $wr_24 = $deli4='';

        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex24_1_')===false) continue;
            $wr_24 .= $deli4.$key.'='.$value; $deli4='|';
        }
        
        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex24_2_')===false) continue;
            $wr_24 .= $deli4.$key.'='.$value; $deli4='|';
        }

        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex24_3_')===false) continue;
            $wr_24 .= $deli4.$key.'='.$value; $deli4='|';
        }

        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex24_4_')===false) continue;
            $wr_24 .= $deli4.$key.'='.$value; $deli4='|';
        }

        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex24_5_')===false) continue;
            $wr_24 .= $deli4.$key.'='.$value; $deli4='|';
        }

        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex24_6_')===false) continue;
            $wr_24 .= $deli4.$key.'='.$value; $deli4='|';
        }


        $wr_25 = $deli5='';

        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex25_1_')===false) continue;
            $wr_25 .= $deli5.$key.'='.$value; $deli5='|';
        }
        
        foreach($_POST as $key=>$value){
            if( strpos($key, 'ex25_2_')===false) continue;
            $wr_25 .= $deli5.$key.'='.$value; $deli5='|';
        }



	
	$user_url = $_POST['wr_11'];
	$varArr = parse_url($user_url);
	parse_str($varArr['query'], $query);

	$videoId = $query['v'];
	if($viewId == ''){
		$youtube_url = $user_url;
	}else{
		$youtube_url = 'https://www.youtube.com/embed/'.$videoId;
	}
	


    $sql = " update {$write_table} set wr_11 = '{$youtube_url}', wr_12 = '{$_POST['wr_12']}', wr_13 = '{$_POST['wr_13']}', wr_20 = '{$_POST['wr_20']}', wr_21 = '{$wr_21}', wr_21_cnt = '{$_POST['wr_21_cnt']}', wr_22 = '{$wr_22}', wr_22_cnt = '{$_POST['wr_22_cnt']}', wr_23 = '{$wr_23}', wr_23_cnt = '{$_POST['wr_23_cnt']}', wr_24 = '{$wr_24}', wr_24_cnt = '{$_POST['wr_24_cnt']}', wr_25 = '{$wr_25}', wr_25_cnt = '{$_POST['wr_25_cnt']}', temp_sel = '{$_POST['temp_sel']}', nc_file_name = '{$nc_file_name}' where wr_id = '{$wr_id}' ";
    sql_query($sql);



    ////////// 첨부파일 추가 //////////
    ////////// 첨부파일 추가 //////////



    // 파일개수 체크
    $file_count = 0;
    $file_count1 = 0;
    $file_count2 = 0;
    $file_count5 = 0;

    $upload_count = count($_FILES['bf_file_new']['name']);
    $upload_count1 = count($_FILES['bf_file_new1']['name']);
    $upload_count2 = count($_FILES['bf_file_new2']['name']);
    $upload_count5 = count($_FILES['bf_file_new5']['name']);


    /*
    if (is_array($_FILES['bf_file_new']['name']) ) {
        if ($row_file['bf_file'] > count($_FILES['bf_file_new']['name'])) {
            $upload_count = count($_FILES['bf_file_new']['name']);
        }
    }
    */
    
    for ($i=0; $i<$upload_count; $i++) {
        if($_FILES['bf_file_new']['name'][$i] && is_uploaded_file($_FILES['bf_file_new']['tmp_name'][$i]))
            $file_count++;
    }

    for ($i=0; $i<$upload_count1; $i++) {
        if($_FILES['bf_file_new1']['name'][$i] && is_uploaded_file($_FILES['bf_file_new1']['tmp_name'][$i]))
            $file_count1++;
    }

    for ($i=0; $i<$upload_count2; $i++) {
        if($_FILES['bf_file_new2']['name'][$i] && is_uploaded_file($_FILES['bf_file_new2']['tmp_name'][$i]))
            $file_count2++;
    }

    for ($i=0; $i<$upload_count5; $i++) {
        if($_FILES['bf_file_new5']['name'][$i] && is_uploaded_file($_FILES['bf_file_new5']['tmp_name'][$i]))
            $file_count5++;
    }


    // 디렉토리가 없다면 생성합니다.
    if($upload_count > 0) {
        if (!is_dir(G5_DATA_PATH.'/namecard/'.$wr_id.'/0')) {
            if (!mkdir(G5_DATA_PATH.'/namecard/'.$wr_id.'/0', G5_DIR_PERMISSION, true)) {
                die('디렉토리 생성 실패: ' . G5_DATA_PATH.'/namecard/'.$wr_id.'/0');
            }
            if (!chmod(G5_DATA_PATH.'/namecard/'.$wr_id.'/0', G5_DIR_PERMISSION)) {
                die('디렉토리 권한 설정 실패: ' . G5_DATA_PATH.'/namecard/'.$wr_id.'/0');
            }
        }
    }

    if($upload_count1 > 0) {
        if (!is_dir(G5_DATA_PATH.'/namecard/'.$wr_id.'/1')) {
            if (!mkdir(G5_DATA_PATH.'/namecard/'.$wr_id.'/1', G5_DIR_PERMISSION, true)) {
                die('디렉토리 생성 실패: ' . G5_DATA_PATH.'/namecard/'.$wr_id.'/1');
            }
            if (!chmod(G5_DATA_PATH.'/namecard/'.$wr_id.'/1', G5_DIR_PERMISSION)) {
                die('디렉토리 권한 설정 실패: ' . G5_DATA_PATH.'/namecard/'.$wr_id.'/1');
            }
        }
    }

    if($upload_count2 > 0) {
        if (!is_dir(G5_DATA_PATH.'/namecard/'.$wr_id.'/2')) {
            if (!mkdir(G5_DATA_PATH.'/namecard/'.$wr_id.'/2', G5_DIR_PERMISSION, true)) {
                die('디렉토리 생성 실패: ' . G5_DATA_PATH.'/namecard/'.$wr_id.'/2');
            }
            if (!chmod(G5_DATA_PATH.'/namecard/'.$wr_id.'/2', G5_DIR_PERMISSION)) {
                die('디렉토리 권한 설정 실패: ' . G5_DATA_PATH.'/namecard/'.$wr_id.'/2');
            }
        }
    }

    if($upload_count5 > 0) {
        if (!is_dir(G5_DATA_PATH.'/namecard/'.$wr_id.'/5')) {
            if (!mkdir(G5_DATA_PATH.'/namecard/'.$wr_id.'/5', G5_DIR_PERMISSION, true)) {
                die('디렉토리 생성 실패: ' . G5_DATA_PATH.'/namecard/'.$wr_id.'/5');
            }
            if (!chmod(G5_DATA_PATH.'/namecard/'.$wr_id.'/5', G5_DIR_PERMISSION)) {
                die('디렉토리 권한 설정 실패: ' . G5_DATA_PATH.'/namecard/'.$wr_id.'/5');
            }
        }
    }

    $chars_array = array_merge(range(0,9), range('a','z'), range('A','Z'));

    // 가변 파일 업로드
    $file_upload_msg = '';
    $upload = array();
    $upload1 = array();
    $upload2 = array();
    $upload5 = array();

    if (is_array($_FILES['bf_file_new']['name']) ) {

        for ($i=0; $i<count($_FILES['bf_file_new']['name']); $i++) {

            $upload[$i]['file']     = '';
            $upload[$i]['source']   = '';
            $upload[$i]['filesize'] = 0;
            $upload[$i]['image']    = array();
            $upload[$i]['image'][0] = '';
            $upload[$i]['image'][1] = '';
            $upload[$i]['image'][2] = '';


            // 삭제에 체크가 되어있다면 파일을 삭제합니다.
            if (isset($_POST['bf_file_new_del'][$i]) && $_POST['bf_file_new_del'][$i]) {
                $upload[$i]['del_check'] = true;

                $row = sql_fetch(" select bf_file from rb_namecard_file where wr_id = '{$wr_id}' and bf_no = '{$i}' and bf_tmp = '0' ");
                @unlink(G5_DATA_PATH.'/namecard/'.$wr_id.'/0/'.$row['bf_file']);
                // 썸네일삭제
                if(preg_match("/\.({$config['cf_image_extension']})$/i", $row['bf_file'])) {
                    delete_member_thumbnail($wr_id, $row['bf_file']); // 수정
                }
            }
            else
                $upload[$i]['del_check'] = false;

            $tmp_file  = $_FILES['bf_file_new']['tmp_name'][$i];
            $filesize  = $_FILES['bf_file_new']['size'][$i];
            $filename  = $_FILES['bf_file_new']['name'][$i];
            $filename  = get_safe_filename($filename);

            if (is_uploaded_file($tmp_file)) {


                //=================================================================\
                // 090714
                // 이미지나 플래시 파일에 악성코드를 심어 업로드 하는 경우를 방지
                // 에러메세지는 출력하지 않는다.
                //-----------------------------------------------------------------
                $timg = @getimagesize($tmp_file);
                // image type
                if ( preg_match("/\.({$config['cf_image_extension']})$/i", $filename) ||
                    preg_match("/\.({$config['cf_flash_extension']})$/i", $filename) ) {
                    if ($timg['2'] < 1 || $timg['2'] > 16)
                        continue;
                }
                //=================================================================

                $upload[$i]['image'] = $timg;

                // 4.00.11 - 글답변에서 파일 업로드시 원글의 파일이 삭제되는 오류를 수정
                if ($w == 'u') {
                    // 존재하는 파일이 있다면 삭제합니다.
                    $row = sql_fetch(" select bf_file from rb_namecard_file where wr_id = '$wr_id' and bf_no = '$i' and bf_tmp = '0' ");
                    @unlink(G5_DATA_PATH.'/namecard/'.$wr_id.'/0/'.$row['bf_file']);
                    // 이미지파일이면 썸네일삭제
                    if(preg_match("/\.({$config['cf_image_extension']})$/i", $row['bf_file'])) {
                        delete_member_thumbnail($wr_id, $row['bf_file']);
                    }
                }

                // 프로그램 원래 파일명
                $upload[$i]['source'] = $filename;
                $upload[$i]['filesize'] = $filesize;

                // 아래의 문자열이 들어간 파일은 -x 를 붙여서 웹경로를 알더라도 실행을 하지 못하도록 함
                $filename = preg_replace("/\.(php|phtm|htm|cgi|pl|exe|jsp|asp|inc)/i", "$0-x", $filename);

                shuffle($chars_array);
                $shuffle = implode('', $chars_array);

                // 첨부파일 첨부시 첨부파일명에 공백이 포함되어 있으면 일부 PC에서 보이지 않거나 다운로드 되지 않는 현상이 있습니다. (길상여의 님 090925)
                $upload[$i]['file'] = abs(ip2long($_SERVER['REMOTE_ADDR'])).'_'.substr($shuffle,0,8).'_'.replace_filename($filename);

                $dest_file = G5_DATA_PATH.'/namecard/'.$wr_id.'/0/'.$upload[$i]['file'];

                // 업로드가 안된다면 에러메세지 출력하고 죽어버립니다.
                $error_code = move_uploaded_file($tmp_file, $dest_file) or die($_FILES['bf_file_new']['error'][$i]);

                // 올라간 파일의 퍼미션을 변경합니다.
                chmod($dest_file, G5_FILE_PERMISSION);
            }
        }
    }


    if (is_array($_FILES['bf_file_new1']['name']) ) {

        for ($i=0; $i<count($_FILES['bf_file_new1']['name']); $i++) {

            $upload1[$i]['file']     = '';
            $upload1[$i]['source']   = '';
            $upload1[$i]['filesize'] = 0;
            $upload1[$i]['image']    = array();
            $upload1[$i]['image'][0] = '';
            $upload1[$i]['image'][1] = '';
            $upload1[$i]['image'][2] = '';


            // 삭제에 체크가 되어있다면 파일을 삭제합니다.
            if (isset($_POST['bf_file_new1_del'][$i]) && $_POST['bf_file_new1_del'][$i]) {
                $upload1[$i]['del_check'] = true;

                $row1 = sql_fetch(" select bf_file from rb_namecard_file where wr_id = '{$wr_id}' and bf_no = '{$i}' and bf_tmp = '1' ");
                @unlink(G5_DATA_PATH.'/namecard/'.$wr_id.'/1/'.$row1['bf_file']);
            }
            else
                $upload1[$i]['del_check'] = false;

            $tmp_file  = $_FILES['bf_file_new1']['tmp_name'][$i];
            $filesize  = $_FILES['bf_file_new1']['size'][$i];
            $filename  = $_FILES['bf_file_new1']['name'][$i];
            $filename  = get_safe_filename($filename);

            if (is_uploaded_file($tmp_file)) {


                //=================================================================\
                // 090714
                // 이미지나 플래시 파일에 악성코드를 심어 업로드 하는 경우를 방지
                // 에러메세지는 출력하지 않는다.
                //-----------------------------------------------------------------
                $timg = @getimagesize($tmp_file);
                // image type
                if ( preg_match("/\.({$config['cf_image_extension']})$/i", $filename) ||
                    preg_match("/\.({$config['cf_flash_extension']})$/i", $filename) ) {
                    if ($timg['2'] < 1 || $timg['2'] > 16)
                        continue;
                }
                //=================================================================

                $upload1[$i]['image'] = $timg;

                // 4.00.11 - 글답변에서 파일 업로드시 원글의 파일이 삭제되는 오류를 수정
                if ($w == 'u') {
                    // 존재하는 파일이 있다면 삭제합니다.
                    $row1 = sql_fetch(" select bf_file from rb_namecard_file where wr_id = '$wr_id' and bf_no = '$i' and bf_tmp = '1' ");
                    @unlink(G5_DATA_PATH.'/namecard/'.$wr_id.'/1/'.$row1['bf_file']);
                }

                // 프로그램 원래 파일명
                $upload1[$i]['source'] = $filename;
                $upload1[$i]['filesize'] = $filesize;

                // 아래의 문자열이 들어간 파일은 -x 를 붙여서 웹경로를 알더라도 실행을 하지 못하도록 함
                $filename = preg_replace("/\.(php|phtm|htm|cgi|pl|exe|jsp|asp|inc)/i", "$0-x", $filename);

                shuffle($chars_array);
                $shuffle = implode('', $chars_array);

                // 첨부파일 첨부시 첨부파일명에 공백이 포함되어 있으면 일부 PC에서 보이지 않거나 다운로드 되지 않는 현상이 있습니다. (길상여의 님 090925)
                $upload1[$i]['file'] = abs(ip2long($_SERVER['REMOTE_ADDR'])).'_'.substr($shuffle,0,8).'_'.replace_filename($filename);

                $dest_file = G5_DATA_PATH.'/namecard/'.$wr_id.'/1/'.$upload1[$i]['file'];

                // 업로드가 안된다면 에러메세지 출력하고 죽어버립니다.
                $error_code = move_uploaded_file($tmp_file, $dest_file) or die($_FILES['bf_file_new1']['error'][$i]);

                // 올라간 파일의 퍼미션을 변경합니다.
                chmod($dest_file, G5_FILE_PERMISSION);
            }
        }
    }



    if (is_array($_FILES['bf_file_new2']['name']) ) {

        for ($i=0; $i<count($_FILES['bf_file_new2']['name']); $i++) {

            $upload2[$i]['file']     = '';
            $upload2[$i]['source']   = '';
            $upload2[$i]['filesize'] = 0;
            $upload2[$i]['image']    = array();
            $upload2[$i]['image'][0] = '';
            $upload2[$i]['image'][1] = '';
            $upload2[$i]['image'][2] = '';


            // 삭제에 체크가 되어있다면 파일을 삭제합니다.
            if (isset($_POST['bf_file_new2_del'][$i]) && $_POST['bf_file_new2_del'][$i]) {
                $upload2[$i]['del_check'] = true;

                $row2 = sql_fetch(" select bf_file from rb_namecard_file where wr_id = '{$wr_id}' and bf_no = '{$i}' and bf_tmp = '2' ");
                @unlink(G5_DATA_PATH.'/namecard/'.$wr_id.'/2/'.$row2['bf_file']);
            }
            else
                $upload2[$i]['del_check'] = false;

            $tmp_file  = $_FILES['bf_file_new2']['tmp_name'][$i];
            $filesize  = $_FILES['bf_file_new2']['size'][$i];
            $filename  = $_FILES['bf_file_new2']['name'][$i];
            $filename  = get_safe_filename($filename);

            if (is_uploaded_file($tmp_file)) {


                //=================================================================\
                // 090714
                // 이미지나 플래시 파일에 악성코드를 심어 업로드 하는 경우를 방지
                // 에러메세지는 출력하지 않는다.
                //-----------------------------------------------------------------
                $timg = @getimagesize($tmp_file);
                // image type
                if ( preg_match("/\.({$config['cf_image_extension']})$/i", $filename) ||
                    preg_match("/\.({$config['cf_flash_extension']})$/i", $filename) ) {
                    if ($timg['2'] < 1 || $timg['2'] > 16)
                        continue;
                }
                //=================================================================

                $upload2[$i]['image'] = $timg;

                // 4.00.11 - 글답변에서 파일 업로드시 원글의 파일이 삭제되는 오류를 수정
                if ($w == 'u') {
                    // 존재하는 파일이 있다면 삭제합니다.
                    $row2 = sql_fetch(" select bf_file from rb_namecard_file where wr_id = '$wr_id' and bf_no = '$i' and bf_tmp = '2' ");
                    @unlink(G5_DATA_PATH.'/namecard/'.$wr_id.'/2/'.$row2['bf_file']);
                }

                // 프로그램 원래 파일명
                $upload2[$i]['source'] = $filename;
                $upload2[$i]['filesize'] = $filesize;

                // 아래의 문자열이 들어간 파일은 -x 를 붙여서 웹경로를 알더라도 실행을 하지 못하도록 함
                $filename = preg_replace("/\.(php|phtm|htm|cgi|pl|exe|jsp|asp|inc)/i", "$0-x", $filename);

                shuffle($chars_array);
                $shuffle = implode('', $chars_array);

                // 첨부파일 첨부시 첨부파일명에 공백이 포함되어 있으면 일부 PC에서 보이지 않거나 다운로드 되지 않는 현상이 있습니다. (길상여의 님 090925)
                $upload2[$i]['file'] = abs(ip2long($_SERVER['REMOTE_ADDR'])).'_'.substr($shuffle,0,8).'_'.replace_filename($filename);

                $dest_file = G5_DATA_PATH.'/namecard/'.$wr_id.'/2/'.$upload2[$i]['file'];

                // 업로드가 안된다면 에러메세지 출력하고 죽어버립니다.
                $error_code = move_uploaded_file($tmp_file, $dest_file) or die($_FILES['bf_file_new2']['error'][$i]);

                // 올라간 파일의 퍼미션을 변경합니다.
                chmod($dest_file, G5_FILE_PERMISSION);
            }
        }
    }




    if (is_array($_FILES['bf_file_new5']['name']) ) {

        for ($i=0; $i<count($_FILES['bf_file_new5']['name']); $i++) {

            $upload5[$i]['file']     = '';
            $upload5[$i]['source']   = '';
            $upload5[$i]['filesize'] = 0;
            $upload5[$i]['image']    = array();
            $upload5[$i]['image'][0] = '';
            $upload5[$i]['image'][1] = '';
            $upload5[$i]['image'][2] = '';


            // 삭제에 체크가 되어있다면 파일을 삭제합니다.
            if (isset($_POST['bf_file_new5_del'][$i]) && $_POST['bf_file_new5_del'][$i]) {
                $upload52[$i]['del_check'] = true;

                $row5 = sql_fetch(" select bf_file from rb_namecard_file where wr_id = '{$wr_id}' and bf_no = '{$i}' and bf_tmp = '5' ");
                @unlink(G5_DATA_PATH.'/namecard/'.$wr_id.'/5/'.$row2['bf_file']);
            }
            else
                $upload5[$i]['del_check'] = false;

            $tmp_file  = $_FILES['bf_file_new5']['tmp_name'][$i];
            $filesize  = $_FILES['bf_file_new5']['size'][$i];
            $filename  = $_FILES['bf_file_new5']['name'][$i];
            $filename  = get_safe_filename($filename);

            if (is_uploaded_file($tmp_file)) {


                //=================================================================\
                // 090714
                // 이미지나 플래시 파일에 악성코드를 심어 업로드 하는 경우를 방지
                // 에러메세지는 출력하지 않는다.
                //-----------------------------------------------------------------
                $timg = @getimagesize($tmp_file);
                // image type
                if ( preg_match("/\.({$config['cf_image_extension']})$/i", $filename) ||
                    preg_match("/\.({$config['cf_flash_extension']})$/i", $filename) ) {
                    if ($timg['2'] < 1 || $timg['2'] > 16)
                        continue;
                }
                //=================================================================

                $upload5[$i]['image'] = $timg;

                // 4.00.11 - 글답변에서 파일 업로드시 원글의 파일이 삭제되는 오류를 수정
                if ($w == 'u') {
                    // 존재하는 파일이 있다면 삭제합니다.
                    $row5 = sql_fetch(" select bf_file from rb_namecard_file where wr_id = '$wr_id' and bf_no = '$i' and bf_tmp = '5' ");
                    @unlink(G5_DATA_PATH.'/namecard/'.$wr_id.'/5/'.$row5['bf_file']);
                }

                // 프로그램 원래 파일명
                $upload5[$i]['source'] = $filename;
                $upload5[$i]['filesize'] = $filesize;

                // 아래의 문자열이 들어간 파일은 -x 를 붙여서 웹경로를 알더라도 실행을 하지 못하도록 함
                $filename = preg_replace("/\.(php|phtm|htm|cgi|pl|exe|jsp|asp|inc)/i", "$0-x", $filename);

                shuffle($chars_array);
                $shuffle = implode('', $chars_array);

                // 첨부파일 첨부시 첨부파일명에 공백이 포함되어 있으면 일부 PC에서 보이지 않거나 다운로드 되지 않는 현상이 있습니다. (길상여의 님 090925)
                $upload5[$i]['file'] = abs(ip2long($_SERVER['REMOTE_ADDR'])).'_'.substr($shuffle,0,8).'_'.replace_filename($filename);

                $dest_file = G5_DATA_PATH.'/namecard/'.$wr_id.'/5/'.$upload5[$i]['file'];

                // 업로드가 안된다면 에러메세지 출력하고 죽어버립니다.
                $error_code = move_uploaded_file($tmp_file, $dest_file) or die($_FILES['bf_file_new5']['error'][$i]);

                // 올라간 파일의 퍼미션을 변경합니다.
                chmod($dest_file, G5_FILE_PERMISSION);
            }
        }
    }
    

    
    for ($i=0; $i<count($upload); $i++)
    {
        if (!get_magic_quotes_gpc()) {
            $upload[$i]['source'] = addslashes($upload[$i]['source']);
        }

        $row = sql_fetch(" select count(*) as cnt from rb_namecard_file where wr_id = '{$wr_id}' and bf_no = '{$i}' and bf_tmp = '0' ");
        if ($row['cnt'])
        {
            // 삭제에 체크가 있거나 파일이 있다면 업데이트를 합니다.
            // 그렇지 않다면 내용만 업데이트 합니다.
            if ($upload[$i]['del_check'] || $upload[$i]['file'])
            {
                $sql = " update rb_namecard_file
                            set bf_source = '{$upload[$i]['source']}',
                                 bf_file = '{$upload[$i]['file']}',
                                 bf_content = '{$bf_content[$i]}',
                                 bf_filesize = '{$upload[$i]['filesize']}',
                                 bf_width = '{$upload[$i]['image']['0']}',
                                 bf_height = '{$upload[$i]['image']['1']}',
                                 bf_type = '{$upload[$i]['image']['2']}',
                                 bf_tmp = '0',
                                 bf_datetime = '".G5_TIME_YMDHIS."'
                          where wr_id = '{$wr_id}' and bf_tmp = '0' 
                                    and bf_no = '{$i}' ";
                sql_query($sql);
            }
            else
            {
                $sql = " update rb_namecard_file
                            set bf_content = '{$bf_content[$i]}'
                            where wr_id = '{$wr_id}' and bf_tmp = '0'
                                      and bf_no = '{$i}' ";
                sql_query($sql);
            }
        }
        else
        {
            $sql = " insert into rb_namecard_file
                        set wr_id = '{$wr_id}',
                             bf_no = '{$i}',
                             bf_tmp = '0',
                             bf_source = '{$upload[$i]['source']}',
                             bf_file = '{$upload[$i]['file']}',
                             bf_content = '{$bf_content[$i]}',
                             bf_download = 0,
                             bf_filesize = '{$upload[$i]['filesize']}',
                             bf_width = '{$upload[$i]['image']['0']}',
                             bf_height = '{$upload[$i]['image']['1']}',
                             bf_type = '{$upload[$i]['image']['2']}',
                             bf_datetime = '".G5_TIME_YMDHIS."' ";
            sql_query($sql);
        }
    }



    for ($i=0; $i<count($upload1); $i++)
    {
        if (!get_magic_quotes_gpc()) {
            $upload1[$i]['source'] = addslashes($upload1[$i]['source']);
        }

        $row1 = sql_fetch(" select count(*) as cnt from rb_namecard_file where wr_id = '{$wr_id}' and bf_no = '{$i}' and bf_tmp = '1' ");

        if ($row1['cnt'])
        {
            // 삭제에 체크가 있거나 파일이 있다면 업데이트를 합니다.
            // 그렇지 않다면 내용만 업데이트 합니다.
            if ($upload1[$i]['del_check'] || $upload1[$i]['file'])
            {
                $sql = " update rb_namecard_file
                            set bf_source = '{$upload1[$i]['source']}',
                                 bf_file = '{$upload1[$i]['file']}',
                                 bf_filesize = '{$upload1[$i]['filesize']}',
                                 bf_width = '{$upload1[$i]['image']['0']}',
                                 bf_height = '{$upload1[$i]['image']['1']}',
                                 bf_type = '{$upload1[$i]['image']['2']}',
                                 bf_tmp = '1',
                                 bf_datetime = '".G5_TIME_YMDHIS."'
                          where wr_id = '{$wr_id}' and and bf_tmp = '1' 
                                    and bf_no = '{$i}' ";
                sql_query($sql);
            }
            else
            {
                $sql = " update rb_namecard_file
                            set bf_content = '{$bf_content[$i]}'
                            where wr_id = '{$wr_id}' and bf_tmp = '1' 
                                      and bf_no = '{$i}' ";
                sql_query($sql);
            }
        }
        else
        {
            
            $sql = " insert into rb_namecard_file
                        set wr_id = '{$wr_id}',
                             bf_no = '{$i}',
                             bf_tmp = '1',
                             bf_source = '{$upload1[$i]['source']}',
                             bf_file = '{$upload1[$i]['file']}',
                             bf_download = 0,
                             bf_filesize = '{$upload1[$i]['filesize']}',
                             bf_width = '{$upload1[$i]['image']['0']}',
                             bf_height = '{$upload1[$i]['image']['1']}',
                             bf_type = '{$upload1[$i]['image']['2']}',
                             bf_datetime = '".G5_TIME_YMDHIS."' ";
            sql_query($sql);
        }
    }




    for ($i=0; $i<count($upload2); $i++)
    {
        if (!get_magic_quotes_gpc()) {
            $upload2[$i]['source'] = addslashes($upload2[$i]['source']);
        }

        $row2 = sql_fetch(" select count(*) as cnt from rb_namecard_file where wr_id = '{$wr_id}' and bf_no = '{$i}' and bf_tmp = '2' ");
        if ($row2['cnt'])
        {
            // 삭제에 체크가 있거나 파일이 있다면 업데이트를 합니다.
            // 그렇지 않다면 내용만 업데이트 합니다.
            if ($upload2[$i]['del_check'] || $upload2[$i]['file'])
            {
                $sql = " update rb_namecard_file
                            set bf_source = '{$upload2[$i]['source']}',
                                 bf_file = '{$upload2[$i]['file']}',
                                 bf_content = '{$bf_content[$i]}',
                                 bf_filesize = '{$upload2[$i]['filesize']}',
                                 bf_width = '{$upload2[$i]['image']['0']}',
                                 bf_height = '{$upload2[$i]['image']['1']}',
                                 bf_type = '{$upload2[$i]['image']['2']}',
                                 bf_tmp = '2',
                                 bf_datetime = '".G5_TIME_YMDHIS."'
                          where wr_id = '{$wr_id}' and bf_tmp = '2' 
                                    and bf_no = '{$i}' ";
                sql_query($sql);
            }
            else
            {
                $sql = " update rb_namecard_file
                            set bf_content = '{$bf_content[$i]}'
                            where wr_id = '{$wr_id}' and bf_tmp = '2' 
                                      and bf_no = '{$i}' ";
                sql_query($sql);
            }
        }
        else
        {
            $sql = " insert into rb_namecard_file
                        set wr_id = '{$wr_id}',
                             bf_no = '{$i}',
                             bf_tmp = '2',
                             bf_source = '{$upload2[$i]['source']}',
                             bf_file = '{$upload2[$i]['file']}',
                             bf_content = '{$bf_content[$i]}',
                             bf_download = 0,
                             bf_filesize = '{$upload2[$i]['filesize']}',
                             bf_width = '{$upload2[$i]['image']['0']}',
                             bf_height = '{$upload2[$i]['image']['1']}',
                             bf_type = '{$upload2[$i]['image']['2']}',
                             bf_datetime = '".G5_TIME_YMDHIS."' ";
            sql_query($sql);
        }
    }



    for ($i=0; $i<count($upload5); $i++)
    {
        if (!get_magic_quotes_gpc()) {
            $upload5[$i]['source'] = addslashes($upload5[$i]['source']);
        }

        $row5 = sql_fetch(" select count(*) as cnt from rb_namecard_file where wr_id = '{$wr_id}' and bf_no = '{$i}' and bf_tmp = '5' ");
        if ($row5['cnt'])
        {
            // 삭제에 체크가 있거나 파일이 있다면 업데이트를 합니다.
            // 그렇지 않다면 내용만 업데이트 합니다.
            if ($upload5[$i]['del_check'] || $upload5[$i]['file'])
            {
                $sql = " update rb_namecard_file
                            set bf_source = '{$upload5[$i]['source']}',
                                 bf_file = '{$upload5[$i]['file']}',
                                 bf_content = '{$bf_content[$i]}',
                                 bf_filesize = '{$upload5[$i]['filesize']}',
                                 bf_width = '{$upload5[$i]['image']['0']}',
                                 bf_height = '{$upload5[$i]['image']['1']}',
                                 bf_type = '{$upload5[$i]['image']['2']}',
                                 bf_tmp = '5',
                                 bf_datetime = '".G5_TIME_YMDHIS."'
                          where wr_id = '{$wr_id}' and bf_tmp = '5' 
                                    and bf_no = '{$i}' ";
                sql_query($sql);
            }
            else
            {
                $sql = " update rb_namecard_file
                            set bf_content = '{$bf_content[$i]}'
                            where wr_id = '{$wr_id}' and bf_tmp = '5' 
                                      and bf_no = '{$i}' ";
                sql_query($sql);
            }
        }
        else
        {
            $sql = " insert into rb_namecard_file
                        set wr_id = '{$wr_id}',
                             bf_no = '{$i}',
                             bf_tmp = '5',
                             bf_source = '{$upload5[$i]['source']}',
                             bf_file = '{$upload5[$i]['file']}',
                             bf_content = '{$bf_content[$i]}',
                             bf_download = 0,
                             bf_filesize = '{$upload5[$i]['filesize']}',
                             bf_width = '{$upload5[$i]['image']['0']}',
                             bf_height = '{$upload5[$i]['image']['1']}',
                             bf_type = '{$upload5[$i]['image']['2']}',
                             bf_datetime = '".G5_TIME_YMDHIS."' ";
            sql_query($sql);
        }
    }



    // 업로드된 파일 내용에서 가장 큰 번호를 얻어 거꾸로 확인해 가면서
    // 파일 정보가 없다면 테이블의 내용을 삭제합니다.
    $row = sql_fetch(" select max(bf_no) as max_bf_no from rb_namecard_file where wr_id = '{$wr_id}' and bf_tmp = '0' ");
    for ($i=(int)$row['max_bf_no']; $i>=0; $i--)
    {
        $row2 = sql_fetch(" select bf_file from rb_namecard_file where wr_id = '{$wr_id}' and bf_no = '{$i}' and bf_tmp = '0' ");

        // 정보가 있다면 빠집니다.
        if ($row2['bf_file']) break;

        // 그렇지 않다면 정보를 삭제합니다.
        sql_query(" delete from rb_namecard_file where wr_id = '{$wr_id}' and bf_no = '{$i}' and bf_tmp = '0' ");
    }



    $row1 = sql_fetch(" select max(bf_no) as max_bf_no from rb_namecard_file where wr_id = '{$wr_id}' and bf_tmp = '1' ");
    for ($k=(int)$row1['max_bf_no']; $k>=0; $k--)
    {
        $row21 = sql_fetch(" select bf_file from rb_namecard_file where wr_id = '{$wr_id}' and bf_no = '{$k}' and bf_tmp = '1' ");

        // 정보가 있다면 빠집니다.
        if ($row21['bf_file']) break;

        // 그렇지 않다면 정보를 삭제합니다.
        sql_query(" delete from rb_namecard_file where wr_id = '{$wr_id}' and bf_no = '{$k}' and bf_tmp = '1' ");
    }



    $row2 = sql_fetch(" select max(bf_no) as max_bf_no from rb_namecard_file where wr_id = '{$wr_id}' and bf_tmp = '2' ");
    for ($n=(int)$row2['max_bf_no']; $n>=0; $n--)
    {
        $row22 = sql_fetch(" select bf_file from rb_namecard_file where wr_id = '{$wr_id}' and bf_no = '{$n}' and bf_tmp = '2' ");

        // 정보가 있다면 빠집니다.
        if ($row22['bf_file']) break;

        // 그렇지 않다면 정보를 삭제합니다.
        sql_query(" delete from rb_namecard_file where wr_id = '{$wr_id}' and bf_no = '{$n}' and bf_tmp = '2' ");
    }


    $row5 = sql_fetch(" select max(bf_no) as max_bf_no from rb_namecard_file where wr_id = '{$wr_id}' and bf_tmp = '5' ");
    for ($s=(int)$row5['max_bf_no']; $s>=0; $s--)
    {
        $row25 = sql_fetch(" select bf_file from rb_namecard_file where wr_id = '{$wr_id}' and bf_no = '{$n}' and bf_tmp = '5' ");

        // 정보가 있다면 빠집니다.
        if ($row25['bf_file']) break;

        // 그렇지 않다면 정보를 삭제합니다.
        sql_query(" delete from rb_namecard_file where wr_id = '{$wr_id}' and bf_no = '{$n}' and bf_tmp = '5' ");
    }
    
    ////////// 첨부파일 추가 //////////
    ////////// 첨부파일 추가 //////////



    if($w == "") {
        //관리자에게 쪽지발송
        memo_auto_send($board['bo_subject'].'에 새글이 등록 되었습니다.', G5_BBS_URL.'/board.php?bo_table='.$bo_table.'&wr_id='.$wr_id, $config['cf_admin'], "system-msg");
    }

    var_dump($_FILES);
?> 
