# PHP 버전 호환성 문제 해결 가이드

## 🚨 문제 상황

**소스 코드**: PHP 7.0용으로 개발됨
**현재 시스템**: PHP 8.2.12
**결과**: 호환성 문제로 동작하지 않을 수 있음

---

## 📍 원인 분석

기존 운영 서버(`.seoulbeauty.app`)의 VirtualHost 설정:

```apache
# Use PHP 7.0
<FilesMatch \.php$>
    SetHandler "proxy:unix:/opt/php70/var/run/php-fpm.sock|fcgi://localhost"
</FilesMatch>
```

→ **PHP 7.0을 명시적으로 사용**하고 있음

현재 새로 만든 VirtualHost(`.seoulbeauty.local`):
- PHP 버전 지정 없음
- 시스템 기본값(PHP 8.2.12) 사용

---

## ✅ 해결 방법 (2가지)

### 방법 1: Windows에서 PHP 7.x 설치 및 사용 (권장)

#### 1단계: PHP 7.0 다운로드 및 설치

```powershell
# PHP 7.0.33 다운로드 (마지막 7.0 버전)
# https://windows.php.net/downloads/releases/archives/

# 또는 PHP 7.4 (더 나은 호환성 + 보안 업데이트)
# https://windows.php.net/downloads/releases/php-7.4.33-Win32-vc15-x64.zip
```

**설치 경로 예시**: `C:\php70\`

#### 2단계: Apache에 PHP 7.0 모듈 등록

파일: `H:\carebaby_project\server_backup_dev\httpd\conf\httpd.conf`

기존 PHP 모듈 주석 처리 후 PHP 7.0 추가:

```apache
# PHP 8.x 모듈 주석 처리
#LoadModule php_module "C:/php/php8apache2_4.dll"
#AddHandler application/x-httpd-php .php
#PHPIniDir "C:/php"

# PHP 7.0 모듈 추가
LoadModule php7_module "C:/php70/php7apache2_4.dll"
AddHandler application/x-httpd-php .php
PHPIniDir "C:/php70"
```

#### 3단계: Apache 재시작

```
XAMPP Control Panel → Apache → Stop → Start
```

#### 4단계: PHP 버전 확인

```
http://carebaby.seoulbeauty.local/test_connection.php
```

→ PHP 7.0.x 표시되어야 함

---

### 방법 2: 코드를 PHP 8.x 호환으로 수정 (비권장 - 작업량 많음)

PHP 7.0 → PHP 8.x 마이그레이션 시 수정 필요 사항:

#### 주요 변경 사항

1. **함수 파라미터 타입 힌트**
   ```php
   // PHP 7.0
   function test($param = null) { }

   // PHP 8.x (nullable 타입)
   function test(?string $param = null) { }
   ```

2. **mysqli 함수 반환값 변경**
   ```php
   // PHP 7.0
   $result = mysqli_query($db, $query);
   if ($result === false) { }

   // PHP 8.x (더 엄격한 타입 체크)
   $result = mysqli_query($db, $query);
   if ($result === false || $result === null) { }
   ```

3. **배열/문자열 오프셋 접근**
   ```php
   // PHP 7.0 (경고만 발생)
   $value = $array['key'];

   // PHP 8.x (더 엄격, 에러 발생)
   $value = $array['key'] ?? null;
   ```

4. **__serialize() / __unserialize() 메서드 추가**

**하지만 이 방법은 비권장**:
- 수정할 파일이 너무 많음 (15개 파일, 5,700+ 줄)
- 테스트 및 검증 필요
- 기존 운영 서버와 동일 환경 유지가 어려움

---

## 🎯 권장 해결 방법

### 최선의 선택: PHP 7.4 설치

**이유**:
1. PHP 7.0과 거의 100% 호환
2. 보안 업데이트 포함 (PHP 7.0은 EOL)
3. PHP 8.x로의 점진적 마이그레이션 경로 제공
4. Windows에서 쉽게 설치 가능

### 설치 단계

#### 1. PHP 7.4 다운로드

```
https://windows.php.net/downloads/releases/php-7.4.33-Win32-vc15-x64.zip
```

#### 2. 압축 해제

```
C:\php74\ 에 압축 해제
```

#### 3. php.ini 설정

```powershell
# php.ini-development를 php.ini로 복사
cd C:\php74
copy php.ini-development php.ini
```

**php.ini 필수 설정**:

```ini
; 확장 모듈 디렉토리
extension_dir = "C:/php74/ext"

; 필수 확장 모듈 활성화
extension=mysqli
extension=mbstring
extension=openssl
extension=curl
extension=gd2
extension=fileinfo

