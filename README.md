# 서울미녀 (Seoul Beauty) — HMS + Gangnamunni + Hospital API

> **인수자 필독** — 이 README를 먼저 읽고 작업을 시작하세요.

## 📦 이 저장소란?

서울미녀 서비스 전체 서버 소스입니다.

| 구성 | 설명 |
|------|------|
| `hairwang_full/gangnamunni/` | **서울미녀 PHP 메인 사이트** (가장 많이 수정하는 폴더) |
| `hairwang_full/plugin/, theme/, bbs/` | Gnuboard 5 컴포넌트 |
| `hospital-api/Modules/` | **HMS API 핵심 모듈** (Laravel) |
| `app/, routes/, resources/` | InfyHMS Laravel 백엔드 |
| `public/` | 웹 루트 (Laravel DocumentRoot) |
| `.dev/` | **개발 환경 설정** (배포 스크립트·규칙·프롬프트) |

## 🖥️ 서버 정보

| 항목 | 정보 |
|------|------|
| 서버 IP | `5.104.82.35` (Contabo) |
| OS | Rocky Linux 9.7 |
| SSH 접속 | `ssh root@5.104.82.35` (비밀번호: <REMOVED_SSH_PASSWORD>) |
| 도메인 | https://hms.seoulbeauty.app |
| 서버 경로 | `/var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/` |
| DB | MariaDB `hms_saas` (root / <REMOVED_DB_PASSWORD>) |
| Apache 재시작 | `systemctl restart httpd` |
| HMS artisan 재시작 | `systemctl restart hms-artisan` |

## 🛠️ .dev/ 개발 환경 폴더

> 이 폴더를 먼저 확인하세요. 서버 접속 정보·배포 스크립트·작업 규칙이 모두 여기 있습니다.

| 파일 | 설명 |
|------|------|
| `.dev/CLAUDE.md` | 프로젝트 규칙 (금지사항·DB구조·배포흐름) |
| `.dev/server_info.md` | 서버·DB·계정 접속 정보 (**GitHub 비공개**, 별도 전달) |
| `.dev/deploy.py` | git push 후 서버 자동 git pull |
| `.dev/sync_check.py` | 서버·PC·GitHub 3자 커밋 동일성 확인 |
| `.dev/cursor_test_prompt.md` | Cursor AI 연동 테스트 + 일상 작업 프롬프트 |

```bash
python .dev/sync_check.py   # 동기화 확인
python .dev/deploy.py       # 서버 배포
```

## 🚀 최초 세팅 (처음 한 번만)

> **⚠️ 반드시 인수 시점에 직접 압축본을 생성해서 받아야 합니다.**
> 미리 만들어진 파일을 그냥 받으면 최신 소스가 아닐 수 있습니다.

### 1단계 — 서버 최종 압축 파일 직접 받기

```bash
# [서버에서] 최종 압축본 직접 생성
ssh root@5.104.82.35
FNAME="hms-saas-v5.8.0_$(date +%Y%m%d_%H%M%S).tar.gz"
tar -czf /var/www/html/backups/$FNAME /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/
echo "완료: $FNAME" && ls -lh /var/www/html/backups/$FNAME
```

```bash
# [PC에서] 다운로드 후 압축 해제 (Git Bash 또는 WSL)
scp root@5.104.82.35:/var/www/html/backups/여기에_위_파일명.tar.gz /c/hms-src.tar.gz
mkdir -p /h/seoul_beauty_project/hms-saas
tar -xzf /c/hms-src.tar.gz --strip-components=8 -C /h/seoul_beauty_project/hms-saas
```

### 2단계 — GitHub 연동

```bash
cd H:/seoul_beauty_project/hms-saas
rm -rf .git
git init
git remote add origin https://github.com/appdosa1004-cyber/hms-saas.git
git fetch origin master
git checkout -b master --track origin/master
git reset origin/master
```

### 3단계 — server_info.md 설정

`.dev/server_info.md` 파일을 인계자에게 별도로 받아서 `.dev/` 폴더에 저장합니다.
(GitHub에 올라가지 않으므로 직접 전달받아야 합니다)

### 4단계 — Composer 패키지 설치

