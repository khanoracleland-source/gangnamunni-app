<?php
/**
 * 모바일 카테고리 반응형 스케일 설정
 * 기기별(PC/Android/iOS) × 요소별 + 대상선택 일률적용 + 저장/원복 + 미리보기(복사본 iframe)
 * @created 2026-06-16
 * 동작: 입력 → 미리보기(index_preview.php 복사본에 즉시반영, DB저장X) → 저장 버튼 → DB반영(운영 적용)
 */
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);

$admin_page_title = '모바일 스케일 설정';
$admin_current = 'mobile_scale';

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

require_admin_login();

$cfg = [];
$res = mysqli_query($connect, "SELECT setting_key, setting_value FROM gangnamunni_settings WHERE setting_group='mobile_scale'");
while ($row = mysqli_fetch_assoc($res)) { $cfg[$row['setting_key']] = $row['setting_value']; }
function cs($k, $d='') { global $cfg; return $cfg[$k] ?? $d; }

// [폰트 스케일] 전체 글자 --fs-scale 값 (gangnamunni_theme_settings)
$fscfg = [];
$fsres = mysqli_query($connect, "SELECT setting_key, setting_value FROM gangnamunni_theme_settings WHERE setting_key LIKE 'fs_%'");
while ($fsres && ($r = mysqli_fetch_assoc($fsres))) { $fscfg[$r['setting_key']] = $r['setting_value']; }
function fsv($k, $d) { global $fscfg; return isset($fscfg[$k]) ? $fscfg[$k] : $d; }
$FS_DEF = ['fs_mobile_min'=>'0.82','fs_os_scale'=>'1','fs_mobile_slope'=>'0.092','fs_mobile_base'=>'0.62'];
$fs_items = [
  ['fs_mobile_min','모바일 최소 배율(하한)','0.70','1.00','0.01', fsv('fs_mobile_min','0.82'),
   '폰에서 글자가 줄어드는 <b>하한선</b>. 1.00=원본(축소X), 0.82=최대18%축소. <b>작을수록 글자가 더 작아짐.</b> 가장 자주 조정.'],
  ['fs_os_scale','전체 글자 미세보정','0.80','1.20','0.01', fsv('fs_os_scale','1'),
   '모든 글자에 곱하는 추가 배율. 1.0=기본. 전체를 조금 더 크게(>1)/작게(<1).'],
  ['fs_mobile_slope','화면폭 비례 기울기(고급)','0.05','0.15','0.001', fsv('fs_mobile_slope','0.092'),
   'vw 계수. <b>보통 안 건드림.</b> 기본 0.092.'],
  ['fs_mobile_base','화면폭 비례 기준(고급)','0.40','0.80','0.01', fsv('fs_mobile_base','0.62'),
   '기준 오프셋. <b>보통 안 건드림.</b> 기본 0.62.'],
];


$devices = ['pc'=>'💻 PC', 'android'=>'🤖 Android', 'ios'=>'🍎 iOS'];
$fields = [
  'enabled' => ['적용 여부', '1', '이 기기에서 반응형 스케일을 켜고 끕니다. OFF 시 원래 크기(고정)로 표시.'],
  'factor'  => ['Factor (배율)', '0', '전체 축소 배율. <b>0=자동</b>(화면폭÷390, 좁을수록 작게). 0보다 큰 값 입력 시 그 값으로 고정(0.7=70%). 보통 0 권장.'],
  'adjust'  => ['전체 보정', '1.0', 'Factor에 곱하는 미세 보정. <b>모든 요소(글자·셀·칩) 동시</b>. 작을수록 전체 작아짐. 모바일 기본 0.7=서브 3개(피그마 일치).'],
  'font'    => ['글자 보정', '1.0', '<b>글자 크기만</b> 추가 조정. 0.9=10% 작게.'],
  'pad'     => ['셀높이 보정', '1.0', '<b>행 높이(여백)만</b> 조정. 0.8=행 촘촘하게.'],
  'chip'    => ['칩 보정', '1.0', '<b>서브 칩(써마지FLX 등)만</b> 조정. 0.9=칩 작게 해 더 많이 표시.'],
];
$defaults = [
  'pc'      => ['enabled'=>'1','factor'=>'0','adjust'=>'1.0','font'=>'1.0','pad'=>'1.0','chip'=>'1.0'],
  'android' => ['enabled'=>'1','factor'=>'0','adjust'=>'0.7','font'=>'1.0','pad'=>'1.0','chip'=>'1.0'],
  'ios'     => ['enabled'=>'1','factor'=>'0','adjust'=>'0.7','font'=>'1.0','pad'=>'1.0','chip'=>'1.0'],
];

