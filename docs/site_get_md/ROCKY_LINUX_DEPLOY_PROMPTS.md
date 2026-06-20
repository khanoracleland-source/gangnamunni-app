# Rocky Linux 서버 배포 작업 프롬프트

**작성일**: 2026-02-06
**대상 서버**: Rocky Linux
**프로젝트**: gangnamunni (강남언니 클론)

---

## 작업 순서

```
1. [로컬] GitHub 레포 생성 및 푸시
2. [로컬] DB 덤프 파일 생성
3. [서버] 환경 확인 및 설정
4. [서버] GitHub에서 소스 클론
5. [서버] DB 임포트
6. [서버] 웹서버 설정
7. [서버] 테스트
```

---

## 프롬프트 1: GitHub 레포 생성 (로컬)

```
H:\carebaby_project\server_backup_dev\hairwang_dev_sns_shop_2026_01_18\gangnamunni 폴더를 GitHub에 올려줘.

요구사항:
1. 새 레포 생성: gangnamunni-service
2. .gitignore 생성:
   - dbconfig.php
   - *.log
   - .DS_Store
   - Thumbs.db
   - node_modules/
   - vendor/

3. dbconfig.php.example 생성 (실제 비밀번호 제거)
4. README.md 생성
5. 초기 커밋 및 푸시

GitHub 계정 정보는 이미 설정되어 있다고 가정.
완료 후 레포 URL 알려줘.
```

---

## 프롬프트 2: DB 덤프 생성 (로컬)

```
hairwang DB에서 gangnamunni 서비스에 필요한 테이블만 덤프해줘.

DB 정보:
- Host: 124.111.85.242
- User: root
- Password: <REMOVED_DB_PASSWORD>
- Database: hairwang

덤프할 테이블:
- gangnamunni_* (12개 테이블)
- hospital_* (5개 테이블)
- navigation_menu

저장 위치: H:\carebaby_project\site_get\sql_dumps\
파일명: gangnamunni_essential_2026_02_06.sql

옵션:
- 테이블 구조 + 데이터
- UTF8MB4 인코딩
- DROP TABLE IF EXISTS 포함
```

---

## 프롬프트 3: 서버 환경 확인 (서버)

```
Rocky Linux 서버 환경을 확인해줘.

확인 항목:
1. OS 버전: cat /etc/os-release
2. PHP 버전: php -v
3. MySQL/MariaDB 버전: mysql --version
4. Apache/Nginx 상태: systemctl status httpd 또는 nginx
5. 방화벽 상태: firewall-cmd --list-all
6. SELinux 상태: getenforce

웹 루트 디렉토리 확인:
- /var/www/html/ 또는 /home/사용자/public_html/

결과를 정리해서 보여줘.
```

---

## 프롬프트 4: 서버에 소스 배포 (서버)

```
GitHub에서 gangnamunni 소스를 서버에 배포해줘.

작업:
1. 웹 루트로 이동
   cd /var/www/html/

2. GitHub에서 클론
   git clone https://github.com/USERNAME/gangnamunni-service.git gangnamunni

3. 권한 설정
   chown -R apache:apache gangnamunni/
   chmod -R 755 gangnamunni/

4. dbconfig.php 생성
   서버 DB 정보로 설정 (별도 제공)

5. 설정 확인
   - config.php 확인
   - 경로 설정 확인

완료 후 파일 목록 보여줘.
```

---

## 프롬프트 5: DB 설정 (서버)

```
서버에 gangnamunni DB를 설정해줘.

작업:
1. MySQL 접속
   mysql -u root -p

2. 데이터베이스 생성
   CREATE DATABASE gangnamunni CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

3. 사용자 생성 및 권한 부여
   CREATE USER 'gangnamunni'@'localhost' IDENTIFIED BY '비밀번호';
   GRANT ALL PRIVILEGES ON gangnamunni.* TO 'gangnamunni'@'localhost';
   FLUSH PRIVILEGES;

4. SQL 덤프 임포트
   mysql -u root -p gangnamunni < /path/to/gangnamunni_essential.sql

5. 테이블 확인
   SHOW TABLES;

완료 후 테이블 목록 보여줘.
```

---

## 프롬프트 6: 웹서버 설정 (서버 - Apache)

