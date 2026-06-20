# 개발 작업 MD 파일 목록

## 개요

이 문서는 hairwang_dev_sns_shop_2026_01_18 프로젝트에서 작업하면서 생성된 MD(마크다운) 파일들의 목록입니다. 개발할 때 참고할 문서들을 정리했습니다.

---

## 1. 핵심 개발 문서 (최우선 참고)

### 1.1 SNS & PG 연통 시스템 개발

| 순서 | 파일명 | 경로 | 설명 | 생성일 |
|------|--------|------|------|--------|
| 1 | **sns_pg연동_개발.md** | 루트 | SNS 로그인 & PG 결제 연동 개발 가이드 | 2026-01-19 |
| 2 | **AI_개발_프롬프트.md** | 루트 | AI 개발 프롬프트 (요구사항) | 2026-01-19 |
| 3 | **프로젝트_통합_분석_보고서.md** | 루트 | 3개 프로젝트 통합 분석 보고서 | 2026-01-19 |
| 4 | **프로젝트에서_사용하는_디비위치와리스트.md** | 루트 | 데이터베이스 설정 정보 | 2026-01-19 |

### 1.2 데이터베이스 백업 도구

| 순서 | 파일명 | 경로 | 설명 | 생성일 |
|------|--------|------|------|--------|
| 5 | **save_database/README.md** | save_database/ | 백업 도구 사용 설명서 | 2026-01-19 |
| 6 | **save_database/dbbackup/README.txt** | save_database/dbbackup/ | 백업 파일 관리 가이드 | 2026-01-19 |

---

## 2. 앱 관련 개발 문서

### 2.1 앱 관리자 개발

| 파일명 | 경로 | 설명 |
|--------|------|------|
| **addon_app_admin/개발_완료_보고서.md** | addon_app_admin/ | 앱 관리자 개발 완료 보고서 |
| **addon_app_admin/INSTALL.md** | addon_app_admin/ | 설치 가이드 |
| **addon_app_admin/프로젝트_요약.md** | addon_app_admin/ | 프로젝트 요약 |
| **addon_app_admin/최종_체크리스트.md** | addon_app_admin/ | 최종 체크리스트 |

### 2.2 HTTP 앱 개발 (new_http_v1_mars)

| 파일명 | 경로 | 설명 |
|--------|------|------|
| **로그인접근개발.md** | new_http_v1_mars/ | 로그인 접근 개발 |
| **소스수정사항.md** | new_http_v1_mars/ | 소스 수정 사항 |
| **일일전달알림.md** | new_http_v1_mars/ | 일일 전달 알림 |
| **app/README.md** | new_http_v1_mars/app/ | 앱 기본 문서 |

### 2.3 안드로이드 앱 개발 (hairwang3/android/mars)

| 파일명 | 경로 | 설명 |
|--------|------|------|
| **README.md** | hairwang_app_source/hairwang3/android/mars/ | 안드로이드 앱 기본 문서 |
| **APP_CONFIG_README.md** | hairwang_app_source/hairwang3/android/mars/MarsApp/ | 앱 설정 문서 |
| **리소스_폴더_구조_통합.md** | hairwang_app_source/hairwang3/android/mars/ | 리소스 폴더 구조 |
| **스크립트_소스_삭제.md** | hairwang_app_source/hairwang3/android/mars/ | 스크립트 소스 삭제 |
| **하단_네비게이션바_겹침_문제_해결.md** | hairwang_app_source/hairwang3/android/mars/ | 하단 네비게이션 바 문제 해결 |

### 2.4 iOS 앱 개발 (hairwang3/ios/mars/2.iOS/Chamosu/Pods)

