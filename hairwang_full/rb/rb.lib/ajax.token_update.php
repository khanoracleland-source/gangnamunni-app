<?php 
include_once('../../common.php');

    if(isset($_POST['token']) && $_POST['token']) {
        $user_idx = isset($_POST['user_idx']) ? $_POST['user_idx'] : '';
        
        //데이터가 있는지 조회한다.
        $rows = sql_fetch (" select COUNT(*) as cnt from rb_app_token where mb_id = '{$user_idx}' ");
                           
        if(isset($rows['cnt']) && $rows['cnt'] > 0) {
            $sql = " update rb_app_token set tk_token = '".trim($_POST['token'])."', tk_and = 'and', tk_datetime = '".G5_TIME_YMDHIS."' where mb_id = '{$user_idx}' ";
            sql_query($sql);
        } else { 
            $sql = " insert rb_app_token set tk_token = '".trim($_POST['token'])."', tk_and = 'and', mb_id = '{$user_idx}', tk_datetime = '".G5_TIME_YMDHIS."' ";
            sql_query($sql);
        }
        
    }

?>


