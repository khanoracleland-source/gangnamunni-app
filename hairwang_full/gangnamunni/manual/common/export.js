/* ============================================
   Seoul Beauty Manual System - Export & Common JS
   Version: 1.0.0 | 2026-02-25
   ============================================ */

// ---- Theme Management ----
(function initTheme() {
  const saved = localStorage.getItem('sb-manual-theme') || 'dark';
  document.documentElement.setAttribute('data-theme', saved);
  document.documentElement.setAttribute('data-bs-theme', saved);
})();

function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme');
  const next = current === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  document.documentElement.setAttribute('data-bs-theme', next);
  localStorage.setItem('sb-manual-theme', next);
  updateThemeIcon();
}

function updateThemeIcon() {
  const theme = document.documentElement.getAttribute('data-theme');
  const icons = document.querySelectorAll('.sb-theme-icon');
  icons.forEach(icon => {
    icon.className = theme === 'dark'
      ? 'bi bi-sun-fill sb-theme-icon'
      : 'bi bi-moon-fill sb-theme-icon';
  });
}

// ---- Back to Top ----
function initBackToTop() {
  const btn = document.querySelector('.sb-back-to-top');
  if (!btn) return;
  window.addEventListener('scroll', () => {
    btn.classList.toggle('visible', window.scrollY > 300);
  });
  btn.addEventListener('click', () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
}

// ---- PDF Export ----
function exportToPDF(filename) {
  const element = document.querySelector('.sb-main');
  if (!element) { alert('내보낼 콘텐츠를 찾을 수 없습니다.'); return; }

  const fname = filename || document.title.replace(/[^a-zA-Z0-9가-힣]/g, '_') + '.pdf';

  // Show loading
  const loadingOverlay = showExportLoading('PDF 생성 중...');

  const opt = {
    margin:       [10, 10, 10, 10],
    filename:     fname,
    image:        { type: 'jpeg', quality: 0.95 },
    html2canvas:  { scale: 2, useCORS: true, letterRendering: true },
    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' },
    pagebreak:    { mode: ['avoid-all', 'css', 'legacy'] }
  };

  if (typeof html2pdf === 'undefined') {
    hideExportLoading(loadingOverlay);
    alert('PDF 라이브러리를 로딩 중입니다. 잠시 후 다시 시도해 주세요.');
    return;
  }

  html2pdf().set(opt).from(element).save().then(() => {
    hideExportLoading(loadingOverlay);
  }).catch(err => {
    hideExportLoading(loadingOverlay);
    console.error('PDF export error:', err);
    alert('PDF 생성 중 오류가 발생했습니다.');
  });
}

// ---- Word Export ----
function exportToWord(filename) {
  const element = document.querySelector('.sb-main');
  if (!element) { alert('내보낼 콘텐츠를 찾을 수 없습니다.'); return; }

  const fname = filename || document.title.replace(/[^a-zA-Z0-9가-힣]/g, '_') + '.doc';
  const loadingOverlay = showExportLoading('Word 문서 생성 중...');

  try {
    const styles = `
      <style>
        body { font-family: '맑은 고딕', Malgun Gothic, sans-serif; font-size: 11pt; line-height: 1.6; color: #333; }
        h1 { font-size: 18pt; color: #6C63FF; border-bottom: 2px solid #6C63FF; padding-bottom: 6pt; }
        h2 { font-size: 15pt; color: #333; margin-top: 16pt; }
        h3 { font-size: 13pt; color: #555; }
        table { border-collapse: collapse; width: 100%; margin: 8pt 0; }
        th, td { border: 1px solid #ddd; padding: 6pt 8pt; text-align: left; }
        th { background: #f5f5f5; font-weight: bold; }
        code { background: #f0f0f0; padding: 2pt 4pt; font-family: Consolas, monospace; font-size: 10pt; }
        pre { background: #f5f5f5; padding: 8pt; border: 1px solid #ddd; font-family: Consolas, monospace; font-size: 9pt; }
        .sb-no-print, .sb-back-to-top, .sb-footer-actions, .btn-go, button { display: none !important; }
        .sb-badge-pass { color: green; font-weight: bold; }
        .sb-badge-fail { color: red; font-weight: bold; }
      </style>
    `;

    const html = `
      <html xmlns:o="urn:schemas-microsoft-com:office:office"
            xmlns:w="urn:schemas-microsoft-com:office:word"
            xmlns="http://www.w3.org/TR/REC-html40">
      <head>
        <meta charset="utf-8">
        <title>${document.title}</title>
        ${styles}
        <!--[if gte mso 9]>
        <xml>
          <w:WordDocument>
            <w:View>Print</w:View>
            <w:Zoom>100</w:Zoom>
          </w:WordDocument>
        </xml>
        <![endif]-->
      </head>
      <body>
        ${element.innerHTML}
      </body>
      </html>
    `;

    const blob = new Blob(['\ufeff', html], { type: 'application/msword' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = fname;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    hideExportLoading(loadingOverlay);
  } catch(err) {
    hideExportLoading(loadingOverlay);
    console.error('Word export error:', err);
    alert('Word 문서 생성 중 오류가 발생했습니다.');
  }
}

// ---- Loading Overlay ----
function showExportLoading(msg) {
  const overlay = document.createElement('div');
  overlay.className = 'sb-export-loading';
  overlay.innerHTML = `
    <div style="background: var(--sb-bg-card); padding: 2rem 3rem; border-radius: 16px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.2); text-align: center;">
      <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
        <span class="visually-hidden">Loading...</span>
      </div>
      <div style="font-size: 1rem; font-weight: 600; color: var(--sb-text);">${msg}</div>
      <div style="font-size: 0.8rem; color: var(--sb-text-muted); margin-top: 0.5rem;">잠시만 기다려 주세요...</div>
    </div>
  `;
  overlay.style.cssText = `
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center;
    z-index: 9999;
  `;
  document.body.appendChild(overlay);
  return overlay;
}

function hideExportLoading(overlay) {
  if (overlay && overlay.parentNode) {
    overlay.parentNode.removeChild(overlay);
  }
}

// ---- Accordion / Endpoint Toggle ----
function initEndpointToggles() {
  document.querySelectorAll('.sb-endpoint-header').forEach(header => {
    header.addEventListener('click', () => {
      const body = header.nextElementSibling;
      if (body && body.classList.contains('sb-endpoint-body')) {
        body.classList.toggle('show');
        header.classList.toggle('expanded');
      }
    });
  });
}

// ---- TOC Active Tracking ----
function initTocTracking() {
  const tocLinks = document.querySelectorAll('.sb-toc-list a');
  if (!tocLinks.length) return;

  const headings = [];
  tocLinks.forEach(link => {
    const target = document.querySelector(link.getAttribute('href'));
    if (target) headings.push({ el: target, link: link });
  });

  window.addEventListener('scroll', () => {
    const scrollPos = window.scrollY + 120;
    let current = headings[0];
    for (const h of headings) {
      if (h.el.offsetTop <= scrollPos) current = h;
    }
    tocLinks.forEach(l => l.classList.remove('active'));
    if (current) current.link.classList.add('active');
  });
}

// ---- Animation on Scroll ----
function initScrollAnimation() {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('sb-animate');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll('.sb-animate-on-scroll').forEach(el => {
    observer.observe(el);
  });
}

// ---- Print ----
function printPage() {
  window.print();
}

// ---- Init ----
document.addEventListener('DOMContentLoaded', () => {
  updateThemeIcon();
  initBackToTop();
  initEndpointToggles();
  initTocTracking();
  initScrollAnimation();
});
