/**
 * book-html-renderer.js - 자체 완결형 HTML 스크린샷 북 생성기
 *
 * 캡처 결과(bookData)를 기반으로 브라우저에서 바로 열 수 있는
 * 완전한 HTML 문서를 생성한다. 다크/라이트 모드, PDF/Word/인쇄
 * 내보내기를 지원하며, Bootstrap CDN 외에는 외부 의존성이 없다.
 *
 * 사용법:
 *   const { renderBookHTML } = require('./book-html-renderer');
 *   const html = renderBookHTML(bookData, 'screenshots');
 *   fs.writeFileSync('manual_book.html', html, 'utf8');
 *
 * @since 2026-02-26
 * @version 1.0.0
 */

'use strict';

// =========================================================
// 유틸리티 함수
// =========================================================

/**
 * HTML 특수문자 이스케이프
 * @param {string} str
 * @returns {string}
 */
function esc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * ISO 날짜 문자열을 한국어 형식으로 변환
 * @param {string} isoStr - ISO 8601 날짜 문자열
 * @returns {string} - "YYYY-MM-DD HH:mm" 형식
 */
function formatDate(isoStr) {
    if (!isoStr) return '-';
    const d = new Date(isoStr);
    if (isNaN(d.getTime())) return String(isoStr);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

/**
 * 상태 코드에 대한 배지 HTML 반환
 * @param {string} status - "pass" | "warn" | "fail"
 * @returns {string}
 */
function statusBadge(status) {
    const map = {
        pass: { label: 'PASS', bg: '#198754', color: '#fff' },
        warn: { label: 'WARN', bg: '#ffc107', color: '#000' },
        fail: { label: 'FAIL', bg: '#dc3545', color: '#fff' },
    };
    const s = map[status] || map.warn;
    return `<span class="badge" style="background:${s.bg};color:${s.color};font-size:0.75rem;padding:0.25em 0.6em;border-radius:4px;vertical-align:middle;">${s.label}</span>`;
}

/**
 * 챕터별 pass/warn/fail 집계
 * @param {Object} chapter
 * @returns {{ pass: number, warn: number, fail: number }}
 */
function chapterStats(chapter) {
    const counts = { pass: 0, warn: 0, fail: 0 };
    (chapter.pages || []).forEach((p) => {
        const s = p.status || 'warn';
        if (counts[s] !== undefined) counts[s]++;
        else counts.warn++;
    });
    return counts;
}

/**
 * 챕터 번호를 0-패딩된 2자리 문자열로 변환
 * @param {number} n
 * @returns {string}
 */
function chNum(n) {
    return String(n).padStart(2, '0');
}


// =========================================================
// HTML 조각 생성 함수
// =========================================================

/**
 * <head> 영역 전체 생성
 */
function buildHead(bookData) {
    const title = esc(bookData.title || 'Seoul Beauty 관리자 매뉴얼');
    return `<!DOCTYPE html>
<html lang="ko" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>${title}</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<script>(function(){var t=localStorage.getItem('sb-manual-theme')||'dark';document.documentElement.setAttribute('data-theme',t);})()</script>
<script src="https://cdn.jsdelivr.net/npm/html2pdf.js@0.10.1/dist/html2pdf.bundle.min.js" defer></script>
${buildInlineCSS()}
</head>`;
}

/**
 * 인라인 CSS 블록 생성
 * Seoul Beauty 매뉴얼 디자인 시스템의 CSS 변수 패턴을 따른다.
 */
function buildInlineCSS() {
    return `<style>
/* ============================================
   Seoul Beauty Screenshot Book - Inline Styles
   기존 매뉴얼 시스템 CSS 변수 패턴 준수
   ============================================ */

/* --- CSS Variables (Light Mode) --- */
:root {
    --sb-primary: #6C63FF;
    --sb-primary-dark: #5A52D5;
    --sb-primary-light: #A5A0FF;
    --sb-accent: #FF6584;
    --sb-success: #2ED47A;
    --sb-warning: #FFB946;
    --sb-danger: #F7685B;
    --sb-info: #109CF1;
    --sb-bg: #F5F6FA;
    --sb-bg-card: #FFFFFF;
    --sb-bg-code: #F1F3F5;
    --sb-text: #1A1D26;
    --sb-text-secondary: #6B7280;
    --sb-text-muted: #9CA3AF;
    --sb-border: #E5E7EB;
    --sb-border-light: #F3F4F6;
    --sb-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.06);
    --sb-shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -2px rgba(0,0,0,0.04);
    --sb-radius: 12px;
    --sb-radius-sm: 8px;
    --sb-radius-lg: 16px;
}

/* --- Dark Mode --- */
[data-theme="dark"] {
    --sb-bg: #0F1117;
    --sb-bg-card: #1A1D2E;
    --sb-bg-code: #252836;
    --sb-text: #E5E7EB;
    --sb-text-secondary: #9CA3AF;
    --sb-text-muted: #6B7280;
    --sb-border: #2D3148;
    --sb-border-light: #252836;
    --sb-shadow: 0 1px 3px rgba(0,0,0,0.3), 0 1px 2px rgba(0,0,0,0.2);
    --sb-shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.3), 0 4px 6px -2px rgba(0,0,0,0.2);
}

/* --- Anti Flash --- */
html[data-theme="dark"] {
    background-color: #0F1117;
    color-scheme: dark;
}

/* --- Base --- */
* { box-sizing: border-box; }

body {
    font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Malgun Gothic', sans-serif;
    background-color: var(--sb-bg);
    color: var(--sb-text);
    line-height: 1.7;
    -webkit-font-smoothing: antialiased;
    font-size: 15px;
    margin: 0;
    padding: 0;
}

a { color: var(--sb-primary); text-decoration: none; }
a:hover { color: var(--sb-primary-dark); }

/* --- Sticky Header --- */
.book-header {
    position: sticky;
    top: 0;
    z-index: 1000;
    background: linear-gradient(135deg, #6C63FF, #4F46E5);
    color: #fff;
    padding: 1rem 0;
    box-shadow: 0 2px 10px rgba(0,0,0,0.15);
}
.book-header h1 {
    font-size: 1.4rem;
    font-weight: 800;
    margin: 0 0 0.2rem;
}
.book-header .header-sub {
    font-size: 0.9rem;
    opacity: 0.9;
}
.book-header .header-stats {
    font-size: 0.8rem;
    opacity: 0.8;
    margin-top: 0.15rem;
}
.book-header .theme-toggle-btn {
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.3);
    color: #fff;
    border-radius: 8px;
    padding: 0.4rem 0.75rem;
    cursor: pointer;
    font-size: 1.1rem;
    transition: background 0.2s;
    line-height: 1;
}
.book-header .theme-toggle-btn:hover {
    background: rgba(255,255,255,0.25);
}

/* --- Cover Page --- */
.book-cover {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    background: linear-gradient(135deg, #1a1d2e 0%, #2d1b69 30%, #6C63FF 60%, #8B5CF6 80%, #FF6584 100%);
    color: #fff;
    padding: 4rem 2rem;
    position: relative;
    overflow: hidden;
}
.book-cover::before {
    content: '';
    position: absolute;
    top: -50%; left: -50%;
    width: 200%; height: 200%;
    background: radial-gradient(circle at 30% 40%, rgba(255,255,255,0.05) 0%, transparent 50%);
    pointer-events: none;
}
.book-cover .cover-logo {
    font-size: 4rem;
    font-weight: 900;
    letter-spacing: -0.03em;
    margin-bottom: 0.5rem;
    text-shadow: 0 4px 20px rgba(0,0,0,0.3);
}
.book-cover .cover-subtitle {
    font-size: 1.6rem;
    font-weight: 300;
    opacity: 0.95;
    margin-bottom: 2rem;
}
.book-cover .cover-divider {
    width: 60%;
    max-width: 400px;
    height: 2px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.6), transparent);
    margin: 0 auto 2rem;
}
.book-cover .cover-meta {
    font-size: 0.95rem;
    opacity: 0.8;
    line-height: 2.2;
}
.book-cover .cover-meta strong {
    opacity: 1;
    font-weight: 600;
}
.book-cover .cover-badge {
    display: inline-block;
    padding: 0.4rem 1.2rem;
    border: 1px solid rgba(255,255,255,0.3);
    border-radius: 20px;
    font-size: 0.85rem;
    margin-top: 1.5rem;
    backdrop-filter: blur(4px);
    background: rgba(255,255,255,0.1);
}

/* --- Summary Stats Cards (on Cover) --- */
.cover-stats {
    display: flex;
    justify-content: center;
    gap: 1.25rem;
    flex-wrap: wrap;
    margin-top: 2.5rem;
}
.cover-stat-card {
    background: rgba(255,255,255,0.12);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 12px;
    padding: 1rem 1.5rem;
    min-width: 120px;
    text-align: center;
}
.cover-stat-card .stat-value {
    font-size: 2rem;
    font-weight: 800;
    line-height: 1.2;
}
.cover-stat-card .stat-label {
    font-size: 0.8rem;
    opacity: 0.8;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-top: 0.25rem;
}

/* --- TOC --- */
.book-toc {
    padding: 3rem 2.5rem;
    max-width: 1400px;
    margin: 0 auto;
}
.book-toc h2 {
    font-size: 1.6rem;
    font-weight: 700;
    color: var(--sb-primary);
    border-bottom: 3px solid var(--sb-primary);
    padding-bottom: 0.75rem;
    margin-bottom: 2rem;
}
.toc-chapter {
    margin-bottom: 1.5rem;
}
.toc-chapter-title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    font-size: 1.05rem;
    color: var(--sb-text);
    padding: 0.4rem 0;
}
.toc-chapter-title .toc-ch-num {
    color: var(--sb-primary);
    min-width: 60px;
}
.toc-chapter-title .toc-ch-icon {
    color: var(--sb-primary);
    font-size: 1.1rem;
    width: 24px;
    text-align: center;
}
.toc-chapter-title .toc-ch-count {
    margin-left: auto;
    font-size: 0.8rem;
    color: var(--sb-text-muted);
    font-weight: 400;
}
.toc-page-list {
    list-style: none;
    padding: 0;
    margin: 0.25rem 0 0 2.5rem;
}
.toc-page-entry {
    display: flex;
    align-items: center;
    padding: 0.3rem 0;
    font-size: 0.9rem;
    color: var(--sb-text-secondary);
    border-bottom: 1px dotted var(--sb-border-light);
}
.toc-page-entry a {
    flex: 1;
    color: var(--sb-text-secondary);
}
.toc-page-entry a:hover {
    color: var(--sb-primary);
}
.toc-page-entry .toc-page-badge {
    margin-left: 0.5rem;
}

/* --- Chapter Sections --- */
.book-chapter {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem 1.5rem;
    border-top: 1px solid var(--sb-border);
}
.book-chapter-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 2rem;
    padding-bottom: 0.75rem;
    border-bottom: 3px solid var(--sb-primary);
}
.book-chapter-header h2 {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--sb-text);
    margin: 0;
}
.book-chapter-header .ch-icon {
    font-size: 1.4rem;
    color: var(--sb-primary);
}
.book-chapter-header .ch-num {
    color: var(--sb-primary);
    font-weight: 800;
}

/* --- Page Sections --- */
.page-section {
    background: var(--sb-bg-card);
    border: 1px solid var(--sb-border);
    border-radius: var(--sb-radius);
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--sb-shadow);
}
.page-section-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}
.page-section-header h3 {
    font-size: 1.15rem;
    font-weight: 700;
    margin: 0;
    color: var(--sb-text);
    padding-left: 0.75rem;
    border-left: 3px solid var(--sb-primary);
}
.page-description {
    color: var(--sb-text-secondary);
    font-size: 0.9rem;
    margin-bottom: 1rem;
    line-height: 1.6;
}
.page-url {
    font-size: 0.78rem;
    color: var(--sb-text-muted);
    font-family: 'JetBrains Mono', Consolas, monospace;
    margin-bottom: 0.75rem;
}

/* --- Screenshot Frame --- */
.screenshot-frame {
    border: 1px solid var(--sb-border);
    border-radius: var(--sb-radius-sm);
    overflow: hidden;
    box-shadow: var(--sb-shadow-lg);
    margin-bottom: 1rem;
    background: var(--sb-bg);
}
.screenshot-frame img {
    display: block;
    width: 100%;
    height: auto;
    cursor: zoom-in;
}
/* --- Phone Frame (2-column layout for mobile screenshots) --- */
.page-two-col {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 2rem;
    align-items: start;
}
.phone-frame-wrapper {
    position: sticky;
    top: 80px;
}
.phone-frame {
    width: 300px;
    background: #1a1a1a;
    border-radius: 36px;
    padding: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.3), inset 0 0 0 2px #333;
    position: relative;
}
.phone-frame::before {
    content: '';
    display: block;
    width: 80px;
    height: 24px;
    background: #1a1a1a;
    border-radius: 0 0 14px 14px;
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    z-index: 2;
}
.phone-frame .phone-screen {
    border-radius: 24px;
    overflow: hidden;
    background: var(--sb-bg);
}
.phone-frame .phone-screen img {
    display: block;
    width: 100%;
    height: auto;
    cursor: zoom-in;
}
.phone-frame .phone-home {
    width: 40px;
    height: 5px;
    background: #555;
    border-radius: 3px;
    margin: 8px auto 0;
}
.page-info-panel {
    min-width: 0;
}
.page-info-panel .info-title {
    font-size: 1.15rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
    color: var(--sb-text);
}
.page-info-panel .info-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    font-size: 0.88rem;
    color: var(--sb-text-secondary);
}
.page-info-panel .info-row i {
    color: var(--sb-primary);
    width: 18px;
    text-align: center;
}
.page-info-panel .info-label {
    font-weight: 600;
    min-width: 60px;
    color: var(--sb-text);
}
.page-info-panel .info-desc {
    margin-top: 1rem;
    padding: 1rem;
    background: var(--sb-surface);
    border-radius: var(--sb-radius-sm);
    font-size: 0.88rem;
    color: var(--sb-text-secondary);
    line-height: 1.7;
    border-left: 3px solid var(--sb-primary);
}
.debug-note-area {
    display: none;
    margin-top: 1rem;
}
.debug-mode-on .debug-note-area {
    display: block;
}
.debug-note-area textarea {
    width: 100%;
    min-height: 60px;
    border: 1px solid var(--sb-border);
    border-radius: var(--sb-radius-sm);
    padding: 8px 12px;
    font-size: 0.85rem;
    background: var(--sb-bg);
    color: var(--sb-text);
    resize: vertical;
    font-family: inherit;
}
.debug-note-area textarea:focus {
    outline: none;
    border-color: var(--sb-primary);
    box-shadow: 0 0 0 3px rgba(108,99,255,0.15);
}
.debug-note-area .save-status {
    font-size: 0.75rem;
    color: var(--sb-text-muted);
    margin-top: 4px;
}
.debug-prompt-btn {
    background: linear-gradient(135deg, #6C63FF, #4834d4);
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 6px 14px;
    font-size: 12px;
    cursor: pointer;
    font-weight: 600;
}
.debug-prompt-btn:hover { filter: brightness(1.1); }
.debug-toggle-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    cursor: pointer;
    user-select: none;
}
.debug-toggle-label input[type=checkbox] { accent-color: var(--sb-primary); }
@media (max-width: 900px) {
    .page-two-col { grid-template-columns: 1fr; }
    .phone-frame-wrapper { position: static; display: flex; justify-content: center; }
    .phone-frame { width: 260px; }
}

/* --- Wizard Step Tabs --- */
.step-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 1rem;
    padding: 8px 12px;
    background: var(--sb-surface);
    border-radius: var(--sb-radius-sm);
    border: 1px solid var(--sb-border);
}
.step-tab {
    padding: 6px 14px;
    border: 1px solid var(--sb-border);
    border-radius: 20px;
    background: transparent;
    color: var(--sb-text-muted);
    font-size: 0.82rem;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.step-tab:hover { border-color: var(--sb-primary); color: var(--sb-primary); }
.step-tab.active {
    background: var(--sb-primary);
    color: #fff;
    border-color: var(--sb-primary);
    font-weight: 600;
}
.step-screenshot { display: none; }
.step-screenshot.active { display: block; }
.wizard-section .step-nav + .step-screenshot.active {
    animation: fadeSlideIn 0.3s ease;
}
@keyframes fadeSlideIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* --- Image Zoom Modal --- */
.img-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.85);
    z-index: 10000;
    cursor: zoom-out;
    overflow: auto;
    padding: 20px;
}
.img-modal-overlay.active { display: flex; align-items: flex-start; justify-content: center; }
.img-modal-overlay img {
    max-width: none;
    margin: auto;
    box-shadow: 0 0 40px rgba(0,0,0,0.5);
    border-radius: 4px;
}
.img-modal-info {
    position: fixed;
    bottom: 16px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0,0,0,0.7);
    color: #fff;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 13px;
    z-index: 10001;
    pointer-events: none;
}

/* --- Scale Controls (상단 고정바) --- */
.scale-bar {
    position: sticky;
    top: 0;
    z-index: 900;
    background: var(--sb-card-bg);
    border-bottom: 1px solid var(--sb-border);
    padding: 6px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: var(--sb-text-secondary);
}
.scale-bar label { font-weight: 600; }
.scale-bar input[type=range] { width: 140px; accent-color: var(--sb-primary); }
.scale-bar .scale-value { min-width: 40px; text-align: center; font-weight: 700; color: var(--sb-primary); }
.scale-bar .scale-btn {
    background: var(--sb-bg);
    border: 1px solid var(--sb-border);
    border-radius: 4px;
    padding: 2px 8px;
    cursor: pointer;
    font-size: 12px;
    color: var(--sb-text);
}
.scale-bar .scale-btn:hover { background: var(--sb-primary); color: #fff; }

/* --- Analysis Row --- */
.analysis-row {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 0.75rem;
    font-size: 0.8rem;
    color: var(--sb-text-secondary);
}
.analysis-item {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    background: var(--sb-bg);
    padding: 0.25rem 0.6rem;
    border-radius: 6px;
    border: 1px solid var(--sb-border-light);
}
.analysis-item .analysis-icon {
    font-size: 0.85rem;
    opacity: 0.7;
}
.analysis-item .analysis-value {
    font-weight: 600;
    color: var(--sb-text);
}

/* --- Error Box --- */
.error-box {
    background: #FEF2F2;
    border-left: 4px solid #dc3545;
    border-radius: 0 var(--sb-radius-sm) var(--sb-radius-sm) 0;
    padding: 0.75rem 1rem;
    margin-top: 0.75rem;
    font-size: 0.85rem;
}
[data-theme="dark"] .error-box {
    background: rgba(220,53,69,0.1);
}
.error-box ul {
    margin: 0;
    padding-left: 1.25rem;
}
.error-box li {
    color: #dc3545;
    margin-bottom: 0.25rem;
}
[data-theme="dark"] .error-box li {
    color: #f5a3ab;
}

/* --- Check Results --- */
.check-results {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: var(--sb-text-secondary);
}
.check-results .check-passed {
    color: #198754;
    font-weight: 600;
}
.check-results .check-failed {
    color: #dc3545;
    font-weight: 600;
}

/* --- Appendix Summary Table --- */
.summary-table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0 1.5rem;
    font-size: 0.88rem;
}
.summary-table th,
.summary-table td {
    border: 1px solid var(--sb-border);
    padding: 0.6rem 0.8rem;
    text-align: left;
    vertical-align: middle;
}
.summary-table th {
    background: var(--sb-bg);
    font-weight: 700;
    color: var(--sb-text);
    white-space: nowrap;
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
.summary-table tbody tr:hover {
    background: var(--sb-bg);
}
.summary-table .num-cell {
    text-align: center;
    font-weight: 600;
}
.summary-table .pass-cell { color: #198754; }
.summary-table .warn-cell { color: #cc9a06; }
.summary-table .fail-cell { color: #dc3545; }

/* --- Export Controls --- */
.export-controls {
    max-width: 1400px;
    margin: 2rem auto;
    padding: 0 1.5rem;
}
.export-card {
    border: none;
    border-radius: var(--sb-radius-lg);
    overflow: hidden;
    background: linear-gradient(135deg, var(--sb-primary) 0%, #8B5CF6 50%, var(--sb-accent) 100%);
    color: #fff;
    box-shadow: 0 10px 30px rgba(108,99,255,0.3);
    padding: 2rem 2.5rem;
}
.export-card h3 {
    margin: 0 0 0.5rem;
    font-weight: 700;
    font-size: 1.15rem;
}
.export-card p {
    margin: 0 0 1.25rem;
    opacity: 0.9;
    font-size: 0.9rem;
}
.export-btn-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.export-btn {
    padding: 0.75rem 1.25rem;
    font-size: 0.9rem;
    font-weight: 700;
    border-radius: 10px;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s;
    cursor: pointer;
    min-width: 150px;
    justify-content: center;
}
.export-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
}
.export-btn-pdf {
    background: linear-gradient(135deg, #dc3545, #e74c6f);
    color: #fff;
}
.export-btn-word {
    background: linear-gradient(135deg, #0d6efd, #6610f2);
    color: #fff;
}
.export-btn-print {
    background: rgba(255,255,255,0.15);
    color: #fff;
    border: 2px solid rgba(255,255,255,0.3) !important;
    backdrop-filter: blur(4px);
}
.export-btn-print:hover {
    background: rgba(255,255,255,0.25);
    color: #fff;
}

/* --- Footer --- */
.book-footer {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
    text-align: center;
    font-size: 0.85rem;
    color: var(--sb-text-muted);
    border-top: 1px solid var(--sb-border);
}

/* --- Back to Top --- */
.back-to-top {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 44px;
    height: 44px;
    background: var(--sb-primary);
    color: #fff;
    border: none;
    border-radius: 50%;
    font-size: 1.2rem;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(108,99,255,0.4);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 999;
    transition: opacity 0.3s, transform 0.3s;
}
.back-to-top:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(108,99,255,0.5);
}
.back-to-top.visible {
    display: flex;
}

/* --- Collapse/Expand --- */
.collapse-all-btn {
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.3);
    color: #fff;
    border-radius: 8px;
    padding: 0.35rem 0.75rem;
    cursor: pointer;
    font-size: 0.82rem;
    font-weight: 600;
    transition: background 0.2s;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.collapse-all-btn:hover { background: rgba(255,255,255,0.25); }

.ch-toggle-btn {
    margin-left: auto;
    background: none;
    border: 1px solid var(--sb-border);
    border-radius: 6px;
    padding: 0.2rem 0.5rem;
    cursor: pointer;
    font-size: 0.85rem;
    color: var(--sb-text-muted);
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
.ch-toggle-btn:hover { color: var(--sb-primary); border-color: var(--sb-primary); }

.page-section-header {
    cursor: pointer;
    user-select: none;
}
.page-section-header:hover h3 { color: var(--sb-primary); }
.page-section-header .toggle-icon {
    font-size: 0.9rem;
    color: var(--sb-text-muted);
    transition: transform 0.25s ease;
    margin-left: auto;
    flex-shrink: 0;
}
.page-section.collapsed .page-section-header .toggle-icon { transform: rotate(-90deg); }
.page-section.collapsed .page-body { display: none; }

.book-chapter.collapsed .page-section { display: none; }
.book-chapter.collapsed .ch-toggle-btn .bi { transform: rotate(-90deg); }

/* --- Print Styles --- */
@media print {
    .no-print,
    .book-header,
    .export-controls,
    .back-to-top { display: none !important; }
    body { background: #fff !important; color: #000 !important; font-size: 11pt; }
    .book-cover {
        min-height: auto;
        padding: 80px 40px;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
        page-break-after: always;
    }
    .book-toc { page-break-after: always; }
    .book-chapter { border-top: none; }
    section[id^="chapter-"] { page-break-before: always; }
    .page-section {
        page-break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ccc;
    }
    img { max-width: 100% !important; }
    .screenshot-frame { box-shadow: none; border: 1px solid #ccc; }
    .phone-frame { box-shadow: none; border: 2px solid #ccc; width: 280px !important; }
    .page-two-col { grid-template-columns: 280px 1fr; gap: 1rem; }
    .phone-frame-wrapper { position: static; }
    .debug-note-area { display: none !important; }
    /* 마법사: 인쇄 시 모든 단계 표시 */
    .step-nav { display: none !important; }
    .step-screenshot { display: block !important; margin-bottom: 1rem; }
    .step-screenshot::before {
        content: 'Step ' attr(data-step);
        display: block;
        font-weight: bold;
        margin-bottom: 0.5rem;
        font-size: 10pt;
    }
    .summary-table th, .summary-table td { border-color: #999 !important; }
    .export-card { display: none !important; }
}

/* --- Responsive --- */
@media (max-width: 768px) {
    .book-cover .cover-logo { font-size: 2.5rem; }
    .book-cover .cover-subtitle { font-size: 1.2rem; }
    .book-cover { padding: 3rem 1.25rem; }
    .cover-stats { gap: 0.75rem; }
    .cover-stat-card { min-width: 90px; padding: 0.75rem 1rem; }
    .cover-stat-card .stat-value { font-size: 1.5rem; }
    .book-toc { padding: 2rem 1.25rem; }
    .book-chapter { padding: 2rem 1rem; }
    .page-section { padding: 1rem; }
    .export-card { padding: 1.5rem; }
    .export-btn { min-width: 120px; padding: 0.6rem 0.75rem; font-size: 0.82rem; }
    .analysis-row { gap: 0.5rem; }
    .toc-page-list { margin-left: 1.25rem; }
}
@media (max-width: 480px) {
    .book-header h1 { font-size: 1.1rem; }
    .export-btn-row { flex-direction: column; }
    .export-btn { min-width: 100%; }
}
</style>`;
}


/**
 * 스티키 헤더 생성
 */
function buildHeader(bookData) {
    const title = esc(bookData.title || 'Seoul Beauty 관리자 매뉴얼');
    const subtitle = esc(bookData.subtitle || '');
    const s = bookData.summary || {};
    const dateStr = formatDate(bookData.generatedAt);
    return `
<!-- ========== Sticky Header ========== -->
<header class="book-header no-print">
  <div class="container">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h1><i class="bi bi-book-half"></i> ${title}</h1>
        <div class="header-sub">${subtitle}</div>
        <div class="header-stats">
          생성: ${dateStr} | ${s.total || 0} 페이지 |
          <span style="color:#86efac">${s.pass || 0} PASS</span> /
          <span style="color:#fde68a">${s.warn || 0} WARN</span> /
          <span style="color:#fca5a5">${s.fail || 0} FAIL</span>
        </div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <button class="collapse-all-btn" onclick="toggleAllSections()" title="전체 확장/축소" id="btnCollapseAll">
          <i class="bi bi-arrows-collapse" id="collapseIcon"></i> <span id="collapseLabel">전체 축소</span>
        </button>
        <button class="theme-toggle-btn" onclick="toggleTheme()" title="테마 전환" aria-label="다크/라이트 모드 전환">
          <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
        </button>
      </div>
    </div>
  </div>
</header>`;
}


/**
 * 표지 생성
 */
function buildCover(bookData) {
    const title = esc(bookData.title || 'Seoul Beauty 관리자 매뉴얼');
    const subtitle = esc(bookData.subtitle || '전체 관리 페이지 스크린샷 북');
    const author = esc(bookData.author || 'Seoul Beauty Dev Team');
    const dateStr = formatDate(bookData.generatedAt);
    const s = bookData.summary || {};
    const chapters = bookData.chapters || [];

    return `
<!-- ========== Cover Page ========== -->
<div class="book-cover">
  <div style="position:relative;z-index:1;">
    <div class="cover-logo">${title}</div>
    <div class="cover-subtitle">${subtitle}</div>
    <div class="cover-divider"></div>
    <div class="cover-meta">
      <strong>작성</strong>: ${author}<br>
      <strong>생성일</strong>: ${dateStr}<br>
      <strong>챕터</strong>: ${chapters.length}개 | <strong>페이지</strong>: ${s.total || 0}개
    </div>
    <div class="cover-stats">
      <div class="cover-stat-card">
        <div class="stat-value">${s.total || 0}</div>
        <div class="stat-label">전체</div>
      </div>
      <div class="cover-stat-card" style="border-color:rgba(134,239,172,0.4);">
        <div class="stat-value" style="color:#86efac;">${s.pass || 0}</div>
        <div class="stat-label">PASS</div>
      </div>
      <div class="cover-stat-card" style="border-color:rgba(253,224,71,0.4);">
        <div class="stat-value" style="color:#fde68a;">${s.warn || 0}</div>
        <div class="stat-label">WARN</div>
      </div>
      <div class="cover-stat-card" style="border-color:rgba(252,165,165,0.4);">
        <div class="stat-value" style="color:#fca5a5;">${s.fail || 0}</div>
        <div class="stat-label">FAIL</div>
      </div>
    </div>
    <div class="cover-badge">Seoul Beauty Admin Manual v1.0</div>
  </div>
</div>`;
}


/**
 * 목차(TOC) 생성
 */
function buildTOC(bookData) {
    const chapters = bookData.chapters || [];
    let tocEntries = '';

    chapters.forEach((ch, idx) => {
        const n = idx + 1;
        const stats = chapterStats(ch);
        const pageCount = (ch.pages || []).length;
        const icon = ch.icon || 'bi-folder';

        tocEntries += `
    <div class="toc-chapter">
      <a href="#chapter-${esc(ch.id)}" class="toc-chapter-title" style="text-decoration:none;color:inherit;">
        <span class="toc-ch-num">Ch.${chNum(n)}</span>
        <span class="toc-ch-icon"><i class="bi ${esc(icon)}"></i></span>
        <span>${esc(ch.title)}</span>
        <span class="toc-ch-count">${pageCount}p</span>
      </a>
      <ul class="toc-page-list">`;

        (ch.pages || []).forEach((pg, pIdx) => {
            tocEntries += `
        <li class="toc-page-entry">
          <a href="#page-${esc(pg.key)}">${n}.${pIdx + 1} ${esc(pg.label)}</a>
          <span class="toc-page-badge">${statusBadge(pg.status)}</span>
        </li>`;
        });

        tocEntries += `
      </ul>
    </div>`;
    });

    return `
<!-- ========== Table of Contents ========== -->
<div class="book-toc" id="toc">
  <h2><i class="bi bi-list-ul"></i> 목차</h2>
  ${tocEntries}
</div>`;
}


/**
 * 한 페이지 섹션 HTML 생성
 * @param {Object} page - 페이지 데이터
 * @param {number} chapterNum - 챕터 번호
 * @param {number} pageNum - 페이지 번호 (챕터 내)
 * @param {string} screenshotsRelPath - 스크린샷 상대 경로
 */
function buildPageSection(page, chapterNum, pageNum, screenshotsRelPath) {
    const label = esc(page.label || page.key);
    const description = esc(page.description || '');
    const url = esc(page.url || '');
    const status = page.status || 'warn';
    const ssFile = page.screenshotFile || '';
    const meta = page.meta || {};
    const errors = page.errors || [];
    const checks = page.checkResults || {};

    // 스크린샷 이미지
    let screenshotHTML = '';
    if (ssFile) {
        const ssPath = `${screenshotsRelPath}/${ssFile}`;
        screenshotHTML = `
      <div class="screenshot-frame">
        <img src="${esc(ssPath)}" alt="${label} 스크린샷" loading="lazy">
      </div>`;
    } else {
        screenshotHTML = `
      <div class="screenshot-frame" style="padding:3rem;text-align:center;color:var(--sb-text-muted);">
        <i class="bi bi-image" style="font-size:3rem;opacity:0.3;"></i>
        <p style="margin-top:0.5rem;font-size:0.85rem;">스크린샷 없음</p>
      </div>`;
    }

    // 분석 지표 행
    const analysisItems = [];
    if (meta.linkCount !== undefined) {
        analysisItems.push({ icon: 'bi-link-45deg', label: '링크', value: meta.linkCount });
    }
    if (meta.formCount !== undefined) {
        analysisItems.push({ icon: 'bi-input-cursor-text', label: '폼', value: meta.formCount });
    }
    if (meta.tableCount !== undefined) {
        analysisItems.push({ icon: 'bi-table', label: '테이블', value: meta.tableCount });
    }
    if (meta.buttonCount !== undefined) {
        analysisItems.push({ icon: 'bi-hand-index', label: '버튼', value: meta.buttonCount });
    }
    if (meta.inputCount !== undefined) {
        analysisItems.push({ icon: 'bi-input-cursor', label: '입력', value: meta.inputCount });
    }

    let analysisHTML = '';
    if (analysisItems.length > 0) {
        analysisHTML = `
      <div class="analysis-row">` +
            analysisItems.map((item) =>
                `<span class="analysis-item"><i class="bi ${item.icon} analysis-icon"></i> ${esc(item.label)} <span class="analysis-value">${item.value}</span></span>`
            ).join('') + `
      </div>`;
    }

    // 에러 표시
    let errorHTML = '';
    if (errors.length > 0) {
        errorHTML = `
      <div class="error-box">
        <strong><i class="bi bi-exclamation-triangle"></i> 오류 (${errors.length}건)</strong>
        <ul>${errors.map((e) => `<li>${esc(e)}</li>`).join('')}</ul>
      </div>`;
    }

    // 체크 결과
    let checkHTML = '';
    if (checks.passed !== undefined || checks.failed !== undefined) {
        checkHTML = `
      <div class="check-results">
        <span><i class="bi bi-clipboard-check"></i> 검증:</span>
        <span class="check-passed">${checks.passed || 0} 통과</span>
        <span class="check-failed">${checks.failed || 0} 실패</span>
      </div>`;
    }

    const phoneScreenHTML = ssFile
        ? '<div class="phone-screen"><img src="' + esc(screenshotsRelPath + '/' + ssFile) + '" alt="' + label + '" loading="lazy"></div>'
        : '<div class="phone-screen" style="padding:3rem;text-align:center;color:var(--sb-text-muted);min-height:200px;display:flex;align-items:center;justify-content:center;flex-direction:column;"><i class="bi bi-image" style="font-size:2rem;opacity:0.3;"></i></div>';

    const loadTime = page.loadTime || (meta && meta.loadTime) || 0;
    const httpCode = (meta && meta.httpCode) || (status === 'pass' ? 200 : '?');

    return `
    <!-- Page: ${label} -->
    <div class="page-section" id="page-${esc(page.key)}">
      <div class="page-section-header" onclick="togglePage('page-${esc(page.key)}')">
        <h3>${chapterNum}.${pageNum} ${label} ${statusBadge(status)}</h3>
        <i class="bi bi-chevron-down toggle-icon"></i>
      </div>
      <div class="page-body">
        <div class="page-two-col">
          <div class="phone-frame-wrapper">
            <div class="phone-frame">
              ${phoneScreenHTML}
              <div class="phone-home"></div>
            </div>
          </div>
          <div class="page-info-panel">
            <div class="info-title">${label}</div>
            ${url ? `<div class="info-row"><i class="bi bi-globe"></i> <span class="info-label">URL</span> <code style="font-size:0.8rem;color:var(--sb-text-muted);">${url}</code></div>` : ''}
            <div class="info-row"><i class="bi bi-check-circle"></i> <span class="info-label">Status</span> ${statusBadge(status)} HTTP ${httpCode}</div>
            ${loadTime ? `<div class="info-row"><i class="bi bi-speedometer2"></i> <span class="info-label">Load</span> ${loadTime}ms</div>` : ''}
            ${description ? `<div class="info-desc">${description}</div>` : ''}
            ${analysisHTML}
            ${checkHTML}
            ${errorHTML}
            <div class="debug-note-area">
              <textarea data-page-key="${esc(page.key)}" placeholder="📝 수정 요청을 입력하세요..." onblur="saveDebugNote(this)" oninput="debounceDebugSave(this)"></textarea>
              <div class="save-status" id="save-${esc(page.key)}"></div>
            </div>
          </div>
        </div>
      </div>
    </div>`;
}


/**
 * 챕터 섹션 HTML 생성
 */
function buildChapterSection(chapter, chapterIndex, screenshotsRelPath) {
    const n = chapterIndex + 1;
    const icon = chapter.icon || 'bi-folder';
    const title = esc(chapter.title || `챕터 ${n}`);
    const pages = chapter.pages || [];

    // 마법사 단계 그룹화: wizardKey가 같은 연속 페이지를 하나로 묶음
    const groupedPages = [];
    let currentGroup = null;

    pages.forEach((pg) => {
        if (pg.isStep && pg.wizardKey) {
            if (currentGroup && currentGroup.wizardKey === pg.wizardKey) {
                currentGroup.steps.push(pg);
            } else {
                currentGroup = {
                    type: 'wizard',
                    wizardKey: pg.wizardKey,
                    label: pg.label ? pg.label.split(' - Step ')[0] : pg.key,
                    description: pg.description || '',
                    url: pg.url || '',
                    steps: [pg]
                };
                groupedPages.push(currentGroup);
            }
        } else {
            currentGroup = null;
            groupedPages.push({ type: 'single', page: pg });
        }
    });

    let pagesHTML = '';
    let pageCounter = 0;
    groupedPages.forEach((group) => {
        pageCounter++;
        if (group.type === 'wizard') {
            pagesHTML += buildWizardSection(group, n, pageCounter, screenshotsRelPath);
        } else {
            pagesHTML += buildPageSection(group.page, n, pageCounter, screenshotsRelPath);
        }
    });

    // 실제 표시할 항목 수 (그룹 기준)
    const displayCount = groupedPages.length;

    return `
<!-- ========== Chapter ${chNum(n)}: ${title} ========== -->
<section id="chapter-${esc(chapter.id)}" class="book-chapter">
  <div class="book-chapter-header">
    <span class="ch-icon"><i class="bi ${esc(icon)}"></i></span>
    <h2><span class="ch-num">Ch.${chNum(n)}</span> ${title}</h2>
    <button class="ch-toggle-btn" onclick="toggleChapter('chapter-${esc(chapter.id)}')" title="챕터 접기/펼치기">
      <i class="bi bi-chevron-down"></i> <span>${displayCount}p</span>
    </button>
  </div>
  ${pagesHTML}
</section>`;
}

/**
 * 마법사 단계별 탭 섹션 HTML 생성
 */
function buildWizardSection(group, chapterNum, pageNum, screenshotsRelPath) {
    const label = esc(group.label);
    const description = esc(group.description);
    const url = esc(group.url);
    const wizardId = `wizard-${esc(group.wizardKey)}`;
    const steps = group.steps;

    // 단계 탭 버튼
    const tabsHTML = steps.map((s, i) => {
        const stepLabel = s.stepLabel || `Step ${s.stepNum}`;
        const activeClass = i === 0 ? 'active' : '';
        return `<button class="step-tab ${activeClass}" onclick="showWizardStep('${wizardId}', ${i})">${circleNum(s.stepNum)} ${esc(stepLabel)}</button>`;
    }).join('\n        ');

    // 각 단계 스크린샷
    const stepsHTML = steps.map((s, i) => {
        const ssFile = s.screenshotFile || '';
        const activeClass = i === 0 ? 'active' : '';
        if (ssFile) {
            const ssPath = `${screenshotsRelPath}/${ssFile}`;
            return `
      <div class="step-screenshot ${activeClass}" data-step="${i}">
        <div class="screenshot-frame">
          <img src="${esc(ssPath)}" alt="${esc(s.stepLabel || '')} 스크린샷" loading="lazy">
        </div>
      </div>`;
        }
        return `<div class="step-screenshot ${activeClass}" data-step="${i}"><div class="screenshot-frame" style="padding:3rem;text-align:center;color:var(--sb-text-muted);"><p>스크린샷 없음</p></div></div>`;
    }).join('');

    return `
    <!-- Wizard: ${label} (${steps.length}단계) -->
    <div class="page-section wizard-section" id="${wizardId}">
      <div class="page-section-header" onclick="togglePage('${wizardId}')">
        <h3>${chapterNum}.${pageNum} ${label} <span class="badge bg-info text-white" style="font-size:0.7rem;vertical-align:middle;">${steps.length}단계 마법사</span></h3>
        <i class="bi bi-chevron-down toggle-icon"></i>
      </div>
      <div class="page-body">
      ${description ? `<p class="page-description">${description}</p>` : ''}
      ${url ? `<div class="page-url"><i class="bi bi-globe"></i> ${url}</div>` : ''}
      <div class="step-nav">
        ${tabsHTML}
      </div>
      ${stepsHTML}
      </div>
    </div>`;
}

function circleNum(n) {
    const circles = ['', '\u2460', '\u2461', '\u2462', '\u2463', '\u2464', '\u2465', '\u2466', '\u2467', '\u2468', '\u2469'];
    return circles[n] || n;
}


/**
 * 부록: 테스트 요약 테이블
 */
function buildAppendix(bookData) {
    const chapters = bookData.chapters || [];
    const s = bookData.summary || {};
    const dateStr = formatDate(bookData.generatedAt);

    // 챕터별 집계 행
    let tableRows = '';
    let totalPages = 0;
    let totalPass = 0;
    let totalWarn = 0;
    let totalFail = 0;

    chapters.forEach((ch, idx) => {
        const n = idx + 1;
        const stats = chapterStats(ch);
        const pageCount = (ch.pages || []).length;
        totalPages += pageCount;
        totalPass += stats.pass;
        totalWarn += stats.warn;
        totalFail += stats.fail;

        tableRows += `
        <tr>
          <td style="text-align:center;">${chNum(n)}</td>
          <td><i class="bi ${esc(ch.icon || 'bi-folder')}"></i> ${esc(ch.title)}</td>
          <td class="num-cell">${pageCount}</td>
          <td class="num-cell pass-cell">${stats.pass}</td>
          <td class="num-cell warn-cell">${stats.warn}</td>
          <td class="num-cell fail-cell">${stats.fail}</td>
        </tr>`;
    });

    // 합계 행
    tableRows += `
        <tr style="font-weight:700;background:var(--sb-bg);">
          <td colspan="2" style="text-align:center;">합계</td>
          <td class="num-cell">${totalPages}</td>
          <td class="num-cell pass-cell">${totalPass}</td>
          <td class="num-cell warn-cell">${totalWarn}</td>
          <td class="num-cell fail-cell">${totalFail}</td>
        </tr>`;

    return `
<!-- ========== Appendix: Test Summary ========== -->
<section id="appendix-summary" class="book-chapter">
  <div class="book-chapter-header">
    <span class="ch-icon"><i class="bi bi-bar-chart-line"></i></span>
    <h2><span class="ch-num">부록</span> 테스트 요약</h2>
  </div>

  <h3 style="font-size:1.05rem;font-weight:700;margin-bottom:1rem;padding-left:0.75rem;border-left:3px solid var(--sb-primary);">
    챕터별 결과 집계
  </h3>
  <table class="summary-table">
    <thead>
      <tr>
        <th style="width:60px;text-align:center;">번호</th>
        <th>챕터</th>
        <th style="width:70px;text-align:center;">페이지</th>
        <th style="width:70px;text-align:center;">PASS</th>
        <th style="width:70px;text-align:center;">WARN</th>
        <th style="width:70px;text-align:center;">FAIL</th>
      </tr>
    </thead>
    <tbody>
      ${tableRows}
    </tbody>
  </table>

  <h3 style="font-size:1.05rem;font-weight:700;margin:2rem 0 1rem;padding-left:0.75rem;border-left:3px solid var(--sb-primary);">
    환경 정보
  </h3>
  <table class="summary-table">
    <tbody>
      <tr><td style="width:160px;font-weight:600;">생성 일시</td><td>${dateStr}</td></tr>
      <tr><td style="font-weight:600;">전체 페이지</td><td>${s.total || totalPages}</td></tr>
      <tr><td style="font-weight:600;">결과</td><td><span style="color:#198754;font-weight:600;">${s.pass || totalPass} PASS</span> / <span style="color:#cc9a06;font-weight:600;">${s.warn || totalWarn} WARN</span> / <span style="color:#dc3545;font-weight:600;">${s.fail || totalFail} FAIL</span></td></tr>
      <tr><td style="font-weight:600;">대상 URL</td><td>https://hms.seoulbeauty.app/hairwang/gangnamunni/</td></tr>
      <tr><td style="font-weight:600;">브라우저</td><td>Chromium (Playwright)</td></tr>
      <tr><td style="font-weight:600;">뷰포트</td><td>1280 x 900</td></tr>
      <tr><td style="font-weight:600;">생성 도구</td><td>Seoul Beauty Auto Manual Engine v1.0</td></tr>
    </tbody>
  </table>
</section>`;
}


/**
 * 내보내기 컨트롤 + 푸터 생성
 */
function buildExportAndFooter(bookData, topOnly = false) {
    const exportCard = `
<!-- ========== Export Controls ========== -->
<div class="export-controls no-print">
  <div class="export-card">
    <h3><i class="bi bi-download"></i> 문서 내보내기</h3>
    <p>현재 매뉴얼 북을 PDF, Word, 인쇄 형식으로 내보낼 수 있습니다.</p>
    <div class="export-btn-row">
      <button class="export-btn export-btn-pdf" onclick="exportToPDF()">
        <i class="bi bi-file-earmark-pdf"></i> PDF 저장
      </button>
      <button class="export-btn export-btn-word" onclick="exportToWord()">
        <i class="bi bi-file-earmark-word"></i> Word 저장
      </button>
      <button class="export-btn export-btn-print" onclick="window.print()">
        <i class="bi bi-printer"></i> 인쇄
      </button>
    </div>
  </div>
</div>`;

    if (topOnly) return exportCard;

    const dateStr = formatDate(bookData.generatedAt);
    return `${exportCard}

<!-- ========== Footer ========== -->
<footer class="book-footer no-print">
  <p>Seoul Beauty 관리자 매뉴얼 | 자동 생성: ${dateStr}</p>
  <p style="font-size:0.78rem;color:var(--sb-text-muted);margin-top:0.25rem;">
    Auto Manual Engine v1.0 | &copy; ${new Date().getFullYear()} Seoul Beauty Dev Team
  </p>
</footer>

<!-- Back to Top -->
<button class="back-to-top no-print" id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="맨 위로 이동">
  <i class="bi bi-chevron-up"></i>
</button>`;
}


/**
 * 인라인 JavaScript (테마 전환, PDF/Word 내보내기, 스크롤 이벤트)
 */
function buildScripts() {
    return `
<script>
// ========================================
// 테마 전환
// ========================================
function toggleTheme() {
    var current = document.documentElement.getAttribute('data-theme') || 'light';
    var next = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('sb-manual-theme', next);
    updateThemeIcon(next);
}

function updateThemeIcon(theme) {
    var icon = document.getElementById('themeIcon');
    if (!icon) return;
    icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
}

// 페이지 로드 시 아이콘 동기화
(function() {
    var t = document.documentElement.getAttribute('data-theme') || 'light';
    updateThemeIcon(t);
})();

// ========================================
// PDF 내보내기 (서버사이드 Playwright)
// ========================================
function exportToPDF() {
    var dirMatch = window.location.pathname.match(/\\/preview\\/([^\\/]+)/);
    if (!dirMatch) { alert('PDF 생성에 실패했습니다. 프리뷰 페이지에서만 사용 가능합니다.'); return; }

    var btn = event.target.closest('.export-btn');
    if (btn) btn.innerHTML = '<i class="bi bi-hourglass-split"></i> 서버에서 PDF 생성 중...';

    // 서버에서 Playwright로 정확한 A4 PDF 생성
    var apiBase = window.location.origin + '/auto-manual-api';
    var apiPrefix = window.location.pathname.includes('/api/app-book/') ? '/api/app-book' : '/api/book';
    window.location.href = apiBase + apiPrefix + '/export-pdf/' + dirMatch[1];

    // 5초 후 버튼 복원 (다운로드 시작되면 페이지 안 바뀜)
    setTimeout(function() {
        if (btn) btn.innerHTML = '<i class="bi bi-file-earmark-pdf"></i> PDF 저장';
    }, 5000);
}

// ========================================
// Word 내보내기 (서버 생성 DOCX 다운로드)
// ========================================
function exportToWord() {
    var dirMatch = window.location.pathname.match(/\\/preview\\/([^\\/]+)/);
    if (!dirMatch) { alert('Word 다운로드에 실패했습니다.'); return; }

    var btn = event.target.closest('.export-btn');
    if (btn) btn.innerHTML = '<i class="bi bi-hourglass-split"></i> 다운로드 중...';

    var apiBase = window.location.origin + '/auto-manual-api';
    var apiPrefix = window.location.pathname.includes('/api/app-book/') ? '/api/app-book' : '/api/book';
    window.location.href = apiBase + apiPrefix + '/export-word/' + dirMatch[1];

    setTimeout(function() {
        if (btn) btn.innerHTML = '<i class="bi bi-file-earmark-word"></i> Word 저장';
    }, 3000);
}

// ========================================
// 섹션 확장/축소
// ========================================
function togglePage(pageId) {
    var el = document.getElementById(pageId);
    if (!el) return;
    el.classList.toggle('collapsed');
}

function toggleChapter(chapterId) {
    var el = document.getElementById(chapterId);
    if (!el) return;
    el.classList.toggle('collapsed');
}

// 마법사 단계 탭 전환
function showWizardStep(wizardId, stepIdx) {
    var container = document.getElementById(wizardId);
    if (!container) return;
    // 탭 활성화
    var tabs = container.querySelectorAll('.step-tab');
    tabs.forEach(function(t, i) {
        t.classList.toggle('active', i === stepIdx);
    });
    // 스크린샷 표시
    var screenshots = container.querySelectorAll('.step-screenshot');
    screenshots.forEach(function(s, i) {
        s.classList.toggle('active', i === stepIdx);
    });
    console.log('[Manual Book] Wizard step:', wizardId, 'step', stepIdx + 1);
}

var _allCollapsed = false;
function toggleAllSections() {
    _allCollapsed = !_allCollapsed;
    var pages = document.querySelectorAll('.page-section');
    var chapters = document.querySelectorAll('.book-chapter');
    for (var i = 0; i < pages.length; i++) {
        if (_allCollapsed) pages[i].classList.add('collapsed');
        else pages[i].classList.remove('collapsed');
    }
    for (var j = 0; j < chapters.length; j++) {
        if (_allCollapsed) chapters[j].classList.add('collapsed');
        else chapters[j].classList.remove('collapsed');
    }
    var icon = document.getElementById('collapseIcon');
    var label = document.getElementById('collapseLabel');
    if (icon) icon.className = _allCollapsed ? 'bi bi-arrows-expand' : 'bi bi-arrows-collapse';
    if (label) label.textContent = _allCollapsed ? '전체 확장' : '전체 축소';
}

// ========================================
// Back to Top 버튼 표시/숨김
// ========================================
(function() {
    var btn = document.getElementById('backToTop');
    if (!btn) return;
    window.addEventListener('scroll', function() {
        if (window.scrollY > 400) {
            btn.classList.add('visible');
        } else {
            btn.classList.remove('visible');
        }
    }, { passive: true });
})();

// ========================================
// 이미지 클릭 확대 모달
// ========================================
(function() {
    document.addEventListener('click', function(e) {
        var img = e.target.closest('.screenshot-frame img');
        if (!img) return;
        var modal = document.getElementById('imgModal');
        var modalImg = document.getElementById('imgModalImg');
        var info = document.getElementById('imgModalInfo');
        modalImg.src = img.src;
        modal.classList.add('active');
        // 원본 크기 정보
        var natW = img.naturalWidth;
        var natH = img.naturalHeight;
        info.textContent = natW + ' x ' + natH + 'px (원본)';
        info.style.display = 'block';
        console.log('[Image] Opened:', img.src.split('/').pop(), natW + 'x' + natH);
    });
})();

function closeImgModal() {
    document.getElementById('imgModal').classList.remove('active');
    document.getElementById('imgModalInfo').style.display = 'none';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeImgModal();
});

// ========================================
// 이미지 배율 조절
// ========================================
var _phoneSize = 300;
function setPhoneSize(val) {
    val = parseInt(val);
    if (isNaN(val) || val < 200) val = 200;
    if (val > 500) val = 500;
    _phoneSize = val;
    var slider = document.getElementById('phoneSizeSlider');
    if (slider) slider.value = val;
    var display = document.getElementById('phoneSizeValue');
    if (display) display.textContent = val + 'px';
    var frames = document.querySelectorAll('.phone-frame');
    for (var i = 0; i < frames.length; i++) {
        frames[i].style.width = val + 'px';
    }
    console.log('[PhoneSize] Set to', val + 'px');
}

// ========================================
// 디버그 모드
// ========================================
function toggleDebugMode(on) {
    if (on) {
        document.body.classList.add('debug-mode-on');
    } else {
        document.body.classList.remove('debug-mode-on');
    }
    var btn = document.getElementById('promptBtn');
    if (btn) btn.style.display = on ? 'inline-block' : 'none';
    loadDebugNotes();
    console.log('[Debug] mode', on ? 'ON' : 'OFF');
}

var _debugSaveTimers = {};
function debounceDebugSave(textarea) {
    var key = textarea.getAttribute('data-page-key');
    if (_debugSaveTimers[key]) clearTimeout(_debugSaveTimers[key]);
    _debugSaveTimers[key] = setTimeout(function() { saveDebugNote(textarea); }, 2000);
}

function saveDebugNote(textarea) {
    var key = textarea.getAttribute('data-page-key');
    var val = textarea.value.trim();
    var storageKey = 'debug_notes_' + getDirFromUrl();
    var notes = {};
    try { notes = JSON.parse(localStorage.getItem(storageKey) || '{}'); } catch(e) {}
    if (val) { notes[key] = val; } else { delete notes[key]; }
    localStorage.setItem(storageKey, JSON.stringify(notes));
    var statusEl = document.getElementById('save-' + key);
    if (statusEl) {
        statusEl.textContent = val ? '저장됨 ' + new Date().toLocaleTimeString() : '';
    }
}

function loadDebugNotes() {
    var storageKey = 'debug_notes_' + getDirFromUrl();
    var notes = {};
    try { notes = JSON.parse(localStorage.getItem(storageKey) || '{}'); } catch(e) {}
    var textareas = document.querySelectorAll('.debug-note-area textarea');
    for (var i = 0; i < textareas.length; i++) {
        var key = textareas[i].getAttribute('data-page-key');
        if (notes[key]) textareas[i].value = notes[key];
    }
}

function getDirFromUrl() {
    var path = window.location.pathname;
    var parts = path.split('/');
    parts.pop();
    return parts.join('/');
}

function generatePrompt() {
    var storageKey = 'debug_notes_' + getDirFromUrl();
    var notes = {};
    try { notes = JSON.parse(localStorage.getItem(storageKey) || '{}'); } catch(e) {}
    var keys = Object.keys(notes);
    if (keys.length === 0) {
        alert('수정 요청이 없습니다. 디버그 모드에서 텍스트를 입력하세요.');
        return;
    }
    var lines = ['## 수정 요청 사항\n'];
    lines.push('아래 페이지들에 대해 수정을 요청합니다:\n');
    for (var i = 0; i < keys.length; i++) {
        var section = document.getElementById('page-' + keys[i]);
        var title = keys[i];
        if (section) {
            var h3 = section.querySelector('h3');
            if (h3) title = h3.textContent.trim();
        }
        lines.push('### ' + (i + 1) + '. ' + title);
        lines.push('- **페이지 키**: \`' + keys[i] + '\`');
        lines.push('- **수정 내용**: ' + notes[keys[i]]);
        lines.push('');
    }
    lines.push('---');
    lines.push('총 ' + keys.length + '건의 수정 요청입니다. 각 항목을 순서대로 처리해주세요.');
    var prompt = lines.join('\n');
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(prompt).then(function() {
            alert('프롬프트가 클립보드에 복사되었습니다! (' + keys.length + '건)');
        });
    } else {
        var ta = document.createElement('textarea');
        ta.value = prompt;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        alert('프롬프트가 클립보드에 복사되었습니다! (' + keys.length + '건)');
    }
}

// ========================================
// 콘솔 디버깅 정보
// ========================================
(function() {
    var sections = document.querySelectorAll('.page-section');
    var images = document.querySelectorAll('.screenshot-frame img');
    var chapters = document.querySelectorAll('.book-chapter');
    console.group('%c[Seoul Beauty Manual Book]', 'color:#6C63FF;font-weight:bold;font-size:14px;');
    console.log('Chapters:', chapters.length);
    console.log('Sections:', sections.length);
    console.log('Images:', images.length);
    // 이미지 로드 상태 체크
    var loaded = 0, failed = 0;
    images.forEach(function(img) {
        if (img.complete && img.naturalHeight > 0) loaded++;
        else failed++;
    });
    console.log('Images loaded:', loaded, '/ failed:', failed);
    // 각 섹션 정보
    sections.forEach(function(s, idx) {
        var h3 = s.querySelector('h3');
        var img = s.querySelector('.screenshot-frame img');
        var imgInfo = img ? (img.complete ? img.naturalWidth + 'x' + img.naturalHeight : 'loading...') : 'no image';
        console.log('  [' + (idx+1) + '] ' + (h3 ? h3.textContent.trim() : 'untitled') + ' — ' + imgInfo);
    });
    console.groupEnd();
})();
</script>`;
}


// =========================================================
// 메인 함수: renderBookHTML
// =========================================================

/**
 * bookData와 스크린샷 상대경로를 받아
 * 자체 완결형 HTML 문서 문자열을 반환한다.
 *
 * @param {Object} bookData - 캡처 결과 데이터 (chapters, summary, title 등)
 * @param {string} [screenshotsRelPath='screenshots'] - 스크린샷 디렉토리 상대 경로
 * @returns {string} - 완전한 HTML 문서 문자열
 */
function renderBookHTML(bookData, screenshotsRelPath) {
    if (!bookData) {
        throw new Error('renderBookHTML: bookData는 필수 인자입니다.');
    }

    // 기본값 처리
    screenshotsRelPath = screenshotsRelPath || 'screenshots';
    bookData.title = bookData.title || 'Seoul Beauty 관리자 매뉴얼';
    bookData.subtitle = bookData.subtitle || '전체 관리 페이지 스크린샷 북';
    bookData.author = bookData.author || 'Seoul Beauty Dev Team';
    bookData.summary = bookData.summary || {};
    bookData.chapters = bookData.chapters || [];

    // summary의 total이 없으면 계산
    if (bookData.summary.total === undefined) {
        let total = 0, pass = 0, warn = 0, fail = 0;
        bookData.chapters.forEach((ch) => {
            (ch.pages || []).forEach((pg) => {
                total++;
                const s = pg.status || 'warn';
                if (s === 'pass') pass++;
                else if (s === 'fail') fail++;
                else warn++;
            });
        });
        bookData.summary.total = total;
        if (bookData.summary.pass === undefined) bookData.summary.pass = pass;
        if (bookData.summary.warn === undefined) bookData.summary.warn = warn;
        if (bookData.summary.fail === undefined) bookData.summary.fail = fail;
    }

    // 챕터 섹션 빌드
    const chaptersHTML = bookData.chapters
        .map((ch, idx) => buildChapterSection(ch, idx, screenshotsRelPath))
        .join('\n');

    // 전체 HTML 조립
    const html = `${buildHead(bookData)}
<body>

${buildHeader(bookData)}

<!-- 배율 조절 바 -->
<div class="scale-bar no-print" id="scaleBar">
    <label><i class="bi bi-phone"></i> 폰 프레임</label>
    <button class="scale-btn" onclick="setPhoneSize(260)" title="소형">S</button>
    <button class="scale-btn" onclick="setPhoneSize(300)" title="기본" style="font-weight:700;">M</button>
    <button class="scale-btn" onclick="setPhoneSize(360)" title="대형">L</button>
    <button class="scale-btn" onclick="setPhoneSize(420)" title="태블릿">XL</button>
    <input type="range" id="phoneSizeSlider" min="200" max="500" value="300" oninput="setPhoneSize(this.value)">
    <span class="scale-value" id="phoneSizeValue">300px</span>
    <span style="border-left:1px solid var(--sb-border);height:20px;margin:0 8px;"></span>
    <label class="debug-toggle-label"><input type="checkbox" id="debugToggle" onchange="toggleDebugMode(this.checked)"> 디버그</label>
    <button class="debug-prompt-btn" id="promptBtn" style="display:none;" onclick="generatePrompt()"><i class="bi bi-robot"></i> 프롬프트 생성</button>
    <span style="margin-left:auto;font-size:12px;opacity:0.6;">클릭하면 원본 크기로 확대</span>
</div>

<!-- 이미지 확대 모달 -->
<div class="img-modal-overlay" id="imgModal" onclick="closeImgModal()">
    <img id="imgModalImg" src="" alt="">
</div>
<div class="img-modal-info" id="imgModalInfo" style="display:none;"></div>

<main id="bookMain">

${buildCover(bookData)}

${buildExportAndFooter(bookData, true)}

${buildTOC(bookData)}

${chaptersHTML}

${buildAppendix(bookData)}

</main>

${buildExportAndFooter(bookData)}

${buildScripts()}

</body>
</html>`;

    return html;
}


// =========================================================
// Module Export
// =========================================================

module.exports = { renderBookHTML };
