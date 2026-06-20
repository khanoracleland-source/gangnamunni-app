<?php
include_once("./_common.php");
include_once("./setup.php");

// 로그인 유지를 위한 세션 갱신
if ($is_member && $member['mb_id']) {
    // 세션 시간 연장
    if (isset($_SESSION)) {
        session_regenerate_id(false);
    }
    
    // 쿠키 갱신 (자동 로그인이 설정되어 있다면)
    if (isset($_COOKIE['ck_mb_id']) && $_COOKIE['ck_mb_id'] === $member['mb_id']) {
        $expire = time() + (86400 * 30); // 30일
        setcookie('ck_mb_id', $member['mb_id'], $expire, '/', '', false, true);
        if (isset($_COOKIE['ck_auto'])) {
            setcookie('ck_auto', $_COOKIE['ck_auto'], $expire, '/', '', false, true);
        }
    }
}

// 캐시 방지 헤더
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// POST 데이터 검증
$pgstc = isset($_POST['gstc']) ? (int)$_POST['gstc'] : 0;
$pmpoint = isset($_POST['pmpoint']) ? (int)$_POST['pmpoint'] : 0;
$okmoney = isset($_POST['okmoney']) ? (int)$_POST['okmoney'] : 0;
$tokenkey = isset($_POST['tokenkey']) ? (int)$_POST['tokenkey'] : 0;
$sgametokenkey = isset($_POST['gamekey']) ? (int)$_POST['gamekey'] : 0;

// 회원 정보 확인
if (!$is_member || !$member['mb_id']) {
    echo "<script>alert('로그인이 필요합니다.');</script>";
    exit;
}

$member_point = (int)$member['mb_point'];
$member_id = sql_real_escape_string($member['mb_id']);

// 기본 유효성 검사
if (!in_array($pgstc, [1, 2])) {
    echo "<script>alert('정상적인 방법으로 게임을 진행하세요.');</script>";
    exit;
}

// 하루 게임 횟수 확인
$today_date = G5_TIME_YMD;
$sql = " SELECT COUNT(*) as cnt FROM {$g5['point_table']}
         WHERE po_rel_table = '@daroll'
         AND mb_id = '{$member_id}'
         AND SUBSTRING(po_rel_action,1,10) = '{$today_date}' ";
$row = sql_fetch($sql);
$today_cnt = (int)$row['cnt'];

if ($today_cnt > $today_max) {
    echo "<script>alert('하루 게임 한도를 초과했습니다.');</script>";
    exit;
}

if ($pgstc == 1) {
    // ========== 베팅 처리 ==========
    
    echo "<script>console.log('베팅 처리 시작: {$pmpoint}P');</script>";
    
    // 베팅 금액 유효성 검사
    if (!$pmpoint || $pmpoint < $set_min_point) {
        echo "<script>alert('최소 베팅 금액은 " . number_format($set_min_point) . "P 입니다.');</script>";
        exit;
    }
    
    // 포인트 부족 확인
    if ($member_point < $pmpoint) {
        echo "<script>alert('보유 포인트가 부족합니다.\\n현재 보유: " . number_format($member_point) . "P');</script>";
        exit;
    }
    
    if ($member_point < $min_point) {
        echo "<script>alert('게임 참여를 위해서는 최소 " . number_format($min_point) . "P가 필요합니다.');</script>";
        exit;
    }
    
    // 베팅 포인트 차감
    $po_content = "돼지레이싱 베팅 차감";
    $po_memo = "베팅금액: " . number_format($pmpoint) . "P";
    
    $result = insert_point($member_id, $pmpoint * (-1), $po_content, "@daroll", $member_id, G5_TIME_YMDHIS . "_" . uniqid(), $po_memo);
    
    if ($result) {
        echo "<script>console.log('베팅 차감 성공: {$pmpoint}P');</script>";
        
        // 클라이언트 업데이트
        $new_point = $member_point - $pmpoint;
        echo "<script type='text/javascript'>
            console.log('포인트 차감: {$pmpoint}P, 남은 포인트: {$new_point}P');
            if(parent && parent.document) {
                var pointsEl = parent.document.getElementById('currentPoints');
                if(pointsEl) {
                    pointsEl.textContent = '" . number_format($new_point) . "P';
                }
            }
        </script>";
    } else {
        echo "<script>alert('베팅 처리 중 오류가 발생했습니다.');</script>";
    }

} elseif ($pgstc == 2) {
    // ========== 상금 지급 처리 ==========
    
    echo "<script>console.log('상금 지급 시작: {$okmoney}P');</script>";
    
    // 상금 유효성 검사
    if (!$okmoney || $okmoney < 10) {
        echo "<script>alert('비정상적인 상금 금액입니다.');</script>";
        exit;
    }
    
    // 최대 상금 제한 확인 (안전장치) - 1억으로 설정
    $max_possible_win = 100000000; // 1억
    if ($okmoney > $max_possible_win) {
        echo "<script>alert('비정상적인 상금 금액이 감지되었습니다.');</script>";
        exit;
    }
    
    // 포인트 지급
    $po_content = "돼지레이싱 상금 획득";
    $po_memo = "상금: " . number_format($okmoney) . "P";
    
    $result = insert_point($member_id, $okmoney, $po_content, "@dokroll", $member_id, "WIN_" . G5_TIME_YMDHIS . "_" . uniqid(), $po_memo);
    
    if ($result) {
        echo "<script>console.log('상금 지급 성공: {$okmoney}P');</script>";
        
        // 클라이언트 업데이트
        $new_point = $member_point + $okmoney;
        echo "<script type='text/javascript'>
            console.log('상금 지급: {$okmoney}P, 총 포인트: {$new_point}P');
            if(parent && parent.document) {
                var pointsEl = parent.document.getElementById('currentPoints');
                if(pointsEl) {
                    pointsEl.textContent = '" . number_format($new_point) . "P';
                }
            }
        </script>";
    } else {
        echo "<script>alert('상금 지급 중 오류가 발생했습니다.');</script>";
    }
    
} else {
    echo "<script>alert('잘못된 요청입니다.');</script>";
    exit;
}
?>