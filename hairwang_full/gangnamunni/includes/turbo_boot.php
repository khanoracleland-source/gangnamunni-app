<?php
/**
 * Turbo Drive bootstrap (in-page navigation for the Seoul Beauty Flutter WebView app).
 *
 * Turbo is injected into the <head> of a page only when ALL of these hold:
 *   - the request is a GET for a page listed in sb_turbo_pages()
 *   - the client sent the cookie sb_turbo_bridge=1 (set by the NEW app versions, which handle the
 *     {action:'pageChanged'} bridge message; old apps and normal browsers never get Turbo)
 *   - there is no kill switch: create the empty file includes/turbo_disabled.flag to turn Turbo
 *     off for everybody immediately (delete it to turn it back on); ?noturbo=1 turns it off per request
 *
 * Testing in a normal browser: open any listed page with ?turbo_test=1 (sets the cookie for a day),
 * ?turbo_test=0 removes it.
 *
 * Pages that must NOT be added to the list: login / logout / OAuth, payment, forms and wizards,
 * file downloads, admin and partner tools. Before adding a page, check that its inline scripts
 * contain no top-level const/let/class (Turbo re-runs them on every visit) -- see turbo-compat.js.
 *
 * Called from includes/init.php (and explicitly from the few listed pages that do not load it).
 */

if (!defined('SB_TURBO_VERSION')) define('SB_TURBO_VERSION', 4);

if (!function_exists('sb_turbo_pages')) {

    function sb_turbo_pages() {
        $pages = sb_turbo_all_pages();
        // Next.js pilot (patient app test build sets sb_next_pilot=1): home, hospital list and
        // detail are served by Next.js, so links to them must be real navigations (the app
        // rewrites them to /next/, /next/hospitals[/id]), never in-page Turbo visits.
        if ((isset($_COOKIE['sb_next_pilot']) ? $_COOKIE['sb_next_pilot'] : '') === '1') {
            $pages = array_values(array_diff($pages, array('index.php', 'hospitals.php', 'detail.php')));
        }
        return $pages;
    }

    function sb_turbo_all_pages() {
        return array(
            'index.php', 'hospitals.php', 'detail.php', 'doctor_detail.php', 'hospital_news.php',
            'hospital_ranking.php', 'hospital_compare.php', 'events.php', 'event_detail.php',
            'event_calendar.php', 'tips.php', 'detail_tip.php', 'columns.php', 'community.php',
            'detail_community.php', 'reviews.php', 'review_detail.php', 'review_photos.php',
            'search.php', 'rankings.php', 'treatment_ranking.php', 'treatments.php',
            'price_compare.php', 'faq.php', 'qna.php', 'membership.php', 'my_wishlist.php',
            'my_events.php', 'my_notifications.php', 'my_coupons.php', 'my_points.php',
            'my_points_policy.php', 'my_points_policy_detail.php', 'my_reviews.php',
            'my_progress_reviews.php', 'my_appointments.php', 'mypage.php',
        );
    }

    function sb_turbo_page_key() {
        $path = parse_url(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '', PHP_URL_PATH);
        return basename((string)$path);
    }

    function sb_turbo_set_test_cookie($on) {
        if (headers_sent()) return;
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $expires = $on ? time() + 86400 : time() - 3600;
        if (PHP_VERSION_ID >= 70300) {
            setcookie('sb_turbo_bridge', $on ? '1' : '', array(
                'expires' => $expires, 'path' => '/', 'secure' => $secure, 'samesite' => 'Lax',
            ));
        } else {
            setcookie('sb_turbo_bridge', $on ? '1' : '', $expires, '/; samesite=Lax', '', $secure);
        }
        if ($on) $_COOKIE['sb_turbo_bridge'] = '1'; else unset($_COOKIE['sb_turbo_bridge']);
    }

    function sb_turbo_active() {
        static $active = null;
        if ($active !== null) return $active;
        $active = false;

        if (is_file(__DIR__ . '/turbo_disabled.flag')) return $active;
        if ((isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET') !== 'GET') return $active;
        if (!in_array(sb_turbo_page_key(), sb_turbo_pages(), true)) return $active;
        if (!empty($_GET['noturbo'])) return $active;

        if (isset($_GET['turbo_test'])) sb_turbo_set_test_cookie($_GET['turbo_test'] === '1');
        if ((isset($_COOKIE['sb_turbo_bridge']) ? $_COOKIE['sb_turbo_bridge'] : '') !== '1') return $active;

        $active = true;
        return $active;
    }

    function sb_turbo_head_html() {
        $cfg = json_encode(array('pages' => sb_turbo_pages(), 'v' => SB_TURBO_VERSION));
        $v = (int)SB_TURBO_VERSION;
        return '<meta name="turbo-cache-control" content="no-cache">'
            . '<style>.turbo-progress-bar{background-color:var(--primary-color,#AC1830);height:3px}</style>'
            . '<script>window.SB_TURBO=' . $cfg . ';</script>'
            . '<script src="assets/js/turbo-compat.js?v=' . $v . '"></script>'
            . '<script src="assets/js/vendor/turbo-7.3.0.es2017-umd.js" defer></script>';
    }

    /** Output-buffer callback: add the Turbo tags right after the opening <head> of an HTML page. */
    function sb_turbo_inject($html) {
        static $done = false;
        if ($done || $html === '' || $html === null) return $html;
        if (!preg_match('/<head\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) return $html;
        foreach (headers_list() as $h) {
            if (stripos($h, 'Content-Type:') === 0 && stripos($h, 'text/html') === false) return $html;
        }
        $done = true;
        $pos = $m[0][1] + strlen($m[0][0]);
        return substr($html, 0, $pos) . sb_turbo_head_html() . substr($html, $pos);
    }

    function sb_turbo_boot() {
        static $started = false;
        if ($started) return;
        if (!sb_turbo_active()) return;
        $started = true;
        ob_start('sb_turbo_inject');
    }
}
