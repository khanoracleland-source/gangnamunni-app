<?php
include_once('../common.php');
/*
if (!$member['mb_id']) {
    alert('회원만 이용하실 수 있습니다.', G5_URL);
}
*/

$g5['title'] = '출석부';
include_once(G5_BBS_PATH.'/_head.php');


include_once(G5_PATH.'/rb/rb.mod/attendance/attend.view.php');
include_once(G5_BBS_PATH.'/_tail.php');