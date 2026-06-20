<?php
/**
 * 최근 본 병원 컴포넌트
 * localStorage 기반 — 서버 DB 불필요, 비로그인도 사용 가능
 *
 * 사용법:
 *   detail.php: renderRecentlyViewedSave($hospital_id, $name, $image, $rating, $review_count, $address)
 *   hospitals.php / index.php: renderRecentlyViewedSection()
 */

/**
 * detail.php에서 호출: 현재 병원을 localStorage에 저장하는 JS 출력
 */
function renderRecentlyViewedSave($hospital_id, $name, $image_url, $rating = 0, $review_count = 0, $address = '') {
    $data = json_encode([
        'id' => (int)$hospital_id,
        'name' => $name,
        'image' => $image_url,
        'rating' => (float)$rating,
        'reviews' => (int)$review_count,
        'address' => $address,
        'ts' => time() * 1000
    ], JSON_UNESCAPED_UNICODE);
    ?>
<script>
(function(){
    try {
        var MAX = 20;
        var KEY = 'rv_hospitals';
        var item = <?php echo $data; ?>;
        var list = JSON.parse(localStorage.getItem(KEY) || '[]');
        // 중복 제거
        list = list.filter(function(h){ return h.id !== item.id; });
        // 앞에 추가
        list.unshift(item);
        // 최대 개수 제한
        if (list.length > MAX) list = list.slice(0, MAX);
        localStorage.setItem(KEY, JSON.stringify(list));
    } catch(e) {}
})();
</script>
    <?php
}

/**
 * 최근 본 병원 섹션 렌더 (hospitals.php, index.php 등)
 * 순수 JS로 localStorage에서 읽어 표시 — PHP 데이터 불필요
 */
function renderRecentlyViewedSection() {
    ?>
<div id="rv-section" style="display:none; padding:0 0 8px; border-bottom:8px solid var(--bs-tertiary-bg,#f0f0f0);">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:16px 16px 8px;">
        <h3 style="font-size:16px;font-weight:700;margin:0;">최근 본 병원</h3>
        <button id="rv-clear-btn" type="button" style="background:none;border:none;font-size:12px;color:var(--bs-secondary,#888);cursor:pointer;padding:4px 8px;">전체 삭제</button>
    </div>
    <div id="rv-scroll" style="display:flex;gap:10px;overflow-x:auto;-webkit-overflow-scrolling:touch;padding:0 16px 12px;scroll-snap-type:x mandatory;"></div>
</div>
<script>
(function(){
    var KEY = 'rv_hospitals';
    var section = document.getElementById('rv-section');
    var scroll = document.getElementById('rv-scroll');
    var clearBtn = document.getElementById('rv-clear-btn');

    function render() {
        var list = [];
        try { list = JSON.parse(localStorage.getItem(KEY) || '[]'); } catch(e) {}
        if (!list.length) { section.style.display = 'none'; return; }

        section.style.display = '';
        scroll.innerHTML = '';

        list.forEach(function(h) {
            var card = document.createElement('a');
            card.href = 'detail.php?id=' + h.id;
            card.style.cssText = 'flex-shrink:0;width:120px;text-decoration:none;color:inherit;scroll-snap-align:start;';

            var img = '';
            if (h.image) {
                img = '<img src="' + escHtml(h.image) + '" alt="" style="width:120px;height:80px;border-radius:10px;object-fit:cover;display:block;">';
            } else {
                img = '<div style="width:120px;height:80px;border-radius:10px;background:var(--bs-tertiary-bg,#eee);display:flex;align-items:center;justify-content:center;"><svg width="28" height="28" fill="none" stroke="var(--bs-secondary,#999)" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/></svg></div>';
            }

            var rating = '';
            if (h.rating > 0) {
                rating = '<div style="font-size:11px;color:#f59e0b;margin-top:2px;">★ ' + Number(h.rating).toFixed(1) + '</div>';
            }

            card.innerHTML = img +
                '<div style="padding:6px 2px 0;">' +
                '<div style="font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + escHtml(h.name || '') + '</div>' +
                rating +
                '</div>';

            scroll.appendChild(card);
        });
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    clearBtn.addEventListener('click', function() {
        if (confirm('최근 본 병원 기록을 모두 삭제하시겠습니까?')) {
            localStorage.removeItem(KEY);
            section.style.display = 'none';
        }
    });

    render();
})();
</script>
    <?php
}
