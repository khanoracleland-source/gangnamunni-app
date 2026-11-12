<?php
/**
 * Sends a visitor of an old PHP page to the Next.js page that replaced it (seoulbeauty.app/ → /next, detail.php?id=5 → /next/hospitals/5 …).
 *
 * Loaded as the first statement of every patient page that has a Next.js counterpart. Same rules as the app's NextPilot
 * (mobile/patient_app/lib/services/next_pilot.dart) and the website's lib/phpLink.ts: a parameter the Next.js page does not
 * understand — and the `php=1` escape hatch — keeps the PHP page.
 *
 * Switch: the file hairwang_full/data/next_redirect.on. Without it nothing happens (rollback = remove the file).
 * Its content chooses the status: "301" = permanent (search engines move their index; browsers cache it), anything else = 302.
 * Only plain page loads are redirected: GET/HEAD, not AJAX / fragment / Turbo requests, never the CLI.
 */
if (!function_exists('sb_next_redirect_target')) {

    /** @return string|null the Next.js URL (path + query) for this PHP script and query, or null to stay on PHP */
    function sb_next_redirect_target($script, array $get)
    {
        $base = '/next';
        $q = array();
        foreach ($get as $k => $v) {
            if (!is_string($k) || !is_string($v)) return null; // arrays (a[]=1) are never a plain page link
            if ($k !== 'app') $q[$k] = $v;
        }
        $only = function (array $allowed) use ($q) {
            foreach ($q as $k => $_) { if (!in_array($k, $allowed, true)) return false; }
            return true;
        };
        $int = function ($name) use ($q) {
            return (isset($q[$name]) && preg_match('/^[1-9][0-9]{0,9}$/', $q[$name])) ? (int)$q[$name] : 0;
        };
        $str = function ($name) use ($q) { return isset($q[$name]) ? $q[$name] : ''; };
        $with = function ($path, array $params) use ($base) {
            $kept = array();
            foreach ($params as $k => $v) { if ($v !== '' && $v !== null) $kept[$k] = (string)$v; }
            return $base . $path . ($kept ? '?' . http_build_query($kept, '', '&', PHP_QUERY_RFC3986) : '');
        };
        $bare = function ($path) use ($only, $with) { return $only(array()) ? $with($path, array()) : null; };
        $by_id = function ($prefix, $param) use ($only, $int, $with) {
            $id = $int($param);
            return ($id && $only(array($param))) ? $with($prefix . '/' . $id, array()) : null;
        };

        switch ($script) {
            case 'index.php':
                return $bare('');
            case 'hospitals.php':
                $keys = array('q', 'category', 'treatment', 'region', 'sort', 'dosage', 'hid', 'tcat');
                if (!$only($keys)) return null;
                $p = array();
                foreach ($keys as $k) $p[$k] = $str($k);
                return $with('/hospitals', $p);
            case 'detail.php':
                $id = $int('id'); $tab = $str('tab');
                if (!$id || !$only(array('id', 'tab', 'from', 'treatment_id', 'treatment', 'return_url'))) return null;
                if ($tab !== '' && !in_array($tab, array('home', 'events', 'reviews', 'doctors'), true)) return null;
                $tid = $int('treatment_id');
                if (isset($q['treatment_id']) ? !$tid : isset($q['treatment'])) return null; // bad id, or a name without an id (PHP matches by name)
                return $with('/hospitals/' . $id, array('tab' => $tab === 'home' ? '' : $tab, 'treatment_id' => $tid ?: ''));
            case 'events.php':
                $filter = $str('filter');
                if (!$only(array('category', 'filter')) || ($filter !== '' && $filter !== 'raffle')) return null;
                return $with('/events', array('category' => $str('category'), 'filter' => $filter));
            case 'event_detail.php':
                return $by_id('/events', 'id');
            case 'community.php':
                $tab = $str('tab'); $page = $str('page');
                if (!$only(array('tab', 'category', 'region', 'q', 'page'))) return null;
                if ($tab !== '' && !in_array($tab, array('latest', 'popular', 'review'), true)) return null;
                if ($page !== '' && !$int('page')) return null;
                return $with('/community', array('tab' => $tab === 'latest' ? '' : $tab, 'category' => $str('category'), 'region' => $str('region'), 'q' => $str('q'), 'page' => $page === '1' ? '' : $page));
            case 'detail_community.php':
                return $by_id('/community', 'id');
            case 'community_write.php':
                if (!$only(array('id')) || (isset($q['id']) && $q['id'] !== '' && !$int('id'))) return null;
                return $with('/community/write', array('id' => $int('id') ?: ''));
            case 'mypage.php':
                return $bare('/mypage');
            case 'my_wishlist.php':
                return $bare('/mypage/wishlist');
            case 'my_reviews.php':
                $tab = $str('tab');
                if (!$only(array('tab')) || ($tab !== '' && $tab !== 'writable' && $tab !== 'done')) return null;
                return $with('/mypage/reviews', array('tab' => $tab === 'writable' ? '' : $tab));
            case 'my_consultations.php':
                return $bare('/mypage/consultations');
            case 'my_bookings.php':
                $action = $str('action'); $tab = $str('tab');
                if ($action !== '') {
                    $id = $int('booking_id');
                    if (!$id || !$only(array('action', 'booking_id', 'tab'))) return null;
                    if ($action === 'change') return $with('/booking', array('change' => $id));
                    if ($action === 'cancel') return $with('/mypage/bookings', array('action' => 'cancel', 'booking_id' => $id));
                    return null;
                }
                if (!$only(array('tab')) || ($tab !== '' && !in_array($tab, array('active', 'completed', 'cancelled'), true))) return null;
                return $with('/mypage/bookings', array('tab' => $tab === 'active' ? '' : $tab));
            case 'booking_request_detail.php':
            case 'booking_cancel_detail.php':
                return $by_id('/mypage/bookings', 'booking_id');
            case 'my_consultation_detail.php':
                return $by_id('/mypage/consultations', 'id');
            case 'review_detail.php':
                return $by_id('/reviews', 'id');
            case 'qna.php':
                return $bare('/qna');
            case 'faq.php':
                if (!$only(array('cat')) || strlen($str('cat')) > 150) return null;
                return $with('/faq', array('cat' => $str('cat')));
            case 'my_notifications.php':
                $type = $str('type');
                if (!$only(array('type')) || ($type !== '' && !in_array($type, array('all', 'booking', 'review', 'benefit'), true))) return null;
                return $with('/mypage/notifications', array('type' => $type === 'all' ? '' : $type));
            case 'profile_edit.php':
                return $bare('/mypage/profile');
            case 'notification_settings.php':
                return $bare('/mypage/settings');
            case 'withdraw.php':
                return $bare('/mypage/withdraw');
            case 'terms_of_service.php':
                return $bare('/terms');
            case 'privacy_policy.php':
                return $bare('/privacy');
            case 'community_guidelines.php':
                return $bare('/community-guidelines');
            case 'my_points.php':
                return $bare('/mypage/points');
            case 'my_points_policy.php':
                $tab = $str('tab');
                if (!$only(array('tab')) || ($tab !== '' && !in_array($tab, array('earn', 'revoke', 'expire', 'use', 'refund'), true))) return null;
                return $with('/points-policy', array('tab' => $tab));
            case 'my_points_policy_detail.php':
                $type = $str('type');
                if (!$only(array('type')) || !in_array($type, array('progress', 'photo', 'receipt', 'first', 'payment'), true)) return null;
                return $with('/points-policy/' . $type, array());
            case 'auth/password_lost.php':
                return $bare('/password-lost');
            case 'auth/register.php':
                return $bare('/register');
            case 'auth/login.php':
                // Only the patient sign-in: a social start (provider=) and a return to a PHP page (partner / admin screens) stay on PHP.
                $url = $str('url'); $pw = $str('pw_changed');
                if (!$only(array('url', 'pw_changed')) || ($pw !== '' && $pw !== '1')) return null;
                if (isset($q['url']) && !preg_match('#^/next(?:[/?]|$)#', $url)) return null;
                if (strlen($url) > 500 || strpos($url, '\\') !== false || preg_match('/[\x00-\x1F\x7F]/', $url)) return null;
                return $with('/login', array('pw_changed' => $pw, 'url' => $url));
            case 'booking.php':
                $keys = array('id', 'treatment_id', 'option_id', 'treatment', 'rebook', 'via', 'source_booking_id', 'from', 'return_url');
                if (!$int('id') || !$only($keys)) return null;
                $p = array();
                foreach ($keys as $k) { if ($k !== 'from' && $k !== 'return_url') $p[$k] = $str($k); }
                return $with('/booking', $p);
            case 'booking_change.php':
                $id = $int('booking_id');
                return ($id && $only(array('booking_id'))) ? $with('/booking', array('change' => $id)) : null;
            case 'booking_cancel_request.php':
                return ($int('booking_id') && $only(array('booking_id'))) ? $with('/mypage/bookings', array()) : null;
            case 'booking_cancel_complete.php':
                $id = $int('booking_id');
                return ($id && $only(array('booking_id'))) ? $with('/mypage/bookings/' . $id . '/cancel/done', array()) : null;
            case 'booking_payment.php':
                $id = $int('booking_id');
                if (!$id || !$only(array('booking_id', 'pay_error')) || !preg_match('/^[A-Za-z0-9_]{0,64}$/', $str('pay_error'))) return null;
                return $with('/mypage/bookings/' . $id . '/payment', array('pay_error' => $str('pay_error')));
            case 'booking_payment_success.php':
                $id = $int('booking_id');
                return ($id && $only(array('booking_id'))) ? $with('/mypage/bookings/' . $id . '/payment/success', array()) : null;
            case 'review_write.php':
                if (!$only(array('booking_id', 'hospital_id', 'id', 'return_url'))) return null;
                $p = array();
                foreach (array('booking_id', 'hospital_id', 'id') as $k) {
                    if ($str($k) === '') continue;
                    if (!$int($k)) return null;
                    $p[$k] = $int($k);
                }
                return $with('/mypage/reviews/write', $p);
            case 'my_progress_reviews.php':
                return $bare('/mypage/progress');
            case 'progress_review.php':
                return $by_id('/mypage/progress', 'id');
        }
        return null;
    }

    /** "auth/login.php" for the pages under auth/, otherwise the file name of the entry script. */
    function sb_next_redirect_script($script_name)
    {
        $script_name = str_replace('\\', '/', (string)$script_name);
        if (preg_match('#/auth/([a-z_]+\.php)$#', $script_name, $m)) return 'auth/' . $m[1];
        return basename($script_name);
    }

    function sb_next_redirect()
    {
        if (PHP_SAPI === 'cli') return;
        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        if ($method !== 'GET' && $method !== 'HEAD') return;
        $flag = __DIR__ . '/../../data/next_redirect.on';
        if (!is_file($flag)) return;
        // only a page load: not jQuery / fetch calls, not the content fragments of the PHP pages, not Turbo
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_SERVER['HTTP_X_SB_CONTENT'])) return;
        $dest = isset($_SERVER['HTTP_SEC_FETCH_DEST']) ? $_SERVER['HTTP_SEC_FETCH_DEST'] : '';
        if ($dest !== '' && $dest !== 'document') return;
        if (headers_sent()) return;

        $target = sb_next_redirect_target(sb_next_redirect_script(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : ''), $_GET);
        if ($target === null) return;
        $permanent = trim((string)@file_get_contents($flag)) === '301';
        header('Cache-Control: ' . ($permanent ? 'public, max-age=3600' : 'no-store'));
        header('Location: ' . $target, true, $permanent ? 301 : 302);
        exit;
    }
}
sb_next_redirect();
