<?php
// /adm/rb/rb_pwa_config.php
$sub_menu = '000761';
include_once('./_common.php');
auth_check_menu($auth, $sub_menu, "w");

// ---- schema bootstrap: 테이블 없을 때만 생성 ----
function rb_pwa_table_exists($name){
    $row = sql_fetch("SHOW TABLES LIKE '".sql_real_escape_string($name)."'");
    return is_array($row) && count($row);
}

// 추가: 컬럼 존재 체크/추가
function rb_pwa_column_exists($table, $col){
    $r = sql_fetch("SHOW COLUMNS FROM `{$table}` LIKE '".sql_real_escape_string($col)."'");
    return (bool)$r;
}

if (!rb_pwa_table_exists('rb_pwa_config')) {
    // config 테이블이 없으면 관련 테이블 생성
    $schema_sqls = [
"CREATE TABLE IF NOT EXISTS rb_pwa_config (
  id         TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  use_yn     TINYINT(1) NOT NULL DEFAULT 0,
  push_yn    TINYINT(1) NOT NULL DEFAULT 0,
  level_min  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  name       VARCHAR(80)  DEFAULT '',
  short_name VARCHAR(40)  DEFAULT '',
  description  TEXT         DEFAULT NULL,
  start_url  VARCHAR(255) DEFAULT '/',
  scope      VARCHAR(255) DEFAULT '/',
  theme_color VARCHAR(20) DEFAULT '#25282b',
  bg_color    VARCHAR(20) DEFAULT '#25282b',
  icon_192   VARCHAR(255) DEFAULT '',
  icon_512   VARCHAR(255) DEFAULT '',
  install_img  VARCHAR(255) DEFAULT NULL,
  install_img_m VARCHAR(255) DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB",
"INSERT INTO rb_pwa_config (id) VALUES (1) ON DUPLICATE KEY UPDATE id=id",

"CREATE TABLE IF NOT EXISTS rb_pwa_vapid (
  domain   VARCHAR(191) PRIMARY KEY,
  pubkey   TEXT NOT NULL,
  prikey   TEXT NOT NULL,
  reg_dt   DATETIME NOT NULL
) ENGINE=InnoDB",

"CREATE TABLE IF NOT EXISTS rb_pwa_subscriptions (
  id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_uid VARCHAR(64)  NOT NULL DEFAULT '',
  endpoint   TEXT NOT NULL,
  p256dh     VARCHAR(255) NOT NULL,
  auth       VARCHAR(255) NOT NULL,
  mb_id      VARCHAR(50)  DEFAULT '',
  ip         VARCHAR(45)  DEFAULT '',
  ua         VARCHAR(255) DEFAULT '',
  installed  TINYINT(1)   DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uniq_device_uid (device_uid),
  KEY idx_endpoint (endpoint(191)),
  KEY idx_mb_id (mb_id),
  KEY idx_installed (installed)
) ENGINE=InnoDB",

"CREATE TABLE IF NOT EXISTS rb_pwa_push_log (
  id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind      ENUM('test','manual','post') NOT NULL DEFAULT 'manual',
  target    ENUM('all','member') NOT NULL DEFAULT 'all',
  target_memo TEXT DEFAULT NULL,
  title     VARCHAR(120) NOT NULL,
  body      VARCHAR(500) NOT NULL,
  url       VARCHAR(255) DEFAULT '/',
  image     VARCHAR(255) NULL,
  total_cnt INT UNSIGNED DEFAULT 0,
  succ_cnt  INT UNSIGNED DEFAULT 0,
  fail_cnt  INT UNSIGNED DEFAULT 0,
  reg_mb_id VARCHAR(50)  DEFAULT '',
  reg_dt    DATETIME NOT NULL
) ENGINE=InnoDB",

"CREATE TABLE IF NOT EXISTS rb_pwa_push_queue (
  id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  log_id    BIGINT UNSIGNED NOT NULL,
  endpoint  TEXT NOT NULL,
  p256dh    VARCHAR(255) NOT NULL,
  auth      VARCHAR(255) NOT NULL,
  status    ENUM('NEW','SENT','FAIL') NOT NULL DEFAULT 'NEW',
  result    VARCHAR(255) DEFAULT '',
  updated_at DATETIME NOT NULL,
  KEY idx_log_status (log_id, status),
  KEY idx_updated (updated_at)
) ENGINE=InnoDB",

"CREATE TABLE IF NOT EXISTS rb_pwa_app_installs (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  device_uid         VARCHAR(64)  NOT NULL,
  mb_id              VARCHAR(50)  DEFAULT '',
  platform           VARCHAR(32)  DEFAULT '',
  os                 VARCHAR(64)  DEFAULT '',
  browser            VARCHAR(64)  DEFAULT '',
  last_seen_mode     ENUM('standalone','browser','twa','unknown') DEFAULT 'unknown',
  install_source     VARCHAR(64)  DEFAULT '',
  first_installed_at DATETIME     DEFAULT NULL,
  last_opened_at     DATETIME     DEFAULT NULL,
  ip                 VARCHAR(45)  DEFAULT '',
  ua                 VARCHAR(255) DEFAULT '',
  created_at         DATETIME     NOT NULL,
  updated_at         DATETIME     NOT NULL,
  UNIQUE KEY uniq_device_uid (device_uid),
  KEY idx_mb (mb_id),
  KEY idx_platform (platform),
  KEY idx_last_opened (last_opened_at)
) ENGINE=InnoDB"
    ];
    foreach ($schema_sqls as $sql) { sql_query($sql, true); }
} else {
    // 기존 테이블에 새 컬럼 자동 추가
    if (!rb_pwa_column_exists('rb_pwa_config','description')) {
        sql_query("ALTER TABLE rb_pwa_config ADD COLUMN description TEXT DEFAULT NULL", true);
    }
    if (!rb_pwa_column_exists('rb_pwa_config','install_img')) {
        sql_query("ALTER TABLE rb_pwa_config ADD COLUMN install_img VARCHAR(255) DEFAULT NULL", true);
    }
    if (!rb_pwa_column_exists('rb_pwa_config','install_img_m')) { // ← 추가
        sql_query("ALTER TABLE rb_pwa_config ADD COLUMN install_img_m VARCHAR(255) DEFAULT NULL", true);
    }
}


