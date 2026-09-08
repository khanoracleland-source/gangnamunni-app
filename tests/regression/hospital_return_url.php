<?php
require __DIR__ . '/../../hairwang_full/gangnamunni/includes/hospital_return_url.php';
$urls = ['hospitals.php', 'hospitals.php?treatment=%ED%8B%B0%ED%83%80%EB%8A%84&sort=price_asc&region=gangnam&page=3', 'hospitals.php?q=a%26b&category=skin'];
foreach ($urls as $url) {
    parse_str('return_url=' . rawurlencode($url), $detail);
    $returned = hospital_return_url($detail['return_url']);
    parse_str('id=8216&tab=reviews&return_url=' . rawurlencode($returned), $tab);
    if (hospital_return_url($tab['return_url']) !== $url) throw new RuntimeException('Filters changed');
}
foreach (['https://evil.test/hospitals.php', '//evil.test', 'javascript:alert(1)', '../hospitals.php', "hospitals.php?x=\n", 'hospitals.php#x', ['hospitals.php']] as $invalid) {
    if (hospital_return_url($invalid) !== '') throw new RuntimeException('Unsafe destination accepted');
}
echo "PASS: full query survives detail/tab round trips; unsafe destinations rejected\n";
