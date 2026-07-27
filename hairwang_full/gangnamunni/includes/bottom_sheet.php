<?php
/**
 * 서울미녀 공통 바텀시트(확인 모달)
 *
 * 사용법 — 페이지 하단(</body> 직전)에서 한 번만 include 한 뒤 JS 로 연다.
 *
 *   <?php include __DIR__ . '/includes/bottom_sheet.php'; ?>
 *   <script>
 *   SMSheet.open({
 *       title:       '잠시 로그아웃하시겠어요?',   // HTML 허용(<br> 등) — 값은 서버에서 이스케이프할 것
 *       desc:        '설명 문구(선택)',            // 텍스트로만 삽입
 *       confirmText: '로그아웃',
 *       cancelText:  '아니오',
 *       onConfirm:   function () { location.href = '...'; }
 *   });
 *   </script>
 *
 * 두 번 include 되어도 마크업이 중복되지 않도록 가드를 둔다.
 *
 * @created 2026-07-27
 */
if (defined('SM_BOTTOM_SHEET_LOADED')) return;
define('SM_BOTTOM_SHEET_LOADED', true);
?>
<style>
:root{
  --sm-red:#AF2C3C;
  --sm-red-press:#8E2231;
  --sm-ink:#1A1A1A;
  --sm-sub:#333333;
  --sm-sheet-max:520px;
}

/* ---- 뒷배경 ---- */
.sm-backdrop{
  position:fixed; inset:0;
  background:rgba(0,0,0,.45);
  opacity:0; visibility:hidden;
  transition:opacity .25s ease, visibility .25s ease;
  z-index:9998;
}
.sm-backdrop.is-open{ opacity:1; visibility:visible; }

/* ---- 시트 본체 ---- */
.sm-sheet{
  position:fixed; left:0; right:0; bottom:0;
  width:100%; max-width:var(--sm-sheet-max);
  margin:0 auto;
  background:#fff;
  padding:14px 20px calc(26px + env(safe-area-inset-bottom, 0px));
  transform:translateY(100%);
  transition:transform .3s cubic-bezier(.32,.72,0,1);
  z-index:9999;
  touch-action:none;
}
.sm-sheet.is-open{ transform:translateY(0); }
.sm-sheet.is-dragging{ transition:none; }

/* ---- 손잡이 ---- */
.sm-handle{
  width:56px; height:5px; border-radius:3px;
  background:var(--sm-red);
  margin:0 auto 30px;
}

/* ---- 텍스트 ---- */
.sm-title{
  margin:0; padding:0 4px;
  font-size:19px; line-height:1.45; font-weight:700;
  color:var(--sm-ink); text-align:center;
  word-break:keep-all;
  white-space:pre-line;
}
.sm-desc{
  margin:18px 0 0; padding:0 4px;
  font-size:14px; line-height:1.5; font-weight:400;
  color:var(--sm-sub); text-align:center;
  word-break:keep-all;
}
.sm-desc:empty{ display:none; }

