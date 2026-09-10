<?php
// Render the shared panel with fixtures; no application database or session is used.
// Run: php -n tests/regression/hospital_treatment_panel.php
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$source = file_get_contents(__DIR__ . '/../../hairwang_full/gangnamunni/admin_hospital_treatments.php');
if (($argv[1] ?? '') === '--redirect') {
    $_GET['hospital_panel'] = $argv[2] ?? '';
    if (($argv[3] ?? '') === 'ajax') $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $start = strpos($source, 'function ht_redirect(');
    $end = strpos($source, 'function ht_execute_or_throw(', $start);
    // Capture the real redirect helper's Location header before its exit.
    eval('namespace PanelRedirectTest; function header($value) { echo $value; } '.substr($source, $start, $end - $start));
    \PanelRedirectTest\ht_redirect(8438, 'Saved option', $argv[4] ?? 'warning');
}
$template = substr($source, strpos($source, '$ht_form_action ='));
function ht_price_won($price) { return (int)$price; }
function expect_panel($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
foreach (['edit', 'view'] as $mode) {
    $html = (static function ($template, $mode) {
        $ht_panel = true;
        $ht_return_mode = $mode;
        $hospital_id = 8438;
        $msg = '<script>alert(1)</script>';
        $msg_type = 'danger';
        $treatment_mgmt_mode = 'both';
        $can_manage_treatments = true;
        $is_super_admin = true;
        $csrf_token = 'fixture-csrf-token';
        $stats = ['total_assigned' => 1, 'promoted' => 0];
        $hospitals = [];
        $assigned_treatments = [['id' => 12, 'treatment_name' => 'Assigned treatment', 'is_promoted' => 0, 'registered_by' => 'admin']];
        $available_treatments = [['id' => 34, 'name' => 'Available treatment', 'cat_id' => 1, 'category_name' => 'Category']];
        $ht_prices = [12 => [['option_name' => 'Option', 'body_part' => 'Face', 'dosage_value' => 100, 'dosage_unit' => 'shots', 'regular_price' => 20000, 'price' => 10000, 'is_representative' => 1]]];
        $ht_images = [];
        ob_start();
        eval($template);
        return ob_get_clean();
    })($template, $mode);
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    $actions = [];
    foreach ($xpath->query('//form') as $form) {
        expect_panel($form->getAttribute('action') === 'admin_hospital_treatments.php?hospital_id=8438&hospital_panel='.$mode, 'Form lost hospital/return context');
        expect_panel($xpath->query('.//input[@name="csrf_token" and @value="fixture-csrf-token"]', $form)->length === 1, 'Missing CSRF protection');
        expect_panel($xpath->query('.//input[@name="hospital_id" and @value="8438"]', $form)->length === 1, 'Wrong hospital');
        $actions[] = $xpath->query('.//input[@name="action"]', $form)->item(0)->getAttribute('value');
    }
    foreach (['assign', 'remove', 'update_price', 'toggle_promoted', 'image_add', 'image_delete', 'image_move'] as $action) {
        expect_panel(in_array($action, $actions, true), 'Missing action: '.$action);
    }
    foreach (['priceModal', 'treatmentImageModal', 'ti_review_form', 'priceRows', 'catTabs'] as $id) {
        expect_panel($xpath->query('//*[@id="'.$id.'"]')->length === 1, 'Missing or duplicate control: '.$id);
    }
    expect_panel($xpath->query('//*[@id="hospitalSearch"]')->length === 0, 'Hospital picker must stay outside panel');
    expect_panel(strpos($html, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false, 'Feedback is not escaped');
    expect_panel(strpos($html, '20,000원') !== false && strpos($html, '10,000원') !== false, 'Price options missing');
    if ($mode === 'edit' && getenv('PANEL_FIXTURE_OUTPUT')) file_put_contents(getenv('PANEL_FIXTURE_OUTPUT'), $html);
    echo 'PASS: '.$mode." panel renders all forms, hospital scope, CSRF, prices, image controls and escaped feedback\n";
}

foreach (['edit', 'view', '', 'https://example.invalid'] as $mode) {
    $result = shell_exec(escapeshellarg(PHP_BINARY).' -n '.escapeshellarg(__FILE__).' --redirect '.escapeshellarg($mode));
    $expected = in_array($mode, ['edit', 'view'], true)
        ? 'Location: admin_hospitals.php?view=8438'.($mode === 'edit' ? '&edit=1' : '').'&treatment_msg=Saved+option&treatment_msg_type=warning#'.($mode === 'edit' ? 'edit' : 'view').'TreatmentsSection'
        : 'Location: admin_hospital_treatments.php?hospital_id=8438&msg=Saved+option&msg_type=warning';
    expect_panel($result === $expected, 'Incorrect return location: '.$mode);
}
echo "PASS: edit/view redirects retain hospital, feedback and expanded section; standalone and invalid modes stay on the treatment page\n";

foreach (['edit', 'view'] as $mode) {
  foreach (['warning', 'danger', 'success'] as $type) {
    $result = shell_exec(escapeshellarg(PHP_BINARY).' -n '.escapeshellarg(__FILE__).' --redirect '.escapeshellarg($mode).' ajax '.escapeshellarg($type));
    expect_panel(strpos($result, 'Location:') === false, 'AJAX must not redirect');
    $payload = json_decode(substr($result, strpos($result, '{')), true);
    expect_panel($payload === ['ok' => $type === 'success', 'message' => 'Saved option', 'type' => $type], 'AJAX feedback must distinguish saved and invalid requests');
  }
}
echo "PASS: AJAX panel responses return structured feedback without navigation\n";
