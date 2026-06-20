/**
 * 매뉴얼 수정요청 위젯 (공유 JS)
 * 모든 매뉴얼 페이지에서 <script src="common/mf_widget.js"></script> 로 사용
 * @version 2026-04-09 v2 — 다크모드 + 드래그드롭 + 빨간 버튼
 */
(function () {
  'use strict';

  // ── CSS 주입 ────────────────────────────────────────────────────────
  // 라이트/다크 모두 명시적으로 지정 (data-theme 기반)
  var CSS = [
    /* 모달 공통 */
    '#mfw-modal .modal-dialog{max-width:min(1100px,96vw);}',
    '#mfw-modal .modal-content{border:none;border-radius:18px;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.4);}',
    '#mfw-modal .mfw-header{background:#111!important;border:none;padding:1.1rem 1.4rem;}',

    /* 라이트 모드 기본 */
    '#mfw-modal .modal-body,#mfw-modal .modal-footer{background:#fff;color:#1a1d26;}',
    '#mfw-modal .modal-footer{border-color:#e5e7eb;}',
    '#mfw-modal .form-control,#mfw-modal .form-select{background:#fff;color:#1a1d26;border-color:#d1d5db;}',
    '#mfw-modal .btn-outline-secondary{color:#6b7280;border-color:#d1d5db;}',
    '#mfw-image-area{border:2px dashed #d1d5db;color:#6b7280;background:#fafafa;}',
    '#mfw-loc-box{background:#f1f3f5;color:#1a1d26;}',
    '.mfw-thumb-name{color:#6b7280;}',
    '.mfw-thumb img{border-color:#e5e7eb;}',

    /* 다크 모드 — data-theme="dark" */
    '[data-theme="dark"] #mfw-modal .modal-body,[data-theme="dark"] #mfw-modal .modal-footer{background:#1a1d2e;color:#e5e7eb;}',
    '[data-theme="dark"] #mfw-modal .modal-footer{border-color:#2d3148;}',
    '[data-theme="dark"] #mfw-modal .form-control,[data-theme="dark"] #mfw-modal .form-select{',
    '  background:#252836;color:#e5e7eb;border-color:#2d3148;}',
    '[data-theme="dark"] #mfw-modal .form-control::placeholder,[data-theme="dark"] #mfw-modal textarea::placeholder{color:#6b7280;}',
    '[data-theme="dark"] #mfw-modal .btn-outline-secondary{color:#9ca3af;border-color:#2d3148;}',
    '[data-theme="dark"] #mfw-image-area{border-color:#2d3148;color:#9ca3af;background:#151824;}',
    '[data-theme="dark"] #mfw-loc-box{background:#252836;color:#e5e7eb;}',
    '[data-theme="dark"] .mfw-thumb-name{color:#9ca3af;}',
    '[data-theme="dark"] .mfw-thumb img{border-color:#2d3148;}',
    '[data-theme="dark"] .mfw-form-label{color:#e5e7eb;}',
    '[data-theme="dark"] #mfw-modal .text-muted{color:#9ca3af!important;}',

    /* 이미지 업로드 영역 공통 */
    '#mfw-image-area{border-radius:12px;padding:2rem 1rem;text-align:center;cursor:pointer;',
    '  font-size:0.85rem;min-height:200px;display:flex;flex-direction:column;',
    '  align-items:center;justify-content:center;gap:8px;transition:border-color .2s,background .2s;}',
    '#mfw-image-area > *{pointer-events:none;}',
    '#mfw-image-area i{font-size:2.5rem;}',
    '#mfw-image-area:hover,#mfw-image-area.drag-over{border-color:#dc3545!important;background:rgba(220,53,69,.07)!important;color:#dc3545!important;}',

    /* 썸네일 */
    '.mfw-thumb{position:relative;display:inline-flex;flex-direction:column;align-items:center;gap:4px;}',
    '.mfw-thumb img{width:130px;height:130px;object-fit:cover;border-radius:10px;',
    '  border:2px solid;cursor:pointer;transition:transform .15s;}',
    '.mfw-thumb img:hover{transform:scale(1.04);}',
    '.mfw-thumb .mfw-thumb-del{position:absolute;top:4px;right:4px;width:22px;height:22px;',
    '  background:#dc3545;color:#fff;border:none;border-radius:50%;font-size:13px;',
    '  display:flex;align-items:center;justify-content:center;cursor:pointer;line-height:1;}',
    '.mfw-thumb .mfw-thumb-name{font-size:0.68rem;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:center;}',

    /* 위치박스 */
    '#mfw-loc-box{border-radius:8px;padding:0.75rem 1rem;font-size:0.78rem;}',
    '#mfw-loc-display{white-space:normal;word-break:break-all;line-height:1.5;font-family:monospace;font-size:0.72rem;opacity:.8;}',

    /* 폼 라벨 */
    '.mfw-form-label{font-size:0.82rem;font-weight:600;margin-bottom:0.3rem;}',

    /* 클립보드 버튼 */
    '.mfw-cb-btn{font-size:0.7rem;padding:2px 8px;border-radius:6px;',
    '  border:1px solid var(--mfw-border,#d1d5db);color:var(--mfw-text-muted,#6b7280);',
    '  background:transparent;white-space:nowrap;transition:all .15s;}',
    '.mfw-cb-btn:hover{border-color:#0d6efd;color:#0d6efd;}',
    '[data-theme="dark"] .mfw-cb-btn{border-color:#2d3148;color:#9ca3af;}',
    '[data-theme="dark"] .mfw-cb-btn:hover{border-color:#60a5fa;color:#60a5fa;}',
    /* 기능 안내 바 */
    '.mfw-img-help{display:flex;flex-wrap:wrap;gap:6px 12px;margin-bottom:8px;font-size:0.72rem;color:var(--mfw-text-muted,#9ca3af);}',
    '.mfw-img-help span{display:flex;align-items:center;gap:3px;}',
    '.mfw-img-help i{font-size:0.8rem;}',
    '[data-theme="dark"] .mfw-img-help{color:#6b7280;}',
    '.mfw-label-muted{font-size:0.75rem;color:var(--mfw-text-muted,#9ca3af);}',
    /* 수정요청 버튼 (h4 옆) */
    '.mf-req-btn{font-size:0.7rem!important;padding:2px 9px!important;font-weight:normal;',
    '  border-color:#dc3545!important;color:#dc3545!important;opacity:.8;flex-shrink:0;}',
    '.mf-req-btn:hover{opacity:1;background:#dc3545!important;color:#fff!important;}',
  ].join('');

  var styleEl = document.createElement('style');
  styleEl.textContent = CSS;
  document.head.appendChild(styleEl);

  // ── 모달 HTML 동적 주입 ──────────────────────────────────────────
  var MODAL_HTML = [
    '<div class="modal fade" id="mfw-modal" tabindex="-1" aria-hidden="true">',
    '<div class="modal-dialog modal-lg modal-dialog-scrollable">',
    '<div class="modal-content">',

    // 헤더
    '<div class="modal-header mfw-header">',
    '<div class="d-flex align-items-center gap-3 flex-grow-1 overflow-hidden">',
    '<div style="width:38px;height:38px;background:rgba(255,255,255,.13);border-radius:10px;',
    'display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:#fff;flex-shrink:0;">',
    '<i class="bi bi-pencil-square"></i></div>',
    '<div class="overflow-hidden">',
    '<h5 class="modal-title mb-0 fw-bold text-white" style="font-size:1rem;">수정요청</h5>',
    '<div id="mfw-loc-label" class="text-white-50 text-truncate" style="font-size:0.72rem;margin-top:2px;"></div>',
    '</div></div>',
    '<button type="button" class="btn-close btn-close-white ms-2 flex-shrink-0" data-bs-dismiss="modal"></button>',
    '</div>',

    // 바디
    '<div class="modal-body p-0">',

    // 로딩
    '<div id="mfw-progress" class="d-none"><div class="d-flex flex-column align-items-center justify-content-center p-5 gap-3">',
    '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">로딩</span></div>',
    '<div class="text-muted small">등록 중입니다...</div></div></div>',

    // 성공
    '<div id="mfw-success" class="d-none"><div class="d-flex flex-column align-items-center justify-content-center p-5 gap-3">',
    '<div style="width:64px;height:64px;background:#198754;border-radius:50%;',
    'display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.8rem;">',
    '<i class="bi bi-check-lg"></i></div>',
    '<div class="text-center"><h5 class="fw-bold mb-1">요청이 등록됐습니다!</h5>',
    '<p class="text-muted small mb-0" id="mfw-success-id"></p></div>',
    '<button class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">닫기</button>',
    '</div></div>',

    // 폼
    '<div id="mfw-form-wrap"><div class="px-4 pb-4 pt-3">',

    // 요청 유형
    '<div class="mb-3"><label class="mfw-form-label">요청 유형</label>',
    '<div class="d-flex gap-1 flex-wrap" id="mfw-type-btns">',
    '<button class="btn btn-sm btn-outline-secondary mfw-type" data-val="bug_fix">오류수정</button>',
    '<button class="btn btn-sm btn-primary mfw-type active" data-val="content_edit">내용수정</button>',
    '<button class="btn btn-sm btn-outline-secondary mfw-type" data-val="feature_add">기능추가</button>',
    '<button class="btn btn-sm btn-outline-secondary mfw-type" data-val="update">업데이트</button>',
    '<button class="btn btn-sm btn-outline-secondary mfw-type" data-val="other">기타</button>',
    '</div></div>',

    // 중요도
    '<div class="mb-3"><label class="mfw-form-label">중요도</label>',
    '<div class="d-flex gap-1 flex-wrap" id="mfw-prio-btns">',
    '<button class="btn btn-sm btn-outline-secondary mfw-prio" data-val="low">낮음</button>',
    '<button class="btn btn-sm btn-primary mfw-prio active" data-val="normal">보통</button>',
    '<button class="btn btn-sm btn-outline-warning mfw-prio" data-val="high">높음</button>',
    '<button class="btn btn-sm btn-outline-danger mfw-prio" data-val="urgent">긴급</button>',
    '</div></div>',

    // 요청 제목
    '<div class="mb-3"><label class="mfw-form-label">요청 제목 <span class="text-danger">*</span></label>',
    '<input type="text" id="mfw-title" class="form-control form-control-sm" maxlength="100" placeholder="수정 요청 제목">',
    '<div id="mfw-title-msg" class="text-danger small mt-1" style="display:none;"></div></div>',

    // 요청 내용
    '<div class="mb-3"><label class="mfw-form-label">요청 내용 <span class="text-danger">*</span></label>',
    '<textarea id="mfw-content" class="form-control form-control-sm" rows="5" maxlength="5000"',
    ' placeholder="수정이 필요한 내용을 상세히 입력하세요."></textarea>',
    '<div id="mfw-content-msg" class="text-danger small mt-1" style="display:none;"></div></div>',

    // 이미지 첨부 (다중, 드래그드롭)
    '<div class="mb-3">',
    '<div class="d-flex align-items-center justify-content-between mb-1">',
    '<label class="mfw-form-label mb-0">이미지 첨부 <span class="mfw-label-muted fw-normal">(여러 장 가능, 장당 최대 5MB)</span></label>',
    '<div class="d-flex gap-1">',
    '<button type="button" class="btn btn-xs mfw-cb-btn" onclick="mfwPasteFromClipboard()" title="클립보드의 이미지를 붙여넣기">',
    '<i class="bi bi-clipboard-plus me-1"></i>클립보드 가져오기</button>',
    '<button type="button" class="btn btn-xs mfw-cb-btn" onclick="mfwCopyToClipboard()" title="제목+내용을 클립보드에 복사">',
    '<i class="bi bi-clipboard-check me-1"></i>클립보드에 저장</button>',
    '</div></div>',

    // 기능 안내
    '<div id="mfw-img-help" class="mfw-img-help">',
    '<span><i class="bi bi-images"></i> 여러 장 선택</span>',
    '<span><i class="bi bi-cloud-arrow-up"></i> 드래그&드롭</span>',
    '<span><i class="bi bi-clipboard"></i> Ctrl+V 붙여넣기</span>',
    '<span><i class="bi bi-x-circle"></i> 개별 제거</span>',
    '<span><i class="bi bi-zoom-in"></i> 클릭 확대</span>',
    '</div>',

    '<div id="mfw-image-grid" style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px;"></div>',
    '<div id="mfw-image-area">',
    '<i class="bi bi-cloud-upload"></i>',
    '<div id="mfw-image-area-label">클릭하거나 이미지를 여기로 드래그</div>',
    '<div style="font-size:0.75rem;opacity:.6;">JPG · PNG · GIF · WEBP</div>',
    '</div>',
    '<input type="file" id="mfw-image" accept="image/jpeg,image/png,image/gif,image/webp" multiple style="display:none">',
    '</div>',

    // 위치 정보
    '<div id="mfw-loc-box">',
    '<div class="text-muted mb-1"><i class="bi bi-geo-alt-fill me-1"></i>위치가 자동으로 기록됩니다</div>',
    '<div id="mfw-loc-display" class="text-truncate" style="font-family:monospace;font-size:0.72rem;"></div>',
    '</div>',

    '</div></div>', // /px-4 /mfw-form-wrap
    '</div>', // /modal-body

    // 푸터
    '<div class="modal-footer py-2 px-4" id="mfw-footer" style="justify-content:space-between;">',
    '<a href="https://hms.seoulbeauty.app/hairwang/gangnamunni/admin_manual_feedback_list.php"',
    ' target="_blank" class="btn btn-outline-secondary btn-sm px-3">',
    '<i class="bi bi-list-ul me-1"></i>수정요청 대시보드</a>',
    '<div class="d-flex gap-2">',
    '<button class="btn btn-outline-secondary btn-sm px-3" data-bs-dismiss="modal">취소</button>',
    '<button class="btn btn-dark btn-sm px-4 fw-bold" onclick="mfwSubmit()">',
    '<i class="bi bi-send me-1"></i>저장 및 제출</button>',
    '</div>',
    '</div>',

    '</div></div></div>' // /modal-content /modal-dialog /modal
  ].join('');

  var el = document.createElement('div');
  el.innerHTML = MODAL_HTML;
  document.body.appendChild(el.firstElementChild);

  // ── 상태 ──────────────────────────────────────────────────────────
  var MFW = { type: 'content_edit', priority: 'normal', file: '', tab: '', section: '', sourceDesc: '' };

  // ── 다중 이미지 관리 ──────────────────────────────────────────────
  MFW._files = [];

  var imgArea  = document.getElementById('mfw-image-area');
  var imgModal = document.getElementById('mfw-modal');

  // 클릭 → 파일선택
  imgArea.addEventListener('click', function () { document.getElementById('mfw-image').click(); });

  // ── drag/drop: document 레벨에서 처리 (Bootstrap modal 차단 우회) ──
  var _dragTarget = null;
  document.addEventListener('dragenter', function (e) {
    if (!imgModal.classList.contains('show')) return;
    _dragTarget = e.target;
    imgArea.classList.add('drag-over');
  });
  document.addEventListener('dragover', function (e) {
    if (!imgModal.classList.contains('show')) return;
    e.preventDefault();
  });
  document.addEventListener('dragleave', function (e) {
    if (!imgModal.classList.contains('show')) return;
    if (e.target === _dragTarget || e.target === document) imgArea.classList.remove('drag-over');
  });
  document.addEventListener('drop', function (e) {
    if (!imgModal.classList.contains('show')) return;
    e.preventDefault();
    imgArea.classList.remove('drag-over');
    var files = Array.from(e.dataTransfer.files || []).filter(function (f) { return f.type.startsWith('image/'); });
    files.forEach(addImage);
  });

  // 파일선택 (multiple)
  document.getElementById('mfw-image').addEventListener('change', function () {
    Array.from(this.files).forEach(addImage);
    this.value = '';
  });

  // Ctrl+V 클립보드
  document.addEventListener('paste', function (e) {
    if (!imgModal.classList.contains('show')) return;
    var items = (e.clipboardData || {}).items || [];
    for (var i = 0; i < items.length; i++) {
      if (items[i].type.startsWith('image/')) {
        var file = items[i].getAsFile();
        if (file) addImage(file);
      }
    }
  });

  function addImage(file) {
    if (file.size > 5 * 1024 * 1024) { alert(file.name + ' : 5MB를 초과합니다.'); return; }
    var idx = MFW._files.length;
    MFW._files.push({ file: file, name: file.name || ('clipboard_' + (idx + 1) + '.png') });
    var reader = new FileReader();
    reader.onload = function (e) {
      MFW._files[idx].dataUrl = e.target.result;
      renderImageGrid();
    };
    reader.readAsDataURL(file);
  }

  function renderImageGrid() {
    var grid = document.getElementById('mfw-image-grid');
    grid.innerHTML = '';
    MFW._files.forEach(function (item, i) {
      if (!item.dataUrl) return;
      var wrap = document.createElement('div');
      wrap.className = 'mfw-thumb';
      wrap.innerHTML =
        '<img src="' + item.dataUrl + '" alt="" onclick="mfwLightbox(' + i + ')">' +
        '<button class="mfw-thumb-del" onclick="mfwRemoveImage(' + i + ')" title="제거">&times;</button>' +
        '<div class="mfw-thumb-name">' + escHtml(item.name) + '</div>';
      grid.appendChild(wrap);
    });
    // 드래그존 라벨 업데이트 (항상 표시)
    var lbl = document.getElementById('mfw-image-area-label');
    if (lbl) lbl.textContent = MFW._files.length > 0
      ? '추가 이미지 클릭 또는 드래그 (현재 ' + MFW._files.length + '장)'
      : '클릭하거나 이미지를 여기로 드래그';
  }

  function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  window.mfwRemoveImage = function (idx) {
    MFW._files.splice(idx, 1);
    renderImageGrid();
  };

  // 라이트박스 확대
  window.mfwLightbox = function (idx) {
    var item = MFW._files[idx];
    if (!item || !item.dataUrl) return;
    var m = document.getElementById('mfw-lb');
    if (!m) {
      m = document.createElement('div');
      m.id = 'mfw-lb';
      m.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:99999;display:flex;align-items:center;justify-content:center;cursor:zoom-out;';
      m.innerHTML = '<img id="mfw-lb-img" style="max-width:92vw;max-height:92vh;border-radius:10px;box-shadow:0 8px 48px rgba(0,0,0,.7);">';
      m.addEventListener('click', function () { m.style.display = 'none'; });
      document.body.appendChild(m);
    }
    document.getElementById('mfw-lb-img').src = item.dataUrl;
    m.style.display = 'flex';
  };

  window.mfwClearImage = function () {
    MFW._files = [];
    var grid = document.getElementById('mfw-image-grid');
    if (grid) grid.innerHTML = '';
    document.getElementById('mfw-image').value = '';
    renderImageGrid();
  };

  // ── 유형 버튼 ────────────────────────────────────────────────────
  document.getElementById('mfw-type-btns').addEventListener('click', function (e) {
    var btn = e.target.closest('.mfw-type');
    if (!btn) return;
    document.querySelectorAll('.mfw-type').forEach(function (b) {
      b.className = 'btn btn-sm mfw-type btn-outline-secondary';
    });
    btn.classList.remove('btn-outline-secondary');
    btn.classList.add('btn-primary', 'active');
    MFW.type = btn.dataset.val;
  });

  // ── 중요도 버튼 ──────────────────────────────────────────────────
  document.getElementById('mfw-prio-btns').addEventListener('click', function (e) {
    var btn = e.target.closest('.mfw-prio');
    if (!btn) return;
    document.querySelectorAll('.mfw-prio').forEach(function (b) {
      b.className = 'btn btn-sm mfw-prio btn-outline-secondary';
    });
    var clsMap = { low: 'btn-outline-secondary', normal: 'btn-primary', high: 'btn-outline-warning', urgent: 'btn-outline-danger' };
    btn.className = 'btn btn-sm mfw-prio active ' + (clsMap[btn.dataset.val] || 'btn-primary');
    MFW.priority = btn.dataset.val;
  });

  // ── 모달 열기 ────────────────────────────────────────────────────
  window.openMfSection = function (opts) {
    MFW.file       = opts.file       || '';
    MFW.tab        = opts.tab        || '';
    MFW.section    = opts.section    || '';
    MFW.sourceDesc = opts.sourceDesc || '';
    MFW.type       = 'content_edit';
    MFW.priority   = 'normal';
    MFW._files = [];

    var locPath = [MFW.file, MFW.tab, MFW.section].filter(Boolean).join(' > ');
    document.getElementById('mfw-loc-label').textContent   = locPath;
    document.getElementById('mfw-loc-display').textContent = location.href + '  ·  ' + locPath;

    document.getElementById('mfw-title').value   = '[' + (MFW.section || MFW.tab || '') + '] ';
    document.getElementById('mfw-content').value = '';
    mfwClearImage();

    document.getElementById('mfw-progress').classList.add('d-none');
    document.getElementById('mfw-success').classList.add('d-none');
    document.getElementById('mfw-form-wrap').classList.remove('d-none');
    document.getElementById('mfw-footer').classList.remove('d-none');
    document.getElementById('mfw-title-msg').style.display   = 'none';
    document.getElementById('mfw-content-msg').style.display = 'none';

    document.querySelectorAll('.mfw-type').forEach(function (b) {
      b.className = 'btn btn-sm mfw-type btn-outline-secondary';
    });
    var tBtn = document.querySelector('.mfw-type[data-val="content_edit"]');
    if (tBtn) { tBtn.classList.remove('btn-outline-secondary'); tBtn.classList.add('btn-primary', 'active'); }

    document.querySelectorAll('.mfw-prio').forEach(function (b) {
      b.className = 'btn btn-sm mfw-prio btn-outline-secondary';
    });
    var pBtn = document.querySelector('.mfw-prio[data-val="normal"]');
    if (pBtn) { pBtn.className = 'btn btn-sm mfw-prio active btn-primary'; }

    new bootstrap.Modal(document.getElementById('mfw-modal')).show();
    setTimeout(function () { document.getElementById('mfw-title').focus(); }, 400);
    // 클립보드에 이미지가 있으면 자동으로 추가 (사용자가 이미 복사해 둔 경우)
    setTimeout(_tryAutoClipboardImage, 600);
  };

  // ── 제출 ─────────────────────────────────────────────────────────
  window.mfwSubmit = async function () {
    var title   = document.getElementById('mfw-title').value.trim();
    var content = document.getElementById('mfw-content').value.trim();

    if (!title) {
      var tm = document.getElementById('mfw-title-msg');
      tm.textContent = '요청 제목은 필수입니다.'; tm.style.display = 'block';
      document.getElementById('mfw-title').focus();
      setTimeout(function () { tm.style.display = 'none'; }, 2500);
      return;
    }
    if (!content) {
      var cm = document.getElementById('mfw-content-msg');
      cm.textContent = '요청 내용은 필수입니다.'; cm.style.display = 'block';
      document.getElementById('mfw-content').focus();
      setTimeout(function () { cm.style.display = 'none'; }, 2500);
      return;
    }

    var fd = new FormData();
    fd.append('request_type',         MFW.type);
    fd.append('priority',             MFW.priority);
    fd.append('title',                title);
    fd.append('content',              content);
    fd.append('page_url',             location.href);
    fd.append('page_title',           document.title);
    fd.append('page_path',            MFW.file || location.pathname);
    fd.append('section_title',        MFW.section);
    fd.append('menu_label',           MFW.tab);
    fd.append('source_location_desc', [MFW.file, MFW.tab, MFW.section, MFW.sourceDesc].filter(Boolean).join(' > '));

    // 이미지: 드래그드롭 또는 파일선택
    MFW._files.forEach(function(item) { fd.append('images[]', item.file, item.name); });

    document.getElementById('mfw-form-wrap').classList.add('d-none');
    document.getElementById('mfw-footer').classList.add('d-none');
    document.getElementById('mfw-progress').classList.remove('d-none');

    try {
      var apiUrl = location.href.replace(/\/manual\/[^?#]*.*$/, '/api_manual_feedback_create.php');
      var resp = await fetch(apiUrl, { method: 'POST', body: fd });
      var res  = await resp.json();
      document.getElementById('mfw-progress').classList.add('d-none');

      if (res.success) {
        document.getElementById('mfw-success').classList.remove('d-none');
        document.getElementById('mfw-success-id').textContent = '요청번호: #' + res.id;
      } else {
        document.getElementById('mfw-form-wrap').classList.remove('d-none');
        document.getElementById('mfw-footer').classList.remove('d-none');
        alert('오류: ' + (res.error || '저장 실패'));
      }
    } catch (e) {
      document.getElementById('mfw-form-wrap').classList.remove('d-none');
      document.getElementById('mfw-footer').classList.remove('d-none');
      alert('네트워크 오류가 발생했습니다.');
    }
  };

  // ── 클립보드 이미지 가져오기 (버튼) ─────────────────────────────
  window.mfwPasteFromClipboard = async function () {
    try {
      if (!navigator.clipboard || !navigator.clipboard.read) {
        alert('이 브라우저는 클립보드 읽기를 지원하지 않습니다. Ctrl+V를 사용하세요.');
        return;
      }
      var items = await navigator.clipboard.read();
      var found = false;
      for (var i = 0; i < items.length; i++) {
        for (var t = 0; t < items[i].types.length; t++) {
          var type = items[i].types[t];
          if (type.startsWith('image/')) {
            var blob = await items[i].getType(type);
            var ext = type.split('/')[1] || 'png';
            var file = new File([blob], 'clipboard.' + ext, { type: type });
            addImage(file);
            found = true;
            break;
          }
        }
        if (found) break;
      }
      if (!found) alert('클립보드에 이미지가 없습니다.');
    } catch (e) {
      alert('클립보드 접근 실패. Ctrl+V로 붙여넣기 하세요.');
    }
  };

  // ── 클립보드에 제목+내용 저장 (버튼) ────────────────────────────
  window.mfwCopyToClipboard = function () {
    var title   = (document.getElementById('mfw-title').value   || '').trim();
    var content = (document.getElementById('mfw-content').value || '').trim();
    var text = '';
    if (title)   text += '[제목] ' + title + '\n\n';
    if (content) text += '[내용] ' + content;
    text = text.trim();
    if (!text) { alert('복사할 내용이 없습니다.'); return; }
    navigator.clipboard.writeText(text)
      .then(function () {
        var btn = document.querySelector('.mfw-cb-btn[onclick*="CopyToClipboard"]');
        if (btn) {
          var orig = btn.innerHTML;
          btn.innerHTML = '<i class="bi bi-check2 me-1"></i>복사됨!';
          setTimeout(function () { btn.innerHTML = orig; }, 1500);
        }
      })
      .catch(function () { alert('클립보드 복사에 실패했습니다.'); });
  };

  // ── 모달 열릴 때 클립보드 이미지 자동 감지 ───────────────────────
  async function _tryAutoClipboardImage() {
    try {
      if (!navigator.clipboard || !navigator.clipboard.read) return;
      var items = await navigator.clipboard.read();
      for (var i = 0; i < items.length; i++) {
        for (var t = 0; t < items[i].types.length; t++) {
          var type = items[i].types[t];
          if (type.startsWith('image/')) {
            var blob = await items[i].getType(type);
            var ext = type.split('/')[1] || 'png';
            var file = new File([blob], 'clipboard.' + ext, { type: type });
            addImage(file);
            return; // 첫 번째 이미지만 자동 추가
          }
        }
      }
    } catch (e) { /* 권한 없으면 조용히 무시 */ }
  }

  // ── 모달 닫힐 때 초기화 ──────────────────────────────────────────
  document.getElementById('mfw-modal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('mfw-form-wrap').classList.remove('d-none');
    document.getElementById('mfw-footer').classList.remove('d-none');
    document.getElementById('mfw-progress').classList.add('d-none');
    document.getElementById('mfw-success').classList.add('d-none');
  });

})();