; 한국 시간대
date.timezone = Asia/Seoul

; 업로드 설정
upload_max_filesize = 50M
post_max_size = 50M

; 세션 저장 경로
session.save_path = "C:/php74/tmp"
```

#### 4. tmp 디렉토리 생성

```powershell
mkdir C:\php74\tmp
```

#### 5. Apache httpd.conf 수정

파일: `H:\carebaby_project\server_backup_dev\httpd\conf\httpd.conf`

**기존 PHP 모듈 찾기** (Ctrl+F: "LoadModule php"):

```apache
# 기존 PHP 8.x 주석 처리
#LoadModule php_module "C:/xampp/php/php8apache2_4.dll"
#AddHandler application/x-httpd-php .php
#PHPIniDir "C:/xampp/php"

# PHP 7.4 추가
LoadModule php7_module "C:/php74/php7apache2_4.dll"
AddHandler application/x-httpd-php .php
PHPIniDir "C:/php74"
```

#### 6. Apache 재시작

```
XAMPP Control Panel → Apache → Stop (5초 대기) → Start
```

#### 7. 확인

```
http://carebaby.seoulbeauty.local/test_connection.php
```

**예상 결과**:
```
PHP Version: 7.4.33
✓ DB 연결 성공!
```

---

## 🔍 문제 진단

### 현재 PHP 버전 확인

#### 방법 1: 웹에서 확인
```
http://carebaby.seoulbeauty.local/test_connection.php
```

#### 방법 2: 명령줄에서 확인
```bash
php -v
```

### PHP 로그 확인

**Apache 에러 로그**:
```
H:\carebaby_project\server_backup_dev\httpd\logs\carebaby-paikpc-error.log
```

**PHP 에러**:
```
[PHP Fatal error]
[PHP Warning]
[PHP Notice]
```

---

## 📋 체크리스트

### PHP 7.4 설치 완료 체크

- [ ] PHP 7.4.33 다운로드 완료
- [ ] C:\php74\ 에 압축 해제
- [ ] php.ini 설정 완료
- [ ] tmp 디렉토리 생성
- [ ] httpd.conf 수정 (LoadModule php7_module)
- [ ] Apache 재시작
- [ ] test_connection.php에서 PHP 7.4.33 확인
- [ ] DB 연결 성공 확인
- [ ] 광고 관리 페이지 접속 확인

---

## ⚠️ 주의사항

### 1. 시스템 PATH 환경 변수

PHP를 명령줄에서도 사용하려면:

```
시스템 환경 변수 편집 → PATH → C:\php74 추가
```

### 2. Apache 모듈 호환성

**중요**: `php7apache2_4.dll` 파일이 PHP 7.4 zip 파일에 포함되어 있어야 함

만약 없다면:
- VC15 x64 Thread Safe 버전 다운로드
- Non Thread Safe (NTS) 버전은 Apache 모듈 없음

### 3. 기존 XAMPP PHP와 충돌

XAMPP에 이미 PHP가 포함되어 있다면:
- XAMPP의 PHP를 사용하지 않도록 httpd.conf 수정 필요
- 또는 XAMPP PHP를 7.4로 교체

---

## 🚀 빠른 설치 스크립트 (PowerShell)

```powershell
# 관리자 권한으로 실행

# 1. PHP 7.4 다운로드 (수동으로 다운로드 후 압축 해제하세요)
# URL: https://windows.php.net/downloads/releases/php-7.4.33-Win32-vc15-x64.zip

# 2. php.ini 생성
Copy-Item "C:\php74\php.ini-development" "C:\php74\php.ini"

# 3. tmp 디렉토리 생성
New-Item -ItemType Directory -Force -Path "C:\php74\tmp"

# 4. Apache 재시작 (XAMPP 경로는 실제 경로로 변경)
& "C:\xampp\apache\bin\httpd.exe" -k stop
Start-Sleep -Seconds 5
& "C:\xampp\apache\bin\httpd.exe" -k start
```

---

## 📞 다음 단계

1. **PHP 7.4 설치**
   - https://windows.php.net/downloads/releases/php-7.4.33-Win32-vc15-x64.zip 다운로드
   - C:\php74\ 에 압축 해제

2. **Apache 설정 수정**
   - httpd.conf에서 LoadModule php7_module 추가
   - php.ini 설정

3. **Apache 재시작**
   - XAMPP Control Panel 사용

4. **테스트**
   - test_connection.php 접속하여 PHP 7.4 확인

5. **광고 관리 시스템 접속**
   - http://carebaby.seoulbeauty.local/admin/ad_list.php

---

**작성일**: 2025-12-30
