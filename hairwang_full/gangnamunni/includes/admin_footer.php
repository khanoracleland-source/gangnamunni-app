</div><!-- /.admin-main -->
<?php
$admin_fast_local = getenv('GANGNAMUNNI_FAST_LOCAL') === '1'
    || (getenv('GANGNAMUNNI_FAST_LOCAL') !== '0' && getenv('G5_MYSQL_HOST') === 'mysql');
?>

<!-- Bootstrap 5.3 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.ADMIN_FAST_LOCAL = <?php echo $admin_fast_local ? 'true' : 'false'; ?>;

// 주간/야간 모드 토글
(function() {
    const html = document.documentElement;
    const toggleBtn = document.getElementById('themeToggle');
    const icon = document.getElementById('themeIcon');
    const saved = localStorage.getItem('admin_theme') || 'light';

    html.setAttribute('data-bs-theme', saved);
    updateIcon(saved);

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            const current = html.getAttribute('data-bs-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-bs-theme', next);
            localStorage.setItem('admin_theme', next);
            updateIcon(next);
        });
    }

    function updateIcon(theme) {
        if (icon) {
            icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        }
        var label = document.getElementById('themeLabel');
        if (label) {
            label.textContent = theme === 'dark' ? '주간' : '야간';
        }
    }
})();

