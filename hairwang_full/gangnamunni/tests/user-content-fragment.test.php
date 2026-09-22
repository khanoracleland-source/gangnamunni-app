<?php
// Run with php -n: fixtures exercise the real templates without a database.
require __DIR__ . '/../includes/user_content_cache.php';
require __DIR__ . '/../includes/wishlist_button.php';
function getSettingValue($connect, $key, $default) { return $key === 'ad_show_hospitals_infeed' ? '0' : $default; }
function extract_addr_district($address) { return ''; }
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SESSION = ['ss_mb_id' => 'fixture-user'];
assert(user_content_cache_enabled());
$_SESSION['ad_admin_logged_in'] = true;
assert(!user_content_cache_enabled());
unset($_SESSION['ad_admin_logged_in']);
$first_scope = user_content_cache_scope();
$_SESSION['ss_mb_id'] = 'another-user';
assert($first_scope !== user_content_cache_scope());
$connect = null;
$hospitals = [['id' => 42, 'name' => 'Clinic <test>', 'address' => 'Seoul', 'ad_grade' => 'none']];
$hospital_return_url = 'hospitals.php?q=test';
$hospital_main_images = $hospital_logos = $hospital_treatment_descs = $slot_hospital_context = [];
$effective_treatment = '';
ob_start();
require __DIR__ . '/../includes/hospital_list_content.php';
$html = ob_get_clean();
assert(strpos($html, 'Clinic &lt;test&gt;') !== false);
assert(strpos($html, 'return_url=') !== false);
assert(strpos($html, 'toggleWishlist') !== false);
assert(strpos($html, '<script') === false);
$hospitals = [];
ob_start();
require __DIR__ . '/../includes/hospital_list_content.php';
$empty = ob_get_clean();
assert(strpos($empty, 'no-result') !== false);
echo "Fragment rendering, escaping, empty state and scope checks passed.\n";