// 이미 선언돼 있다면 재선언 막기
if (!function_exists('rb_pwa_table_exists')) {
    // 테이블 존재 확인 (현재 DB에서)
    function rb_pwa_table_exists($table){
        $table = isset($table) ? $table : '';
        // 그누보드 표준 이스케이프 함수 사용
        if (function_exists('sql_escape_string')) {
            $table = sql_escape_string($table);
        }
        $row = sql_fetch("SHOW TABLES LIKE '{$table}'");
        return (bool)$row;
    }
}

if (!function_exists('rb_pwa_column_exists')) {
    // 컬럼 존재 확인 (권한 문제 없는 SHOW COLUMNS)
    function rb_pwa_column_exists($table, $column){
        if ($column === '' || $column === null) return false;
        if (function_exists('sql_escape_string')) {
            $table  = sql_escape_string($table);
            $column = sql_escape_string($column);
        }
        $row = sql_fetch("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return (bool)$row;
    }
}

if (rb_pwa_table_exists('rb_pwa_config')) {
    $adds = [];

    if (!rb_pwa_column_exists('rb_pwa_config','words1'))   $adds[] = "ADD COLUMN `words1` VARCHAR(255) NULL";
    if (!rb_pwa_column_exists('rb_pwa_config','words2'))   $adds[] = "ADD COLUMN `words2` VARCHAR(255) NULL";
    if (!rb_pwa_column_exists('rb_pwa_config','words3'))   $adds[] = "ADD COLUMN `words3` VARCHAR(255) NULL";
    if (!rb_pwa_column_exists('rb_pwa_config','words4'))   $adds[] = "ADD COLUMN `words4` VARCHAR(255) NULL";
    if (!rb_pwa_column_exists('rb_pwa_config','pop1_use')) $adds[] = "ADD COLUMN `pop1_use` TINYINT(1) NOT NULL DEFAULT 1";
    if (!rb_pwa_column_exists('rb_pwa_config','pop2_use')) $adds[] = "ADD COLUMN `pop2_use` TINYINT(1) NOT NULL DEFAULT 1";

    if ($adds) {
        // 한 번의 ALTER로 실행
        sql_query("ALTER TABLE `rb_pwa_config` ".implode(", ", $adds), true);
    }
}

// ---- helpers (구버전 호환) ----
if (!function_exists('help')) {
    function help($str){ return '<div class="tbl_frm01_help">'.$str.'</div>'; }
}
function rb_pwa_admin_token_check() {
    if (function_exists('check_admin_token')) { check_admin_token(); return; }
    if (function_exists('check_token')) { check_token(); return; }
}
function rb_pwa_admin_token_input_html() {
    if (function_exists('get_admin_token')) {
        $t = get_admin_token();
        if (stripos($t, '<input') !== false) return $t;
        return '<input type="hidden" name="token" value="'.htmlspecialchars($t, ENT_QUOTES).'">';
    }
    if (function_exists('get_token')) {
        $t = get_token();
        return '<input type="hidden" name="token" value="'.htmlspecialchars($t, ENT_QUOTES).'">';
    }
    return '';
}
// URL -> 로컬 경로 추정 (아이콘 존재 여부 확인용)
function rb_pwa_url_to_path($url){
    if (!$url) return '';
    $path = parse_url($url, PHP_URL_PATH);
    if (!$path) return '';
    if (defined('G5_URL') && strpos($url, G5_URL) === 0) return G5_PATH . substr($url, strlen(G5_URL));
    if ($path[0] === '/') return rtrim($_SERVER['DOCUMENT_ROOT'],'/').$path;
    return '';
}

// PNG 정확 픽셀 검증 후 저장
function rb_pwa_check_and_save_png_exact($field, $dest_abs, $needW, $needH) {
    if (empty($_FILES[$field]['name'])) return null; // 업로드 안 함
    $tmp = $_FILES[$field]['tmp_name'];
    if (!is_uploaded_file($tmp)) return '업로드 실패';
    $info = @getimagesize($tmp);
    if (!$info || $info[2] !== IMAGETYPE_PNG) return 'PNG 파일만 가능합니다.';
    if ((int)$info[0] !== (int)$needW || (int)$info[1] !== (int)$needH) {
        return sprintf('이미지 크기 불일치: %dx%d 필요 (현재 %dx%d)', $needW, $needH, $info[0], $info[1]);
    }
    @mkdir(dirname($dest_abs), 0755, true);
    if (!@move_uploaded_file($tmp, $dest_abs)) return '저장 실패';
    @chmod($dest_abs, 0644);
    return true;
}

// start_url 보정: 루트 기준 절대경로 + source=pwa 파라미터 강제
function rb_pwa_ensure_source_pwa($url){
    $u = trim((string)$url);
    if ($u === '') $u = '/';
    if ($u[0] !== '/') $u = '/'.$u;

    // 이미 source= 가 있으면 중복 추가 안 함
    if (!preg_match('/(?:^|[?&])source=/', $u)) {
        $u .= (strpos($u, '?') === false ? '?' : '&').'source=pwa';
    }
    return $u;
}

// 파일명에 따라 권장 비율/사이즈로 "센터-크롭 → 리사이즈" 후 JPG 저장
//  - install-card.jpg         → 1200x675 (16:9, 데스크톱 wide)
//  - install-card-narrow.jpg  → 720x1280 (9:16, 모바일 narrow)
function rb_pwa_save_install_image($field, $dest_abs, &$out_url, $base_url){
    if (empty($_FILES[$field]['name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) return null;

    if (!function_exists('getimagesize')) return 'GD 확장 모듈이 필요합니다.';
    $tmp  = $_FILES[$field]['tmp_name'];
    $info = @getimagesize($tmp);
    if (!$info) return '이미지 파일이 아닙니다.';

    list($w,$h,$type) = $info;
    switch($type){
        case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($tmp); break;
        case IMAGETYPE_PNG:  $src = @imagecreatefrompng($tmp);  break;
        case IMAGETYPE_GIF:  $src = @imagecreatefromgif($tmp);  break;
        case IMAGETYPE_WEBP: $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : null; break;
        default: $src = null;
    }
    if (!$src) return '지원하지 않는 이미지 형식입니다. (JPG/PNG/GIF/WebP)';

    $bn = basename($dest_abs);
    if (strpos($bn, 'install-card-narrow') !== false) {
        $tw=720;  $th=1280; $tr = 9/16;    // 모바일 세로형
    } else {
        $tw=1200; $th=675;  $tr = 16/9;    // 데스크톱 가로형
    }

    // 소스 → 타깃 비율로 센터 크롭
    $sr = $w / $h;
    if ($sr > $tr) { // 가로 과다 → 좌우 크롭
        $cw = (int)round($h * $tr);
        $ch = $h;
        $sx = (int)(($w - $cw)/2);
        $sy = 0;
    } else {         // 세로 과다 → 상하 크롭
        $cw = $w;
        $ch = (int)round($w / $tr);
        $sx = 0;
        $sy = (int)(($h - $ch)/2);
    }

    @mkdir(dirname($dest_abs), 0755, true);

    $crop = imagecreatetruecolor($cw,$ch);
    imagecopy($crop, $src, 0,0, $sx,$sy, $cw,$ch);

    $dst = imagecreatetruecolor($tw,$th);
    // 투명 원본 대비: 흰 배경
    $white = imagecolorallocate($dst, 255,255,255);
    imagefill($dst, 0,0, $white);

    imagecopyresampled($dst, $crop, 0,0, 0,0, $tw,$th, $cw,$ch);

    $ok = @imagejpeg($dst, $dest_abs, 90);
    imagedestroy($dst); imagedestroy($crop); imagedestroy($src);
    if (!$ok) return '이미지 저장 실패 (퍼미션/경로 확인)';

    @chmod($dest_abs,0644);
    $out_url = rtrim($base_url,'/').'/'.$bn; // 실제 파일명으로 URL 반환
    return true;
}

// ---- data dirs (/data/pwa/icons) ----
$DATA_PATH = defined('G5_DATA_PATH') ? G5_DATA_PATH : (G5_PATH.'/data');
$DATA_URL  = defined('G5_DATA_URL')  ? G5_DATA_URL  : (G5_URL .'/data');
$icons_dir = $DATA_PATH.'/pwa/icons';
$icons_url = $DATA_URL .'/pwa/icons';

if (!is_dir($icons_dir)) @mkdir($icons_dir, 0775, true);
if (!is_file($icons_dir.'/index.php')) @file_put_contents($icons_dir.'/index.php', "<?php\n// silence\n");



$g5['title'] = 'PWA 설정';
include_once(G5_ADMIN_PATH.'/admin.head.php');

// 현재 설정 로드
$cfg = sql_fetch("SELECT * FROM rb_pwa_config WHERE id=1");

// 현재 도메인의 VAPID 키 존재 여부
$__host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
$__vap  = sql_fetch("SELECT pubkey FROM rb_pwa_vapid WHERE domain='".sql_real_escape_string($__host)."'");
$__has_vapid = !empty($__vap['pubkey']);

// ---- 저장 처리 ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rb_pwa_admin_token_check();

    $use_yn    = isset($_POST['use_yn']) ? 1 : 0;
    $push_yn   = isset($_POST['push_yn']) ? 1 : 0;
    $level_min = max(1, (int)($_POST['level_min'] ?? 1));
    $name      = sql_real_escape_string(trim(isset($_POST['name']) ? $_POST['name'] : ''));
    $short     = sql_real_escape_string(trim(isset($_POST['name']) ? $_POST['name'] : ''));
    $desc      = sql_real_escape_string(trim($_POST['description'] ?? ''));
    $theme     = sql_real_escape_string(trim(isset($_POST['theme_color']) ? $_POST['theme_color'] : '#25282b'));
    $bg        = sql_real_escape_string(trim(isset($_POST['bg_color']) ? $_POST['bg_color'] : '#25282b'));
    
    $words1     = sql_real_escape_string(trim(isset($_POST['words1']) ? $_POST['words1'] : ''));
    $words2     = sql_real_escape_string(trim(isset($_POST['words2']) ? $_POST['words2'] : ''));
    $words3     = sql_real_escape_string(trim(isset($_POST['words3']) ? $_POST['words3'] : ''));
    $words4     = sql_real_escape_string(trim(isset($_POST['words4']) ? $_POST['words4'] : ''));
    
    $pop1_use     = sql_real_escape_string(trim(isset($_POST['pop1_use']) ? $_POST['pop1_use'] : '0'));
    $pop2_use     = sql_real_escape_string(trim(isset($_POST['pop2_use']) ? $_POST['pop2_use'] : '0'));
    
    $start_url_raw = trim(isset($_POST['start_url']) ? $_POST['start_url'] : '/');
    $scope_raw     = trim(isset($_POST['scope']) ? $_POST['scope'] : '/');

    // 보정: 루트 시작 + source=pwa 부착
    $start_url = sql_real_escape_string(rb_pwa_ensure_source_pwa($start_url_raw));

    // scope는 루트 시작만 보정
    if ($scope_raw === '' || $scope_raw[0] !== '/') $scope_raw = '/';
    $scope = sql_real_escape_string($scope_raw);
    
    // 삭제 체크박스 값
    $del_icon_192 = !empty($_POST['del_icon_192']);
    $del_icon_512 = !empty($_POST['del_icon_512']);
    $del_badge_72 = !empty($_POST['del_badge_72']);
    $del_installimg = !empty($_POST['del_install_img']);
    $del_installimg_m = !empty($_POST['del_install_img_m']);

    // 기존 저장값 유지(텍스트 입력칸 없음)
    $icon_192_path = !empty($cfg['icon_192']) ? $cfg['icon_192'] : ($icons_url.'/icon-192.png');
    $icon_512_path = !empty($cfg['icon_512']) ? $cfg['icon_512'] : ($icons_url.'/icon-512.png');
    $install_img    = $cfg['install_img'] ?? null; 
    $install_img_m   = $cfg['install_img_m'] ?? null;
    
    // 실제 파일 경로
    $abs_192_cfg = rb_pwa_url_to_path(isset($cfg['icon_192']) ? $cfg['icon_192'] : '');
    $abs_512_cfg = rb_pwa_url_to_path(isset($cfg['icon_512']) ? $cfg['icon_512'] : '');
    $abs_192_std = $icons_dir.'/icon-192.png';
    $abs_512_std = $icons_dir.'/icon-512.png';
    $abs_badge   = $icons_dir.'/badge-72.png';
    
    // 설치 이미지 경로(/data/pwa/)
    $pwa_dir     = (defined('G5_DATA_PATH') ? G5_DATA_PATH : (G5_PATH.'/data')).'/pwa';
    $pwa_url     = (defined('G5_DATA_URL')  ? G5_DATA_URL  : (G5_URL .'/data')).'/pwa';
    @mkdir($pwa_dir, 0775, true);
    if (!is_file($pwa_dir.'/index.php')) @file_put_contents($pwa_dir.'/index.php', "<?php\n// silence\n");

    
    // 1) 삭제 먼저 처리 (체크 시 DB 경로도 비움)
    if ($del_icon_192) {
        @unlink($abs_192_std);
        if ($abs_192_cfg && is_file($abs_192_cfg)) @unlink($abs_192_cfg);
        $icon_192_path = ''; // DB에서 비움
    }
    if ($del_icon_512) {
        @unlink($abs_512_std);
        if ($abs_512_cfg && is_file($abs_512_cfg)) @unlink($abs_512_cfg);
        $icon_512_path = ''; // DB에서 비움
    }
    if ($del_badge_72) {
        @unlink($abs_badge);
        // 배지는 DB 칼럼 없음
    }
    if ($del_installimg) {
        @unlink($pwa_dir.'/install-card.jpg');
        $install_img = null;
    }
    if ($del_installimg_m) {
        @unlink($pwa_dir.'/install-card-narrow.jpg');
        $install_img_m = null;
    }

    // 업로드(있을 때만 교체)
    $r = rb_pwa_check_and_save_png_exact('icon_192_file', $icons_dir.'/icon-192.png', 192, 192);
    if ($r === true) {
        $icon_192_path = $icons_url.'/icon-192.png';
    } elseif (is_string($r)) {
        alert('아이콘(192X192) 업로드 실패: '.$r);
    }
    
    $r = rb_pwa_check_and_save_png_exact('icon_512_file', $icons_dir.'/icon-512.png', 512, 512);
    if ($r === true) {
        $icon_512_path = $icons_url.'/icon-512.png';
    } elseif (is_string($r)) {
        alert('아이콘(512X512) 업로드 실패: '.$r);
    }
    
    $r = rb_pwa_check_and_save_png_exact('badge_72_file', $icons_dir.'/badge-72.png', 72, 72);
    if ($r !== null && $r !== true) {
        alert('배지 업로드 실패: '.$r);
    }
    
    // 설치 카드 이미지 업로드 (JPG로 변환 저장)
    $install_upload = rb_pwa_save_install_image('install_img_file', $pwa_dir.'/install-card.jpg', $install_img_url, $pwa_url);
    if ($install_upload === true) {
        $install_img = $install_img_url; // '/data/pwa/install-card.jpg'
    } elseif (is_string($install_upload)) {
        alert('설치 이미지 업로드 실패: '.$install_upload);
    }
    
    // narrow: 모바일 추가
    $install_upload_m = rb_pwa_save_install_image('install_img_m_file', $pwa_dir.'/install-card-narrow.jpg', $install_img_m_url, $pwa_url);
    if ($install_upload_m === true) {
        $install_img_m = $install_img_m_url; // '/data/pwa/install-card-narrow.jpg'
    } elseif (is_string($install_upload_m)) {
        alert('설치 이미지(모바일/narrow) 업로드 실패: '.$install_upload_m);
    }

    sql_query("UPDATE rb_pwa_config SET
        use_yn={$use_yn}, push_yn={$push_yn}, level_min={$level_min},
        name='{$name}', short_name='{$short}', description='{$desc}', 
        start_url='{$start_url}', scope='{$scope}',
        theme_color='{$theme}', bg_color='{$bg}', 
        words1='{$words1}', words2='{$words2}', words3='{$words3}', words4='{$words4}', pop1_use='{$pop1_use}', pop2_use='{$pop2_use}', 
        icon_192='".sql_real_escape_string($icon_192_path)."',
        icon_512='".sql_real_escape_string($icon_512_path)."', 
        install_img ".($install_img===null? "=NULL" : "='".sql_real_escape_string($install_img)."'").", 
        install_img_m ".($install_img_m===null? " = NULL " : " = '".sql_real_escape_string($install_img_m)."'")." 
      WHERE id=1");

    goto_url($_SERVER['SCRIPT_NAME']); exit;
}

// 미리보기는 실제 파일이 있을 때만 출력
$icon192_exist = is_file(rb_pwa_url_to_path(isset($cfg['icon_192']) ? $cfg['icon_192'] : ''));
$icon512_exist = is_file(rb_pwa_url_to_path(isset($cfg['icon_512']) ? $cfg['icon_512'] : ''));
$badge_exist   = is_file($icons_dir.'/badge-72.png');

$token_html = rb_pwa_admin_token_input_html();


?>

<section class="cbox">
    <h2 class="h2_frm">기본설정</h2>

    <form method="post" enctype="multipart/form-data">
        <?php echo $token_html; ?>
        <div class="tbl_frm01 tbl_wrap">
            <table>
                <colgroup>
                    <col class="grid_4">
                    <col>
                </colgroup>
                <tbody>
                    <tr>
                        <th>사용여부</th>
                        <td>
                            <?php echo help('앱설치/푸시알림 기능을 활성화합니다. 체크를 해제하는 경우 사용하지 않습니다.'); ?>
                            <label><input type="checkbox" name="use_yn" value="1" <?php echo !empty($cfg['use_yn'])?'checked':''; ?>> 앱설치 사용</label>
                            &nbsp;&nbsp;
                            <label><input type="checkbox" name="push_yn" value="1" <?php echo !empty($cfg['push_yn'])?'checked':''; ?>> 푸시알림 사용</label>
                        </td>
                    </tr>

                    <tr>
                        <th>설치가능 레벨</th>
                        <td>
                            <?php echo help('1 이면 비회원도 알림수신 및 앱 설치가 가능합니다.'); ?>
                            <?php echo get_member_level_select('level_min', 1, $member['mb_level'], (int)$cfg['level_min']); ?> 레벨부터 사용
                        </td>
                    </tr>

                    <tr>
                        <th>앱 타이틀</th>
                        <td>
                            <?php echo help('앱 설치 완료시 아이콘과 함께 표기 될 대표 타이틀 입니다.'); ?>
                            <input type="text" name="name" value="<?php echo get_text($cfg['name']); ?>" class="frm_input required" size="20" placeholder="앱 타이틀" required>
                        </td>
                    </tr>
                    <tr>
                        <th>앱 설명</th>
                        <td>
                            <?php echo help('앱설치시 설치 팝업에 표기되는 설명글 입니다.'); ?>
                            <input type="text" name="description" value="<?php echo get_text($cfg['description']); ?>" class="frm_input" size="50" style="margin-top:5px" placeholder="앱 설명">
                        </td>
                    </tr>


                    <?php
                      // 현재 설정값을 기준으로 미리 보정
                      $start_url_form = rb_pwa_ensure_source_pwa($cfg['start_url'] ?? '/');
                      $scope_form     = ($cfg['scope'] ?? '/');
                      if ($scope_form === '' || $scope_form[0] !== '/') $scope_form = '/';
                    ?>
                    <input type="hidden" name="start_url" value="<?php echo htmlspecialchars($start_url_form, ENT_QUOTES); ?>" class="frm_input" size="40">
                    <input type="hidden" name="scope" value="<?php echo htmlspecialchars($scope_form, ENT_QUOTES); ?>" class="frm_input" size="40">

                    <tr>
                        <th>상단바 컬러</th>
                        <td>
                            <?php echo help('앱실행시 상단바의 컬러를 설정할 수 있습니다.'); ?>
                            <input type="text" name="theme_color" value="<?php echo get_text($cfg['theme_color']); ?>" class="frm_input" size="10" placeholder="#25282B">
                            <input type="hidden" name="bg_color" value="#FFFFFF" class="frm_input" size="10" placeholder="#FFFFFF">
                        </td>
                    </tr>

                    <tr>
                        <th>스플래시 배경 컬러</th>
                        <td>
                            <?php echo help('앱을 오픈할때 아이콘과 함께 잠시 보이는 인트로 화면의 배경컬러 입니다.'); ?>
                            <input type="text" name="bg_color" value="<?php echo get_text($cfg['bg_color']); ?>" class="frm_input" size="10" placeholder="#25282B">
                        </td>
                    </tr>

                    <tr>
                        <th>아이콘<br>(192px)</th>
                        <td>
                            <?php echo help('PNG(192x192) 파일을 업로드 해주세요.<br>/data/pwa/icons/icon-192.png 로 저장됩니다.'); ?>
                            <input type="file" name="icon_192_file" accept="image/png">
                            <?php if ($icon192_exist) { ?>
                            <div style="margin-top:6px; display:flex; align-items:center; gap:10px;">
                                <img src="<?php echo htmlspecialchars($cfg['icon_192'],ENT_QUOTES); ?>?ver=<?php echo G5_SERVER_TIME ?>" alt="icon-192" style="height:48px;border-radius:8px">
                                <label style="line-height:28px;">
                                    <input type="checkbox" name="del_icon_192" value="1"> 삭제
                                </label>
                            </div>
                            <?php } ?>
                        </td>
                    </tr>

                    <tr>
                        <th>아이콘<br>(512px)</th>
                        <td>
                            <?php echo help('PNG(512x512) 파일을 업로드 해주세요.<br>/data/pwa/icons/icon-512.png 로 저장됩니다.'); ?>
                            <input type="file" name="icon_512_file" accept="image/png">
                            <?php if ($icon512_exist) { ?>
                            <div style="margin-top:6px; display:flex; align-items:center; gap:10px;">
                                <img src="<?php echo htmlspecialchars($cfg['icon_512'],ENT_QUOTES); ?>?ver=<?php echo G5_SERVER_TIME ?>" alt="icon-512" style="height:48px;border-radius:8px">
                                <label style="line-height:28px;">
                                    <input type="checkbox" name="del_icon_512" value="1"> 삭제
                                </label>
                            </div>
                            <?php } ?>
                        </td>
                    </tr>

                    <tr>
                        <th>알림 배지<br>(72px)</th>
                        <td>
                            <?php echo help('PNG(72x72) 파일을 업로드 해주세요.<br>/data/pwa/icons/badge-72.png 로 저장됩니다.<br>배지는 규격상 투명 배경에 여백없는 흰색(단색)으로 업로드 해주세요.'); ?>
                            <input type="file" name="badge_72_file" accept="image/png">
                            <?php if ($badge_exist) { ?>
                            <div style="margin-top:6px; display:flex; align-items:center; gap:10px;">
                                <img src="<?php echo $icons_url.'/badge-72.png'; ?>?ver=<?php echo G5_SERVER_TIME ?>" alt="badge-72" style="height:36px;border-radius:6px; background-color:#f0f5f9;">
                                <label style="line-height:28px;">
                                    <input type="checkbox" name="del_badge_72" value="1"> 삭제
                                </label>
                            </div>
                            <?php } ?>
                        </td>
                    </tr>

                    <?php
                      $install_img_exist   = !empty($cfg['install_img'])   && is_file(rb_pwa_url_to_path($cfg['install_img']));
                      $install_img_m_exist = !empty($cfg['install_img_m']) && is_file(rb_pwa_url_to_path($cfg['install_img_m']));
                    ?>
                    <tr>
                        <th>설치 이미지<br>(데스크톱/WIDE)</th>
                        <td>
                            <?php echo help('리치 설치 UI(데스크톱)용 가로형 이미지를 업로드 해주세요.<br>권장 1280X720, JPG/PNG/GIF/WebP.<br>저장: /data/pwa/install-card.jpg'); ?>
                            <input type="file" name="install_img_file" accept="image/*">
                            <?php if ($install_img_exist) { ?>
                            <div style="margin-top:8px; display:flex; align-items:flex-start; gap:12px;">
                                <img src="<?php echo htmlspecialchars($cfg['install_img'],ENT_QUOTES); ?>?v=<?php echo G5_SERVER_TIME; ?>" alt="install-card-wide" style="max-height:120px; border-radius:8px; background:#f5f7fa;">
                                <label style="line-height:28px;"><input type="checkbox" name="del_install_img" value="1"> 삭제</label>
                            </div>
                            <?php } ?>
                        </td>
                    </tr>

                    <tr>
                        <th>설치 이미지<br>(모바일/NARROW)</th>
                        <td>
                            <?php echo help('리치 설치 UI(모바일)용 세로형 이미지를 업로드 해주세요.<br>권장 720X1280, JPG/PNG/GIF/WebP.<br>저장: /data/pwa/install-card-narrow.jpg'); ?>
                            <input type="file" name="install_img_m_file" accept="image/*"> <!-- ← 추가 -->
                            <?php if ($install_img_m_exist) { ?>
                            <div style="margin-top:8px; display:flex; align-items:flex-start; gap:12px;">
                                <img src="<?php echo htmlspecialchars($cfg['install_img_m'],ENT_QUOTES); ?>?v=<?php echo G5_SERVER_TIME; ?>" alt="install-card-narrow" style="max-width:150px; border-radius:8px; background:#f5f7fa;">
                                <label style="line-height:28px;"><input type="checkbox" name="del_install_img_m" value="1"> 삭제</label>
                            </div>
                            <?php } ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th>알림팝업 설정</th>
                        <td>
                            <?php echo help('알림 해제시 팝업에 사용되는 워딩을 설정할 수 있습니다.'); ?>
                            <input type="text" name="words1" value="<?php echo get_text($cfg['words1']); ?>" class="frm_input" size="20" placeholder="알림이 꺼져 있어요" ><br>
                            <input type="text" name="words2" value="<?php echo get_text($cfg['words2']); ?>" class="frm_input" size="50" style="margin-top:5px;" placeholder="주소(URL) 옆 아이콘을 클릭 하시면 알림 설정을 변경할 수 있어요." ><br>
                            <label><input type="checkbox" name="pop1_use" value="1" <?php echo !empty($cfg['pop1_use'])?'checked':''; ?>> 알림팝업 사용</label>
                        </td>
                    </tr>
                    <tr>
                        <th>앱 설치팝업 설정</th>
                        <td>
                            <?php echo help('앱 미설치시 팝업에 사용되는 워딩을 설정할 수 있습니다.'); ?>
                            <input type="text" name="words3" value="<?php echo get_text($cfg['words3']); ?>" class="frm_input" size="20" placeholder="앱으로 설치하면 더 편해요" ><br>
                            <input type="text" name="words4" value="<?php echo get_text($cfg['words4']); ?>" class="frm_input" size="50" style="margin-top:5px;" placeholder="홈 화면에서 바로 접속하고, 주요 알림을 푸시로 받을 수 있어요." ><br>
                            <label><input type="checkbox" name="pop2_use" value="1" <?php echo !empty($cfg['pop2_use'])?'checked':''; ?>> 앱 설치팝업 사용</label>
                        </td>
                    </tr>

                    <!-- 인라인 점검/키생성/미리보기 -->
                    <tr>
                        <th>환경 점검</th>
                        <td>
                            <a href="javascript:void(0);" class="btn_frmline" style="height:25px; line-height:25px;" onclick="rbpwaCheck()">환경점검 실행</a>
                            <div id="rbpwaCheckBox" style="margin-top:8px; display:none;">
                                <pre id="rbpwaCheckOut" style="max-height:200px;overflow:auto;background:#f0f5f9;padding:15px;border-radius:6px;"></pre>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>VAPID 키</th>
                        <td id="rbpwaVapidTd">
                            <?php echo help('공개키는 알림 구독에 사용됩니다.<br>교체/갱신 시 기존 사용자는 재방문(재구독) 전까지 알림을 받지 못합니다.'); ?>

                            <?php if ($__has_vapid) { ?>
                            <input type="text" id="vapidPub" class="frm_input" readonly value="<?php echo htmlspecialchars($__vap['pubkey'], ENT_QUOTES); ?>" size="20" onclick="this.select()">
                            <a href="javascript:void(0);" class="btn_frmline" id="btnVapidRenew">교체/갱신</a>
                            <?php } else { ?>
                            <a href="javascript:void(0);" class="btn_frmline" id="btnVapidCreate">키생성</a>
                            <?php } ?>

                            <div id="rbpwaVapidBox" style="margin-top:8px; display:none;">
                                <pre id="rbpwaVapidOut" style="max-height:200px;overflow:auto;background:#f0f5f9;padding:15px;border-radius:6px"></pre>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>Manifest 미리보기</th>
                        <td>
                            <a href="javascript:void(0);" class="btn_frmline" style="height:25px; line-height:25px;" onclick="rbpwaManifest()">미리보기</a>
                            <div id="rbpwaManiBox" style="margin-top:8px; display:none;">
                                <pre id="rbpwaManiOut" style="max-height:200px;overflow:auto;background:#f0f5f9;padding:15px;border-radius:6px"></pre>
                            </div>
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>

        <div class="btn_fixed_top">
            <input type="submit" value="저장" class="btn_submit btn" accesskey="s">
        </div>
    </form>
</section>

<script>
    // 콘솔 출력 없이 조용히 동작
    function rbpwaCheck() {
        var box = document.getElementById('rbpwaCheckBox');
        var out = document.getElementById('rbpwaCheckOut');
        box.style.display = 'block';
        out.textContent = '불러오는 중...';
        fetch('/rb/rb.mod/pwa/api/check_host.php', {
                cache: 'no-store'
            })
            .then(function(r) {
                return r.text();
            })
            .then(function(t) {
                out.textContent = t;
            })
            .catch(function() {
                out.textContent = '오류가 발생했습니다.';
            });
    }

    function rbpwaVapid() {
        var box = document.getElementById('rbpwaVapidBox');
        var out = document.getElementById('rbpwaVapidOut');
        box.style.display = 'block';
        out.textContent = '키 생성/갱신 중...';
        fetch('/rb/rb.mod/pwa/api/install_vapid.php', {
                cache: 'no-store'
            })
            .then(function(r) {
                return r.json();
            })
            .then(function(j) {
                if (j && j.ok) {
                    out.textContent = 'OK\npublicKey: ' + (j.publicKey || '');
                } else {
                    out.textContent = '실패';
                }
            })
            .catch(function() {
                out.textContent = '오류가 발생했습니다.';
            });
    }

    function rbpwaManifest() {
        var box = document.getElementById('rbpwaManiBox');
        var out = document.getElementById('rbpwaManiOut');
        box.style.display = 'block';
        out.textContent = '불러오는 중...';
        fetch('/rb/rb.mod/pwa/manifest.php', {
                cache: 'no-store'
            })
            .then(function(r) {
                return r.json();
            })
            .then(function(j) {
                out.textContent = JSON.stringify(j, null, 2);
            })
            .catch(function() {
                out.textContent = '오류가 발생했습니다.';
            });
    }
</script>

<script>
    (function() {
        function checkDim(input, needW, needH) {
            const f = input.files && input.files[0];
            if (!f) return;
            const img = new Image();
            img.onload = function() {
                if (this.width !== needW || this.height !== needH) {
                    alert(needW + 'x' + needH + ' PNG만 허용됩니다. (현재 ' + this.width + 'x' + this.height + ')');
                    input.value = '';
                }
            };
            img.src = URL.createObjectURL(f);
        }
        document.querySelector('input[name="icon_192_file"]')?.addEventListener('change', function() {
            checkDim(this, 192, 192)
        });
        document.querySelector('input[name="icon_512_file"]')?.addEventListener('change', function() {
            checkDim(this, 512, 512)
        });
        document.querySelector('input[name="badge_72_file"]')?.addEventListener('change', function() {
            checkDim(this, 72, 72)
        });
    })();
</script>

<script>
    // --- rb_confirm 프라미스 (없으면 폴리필) ---
    if (typeof window.rb_confirm !== 'function') {
        window.rb_confirm = function(msg) {
            return new Promise(function(resolve) {
                resolve(window.confirm(msg));
            });
        };
    }

    // PHP 도움말 HTML을 JS 상수로 안전하게 주입 (따옴표/개행 문제 방지)
    const RB_VAPID_HELP = <?php
    echo json_encode(
      help('공개키는 알림 구독에 사용됩니다. 교체/갱신 시 기존 사용자는 재방문(재구독) 전까지 알림을 받지 못합니다.'),
      JSON_UNESCAPED_UNICODE
    );
  ?>;

    // (기존) rbpwaCheck(), rbpwaManifest() 함수는 그대로 유지

    (function() {
        var outBox = document.getElementById('rbpwaVapidBox');
        var outPre = document.getElementById('rbpwaVapidOut');

        function showOut(text) {
            if (!outBox || !outPre) return;
            outBox.style.display = 'block';
            outPre.textContent = text;
        }

        function bindRenew() {
            var btnRenew = document.getElementById('btnVapidRenew');
            if (!btnRenew) return;
            btnRenew.addEventListener('click', function() {
                rb_confirm("VAPID 키를 갱신하는 경우 기존 사용자의 재방문이 없으면 해당 사용자에게 알림을 보낼 수 없습니다. 갱신하시겠습니까?")
                    .then(function(confirmed) {
                        if (!confirmed) return;
                        showOut('키 갱신 중..');
                        fetch('/rb/rb.mod/pwa/api/install_vapid.php', {
                                cache: 'no-store'
                            })
                            .then(function(r) {
                                return r.json();
                            })
                            .then(function(j) {
                                if (j && j.ok) {
                                    var pub = document.getElementById('vapidPub');
                                    if (pub) pub.value = j.publicKey || '';
                                    showOut('OK\npublicKey: ' + (j.publicKey || ''));
                                } else {
                                    showOut('실패: ' + (j && j.msg ? j.msg : 'unknown'));
                                }
                            })
                            .catch(function() {
                                showOut('오류가 발생했습니다.');
                            });
                    });
            });
        }

        // 생성 버튼
        var btnCreate = document.getElementById('btnVapidCreate');
        if (btnCreate) {
            btnCreate.addEventListener('click', function() {
                rb_confirm("VAPID 키를 새로 생성합니다. 생성된 키는 가급적 변경하지마세요.")
                    .then(function(confirmed) {
                        if (!confirmed) return;
                        showOut('키 생성 중..');
                        fetch('/rb/rb.mod/pwa/api/install_vapid.php', {
                                cache: 'no-store'
                            })
                            .then(function(r) {
                                return r.json();
                            })
                            .then(function(j) {
                                if (j && j.ok) {
                                    // TD 내용을 '키 표시 + 갱신 버튼' 상태로 전환
                                    var td = document.getElementById('rbpwaVapidTd');
                                    if (td) {
                                        td.innerHTML = RB_VAPID_HELP + `
                      <input type="text" id="vapidPub" class="frm_input" readonly size="80" onclick="this.select()">
                      <a href="javascript:void(0);" class="btn_frmline" id="btnVapidRenew">교체/갱신</a>
                      <div id="rbpwaVapidBox" style="margin-top:8px; display:block;">
                        <pre id="rbpwaVapidOut" style="max-height:200px;overflow:auto;background:#f0f5f9;padding:15px;border-radius:6px"></pre>
                      </div>`;
                                        // 값 세팅 & 리스너 바인딩
                                        var pub = document.getElementById('vapidPub');
                                        if (pub) pub.value = (j.publicKey || '');
                                        outBox = document.getElementById('rbpwaVapidBox');
                                        outPre = document.getElementById('rbpwaVapidOut');
                                        showOut('OK\npublicKey: ' + (j.publicKey || ''));
                                        bindRenew();
                                    } else {
                                        showOut('OK\npublicKey: ' + (j.publicKey || ''));
                                    }
                                } else {
                                    showOut('실패: ' + (j && j.msg ? j.msg : 'unknown'));
                                }
                            })
                            .catch(function() {
                                showOut('오류가 발생했습니다.');
                            });
                    });
            });
        }

        // 초기 갱신 버튼 바인딩
        bindRenew();
    })();
</script>

<?php include_once(G5_ADMIN_PATH.'/admin.tail.php'); ?>