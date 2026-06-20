# Apache VirtualProtect 에러 해결 가이드

## 🚨 현재 에러

```
VirtualProtect() failed [87] 매개 변수가 틀립니다
```

**원인**: PHP 7.4 DLL과 Apache의 Visual C++ 런타임 버전 불일치

---

## ✅ 해결 방법 1: LoadFile 제거 (시도 완료)

LoadFile 라인을 제거하고 LoadModule만 사용하도록 수정했습니다.

**수정된 설정**:
```apache
# PHP 7.4 (For NFOR compatibility)
LoadModule php_module "C:/php74/php7apache2_4.dll"
```

### 테스트
1. XAMPP Control Panel에서 Apache Start 클릭
2. 성공하면 → 완료!
3. 실패하면 → 아래 "해결 방법 2" 진행

---

## ✅ 해결 방법 2: Visual C++ 런타임 설치

PHP 7.4는 **Visual C++ 2015-2019 Redistributable (VC15)** 필요

### 다운로드 및 설치

#### 64-bit (권장)
```
https://aka.ms/vs/17/release/vc_redist.x64.exe
```

#### 32-bit
```
https://aka.ms/vs/17/release/vc_redist.x86.exe
```

### 설치 후
1. 컴퓨터 재시작 (권장)
2. XAMPP Control Panel에서 Apache 시작

---

## ✅ 해결 방법 3: 임시로 PHP 8 사용 (빠른 해결)

PHP 7.4가 작동하지 않으면 일단 PHP 8로 되돌려서 시스템을 작동시킨 후, PHP 호환성 문제를 다른 방법으로 해결합니다.

### 3-1. PHP 8로 되돌리기

파일: `C:\xampp\apache\conf\extra\httpd-xampp.conf`

```apache
#
# PHP-Module setup
#
# PHP 8.x (Original XAMPP)
LoadFile "C:/xampp/php/php8ts.dll"
LoadFile "C:/xampp/php/libpq.dll"
LoadFile "C:/xampp/php/libsqlite3.dll"
LoadModule php_module "C:/xampp/php/php8apache2_4.dll"

# PHP 7.4 (비활성화)
#LoadModule php_module "C:/php74/php7apache2_4.dll"

<IfModule php_module>
    PHPINIDir "C:/xampp/php"
</IfModule>
```

### 3-2. Apache 재시작
XAMPP Control Panel → Apache Start

### 3-3. PHP 8에서 NFOR 작동시키기

PHP 7.0 → 8.x 마이그레이션은 코드 수정이 필요하지만, 대부분의 NFOR 코드는 작동할 가능성이 높습니다.

**테스트**:
```
http://carebaby.seoulbeauty.local/test_connection.php
```

만약 PHP 8에서도 작동한다면:
- 광고 시스템 페이지 접속
- 기본 기능 테스트
- 에러 발생 시 수정

---

## ✅ 해결 방법 4: PHP 7.4를 XAMPP php 폴더로 복사

XAMPP의 기본 PHP 폴더를 PHP 7.4로 교체합니다.

### 주의: 백업 필수!

```powershell
# 1. 기존 PHP 8 백업
Rename-Item "C:\xampp\php" "C:\xampp\php_8.2_backup"

# 2. PHP 7.4 복사
Copy-Item "C:\php74" "C:\xampp\php" -Recurse

# 3. httpd-xampp.conf 원래대로
# (PHP 8 설정 그대로 사용 - 경로만 C:/xampp/php)
```

파일: `C:\xampp\apache\conf\extra\httpd-xampp.conf`
```apache
# PHP-Module setup (기본 XAMPP 설정 사용)
LoadFile "C:/xampp/php/php7ts.dll"
LoadFile "C:/xampp/php/libpq.dll"
LoadFile "C:/xampp/php/libsqlite3.dll"
LoadModule php_module "C:/xampp/php/php7apache2_4.dll"

<IfModule php_module>
    PHPINIDir "C:/xampp/php"
</IfModule>
```

