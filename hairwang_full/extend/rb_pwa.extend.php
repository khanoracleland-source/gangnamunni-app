<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

add_replace('admin_menu', 'add_admin_bbs_menu_pwa', 0, 1); // 관리자 메뉴를 추가함

function add_admin_bbs_menu_pwa($admin_menu){ // 메뉴추가
    
    $admin_menu['menu000'][] =  array('000761', 'PWA 설정', G5_ADMIN_URL . '/rb/rb_pwa_config.php',   'rb_config');
    $admin_menu['menu000'][] =  array('000762', 'PWA 알림발송', G5_ADMIN_URL . '/rb/rb_pwa_send.php',   'rb_config');
    $admin_menu['menu000'][] =  array('000763', 'PWA 알림발송 이력', G5_ADMIN_URL . '/rb/rb_pwa_logs.php',   'rb_config');
    $admin_menu['menu000'][] =  array('000764', 'PWA 알림수신 현황', G5_ADMIN_URL . '/rb/rb_pwa_installs.php',   'rb_config');
    $admin_menu['menu000'][] =  array('000765', 'PWA 앱 설치 현황', G5_ADMIN_URL . '/rb/rb_pwa_app_installs.php',   'rb_config');
    
    $admin_menu['menu000'][] = array('000000', '　', G5_ADMIN_URL, 'rb_config');
    return $admin_menu;
}

// PWA 1인 대상 발송 공용 함수
// send_pwa_if_needed('수신ID', '발신ID', '제목', 'URL', '내용');
if (!function_exists('send_pwa_if_needed')) {
    function send_pwa_if_needed($recv_mb_id, $send_mb_id, $title, $link_url, $body_for_push) {
        global $config, $rb_builder;

        // PWA 사용 여부
        $cfg = sql_fetch("SELECT push_yn FROM rb_pwa_config WHERE id=1");
        if (empty($cfg['push_yn'])) return;
        if (!$recv_mb_id) return;

        // 실제 발송
        $lib = G5_PATH.'/rb/rb.mod/pwa/inc/pwa.lib.php';
        if (!function_exists('rb_pwa_notify_member') && is_file($lib)) {
            include_once $lib;
        }
        if (function_exists('rb_pwa_notify_member')) {
            // rb_pwa_notify_member($mb_id, $title, $body, $url)
            rb_pwa_notify_member($recv_mb_id, (string)$title, (string)$body_for_push, (string)$link_url);
        }
    }
}


if (function_exists('add_event')) {
    add_event('memo_form_update_before', 'rb_memo_before_fix'); // 인자 없어도 동작
    add_event('memo_form_update_after',  'rb_memo_after_push');  // 인자 없어도 동작
}

/* BEFORE: 인자 없이도 $_POST에서 직접 복원 */
if (!function_exists('rb_memo_before_fix')) {
    function rb_memo_before_fix() { // ← 파라미터 제거
        // me_memo를 항상 문자열로 확정 (Deprecated 방지)
        if (!isset($_POST['me_memo']) || $_POST['me_memo'] === null) $_POST['me_memo'] = '';
        else if (!is_string($_POST['me_memo'])) $_POST['me_memo'] = (string)$_POST['me_memo'];
        $_POST['me_memo'] = preg_replace("#[\\\\]+$#", "", substr(trim($_POST['me_memo']), 0, 65536));

        // recv_list도 $_POST에서 복원
        $recv_raw  = isset($_POST['me_recv_mb_id']) ? (string)$_POST['me_recv_mb_id'] : '';
        $recv_list = $recv_raw === '' ? [] : explode(',', trim($recv_raw));

        // 정규화해서 폴백 저장
        $ids = [];
        foreach ((array)$recv_list as $id) {
            $id = substr(preg_replace('/[^a-zA-Z0-9_]/', '', (string)$id), 0, 20);
            if ($id !== '') $ids[] = $id;
        }
        $GLOBALS['RB_MEMO_STAGE'] = [
            'ids'  => array_values(array_unique($ids)),
            'memo' => $_POST['me_memo'],
        ];
    }
}

/* AFTER: 인자 없이 호출돼도 func_get_args()로 안전 처리 후 푸시 */
if (!function_exists('rb_memo_after_push')) {
    function rb_memo_after_push() { // ← 파라미터 선언 안 함
        global $member, $g5;

        if (!function_exists('send_pwa_if_needed')) return;

        $send_mb_id   = (string)($member['mb_id']  ?? '');
        $send_mb_nick = (string)($member['mb_nick']?? $send_mb_id);
        if ($send_mb_id === '') return;

        // 훅 인자 안전 추출
        $a = func_get_args();
        $member_list   = isset($a[0]) && is_array($a[0]) ? $a[0] : null;
        $me_memo       = isset($a[3]) ? (string)$a[3] : ''; // 코어가 $_POST['me_memo']를 그대로 넣어줌

        // 수신자: after 인자 우선, 없으면 before 폴백
        $recv_ids = [];
        $me_ids   = [];
        if ($member_list && !empty($member_list['id'])) {
            foreach ($member_list['id'] as $id) {
                $id = substr(preg_replace('/[^a-zA-Z0-9_]/', '', (string)$id), 0, 20);
                if ($id !== '') $recv_ids[] = $id;
            }
            if (!empty($member_list['me_id']) && is_array($member_list['me_id'])) $me_ids = $member_list['me_id'];
        } elseif (!empty($GLOBALS['RB_MEMO_STAGE']['ids'])) {
            $recv_ids = $GLOBALS['RB_MEMO_STAGE']['ids'];
        }
        if (!$recv_ids) return;

        // 본문: after 인자 우선 → before 폴백
        $plain = $me_memo !== '' ? $me_memo : (string)($GLOBALS['RB_MEMO_STAGE']['memo'] ?? '');
        $plain = strip_tags($plain);
        if (function_exists('cut_str')) $plain = cut_str($plain, 120, '...');
        else if (mb_strlen($plain,'UTF-8')>120) $plain = mb_substr($plain,0,120,'UTF-8').'...';

        $title = '쪽지가 도착했어요!';

        foreach ($recv_ids as $i => $rid) {
            $me_id = isset($me_ids[$i]) ? (int)$me_ids[$i] : 0;
            //$link  = G5_URL.'/bbs/memo.php?kind=recv';
            //if ($me_id > 0) $link = G5_URL.'/bbs/memo_view.php?me_id='.$me_id.'&kind=recv';
            $link  = G5_URL;
            if ($me_id > 0) $link = G5_URL; //쪽지의 경우 url을 주면 쪽지창으로 가기때문에 메인으로 처리

            send_pwa_if_needed($rid, $send_mb_id, $title, $link, '['.$send_mb_nick.'] '.$plain);
        }
    }
}



