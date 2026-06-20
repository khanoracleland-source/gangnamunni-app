@echo off
REM ========================================
REM Hairwang 프로젝트 웹서버 실행 스크립트
REM ========================================
REM PHP 내장 웹서버로 로컬 테스트 환경 구동
REM 작성일: 2026-01-29
REM ========================================

echo.
echo ========================================
echo  Hairwang 웹서버 시작
echo ========================================
echo.

REM 현재 디렉토리로 이동
cd /d "%~dp0"

REM 포트 번호 설정
set PORT=8080
set HOST=localhost

echo [1/3] 서버 설정 확인...
echo     - 호스트: %HOST%
echo     - 포트: %PORT%
echo     - 문서 루트: %CD%
echo.

REM PHP 버전 확인
php -v > nul 2>&1
if errorlevel 1 (
    echo [ERROR] PHP가 설치되어 있지 않거나 PATH에 등록되지 않았습니다.
    echo.
    echo PHP 설치 경로를 확인하고 시스템 환경 변수 PATH에 추가해주세요.
    echo 예: C:\php\php.exe
    pause
    exit /b 1
)

echo [2/3] PHP 버전 확인...
php -v | findstr "PHP"
echo.

echo [3/3] 웹서버 시작 중...
echo.
echo ========================================
echo  서버가 시작되었습니다!
echo ========================================
echo.
echo  메인 사이트: http://%HOST%:%PORT%/
echo  관리자 페이지: http://%HOST%:%PORT%/adm/
echo  푸시 대시보드: http://%HOST%:%PORT%/new_http_v1_mars/
echo  API 테스트: http://%HOST%:%PORT%/api/
echo.
echo  [Ctrl+C]를 눌러 서버를 중지할 수 있습니다.
echo ========================================
echo.

REM PHP 내장 웹서버 실행
php -S %HOST%:%PORT% -t "%CD%"

pause
