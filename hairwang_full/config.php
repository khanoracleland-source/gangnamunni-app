<?php

/********************
    상수 선언
********************/

// 이 상수가 정의되지 않으면 각각의 개별 페이지는 별도로 실행될 수 없음
define('_GNUBOARD_', true);

/** @var array $g5_path common.php 가 이 파일을 불러오기 전에 만들어 둔다 */
include_once($g5_path['path'].'/version.php');   // 설정 파일

// 기본 시간대 설정
date_default_timezone_set("Asia/Seoul");
// DATETIME token expiry is stored in the same timezone as PHP date().
if (!defined('G5_TIMEZONE')) define('G5_TIMEZONE', '+09:00');

/********************
    경로 상수
********************/

/*
보안서버 도메인
회원가입, 글쓰기에 사용되는 https 로 시작되는 주소를 말합니다.
포트가 있다면 도메인 뒤에 :443 과 같이 입력하세요.
보안서버주소가 없다면 공란으로 두시면 되며 보안서버주소 뒤에 / 는 붙이지 않습니다.
입력 예) https://www.domain.com:443/gnuboard5
*/
$g5_request_host = $_SERVER['HTTP_HOST'] ?? '';
$g5_host_without_port = strtolower(preg_replace('/:\\d+$/', '', $g5_request_host));
$g5_is_local_request = in_array($g5_host_without_port, ['127.0.0.1', 'localhost', '::1'], true);
$g5_request_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$g5_domain = $g5_is_local_request
    ? $g5_request_scheme . '://' . $g5_request_host
    : 'https://hms.seoulbeauty.app/hairwang';

define('G5_DOMAIN', $g5_domain);
define('G5_HTTPS_DOMAIN', $g5_domain);

// 그누보드 디버그바 설정입니다, 실제 서버운영시 false 로 설정해 주세요.
define('G5_DEBUG', false);
define('G5_COLLECT_QUERY', false);

// Set Database table default engine is Database default_storage_engine, If you want to use MyISAM or InnoDB, change to MyISAM or InnoDB.
// DB에 테이블 생성 시 테이블의 기본 스토리지 엔진을 설정할 수 있습니다.
// InnoDB 또는 MyISAM 으로 설정 가능합니다.
// 빈값으로 두면 DB 버전이나 호스팅사 정책의 기본값에 따라 설정됩니다.
define('G5_DB_ENGINE', '');

// Set Database table default Charset
// utf8, utf8mb4 등 지정 가능 기본값은 utf8, 설치전에 utf8mb4 으로 수정 시 모든 테이블에 이모지 입력이 가능합니다.
// utf8mb4 인코딩은 MySQL 또는 MariaDB 5.5 버전 이상을 요구합니다.
define('G5_DB_CHARSET', 'utf8mb4');

/*
www.sir.kr 과 sir.kr 도메인은 서로 다른 도메인으로 인식합니다. 쿠키를 공유하려면 .sir.kr 과 같이 입력하세요.
이곳에 입력이 없다면 www 붙은 도메인과 그렇지 않은 도메인은 쿠키를 공유하지 않으므로 로그인이 풀릴 수 있습니다.
*/
define('G5_COOKIE_DOMAIN',  '');

/*
소셜 로그인(plugin/social)의 HybridAuth 엔드포인트는 G5_HTTPS_DOMAIN 기준이라
항상 hms.seoulbeauty.app 에서 열린다. 공개 사이트(seoulbeauty.app)에서 로그인을
시작하면 세션 쿠키가 host-only 라서 그 호스트로 전달되지 않고, HybridAuth 가
412 "You cannot access this page directly." 를 던진다.

그래서 "세션 쿠키만" 서브도메인 공용으로 만든다. G5_COOKIE_DOMAIN(= set_cookie()
기본 도메인)은 건드리지 않아 ck_* 등 나머지 쿠키의 동작은 그대로 유지된다.
localhost/IP 접속에서는 도메인 쿠키가 거부되므로 빈 값으로 둔다.
*/
$g5_session_cookie_host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
define('G5_SESSION_COOKIE_DOMAIN',
    ($g5_session_cookie_host === 'seoulbeauty.app' || substr($g5_session_cookie_host, -16) === '.seoulbeauty.app')
        ? '.seoulbeauty.app'
        : '');

