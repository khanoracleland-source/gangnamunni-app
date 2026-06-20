<?php
include_once('../_common.php');
$sb_type = $_POST['sb_type'];

// 로그인 여부 확인
if ($is_member) {
    
    if($sb_type == "push") {
        
        if (isset($_POST['sb_id']) && !empty($_POST['sb_id'])) {
            $mb_id = $member['mb_id'];
            $sb_id = $_POST['sb_id'];
            $sb_push = $_POST['sb_push'];
            
            
            
            $sql =  " UPDATE rb_subscribe SET sb_push = '{$sb_push}' WHERE sb_id = '{$sb_id}' and sb_mb_id = '{$mb_id}' ";
            sql_query($sql);
            
            if($sb_push == 1) {
                $sb_push_res = 0;
                echo '<button type="button" class="fw_push_btn" onclick="sb_submit(\''.$sb_id.'\', \''.$sb_push_res.'\')"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-bell"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg></button>';
            } else { 
                $sb_push_res = 1;
                echo '<button type="button" class="fw_push_btn" onclick="sb_submit(\''.$sb_id.'\', \''.$sb_push_res.'\')"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#bbb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-bell-off"><path d="M13.73 21a2 2 0 0 1-3.46 0"></path><path d="M18.63 13A17.89 17.89 0 0 1 18 8"></path><path d="M6.26 6.26A5.86 5.86 0 0 0 6 8c0 7-3 9-3 9h14"></path><path d="M18 8a6 6 0 0 0-9.33-5"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg></button>';
            }
            
            

        }
    }

    if($sb_type == "del") {
        if (isset($_POST['sb_id']) && !empty($_POST['sb_id'])) {
            
            $mb_id = $member['mb_id'];
            $sb_id = $_POST['sb_id'];
            
            $sql =  " DELETE from rb_subscribe where sb_id = '{$sb_id}' and sb_mb_id = '{$mb_id}' ";
            sql_query($sql);
        }
    }
 

}
