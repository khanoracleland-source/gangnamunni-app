<?php

/**
 * Session-bearing documents must reach the server on each navigation.
 * User content fragments have their own bounded stale-while-revalidate cache.
 * Keep the existing signature for pages that still call this helper.
 */
function gangnamunni_page_cache($max_age, $scope = 'public') {
    if (headers_sent()) return;
    header('Cache-Control: private, no-store, max-age=0');
    header('Vary: Accept-Encoding, Cookie');
}