/*
공용 세션 쿠키는 기존 host-only 쿠키와 "이름이 반드시 달라야" 한다.
이름이 같으면 브라우저는 (host-only / domain) 두 쿠키를 모두 보관하고 한 요청에
둘 다 보내는데, PHP 는 Cookie 헤더의 "첫 번째" 값만 취한다(직접 확인함).
RFC 6265 5.4 에 따라 먼저 만들어진 예전 쿠키가 앞에 오므로, 이름이 같으면
새 공용 쿠키는 영구히 가려져 아무 효과가 없다. 이름을 분리하면 두 쿠키가
서로 다른 키가 되어 어떤 값이 쓰일지에 대한 모호함 자체가 사라진다.
*/
define('G5_SHARED_SESSION_NAME', 'SBSESSID');

define('G5_DBCONFIG_FILE',  'dbconfig.php');

define('G5_ADMIN_DIR',      'adm');
define('G5_BBS_DIR',        'bbs');
define('G5_CSS_DIR',        'css');
define('G5_DATA_DIR',       'data');
define('G5_EXTEND_DIR',     'extend');
define('G5_IMG_DIR',        'img');
define('G5_JS_DIR',         'js');
define('G5_LIB_DIR',        'lib');
define('G5_PLUGIN_DIR',     'plugin');
define('G5_SKIN_DIR',       'skin');
define('G5_EDITOR_DIR',     'editor');
define('G5_MOBILE_DIR',     'mobile');
define('G5_OKNAME_DIR',     'okname');

define('G5_KCPCERT_DIR',    'kcpcert');
define('G5_INICERT_DIR',     'inicert');
define('G5_LGXPAY_DIR',     'lgxpay');

define('G5_SNS_DIR',        'sns');
define('G5_SYNDI_DIR',      'syndi');
define('G5_PHPMAILER_DIR',  'PHPMailer');
define('G5_SESSION_DIR',    'session');
define('G5_THEME_DIR',      'theme');

define('G5_GROUP_DIR',      'group');
define('G5_CONTENT_DIR',    'content');

// URL 은 브라우저상에서의 경로 (도메인으로 부터)
if (G5_DOMAIN) {
    define('G5_URL', G5_DOMAIN);
} else {
    if (isset($g5_path['url']))
        define('G5_URL', $g5_path['url']);
    else
        define('G5_URL', '');
}

if (isset($g5_path['path'])) {
    define('G5_PATH', $g5_path['path']);
} else {
    define('G5_PATH', '');
}

define('G5_ADMIN_URL',      G5_URL.'/'.G5_ADMIN_DIR);
define('G5_BBS_URL',        G5_URL.'/'.G5_BBS_DIR);
define('G5_CSS_URL',        G5_URL.'/'.G5_CSS_DIR);
define('G5_DATA_URL',       G5_URL.'/'.G5_DATA_DIR);
define('G5_IMG_URL',        G5_URL.'/'.G5_IMG_DIR);
define('G5_JS_URL',         G5_URL.'/'.G5_JS_DIR);
define('G5_SKIN_URL',       G5_URL.'/'.G5_SKIN_DIR);
define('G5_PLUGIN_URL',     G5_URL.'/'.G5_PLUGIN_DIR);
define('G5_EDITOR_URL',     G5_PLUGIN_URL.'/'.G5_EDITOR_DIR);
define('G5_OKNAME_URL',     G5_PLUGIN_URL.'/'.G5_OKNAME_DIR);
define('G5_KCPCERT_URL',    G5_PLUGIN_URL.'/'.G5_KCPCERT_DIR);
define('G5_INICERT_URL',     G5_PLUGIN_URL.'/'.G5_INICERT_DIR);
define('G5_LGXPAY_URL',     G5_PLUGIN_URL.'/'.G5_LGXPAY_DIR);
define('G5_SNS_URL',        G5_PLUGIN_URL.'/'.G5_SNS_DIR);
define('G5_SYNDI_URL',      G5_PLUGIN_URL.'/'.G5_SYNDI_DIR);
define('G5_MOBILE_URL',     G5_URL.'/'.G5_MOBILE_DIR);