// 사이드바 토글 (모바일) + 그룹 확장 상태 유지
(function() {
    const btn = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('adminSidebar');

    // 사이드바 토글 (모바일 전용)
    if (btn && sidebar) {
        btn.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
        // 사이드바 외부 클릭 시 닫기
        document.addEventListener('click', function(e) {
            if (window.innerWidth < 992 && sidebar.classList.contains('show')) {
                if (!sidebar.contains(e.target) && !btn.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
    }

    // ===== 사이드바 그룹 확장 상태 + 전체 확장/축소 모드 유지 로직 =====
    // 열린 그룹을 localStorage 에 저장하여, 페이지 이동 후에도 그대로 펼쳐지도록 한다.
    var storageKey = 'admin_sidebar_open_groups'; // 열린 그룹 key 배열을 저장하는 키(한글 주석)
    var storageKeyMode = 'admin_sidebar_expand_mode'; // 전체 확장/축소 모드 저장 키(한글 주석)

    if (!sidebar) return;

    // 저장된 그룹 key 배열을 불러오는 함수
    function loadOpenGroups() {
        try {
            var raw = localStorage.getItem(storageKey);
            if (!raw) return [];
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            console.warn('사이드바 그룹 상태 로드 실패:', e);
            return [];
        }
    }

    // 그룹 key 배열을 localStorage 에 저장하는 함수
    function saveOpenGroups(keys) {
        try {
            localStorage.setItem(storageKey, JSON.stringify(keys));
        } catch (e) {
            console.warn('사이드바 그룹 상태 저장 실패:', e);
        }
    }

    // collapse id (예: grp_content) 에서 실제 그룹 key (content) 를 추출
    function getGroupKeyFromId(id) {
        if (!id) return null;
        if (id.indexOf('grp_') === 0) return id.substring(4);
        return id;
    }

    // 전체 확장/축소 모드를 불러오는 함수 (기본값: 전체 확장)
    function loadExpandMode() {
        try {
            var m = localStorage.getItem(storageKeyMode);
            return m || 'custom';
        } catch (e) {
            console.warn('사이드바 확장 모드 로드 실패:', e);
            return 'expand_all';
        }
    }

    // 전체 확장/축소 모드를 저장하는 함수
    function saveExpandMode(mode) {
        try {
            localStorage.setItem(storageKeyMode, mode);
        } catch (e) {
            console.warn('사이드바 확장 모드 저장 실패:', e);
        }
    }

    var openGroups = loadOpenGroups(); // 초기 열린 그룹 목록
    var expandMode = loadExpandMode(); // 현재 전체 확장/축소 모드

    // data-bs-toggle="collapse" 가 붙은 그룹 헤더 링크들을 순회
    var groupLinks = sidebar.querySelectorAll('a.nav-link[data-bs-toggle="collapse"]');
    groupLinks.forEach(function(link) {
        var target = link.getAttribute('href') || link.getAttribute('data-bs-target');
        if (!target || target.charAt(0) !== '#') return;

        var collapseId = target.substring(1);
        var collapseEl = document.getElementById(collapseId);
        if (!collapseEl) return;

        var groupKey = getGroupKeyFromId(collapseId);
        if (!groupKey) return;

        // 전체 확장/축소 모드에 따라 초기 상태 결정
        if (false) {
            // expand_all 비활성화
        } else if (expandMode === 'collapse_all') {
            // 전체 접기 모드: 모든 그룹을 닫음
            collapseEl.classList.remove('show');
            link.setAttribute('aria-expanded', 'false');
        } else {
            // 커스텀 모드: 저장된 목록에 있으면 펼침
            if (openGroups.indexOf(groupKey) !== -1) {
                collapseEl.classList.add('show');
                link.setAttribute('aria-expanded', 'true');
            }
        }

        // 그룹 헤더 클릭으로 접을 때만 플래그 설정 (캡처: 서브메뉴 클릭 등으로 인한 hidden 과 구분) (한글 주석)
        link.addEventListener('click', function() {
            if (collapseEl.classList.contains('show')) {
                collapseEl.dataset.sbUserHeaderCollapse = '1';
            } else {
                delete collapseEl.dataset.sbUserHeaderCollapse;
            }
        }, true);

        // Bootstrap collapse 이벤트를 사용해 열린/닫힌 상태를 저장
        collapseEl.addEventListener('shown.bs.collapse', function() {
            var keys = loadOpenGroups();
            if (keys.indexOf(groupKey) === -1) {
                keys.push(groupKey);
                saveOpenGroups(keys);
            }
        });

        collapseEl.addEventListener('hidden.bs.collapse', function() {
            // 사용자가 그룹 헤더를 직접 눌러서 접은 경우인지 구분 (한글 주석)
            var userHeaderCollapse = collapseEl.dataset.sbUserHeaderCollapse === '1';
            delete collapseEl.dataset.sbUserHeaderCollapse;

            // 서브메뉴 클릭/SPA 전환 등 헤더를 직접 누르지 않은 hidden 이벤트는 무시 —
            // 열림 목록을 건드리지 않아 그룹이 임의로 축소/저장되는 현상을 막는다. (한글 주석)
            if (!userHeaderCollapse) {
                return;
            }

            // 전체 접기(sbCollapseAll) 등으로 닫힐 때는 custom 으로 바꾸지 않고 종료 (한글 주석)
            if (loadExpandMode() === 'collapse_all') {
                return;
            }

            // 사용자가 헤더로 직접 접음 → 커스텀 모드 + 열림 목록에서 제거 (한글 주석)
            saveExpandMode('custom');
            expandMode = 'custom';
            applyModeStyles('custom');

            var keys = loadOpenGroups();
            var idx = keys.indexOf(groupKey);
            if (idx !== -1) {
                keys.splice(idx, 1);
                saveOpenGroups(keys);
            }
        });
    });

    // [현재 페이지 그룹 자동 펼침] 직접 URL 진입 시(예: admin_reviews.php) active 항목이 속한
    // 그룹은 localStorage 복원 결과와 무관하게 항상 펼친다. 전체 접기 모드여도 현재 그룹만 예외로 연다. (한글 주석)
    sidebar.querySelectorAll('.collapse .nav-link.active').forEach(function(activeLink) {
        var grp = activeLink.closest('.collapse');
        if (!grp) return;
        if (!grp.classList.contains('show')) {
            grp.classList.add('show');
        }
        var hdr = sidebar.querySelector('[href="#' + grp.id + '"]');
        if (hdr) {
            hdr.setAttribute('aria-expanded', 'true');
            hdr.classList.add('fw-bold');
        }
        // 현재 그룹은 열린 목록에도 반영해 다음 이동 때도 유지 (한글 주석)
        var gKey = getGroupKeyFromId(grp.id);
        if (gKey) {
            var ks = loadOpenGroups();
            if (ks.indexOf(gKey) === -1) { ks.push(gKey); saveOpenGroups(ks); }
        }
    });

    // 초기 openGroups 상태를 모드에 맞춰 한 번 저장
    if (expandMode === 'expand_all') {
        saveOpenGroups(openGroups);
    } else if (expandMode === 'collapse_all') {
        saveOpenGroups([]);
    }

    // 전체 확장/축소 버튼 핸들러
    var expandBtn = document.getElementById('sidebarExpandAllBtn');   // 전체 펼치기 버튼 DOM (한글 주석)
    var collapseBtn = document.getElementById('sidebarCollapseAllBtn'); // 전체 접기 버튼 DOM (한글 주석)

    function applyModeStyles(mode) {
        if (!expandBtn || !collapseBtn) return;
        if (false && mode === 'expand_all') {
            expandBtn.classList.remove('btn-outline-secondary');
            expandBtn.classList.add('btn-primary');
            collapseBtn.classList.remove('btn-primary');
            collapseBtn.classList.add('btn-outline-secondary');
        } else if (mode === 'collapse_all') {
            collapseBtn.classList.remove('btn-outline-secondary');
            collapseBtn.classList.add('btn-primary');
            expandBtn.classList.remove('btn-primary');
            expandBtn.classList.add('btn-outline-secondary');
        } else {
            // 커스텀 모드일 때는 둘 다 outline 으로 표시
            expandBtn.classList.remove('btn-primary');
            collapseBtn.classList.remove('btn-primary');
            expandBtn.classList.add('btn-outline-secondary');
            collapseBtn.classList.add('btn-outline-secondary');
        }
    }

    function setExpandMode(mode) {
        expandMode = mode;
        saveExpandMode(mode);

        var keys = [];

        groupLinks.forEach(function(link) {
            var target = link.getAttribute('href') || link.getAttribute('data-bs-target');
            if (!target || target.charAt(0) !== '#') return;
            var collapseId = target.substring(1);
            var collapseEl = document.getElementById(collapseId);
            if (!collapseEl) return;
            var groupKey = getGroupKeyFromId(collapseId);
            if (!groupKey) return;

            if (false && mode === 'expand_all') {
                collapseEl.classList.add('show');
                link.setAttribute('aria-expanded', 'true');
                keys.push(groupKey);
            } else if (mode === 'collapse_all') {
                collapseEl.classList.remove('show');
                link.setAttribute('aria-expanded', 'false');
            }
        });

        if (false && mode === 'expand_all') {
            saveOpenGroups(keys);
        } else if (mode === 'collapse_all') {
            saveOpenGroups([]);
        }

        applyModeStyles(mode);
    }

    // 초기 버튼 스타일 적용
    applyModeStyles(expandMode);

    if (expandBtn) {
        expandBtn.addEventListener('click', function() {
            setExpandMode('expand_all');
        });
    }
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function() {
            setExpandMode('collapse_all');
        });
    }
})();

// 전체 선택 체크박스
function toggleSelectAll(source) {
    var checkboxes = document.querySelectorAll('.row-check');
    checkboxes.forEach(function(cb) { cb.checked = source.checked; });
    updateBulkActions();
}

function updateBulkActions() {
    var checked = document.querySelectorAll('.row-check:checked').length;
    var bulkBar = document.getElementById('bulkActions');
    if (bulkBar) {
        bulkBar.style.display = checked > 0 ? 'flex' : 'none';
        var countEl = document.getElementById('selectedCount');
        if (countEl) countEl.textContent = checked;
    }
}

// 테이블 검색 필터 (클라이언트 사이드)
function filterTable(inputId, tableId) {
    var input = document.getElementById(inputId);
    var table = document.getElementById(tableId);
    if (!input || !table) return;

    var filter = input.value.toLowerCase();
    var rows = table.querySelectorAll('tbody tr');
    rows.forEach(function(row) {
        var text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}

// 페이지당 행수 변경
function changePerPage(selectEl) {
    var url = new URL(window.location.href);
    url.searchParams.set('per_page', selectEl.value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

// 캐시 초기화 기능
(function() {
    var clearBtn = document.getElementById('clearCacheBtn');
    if (!clearBtn) return;

    clearBtn.addEventListener('click', function() {
        if (!confirm('브라우저 및 서버 캐시를 모두 초기화하시겠습니까?\n페이지가 자동으로 새로고침됩니다.')) {
            return;
        }

        var icon = this.querySelector('i');
        var originalClass = icon.className;
        icon.className = 'bi bi-arrow-clockwise spinner-border spinner-border-sm';
        this.disabled = true;

        // 1. 브라우저 캐시 클리어
        try {
            // localStorage 클리어 (테마 제외)
            var theme = localStorage.getItem('admin_theme');
            localStorage.clear();
            if (theme) localStorage.setItem('admin_theme', theme);

            // sessionStorage 클리어
            sessionStorage.clear();

            // Cache API 클리어 (Service Worker 캐시)
            if ('caches' in window) {
                caches.keys().then(function(names) {
                    names.forEach(function(name) {
                        caches.delete(name);
                    });
                });
            }

            console.log('브라우저 캐시 클리어 완료');
        } catch (e) {
            console.error('브라우저 캐시 클리어 오류:', e);
        }

        // 2. 서버 캐시 클리어 (AJAX)
        fetch('clear_cache.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=clear_cache'
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            console.log('서버 응답:', data);
            if (data.success) {
                alert('캐시 초기화 완료!\n페이지를 새로고침합니다.');
                // 강제 리로드 (캐시 무시)
                window.location.reload(true);
            } else {
                alert('서버 캐시 클리어 실패: ' + (data.message || '알 수 없는 오류'));
                icon.className = originalClass;
                clearBtn.disabled = false;
            }
        })
        .catch(function(error) {
            console.error('AJAX 오류:', error);
            alert('서버와 통신 중 오류가 발생했습니다.\n브라우저 캐시만 클리어하고 새로고침합니다.');
            window.location.reload(true);
        });
    });
})();

// ===== 좌측 메뉴 부드러운 전환 (우측 컨텐츠만 로딩) =====
(function() {
    var sidebar = document.getElementById('adminSidebar'); // 관리자 좌측 사이드바 DOM (한글 주석)
    var main = document.querySelector('.admin-main');      // 우측 메인 컨텐츠 영역 DOM (한글 주석)
    if (!sidebar || !main) return;

    // ===== 사이드바 스크롤 위치 유지 =====
    var SCROLL_KEY = 'admin_sidebar_scroll'; // 사이드바 스크롤 위치 저장 키 (한글 주석)

    // 저장된 스크롤 위치 복원
    try {
        var savedScroll = localStorage.getItem(SCROLL_KEY);
        if (savedScroll !== null) {
            sidebar.scrollTop = parseInt(savedScroll, 10) || 0;
        }
    } catch (e) {
        console.warn('사이드바 스크롤 복원 실패:', e);
    }

    // 스크롤 이벤트로 현재 위치를 저장 (부하를 줄이기 위해 간단한 디바운스 사용)
    var scrollSaveTimer = null;
    sidebar.addEventListener('scroll', function() {
        if (scrollSaveTimer) clearTimeout(scrollSaveTimer);
        scrollSaveTimer = setTimeout(function() {
            try {
                localStorage.setItem(SCROLL_KEY, String(sidebar.scrollTop));
            } catch (e) {
                console.warn('사이드바 스크롤 저장 실패:', e);
            }
        }, 100);
    });

    // 우측만 교체할 때 opacity 등 시각 효과를 주지 않음 — 좌측 메뉴와 함께 "깜빡이는" 느낌 방지 (한글 주석)
    function showMainLoading() {}

    function hideMainLoading() {}

    // 관리자 레이아웃 모바일 구간과 동일 (한글 주석)
    function isAdminMobileViewport() {
        return window.innerWidth < 992;
    }

    // 같은 사이트의 정적 HTML 리포트 링크인지 (한글 주석)
    function isSameOriginHtmlReportHref(href) {
        if (!href || href.charAt(0) === '#') return false;
        if (/^https?:\/\//i.test(href)) return false;
        try {
            var u = new URL(href, window.location.href);
            if (u.origin !== window.location.origin) return false;
            return /\.html($|[?#])/i.test(u.pathname);
        } catch (err) {
            return false;
        }
    }

    // 상단 관리자와 동일한 테마 값 (data-bs-theme 우선) (한글 주석)
    function getAdminTheme() {
        var t = document.documentElement.getAttribute('data-bs-theme');
        if (t === 'dark' || t === 'light') return t;
        try {
            return localStorage.getItem('admin_theme') || 'light';
        } catch (e) {
            return 'light';
        }
    }

    // iframe 내부 리포트에 야간/주간 모드 반영 (부모와 불일치 방지) (한글 주석)
    function syncReportIframeTheme(frame) {
        if (!frame) return;
        var theme = getAdminTheme();
        try {
            var doc = frame.contentDocument;
            if (!doc || !doc.documentElement) return;
            doc.documentElement.setAttribute('data-bs-theme', theme);
            doc.documentElement.setAttribute('data-theme', theme);
            try {
                if (frame.contentWindow && frame.contentWindow.localStorage) {
                    frame.contentWindow.localStorage.setItem('sb-manual-theme', theme);
                }
            } catch (e2) {}
            var id = 'admin-iframe-theme-sync';
            var old = doc.getElementById(id);
            if (old) old.remove();
            if (theme !== 'dark') {
                doc.documentElement.style.colorScheme = '';
                if (doc.body) doc.body.style.colorScheme = '';
                return;
            }
            doc.documentElement.style.colorScheme = 'dark';
            if (doc.body) doc.body.style.colorScheme = 'dark';
            var s = doc.createElement('style');
            s.id = id;
            s.textContent = [
                ':root, html {',
                '  --bg: #212529 !important;',
                '  --card: #2b3035 !important;',
                '  --surface: #2b3035 !important;',
                '  --text: #e9ecef !important;',
                '  --text2: #adb5bd !important;',
                '  --muted: #adb5bd !important;',
                '  --text-muted: #adb5bd !important;',
                '  --border: #495057 !important;',
                '  --accent: #a78bfa !important;',
                '  --pri: #a78bfa !important;',
                '  --primary: #8b9cf7 !important;',
                '  --ok: #4ade80 !important;',
                '  --fail: #f87171 !important;',
                '  --warn: #fbbf24 !important;',
                '  --fix: #60a5fa !important;',
                '  --app: #22d3ee !important;',
                '}',
                'body { background: var(--bg) !important; color: var(--text) !important; }',
                '.tabs, .controls { background-color: var(--card) !important; color: var(--text) !important; }',
                '.tab-btn { color: var(--text2) !important; }',
                '.controls input, .controls select { background: var(--card) !important; color: var(--text) !important; border-color: var(--border) !important; }',
                'tr:nth-child(even) { background: rgba(255,255,255,0.04) !important; }',
                '.prog-bar-bg { background: var(--border) !important; }',
                '.file-item { border-bottom-color: var(--border) !important; }',
                '.stat-card, .screenshot-card, table, .file-list, .summary, .progress-section { background: var(--card) !important; border-color: var(--border) !important; color: var(--text) !important; }',
                'td, th { border-color: var(--border) !important; }',
                'h1, h2, h3, h4, h5, .section-title { color: var(--text) !important; }',
                '.subtitle, .stat-label, .sb-subtitle { color: var(--muted) !important; }',
            ].join('\n');
            doc.head.appendChild(s);
        } catch (err) {
            console.warn('리포트 iframe 테마 동기화 실패:', err);
        }
    }

    // 우측 패널에 HTML 리포트 iframe 표시 (한글 주석)
    function loadHtmlReportInMain(href) {
        var safe = String(href).replace(/[<>"']/g, '');
        main.innerHTML =
            '<div class="card border-0 shadow-sm admin-qa-report-wrap">' +
            '<div class="card-body p-0" style="min-height:calc(100vh - 100px);">' +
            '<iframe class="admin-qa-report-frame" title="리포트"></iframe>' +
            '</div></div>';
        var frame = main.querySelector('.admin-qa-report-frame');
        if (frame) {
            frame.addEventListener('load', function onReportFrameLoad() {
                syncReportIframeTheme(frame);
            });
            frame.setAttribute('src', safe);
        }
    }

    // 관리자 테마 토글 시 열려 있는 리포트 iframe 도 맞춤 (한글 주석)
    if (!window.__adminReportThemeObserver) {
        window.__adminReportThemeObserver = true;
        new MutationObserver(function() {
            var f = document.querySelector('.admin-qa-report-frame');
            if (f) syncReportIframeTheme(f);
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
    }

    // 레이아웃·collapse 적용 후 사이드바 스크롤을 다시 맞춤 (한글 주석)
    function restoreSidebarScrollAfterNav() {
        try {
            var y = localStorage.getItem(SCROLL_KEY);
            if (y === null) return;
            var top = parseInt(y, 10) || 0;
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    sidebar.scrollTop = top;
                });
            });
        } catch (err) {
            console.warn('사이드바 스크롤 재적용 실패:', err);
        }
    }

    // 응답 HTML에서 .admin-main 부분만 추출하는 함수
    function extractMainContent(htmlText) {
        var parser = new DOMParser();
        var doc = parser.parseFromString(htmlText, 'text/html');
        var newMain = doc.querySelector('.admin-main');
        return newMain ? newMain.innerHTML : null;
    }

    // .admin-main 내부 + 푸터의 #adminPageExtraJs 만 실행 (전체 document script 는 푸터·Bootstrap 중복 실행 방지) (한글 주석)
    function executeInlineScriptsFrom(htmlText) {
        var parser = new DOMParser();
        var doc = parser.parseFromString(htmlText, 'text/html');
        var newMain = doc.querySelector('.admin-main');
        if (!newMain) return;

        function appendInlineClone(text) {
            if (!text || !String(text).trim()) return;
            var s = document.createElement('script');
            s.text = text;
            document.body.appendChild(s);
        }

        newMain.querySelectorAll('script').forEach(function(oldScript) {
            if (oldScript.src) return;
            appendInlineClone(oldScript.textContent);
        });

        var extra = doc.getElementById('adminPageExtraJs');
        if (extra && !extra.src) {
            appendInlineClone(extra.textContent);
        }
    }

    // 좌측 메뉴 active 상태를 간단히 업데이트
    function updateActiveLink(clicked) {
        sidebar.querySelectorAll('.nav-link.active').forEach(function(a) {
            a.classList.remove('active');
        });
        // 그룹 헤더 fw-bold 누적 방지 (한글 주석)
        sidebar.querySelectorAll('a.nav-link[data-bs-toggle="collapse"]').forEach(function(a) {
            a.classList.remove('fw-bold');
        });
        if (clicked) {
            clicked.classList.add('active');
            // 상위 그룹 헤더에 굵게 표시
            var groupHeader = clicked.closest('.collapse');
            if (groupHeader) {
                var headerLink = sidebar.querySelector('a.nav-link[href="#' + groupHeader.id + '"]');
                if (headerLink) headerLink.classList.add('fw-bold');
            }
        }
    }

    // 좌측 메뉴 링크 클릭 인터셉트
    sidebar.addEventListener('click', function(e) {
        var link = e.target.closest('a.nav-link');
        if (!link) return;

        var href = link.getAttribute('href');
        var target = link.getAttribute('target');

        // 그룹 토글 용 링크(#grp_xxx)는 제외
        if (href && href.charAt(0) === '#') return;

        // 로그아웃 등 특수 링크는 그대로 진행
        if (href && href.indexOf('logout=1') !== -1) return;

        // target="_blank" 메뉴(피그마 그룹 등)는 반드시 새 탭으로 — WebView/관리자 셸에서
        // 기본 동작이 같은 화면 리로드로 떨어져 사이드바가 다시 그려지고 그룹이 축소되는 현상 방지.
        // preventDefault 로 현재 페이지는 그대로 두고 window.open 으로 새 탭을 연다. (한글 주석)
        if (target === '_blank' && href && href.charAt(0) !== '#') {
            e.preventDefault();
            e.stopPropagation();
            window.open(href, '_blank', 'noopener');
            return;
        }

        // 동일 출처 .html 리포트: 우측 iframe 또는 같은 탭으로 통일 (한글 주석)
        if (href && isSameOriginHtmlReportHref(href)) {
            e.preventDefault();
            if (isAdminMobileViewport()) {
                window.location.href = href;
                return;
            }
            loadHtmlReportInMain(href);
            hideMainLoading();
            updateActiveLink(link);
            restoreSidebarScrollAfterNav();
            if (window.history && history.pushState) {
                history.pushState({ adminPartial: true, adminHtmlReport: true }, '', href);
            }
            return;
        }

        // 새창/외부 링크 (메일서버 등) — .html 리포트는 위에서 이미 처리 (한글 주석)
        if (target === '_blank') return;

        // admin 내부 PHP 페이지 SPA
        if (!href || href.indexOf('.php') === -1) return;

        // 특정 페이지는 SPA 전환 제외 (후기 검수 등, 한글 주석)
        if (href.indexOf('admin_review_wizard.php') !== -1) return;

        e.preventDefault();

        // 부드러운 전환: 페이지 최상단 강제 스크롤 없이 로딩 오버레이만 표시
        showMainLoading();

        fetch(href, { credentials: 'same-origin' })
            .then(function(r) { return r.text(); })
            .then(function(html) {
                var inner = extractMainContent(html);
                if (!inner) {
                    // 실패 시 전체 이동으로 폴백
                    hideMainLoading();
                    window.location.href = href;
                    return;
                }
                main.innerHTML = inner;
                hideMainLoading();
                updateActiveLink(link);
                restoreSidebarScrollAfterNav();
                // history 갱신
                if (window.history && history.pushState) {
                    history.pushState({ adminPartial: true }, '', href);
                }
                // 페이지 안에 포함된 스크립트 재실행
                executeInlineScriptsFrom(html);
            })
            .catch(function() {
                hideMainLoading();
                window.location.href = href;
            });
    });
})();
</script>
<?php if (isset($admin_extra_js)) echo '<script id="adminPageExtraJs">' . $admin_extra_js . '</script>'; ?>
<script>
// ===== 알림 벨 시스템 =====
(function() {
    if (window.ADMIN_FAST_LOCAL) return;

    var badge = document.getElementById('notifUnreadBadge');
    var bellBtn = document.getElementById('notifBellBtn');
    var listEl = document.getElementById('notifList');
    var readAllBtn = document.getElementById('notifReadAllBtn');
    if (!badge || !bellBtn) return;

    // 알림 타입 → 색상 맵
    var typeColors = {
        hospital_request:'primary', ad_request:'warning', review_new:'success',
        consultation_new:'info', appointment_new:'primary', member_join:'success',
        mail_incoming:'secondary', review_pending:'warning', hospital_pending:'warning',
        ad_pending:'warning', test_fail:'danger', login_fail:'danger',
        maintenance_toggle:'secondary', admin_created:'info', ai_key_error:'danger'
    };

    function updateBadge(count) {
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    }

    function fetchCount() {
        fetch('admin_notification_api.php?action=count')
            .then(function(r) { return r.json(); })
            .then(function(d) { if (d.count !== undefined) updateBadge(d.count); })
            .catch(function() {});
    }

    function fetchRecent() {
        fetch('admin_notification_api.php?action=recent')
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.count !== undefined) updateBadge(d.count);
                if (!d.items || d.items.length === 0) {
                    listEl.innerHTML = '<div class="text-center text-muted py-4" style="font-size:13px;"><i class="bi bi-bell-slash d-block mb-2" style="font-size:24px;"></i>알림이 없습니다</div>';
                    return;
                }
                var html = '';
                d.items.forEach(function(n) {
                    var c = typeColors[n.type] || 'secondary';
                    var bg = n.is_read ? '' : 'background:var(--bs-tertiary-bg);';
                    html += '<a href="' + (n.link || '#') + '" class="d-flex align-items-start gap-2 px-3 py-2 text-decoration-none border-bottom notif-item" data-id="' + n.id + '" style="' + bg + 'color:var(--bs-body-color);">';
                    html += '<span class="badge bg-' + c + ' mt-1" style="min-width:24px;"><i class="bi ' + n.icon + '"></i></span>';
                    html += '<div style="flex:1;min-width:0;">';
                    html += '<div style="font-size:13px;font-weight:' + (n.is_read ? '400' : '600') + ';" class="text-truncate">' + n.title + '</div>';
                    if (n.message) html += '<div style="font-size:11px;" class="text-body-secondary text-truncate">' + n.message + '</div>';
                    html += '</div>';
                    html += '<small class="text-body-secondary text-nowrap" style="font-size:11px;">' + n.time_ago + '</small>';
                    html += '</a>';
                });
                listEl.innerHTML = html;

                // 클릭 시 읽음 처리
                listEl.querySelectorAll('.notif-item').forEach(function(el) {
                    el.addEventListener('click', function() {
                        var nid = this.getAttribute('data-id');
                        fetch('admin_notification_api.php?action=read&id=' + nid, {method:'POST'});
                    });
                });
            })
            .catch(function() {});
    }

    // 벨 클릭 시 최근 알림 로드
    bellBtn.addEventListener('click', function() { fetchRecent(); });

    // 모두 읽음
    if (readAllBtn) {
        readAllBtn.addEventListener('click', function() {
            fetch('admin_notification_api.php?action=read_all', {method:'POST'})
                .then(function(r) { return r.json(); })
                .then(function() { updateBadge(0); fetchRecent(); });
        });
    }

    // 초기 카운트 + 30초 폴링
    fetchCount();
    setInterval(fetchCount, 30000);
})();
</script>
<script>
// 안 읽은 메일 수 배지 업데이트 (모든 admin 페이지)
(function() {
    if (window.ADMIN_FAST_LOCAL) return;

    function updateMailBadge() {
        fetch('admin_mail.php?ajax=unread_counts')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var badge = document.getElementById('mailUnreadBadge');
                if (badge && data.total_unseen !== undefined) {
                    badge.textContent = data.total_unseen;
                    badge.style.display = data.total_unseen > 0 ? 'inline-block' : 'none';
                }
            })
            .catch(function() {});
    }
    // 페이지 로드 시 1회, 이후 3분마다
    updateMailBadge();
    setInterval(updateMailBadge, 180000);
})();

// 알림 벨 배지 업데이트
(function() {
    if (window.ADMIN_FAST_LOCAL) return;

    function loadNotifications() {
        fetch('api_admin_notifications.php?limit=10')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            const badge = document.getElementById('notifBadge');
            const list = document.getElementById('notifBadgeList');
            if (!badge || !list) return;

            const unread = data.unread || 0;
            badge.textContent = unread;
            badge.style.display = unread > 0 ? 'inline' : 'none';

            if (data.items && data.items.length > 0) {
                const icons = {hospital_apply:'bi-building-add',hospital_approve:'bi-check-circle',hospital_reject:'bi-x-circle',booking:'bi-calendar-check',review:'bi-chat-dots',consultation:'bi-headset',event_entry:'bi-ticket'};
                const colors = {hospital_apply:'primary',hospital_approve:'success',hospital_reject:'danger',booking:'info',review:'warning',consultation:'secondary'};
                list.innerHTML = data.items.map(n => {
                    const icon = icons[n.type] || 'bi-bell';
                    const color = colors[n.type] || 'secondary';
                    const time = n.created_at ? n.created_at.substring(5, 16) : '';
                    const readClass = n.is_read == 1 ? 'opacity-50' : '';
                    return '<a href="' + (n.link || '#') + '" class="dropdown-item d-flex gap-2 py-2 border-bottom ' + readClass + '" style="white-space:normal;font-size:13px;">' +
                        '<i class="bi ' + icon + ' text-' + color + ' mt-1"></i>' +
                        '<div><strong>' + (n.title||'') + '</strong><br><small class="text-muted">' + (n.message||'').substring(0,60) + '</small><br><small class="text-muted">' + time + '</small></div></a>';
                }).join('');
            }
        })
        .catch(() => {});
    }
    loadNotifications();
    setInterval(loadNotifications, 30000);
})();
</script>
<?php
// 개발모드 피드백 패널 (dev_mode=ON 일 때만)
if (!function_exists('isDevMode') && file_exists(__DIR__ . '/dev_mode_helper.php')) {
    require_once __DIR__ . '/dev_mode_helper.php';
}
if (file_exists(__DIR__ . '/dev_feedback_panel.php')) {
    include __DIR__ . '/dev_feedback_panel.php';
}
?>
</body>
</html>
