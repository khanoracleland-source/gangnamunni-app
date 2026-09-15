<?php
/**
 * 점검 중 페이지
 */

require_once __DIR__ . '/maintenance_config.php';

// 점검 모드가 아니면 메인으로 리다이렉트
if (!isMaintenanceMode()) {
    header('Location: index.php');
    exit;
}

$config = getMaintenanceConfig();
$message = $config['message'] ?? '시스템 점검 중입니다';
$start_time = $config['start_time'] ?? null;
$end_time = $config['end_time'] ?? null;
$contact_email = $config['contact_email'] ?? 'support@seoulbeauty.app';

$progress = getMaintenanceProgress();
$remaining = getRemainingMinutes();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="30">
    <title>시스템 점검 중 | 서울미녀</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Malgun Gothic', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 24px;
            padding: 60px 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        h1 {
            font-size: 32px;
            color: #333;
            margin-bottom: 20px;
        }

        .message {
            font-size: 18px;
            color: #666;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .time-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .time-info p {
            margin: 8px 0;
            color: #555;
            font-size: 15px;
        }

        .time-info strong {
            color: #667eea;
        }

        .progress-container {
            width: 100%;
            height: 30px;
            background: #e0e0e0;
            border-radius: 15px;
            overflow: hidden;
            margin: 30px 0;
            position: relative;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            width: <?= $progress ?>%;
            transition: width 1s ease;
            position: relative;
            overflow: hidden;
        }

        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .remaining-time {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
            margin: 20px 0;
        }

        .footer {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #e0e0e0;
        }

        .footer p {
            color: #999;
            font-size: 14px;
            margin: 5px 0;
        }

        .auto-refresh {
            font-size: 13px;
            color: #aaa;
            margin-top: 20px;
        }

        .spinner {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid #e0e0e0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            vertical-align: middle;
            margin-right: 5px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 640px) {
            .container {
                padding: 40px 30px;
            }

            h1 {
                font-size: 24px;
            }

            .icon {
                font-size: 60px;
            }

            .message {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>

        <h1>시스템 점검 중</h1>

        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>

        <?php if ($start_time && $end_time): ?>
        <div class="time-info">
            <p><strong>점검 시간:</strong> <?= date('H:i', strtotime($start_time)) ?> ~ <?= date('H:i', strtotime($end_time)) ?></p>
            <p><strong>현재 시간:</strong> <?= date('H:i') ?></p>
        </div>

        <div class="progress-container">
            <div class="progress-bar"></div>
            <div class="progress-text"><?= $progress ?>%</div>
        </div>

        <?php if ($remaining > 0): ?>
        <div class="remaining-time">
            약 <?= $remaining ?>분 남음
        </div>
        <?php else: ?>
        <div class="remaining-time">
            곧 완료됩니다
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <p style="font-size: 16px; color: #666; margin-top: 20px;">
            잠시 후 다시 접속해주세요
        </p>

        <div class="footer">
            <p>문의: <?= htmlspecialchars($contact_email) ?></p>
            <p class="auto-refresh">
                <span class="spinner"></span>
                자동 새로고침: 30초 후
            </p>
        </div>
    </div>

    <script>
        // 진행률 바 애니메이션
        document.addEventListener('DOMContentLoaded', function() {
            const progressBar = document.querySelector('.progress-bar');
            if (progressBar) {
                setTimeout(() => {
                    progressBar.style.width = '<?= $progress ?>%';
                }, 100);
            }
        });

        // 카운트다운 타이머
        let countdown = 30;
        const refreshText = document.querySelector('.auto-refresh');

        setInterval(() => {
            countdown--;
            if (countdown <= 0) {
                location.reload();
            } else {
                refreshText.innerHTML = `<span class="spinner"></span>자동 새로고침: ${countdown}초 후`;
            }
        }, 1000);
    </script>
</body>
</html>
