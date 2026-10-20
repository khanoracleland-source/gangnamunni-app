<?php
/**
 * CPV 과금 시스템 — 통합 설정 관리
 * 55개 설정 (ON/OFF + 가격 + 비율 + 한도) 한 페이지에서 관리
 */
session_start();
$admin_page_title = 'CPV 과금 설정';
$admin_current = 'cpv_settings';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('DB connection failed');
mysqli_set_charset($connect, 'utf8mb4');

loadThemeSettings($connect);
require_admin_login();
require_permission('cpv_settings');

require_once __DIR__ . '/includes/cpv_helper.php';

// 카테고리 정의
$categories = getCpvSettingCategories();

// 현재 설정값 로드
$settings = [];
$r = mysqli_query($connect, "SELECT * FROM gangnamunni_cpv_settings ORDER BY category, sort_order");
if ($r) {
    while ($row = mysqli_fetch_assoc($r)) {
        $cat = $row['category'];
        if (!isset($settings[$cat])) $settings[$cat] = [];
        $settings[$cat][] = $row;
    }
}

// 없으면 API 호출로 테이블 생성 유도
if (empty($settings)) {
    // 테이블 자동 생성 시도
    include_once __DIR__ . '/api_cpv_settings.php';
    // 다시 로드 시도는 JS에서 처리
}

require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
.cpv-container { max-width: 1200px; margin: 0 auto; }
.cpv-phase {
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 12px;
    margin-bottom: 20px;
    overflow: hidden;
    transition: all 0.3s;
}
.cpv-phase:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
.cpv-phase-header {
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    user-select: none;
    transition: background 0.2s;
}
.cpv-phase-header:hover { opacity: 0.9; }
.cpv-phase-header .phase-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: #fff; flex-shrink: 0;
}
.cpv-phase-header .phase-title { font-size: 16px; font-weight: 700; color: #fff; flex: 1; }
.cpv-phase-header .phase-count {
    background: rgba(255,255,255,0.2); padding: 2px 10px; border-radius: 12px;
    font-size: 12px; font-weight: 700; color: #fff;
}
.cpv-phase-header .phase-toggle {
    color: #fff; font-size: 14px; transition: transform 0.3s;
}
.cpv-phase-header .phase-toggle.collapsed { transform: rotate(-90deg); }

.cpv-phase-body { padding: 0; }
.cpv-phase-body.collapsed { display: none; }

.setting-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    border-bottom: 1px solid var(--bs-border-color);
    transition: background 0.15s;
}
.setting-row:last-child { border-bottom: none; }
.setting-row:hover { background: var(--bs-tertiary-bg); }
.setting-row.sub-setting {
    padding-left: 52px;
    background: var(--bs-tertiary-bg);
}
.setting-row.sub-setting::before {
    content: '';
    display: inline-block;
    width: 2px; height: 24px;
    background: var(--bs-border-color);
    border-radius: 1px;
    margin-right: 8px;
    flex-shrink: 0;
}

.setting-label {
    flex: 1;
    min-width: 200px;
}
.setting-label .label-text { font-size: 14px; font-weight: 600; }
.setting-label .label-desc { font-size: 11px; color: var(--bs-secondary-color); margin-top: 2px; }

.setting-input { flex-shrink: 0; display: flex; align-items: center; gap: 8px; }

