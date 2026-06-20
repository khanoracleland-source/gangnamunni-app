<?php
/**
 * 공통 헤더
 * 페이지마다 include하여 사용
 */

// 페이지 타이틀 기본값
$page_title = $page_title ?? (function_exists('site_name') ? site_name() : '서울미녀');
$show_back_btn = $show_back_btn ?? false;
$back_url = $back_url ?? 'index.php';
$header_title = $header_title ?? '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <?php if (function_exists('generateFaviconLinks')): ?>
    <?php echo generateFaviconLinks(); ?>
    <?php endif; ?>

    <style>
        :root {
            --color-primary: #AE1932;
            --color-primary-light: #FFEBEE;
            --color-primary-dark: #B71C1C;
            --color-text-primary: #333333;
            --color-text-secondary: #666666;
            --color-text-muted: #999999;
            --color-bg-white: #ffffff;
            --color-bg-light: #f8f8f8;
            --color-bg-gray: #eeeeee;
            --color-border: #f0f0f0;
            --color-star: #fbbf24;
            --color-tag-bg: #F0F4FF;
            --color-tag-text: #4A90E2;
            --color-kakao: #fee500;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Malgun Gothic', sans-serif;
            background: var(--color-bg-light);
            color: var(--color-text-primary);
            line-height: 1.6;
        }

        /* PC 중앙 정렬 컨테이너 */
        .page-container {
            max-width: 640px;
            margin: 0 auto;
            background: var(--color-bg-white);
            min-height: 100vh;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }

        /* 페이지 헤더 (글로벌 네비 아래) */
        .page-header {
            position: fixed;
            top: 56px;
            left: 50%;
            transform: translateX(-50%);
            width: 100%;
            max-width: 640px;
            height: 56px;
            background: white;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            padding: 0 16px;
            z-index: 99;
        }

        .back-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--color-text-primary);
            text-decoration: none;
            margin-right: 8px;
        }

        .page-title-header {
            flex: 1;
            font-size: 16px;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .page-header-spacer {
            height: 56px;
        }
    </style>

    <?php
    // 페이지별 추가 스타일 로드
    if (isset($page_styles) && is_array($page_styles)) {
        foreach ($page_styles as $style_file) {
            echo '<link rel="stylesheet" href="' . htmlspecialchars($style_file) . '">' . "\n";
        }
    }
    ?>
</head>
<body>
    <div class="page-container">
        <!-- 글로벌 네비게이션 (모든 페이지 공통) -->
        <?php include __DIR__ . '/global_nav.php'; ?>

        <!-- 페이지별 헤더 (뒤로가기 버튼, 페이지 타이틀) -->
        <?php if ($show_back_btn || $header_title): ?>
        <div class="page-header">
            <?php if ($show_back_btn): ?>
            <a href="<?php echo htmlspecialchars($back_url); ?>" class="back-btn">←</a>
            <?php endif; ?>

            <?php if ($header_title): ?>
            <div class="page-title-header"><?php echo htmlspecialchars($header_title); ?></div>
            <?php endif; ?>
        </div>

        <div class="page-header-spacer"></div>
        <?php endif; ?>

        <!-- 페이지 컨텐츠 시작 -->