| 파일명 | 경로 | 설명 |
|--------|------|------|
| **APP_CONFIG_README.md** | hairwang_app_source/hairwang3/ios/mars/2.iOS/Chamosu/ | iOS 앱 설정 문서 |
| **Alamofire/README.md** | Pods/Alamofire/ | 네트워크 라이브러리 |
| **BWWalkthrough/README.md** | Pods/BWWalkthrough/ | 워크스루 라이브러리 |
| **CryptoSwift/README.md** | Pods/CryptoSwift/ | 암호화 라이브러리 |
| **Firebase/README.md** | Pods/Firebase/* | Firebase 연동 문서 |
| **GoogleDataTransport/README.md** | Pods/GoogleDataTransport/ | Google Data Transport |
| **GoogleUtilities/README.md** | Pods/GoogleUtilities/ | Google 유틸리티 |
| **JWT/README.md** | Pods/* | JWT 관련 문서들 |
| **ObjectMapper/README.md** | Pods/ObjectMapper/ | JSON 매핑 라이브러리 |
| **PKHUD/README.md** | Pods/PKHUD/ | 프로그레스 HUD |
| **PromiseKit/README.md** | Pods/PromiseKit/ | 비동기 처리 |
| **RecaptchaInterop/README.md** | Pods/RecaptchaInterop/ | reCAPTCHA 연동 |
| **SwiftyJSON/README.md** | Pods/SwiftyJSON/ | JSON 처리 |
| **SwiftyTimer/README.md** | Pods/SwiftyTimer/ | 타이머 |
| **SwiftyUserDefaults/README.md** | Pods/SwiftyUserDefaults/ | UserDefaults 처리 |

---

## 3. 기존 기술 문서

### 3.1 FCM 및 알림 관련

| 파일명 | 경로 | 설명 |
|--------|------|------|
| **FCM알림설정체크결과.md** | 루트 | FCM 알림 설정 체크 결과 |
| **알림관련작업.md** | 루트 | 알림 관련 작업 내역 |

### 3.2 로그인 및 토큰 관련

| 파일명 | 경로 | 설명 |
|--------|------|------|
| **로그인시_토큰저장_문제점_및_해결방법.md** | 루트 | 토큰 저장 문제점 및 해결방법 |

### 3.3 UI/UX 관련

| 파일명 | 경로 | 설명 |
|--------|------|------|
| **마이페이지_모바일_수정_작업내역.md** | 루트 | 마이페이지 모바일 수정 작업내역 |
| **모바일_헤더배너_여백작업_splash이슈작업.md** | 루트 | 모바일 헤더배너 여백 작업 및 스플래시 이슈 |
| **모바일여백수정계획.md** | 루트 | 모바일 여백 수정 계획 |
| **시작url주소지정.md** | 루트 | 시작 URL 주소 지정 |

---

## 4. 라이브러리 및 외부 패키지 문서

### 4.1 PHP 라이브러리

| 폴더 | 문서 수 | 주요 라이브러리 |
|--------|---------|-------------|
| db/vendor/ | 100+ | phpMyAdmin, Symfony, Twig, TCPDF, Google reCAPTCHA, Pragmarx Google2FA |

### 4.2 JavaScript 라이브러리

| 폴더 | 문서 수 | 주요 라이브러리 |
|--------|---------|-------------|
| rb/rb.lib/vendor/ | 10+ | Guzzle HTTP, PSR-7 Interfaces, HTTP Client, HTTP Message |

---

## 5. 개발 순서별 참고 문서

### 5.1 1단계: 기반 구축 (SNS & PG 연통 시스템)

1. **sns_pg연동_개발.md** - 요구사항 및 구현 방안
2. **AI_개발_프롬프트.md** - AI 개발 프롬프트 (요구사항)
3. **프로젝트_통합_분석_보고서.md** - 3개 프로젝트 분석

### 5.2 2단계: 데이터베이스 설정

1. **프로젝트에서_사용하는_디비위치와리스트.md** - 데이터베이스 설정
2. **save_database/README.md** - 백업 도구 사용법

### 5.3 3단계: carebaby 구현

1. **프로젝트_통합_분석_보고서.md** - carebaby 섹션 참고

### 5.4 4단계: frezka/kivicare 구현

1. **프로젝트_통합_분석_보고서.md** - frezka/kivicare 섹션 참고

### 5.5 5단계: 앱 연동

1. **addon_app_admin/개발_완료_보고서.md** - 앱 관리자 개발 완료 보고서
2. **addon_app_admin/INSTALL.md** - 설치 가이드
3. **new_http_v1_mars/로그인접근개발.md** - HTTP 앱 로그인 개발

---

## 6. 문서 종류별 분류

### 6.1 개발 가이드

- sns_pg연동_개발.md
- AI_개발_프롬프트.md
- 프로젝트_통합_분석_보고서.md
- save_database/README.md
- addon_app_admin/INSTALL.md
- new_http_v1_mars/로그인접근개발.md

### 6.2 분석 보고서

- 프로젝트_통합_분석_보고서.md
- 프로젝트에서_사용하는_디비위치와리스트.md
- addon_app_admin/개발_완료_보고서.md
- FCM알림설정체크결과.md

### 6.3 문제 해결

- 로그인시_토큰저장_문제점_및_해결방법.md
- 하단_네비게이션바_겹침_문제_해결.md
- 알림관련작업.md

### 6.4 설정 문서

- 프로젝트에서_사용하는_디비위치와리스트.md
- save_database/dbbackup/README.txt
- addon_app_admin/프로젝트_요약.md

### 6.5 체크리스트

- addon_app_admin/최종_체크리스트.md

### 6.6 사용자 매뉴얼

- save_database/dbbackup/README.txt
- hairwang_app_source/*/APP_CONFIG_README.md

---

## 7. 최신 업데이트 문서 (2026-01-19)

1. ✅ 프로젝트_통합_분석_보고서.md - 최신
2. ✅ 프로젝트에서_사용하는_디비위치와리스트.md - 최신
3. ✅ save_database/README.md - 최신
4. ✅ save_database/dbbackup/README.txt - 최신

---

## 8. 주요 참고 섹션 (개발 시 필독)

