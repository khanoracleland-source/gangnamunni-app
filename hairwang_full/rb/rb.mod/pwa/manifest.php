<?php
require __DIR__.'/api/_bootstrap.php';
$cfg = rb_pwa_cfg();

// 캐시 최소화
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-store');

// URL → 로컬 파일 경로
function _url_to_path($u){
  if(!$u) return '';
  $p = parse_url($u, PHP_URL_PATH);
  if(!$p) return '';
  return rtrim($_SERVER['DOCUMENT_ROOT'],'/').$p;
}
// 파일 버전 쿼리 추가 (filemtime)
function _ver_url($u){
  $abs = _url_to_path($u);
  if($abs && is_file($abs)){
    $v = @filemtime($abs) ?: time();
    // 이미 쿼리가 있으면 &v=, 없으면 ?v=
    return $u . (strpos($u,'?')===false ? '?v=' : '&v=') . $v;
  }
  return $u;
}
// 경로 정규화: 빈값이면 '/', 상대면 루트로
function _norm_path($p, $fallback='/'){
  $p = trim((string)$p);
  if ($p === '') return $fallback;
  return ($p[0] === '/') ? $p : '/'.$p;
}

// ---------------- Icons ----------------
$icons = [];
// 192
if (!empty($cfg['icon_192']) && is_file(_url_to_path($cfg['icon_192']))) {
  $icons[] = ['src'=>_ver_url($cfg['icon_192']),'sizes'=>'192x192','type'=>'image/png'];
}
// 512
if (!empty($cfg['icon_512']) && is_file(_url_to_path($cfg['icon_512']))) {
  $icons[] = ['src'=>_ver_url($cfg['icon_512']),'sizes'=>'512x512','type'=>'image/png'];
}
// maskable 512 (선택)
$mask512 = '/data/pwa/icons/icon-512-maskable.png';
if (is_file(rtrim($_SERVER['DOCUMENT_ROOT'],'/').$mask512)) {
  $icons[] = ['src'=>_ver_url($mask512),'sizes'=>'512x512','type'=>'image/png','purpose'=>'any maskable'];
}
// maskable 192 (선택)
$mask192 = '/data/pwa/icons/icon-192-maskable.png';
if (is_file(rtrim($_SERVER['DOCUMENT_ROOT'],'/').$mask192)) {
  $icons[] = ['src'=>_ver_url($mask192),'sizes'=>'192x192','type'=>'image/png','purpose'=>'any maskable'];
}

// ---------------- Screenshots ----------------
// rb_pwa_config.install_img (wide), install_img_m (narrow)
$screens = [];

// wide(데스크톱)
$promo_w = trim((string)($cfg['install_img'] ?? ''));
if ($promo_w !== '') {
  $abs = _url_to_path($promo_w);
  if ($abs && is_file($abs)) {
    $info = @getimagesize($abs); // 업로더가 1200x675로 저장하도록 강제됨
    $entry = [
      'src'         => _ver_url($promo_w),
      'form_factor' => 'wide',
      'label'       => '미리보기'
    ];
    if ($info) {
      if (!empty($info[2])) $entry['type']  = image_type_to_mime_type($info[2]);
      if (!empty($info[0]) && !empty($info[1])) $entry['sizes'] = $info[0].'x'.$info[1];
    }
    $screens[] = $entry;
  }
}

// narrow(모바일)
$promo_m = trim((string)($cfg['install_img_m'] ?? ''));
if ($promo_m !== '') {
  $abs = _url_to_path($promo_m);
  if ($abs && is_file($abs)) {
    $info = @getimagesize($abs); // 업로더가 720x1280으로 저장하도록 강제됨
    $entry = [
      'src'         => _ver_url($promo_m),
      'form_factor' => 'narrow',
      'label'       => '미리보기'
    ];
    if ($info) {
      if (!empty($info[2])) $entry['type']  = image_type_to_mime_type($info[2]);
      if (!empty($info[0]) && !empty($info[1])) $entry['sizes'] = $info[0].'x'.$info[1];
    }
    $screens[] = $entry;
  }
}

// ---------------- Base fields ----------------
$start = _norm_path($cfg['start_url'] ?? '/', '/');
$scope = _norm_path($cfg['scope']     ?? '/', '/');

// 앱 고유 ID (scope 기반 고정)
$manifest = [
  'id'                          => $scope,
  'name'                        => ($cfg['name'] ?? '') ?: 'PWA',
  'description'                 => (string)($cfg['description'] ?? ''),
  'short_name'                  => ($cfg['short_name'] ?? '') ?: 'PWA',
  'start_url'                   => $start,
  'scope'                       => $scope,
  'display'                     => 'standalone',
  'theme_color'                 => ($cfg['theme_color'] ?? '') ?: '#25282b',
  'background_color'            => ($cfg['bg_color'] ?? '') ?: '#25282b',
  'icons'                       => $icons,
  'prefer_related_applications' => false,
  'lang'                        => 'ko-KR',
  'dir'                         => 'ltr',
];
if ($screens) $manifest['screenshots'] = $screens;

// 출력
echo json_encode($manifest, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
