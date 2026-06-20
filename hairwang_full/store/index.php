<?php
include_once('./_common.php');

$p = isset($_GET['p']) ? $_GET['p'] : '';

if($p) { 
    
    $pm = get_member($p);
    
    if (isset($pm['mb_id']) && $pm['mb_id']) {
        
        if (isset($pm['mb_partner']) && $pm['mb_partner'] == 2) {
            include_once(G5_PATH.'/rb/rb.mod/partner/store.php');
        } else { 
            alert('입점사 아이디를 확인해주세요.', G5_URL);
        }
       
    } else { 
        alert('입점사 아이디를 확인해주세요.', G5_URL);
    }
    
} else { 
    alert('입점사 정보가 없습니다.', G5_URL);
}

?>