```bash
composer install
cd hospital-api && composer install && cd ..
```

## ⚠️ GitHub 저장소 유의사항

| 저장소 | 용도 | 주의 |
|--------|------|------|
| **[hms-saas](https://github.com/appdosa1004-cyber/hms-saas)** | ⭐ **메인 작업 저장소** | 새 폴더에서 시작해도 이 저장소를 remote로 연결. 새 저장소 만들지 마세요. |
| [site_get](https://github.com/appdosa1004-cyber/site_get) | 작업 도구 모음 | 서비스 소스 없음. 혼용 금지. |

## 🔄 매일 작업 흐름

```bash
# [PC] 파일 수정 후
git add 수정한파일.php
git commit -m "수정 내용 설명"
git push origin master

# [서버] 방법 1: 스크립트
python .dev/deploy.py

# [서버] 방법 2: 직접 SSH
ssh root@5.104.82.35
cd /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas
git pull origin master
```

> ✅ `gangnamunni/` PHP 파일 수정은 git pull 즉시 반영됩니다. 빌드 불필요.

## 💾 서버 백업

```bash
ssh root@5.104.82.35
FNAME="hms-saas-v5.8.0_$(date +%Y%m%d_%H%M%S).tar.gz"
tar -czf /var/www/html/backups/$FNAME /var/www/html/multi-hospital-management-system-saas-hospital/
echo "완료: $FNAME"
```

백업 폴더: `/var/www/html/backups/`

## ⚠️ git 제외 항목 (서버에만 존재)

| 경로 | 이유 |
|------|------|
| `gangnamunni/uploads/` | 사용자 업로드 이미지 |
| `hairwang_full/data/` | Gnuboard 업로드 데이터 |
| `vendor/`, `hospital-api/vendor/` | `composer install`로 재설치 |
| `.env` | DB 비밀번호 |
| `.dev/server_info.md` | 서버 접속 비밀번호 |

## 🚫 개발 필수 규칙

자세한 규칙은 `.dev/CLAUDE.md` 참조.

| 구분 | 규칙 |
|------|------|
| ❌ 금지 | Gnuboard **init.php 사용 금지** — `gangnamunni/dbconfig.php` 사용 |
| ❌ 금지 | **KiviCare `/kv-api/` 사용 금지** — HMS `/api/*` 만 사용 |
| ❌ 금지 | `gangnamunni/` 하위 서브디렉토리에 PHP 생성 금지 |
| ✅ 필수 | SQL **Prepared Statement 필수** |
| ✅ 필수 | 세션 변수 접두사 **`ad_admin_`** 고정 |

## 📱 모바일 앱

| 앱 | 플랫폼 | 패키지명 | 소스 |
|----|--------|---------|------|
| 서울미녀 | Android/iOS | com.celebhospital.patient | **Flutter** — Android·iOS 동일 소스 |

### 앱 소스 위치 (Mac Mini 192.168.0.75)
| 항목 | 경로 |
|------|------|
| **Flutter 소스 (Android+iOS 공통)** | `~/s_app/patient_app/` |
| iOS Xcode 프로젝트 | `~/s_app/patient_app/ios/Runner.xcworkspace` |
| Android 빌드 | `flutter build appbundle --release` → `build/app/outputs/bundle/release/app-release.aab` |
| iOS 빌드 | Xcode → Product → Archive → App Store Connect 업로드 |
| Flutter 버전 | **3.35.4 고정** (최신 버전 빌드 실패) |

> ⚠️ `~/seoulbeauty_native/MarsApp/` (Swift WebView)은 **사용하지 않음** — 외장하드 `mac_backup/deprecated_swift_webview_NOT_USE/`로 이동됨

## 🌐 주요 URL

| 서비스 | URL |
|--------|-----|
| 서울미녀 메인 | https://hms.seoulbeauty.app/hairwang/gangnamunni/ |
| HMS 관리자 | https://hms.seoulbeauty.app |
| 인수인계 대시보드 | https://hms.seoulbeauty.app/hairwang/gangnamunni/admin_handover.php |

---
*최종 업데이트: 2026-06-17 · 인계자: sj system → 인수자: 서울미녀 개발팀*
