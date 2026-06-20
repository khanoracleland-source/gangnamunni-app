@echo off
REM ========================================
REM 원클릭 시작: 웹서버 실행 + 크롬으로 모든 페이지 열기
REM ========================================
REM 작성일: 2026-01-29
REM 이 파일 하나만 더블클릭하면 모든 것이 실행됩니다!
REM ========================================

color 0A
echo.
echo  ========================================
echo   ___  _   _   ___ __      __ ___  _  _  ___
echo  / __^|^| ^| ^| ^| ^|_ _^|\ \    / // _ \^| \^| ^|/ __^|
echo  \__ \^|  ^| ^|   ^| ^| \ \/\/ /^| ^(_^) ^| .\` ^| ^|_ \
echo  ^|___/^|_^| ^|_^| ^|___^| \_/\_/  \___/^|_^|\_^|\___^|
echo.
echo  ========================================
echo   Hairwang 프로젝트 원클릭 시작
echo  ========================================
echo.

REM 관리자 권한 확인 (선택사항)
net session >nul 2>&1
if %errorLevel% == 0 (
    echo [관리자 모드] 관리자 권한으로 실행 중
) else (
    echo [일반 모드] 일반 사용자 권한으로 실행 중
)
echo.

REM PHP 설치 확인
echo [1/4] PHP 설치 확인 중...
php -v > nul 2>&1
if errorlevel 1 (
    echo.
    echo [경고] PHP가 설치되어 있지 않거나 PATH에 등록되지 않았습니다.
    echo.
    echo PHP 없이는 웹서버를 실행할 수 없습니다.
    echo.
    echo [선택하세요]
    echo 1. PHP 설치 가이드 보기
    echo 2. 크롬으로 시작 페이지만 열기 (PHP 없이)
    echo 3. 종료
    echo.
    choice /C 123 /N /M "선택 (1, 2 또는 3): "

    if errorlevel 3 exit /b 1
    if errorlevel 2 goto OPEN_CHROME_ONLY
    if errorlevel 1 (
        echo.
        echo ========================================
        echo  PHP 설치 가이드
        echo ========================================
        echo.
        echo 1. PHP 다운로드: https://windows.php.net/download/
        echo    - Thread Safe 버전 선택
        echo    - ZIP 파일 다운로드
        echo.
        echo 2. C:\php 폴더에 압축 해제
        echo.
        echo 3. 시스템 환경 변수 PATH에 추가:
        echo    - 시스템 속성 ^> 환경 변수
        echo    - Path 변수에 "C:\php" 추가
        echo.
        echo 4. CMD 재시작 후 "php -v" 실행하여 확인
        echo.
        pause
        exit /b 1
    )
)

php -v | findstr "PHP"
echo.

REM 데이터베이스 연결 확인 (선택사항)
echo [2/4] 데이터베이스 연결 확인 중...
echo    MySQL 서버: 124.111.85.242:3306
echo    데이터베이스: hairwang
echo.

REM 웹서버 실행
echo [3/4] 웹서버 시작 중...
echo    포트: 8080
echo    문서 루트: %~dp0
echo.

REM 백그라운드로 웹서버 실행
start /B "" php -S localhost:8080 -t "%~dp0" > "%~dp0server.log" 2>&1

REM 웹서버 시작 대기
echo 웹서버 초기화 중... (5초)
timeout /t 5 /nobreak > nul

REM 웹서버 실행 확인
curl -s http://localhost:8080/ > nul 2>&1
if errorlevel 1 (
    echo.
    echo [오류] 웹서버 시작 실패
    echo 로그 파일을 확인하세요: server.log
    pause
    exit /b 1
)

echo [완료] 웹서버가 성공적으로 시작되었습니다!
echo.

:OPEN_CHROME_ONLY
REM 크롬으로 모든 페이지 열기
echo [4/4] 크롬 브라우저로 모든 페이지 열기...
echo.

REM 크롬 경로 찾기
set CHROME=""
if exist "C:\Program Files\Google\Chrome\Application\chrome.exe" (
    set CHROME="C:\Program Files\Google\Chrome\Application\chrome.exe"
) else if exist "C:\Program Files (x86)\Google\Chrome\Application\chrome.exe" (
    set CHROME="C:\Program Files (x86)\Google\Chrome\Application\chrome.exe"
) else if exist "%LOCALAPPDATA%\Google\Chrome\Application\chrome.exe" (
    set CHROME="%LOCALAPPDATA%\Google\Chrome\Application\chrome.exe"
) else (
    echo [경고] 크롬 브라우저를 찾을 수 없습니다.
    echo 수동으로 http://localhost:8080/ 에 접속하세요.
    pause
    exit /b 0
)

REM 모든 페이지 열기
echo 크롬으로 8개 페이지 열기 시작...
echo.

start "" %CHROME% "file:///%~dp0START_HERE.html"
timeout /t 1 /nobreak > nul

start "" %CHROME% "http://localhost:8080/"
timeout /t 1 /nobreak > nul

start "" %CHROME% "http://localhost:8080/test_db_connection.php"
timeout /t 1 /nobreak > nul

start "" %CHROME% "http://localhost:8080/new_http_v1_mars/login.php"
timeout /t 1 /nobreak > nul

start "" %CHROME% "http://localhost:8080/adm/"
timeout /t 1 /nobreak > nul

start "" %CHROME% "http://localhost:8080/api/sns/test_login.php"
timeout /t 1 /nobreak > nul

start "" %CHROME% "http://localhost:8080/addon_app_admin/"
timeout /t 1 /nobreak > nul

start "" %CHROME% "http://localhost:8080/new_http_v1_mars/api_docs.php"

echo.
echo ========================================
echo   시작 완료!
echo ========================================
echo.
echo  ✅ 웹서버 실행 중 (localhost:8080)
echo  ✅ 크롬 브라우저에서 8개 탭 열림
echo.
echo  [중요] 이 창을 닫으면 웹서버가 종료됩니다!
echo.
echo  웹서버를 중지하려면 [Ctrl+C]를 누르세요.
echo ========================================
echo.

REM 서버 로그 실시간 표시 (선택사항)
echo 서버 로그 모니터링 중... (새 요청이 여기에 표시됩니다)
echo.
tail -f "%~dp0server.log" 2>nul

REM tail 명령이 없으면 무한 대기
:WAIT_LOOP
timeout /t 60 /nobreak > nul
goto WAIT_LOOP