/* ---- 버튼 ---- */
.sm-actions{
  display:flex; gap:12px; justify-content:center;
  margin-top:30px;
}
.sm-btn{
  flex:1 1 0; max-width:170px; min-height:52px;
  font-family:inherit; font-size:16px; font-weight:600;
  border:1px solid var(--sm-red); border-radius:0;
  cursor:pointer; -webkit-tap-highlight-color:transparent;
  transition:background-color .15s ease, color .15s ease;
}
.sm-btn--ghost{ background:#fff; color:var(--sm-red); }
.sm-btn--solid{ background:var(--sm-red); color:#fff; }
.sm-btn:active{ transform:translateY(1px); }
.sm-btn--ghost:active{ background:#FBEFF1; }
.sm-btn--solid:active{ background:var(--sm-red-press); border-color:var(--sm-red-press); }
.sm-btn:focus-visible{ outline:2px solid var(--sm-ink); outline-offset:2px; }

/* ---- 스크롤 잠금 ---- */
body.sm-locked{ position:fixed; width:100%; overflow:hidden; }

/* ---- 접근성: 모션 최소화 ---- */
@media (prefers-reduced-motion: reduce){
  .sm-sheet, .sm-backdrop{ transition-duration:.01ms; }
}
</style>

<div class="sm-backdrop" id="smBackdrop" hidden></div>

<div class="sm-sheet" id="smSheet" role="dialog" aria-modal="true" aria-labelledby="smTitle" hidden>
  <div class="sm-handle" id="smHandle"></div>
  <h2 class="sm-title" id="smTitle"></h2>
  <p class="sm-desc" id="smDesc"></p>
  <div class="sm-actions">
    <button type="button" class="sm-btn sm-btn--ghost" id="smConfirm"></button>
    <button type="button" class="sm-btn sm-btn--solid" id="smCancel"></button>
  </div>
</div>

<script>
var SMSheet = (function () {
  var backdrop = document.getElementById('smBackdrop');
  var sheet    = document.getElementById('smSheet');
  var handle   = document.getElementById('smHandle');
  var titleEl  = document.getElementById('smTitle');
  var descEl   = document.getElementById('smDesc');
  var okBtn    = document.getElementById('smConfirm');
  var noBtn    = document.getElementById('smCancel');

  var onConfirm = null;
  var lastFocus = null;
  var scrollY   = 0;
  var isOpen    = false;

  /* ---------- 열기 ---------- */
  function open(opt) {
    if (isOpen) return;
    opt = opt || {};

    // 제목은 항상 텍스트로 삽입한다. 줄바꿈은 "\n" + white-space:pre-line 으로 처리한다.
    titleEl.textContent = opt.title || '';
    descEl.textContent  = opt.desc  || '';
    okBtn.textContent   = opt.confirmText || '확인';
    noBtn.textContent   = opt.cancelText  || '아니오';
    onConfirm           = typeof opt.onConfirm === 'function' ? opt.onConfirm : null;

    lastFocus = document.activeElement;
    scrollY   = window.scrollY || window.pageYOffset;

    backdrop.hidden = false;
    sheet.hidden    = false;
    // 강제 리플로우 → 트랜지션이 확실히 재생되도록
    void sheet.offsetHeight;

    document.body.style.top = '-' + scrollY + 'px';
    document.body.classList.add('sm-locked');

    backdrop.classList.add('is-open');
    sheet.classList.add('is-open');
    isOpen = true;

    // 실수 클릭 방지: 취소 버튼에 포커스
    setTimeout(function () { noBtn.focus(); }, 60);
  }

  /* ---------- 닫기 ---------- */
  function close() {
    if (!isOpen) return;
    isOpen = false;

    backdrop.classList.remove('is-open');
    sheet.classList.remove('is-open');
    sheet.style.transform = '';

    document.body.classList.remove('sm-locked');
    document.body.style.top = '';
    window.scrollTo(0, scrollY);

    setTimeout(function () {
      if (!isOpen) { backdrop.hidden = true; sheet.hidden = true; }
    }, 300);

    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  /* ---------- 기본 이벤트 ---------- */
  noBtn.addEventListener('click', close);
  backdrop.addEventListener('click', close);

  okBtn.addEventListener('click', function () {
    var fn = onConfirm;
    close();
    if (fn) fn();
  });

  document.addEventListener('keydown', function (e) {
    if (!isOpen) return;
    if (e.key === 'Escape') { close(); return; }

    // 시트 밖으로 탭 이동 방지
    if (e.key === 'Tab') {
      var f = [okBtn, noBtn];
      var i = f.indexOf(document.activeElement);
      e.preventDefault();
      var next = e.shiftKey ? (i <= 0 ? f.length - 1 : i - 1)
                            : (i === f.length - 1 ? 0 : i + 1);
      f[next].focus();
    }
  });

  /* ---------- 아래로 끌어서 닫기 ---------- */
  var startY = 0, deltaY = 0, dragging = false;

  function dragStart(e) {
    if (!isOpen) return;
    dragging = true; deltaY = 0;
    startY = (e.touches ? e.touches[0].clientY : e.clientY);
    sheet.classList.add('is-dragging');
  }
  function dragMove(e) {
    if (!dragging) return;
    var y = (e.touches ? e.touches[0].clientY : e.clientY);
    deltaY = Math.max(0, y - startY);      // 위로는 끌리지 않게
    sheet.style.transform = 'translateY(' + deltaY + 'px)';
  }
  function dragEnd() {
    if (!dragging) return;
    dragging = false;
    sheet.classList.remove('is-dragging');
    if (deltaY > 80) { close(); }          // 충분히 내렸으면 닫기
    else { sheet.style.transform = 'translateY(0)'; }
  }

  // 손잡이 영역에서만 드래그 시작 (버튼 오작동 방지)
  handle.addEventListener('touchstart', dragStart, { passive: true });
  handle.addEventListener('mousedown',  dragStart);
  document.addEventListener('touchmove', dragMove, { passive: true });
  document.addEventListener('mousemove', dragMove);
  document.addEventListener('touchend',  dragEnd);
  document.addEventListener('mouseup',   dragEnd);

  return { open: open, close: close };
})();
</script>