// PATH 는 서버상에서의 절대경로
define('G5_ADMIN_PATH',     G5_PATH.'/'.G5_ADMIN_DIR);
define('G5_BBS_PATH',       G5_PATH.'/'.G5_BBS_DIR);
define('G5_DATA_PATH',      G5_PATH.'/'.G5_DATA_DIR);
define('G5_EXTEND_PATH',    G5_PATH.'/'.G5_EXTEND_DIR);
define('G5_LIB_PATH',       G5_PATH.'/'.G5_LIB_DIR);
define('G5_PLUGIN_PATH',    G5_PATH.'/'.G5_PLUGIN_DIR);
define('G5_SKIN_PATH',      G5_PATH.'/'.G5_SKIN_DIR);
define('G5_MOBILE_PATH',    G5_PATH.'/'.G5_MOBILE_DIR);
define('G5_SESSION_PATH',   G5_DATA_PATH.'/'.G5_SESSION_DIR);
define('G5_EDITOR_PATH',    G5_PLUGIN_PATH.'/'.G5_EDITOR_DIR);
define('G5_OKNAME_PATH',    G5_PLUGIN_PATH.'/'.G5_OKNAME_DIR);

define('G5_KCPCERT_PATH',   G5_PLUGIN_PATH.'/'.G5_KCPCERT_DIR);
define('G5_INICERT_PATH',   G5_PLUGIN_PATH.'/'.G5_INICERT_DIR);
define('G5_LGXPAY_PATH',    G5_PLUGIN_PATH.'/'.G5_LGXPAY_DIR);

define('G5_SNS_PATH',       G5_PLUGIN_PATH.'/'.G5_SNS_DIR);
define('G5_SYNDI_PATH',     G5_PLUGIN_PATH.'/'.G5_SYNDI_DIR);
define('G5_PHPMAILER_PATH', G5_PLUGIN_PATH.'/'.G5_PHPMAILER_DIR);
//==============================================================================


//==============================================================================
// 사용기기 설정
// pc 설정 시 모바일 기기에서도 PC화면 보여짐
// mobile 설정 시 PC에서도 모바일화면 보여짐
// both 설정 시 접속 기기에 따른 화면 보여짐
//------------------------------------------------------------------------------
define('G5_SET_DEVICE', 'both');

define('G5_USE_MOBILE', true); // 모바일 홈페이지를 사용하지 않을 경우 false 로 설정
define('G5_USE_CACHE',  false); // 최신글등에 cache 기능 사용 여부


/********************
    시간 상수
********************/
// 서버의 시간과 실제 사용하는 시간이 틀린 경우 수정하세요.
// 하루는 86400 초입니다. 1시간은 3600초
// 6시간이 빠른 경우 time() + (3600 * 6);
// 6시간이 느린 경우 time() - (3600 * 6);
define('G5_SERVER_TIME',    time());
define('G5_TIME_YMDHIS',    date('Y-m-d H:i:s', G5_SERVER_TIME));
define('G5_TIME_YMD',       substr(G5_TIME_YMDHIS, 0, 10));
define('G5_TIME_HIS',       substr(G5_TIME_YMDHIS, 11, 8));

// 입력값 검사 상수 (숫자를 변경하시면 안됩니다.)
define('G5_ALPHAUPPER',      1); // 영대문자
define('G5_ALPHALOWER',      2); // 영소문자
define('G5_ALPHABETIC',      4); // 영대,소문자
define('G5_NUMERIC',         8); // 숫자
define('G5_HANGUL',         16); // 한글
define('G5_SPACE',          32); // 공백
define('G5_SPECIAL',        64); // 특수문자

// SEO TITLE 문단 길이
define('G5_SEO_TITLE_WORD_CUT', 8);        // SEO TITLE 문단 길이

// 퍼미션
define('G5_DIR_PERMISSION',  0755); // 디렉토리 생성시 퍼미션
define('G5_FILE_PERMISSION', 0644); // 파일 생성시 퍼미션

// 모바일 인지 결정 $_SERVER['HTTP_USER_AGENT']
define('G5_MOBILE_AGENT',   'phone|samsung|lgtel|mobile|[^A]skt|nokia|blackberry|BB10|android|sony');