// 관리자/adm 페이지는 제외
$uri = $_SERVER['REQUEST_URI'] ?? '';
if (defined('G5_IS_ADMIN') || strpos($uri, '/adm/') === 0) return;

/**
 * </head> 직전에 PWA 관련 태그 주입
 */
if (!function_exists('rb_pwa_head_injector')) {
    function rb_pwa_head_injector($buffer) {
        // head 없는 응답(ajax/json 등)은 무시
        if (stripos($buffer, '</head>') === false) return $buffer;

        global $member;

        // DB에서 PWA 설정 읽기
        $cfg = sql_fetch("SELECT use_yn, push_yn, level_min, theme_color,
                                 words1, words2, words3, words4, pop1_use, pop2_use
                            FROM rb_pwa_config
                           WHERE id=1");

        $enabled     = !empty($cfg['use_yn']);
        $pushEnabled = !empty($cfg['push_yn']);
        $needLevel   = (int)($cfg['level_min'] ?? 1);
        $userLevel   = isset($member['mb_level']) ? (int)$member['mb_level'] : 1;

        // 사용안함이거나 레벨 미달이면 패스
        if (!$enabled || $userLevel < $needLevel) return $buffer;

        // 중복 검사(이미 있는 경우는 다시 안 넣음)
        $hasManifest   = (bool)preg_match('/<link[^>]+rel=["\']manifest["\'][^>]*>/i', $buffer);
        $hasThemeColor = (bool)preg_match('/<meta\s+name=["\']theme-color["\'][^>]*>/i', $buffer);
        $hasPwaInit    = (bool)preg_match('#<script[^>]+src=["\'][^"\']*/rb/rb\.mod/pwa/pwa-init\.js[^"\']*["\']#i', $buffer);
        $hasCfgFlag    = (bool)preg_match('/window\.RB_PWA_CFG\s*=/', $buffer);
        $hasUiFlag     = (bool)preg_match('/window\.RB_PWA_UI\s*=/', $buffer);

        $lines = [];

        // manifest
        if (!$hasManifest) {
            $lines[] = '<link rel="manifest" href="'.G5_URL.'/rb/rb.mod/pwa/manifest.php?ver='.G5_SERVER_TIME.'">';
        }

        // theme-color
        if (!$hasThemeColor) {
            $theme = $cfg['theme_color'] ?: '#111111';
            // get_text가 있으면 사용(gnuboard), 없으면 htmlspecialchars
            if (function_exists('get_text')) $theme = get_text($theme);
            else $theme = htmlspecialchars((string)$theme, ENT_QUOTES);
            $lines[] = '<meta name="theme-color" content="'.$theme.'">';
        }

        // 전역 플래그
        if (!$hasCfgFlag) {
            $lines[] = '<script>window.RB_PWA_CFG={enabled:1,push:'.(int)$pushEnabled.'};</script>';
        }
        
        // 관리자 UI 설정 전역 주입
        if (!$hasUiFlag) {

            $payload = json_encode([
                'words1'   => (string)($cfg['words1'] ?? ''),   // 알림팝업 제목
                'words2'   => (string)($cfg['words2'] ?? ''),   // 알림팝업 본문
                'pop1_use' => (int)($cfg['pop1_use'] ?? 1),     // 알림팝업 사용여부
                'words3'   => (string)($cfg['words3'] ?? ''),   // 설치팝업 제목
                'words4'   => (string)($cfg['words4'] ?? ''),   // 설치팝업 본문
                'pop2_use' => (int)($cfg['pop2_use'] ?? 1),     // 설치팝업 사용여부
            ], JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);

            $lines[] = '<script>window.RB_PWA_UI='.$payload.';</script>';
        }


        // 초기화 스크립트
        if (!$hasPwaInit) {
            $lines[] = '<script src="'.G5_URL.'/rb/rb.mod/pwa/pwa-init.js?ver='.G5_SERVER_TIME.'" async></script>';
        }

        if (!$lines) return $buffer; // 넣을 게 없으면 그대로

        $injection = implode(PHP_EOL, $lines).PHP_EOL;

        // </head> 바로 앞에 1회만 삽입
        return preg_replace('/<\/head>/i', $injection.'</head>', $buffer, 1);
    }
}

// 최종 출력 훅
if (!headers_sent()) {
    ob_start('rb_pwa_head_injector');
}