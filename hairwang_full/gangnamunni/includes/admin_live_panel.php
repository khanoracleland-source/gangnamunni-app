<?php
// Admin Live Settings Panel v2 - PC only
$_lp_is_admin = false;
if (!empty($_SESSION['ad_admin_logged_in'])) {
    $_lp_is_admin = true;
} elseif (isset($_COOKIE['PHPSESSID'])) {
    $_lp_save = session_save_path() ?: '/tmp';
    $_lp_sid = preg_replace('/[^a-zA-Z0-9,-]/', '', $_COOKIE['PHPSESSID']);
    $_lp_data = @file_get_contents($_lp_save . '/sess_' . $_lp_sid);
    if ($_lp_data && strpos($_lp_data, 'ad_admin_logged_in|b:1') !== false) $_lp_is_admin = true;
}
if (!$_lp_is_admin) return;
if (!function_exists('getTheme')) return;

// Load settings from gangnamunni_settings
if (!function_exists('_lpGetSetting')) {
    function _lpGetSetting($connect, $key, $default = '') {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            $r = @mysqli_query($connect, 'SELECT setting_key, setting_value FROM gangnamunni_settings');
            if ($r) while ($row = mysqli_fetch_assoc($r)) $cache[$row['setting_key']] = $row['setting_value'];
        }
        return $cache[$key] ?? $default;
    }
}
if (!function_exists('_lpGetAI')) {
    function _lpGetAI($connect, $key, $default = '') {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            $r = @mysqli_query($connect, 'SELECT setting_key, setting_value FROM gangnamunni_ai_settings');
            if ($r) while ($row = mysqli_fetch_assoc($r)) $cache[$row['setting_key']] = $row['setting_value'];
        }
        return $cache[$key] ?? $default;
    }
}

