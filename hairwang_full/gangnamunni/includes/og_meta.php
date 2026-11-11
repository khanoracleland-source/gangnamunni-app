<?php
/**
 * 링크 미리보기(카카오톡 등) 메타 태그. <head> 안에서 include 한다.
 * Next(seoulbeauty-web/src/app/layout.tsx)와 같은 이미지·제목을 쓴다. 이미지는 절대 URL이어야 한다.
 *   $og_description  설명(없으면 $page_description, 그것도 없으면 기본 문구)
 *   $page_canonical  있으면 og:url 로 쓴다
 */
$og_meta_description = !empty($og_description) ? $og_description : (!empty($page_description) ? $page_description : '서울미녀 — 병원 찾기');
?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="서울미녀">
    <meta property="og:title" content="서울미녀">
    <meta property="og:description" content="<?php echo htmlspecialchars((string)$og_meta_description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="https://seoulbeauty.app/assets/images/og-seoulbeauty.jpg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
<?php if (!empty($page_canonical)): ?>
    <meta property="og:url" content="<?php echo htmlspecialchars((string)$page_canonical, ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
