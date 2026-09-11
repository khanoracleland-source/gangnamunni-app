<?php
require_once __DIR__ . '/../../hairwang_full/gangnamunni/seo.php';
$base = 'https://seoulbeauty.app/gangnamunni/';
$cases = [
    ['/gangnamunni/', $base],
    ['/hairwang/gangnamunni/index.php', $base],
    ['/hairwang/gangnamunni/detail.php?id=8206&tab=reviews', $base . 'detail.php?id=8206'],
    ['/gangnamunni/doctor_detail.php?id=0206&utm_source=test', $base . 'doctor_detail.php?id=206'],
    ['/gangnamunni/detail.php?id[]=2', null],
    ['/gangnamunni/detail.php?id=-1', null],
    ['/gangnamunni/auth/login.php?url=test', null],
    ['/gangnamunni/admin.php', null],
    ['/unrelated/detail.php?id=1', null],
    ['/gangnamunni/hospitals.php?treatment=laser&page=2&utm_source=test', $base . 'hospitals.php?page=2&treatment=laser'],
    ['/gangnamunni/tips.php?type=community', $base . 'tips.php?type=community'],
];
foreach ($cases as [$input, $expected]) {
    $actual = seoulbeautyCanonicalUrl($input);
    if ($actual !== $expected) throw new RuntimeException($input . ': ' . var_export($actual, true));
}
$_SERVER['REQUEST_URI'] = '/gangnamunni/hospitals.php?q=%22%3E%3Cscript%3E&page=2';
$html = generateSeoulbeautyCanonicalLink();
if (strpos($html, '<script>') !== false || strpos($html, '&amp;') === false) throw new RuntimeException('Escaping failed');
echo count($cases) . " canonical cases and HTML escaping passed\n";