$_colors = [
    ['key'=>'primary_color','label'=>'메인 컬러','default'=>'#AE1932'],
    ['key'=>'secondary_color','label'=>'보조 컬러','default'=>'#8A1428'],
    ['key'=>'background_color','label'=>'배경 컬러','default'=>'#FFFFFF'],
    ['key'=>'text_color','label'=>'글자 컬러','default'=>'#000000'],
    ['key'=>'border_color','label'=>'테두리 컬러','default'=>'#E5E7EB'],
    ['key'=>'link_color','label'=>'링크 컬러','default'=>'#AE1932'],
    ['key'=>'login_button_bg','label'=>'로그인 버튼','default'=>'#AE1932'],
];
$_ranges = [
    ['key'=>'border_radius','label'=>'모서리 둥글기','default'=>'12','min'=>'0','max'=>'30','unit'=>'px'],
    ['key'=>'spacing_small','label'=>'간격(소)','default'=>'8','min'=>'2','max'=>'20','unit'=>'px'],
    ['key'=>'spacing_medium','label'=>'간격(중)','default'=>'16','min'=>'4','max'=>'40','unit'=>'px'],
    ['key'=>'spacing_large','label'=>'간격(대)','default'=>'24','min'=>'8','max'=>'60','unit'=>'px'],
    ['key'=>'font_size_base','label'=>'기본 크기','default'=>'14','min'=>'10','max'=>'20','unit'=>'px'],
];
$_fonts_kr = ['Noto Sans KR','Pretendard','Spoqa Han Sans Neo','IBM Plex Sans KR','Gothic A1'];
$_fonts_en = ['Roboto','Inter','Poppins','Open Sans','Lato','Montserrat'];
$_texts = [
    ['key'=>'site_name','label'=>'사이트 이름','default'=>'서울미녀'],
    ['key'=>'button_booking_text','label'=>'예약 버튼','default'=>'예약하기'],
    ['key'=>'button_consultation_text','label'=>'상담 버튼','default'=>'상담신청'],
];
$_ad_display = [
    ['key'=>'ad_show_index','label'=>'메인 배너'],
    ['key'=>'ad_show_hospitals','label'=>'병원 목록'],
    ['key'=>'ad_show_hospitals_infeed','label'=>'병원 인피드'],
    ['key'=>'ad_show_events','label'=>'이벤트'],
    ['key'=>'ad_show_reviews','label'=>'후기'],
    ['key'=>'ad_show_tips','label'=>'꿀팁'],
    ['key'=>'ad_show_columns','label'=>'칼럼'],
    ['key'=>'ad_show_consultation','label'=>'상담'],
    ['key'=>'promo_show_index','label'=>'프로모션'],
];
$_hv_mode = [
    ['key'=>'hospital_view_seoul_enabled','label'=>'서울미녀형'],
    ['key'=>'hospital_view_card_enabled','label'=>'카드형'],
    ['key'=>'hospital_view_list_enabled','label'=>'리스트형'],
];
$_hv_style = [
    ['group'=>'카드형','items'=>[
        ['key'=>'hv_card_btn_color','label'=>'버튼 색상','type'=>'color'],
        ['key'=>'hv_card_tag_color','label'=>'태그 색상','type'=>'color'],
        ['key'=>'hv_card_name_size','label'=>'병원명 크기','type'=>'range'],
        ['key'=>'hv_card_tag_size','label'=>'태그 크기','type'=>'range'],
        ['key'=>'hv_card_cta_size','label'=>'CTA 크기','type'=>'range'],
    ]],
    ['group'=>'리스트형','items'=>[
        ['key'=>'hv_list_btn_color','label'=>'버튼 색상','type'=>'color'],
        ['key'=>'hv_list_name_size','label'=>'병원명 크기','type'=>'range'],
        ['key'=>'hv_list_tag_size','label'=>'태그 크기','type'=>'range'],
        ['key'=>'hv_list_cta_size','label'=>'CTA 크기','type'=>'range'],
    ]],
    ['group'=>'서울미녀형','items'=>[
        ['key'=>'hv_seoul_btn_color','label'=>'버튼 색상','type'=>'color'],
        ['key'=>'hv_seoul_tag_color','label'=>'태그 색상','type'=>'color'],
        ['key'=>'hv_seoul_addr_size','label'=>'주소 크기','type'=>'range'],
        ['key'=>'hv_seoul_name_size','label'=>'병원명 크기','type'=>'range'],
        ['key'=>'hv_seoul_tag_size','label'=>'태그 크기','type'=>'range'],
        ['key'=>'hv_seoul_cta_size','label'=>'CTA 크기','type'=>'range'],
    ]],
];
$_nav_display = [
    ['key'=>'nav_top_show_mobile','label'=>'상단-모바일'],
    ['key'=>'nav_top_show_desktop','label'=>'상단-PC'],
    ['key'=>'nav_bottom_show_mobile','label'=>'하단-모바일'],
    ['key'=>'nav_bottom_show_desktop','label'=>'하단-PC'],
];
?>
<style>
.live-panel{position:fixed;top:0;left:-350px;width:340px;height:100vh;background:#16213e;color:#e8e8e8;z-index:9999;transition:left .3s ease;overflow-y:auto;box-shadow:4px 0 20px rgba(0,0,0,.4);font-size:13px;display:flex;flex-direction:column;font-family:-apple-system,BlinkMacSystemFont,sans-serif}
.live-panel.open{left:0}
body.lp-active{margin-left:340px;transition:margin-left .3s ease}
body.lp-active .global-nav{left:calc(50% + 170px)}
body.lp-active .bottom-nav{left:calc(50% + 170px)}
.live-panel-toggle{position:fixed;left:16px;bottom:80px;width:52px;height:52px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:50%;color:#fff;font-size:24px;z-index:10000;cursor:pointer;box-shadow:0 4px 16px rgba(102,126,234,.5);display:flex;align-items:center;justify-content:center;transition:all .3s;border:none}
.live-panel-toggle:hover{transform:scale(1.1) rotate(30deg)}
body.lp-active .live-panel-toggle{left:356px}
.lp-header{padding:12px 14px;background:#0f3460;display:flex;justify-content:space-between;align-items:center;font-weight:700;font-size:14px;border-bottom:1px solid #1a1a4e}
.lp-close{cursor:pointer;font-size:22px;opacity:.7;transition:opacity .2s}.lp-close:hover{opacity:1}
.lp-body{flex:1;overflow-y:auto;padding:4px 0}
.lp-group{border-bottom:1px solid rgba(255,255,255,.06)}
.lp-group-title{padding:9px 14px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;font-weight:600;font-size:12px;color:#a8b4ce;transition:color .2s}
.lp-group-title:hover{color:#fff}
.lp-chevron{transition:transform .2s;font-size:11px}
.lp-group-body{display:none;padding:2px 14px 8px}.lp-group-body.show{display:block}
.lp-row{display:flex;justify-content:space-between;align-items:center;padding:4px 0;gap:6px}
.lp-row label{font-size:11px;color:#9ca3af;white-space:nowrap;min-width:65px}
.lp-color-wrap{display:flex;align-items:center;gap:4px}
.lp-color-wrap input[type=color]{width:28px;height:22px;border:none;background:none;cursor:pointer;padding:0}
.lp-color-val{font-size:10px;color:#6b7280;font-family:monospace}
.lp-range-wrap{display:flex;align-items:center;gap:4px;flex:1;max-width:150px}
.lp-range-wrap input[type=range]{flex:1;height:3px;accent-color:#667eea}
.lp-range-val{font-size:10px;color:#6b7280;min-width:30px;text-align:right;font-family:monospace}
.lp-select{background:#1a1a4e;color:#e8e8e8;border:1px solid #333;border-radius:4px;padding:2px 4px;font-size:11px;width:100%;max-width:150px}
.lp-input{background:#1a1a4e;color:#e8e8e8;border:1px solid #333;border-radius:4px;padding:3px 6px;font-size:11px;width:100%;max-width:150px}
.lp-switch{display:flex;align-items:center;gap:4px}
.lp-switch input[type=checkbox]{width:32px;height:16px;accent-color:#667eea}
.lp-sub-label{font-size:10px;color:#667eea;font-weight:600;padding:4px 0 2px;border-top:1px solid rgba(255,255,255,.04)}
.lp-footer{padding:8px 14px;background:#0f3460;border-top:1px solid #1a1a4e;display:flex;gap:6px}
.lp-btn{flex:1;padding:7px 0;border:none;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;transition:all .2s}
.lp-btn-save{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}.lp-btn-save:hover{opacity:.9}
.lp-btn-reset{background:#374151;color:#d1d5db}.lp-btn-reset:hover{background:#4b5563}
.lp-console{padding:6px 10px;background:#0a0a1a;border-top:1px solid #1a1a4e;font-family:monospace;font-size:9px;color:#6b7280;max-height:70px;overflow-y:auto}
.lp-console div{padding:1px 0}.lp-console .ok{color:#22c55e}.lp-console .err{color:#ef4444}.lp-console .info{color:#3b82f6}
#lpToast{position:fixed;bottom:20px;right:20px;padding:10px 20px;border-radius:8px;color:#fff;font-size:13px;font-weight:600;opacity:0;transform:translateY(20px);transition:all .3s;z-index:10001;pointer-events:none}
#lpToast.show{opacity:1;transform:translateY(0)}
@media(max-width:1024px){.live-panel-toggle,.live-panel{display:none!important}body.lp-active{margin-left:0!important}}

.lp-dev-btn{background:#1e3a5f;color:#a8b4ce;border:1px solid #2a4a7f;border-radius:4px;padding:4px 8px;font-size:10px;cursor:pointer;transition:all .2s;white-space:nowrap}
.lp-dev-btn:hover{background:#2a4a7f;color:#fff}
.lp-dev-btn.s{font-size:9px;padding:3px 6px}
.lp-dev-btn.del{background:#3b1a1a;border-color:#5c2a2a;color:#ef9999}
.lp-dev-btn.del:hover{background:#5c2a2a;color:#fff}
.lp-link{display:inline-block;background:#1a1a4e;color:#a8b4ce;text-decoration:none;padding:3px 8px;border-radius:4px;font-size:10px;transition:all .2s;border:1px solid #2a2a6e}
.lp-link:hover{background:#2a2a6e;color:#fff}
.lp-log-entry{padding:3px 0;border-bottom:1px solid rgba(255,255,255,.04)}
.lp-log-time{color:#667eea}.lp-log-user{color:#22c55e}.lp-log-key{color:#eab308}.lp-log-val{color:#ef4444}
</style>
<div class="live-panel" id="livePanel">
  <div class="lp-header">
    <span><i class="bi bi-palette2"></i> 라이브 세팅 v2</span>
    <span class="lp-close" onclick="LivePanel.toggle()">&times;</span>
  </div>
  <div class="lp-body">
    <div class="lp-group"><div class="lp-group-title" onclick="LivePanel.toggleGroup(this)"><span>🎨 테마 색상</span><i class="bi bi-chevron-down lp-chevron"></i></div>
    <div class="lp-group-body show">
    <?php foreach ($_colors as $c): ?>
    <div class="lp-row"><label><?= $c['label'] ?></label><div class="lp-color-wrap"><input type="color" data-key="<?= $c['key'] ?>" data-table="theme_settings" value="<?= htmlspecialchars(getTheme($c['key'],$c['default'])) ?>"><span class="lp-color-val"><?= getTheme($c['key'],$c['default']) ?></span></div></div>
    <?php endforeach; ?>
    </div></div>
    <div class="lp-group"><div class="lp-group-title" onclick="LivePanel.toggleGroup(this)"><span>📐 레이아웃</span><i class="bi bi-chevron-down lp-chevron"></i></div>
    <div class="lp-group-body">
    <?php foreach ($_ranges as $r): ?>
    <div class="lp-row"><label><?= $r['label'] ?></label><div class="lp-range-wrap"><input type="range" data-key="<?= $r['key'] ?>" data-table="theme_settings" data-unit="<?= $r['unit'] ?>" min="<?= $r['min'] ?>" max="<?= $r['max'] ?>" value="<?= intval(getTheme($r['key'],$r['default'])) ?>"><span class="lp-range-val"><?= getTheme($r['key'],$r['default']) ?></span></div></div>
    <?php endforeach; ?>
    </div></div>
    <div class="lp-group"><div class="lp-group-title" onclick="LivePanel.toggleGroup(this)"><span>🔤 폰트</span><i class="bi bi-chevron-down lp-chevron"></i></div>
    <div class="lp-group-body">
    <div class="lp-row"><label>한글</label><select data-key="font_korean" data-table="theme_settings" class="lp-select"><?php foreach($_fonts_kr as $f): ?><option <?= getTheme('font_korean')===$f?'selected':'' ?>><?= $f ?></option><?php endforeach; ?></select></div>
    <div class="lp-row"><label>영문</label><select data-key="font_english" data-table="theme_settings" class="lp-select"><?php foreach($_fonts_en as $f): ?><option <?= getTheme('font_english')===$f?'selected':'' ?>><?= $f ?></option><?php endforeach; ?></select></div>
    </div></div>
    <div class="lp-group"><div class="lp-group-title" onclick="LivePanel.toggleGroup(this)"><span>📝 텍스트</span><i class="bi bi-chevron-down lp-chevron"></i></div>
    <div class="lp-group-body">
    <?php foreach ($_texts as $t): ?>
    <div class="lp-row"><label><?= $t['label'] ?></label><input class="lp-input" type="text" data-key="<?= $t['key'] ?>" data-table="theme_settings" value="<?= htmlspecialchars(getTheme($t['key'],$t['default'])) ?>"></div>
    <?php endforeach; ?>
    </div></div>
    <div class="lp-group"><div class="lp-group-title" onclick="LivePanel.toggleGroup(this)"><span>📢 광고/콘텐츠 ON/OFF</span><i class="bi bi-chevron-down lp-chevron"></i></div>
    <div class="lp-group-body">
    <?php foreach ($_ad_display as $ad): ?>
    <div class="lp-row"><label><?= $ad['label'] ?></label><div class="lp-switch"><input type="checkbox" data-key="<?= $ad['key'] ?>" data-table="settings" <?= _lpGetSetting($connect, $ad['key'], '1')==='1'?'checked':'' ?>></div></div>
    <?php endforeach; ?>
    </div></div>
    <div class="lp-group"><div class="lp-group-title" onclick="LivePanel.toggleGroup(this)"><span>⚙️ 광고 동작</span><i class="bi bi-chevron-down lp-chevron"></i></div>
    <div class="lp-group-body">
    <div class="lp-row"><label>자동 스크롤</label><div class="lp-switch"><input type="checkbox" data-key="ad_auto_scroll" data-table="settings" <?= _lpGetSetting($connect,'ad_auto_scroll','1')==='1'?'checked':'' ?>></div></div>
    <div class="lp-row"><label>스크롤 속도</label><div class="lp-range-wrap"><input type="range" data-key="ad_auto_scroll_speed" data-table="settings" min="1" max="10" value="<?= _lpGetSetting($connect,'ad_auto_scroll_speed','5') ?>"><span class="lp-range-val"><?= _lpGetSetting($connect,'ad_auto_scroll_speed','5') ?></span></div></div>
    <div class="lp-row"><label>인피드 간격</label><div class="lp-range-wrap"><input type="range" data-key="ad_infeed_interval" data-table="settings" min="2" max="10" value="<?= _lpGetSetting($connect,'ad_infeed_interval','4') ?>"><span class="lp-range-val"><?= _lpGetSetting($connect,'ad_infeed_interval','4') ?></span></div></div>
    </div></div>
    <div class="lp-group"><div class="lp-group-title" onclick="LivePanel.toggleGroup(this)"><span>🏥 병원뷰 모드</span><i class="bi bi-chevron-down lp-chevron"></i></div>
    <div class="lp-group-body">
    <div class="lp-row"><label>기본 뷰</label><select data-key="hospital_view_default" data-table="settings" class="lp-select">
      <option value="seoul" <?= _lpGetSetting($connect,'hospital_view_default','seoul')==='seoul'?'selected':'' ?>>서울미녀형</option>
      <option value="card" <?= _lpGetSetting($connect,'hospital_view_default','seoul')==='card'?'selected':'' ?>>카드형</option>
      <option value="list" <?= _lpGetSetting($connect,'hospital_view_default','seoul')==='list'?'selected':'' ?>>리스트형</option>
    </select></div>
    <?php foreach ($_hv_mode as $hv): ?>
    <div class="lp-row"><label><?= $hv['label'] ?></label><div class="lp-switch"><input type="checkbox" data-key="<?= $hv['key'] ?>" data-table="settings" <?= _lpGetSetting($connect,$hv['key'],'1')==='1'?'checked':'' ?>></div></div>
    <?php endforeach; ?>
    </div></div>
    <div class="lp-group"><div class="lp-group-title" onclick="LivePanel.toggleGroup(this)"><span>🎨 병원뷰 스타일</span><i class="bi bi-chevron-down lp-chevron"></i></div>
    <div class="lp-group-body">
    <?php foreach ($_hv_style as $group): ?>
    <div class="lp-sub-label"><?= $group['group'] ?></div>
    <?php foreach ($group['items'] as $si): ?>
    <?php if ($si['type'] === 'color'): ?>
    <div class="lp-row"><label><?= $si['label'] ?></label><div class="lp-color-wrap"><input type="color" data-key="<?= $si['key'] ?>" data-table="settings" value="<?= _lpGetSetting($connect,$si['key'],'#AE1932') ?>"><span class="lp-color-val"><?= _lpGetSetting($connect,$si['key'],'#AE1932') ?></span></div></div>
    <?php else: ?>
    <div class="lp-row"><label><?= $si['label'] ?></label><div class="lp-range-wrap"><input type="range" data-key="<?= $si['key'] ?>" data-table="settings" data-unit="px" min="10" max="24" value="<?= _lpGetSetting($connect,$si['key'],'14') ?>"><span class="lp-range-val"><?= _lpGetSetting($connect,$si['key'],'14') ?>px</span></div></div>
    <?php endif; ?>
    <?php endforeach; ?>
    <?php endforeach; ?>
    </div></div>
    <div class="lp-group"><div class="lp-group-title" onclick="LivePanel.toggleGroup(this)"><span>🧭 네비게이션</span><i class="bi bi-chevron-down lp-chevron"></i></div>
    <div class="lp-group-body">
    <?php foreach ($_nav_display as $nd): ?>
    <div class="lp-row"><label><?= $nd['label'] ?></label><div class="lp-switch"><input type="checkbox" data-key="<?= $nd['key'] ?>" data-table="settings" <?= _lpGetSetting($connect,$nd['key'],'0')==='1'?'checked':'' ?>></div></div>
    <?php endforeach; ?>
    </div></div>
    <div class="lp-group"><div class="lp-group-title" onclick="LivePanel.toggleGroup(this)"><span>🤖 AI 설정</span><i class="bi bi-chevron-down lp-chevron"></i></div>
    <div class="lp-group-body">
    <div class="lp-row"><label>AI 프로바이더</label><select data-key="ai_provider" data-table="ai_settings" class="lp-select">
      <option value="openai" <?= _lpGetAI($connect,'ai_provider','openai')==='openai'?'selected':'' ?>>openai</option>
      <option value="deepseek" <?= _lpGetAI($connect,'ai_provider','openai')==='deepseek'?'selected':'' ?>>deepseek</option>
      <option value="zhipu" <?= _lpGetAI($connect,'ai_provider','openai')==='zhipu'?'selected':'' ?>>zhipu</option>
      <option value="openrouter" <?= _lpGetAI($connect,'ai_provider','openai')==='openrouter'?'selected':'' ?>>openrouter</option>
      <option value="ollama" <?= _lpGetAI($connect,'ai_provider','openai')==='ollama'?'selected':'' ?>>ollama</option>
    </select></div>
    <div class="lp-row"><label>AI 모델</label><input class="lp-input" type="text" data-key="ai_model" data-table="ai_settings" value="<?= htmlspecialchars(_lpGetAI($connect,'ai_model','gpt-4o-mini')) ?>"></div>
    <div class="lp-row"><label>후기 AI 검증</label><div class="lp-switch"><input type="checkbox" data-key="review_ai_enabled" data-table="ai_settings" <?= _lpGetAI($connect,'review_ai_enabled','1')==='1'?'checked':'' ?>></div></div>
    <div class="lp-row"><label>광고 AI 검증</label><div class="lp-switch"><input type="checkbox" data-key="ad_ai_enabled" data-table="ai_settings" <?= _lpGetAI($connect,'ad_ai_enabled','1')==='1'?'checked':'' ?>></div></div>
    <div class="lp-row"><label>최대 토큰</label><div class="lp-range-wrap"><input type="range" data-key="ai_max_tokens" data-table="ai_settings" min="500" max="4000" step="100" value="<?= _lpGetAI($connect,'ai_max_tokens','2000') ?>"><span class="lp-range-val"><?= _lpGetAI($connect,'ai_max_tokens','2000') ?></span></div></div>
    </div></div>
  </div>

    <!-- Quick Test Tools -->
    <div class="lp-group"><div class="lp-group-title" onclick="LivePanel.toggleGroup(this)"><span>⚡ 빠른 테스트</span><i class="bi bi-chevron-down lp-chevron"></i></div>
    <div class="lp-group-body">
    <div style="display:flex;flex-wrap:wrap;gap:4px">
      <button onclick="LivePanel.devAction('db_stats')" class="lp-dev-btn">📊 DB 통계</button>
      <button onclick="LivePanel.devAction('health_check')" class="lp-dev-btn">🏥 상태 체크</button>
      <button onclick="LivePanel.devAction('page_check')" class="lp-dev-btn">🌐 페이지 체크</button>
      <button onclick="LivePanel.devAction('clear_cache')" class="lp-dev-btn">🔄 캐시 초기화</button>
    </div>
    <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:4px">
      <button onclick="LivePanel.devAction('sample_create','hospitals')" class="lp-dev-btn s">🏥+5</button>
      <button onclick="LivePanel.devAction('sample_create','reviews')" class="lp-dev-btn s">📝+3</button>
      <button onclick="LivePanel.devAction('sample_create','events')" class="lp-dev-btn s">🎪+3</button>
      <button onclick="LivePanel.devAction('sample_delete')" class="lp-dev-btn s del">🗑 샘플 전체 삭제</button>
    </div>
    <div id="lpDevResult" style="margin-top:6px;font-size:10px;color:#9ca3af;max-height:80px;overflow-y:auto;font-family:monospace"></div>
    </div></div>

    <!-- Shortcuts -->
    <div class="lp-group"><div class="lp-group-title" onclick="LivePanel.toggleGroup(this)"><span>🔗 바로가기</span><i class="bi bi-chevron-down lp-chevron"></i></div>
    <div class="lp-group-body">
    <div style="display:flex;flex-wrap:wrap;gap:3px">
      <a href="dashboard.php" class="lp-link">📊 대시보드</a>
      <a href="admin_hospitals.php" class="lp-link">🏥 병원</a>
      <a href="admin_ads.php" class="lp-link">📢 광고</a>
      <a href="admin_events.php" class="lp-link">🎪 이벤트</a>
      <a href="admin_reviews.php" class="lp-link">📝 후기</a>
      <a href="admin_theme.php" class="lp-link">🎨 테마</a>
      <a href="admin_ai_settings.php" class="lp-link">🤖 AI</a>
      <a href="admin_bookings.php" class="lp-link">📅 예약</a>
      <a href="admin_backup.php" class="lp-link">💾 백업</a>
      <a href="admin_auto_manual.php" class="lp-link">📖 매뉴얼</a>
      <a href="admin_fix_log.php" class="lp-link">📋 수정이력</a>
      <a href="admin_ad_settings.php" class="lp-link">⚙ 광고설정</a>
    </div>
    </div></div>

    <!-- Change Log -->
    <div class="lp-group"><div class="lp-group-title" onclick="LivePanel.toggleGroup(this);LivePanel.loadLog()"><span>📋 변경 이력</span><i class="bi bi-chevron-down lp-chevron"></i></div>
    <div class="lp-group-body">
    <div id="lpChangeLog" style="font-size:10px;color:#9ca3af;max-height:120px;overflow-y:auto;font-family:monospace">로딩...</div>
    </div></div>
  <div class="lp-footer">
    <button class="lp-btn lp-btn-save" onclick="LivePanel.save()">💾 저장</button>
    <button class="lp-btn lp-btn-reset" onclick="LivePanel.reset()">↩ 초기화</button>
  </div>
  <div class="lp-console" id="lpConsole"></div>
</div>
<button class="live-panel-toggle" onclick="LivePanel.toggle()" title="Live Settings v2"><i class="bi bi-gear-fill"></i></button>
<div id="lpToast"></div>
<script>
var LivePanel=(function(){
'use strict';
var panel=document.getElementById("livePanel"),consoleEl=document.getElementById("lpConsole");
var isOpen=false,orig={},changed={};
var cssMap={primary_color:"--primary-color",secondary_color:"--secondary-color",text_color:"--text-color",background_color:"--background-color",border_color:"--border-color",link_color:"--link-color",border_radius:"--border-radius",spacing_small:"--spacing-sm",spacing_medium:"--spacing-md",spacing_large:"--spacing-lg",font_size_base:"--font-size-base",font_korean:"--font-korean",font_english:"--font-english"};

function log(msg,type){console.log("[LIVE-PANEL] "+msg);if(!consoleEl)return;var d=document.createElement("div");d.className=type||"ok";var ts=new Date().toLocaleTimeString("ko-KR",{hour12:false});d.textContent=ts+" "+msg;consoleEl.appendChild(d);consoleEl.scrollTop=consoleEl.scrollHeight;}

function init(){
  if(!panel)return;
  panel.querySelectorAll("[data-key]").forEach(function(el){
    var k=el.getAttribute("data-key"),t=el.getAttribute("data-table")||"theme_settings";
    if(el.type==="checkbox")orig[k]={v:el.checked?"1":"0",t:t};
    else if(el.type==="range"){var u=el.getAttribute("data-unit")||"";orig[k]={v:el.value+u,t:t};}
    else orig[k]={v:el.value,t:t};
  });
  panel.querySelectorAll("input[type=color]").forEach(function(el){el.addEventListener("input",function(){var k=this.getAttribute("data-key");applyPreview(k,this.value);this.nextElementSibling.textContent=this.value;});});
  panel.querySelectorAll("input[type=range]").forEach(function(el){el.addEventListener("input",function(){var k=this.getAttribute("data-key"),u=this.getAttribute("data-unit")||"",v=this.value+u;applyPreview(k,v);var s=this.parentElement.querySelector(".lp-range-val");if(s)s.textContent=v||this.value;});});
  panel.querySelectorAll("select").forEach(function(el){el.addEventListener("change",function(){applyPreview(this.getAttribute("data-key"),this.value);});});
  panel.querySelectorAll(".lp-input").forEach(function(el){el.addEventListener("input",function(){applyPreview(this.getAttribute("data-key"),this.value);});});
  panel.querySelectorAll(".lp-switch input").forEach(function(el){el.addEventListener("change",function(){var k=this.getAttribute("data-key"),t=this.getAttribute("data-table");changed[k]={v:this.checked?"1":"0",t:t};log(k+"="+(this.checked?"ON":"OFF"));});});
  log("패널 초기화 ("+Object.keys(orig).length+"개)","info");
  console.group("[LIVE-PANEL] System Info v2");console.log("Settings:",Object.keys(orig).length);console.table(Object.fromEntries(Object.entries(orig).map(function(e){{return[e[0],e[1].v+" ("+e[1].t+")"];}})));console.groupEnd();
}

function applyPreview(k,v){
  var t=(orig[k]&&orig[k].t)||"theme_settings";
  changed[k]={v:v,t:t};
  if(cssMap[k]){document.documentElement.style.setProperty(cssMap[k],v);log(k+": "+v);}
  else log(k+"="+v);
  if(k==="site_name")document.querySelectorAll(".global-nav-logo span,.site-name").forEach(function(el){el.textContent=v;});
}

function toggle(){isOpen=!isOpen;panel.classList.toggle("open",isOpen);document.body.classList.toggle("lp-active",isOpen);log("Panel "+(isOpen?"OPEN":"CLOSED"),"info");}
function toggleGroup(el){el.nextElementSibling.classList.toggle("show");}

function save(){
  var keys=Object.keys(changed);
  if(!keys.length){toast("변경사항 없음","#eab308");return;}
  log("저장 중...("+keys.length+"개)","info");
  var s=keys.map(function(kk){return{key:kk,value:changed[kk].v};});
  fetch("api/theme_live_update.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({settings:s})})
  .then(function(r){return r.json();})
  .then(function(d){
    if(d.success){toast("저장 완료! ("+d.updated+"개, log:"+d.logged+")","#16a34a");log("저장 성공: "+d.updated+" (admin:"+d.admin+")","info");keys.forEach(function(kk){orig[kk]=changed[kk];});changed={};}
    else{toast("실패: "+(d.message||""),"#ef4444");log("실패: "+d.message,"error");}
  }).catch(function(e){toast("네트워크 오류","#ef4444");log("Error: "+e.message,"error");});
}

function reset(){
  Object.keys(orig).forEach(function(kk){
    var el=panel.querySelector("[data-key=\""+kk+"\"]");if(!el)return;
    var ov=orig[kk].v;
    if(el.type==="checkbox")el.checked=ov==="1";
    else el.value=String(ov).replace(/px|ms/,"");
    if(cssMap[kk])document.documentElement.style.setProperty(cssMap[kk],ov);
    var vEl=el.parentElement&&el.parentElement.querySelector(".lp-color-val,.lp-range-val");
    if(vEl)vEl.textContent=ov;
  });
  changed={};
  log("초기화 완료","info");toast("원래 값으로 복원","#3b82f6");
}

function toast(msg,color){var t=document.getElementById("lpToast");t.textContent=msg;t.style.background=color||"#16a34a";t.classList.add("show");setTimeout(function(){t.classList.remove("show");},2500);}

if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",init);else init();

function devAction(action,type){
  var url="api/dev_tools.php?action="+action;
  var opts={method:"GET"};
  if(action==="sample_create"||action==="sample_delete"){
    opts={method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:"action="+action+(type?"&type="+type:"")};
    url="api/dev_tools.php";
  }
  log("DevTool: "+action+(type?" ("+type+")":""),"info");
  var el=document.getElementById("lpDevResult");
  if(el)el.textContent="...";
  fetch(url,opts).then(function(r){return r.json();}).then(function(d){
    if(el){
      var txt=JSON.stringify(d,null,1).replace(/[{}]/g,"").trim();
      el.innerHTML="<pre style=\"margin:0;white-space:pre-wrap\">"+txt+"</pre>";
    }
    log("DevTool OK: "+action,"ok");
    console.log("[LIVE-PANEL] DevTool",action,d);
  }).catch(function(e){log("DevTool Error: "+e.message,"err");});
}

function loadLog(){
  fetch("api/dev_tools.php?action=change_log&limit=10").then(function(r){return r.json();}).then(function(d){
    var el=document.getElementById("lpChangeLog");
    if(!el||!d.logs)return;
    if(!d.logs.length){el.textContent="\uc774\ub825 \uc5c6\uc74c";return;}
    el.innerHTML=d.logs.map(function(l){
      var dt=l.created_at.substring(5,16);
      return '<div class="lp-log-entry"><span class="lp-log-time">'+dt+'</span> <span class="lp-log-user">'+l.admin_user+'</span> <span class="lp-log-key">'+l.setting_key+'</span>: <span class="lp-log-val">'+(l.old_value||"-")+'</span> \u2192 '+l.new_value+'</div>';
    }).join("");
    log("\ubcc0\uacbd \uc774\ub825 "+d.logs.length+"\uac74 \ub85c\ub4dc","info");
  }).catch(function(e){});
}
return{toggle:toggle,toggleGroup:toggleGroup,save:save,reset:reset,devAction:devAction,loadLog:loadLog};
})();
</script>