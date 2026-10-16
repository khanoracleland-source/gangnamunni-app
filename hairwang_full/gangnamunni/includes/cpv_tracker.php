<?php
/**
 * CPV 추적 JavaScript 삽입
 *
 * 사용법: 페이지에서 $cpv_page_type, $cpv_target_id 설정 후 include
 *
 * 예: $cpv_page_type = 'hospital'; $cpv_target_id = $hospital_id;
 *     include __DIR__ . '/includes/cpv_tracker.php';
 */

if (!isset($cpv_page_type) || !isset($cpv_target_id) || $cpv_target_id <= 0) return;

// keyword/procedure_type 은 단가 가중치에 쓰이므로 브라우저에서 보내지 않는다 (서버에서 산출).
?>
<script>
(function() {
    // 페이지 100% 로드 후 CPV 추적 호출
    if (document.readyState === 'complete') {
        _sendCpvTrack();
    } else {
        window.addEventListener('load', _sendCpvTrack);
    }

    function _sendCpvTrack() {
        // 봇/크롤러 제외
        if (navigator.webdriver || /bot|crawl|spider|slurp/i.test(navigator.userAgent)) return;

        var data = {
            page_type: <?php echo json_encode($cpv_page_type); ?>,
            target_id: <?php echo json_encode((string)$cpv_target_id); ?>,
            page_url: location.href
        };

        // Beacon API 우선 (페이지 이탈 시에도 전송 보장)
        if (navigator.sendBeacon) {
            navigator.sendBeacon('api_cpv_track.php', JSON.stringify(data));
        } else {
            fetch('api_cpv_track.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data),
                keepalive: true
            }).catch(function(){});
        }
    }
})();
</script>
