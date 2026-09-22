    </div><!-- /.main-content -->

    <!-- 하단 네비게이션 (앱 모드에서는 숨김) -->
    <?php if (empty($is_app_mode) && empty($hide_bottom_nav)) { include_once __DIR__ . '/bottom_nav.php'; } ?>

    <?php if (!empty($page_js)): ?>
    <script>
        <?php echo $page_js; ?>
    </script>
    <?php endif; ?>

<?php
// admin live panel (PC only, check PHPSESSID session for admin login)
$_lp_admin = false;
if (!empty($_SESSION['ad_admin_logged_in'])) {
    $_lp_admin = true;
} elseif (isset($_COOKIE['PHPSESSID'])) {
    $_lp_save = session_save_path() ?: '/tmp';
    $_lp_sid = preg_replace('/[^a-zA-Z0-9,-]/', '', $_COOKIE['PHPSESSID']);
    $_lp_data = @file_get_contents($_lp_save . '/sess_' . $_lp_sid);
    if ($_lp_data && strpos($_lp_data, 'ad_admin_logged_in|b:1') !== false) {
        $_lp_admin = true;
    }
}
?>

<?php
// Frontend console debug (admin only)
if ($_lp_admin) {
    include __DIR__ . '/frontend_debug.php';
}
?>

<!-- Ad tracking script -->
<script>
(function(){
    var trackingUrl = <?php echo json_encode(SITE_BASE_PATH . 'api_ad_tracking.php'); ?>;
    var tracked = {};

    function trackImpression(adId) {
        if (tracked['imp_' + adId]) return;
        tracked['imp_' + adId] = true;
        var fd = new FormData();
        fd.append('action', 'impression');
        fd.append('ad_id', adId);
        fd.append('page_url', location.href);
        fetch(trackingUrl, { method: 'POST', body: fd }).catch(function(){});
    }

    function trackClick(adId, targetUrl) {
        var fd = new FormData();
        fd.append('action', 'click');
        fd.append('ad_id', adId);
        fd.append('page_url', location.href);
        fd.append('target_url', targetUrl || '');
        fetch(trackingUrl, { method: 'POST', body: fd }).catch(function(){});
    }

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var adId = entry.target.getAttribute('data-ad-id');
                    if (adId) trackImpression(adId);
                }
            });
        }, { threshold: 0.5 });

        document.querySelectorAll('[data-ad-id]').forEach(function(el) {
            observer.observe(el);
        });
        document.addEventListener('sb:content-updated', function(event) {
            event.detail.root.querySelectorAll('[data-ad-id]').forEach(function(el) { observer.observe(el); });
        });
    } else {
        document.querySelectorAll('[data-ad-id]').forEach(function(el) {
            trackImpression(el.getAttribute('data-ad-id'));
        });
    }

    document.addEventListener('click', function(e) {
        var el = e.target.closest('[data-ad-id]');
        if (el) {
            var adId = el.getAttribute('data-ad-id');
            var link = el.tagName === 'A' ? el.href : (el.querySelector('a') ? el.querySelector('a').href : '');
            trackClick(adId, link);
        }
    });
})();
</script>

<!-- Keyword slot CPC/CPM tracking -->
<script>
(function(){
    var trackingUrl = <?php echo json_encode(SITE_BASE_PATH . 'api_ad_slot_tracking.php'); ?>;
    var impressionTimers = new WeakMap();
    var impressed = {};

    function sendSlotEvent(action, element, targetUrl) {
        var bookingId = element.getAttribute('data-ad-slot-booking');
        if (!bookingId) return;

        var fd = new FormData();
        fd.append('action', action);
        fd.append('booking_id', bookingId);
        fd.append('keyword', element.getAttribute('data-ad-slot-keyword') || '');
        fd.append('page_url', location.href);
        fd.append('target_url', targetUrl || '');

        if (navigator.sendBeacon) {
            navigator.sendBeacon(trackingUrl, fd);
        } else {
            fetch(trackingUrl, { method: 'POST', body: fd, keepalive: true }).catch(function(){});
        }
    }

    function scheduleImpression(element) {
        var bookingId = element.getAttribute('data-ad-slot-booking');
        if (!bookingId || impressed[bookingId] || impressionTimers.has(element)) return;

        var timer = setTimeout(function() {
            impressionTimers.delete(element);
            impressed[bookingId] = true;
            sendSlotEvent('impression', element, '');
        }, 1000);
        impressionTimers.set(element, timer);
    }

    function cancelImpression(element) {
        var timer = impressionTimers.get(element);
        if (!timer) return;
        clearTimeout(timer);
        impressionTimers.delete(element);
    }

    var elements = document.querySelectorAll('[data-ad-slot-booking]');
    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
                    scheduleImpression(entry.target);
                } else {
                    cancelImpression(entry.target);
                }
            });
        }, { threshold: [0, 0.5] });
        elements.forEach(function(element) { observer.observe(element); });
        document.addEventListener('sb:content-updated', function(event) {
            event.detail.root.querySelectorAll('[data-ad-slot-booking]').forEach(function(element) { observer.observe(element); });
        });
    } else {
        elements.forEach(scheduleImpression);
    }

    document.addEventListener('click', function(event) {
        var element = event.target.closest('[data-ad-slot-booking]');
        if (!element) return;
        var link = element.closest('a') || element.querySelector('a');
        var targetUrl = link ? link.href : (element.getAttribute('data-target-url') || '');
        sendSlotEvent('click', element, targetUrl);
    }, true);
})();
</script>
</body>
</html>