// SMTP
// lib/mailer.lib.php 에서 사용
define('G5_SMTP',      'localhost');
define('G5_SMTP_PORT', '587');
define('G5_SMTP_USER', 'admin@seoulbeauty.app');
// SMTP 비밀번호는 소스에 두지 않는다 — 환경변수, 없으면 gangnamunni/.env 의
// MAIL_ADMIN_PASSWORD 에서 읽는다(.env 는 .gitignore 에 있고 웹에서도 차단됨).
// 이전에는 이 자리에 평문 비밀번호가 있었고, 그 값이 git 이력과 인수인계/도움말
// 페이지에 함께 퍼져 있었다.
$_g5_smtp_pass = getenv('G5_SMTP_PASS') ?: (getenv('MAIL_ADMIN_PASSWORD') ?: '');
if ($_g5_smtp_pass === '') {
    $_g5_env_file = __DIR__ . '/gangnamunni/.env';
    if (is_readable($_g5_env_file)) {
        foreach (file($_g5_env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_g5_line) {
            if (strpos(ltrim($_g5_line), '#') === 0 || strpos($_g5_line, '=') === false) continue;
            list($_g5_k, $_g5_v) = explode('=', $_g5_line, 2);
            if (trim($_g5_k) === 'MAIL_ADMIN_PASSWORD') {
                $_g5_smtp_pass = trim($_g5_v, " \t\"'");
                break;
            }
        }
    }
    unset($_g5_env_file, $_g5_line, $_g5_k, $_g5_v);
}
if ($_g5_smtp_pass === '') {
    error_log('[config] G5_SMTP_PASS 를 환경변수/.env 에서 찾지 못했습니다 — 메일 발송이 실패합니다.');
}
define('G5_SMTP_PASS', $_g5_smtp_pass);
unset($_g5_smtp_pass);


/********************
    기타 상수
********************/

// 암호화 함수 지정
// 사이트 운영 중 설정을 변경하면 로그인이 안되는 등의 문제가 발생합니다.
// 5.4 버전 이전에는 sql_password 이 사용됨, 5.4 버전부터 기본이 create_hash 로 변경
//define('G5_STRING_ENCRYPT_FUNCTION', 'sql_password');
define('G5_STRING_ENCRYPT_FUNCTION', 'create_hash');
define('G5_MYSQL_PASSWORD_LENGTH', 41);         // mysql password length 41, old_password 의 경우에는 16

// SQL 에러를 표시할 것인지 지정
// 에러를 표시하려면 true 로 변경
define('G5_DISPLAY_SQL_ERROR', false);

// escape string 처리 함수 지정
// addslashes 로 변경 가능
define('G5_ESCAPE_FUNCTION', 'sql_escape_string');

// sql_escape_string 함수에서 사용될 패턴
//define('G5_ESCAPE_PATTERN',  '/(and|or).*(union|select|insert|update|delete|from|where|limit|create|drop).*/i');
//define('G5_ESCAPE_REPLACE',  '');

// 게시판에서 링크의 기본개수를 말합니다.
// 필드를 추가하면 이 숫자를 필드수에 맞게 늘려주십시오.
define('G5_LINK_COUNT', 2);

// 썸네일 jpg Quality 설정
define('G5_THUMB_JPG_QUALITY', 90);

// 썸네일 png Compress 설정
define('G5_THUMB_PNG_COMPRESS', 5);

// 모바일 기기에서 DHTML 에디터 사용여부를 설정합니다.
define('G5_IS_MOBILE_DHTML_USE', false);

// MySQLi 사용여부를 설정합니다.
define('G5_MYSQLI_USE', true);

// Browscap 사용여부를 설정합니다.
define('G5_BROWSCAP_USE', true);

// 접속자 기록 때 Browscap 사용여부를 설정합니다.
define('G5_VISIT_BROWSCAP_USE', false);

// ip 숨김방법 설정
/* 123.456.789.012 ip의 숨김 방법을 변경하는 방법은
\\1 은 123, \\2는 456, \\3은 789, \\4는 012에 각각 대응되므로
표시되는 부분은 \\1 과 같이 사용하시면 되고 숨길 부분은 ♡등의
다른 문자를 적어주시면 됩니다.
*/
define('G5_IP_DISPLAY', '\\1.♡.\\3.\\4');

// KAKAO 우편번호 서비스 CDN
define('G5_POSTCODE_JS', '<script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js" async></script>');

// 회원권한을 SELECT 형식으로 얻음, 숫자가 아닌 명칭을 사용_2025_01_03 by jay
$member_levels = array(
	1=> '비회원',
	2=> '회원',
	3=> '등급1',
	4=> '등급2',
	5=> '등급3',
	6=> '등급4',
	7=> '등급5',
	8=> '등급6',
	9=> '등급7',
	10=> '최고관리자',
);