require_once __DIR__ . '/includes/admin_header.php';
?>
<style>
.ms-wrap{display:flex;gap:16px;align-items:flex-start}
.ms-left{flex:1;min-width:0}
.ms-preview{flex:none;width:400px;position:sticky;top:10px}
.ms-preview .phone{border:8px solid #222;border-radius:24px;overflow:hidden;background:#000;box-shadow:0 8px 30px rgba(0,0,0,.3)}
.ms-preview iframe{width:100%;height:620px;border:0;background:#fff;display:block}
.ms-dirty{display:none}
body.ms-has-dirty .ms-dirty{display:inline}
@media (max-width:1100px){ .ms-wrap{flex-direction:column} .ms-preview{width:100%;position:static} }
</style>
<div class="container-fluid py-3" style="max-width:1400px">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-phone"></i> 모바일 카테고리 반응형 스케일</h4>
    <div class="d-flex gap-2">
      <select id="prevDevice" class="form-select form-select-sm" style="width:auto" onchange="refreshPreview()">
        <option value="android">🤖 Android 미리보기</option>
        <option value="ios">🍎 iOS 미리보기</option>
        <option value="pc">💻 PC 미리보기</option>
      </select>
      <button class="btn btn-sm btn-primary" onclick="updatePreview()"><i class="bi bi-eye"></i> 미리보기 적용</button>
    </div>
  </div>

  <ul class="nav nav-tabs mb-3" id="msTabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-setting" type="button"><i class="bi bi-sliders"></i> 설정</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-howto" type="button"><i class="bi bi-book"></i> 동작원리</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-db" type="button"><i class="bi bi-database"></i> 관련 DB 정보</button></li>
  </ul>
  <div class="tab-content">
  <div class="tab-pane fade show active" id="tab-setting" role="tabpanel">
  <div id="saveAlert" class="alert alert-success d-none" style="position:sticky;top:8px;z-index:50"><i class="bi bi-check-circle"></i> <span id="saveMsg">저장됨</span></div>

  <div class="alert alert-warning" style="font-size:13.5px;line-height:1.7">
    ⚠️ <b>입력해도 바로 적용되지 않습니다.</b> 값 변경 → <b>[미리보기 적용]</b> 으로 오른쪽 화면 확인 → 만족하면 <b>[저장]</b> 버튼으로 운영 반영.
    <span class="ms-dirty text-danger fw-bold">　● 저장 안 된 변경사항이 있습니다.</span>
  </div>

  <!-- 동작 방식 설명 -->
  <details class="card mb-3" style="border-left:4px solid #0d6efd">
    <summary class="card-header fw-bold" style="cursor:pointer;list-style:revert"><i class="bi bi-info-circle"></i> 이 설정은 어떻게 동작하나요? (클릭하여 펼치기)</summary>
    <div class="card-body" style="font-size:13.5px;line-height:1.8">
      <p class="mb-2"><b>📍 적용 범위:</b> <b>메인 페이지(index.php)의 시술 카테고리 표</b>에만 적용됩니다. 다른 페이지(병원목록·후기 등)에는 영향 없습니다.</p>
      <p class="mb-2"><b>🎯 왜 필요한가:</b> 카테고리 표의 글자·셀 크기가 고정(px)이라 폰 화면이 좁으면 칸이 2개만 보였습니다. 화면 폭에 맞춰 <b>비례 축소</b>하면 피그마 디자인처럼 서브 3개가 보입니다.</p>
      <p class="mb-2"><b>⚙️ 동작 흐름:</b></p>
      <ol class="mb-2" style="padding-left:20px">
        <li>관리자가 입력한 값(Factor·보정 등)으로 JS가 <code>--cat-scale / --cat-font / --cat-pad / --cat-chip</code> 4개 CSS 변수를 계산</li>
        <li>메인의 글자·셀·칩 크기는 <code>calc(원래크기 × 변수)</code> 로 정해지므로, 변수만 바꾸면 즉시 비례 조정됨</li>
        <li>방문자 접속 시 JS가 <b>기기 판별</b>(User-Agent로 PC/Android/iOS) → 그 기기의 저장된 값을 적용</li>
        <li><b>Factor 0=자동</b>: 화면폭 ÷ 390 으로 계산(좁을수록 작게). <b>0보다 큰 값</b>이면 그 배율 고정</li>
      </ol>
      <p class="mb-2"><b>👀 미리보기 vs 저장 (중요):</b></p>
      <ul class="mb-2" style="padding-left:20px">
        <li><b>미리보기</b> = 오른쪽 <b>복사본(index_preview.php)</b> iframe 에만 임시 반영. <b>DB 저장 안 함, 운영/방문자 영향 0</b>.</li>
        <li><b>저장</b> = DB(gangnamunni_settings)에 기록 → <b>실제 운영 메인에 반영</b>. 방문자가 새로고침하면 적용됨.</li>
      </ul>
      <p class="mb-0"><b>🔧 값 가이드:</b> 값이 <b>작을수록</b> 더 작게(한 줄에 더 많이). 모바일 기본 <b>전체보정 0.7</b> = 서브 3개(피그마 일치). 글자/높이/칩은 요소별 미세조정용(기본 1.0).</p>
    </div>
  </details>

  <div class="ms-wrap">
    <div class="ms-left">
      <!-- ===== [폰트 스케일] 전체 글자 (전 페이지 공통) ===== -->
      <div class="card mb-3" style="border:2px solid #2f6fed">
        <div class="card-header fw-bold" style="background:rgba(47,111,237,.08)"><i class="bi bi-fonts"></i> 폰트 스케일 — 전체 글자(전 페이지)</div>
        <div class="card-body">
          <div class="alert alert-info py-2 px-3 small mb-3">
            이 설정은 <b>모든 페이지의 글자 크기</b>를 폰 화면폭에 맞춰 비례 축소합니다. (아래 카테고리표 설정과 별개) <b>PC는 영향 없음.</b>
            슬라이더 조정 → <b>[적용해보기]</b>로 오른쪽 미리보기 확인 → <b>[저장]</b>으로 사이트 반영.
          </div>
          <?php foreach ($fs_items as $it): list($fk,$lbl,$mn,$mx,$st,$cur,$desc)=$it; ?>
          <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center">
              <label class="form-label small fw-bold mb-0"><?= $lbl ?></label>
              <span class="fw-bold" style="color:#2f6fed;font-variant-numeric:tabular-nums" id="fsval_<?= $fk ?>"><?= htmlspecialchars($cur) ?></span>
            </div>
            <input type="range" class="form-range" min="<?= $mn ?>" max="<?= $mx ?>" step="<?= $st ?>" value="<?= htmlspecialchars($cur) ?>" id="fsrng_<?= $fk ?>" style="accent-color:#2f6fed" oninput="onFsSlide('<?= $fk ?>',this.value)">
            <div class="form-text" style="font-size:11.5px"><?= $desc ?> <button type="button" class="btn btn-link btn-sm p-0" style="font-size:11px" onclick="fsResetOne('<?= $fk ?>')">↺ 기본(<?= $FS_DEF[$fk] ?>)</button></div>
          </div>
          <?php endforeach; ?>
          <div class="d-flex gap-2 mt-2">
            <button class="btn btn-primary btn-sm" onclick="fsPreview()"><i class="bi bi-eye"></i> 적용해보기 (미리보기)</button>
            <button class="btn btn-success btn-sm" onclick="fsSave()"><i class="bi bi-save"></i> 저장 (사이트 적용)</button>
            <button class="btn btn-outline-secondary btn-sm" onclick="fsResetAll()"><i class="bi bi-arrow-counterclockwise"></i> 전체 원복</button>
          </div>
        </div>
      </div>

      <!-- 일률 적용 -->
      <div class="card mb-3" style="border:2px solid var(--bs-primary,#AC1830)">
        <div class="card-header fw-bold" style="background:rgba(172, 24, 48,.08)"><i class="bi bi-lightning-charge"></i> 일률 적용 — 선택 대상에 값 한 번에</div>
        <div class="card-body">
          <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
              <label class="form-label small fw-bold">적용 대상</label>
              <select id="bulkTarget" class="form-select form-select-sm">
                <option value="all">전체 (PC+And+iOS)</option>
                <option value="mobile">모바일만 (And+iOS)</option>
                <option value="pc">💻 PC 만</option>
                <option value="android">🤖 Android 만</option>
                <option value="ios">🍎 iOS 만</option>
              </select>
            </div>
            <?php foreach (['factor','adjust','font','pad','chip'] as $fk): ?>
            <div class="col-6 col-md-2">
              <label class="form-label small"><?= htmlspecialchars($fields[$fk][0]) ?></label>
              <input type="number" step="0.1" class="form-control form-control-sm" id="bulk_<?= $fk ?>" placeholder="<?= $fk==='factor'?'0':'1.0' ?>">
            </div>
            <?php endforeach; ?>
          </div>
          <div class="mt-2"><button class="btn btn-primary btn-sm" onclick="applyBulk()"><i class="bi bi-input-cursor"></i> 입력칸에 채우기</button>
            <span class="form-text">선택 대상 입력칸에 값을 채웁니다. (저장은 각 카드 [저장] 버튼)</span></div>
        </div>
      </div>

      <!-- 기기별 -->
      <div class="row g-3">
        <?php foreach ($devices as $dk=>$dlabel): ?>
        <div class="col-12 col-xl-4">
          <div class="card h-100">
            <div class="card-header fw-bold"><?= $dlabel ?></div>
            <div class="card-body">
              <?php foreach ($fields as $fk=>$fi):
                $key="cat_scale_{$dk}_{$fk}"; $val=cs($key,$defaults[$dk][$fk]); ?>
              <div class="mb-3">
                <?php if ($fk==='enabled'): ?>
                  <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="<?= $key ?>" data-dev="<?= $dk ?>" data-f="<?= $fk ?>" <?= $val==='1'?'checked':'' ?> onchange="markDirty()">
                    <label class="form-check-label fw-bold" for="<?= $key ?>"><?= htmlspecialchars($fi[0]) ?></label>
                  </div>
                  <div class="form-text"><?= $fi[2] ?></div>
                <?php else: ?>
                  <label class="form-label small fw-bold mb-1"><?= htmlspecialchars($fi[0]) ?></label>
                  <input type="number" step="0.1" class="form-control form-control-sm" id="<?= $key ?>" data-dev="<?= $dk ?>" data-f="<?= $fk ?>" value="<?= htmlspecialchars($val) ?>" oninput="markDirty()">
                  <div class="form-text" style="font-size:11.5px"><?= $fi[2] ?></div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
              <div class="d-flex gap-2 mt-2">
                <button class="btn btn-success btn-sm flex-fill" onclick="saveDevice('<?= $dk ?>')"><i class="bi bi-save"></i> 저장 (운영 반영)</button>
                <button class="btn btn-outline-secondary btn-sm flex-fill" onclick="resetDevice('<?= $dk ?>')"><i class="bi bi-arrow-counterclockwise"></i> 원복</button>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 미리보기 패널 (복사본 iframe) -->
    <div class="ms-preview">
      <div class="card">
        <div class="card-header fw-bold py-2"><i class="bi bi-phone"></i> 미리보기 <span id="prevLabel" class="text-muted small">(Android)</span></div>
        <div class="card-body p-2">
          <div class="phone"><iframe id="previewFrame" src="index_preview.php"></iframe></div>
          <div class="form-text mt-1">※ 복사본(index_preview.php) — 저장 전 미리보기. 운영 영향 없음.</div>
        </div>
      </div>
    </div>
  </div>
</div>

  </div><!-- /tab-setting -->

  <div class="tab-pane fade" id="tab-howto" role="tabpanel">
    <div class="card"><div class="card-body" style="font-size:14px;line-height:1.9">
      <h5 class="fw-bold mb-3"><i class="bi bi-book"></i> 모바일 반응형 스케일 — 동작 원리</h5>

      <div class="alert alert-primary py-2 px-3">이 페이지는 <b>두 가지</b>를 관리합니다: ① <b>전체 글자</b>(모든 페이지) ② <b>카테고리 표</b>(메인 전용). 둘은 독립적으로 작동합니다.</div>

      <h6 class="fw-bold mt-4">① 폰트 스케일 — <code>--fs-scale</code> (전 페이지 글자)</h6>
      <table class="table table-sm table-bordered">
        <tr><th style="width:160px">문제</th><td>사이트 글자가 고정 px(2,179곳)이라 좁은 폰에서 너무 크게 보였음</td></tr>
        <tr><th>해결</th><td>모든 <code>font-size:NNpx</code> → <code>calc(NNpx × var(--fs-scale,1) × var(--fs-page,1))</code> 로 변환</td></tr>
        <tr><th>스케일 공식</th><td><code>@media(max-width:767px){ --fs-scale: clamp(하한, 0.62+0.092vw, 1.0) }</code> — 화면폭에 비례, PC는 1 고정</td></tr>
        <tr><th>깜빡임</th><td>순수 CSS clamp → JS 없이 첫 렌더부터 적용 → <b>깜빡임 없음</b></td></tr>
        <tr><th>PC 영향</th><td><code>--fs-scale=1</code> 고정(미디어쿼리 밖) → <b>PC 절대 안 깨짐</b></td></tr>
      </table>

      <h6 class="fw-bold mt-4">② 카테고리 표 — <code>--cat-scale</code> (메인 index.php 전용)</h6>
      <table class="table table-sm table-bordered">
        <tr><th style="width:160px">적용 범위</th><td>메인 페이지 <b>시술 카테고리 엑셀형 표</b>에만</td></tr>
        <tr><th>변수 4종</th><td><code>--cat-scale</code>(전체) · <code>--cat-font</code>(글자) · <code>--cat-pad</code>(셀높이) · <code>--cat-chip</code>(서브칩)</td></tr>
        <tr><th>계산</th><td>JS가 기기 판별(UA) → 입력값으로 변수 계산 → <code>calc(원래크기 × 변수)</code></td></tr>
        <tr><th>효과</th><td>보정 0.7 → 폰에서 서브 3개 표시(피그마 일치)</td></tr>
      </table>

      <h6 class="fw-bold mt-4">③ 저장 / 미리보기 흐름</h6>
      <ol style="padding-left:20px">
        <li><b>슬라이더/입력 조정</b> → 미리보기(오른쪽 iframe 복사본)에 <b>즉시 반영</b> (DB 저장 안 함)</li>
        <li><b>[저장]</b> 클릭 → DB 기록 → 실제 사이트 반영. 방문자가 새로고침하면 적용</li>
        <li><b>원복</b> → 권장 기본값으로 되돌림</li>
      </ol>

      <h6 class="fw-bold mt-4">④ 적용 파일</h6>
      <ul style="font-size:13px">
        <li><code>theme_helper.php</code> — <code>:root</code>에 <code>--fs-scale</code>/토큰 출력 (generateThemeCSS / generateFontScaleCSS)</li>
        <li><code>index_preview.php</code> — 미리보기 복사본 (postMessage 수신: catscale_preview / fontscale_preview)</li>
        <li><code>api_save_font_scale.php</code> — 폰트 스케일 전용 저장 API (theme_settings 업서트)</li>
        <li><code>api_save_setting.php</code> — 카테고리(cat_scale) 저장</li>
        <li>43개 프론트 페이지 — font-size calc() 변환 적용</li>
      </ul>
    </div></div>
  </div><!-- /tab-howto -->

  <div class="tab-pane fade" id="tab-db" role="tabpanel">
    <div class="card"><div class="card-body" style="font-size:13.5px;line-height:1.8">
      <h5 class="fw-bold mb-3"><i class="bi bi-database"></i> 관련 DB 정보</h5>

      <h6 class="fw-bold mt-2">테이블 ① <code>gangnamunni_theme_settings</code> — 폰트 스케일(전체 글자)</h6>
      <p class="text-muted mb-2" style="font-size:12.5px">읽기: <code>getTheme()</code> (theme_helper.php) · 저장: <code>api_save_font_scale.php</code> (INSERT...ON DUPLICATE 업서트)</p>
      <table class="table table-sm table-bordered">
        <thead><tr><th>setting_key</th><th>기본값</th><th>의미</th></tr></thead>
        <tbody>
          <tr><td><code>fs_mobile_min</code></td><td>0.82</td><td>모바일 글자 <b>최소 배율(하한)</b>. 작을수록 더 축소</td></tr>
          <tr><td><code>fs_os_scale</code></td><td>1</td><td>전체 글자 추가 배율(미세조정)</td></tr>
          <tr><td><code>fs_page_scale</code></td><td>1</td><td>페이지별 추가 배율</td></tr>
          <tr><td><code>fs_mobile_slope</code></td><td>0.092</td><td>화면폭 비례 기울기(vw 계수, 고급)</td></tr>
          <tr><td><code>fs_mobile_base</code></td><td>0.62</td><td>화면폭 비례 기준 오프셋(고급)</td></tr>
        </tbody>
      </table>

      <h6 class="fw-bold mt-4">테이블 ② <code>gangnamunni_settings</code> (group=mobile_scale) — 카테고리 표</h6>
      <p class="text-muted mb-2" style="font-size:12.5px">읽기: <code>getSettingValue()</code> · 저장: <code>api_save_setting.php</code> · 키 패턴: <code>cat_scale_{기기}_{항목}</code></p>
      <table class="table table-sm table-bordered">
        <thead><tr><th>기기</th><th>항목 키</th><th>의미</th><th>모바일 기본</th></tr></thead>
        <tbody>
          <tr><td rowspan="6">pc /<br>android /<br>ios</td><td><code>_enabled</code></td><td>적용 여부(1/0)</td><td>1</td></tr>
          <tr><td><code>_factor</code></td><td>배율(0=자동)</td><td>0</td></tr>
          <tr><td><code>_adjust</code></td><td>전체 보정</td><td>0.7</td></tr>
          <tr><td><code>_font</code></td><td>글자 보정</td><td>1.0</td></tr>
          <tr><td><code>_pad</code></td><td>셀높이 보정</td><td>1.0</td></tr>
          <tr><td><code>_chip</code></td><td>서브칩 보정</td><td>1.0</td></tr>
        </tbody>
      </table>
      <p class="text-muted" style="font-size:12px">예: <code>cat_scale_android_adjust = 0.7</code> (총 18키 = 3기기 × 6항목)</p>

      <div class="alert alert-secondary mt-3 py-2 px-3" style="font-size:12.5px">
        <b>⚠️ 인계 주의:</b> 폰트(theme_settings)와 카테고리(settings)는 <b>다른 테이블</b>입니다. <code>getTheme()</code>=theme_settings, <code>getSettingValue()</code>=settings. 키 추가 시 읽기/저장/화이트리스트 <b>3곳</b>을 맞춰야 반영됩니다.
      </div>
    </div></div>
  </div><!-- /tab-db -->

  </div><!-- /tab-content -->
<script>
var FS_DEF=<?php echo json_encode($FS_DEF); ?>;
var fsCur={fs_mobile_min:<?= json_encode(fsv('fs_mobile_min','0.82')) ?>, fs_os_scale:<?= json_encode(fsv('fs_os_scale','1')) ?>, fs_mobile_slope:<?= json_encode(fsv('fs_mobile_slope','0.092')) ?>, fs_mobile_base:<?= json_encode(fsv('fs_mobile_base','0.62')) ?>};
function onFsSlide(k,v){ document.getElementById('fsval_'+k).textContent=v; fsCur[k]=v; markDirty(); fsPreview(); }
function fsResetOne(k){ fsCur[k]=FS_DEF[k]; var r=document.getElementById('fsrng_'+k); if(r)r.value=FS_DEF[k]; document.getElementById('fsval_'+k).textContent=FS_DEF[k]; markDirty(); }
function fsResetAll(){ for(var k in FS_DEF) fsResetOne(k); fsPreview(); }
function fsCalcScale(framePx){
  var mn=parseFloat(fsCur.fs_mobile_min), sl=parseFloat(fsCur.fs_mobile_slope), ba=parseFloat(fsCur.fs_mobile_base), os=parseFloat(fsCur.fs_os_scale);
  var vw=framePx/100; var calc=ba+sl*vw; var sc=Math.max(mn,Math.min(calc,1.0))*os;
  return sc;
}
// [적용해보기] — 미리보기 iframe 에 --fs-scale 적용 (저장 아님)
function fsPreview(){
  var f=document.getElementById('previewFrame'); if(!f)return;
  // 미리보기 프레임 실제 폭 측정 (모바일 폭 기준)
  var px=f.getBoundingClientRect().width||360;
  var sc=fsCalcScale(px);
  try{ f.contentWindow.postMessage({type:'fontscale_preview', scale:sc.toFixed(3)},'*'); }catch(e){}
  showAlert('폰트 미리보기 적용 (배율 '+sc.toFixed(3)+') — 저장 전');
}
// [저장] — theme_settings 업서트 (전용 API)
function fsSave(){
  var keys=Object.keys(fsCur), done=0, fail=0;
  keys.forEach(function(k){
    var fd=new FormData(); fd.append('key',k); fd.append('value',fsCur[k]);
    fetch('api_save_font_scale.php',{method:'POST',body:fd,credentials:'same-origin'})
      .then(function(r){return r.json()}).then(function(j){ done++; if(!j.success)fail++;
        if(done===keys.length){ if(!fail){document.body.classList.remove('ms-has-dirty'); showAlert('폰트 스케일 저장 완료 — 사이트 반영됨');} else showAlert('폰트 저장 일부 실패('+fail+')'); }
      }).catch(function(){done++;fail++; if(done===keys.length)showAlert('폰트 저장 오류');});
  });
}
var FIELDS=['enabled','factor','adjust','font','pad','chip'];
var DEFAULTS=<?php echo json_encode($defaults); ?>;
function showAlert(m){var a=document.getElementById('saveAlert');document.getElementById('saveMsg').textContent=m;a.classList.remove('d-none');setTimeout(function(){a.classList.add('d-none');},2200);}
function markDirty(){ document.body.classList.add('ms-has-dirty'); }
function getVal(dev,f){var el=document.getElementById('cat_scale_'+dev+'_'+f);if(!el)return null;return el.type==='checkbox'?(el.checked?'1':'0'):el.value;}
function num(v,d){var n=parseFloat(v);return (n&&n>0)?n:d;}

// 입력값으로 --cat-* 계산 (index.php JS 와 동일 로직)
function calcVars(dev){
  var en=getVal(dev,'enabled');
  if(en!=='1') return {scale:'1',font:'1',pad:'1',chip:'1'};
  var manual=parseFloat(getVal(dev,'factor'));
  // 미리보기 프레임 폭 기준 (모바일=프레임폭, pc=1280 가정)
  var fw = (dev==='pc') ? 1280 : 360;
  var base=(manual&&manual>0)?manual:Math.min(1, fw/390);
  var scale=base*num(getVal(dev,'adjust'),1);
  scale=Math.max(0.4,Math.min(1.5,scale));
  return {scale:scale.toFixed(3),font:num(getVal(dev,'font'),1).toFixed(3),pad:num(getVal(dev,'pad'),1).toFixed(3),chip:num(getVal(dev,'chip'),1).toFixed(3)};
}
// 미리보기 적용 (iframe 에 값 전달 — DB 저장 아님)
function updatePreview(){
  var dev=document.getElementById('prevDevice').value;
  var v=calcVars(dev); v.type='catscale_preview';
  var f=document.getElementById('previewFrame');
  f.contentWindow.postMessage(v,'*');
  showAlert('미리보기 적용됨 ('+dev.toUpperCase()+') — 저장 전 화면입니다');
}
function refreshPreview(){
  var dev=document.getElementById('prevDevice').value;
  document.getElementById('prevLabel').textContent='('+dev.toUpperCase()+')';
  // 프레임 새로고침 후 적용
  var f=document.getElementById('previewFrame');
  f.onload=function(){ setTimeout(updatePreview,400); f.onload=null; };
  f.src='index_preview.php';
}
// 저장 (운영 반영)
function saveOne(key,value){return fetch('api_save_setting.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'key='+encodeURIComponent(key)+'&value='+encodeURIComponent(value)}).then(function(r){return r.json();});}
function saveDevice(dev){
  var jobs=[]; FIELDS.forEach(function(f){jobs.push(saveOne('cat_scale_'+dev+'_'+f,getVal(dev,f)));});
  Promise.all(jobs).then(function(){ document.body.classList.remove('ms-has-dirty'); showAlert(dev.toUpperCase()+' 저장됨 — 운영에 반영됨 (메인 새로고침)'); });
}
function resetDevice(dev){
  if(!confirm(dev.toUpperCase()+' 를 기본값으로 되돌리고 저장할까요?'))return;
  var d=DEFAULTS[dev],jobs=[];
  FIELDS.forEach(function(f){var el=document.getElementById('cat_scale_'+dev+'_'+f);if(el){if(el.type==='checkbox')el.checked=(d[f]==='1');else el.value=d[f];}jobs.push(saveOne('cat_scale_'+dev+'_'+f,d[f]));});
  Promise.all(jobs).then(function(){ showAlert(dev.toUpperCase()+' 기본값 원복+저장됨'); });
}
// 일률 = 입력칸 채우기 (저장 아님)
function applyBulk(){
  var t=document.getElementById('bulkTarget').value;
  var devs=t==='all'?['pc','android','ios']:t==='mobile'?['android','ios']:[t];
  var filled=0;
  ['factor','adjust','font','pad','chip'].forEach(function(f){
    var v=document.getElementById('bulk_'+f).value; if(v==='')return;
    devs.forEach(function(dev){var inp=document.getElementById('cat_scale_'+dev+'_'+f);if(inp){inp.value=v;filled++;}});
  });
  if(!filled){alert('값을 입력하세요');return;}
  markDirty();
  showAlert('입력칸에 채움 — [미리보기 적용] 후 [저장] 하세요');
}
</script>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
