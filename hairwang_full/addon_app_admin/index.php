<?php
/**
 * ========================================
 * 앱 관리 프로그램 - 메인 진입 페이지
 * ========================================
 *
 * @file    index.php
 * @created 2026-01-29
 * @version 1.0.0
 */

// 설치 확인
$install_check_file = __DIR__ . '/install/.installed';
$is_installed = file_exists($install_check_file);

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>앱 관리 프로그램</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 60px;
            max-width: 700px;
            width: 100%;
            text-align: center;
        }

        h1 {
            font-size: 48px;
            margin-bottom: 20px;
            color: #333;
        }

        .subtitle {
            font-size: 18px;
            color: #666;
            margin-bottom: 40px;
        }

        .status {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            border-left: 5px solid <?php echo $is_installed ? '#28a745' : '#ffc107'; ?>;
        }

        .status h2 {
            color: <?php echo $is_installed ? '#28a745' : '#ffc107'; ?>;
            margin-bottom: 10px;
        }

        .btn {
            display: inline-block;
            padding: 20px 40px;
            margin: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            transition: all 0.3s;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.6);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
            text-align: left;
        }

        .info-box h3 {
            color: #0d47a1;
            margin-bottom: 10px;
        }

        .info-box ul {
            margin-left: 20px;
            line-height: 1.8;
        }

        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">📱</div>
        <h1>앱 관리 프로그램</h1>
        <p class="subtitle">Hairwang Mobile App Management System</p>

        <?php if (!$is_installed): ?>
        <!-- 미설치 상태 -->
        <div class="status">
            <h2>⚠️ 설치가 필요합니다</h2>
            <p>앱 관리 프로그램을 사용하기 전에 먼저 설치해야 합니다.</p>
        </div>

        <div class="info-box">
            <h3>📋 설치 단계</h3>
            <ul>
                <li>1. 데이터베이스 테이블 생성</li>
                <li>2. 기본 설정 구성</li>
                <li>3. 관리자 계정 생성</li>
                <li>4. 앱 버전 초기화</li>
            </ul>
        </div>

        <a href="install.php" class="btn">
            🚀 지금 설치하기
        </a>

        <a href="install_check.php" class="btn btn-secondary">
            🔍 설치 상태 확인
        </a>

        <?php else: ?>
        <!-- 설치 완료 상태 -->
        <div class="status">
            <h2>✅ 설치 완료</h2>
            <p>앱 관리 프로그램을 사용할 준비가 되었습니다.</p>
        </div>

        <div class="info-box">
            <h3>✨ 주요 기능</h3>
            <ul>
                <li>📱 앱 버전 관리 (Android/iOS)</li>
                <li>📢 공지사항 관리</li>
                <li>📊 사용자 통계 및 분석</li>
                <li>🔔 푸시 알림 히스토리</li>
                <li>⚙️ 앱 설정 관리</li>
            </ul>
        </div>

        <a href="admin/" class="btn btn-success">
            🎯 관리자 대시보드 열기
        </a>

        <a href="install_check.php" class="btn btn-secondary">
            🔍 시스템 상태 확인
        </a>

        <?php endif; ?>

        <div class="info-box" style="margin-top: 40px;">
            <h3>📚 문서</h3>
            <ul>
                <li><a href="README.md" target="_blank">README.md</a> - 프로젝트 개요</li>
                <li><a href="INSTALL.md" target="_blank">INSTALL.md</a> - 설치 가이드</li>
                <li><a href="../addon_app_admin_개발_완료_보고서.md" target="_blank">개발 완료 보고서</a></li>
            </ul>
        </div>

        <p style="color: #999; font-size: 14px; margin-top: 30px;">
            Hairwang App Management System v1.0<br>
            개발 완료: 2026-01-19
        </p>
    </div>
</body>
</html>
