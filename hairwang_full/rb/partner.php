<?php
include_once('../common.php');

if(isset($pa['pa_is']) && $pa['pa_is'] == 1) {
    
} else { 
    alert('현재 서비스를 이용할 수 없습니다.', G5_URL);
}

if (!$member['mb_id']) {
    alert('회원만 이용하실 수 있습니다.', G5_URL);
}


$mb_id = isset($member['mb_id']) ? $member['mb_id']: '';
$mb = get_member($mb_id);

if (isset($mb['mb_partner']) && $mb['mb_partner'] == 2 || $is_admin) {
    
} else { 
    alert('입점사 회원만 이용하실 수 있습니다.', G5_URL);
}

$g5['title'] = '입점사 전용시스템';
include_once(G5_SHOP_PATH.'/_head.php');

$mb_profile = $mb['mb_profile'] ? conv_content($mb['mb_profile'],0) : '소개 내용이 없습니다.';
$mb_signature = $mb['mb_signature'] ? conv_content($mb['mb_signature'],0) : '서명이 없습니다.';

include_once(G5_SHOP_SKIN_PATH.'/partner.skin.php');
include_once(G5_SHOP_PATH.'/_tail.php');