```
Apache에서 gangnamunni 가상호스트를 설정해줘.

작업:
1. 가상호스트 설정 파일 생성
   /etc/httpd/conf.d/gangnamunni.conf

내용:
<VirtualHost *:80>
    ServerName gangnamunni.도메인.com
    DocumentRoot /var/www/html/gangnamunni

    <Directory /var/www/html/gangnamunni>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog /var/log/httpd/gangnamunni-error.log
    CustomLog /var/log/httpd/gangnamunni-access.log combined
</VirtualHost>

2. .htaccess 확인 (mod_rewrite)

3. Apache 재시작
   systemctl restart httpd

4. 방화벽 확인
   firewall-cmd --add-service=http --permanent
   firewall-cmd --reload

완료 후 접속 테스트.
```

---

## 프롬프트 7: 웹서버 설정 (서버 - Nginx)

```
Nginx에서 gangnamunni 설정을 해줘.

작업:
1. 설정 파일 생성
   /etc/nginx/conf.d/gangnamunni.conf

내용:
server {
    listen 80;
    server_name gangnamunni.도메인.com;
    root /var/www/html/gangnamunni;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php-fpm/www.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}

2. 설정 테스트
   nginx -t

3. Nginx 재시작
   systemctl restart nginx

완료 후 접속 테스트.
```

---

## 프롬프트 8: SSL 설정 (서버)

```
Let's Encrypt로 SSL 인증서를 설정해줘.

작업:
1. certbot 설치 (없는 경우)
   dnf install certbot python3-certbot-apache  # Apache
   dnf install certbot python3-certbot-nginx   # Nginx

2. 인증서 발급
   certbot --apache -d gangnamunni.도메인.com  # Apache
   certbot --nginx -d gangnamunni.도메인.com   # Nginx

3. 자동 갱신 확인
   certbot renew --dry-run

완료 후 https 접속 테스트.
```

---

## 프롬프트 9: 이미지 업로드 (서버)

```
HTML to Image 변환 결과를 서버에 업로드해줘.

로컬 경로: H:\carebaby_project\site_get\output\{사이트}\screenshots\
서버 경로: /var/www/html/gangnamunni/assets/screenshots/

작업:
1. 서버에 디렉토리 생성
   mkdir -p /var/www/html/gangnamunni/assets/screenshots/gangnamunni
   mkdir -p /var/www/html/gangnamunni/assets/screenshots/yeoshin
   mkdir -p /var/www/html/gangnamunni/assets/screenshots/babitalk

2. SCP로 파일 업로드
   scp -r screenshots/* user@server:/var/www/html/gangnamunni/assets/screenshots/

3. 권한 설정
   chown -R apache:apache /var/www/html/gangnamunni/assets/screenshots/
   chmod -R 755 /var/www/html/gangnamunni/assets/screenshots/

완료 후 이미지 접속 테스트.
```

---

## 프롬프트 10: 최종 테스트 (서버)

```
gangnamunni 서비스 전체 테스트를 해줘.

테스트 항목:
1. 메인 페이지 접속
   curl -I https://gangnamunni.도메인.com/

2. DB 연결 테스트
   PHP에서 DB 연결 확인

3. 페이지별 테스트
   - /index.php (메인)
   - /hospitals.php (병원 목록)
   - /detail.php?id=1 (병원 상세)
   - /events.php (이벤트)
   - /tips.php (칼럼)
   - /booking.php (예약)

4. 관리자 페이지
   - /admin_theme.php

5. 이미지 로딩 확인
   - /assets/screenshots/ 경로 확인

6. 에러 로그 확인
   tail -f /var/log/httpd/gangnamunni-error.log

문제 있으면 에러 내용 알려줘.
```

---

## 빠른 참조: 서버 정보 템플릿

```
서버 정보:
- IP:
- SSH Port: 22
- User:
- 웹 루트: /var/www/html/gangnamunni/

DB 정보:
- Host: localhost
- Database: gangnamunni
- User: gangnamunni
- Password:

도메인:
- 메인:
- SSL: Let's Encrypt
```

---

## 트러블슈팅

### SELinux 문제
```bash
# 웹서버가 DB 연결 못할 때
setsebool -P httpd_can_network_connect_db 1

# 파일 쓰기 권한 문제
chcon -R -t httpd_sys_rw_content_t /var/www/html/gangnamunni/data/
```

### 방화벽 문제
```bash
firewall-cmd --add-port=80/tcp --permanent
firewall-cmd --add-port=443/tcp --permanent
firewall-cmd --reload
```

### PHP 확장 모듈 부족
```bash
dnf install php-mysql php-gd php-mbstring php-json
systemctl restart php-fpm
```

---

**작성일**: 2026-02-06
**다음 단계**: 프롬프트 1부터 순서대로 실행