/* 토글 스위치 */
.cpv-toggle {
    position: relative; width: 52px; height: 28px; cursor: pointer;
}
.cpv-toggle input { display: none; }
.cpv-toggle .slider {
    position: absolute; top: 0; left: 0; right: 0; bottom: 0;
    background: #ccc; border-radius: 14px; transition: 0.3s;
}
.cpv-toggle .slider::before {
    content: ''; position: absolute;
    width: 22px; height: 22px; left: 3px; bottom: 3px;
    background: var(--bs-body-bg); border-radius: 50%; transition: 0.3s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.cpv-toggle input:checked + .slider { background: #2563eb; }
.cpv-toggle input:checked + .slider::before { transform: translateX(24px); }

/* 숫자/통화 입력 */
.cpv-number-input {
    width: 120px; padding: 6px 10px; border: 1px solid var(--bs-border-color);
    border-radius: 8px; font-size: 13px; font-weight: 600;
    text-align: right; background: var(--bs-body-bg); color: var(--bs-body-color);
    transition: border-color 0.2s;
}
.cpv-number-input:focus { border-color: #2563eb; outline: none; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }

.cpv-select {
    padding: 6px 10px; border: 1px solid var(--bs-border-color);
    border-radius: 8px; font-size: 13px; font-weight: 600;
    background: var(--bs-body-bg); color: var(--bs-body-color);
}

.input-unit {
    font-size: 12px; color: var(--bs-secondary-color); font-weight: 600;
    min-width: 30px;
}

/* 액션 바 */
.cpv-action-bar {
    position: sticky; bottom: 0; z-index: 100;
    background: var(--bs-body-bg);
    border-top: 2px solid var(--bs-border-color);
    padding: 16px 20px;
    display: flex; align-items: center; gap: 12px;
    box-shadow: 0 -4px 16px rgba(0,0,0,0.1);
    border-radius: 0 0 12px 12px;
}
.cpv-action-bar .btn { font-weight: 700; }
.change-indicator {
    display: none; font-size: 13px; font-weight: 600; color: #ea580c;
}
.change-indicator.show { display: inline-flex; align-items: center; gap: 4px; }

/* 단가 계산 미리보기 */
.rate-preview {
    background: linear-gradient(135deg, #fefce8, #fef9c3);
    border: 2px solid #fcd34d;
    border-radius: 10px;
    padding: 16px 20px;
    margin: 0 20px 16px;
}
[data-bs-theme="dark"] .rate-preview {
    background: linear-gradient(135deg, #422006, #451a03);
    border-color: #92400e;
}
.rate-preview h5 { font-size: 13px; font-weight: 700; color: #92400e; margin-bottom: 8px; }
[data-bs-theme="dark"] .rate-preview h5 { color: #fbbf24; }
.rate-formula { font-size: 12px; color: #78716c; font-family: monospace; }
.rate-result { font-size: 20px; font-weight: 800; color: #dc2626; margin-top: 8px; }

/* 마스터 스위치 강조 */
.master-switch-row {
    background: linear-gradient(135deg, #fef2f2, #fee2e2) !important;
    border: 2px solid #fca5a5;
    border-radius: 8px;
    margin: 12px 20px;
    padding: 16px 20px !important;
}
[data-bs-theme="dark"] .master-switch-row {
    background: linear-gradient(135deg, #450a0a, #7f1d1d) !important;
    border-color: #991b1b;
}
</style>

<div class="cpv-container">
    <!-- 상단 요약 카드 -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div id="totalSettings" class="fs-2 fw-bold text-primary">0</div>
                    <div class="text-secondary small">전체 설정</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div id="activeToggles" class="fs-2 fw-bold text-success">0</div>
                    <div class="text-secondary small">활성 토글</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div id="masterStatus" class="fs-2 fw-bold text-danger">OFF</div>
                    <div class="text-secondary small">마스터 스위치</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <div id="changedCount" class="fs-2 fw-bold text-warning">0</div>
                    <div class="text-secondary small">변경 대기</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 설정 패널 (JS로 렌더링) -->
    <div id="settingsPanel"></div>

    <!-- 단가 계산 미리보기 -->
    <div class="rate-preview" id="ratePreview">
        <h5><i class="bi bi-calculator"></i> 단가 계산 미리보기</h5>
        <div class="rate-formula" id="rateFormula">기본단가 x 키워드 x 시술 x 이벤트 x 평점</div>
        <div class="rate-result" id="rateResult">= 200원/뷰</div>
    </div>

    <!-- 하단 액션 바 -->
    <div class="cpv-action-bar">
        <button class="btn btn-primary" onclick="saveAllSettings()">
            <i class="bi bi-check-lg"></i> 전체 저장
        </button>
        <button class="btn btn-outline-success" onclick="bulkAction('all_on')">
            <i class="bi bi-toggle-on"></i> 전체 ON
        </button>
        <button class="btn btn-outline-secondary" onclick="bulkAction('all_off')">
            <i class="bi bi-toggle-off"></i> 전체 OFF
        </button>
        <button class="btn btn-outline-warning" onclick="bulkAction('reset_defaults')">
            <i class="bi bi-arrow-counterclockwise"></i> 기본값 복원
        </button>
        <button class="btn btn-outline-info" onclick="exportSettings()">
            <i class="bi bi-download"></i> JSON 내보내기
        </button>
        <span class="change-indicator" id="changeIndicator">
            <i class="bi bi-exclamation-circle"></i> <span id="changeCount">0</span>개 변경 대기
        </span>
        <span class="ms-auto text-secondary small" id="lastSaved"></span>
    </div>
</div>

<script>
var API_URL = 'api_cpv_settings.php';
var allSettings = {};
var changedKeys = new Set();
var phaseLabels = {
    phase6: {label: '\uc2dc\uc2a4\ud15c \uc804\uc5ed \uc124\uc815', icon: 'bi-gear-wide-connected', color: '#6b7280'},
    phase1: {label: '\ud3ec\uc778\ud2b8 \uc9c0\uac11 \uc2dc\uc2a4\ud15c', icon: 'bi-wallet2', color: '#2563eb'},
    phase2: {label: 'CPV \uacfc\uae08 \uc5d4\uc9c4', icon: 'bi-cpu', color: '#dc2626'},
    phase3: {label: '\ub2e8\uac00 \uc815\ucc45', icon: 'bi-currency-dollar', color: '#ea580c'},
    phase4: {label: '\uc608\uc0b0 \uad00\ub9ac', icon: 'bi-piggy-bank', color: '#16a34a'},
    phase5: {label: '\ucda9\uc804 \uc0c1\ud488 & \ub300\uc2dc\ubcf4\ub4dc', icon: 'bi-graph-up-arrow', color: '#AC1830'},
    phase7: {label: 'AI \uacfc\uae08 \uc124\uc815', icon: 'bi-robot', color: '#0891b2'}
};
var phaseOrder = ['phase6','phase1','phase2','phase3','phase4','phase5','phase7'];

// 초기 로드
document.addEventListener('DOMContentLoaded', function() {
    loadSettings();
});

function loadSettings() {
    fetch(API_URL)
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                allSettings = {};
                // 플랫 맵 생성
                for (var cat in res.data) {
                    for (var i = 0; i < res.data[cat].length; i++) {
                        var s = res.data[cat][i];
                        allSettings[s.setting_key] = s;
                    }
                }
                renderPanel(res.data);
                updateSummary();
                updateRatePreview();
            }
        })
        .catch(function(e) { console.error('Load failed:', e); });
}

function renderPanel(data) {
    var panel = document.getElementById('settingsPanel');
    var html = '';

    for (var pi = 0; pi < phaseOrder.length; pi++) {
        var cat = phaseOrder[pi];
        var items = data[cat] || [];
        var meta = phaseLabels[cat];
        if (!meta) continue;

        html += '<div class="cpv-phase" data-phase="' + cat + '">';
        html += '<div class="cpv-phase-header" style="background:' + meta.color + ';" onclick="togglePhase(this)">';
        html += '<div class="phase-icon" style="background:rgba(255,255,255,0.15);"><i class="bi ' + meta.icon + '"></i></div>';
        html += '<span class="phase-title">' + meta.label + '</span>';
        html += '<span class="phase-count">' + items.length + '\uac1c</span>';
        html += '<i class="bi bi-chevron-down phase-toggle"></i>';
        html += '</div>';
        html += '<div class="cpv-phase-body">';

        for (var i = 0; i < items.length; i++) {
            var s = items[i];
            var isSub = s.sub_category && s.sub_category !== '';
            var isMaster = s.setting_key === 'cpv_master_switch';
            var rowClass = 'setting-row';
            if (isSub) rowClass += ' sub-setting';
            if (isMaster) rowClass += ' master-switch-row';

            html += '<div class="' + rowClass + '" data-key="' + s.setting_key + '">';
            html += '<div class="setting-label">';
            html += '<div class="label-text">' + (isMaster ? '\u26a0\ufe0f ' : '') + s.label + '</div>';
            if (s.description) html += '<div class="label-desc">' + s.description + '</div>';
            html += '</div>';
            html += '<div class="setting-input">';
            html += renderInput(s);
            html += '</div>';
            html += '</div>';
        }

        // Phase 3에 단가 계산 공식 표시
        if (cat === 'phase3') {
            html += '<div class="rate-preview" style="margin:12px 20px 16px;">';
            html += '<h5><i class="bi bi-calculator"></i> \ub2e8\uac00 \uacc4\uc0b0 \uacf5\uc2dd</h5>';
            html += '<div class="rate-formula">\uae30\ubcf8\ub2e8\uac00 \xd7 \ud0a4\uc6cc\ub4dc\uac00\uc911\uce58 \xd7 \uc2dc\uc220\uac00\uc911\uce58 \xd7 \uc774\ubca4\ud2b8\uac00\uc911\uce58 \xd7 \ud3c9\uc810\uac00\uc911\uce58</div>';
            html += '<div class="rate-formula" id="rateCalc" style="margin-top:8px;font-size:14px;font-weight:700;color:#dc2626;"></div>';
            html += '</div>';
        }

        html += '</div></div>';
    }

    panel.innerHTML = html;
}

function renderInput(s) {
    var key = s.setting_key;
    var val = s.setting_value;
    var type = s.setting_type;

    if (type === 'toggle') {
        var checked = val === '1' ? ' checked' : '';
        return '<label class="cpv-toggle"><input type="checkbox" data-key="' + key + '"' + checked + ' onchange="onSettingChange(\'' + key + '\', this.checked ? \'1\' : \'0\')"><span class="slider"></span></label>';
    }

    if (type === 'number') {
        var min = s.min_value !== null ? ' min="' + s.min_value + '"' : '';
        var max = s.max_value !== null ? ' max="' + s.max_value + '"' : '';
        var unit = '';
        if (s.description) {
            var dm = s.description.match(/[(\uff08]([^)\uff09]+)[)\uff09]/);
            if (dm) unit = dm[1];
            else if (s.description.indexOf('\ubd84') >= 0) unit = '\ubd84';
            else if (s.description.indexOf('\uc77c') >= 0) unit = '\uc77c';
            else if (s.description.indexOf('\ucd08') >= 0) unit = '\ucd08';
            else if (s.description.indexOf('\uc2dc\uac04') >= 0) unit = '\uc2dc\uac04';
            else if (s.description.indexOf('\ud68c') >= 0) unit = '\ud68c';
            else if (s.description.indexOf('\ubc30\uc218') >= 0 || s.description.indexOf('\ubc30') >= 0) unit = '\xd7';
        }
        return '<input type="number" class="cpv-number-input" data-key="' + key + '" value="' + val + '"' + min + max + ' step="any" onchange="onSettingChange(\'' + key + '\', this.value)">' + (unit ? '<span class="input-unit">' + unit + '</span>' : '');
    }

    if (type === 'currency') {
        var min = s.min_value !== null ? ' min="' + s.min_value + '"' : '';
        var max = s.max_value !== null ? ' max="' + s.max_value + '"' : '';
        return '<input type="number" class="cpv-number-input" data-key="' + key + '" value="' + val + '"' + min + max + ' step="1" onchange="onSettingChange(\'' + key + '\', this.value)" style="width:140px;">' + '<span class="input-unit">\uc6d0</span>';
    }

    if (type === 'percent') {
        var min = s.min_value !== null ? ' min="' + s.min_value + '"' : '';
        var max = s.max_value !== null ? ' max="' + s.max_value + '"' : '';
        return '<input type="number" class="cpv-number-input" data-key="' + key + '" value="' + val + '"' + min + max + ' step="0.1" onchange="onSettingChange(\'' + key + '\', this.value)">' + '<span class="input-unit">%</span>';
    }

    if (type === 'select') {
        var options = [];
        if (key === 'currency_unit') options = [['KRW','\uc6d0 (KRW)'],['USD','$ (USD)'],['JPY','\u00a5 (JPY)']];
        else if (key === 'decimal_rounding') options = [['round','\ubc18\uc62c\ub9bc'],['ceil','\uc62c\ub9bc'],['floor','\ubc84\ub9bc']];
        else if (key === 'settlement_cycle') options = [['monthly','\uc6d4 1\ud68c'],['biweekly','2\uc8fc 1\ud68c'],['weekly','\uc8fc 1\ud68c']];
        else {
            // description에서 파싱
            if (s.description) {
                var parts = s.description.split('/');
                for (var p = 0; p < parts.length; p++) options.push([parts[p].trim(), parts[p].trim()]);
            }
        }
        var html = '<select class="cpv-select" data-key="' + key + '" onchange="onSettingChange(\'' + key + '\', this.value)">';
        for (var o = 0; o < options.length; o++) {
            var sel = val === options[o][0] ? ' selected' : '';
            html += '<option value="' + options[o][0] + '"' + sel + '>' + options[o][1] + '</option>';
        }
        html += '</select>';
        return html;
    }

    // text (기본)
    return '<input type="text" class="cpv-number-input" data-key="' + key + '" value="' + val + '" onchange="onSettingChange(\'' + key + '\', this.value)" style="width:100px;">';
}

function onSettingChange(key, value) {
    if (allSettings[key]) {
        allSettings[key].setting_value = value;
    }
    changedKeys.add(key);
    updateSummary();
    updateRatePreview();
}

function updateSummary() {
    var total = Object.keys(allSettings).length;
    var active = 0;
    var masterOn = false;
    for (var k in allSettings) {
        if (allSettings[k].setting_type === 'toggle' && allSettings[k].setting_value === '1') active++;
        if (k === 'cpv_master_switch' && allSettings[k].setting_value === '1') masterOn = true;
    }
    document.getElementById('totalSettings').textContent = total;
    document.getElementById('activeToggles').textContent = active;
    document.getElementById('masterStatus').textContent = masterOn ? 'ON' : 'OFF';
    document.getElementById('masterStatus').className = 'fs-2 fw-bold ' + (masterOn ? 'text-success' : 'text-danger');
    document.getElementById('changedCount').textContent = changedKeys.size;

    var indicator = document.getElementById('changeIndicator');
    if (changedKeys.size > 0) {
        indicator.classList.add('show');
        document.getElementById('changeCount').textContent = changedKeys.size;
    } else {
        indicator.classList.remove('show');
    }
}

function updateRatePreview() {
    var base = parseFloat(getVal('base_cpv_rate', 200));
    var kw = parseFloat(getVal('weight_keyword_eye', 1.5));
    var proc = parseFloat(getVal('weight_proc_premium', 2.0));
    var evt = parseFloat(getVal('weight_event_active', 1.5));
    var rat = parseFloat(getVal('weight_rating_high', 1.3));
    var result = base * kw * proc * evt * rat;
    var max = parseFloat(getVal('max_cpv_rate', 5000));
    var min = parseFloat(getVal('min_cpv_rate', 50));
    result = Math.max(min, Math.min(max, result));

    var calcEl = document.getElementById('rateCalc');
    if (calcEl) {
        calcEl.textContent = base + '\uc6d0 \xd7 ' + kw + ' \xd7 ' + proc + ' \xd7 ' + evt + ' \xd7 ' + rat + ' = ' + Math.round(result).toLocaleString() + '\uc6d0/\ubdf0 (\ucd5c\ub300 \uc608\uc2dc)';
    }

    var previewResult = document.getElementById('rateResult');
    if (previewResult) {
        previewResult.textContent = '= ' + Math.round(result).toLocaleString() + '\uc6d0/\ubdf0 (\ub208\uc131\ud615 + \ud504\ub9ac\ubbf8\uc5c4 + \uc774\ubca4\ud2b8 + \uace0\ud3c9\uc810)';
    }
}

function getVal(key, def) {
    if (allSettings[key]) return allSettings[key].setting_value;
    return def;
}

function togglePhase(header) {
    var body = header.nextElementSibling;
    var arrow = header.querySelector('.phase-toggle');
    body.classList.toggle('collapsed');
    arrow.classList.toggle('collapsed');
}

function saveAllSettings() {
    if (changedKeys.size === 0) {
        showToast('\ubcc0\uacbd\ub41c \uc124\uc815\uc774 \uc5c6\uc2b5\ub2c8\ub2e4.', 'warning');
        return;
    }
    var settings = {};
    changedKeys.forEach(function(key) {
        if (allSettings[key]) settings[key] = allSettings[key].setting_value;
    });

    fetch(API_URL, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'bulk_save', settings: settings})
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            showToast(res.updated + '\uac1c \uc124\uc815 \uc800\uc7a5 \uc644\ub8cc!', 'success');
            changedKeys.clear();
            updateSummary();
            document.getElementById('lastSaved').textContent = '\ub9c8\uc9c0\ub9c9 \uc800\uc7a5: ' + new Date().toLocaleString('ko-KR');
        } else {
            showToast('\uc800\uc7a5 \uc2e4\ud328: ' + (res.message || ''), 'danger');
        }
    })
    .catch(function(e) {
        showToast('\ub124\ud2b8\uc6cc\ud06c \uc624\ub958', 'danger');
    });
}

function bulkAction(action) {
    var confirmMsg = {
        'all_on': '\ubaa8\ub4e0 \ud1a0\uae00\uc744 ON\uc73c\ub85c \ubcc0\uacbd\ud558\uc2dc\uaca0\uc2b5\ub2c8\uae4c?',
        'all_off': '\ubaa8\ub4e0 \ud1a0\uae00\uc744 OFF\ub85c \ubcc0\uacbd\ud558\uc2dc\uaca0\uc2b5\ub2c8\uae4c?',
        'reset_defaults': '\ubaa8\ub4e0 \uc124\uc815\uc744 \uae30\ubcf8\uac12\uc73c\ub85c \ubcf5\uc6d0\ud558\uc2dc\uaca0\uc2b5\ub2c8\uae4c? \uc774 \uc791\uc5c5\uc740 \ub418\ub3cc\ub9b4 \uc218 \uc5c6\uc2b5\ub2c8\ub2e4.'
    };
    if (!confirm(confirmMsg[action] || '\uc9c4\ud589\ud558\uc2dc\uaca0\uc2b5\ub2c8\uae4c?')) return;

    fetch(API_URL, {
        method: 'PUT',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: action})
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.success) {
            showToast(res.message, 'success');
            changedKeys.clear();
            loadSettings();
        }
    });
}

function exportSettings() {
    fetch(API_URL + '?action=export')
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                var blob = new Blob([JSON.stringify(res, null, 2)], {type: 'application/json'});
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'cpv_settings_' + new Date().toISOString().slice(0,10) + '.json';
                a.click();
                showToast('JSON \ub0b4\ubcf4\ub0b4\uae30 \uc644\ub8cc', 'success');
            }
        });
}

function showToast(msg, type) {
    var toast = document.createElement('div');
    toast.className = 'alert alert-' + type + ' position-fixed shadow';
    toast.style.cssText = 'top:20px;right:20px;z-index:9999;min-width:300px;animation:fadeIn 0.3s;';
    toast.innerHTML = '<i class="bi ' + (type === 'success' ? 'bi-check-circle' : type === 'danger' ? 'bi-x-circle' : 'bi-exclamation-circle') + '"></i> ' + msg;
    document.body.appendChild(toast);
    setTimeout(function() { toast.remove(); }, 3000);
}
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
