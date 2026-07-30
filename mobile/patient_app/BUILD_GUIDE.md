# Seoul Beauty App Build Guide

서울미녀 앱 빌드 및 배포 가이드

---

## 📋 사전 준비

### 1. 개발 환경

```bash
# Flutter 버전 확인
flutter --version
# 필요 버전: Flutter 3.0.0 이상

# Flutter Doctor 실행
flutter doctor
# 모든 항목이 ✓ 확인
```

### 2. 의존성 설치

```bash
cd h:/carebaby_project/server_backup_dev/kivicare-app/kivicare-complete-clinic-flutter-apps/patient-app/kivicare-laravel-patient-flutter-app-v1.9.0

# 의존성 설치
flutter pub get

# 클린 빌드
flutter clean
```

---

## 🔧 Firebase 설정

### Android

1. **Firebase Console에서 google-services.json 다운로드**
   - 프로젝트: seoul-beauty-app
   - 패키지명: com.seoulbeauty.app

2. **파일 위치**
   ```
   android/app/google-services.json
   ```

### iOS

1. **Firebase Console에서 GoogleService-Info.plist 다운로드**

2. **파일 위치**
   ```
   ios/Runner/GoogleService-Info.plist
   ```

---

## 🔑 Signing 설정 (Android)

### 1. Release Keystore 생성

```bash
keytool -genkey -v -keystore seoulbeauty-release-key.jks -keyalg RSA -keysize 2048 -validity 10000 -alias seoulbeauty
```

**입력 정보:**
- Keystore password: **안전한 비밀번호**
- Key password: **안전한 비밀번호**
- First and last name: Seoul Beauty
- Organizational unit: Development
- Organization: Seoul Beauty
- City: Seoul
- State: Seoul
- Country code: KR

### 2. key.properties 파일 생성

```bash
# android/key.properties.example을 복사
cp android/key.properties.example android/key.properties
```

**android/key.properties 내용:**
```properties
storePassword=YOUR_ACTUAL_STORE_PASSWORD
keyPassword=YOUR_ACTUAL_KEY_PASSWORD
keyAlias=seoulbeauty
storeFile=../seoulbeauty-release-key.jks
```

⚠️ **주의**: `key.properties`와 `*.jks` 파일은 절대 Git에 커밋하지 마세요!

---

## 🏗️ 빌드 명령어

### Android Debug Build

```bash
flutter build apk --debug
```

**출력 위치:** `build/app/outputs/flutter-apk/app-debug.apk`

### Android Release Build (AAB - Google Play 업로드용)

```bash
flutter build appbundle --release
```

**출력 위치:** `build/app/outputs/bundle/release/app-release.aab`

### Android Release Build (APK - 직접 설치용)

```bash
flutter build apk --release
```

**출력 위치:** `build/app/outputs/flutter-apk/app-release.apk`

### iOS Build (macOS 필요)

```bash
flutter build ios --release
```

---

## 📱 Google Play Console 설정

### 1. 앱 등록

1. [Google Play Console](https://play.google.com/console) 접속
2. "앱 만들기" 클릭
3. 앱 정보 입력:
   - **앱 이름**: 서울미녀
   - **기본 언어**: 한국어
   - **앱 또는 게임**: 앱
   - **무료 또는 유료**: 무료

### 2. 앱 콘텐츠

- **개인정보처리방침 URL**: https://kivicare.seoulbeauty.app/page/privacy-policy
- **앱 카테고리**: 건강 및 피트니스
- **대상 연령**: 만 12세 이상

### 3. AAB 업로드

```
Production > Releases > Create new release
```

1. AAB 파일 업로드: `build/app/outputs/bundle/release/app-release.aab`
2. 버전 정보 입력
3. 검토 후 출시

---

## 🧪 테스트

### 코드 분석

```bash
flutter analyze
```

### 테스트 실행

```bash
flutter test
```

### 실제 디바이스 테스트

```bash
# 연결된 디바이스 확인
flutter devices

# 실행
flutter run --release
```

---

## 📦 빌드 버전 관리

### pubspec.yaml

```yaml
version: 1.9.0+25
```

- **1.9.0**: 버전 이름 (사용자에게 표시)
- **25**: 버전 코드 (Google Play에서 사용)

### 버전 업데이트 시

1. `pubspec.yaml`의 version 수정
2. AAB 재빌드
3. Google Play Console에 업로드

---

## 🔐 보안 체크리스트

- [ ] `google-services.json` Git에 커밋 안 됨
- [ ] `key.properties` Git에 커밋 안 됨
- [ ] `*.jks` 파일 안전한 곳에 백업
- [ ] Firebase 프로젝트 권한 확인
- [ ] INICIS API 키 안전하게 관리
- [ ] ProGuard 설정 확인 (난독화)

---

## 🚀 배포 체크리스트

### 출시 전

- [ ] 모든 기능 테스트 완료
- [ ] 결제 테스트 (실제 INICIS 계정)
- [ ] 한국어 번역 검증
- [ ] 스플래시 화면 / 아이콘 확인
- [ ] 백엔드 API 연동 확인
- [ ] 개인정보처리방침 업데이트
- [ ] 이용약관 업데이트

### Google Play 제출

- [ ] AAB 파일 업로드
- [ ] 스크린샷 준비 (최소 2장)
- [ ] 앱 설명 작성 (한국어)
- [ ] 개인정보처리방침 링크 확인
- [ ] 연령 등급 설정
- [ ] 콘텐츠 등급 질문 답변

### 출시 후

- [ ] 사용자 리뷰 모니터링
- [ ] Crashlytics 확인
- [ ] 결제 로그 확인
- [ ] 사용자 피드백 수집

---

## 📞 문의

기술 지원: support@seoulbeauty.app
개발팀: dev@seoulbeauty.app

---

**마지막 업데이트**: 2026-01-14
