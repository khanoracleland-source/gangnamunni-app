@echo off
REM ========================================
REM 크롬 브라우저로 모든 테스트 페이지 열기
REM ========================================
REM 작성일: 2026-01-29
REM ========================================

echo.
echo ========================================
echo  크롬 브라우저로 테스트 페이지 열기
echo ========================================
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
    echo [ERROR] 크롬 브라우저를 찾을 수 없습니다.
    echo.
    echo 크롬이 설치되어 있는지 확인해주세요.
    echo https://www.google.com/chrome/
    pause
    exit /b 1
)

echo [1/2] 크롬 브라우저 발견: %CHROME%
echo.

REM 웹서버 실행 여부 확인
echo [2/2] 웹서버 실행 상태 확인 중...
curl -s http://localhost:8080/ > nul 2>&1
if errorlevel 1 (
    echo.
    echo ========================================
    echo  [경고] 웹서버가 실행되지 않았습니다!
    echo ========================================
    echo.
    echo  웹서버를 먼저 실행해야 페이지를 열 수 있습니다.
    echo.
    echo  [선택하세요]
    echo  1. 지금 웹서버 실행하기 (start_server.bat)
    echo  2. 무시하고 크롬만 열기
    echo.
    choice /C 12 /N /M "선택 (1 또는 2): "

    if errorlevel 2 goto OPEN_CHROME
    if errorlevel 1 (
        echo.
        echo [웹서버 실행 중...]
        start "" "%~dp0start_server.bat"
        echo.
        echo 웹서버가 시작될 때까지 5초 대기 중...
        timeout /t 5 /nobreak > nul
    )
)

:OPEN_CHROME
echo.
echo ========================================
echo  크롬으로 모든 페이지 열기 시작
echo ========================================
echo.

REM START_HERE.html을 먼저 열기
echo [1/8] 시작 페이지 (START_HERE.html)
start "" %CHROME% "file:///%~dp0START_HERE.html"
timeout /t 1 /nobreak > nul

REM 메인 사이트
echo [2/8] 메인 사이트 (localhost:8080)
start "" %CHROME% "http://localhost:8080/"
timeout /t 1 /nobreak > nul

REM 데이터베이스 테스트
echo [3/8] 데이터베이스 테스트
start "" %CHROME% "http://localhost:8080/test_db_connection.php"
timeout /t 1 /nobreak > nul

REM 푸시 알림 대시보드
echo [4/8] 푸시 알림 대시보드
start "" %CHROME% "http://localhost:8080/new_http_v1_mars/login.php"
timeout /t 1 /nobreak > nul

REM 관리자 페이지
echo [5/8] 관리자 페이지
start "" %CHROME% "http://localhost:8080/adm/"
timeout /t 1 /nobreak > nul

REM SNS 로그인 테스트
echo [6/8] SNS 로그인 테스트
start "" %CHROME% "http://localhost:8080/api/sns/test_login.php"
timeout /t 1 /nobreak > nul

REM 앱 관리 프로그램
echo [7/8] 앱 관리 프로그램
start "" %CHROME% "http://localhost:8080/addon_app_admin/"
timeout /t 1 /nobreak > nul

REM API 문서
echo [8/8] 푸시 API 문서
start "" %CHROME% "http://localhost:8080/new_http_v1_mars/api_docs.php"

echo.
echo ========================================
echo  모든 페이지가 크롬으로 열렸습니다!
echo ========================================
echo.
echo  총 8개의 탭이 열렸습니다:
echo    1. START_HERE.html (시작 페이지)
echo    2. 메인 사이트
echo    3. 데이터베이스 테스트
echo    4. 푸시 알림 대시보드
echo    5. 관리자 페이지
echo    6. SNS 로그인 테스트
echo    7. 앱 관리 프로그램
echo    8. API 문서
echo.
echo  크롬 브라우저를 확인하세요!
echo ========================================
echo.

pause
