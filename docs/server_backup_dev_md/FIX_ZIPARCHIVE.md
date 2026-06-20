# ZipArchive 문제 해결

## 🔍 패키지 검색 및 설치

### 1단계: 사용 가능한 PHP zip 패키지 찾기
```bash
# PHP 8.3 관련 패키지 검색
yum search php | grep -i zip

# 또는
dnf search php | grep -i zip

# 설치된 PHP 버전 확인
php83 -v
```

### 2단계: 가능한 패키지 이름들
```bash
# 시도 1
yum install php-zip

# 시도 2
yum install php83-pecl-zip

# 시도 3
dnf install php-pecl-zip

# 시도 4 (Remi repository)
yum install php83-php-zip
```

---

## 🚀 가장 빠른 해결책: backup.php 비활성화

### 방법 A: 파일 이름 변경 (임시 비활성화)
```bash
cd /var/www/html/demo/hospital/kivicare-laravel-web-v1.9.0

# backup.php 임시 비활성화
mv config/backup.php config/backup.php.disabled

# 캐시 삭제
php83 artisan config:clear
php83 artisan cache:clear
php83 artisan optimize:clear

# 웹 서버 재시작
systemctl restart httpd

# 사이트 확인
echo "✅ Check https://kivicare.seoulbeauty.app"
```

### 방법 B: backup.php 파일 수정
```bash
cd /var/www/html/demo/hospital/kivicare-laravel-web-v1.9.0
nano config/backup.php
```

**Line 141 근처 찾기 (Ctrl + W, 검색: ZipArchive)**

**변경 전:**
```php
'default_zip' => ZipArchive::class,
```

**변경 후:**
```php
'default_zip' => null,  // ZipArchive::class,
```

저장: Ctrl + O → Enter → Ctrl + X

```bash
php83 artisan config:clear
php83 artisan cache:clear
systemctl restart httpd
```

---

## 📋 즉시 실행할 명령어 (복사해서 실행)

```bash
cd /var/www/html/demo/hospital/kivicare-laravel-web-v1.9.0

# 백업 설정 비활성화
mv config/backup.php config/backup.php.disabled

# 캐시 완전 삭제
php83 artisan config:clear
php83 artisan cache:clear
php83 artisan route:clear
php83 artisan view:clear
php83 artisan optimize:clear

# 웹 서버 재시작
systemctl restart httpd

# 완료
echo "=========================================="
echo "✅ Backup disabled. Check the site now!"
echo "=========================================="
```

---

## 🔍 PHP 확장 확인

### 현재 설치된 PHP 확장 목록 보기
```bash
php83 -m | grep -i zip
```

**출력이 없다면** → zip 확장이 설치되지 않음

### PHP 8.3 패키지 검색
```bash
# 사용 가능한 모든 php83 패키지 보기
yum list available | grep php83

# 또는
dnf list available | grep php83
```

---

## 🎯 권장 순서

**1순위: backup.php 비활성화 (즉시 해결)**
```bash
mv config/backup.php config/backup.php.disabled
php83 artisan optimize:clear
systemctl restart httpd
```

**2순위: 올바른 패키지 이름 찾기**
```bash
yum list available | grep "php.*zip"
```

**3순위: 수동 설치**
```bash
# PECL로 설치 (php83-devel 필요)
pecl install zip
```

---

## ✅ 확인 방법

### 1. 사이트 접속
```
https://kivicare.seoulbeauty.app
```

### 2. 500 에러가 사라졌는지 확인

### 3. PHP 에러 로그 재확인
```bash
tail -20 storage/logs/laravel.log
```

**에러가 없어야 함!**

---

## 🚨 문제가 계속되면?

### Laravel 로그 다시 확인
```bash
tail -50 storage/logs/laravel.log
```

### Apache/Nginx 에러 로그
```bash
tail -50 /var/log/httpd/error_log
```

### PHP-FPM 로그 (있는 경우)
```bash
tail -50 /var/log/php-fpm/error.log
```