### 장점
- XAMPP 기본 구조 유지
- 설정 파일 수정 최소화

### 단점
- PHP 8로 쉽게 되돌리기 어려움 (폴더 이름 변경 필요)

---

## 🔍 현재 상태 진단

### Apache 시작 실패 확인

```bash
# 에러 로그 확인
tail -50 "C:\xampp\apache\logs\error.log"
```

### 예상되는 에러 메시지

#### 1. VirtualProtect() failed [87]
→ **해결방법 2** (VC++ 런타임 설치)

#### 2. Cannot load ... php7ts.dll
→ 파일 경로 확인 또는 **해결방법 4** (XAMPP 폴더로 복사)

#### 3. The specified module could not be found
→ 의존성 DLL 누락, **해결방법 2** (VC++ 런타임)

---

## 📋 권장 순서

### 단계 1: LoadFile 제거로 시도 (이미 완료)
```
XAMPP Control Panel → Apache Start
```

### 단계 2: 실패 시 → PHP 8로 임시 복구
Apache를 일단 작동시킨 후, PHP 8에서 테스트

### 단계 3: VC++ 런타임 설치
다운로드: https://aka.ms/vs/17/release/vc_redist.x64.exe

### 단계 4: PHP 7.4 재시도
VC++ 설치 후 다시 PHP 7.4 설정

### 단계 5: 안되면 → PHP 8에서 계속 사용
대부분의 NFOR 코드는 PHP 8에서도 작동

---

## 🎯 빠른 복구 스크립트

### PHP 8로 되돌리기 (PowerShell)

```powershell
# httpd-xampp.conf 파일 읽기
$configFile = "C:\xampp\apache\conf\extra\httpd-xampp.conf"
$content = Get-Content $configFile -Raw

# PHP 7.4 주석 처리, PHP 8 활성화
$content = $content -replace '(?m)^LoadModule php_module "C:/php74/php7apache2_4\.dll"', '#LoadModule php_module "C:/php74/php7apache2_4.dll"'
$content = $content -replace '(?m)^#LoadFile "C:/xampp/php/php8ts\.dll"', 'LoadFile "C:/xampp/php/php8ts.dll"'
$content = $content -replace '(?m)^#LoadFile "C:/xampp/php/libpq\.dll"', 'LoadFile "C:/xampp/php/libpq.dll"'
$content = $content -replace '(?m)^#LoadFile "C:/xampp/php/libsqlite3\.dll"', 'LoadFile "C:/xampp/php/libsqlite3.dll"'
$content = $content -replace '(?m)^#LoadModule php_module "C:/xampp/php/php8apache2_4\.dll"', 'LoadModule php_module "C:/xampp/php/php8apache2_4.dll"'
$content = $content -replace '(?m)^    PHPINIDir "C:/php74"', '    PHPINIDir "C:/xampp/php"'

# 저장
Set-Content $configFile $content

Write-Host "PHP 8로 설정 변경 완료. XAMPP에서 Apache를 시작하세요."
```

---

## 💡 최종 권장사항

### 옵션 A: PHP 7.4 사용 (이상적)
1. VC++ 2015-2019 런타임 설치
2. Apache 재시작
3. 정상 작동 확인

### 옵션 B: PHP 8 사용 (실용적)
1. PHP 8로 설정 복구
2. Apache 시작
3. 광고 시스템 테스트
4. 에러 발생 시 개별 수정

**대부분의 경우 PHP 8에서도 작동**합니다:
- mysqli 함수: 계속 지원됨
- 기본 PHP 문법: 호환됨
- NFOR 프레임워크: 대부분 호환

PHP 7 → 8 주요 변경사항은 엄격한 타입 체크와 일부 deprecated 함수인데, NFOR 코드는 대부분 영향을 받지 않습니다.

---

**작성일**: 2025-12-30
**에러**: VirtualProtect() failed [87]
**상태**: LoadFile 제거 완료, 재시작 대기