### 8.1 SNS 로그인 구현

참고 문서:
- **sns_pg연동_개발.md** - 섹션 2: SNS 로그인 시스템 분석
- **프로젝트_통합_분석_보고서.md** - 섹션 3.2, 3.3: SNS 로그인 API 구현

### 8.2 PG 결제 구현

참고 문서:
- **sns_pg연동_개발.md** - 섹션 3: 쇼핑몰 시스템 분석
- **프로젝트_통합_분석_보고서.md** - 섹션 3.2, 3.3: PG 결제 API 구현

### 8.3 데이터베이스 설정

참고 문서:
- **프로젝트에서_사용하는_디비위치와리스트.md** - 전체 데이터베이스 설정
- **save_database/README.md** - 백업 및 복구 방법

### 8.4 API 인증 시스템

참고 문서:
- **프로젝트_통합_분석_보고서.md** - 섹션 3.1: 공통 API 인터페이스 설계
- **프로젝트_통합_분석_보고서.md** - 섹션 4: 데이터베이스 구조

---

## 9. 빠른 참고 링크

### 9.1 핵심 개발 문서

```
📋 sns_pg연동_개발.md
📋 AI_개발_프롬프트.md
📋 프로젝트_통합_분석_보고서.md
📋 프로젝트에서_사용하는_디비위치와리스트.md
```

### 9.2 데이터베이스 관련

```
📄 save_database/README.md
📄 save_database/dbbackup/README.txt
📄 save_database/index.php (백업 도구)
```

### 9.3 앱 개발 관련

```
📱 addon_app_admin/개발_완료_보고서.md
📱 addon_app_admin/INSTALL.md
📱 new_http_v1_mars/로그인접근개발.md
```

---

## 10. 개발 순서 추천

### 1단계: 기반 구축 (1일)
1. **프로젝트_통합_분석_보고서.md** - 전체 구조 이해
2. **프로젝트에서_사용하는_디비위치와리스트.md** - 데이터베이스 설정 확인
3. **save_database/index.php** - 백업 도구로 기존 데이터베이스 백업

### 2단계: carebaby 개발 (2-3일)
1. **sns_pg연동_개발.md** - 요구사항 확인
2. **프로젝트_통합_분석_보고서.md** - carebaby 섹션 구현
3. **save_database/** - 개발 진행 중 백업

### 3단계: frezka/kivicare 개발 (2-3일)
1. **프로젝트_통합_분석_보고서.md** - frezka/kivicare 섹션 구현
2. **save_database/** - 개발 진행 중 백업

### 4단계: 앱 연동 (1-2일)
1. **addon_app_admin/개발_완료_보고서.md** - 기존 기능 확인
2. **new_http_v1_mars/로그인접근개발.md** - 로그인 연동

### 5단계: 테스트 및 배포 (1일)
1. **addon_app_admin/최종_체크리스트.md** - 최종 체크
2. **save_database/** - 최종 백업

---

## 11. 기술 스택 참고

### 11.1 carebaby (PHP 기반)

- **프레임워크**: NFOR 솔루션 (그누보드5 기반)
- **인증**: JWT (Firebase PHP-JWT)
- **PG**: INIpay (이니시스)
- **SNS**: 네이버, 카카오, 구글, 페이스북, 애플

### 11.2 frezka (Laravel 10)

- **프레임워크**: Laravel 10.10
- **인증**: Laravel Sanctum
- **SNS**: Laravel Socialite 5.10
- **PG**: Razorpay, Stripe
- **모듈 시스템**: nwidart/laravel-modules

### 11.3 kivicare (Laravel 11)

- **프레임워크**: Laravel 11.0
- **인증**: Laravel Sanctum 4.0
- **SNS**: Laravel Socialite 5.12
- **PG**: 이니시스(Inicis), Razorpay, Stripe, PayPal
- **모듈 시스템**: nwidart/laravel-modules

---

## 12. 추가 리소스

### 12.1 데이터베이스 관리

- **백업 도구**: http://localhost/hairwang_dev_sns_shop_2026_01_18/save_database/
- **phpMyAdmin**: http://localhost/phpMyAdmin/
- **XAMPP MySQL**: C:\xampp\mysql\bin\mysql.exe

### 12.2 문서 관리

- **버전 관리**: .snapshots/ 폴더 (프로젝트 스냅샷)
- **Git 무시**: save_database/.gitignore

---

## 13. 주의사항

1. **문서 최신화**: 개발 진행 중 문서를 최신 상태로 유지하세요.
2. **백업 필수**: 개발 전 반드시 데이터베이스를 백업하세요.
3. **체크리스트**: 개발 완료 전 최종 체크리스트를 확인하세요.
4. **버전 관리**: 각 주요 단계별로 스냅샷을 생성하세요.

---

**작성일**: 2026-01-19
**작성자**: AI Assistant
**버전**: 1.0
**마지막 업데이트**: 2026-01-19 12:30
