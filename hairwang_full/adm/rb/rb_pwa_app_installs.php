<?php
// /adm/rb/rb_pwa_app_installs.php
$sub_menu = '000765'; // 환경에 맞는 새 메뉴코드 사용
include_once('./_common.php');
auth_check_menu($auth, $sub_menu, "r");

if (!function_exists('help')) {
    function help($str){ return '<div class="tbl_frm01_help">'.$str.'</div>'; }
}
function rb_admin_token_input_html() {
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

$g5['title'] = 'PWA 앱 설치현황';
include_once(G5_ADMIN_PATH.'/admin.head.php');

// --- 필터 ---
$kw     = trim($_GET['kw'] ?? '');
$plat   = preg_replace('/[^a-z]/','', $_GET['platform'] ?? '');
$active = preg_replace('/[^0-9]/','', $_GET['active'] ?? ''); // 최근 N일
$page   = max(1, (int)($_GET['page'] ?? 1));
$rows   = 30;

$where = ["1"];
if ($kw !== '') {
  $e = sql_real_escape_string($kw);
  $where[] = "(device_uid LIKE '%{$e}%' OR mb_id LIKE '%{$e}%' OR ip LIKE '%{$e}%' OR ua LIKE '%{$e}%')";
}
if ($plat !== '' && in_array($plat, ['android','ios','desktop','web'])) {
  $where[] = "platform='".sql_real_escape_string($plat)."'";
}
if ($active !== '' && (int)$active > 0) {
  $where[] = "last_opened_at >= DATE_SUB(".G5_TIME_YMDHIS.", INTERVAL ".(int)$active." DAY)";
}
$sql_where = ' WHERE '.implode(' AND ', $where);

// 요약
$card = sql_fetch("SELECT
  COUNT(*) AS total,
  SUM(platform='android') AS c_android,
  SUM(platform='ios')     AS c_ios,
  SUM(platform='desktop') AS c_desktop,
  SUM(last_opened_at IS NOT NULL) AS c_opened
FROM rb_pwa_app_installs");

// 페이징
$cnt_row = sql_fetch("SELECT COUNT(*) AS c FROM rb_pwa_app_installs {$sql_where}");
$total_count = (int)($cnt_row['c'] ?? 0);
$total_page  = $total_count ? ceil($total_count / $rows) : 1;
$from        = ($page-1)*$rows;

// 목록
$q = sql_query("SELECT id, device_uid, mb_id, platform, os, browser, last_seen_mode,
                       first_installed_at, last_opened_at, ip, ua, created_at, updated_at
                FROM rb_pwa_app_installs
                {$sql_where}
                ORDER BY COALESCE(last_opened_at, created_at) DESC
                LIMIT {$from}, {$rows}");

// qstr (필터 유지)
$qstr = 'platform='.urlencode($plat).'&amp;active='.urlencode($active).'&amp;kw='.urlencode($kw);

function rb_ko_platform($v){
  $m = [
    'android' => '안드로이드',
    'ios'     => 'iOS',
    'desktop' => '데스크탑',
    'web'     => '웹',
  ];
  $k = strtolower(trim((string)$v));
  return $m[$k] ?? $v;
}

function rb_ko_mode($v){
  $m = [
    'standalone' => '앱',
    'browser'    => '브라우저',
    'tab'        => '브라우저',
    'fullscreen' => '전체 화면',
    'minimal-ui' => '미니멀 UI',
  ];
  $k = strtolower(trim((string)$v));
  return $m[$k] ?? $v;
}

function rb_ko_os($v){
  $t = strtolower((string)$v);
  if ($t==='') return '';
  if (strpos($t,'android') !== false) return '안드로이드';
  if (strpos($t,'ipad')    !== false) return '아이패드OS';
  if (strpos($t,'ios')     !== false) return 'iOS';
  if (strpos($t,'mac')     !== false) return 'macOS';
  if (strpos($t,'windows') !== false) return '윈도우';
  if (strpos($t,'linux')   !== false) return '리눅스';
  if (strpos($t,'cros') !== false || strpos($t,'chrome os') !== false) return '크롬OS';
  return $v;
}

function rb_ko_browser($v){
  $t = strtolower((string)$v);
  if ($t==='') return '';
  if (strpos($t,'samsung') !== false) return '삼성';
  if (strpos($t,'chrome')  !== false) return '크롬';
  if (strpos($t,'safari')  !== false) return '사파리';
  if (strpos($t,'edge')    !== false) return '엣지';
  if (strpos($t,'firefox') !== false) return '파이어폭스';
  if (strpos($t,'opera')   !== false) return '오페라';
  if (strpos($t,'whale')   !== false) return '웨일';
  return $v;
}
?>
<section class="cbox">
    <h2 class="h2_frm">요약</h2>
    <div class="tbl_frm01 tbl_wrap">
        <table>
            <tbody>
                <tr>
                    <th>총 설치 디바이스</th>
                    <td><?php echo number_format($card['total'] ?? 0); ?></td>
                    <th>Android</th>
                    <td><?php echo number_format($card['c_android'] ?? 0); ?></td>
                    <th>iOS</th>
                    <td><?php echo number_format($card['c_ios'] ?? 0); ?></td>
                    <th>Desktop</th>
                    <td><?php echo number_format($card['c_desktop'] ?? 0); ?></td>
                    <th>실행 기록 있음</th>
                    <td><?php echo number_format($card['c_opened'] ?? 0); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <form class="local_sch01 local_sch" method="get" style="margin-top:10px">
        <select name="platform">
          <option value="">플랫폼 전체</option>
          <option value="android" <?php echo $plat==='android'?'selected':''; ?>>안드로이드</option>
          <option value="ios"     <?php echo $plat==='ios'?'selected':''; ?>>iOS</option>
          <option value="desktop" <?php echo $plat==='desktop'?'selected':''; ?>>데스크탑</option>
        </select>

        <input type="text" name="kw" value="<?php echo htmlspecialchars($kw,ENT_QUOTES); ?>" class="frm_input">
        <input type="submit" value="검색" class="btn_submit">
        <span class="btn_ov01"><span class="ov_txt">총</span> <span class="ov_num"><?php echo number_format($total_count); ?>건</span></span>
    </form>

    <form name="fapp" method="post" action="./rb_pwa_app_installs_update.php" onsubmit="return fapp_submit(this);" autocomplete="off">
        <?php echo rb_admin_token_input_html(); ?>
        <input type="hidden" name="qstr" value="<?php echo $qstr; ?>">
        <input type="hidden" name="page" value="<?php echo (int)$page; ?>">

        <div class="tbl_head01 tbl_wrap" style="margin-top:10px;">
            <table>
                <caption><?php echo $g5['title']; ?> 목록</caption>
                <thead>
                    <tr>
                        <th scope="col">
                            <input type="checkbox" name="chkall" value="1" id="chkall" onclick="check_all(this.form)">
                            <label for="chkall" class="sound_only">전체</label>
                        </th>
                        <th scope="col">닉네임</th>
                        <th scope="col">ID</th>
                        <th scope="col">Device UID</th>
                        <th scope="col">플랫폼</th>
                        <th scope="col">OS/브라우저</th>
                        <th scope="col">모드</th>
                        <th scope="col">최초설치</th>
                        <th scope="col">마지막 실행</th>
                        <th scope="col">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    for($i=0; $r=sql_fetch_array($q); $i++){
                        if(!empty($r['mb_id'])) { 
                            $mbx = get_member($r['mb_id']);
                            $mb_nick = get_sideview($r['mb_id'], get_text($mbx['mb_nick']), $mbx['mb_email'], $mbx['mb_homepage']);
                        } else { 
                            $mb_nick = '<span style="color:#999">비회원</span>';
                        }

                        $bg = 'bg'.($i%2);
                    ?>
                    <tr class="<?php echo $bg; ?>">
                        <td class="td_chk"><input type="checkbox" name="chk[]" value="<?php echo (int)$r['id']; ?>"></td>
                         <td class="td_name sv_use" nowrap>
                            <div><?php echo $mb_nick ?></div>
                        </td>
                        <td class="" nowrap>
                            <?php echo htmlspecialchars($r['mb_id'],ENT_QUOTES); ?>
                        </td>
                        <td class=""><?php echo htmlspecialchars($r['device_uid'],ENT_QUOTES); ?></td>
                        <td class="" nowrap><?php echo htmlspecialchars(rb_ko_platform($r['platform']), ENT_QUOTES); ?></td>
                        <td class="" nowrap>
                          <?php
                            $ko_os = rb_ko_os($r['os']);
                            $ko_br = rb_ko_browser($r['browser']);
                            echo htmlspecialchars(trim(($ko_os ?: '') . ' / ' . ($ko_br ?: '')), ENT_QUOTES);
                          ?>
                        </td>
                        <td class="" nowrap><?php echo htmlspecialchars(rb_ko_mode($r['last_seen_mode']), ENT_QUOTES); ?></td>
                        <td class="td_datetime" nowrap><?php echo htmlspecialchars($r['first_installed_at'],ENT_QUOTES); ?></td>
                        <td class="td_datetime" nowrap><?php echo htmlspecialchars($r['last_opened_at'],ENT_QUOTES); ?></td>
                        <td class=""><?php echo htmlspecialchars($r['ip'],ENT_QUOTES); ?></td>
                    </tr>
                    <?php }
                    if ($i===0){ ?>
                    <tr>
                        <td colspan="11" class="empty_table"><span>자료가 없습니다.</span></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div class="btn_fixed_top">
            <input type="submit" name="act_button" value="선택삭제" class="btn btn_02" onclick="document.pressed=this.value">
            <input type="submit" name="act_button" value="전체삭제" class="btn btn_02" onclick="document.pressed=this.value">
        </div>
    </form>

    <?php
  echo get_paging(
    G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'],
    $page, $total_page, "{$_SERVER['SCRIPT_NAME']}?{$qstr}&amp;page="
  );
  ?>
</section>

<script>
    // 클릭된 submit 버튼 추적 (버튼 수정 없이 호환)
    (function() {
        var lastPressedEl = null;
        document.addEventListener('click', function(e) {
            var btn = e.target && e.target.closest('form[name="fapp"] input[type="submit"][name="act_button"]');
            if (btn) {
                lastPressedEl = btn;
                document.pressed = btn.value; // 기존 패턴 호환
            }
        }, true);
        window.__getLastPressedEl = function() {
            return lastPressedEl;
        };
    })();

    // rb_confirm 폴리필
    if (typeof window.rb_confirm !== 'function') {
        window.rb_confirm = function(msg) {
            return new Promise(function(resolve) {
                resolve(window.confirm(msg));
            });
        };
    }

    function fapp_submit(f) {
        // 재제출 루프 방지
        if (f.dataset.skipConfirm === '1') {
            f.dataset.skipConfirm = '';
            return true;
        }

        var pressed = document.pressed || '';

        function doSubmitWithPressed() {
            f.dataset.skipConfirm = '1';
            var btn = (typeof window.__getLastPressedEl === 'function') ? window.__getLastPressedEl() : null;

            if (typeof f.requestSubmit === 'function' && btn) {
                f.requestSubmit(btn); // 버튼 name/value 포함 제출
                return;
            }
            // 폴백: hidden act_button 보장
            var hid = f.querySelector('input[type="hidden"][name="act_button"]');
            if (!hid) {
                hid = document.createElement('input');
                hid.type = 'hidden';
                hid.name = 'act_button';
                f.appendChild(hid);
            }
            hid.value = pressed || (btn && btn.value) || '';
            f.submit();
        }

        if (pressed === '선택삭제') {
            var chks = f.querySelectorAll('input[name="chk[]"]:checked');
            if (chks.length === 0) {
                alert('삭제할 항목을 선택하세요.');
                return false;
            }
            var msg = '선택한 설치 정보를 삭제하시겠습니까?';
            if (typeof rb_confirm === 'function') {
                rb_confirm(msg).then(function(confirmed) {
                    if (confirmed) doSubmitWithPressed();
                });
                return false;
            } else {
                return confirm(msg);
            }
        }

        if (pressed === '전체삭제') {
            var msg2 = '앱 설치 현황을 전체 삭제합니다.\n삭제된 데이터는 복구되지 않습니다.';
            if (typeof rb_confirm === 'function') {
                rb_confirm(msg2).then(function(confirmed) {
                    if (confirmed) doSubmitWithPressed();
                });
                return false;
            } else {
                return confirm(msg2);
            }
        }

        return true;
    }
</script>

<?php include_once(G5_ADMIN_PATH.'/admin.tail.php'); ?>