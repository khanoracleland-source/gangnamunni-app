<?php
/**
 * 찜하기 하트 버튼 공통 컴포넌트
 * 사용법: detail.php, hospitals.php, doctor_detail.php 등에서 include 후
 *         renderWishlistButton('hospital', $hospital_id) 호출
 *
 * CSS + JS를 한 번만 출력하고, 버튼은 여러 번 호출 가능
 */

if (!defined('WISHLIST_COMPONENT_LOADED')) {
    define('WISHLIST_COMPONENT_LOADED', true);
}

function renderWishlistButton($type, $targetId, $size = 'md', $showCount = true, $icon = 'heart') {
    $sizeMap = ['sm' => '20px', 'md' => '28px', 'lg' => '28px'];
    $iconSize = $sizeMap[$size] ?? $sizeMap['md'];
    $uid = "wl_{$type}_{$targetId}";
    echo "<button type=\"button\" class=\"wishlist-btn wl-style-{$icon}\" id=\"{$uid}\" data-type=\"{$type}\" data-id=\"{$targetId}\" onclick=\"toggleWishlist('{$type}', {$targetId}, this)\" style=\"background:none;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:4px;padding:4px;\">";
    if ($icon === 'star') {
        echo "<i class=\"bi bi-star wl-icon wl-star-empty\" style=\"font-size:{$iconSize};line-height:1;\"></i>";
        echo "<i class=\"bi bi-star-fill wl-icon wl-star-full\" style=\"font-size:{$iconSize};line-height:1;display:none;\"></i>";
    } else {
        echo "<svg class=\"wl-icon\" width=\"{$iconSize}\" height=\"{$iconSize}\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z\"/></svg>";
    }
    echo "</button>";
}

function renderWishlistScript() {
    // JS + CSS 한 번만 출력
    static $scriptLoaded = false;
    if ($scriptLoaded) return;
    $scriptLoaded = true;
    ?>
<style>
.wishlist-btn { transition: transform 0.2s; }
.wishlist-btn:hover { transform: scale(1.15); }
.wishlist-btn.active .wl-icon { fill: #e53e3e; stroke: #e53e3e; color: #e53e3e; }
.wishlist-btn:not(.active) .wl-icon { fill: none; stroke: var(--bs-secondary, #888); color: var(--bs-secondary, #888); }
/* star 스타일 (icon='star') */
.wl-style-star .wl-star-empty { color: var(--bs-secondary, #888); }
.wl-style-star.active .wl-star-empty { display: none !important; }
.wl-style-star.active .wl-star-full { display: inline-block !important; color: #e53e3e; }
</style>
<script>
var WL_API = '<?php echo (isset($gnuboard_base) ? $gnuboard_base : '') . '/api_wishlist.php'; ?>';
// 자동 감지: 현재 페이지 기준 API URL
(function() {
    var scripts = document.getElementsByTagName('script');
    var basePath = window.location.pathname.replace(/\/[^\/]*$/, '');
    WL_API = basePath + '/api_wishlist.php';
})();

function toggleWishlist(type, targetId, btn) {
    fetch(WL_API, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({type: type, target_id: targetId})
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (res.login_required) {
            if (confirm('로그인이 필요합니다. 로그인 페이지로 이동하시겠습니까?')) {
                window.location.href = 'auth/login.php?url=' + encodeURIComponent(window.location.href);
            }
            return;
        }
        if (res.success) {
            btn.classList.toggle('active', res.wishlisted);
            btn.classList.add('pop');
            setTimeout(function() { btn.classList.remove('pop'); }, 300);
            var countEl = btn.querySelector('.wl-count');
            if (countEl) countEl.textContent = res.count;
            // 같은 대상의 다른 버튼도 업데이트
            document.querySelectorAll('.wishlist-btn[data-type="'+type+'"][data-id="'+targetId+'"]').forEach(function(b) {
                if (b !== btn) {
                    b.classList.toggle('active', res.wishlisted);
                    var c = b.querySelector('.wl-count');
                    if (c) c.textContent = res.count;
                }
            });
        }
    })
    .catch(function(e) { console.error('Wishlist error:', e); });
}

// 페이지 로드 시 찜 상태 체크
document.addEventListener('DOMContentLoaded', function() {
    var btns = document.querySelectorAll('.wishlist-btn');
    btns.forEach(function(btn) {
        var type = btn.getAttribute('data-type');
        var id = btn.getAttribute('data-id');
        if (type && id) {
            fetch(WL_API + '?action=check&type=' + type + '&target_id=' + id)
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    btn.classList.toggle('active', res.wishlisted);
                    var countEl = btn.querySelector('.wl-count');
                    if (countEl) countEl.textContent = res.count;
                }
            })
            .catch(function() {});
        }
    });
});
</script>
    <?php
}
