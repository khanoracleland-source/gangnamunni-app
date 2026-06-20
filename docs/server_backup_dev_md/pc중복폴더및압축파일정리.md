# PC 중복 폴더 및 압축 파일 정리 계획

**분석 일시**: 2026-01-16
**분석 대상**: C:, D:, E:, F:, G:, H:, I: 드라이브
**현재 작업 상태**: ⚠️ **분석만 완료 - 실제 작업은 진행하지 않음**

---

## 📊 시스템 현황 요약

### 드라이브 용량 현황
| 드라이브 | 전체 용량 | 사용 공간 | 여유 공간 | 사용률 |
|---------|----------|----------|----------|--------|
| C: | 931 GB | 713 GB | 218 GB | 76.6% |
| D: | 931 GB | 882 GB | 49 GB | 94.7% ⚠️ |
| E: | 932 GB | 186 GB | 747 GB | 20.0% |
| F: | 932 GB | 877 GB | 55 GB | 94.1% ⚠️ |
| G: | 932 GB | 885 GB | 46 GB | 94.9% ⚠️ |
| H: | 921 GB | 879 GB | 42 GB | 95.4% ⚠️ |
| I: | 1863 GB | 1815 GB | 48 GB | 97.4% 🚨 |

**위험 드라이브**: D:, F:, G:, H:, I: (90% 이상 사용 중)

---

## 🔍 중복 분석 결과

### 1. 중복 폴더 패턴 (상위 10개)

#### 1위: Screenshots (278개 중복)
**문제점**: iOS/Android 샘플 프로젝트에서 자동 생성된 스크린샷 폴더
**위치 예시**:
- C:\project_ingram\Demos* 시리즈 (13개)
- D:\ios-samples-main\ 하위 150개 이상
- D:\monodroid-samples-master\ 하위 100개 이상

**정리 방안**:
```
권장사항:
1. 오래된 샘플 프로젝트는 전체 압축 후 아카이브
2. Screenshots 폴더는 프로젝트당 5MB 미만이므로 개별 삭제보다
   프로젝트 전체를 압축하는 것이 효율적
3. 최근 1년 이내 수정되지 않은 샘플 프로젝트는 E: 드라이브로 이동 후 압축
```

#### 2위: lib (192개 중복)
**문제점**: PHP, Python, Xamarin 프로젝트의 라이브러리 폴더
**위치 예시**:
- gnuboard 여러 버전 (D:, E:, F:, H:, I: 25개 이상)
- xamarin 프로젝트 (F:, H: 20개 이상)
- iyumbuilder 시리즈 (H: 8개)
- ingram_server_backup_php 하위 (I: 40개 이상)

**정리 방안**:
```
주의사항:
1. 프로젝트별로 의존성이 다를 수 있음
2. gnuboard 오래된 버전(5.2.x, 5.3.x)은 삭제 고려
3. xamarin 프로젝트의 lib는 프로젝트와 함께 정리
4. PHP 프로젝트는 composer로 재생성 가능 여부 확인

예상 확보 공간: 5GB ~ 15GB
```

#### 3위: resources (191개 중복)
**문제점**: Android/iOS/Xamarin 프로젝트의 리소스 폴더
**위치 예시**:
- essential-ui-kit 시리즈 (D:, F: 여러 버전)
- ImageEditor 프로젝트 (D:, F: 여러 위치)
- xamarin 프로젝트 전반

**정리 방안**:
```
주의사항:
1. 각 프로젝트의 이미지, 아이콘 등 포함
2. 개별 삭제보다 프로젝트 전체 정리가 효율적
3. 중복 프로젝트 정리 시 함께 삭제됨

예상 절감: 프로젝트 정리에 포함됨
```

#### 4위: node_modules (추정 50개 이상)
**문제점**: React/Node.js 프로젝트의 의존성 폴더, 각 100MB~500MB
**위치 예시**:
- G:\blis_diet\react-client*
- I:\react_nfc_ble\*
- 각 React Native 프로젝트

**정리 방안**:
```
즉시 삭제 가능:
1. npm install로 언제든 재생성 가능
2. node_modules 폴더는 백업 불필요
3. package.json, package-lock.json만 보존하면 복원 가능

예상 확보 공간: 10GB ~ 30GB
```

#### 5위: 프로젝트 버전 중복 (xamarin, android, server_backup)
**문제점**: 같은 프로젝트의 여러 버전이 다른 드라이브에 중복 저장

**주요 중복 프로젝트**:
- `1native_packing` (D:, F:, G: 3곳)
- `xamarin_slowside*` (D:, F:, G: 여러 버전)
- `server_backup` (E:, F:, H:, I: 4곳 이상)
- `infection` 관련 (D:, F:, G: 3곳)
- `essential-ui-kit-for-xamarin.forms-master` (D:, F: 2곳)

---

### 2. 압축 파일 중복 분석

**총 압축 파일 수**: 4,306개
**중복 파일 그룹**: 677개

#### 주요 중복 압축 파일

##### A. android.zip (67개 중복)
**특징**:
- 대부분 0MB (빈 파일 또는 매우 작은 파일)
- 2018년~2021년 사이에 생성
- 주로 1native_packing 프로젝트 하위

**정리 방안**:
```
권장사항:
1. 0MB 파일은 모두 삭제
2. D:, F: 드라이브에 동일한 파일이 중복되어 있으므로 하나만 유지
3. 2021년 이전 파일은 대부분 오래된 프로젝트 → 삭제 고려
```

##### B. ImageSets.zip (32개 중복)
**크기**: 0.15MB ~ 6.52MB
**위치**: 주로 1native_packing, 0.android_reg 하위

**정리 방안**:
```
보존 기준:
1. 최신 수정일 파일만 1개 유지
2. 같은 프로젝트 폴더에 여러 개 있는 경우 최신 것만 보존
3. 오래된 버전은 삭제

예상 절감: 약 100~150MB
```

##### C. drawable.zip (29개 중복)
**크기**: 대부분 0.19MB
**위치**: H: 드라이브의 iyumbuilder, callback 관련 폴더

**정리 방안**:
```
문제점:
- 거의 동일한 크기 (0.19MB)로 같은 내용일 가능성 높음
- 2022년~2025년까지 여러 버전

정리 방법:
1. 최신 버전 1개만 유지 (2025-05-18 버전)
2. 나머지는 삭제
```

##### D. partner.zip / consumer.zip (각 22개, 18개 중복)
**크기**: 2MB ~ 161MB
**위치**: I: 드라이브의 ingram 관련 프로젝트

**정리 방안**:
```
주의사항:
- 크기가 다른 것은 내용이 다를 수 있음
- 37MB vs 161MB → 압축 방식 차이 또는 내용 차이

보존 전략:
1. 최신 버전 3개 유지 (최근 3개월)
2. 크기별로 대표 파일 1개씩 유지
3. 오래된 버전은 E: 드라이브로 이동 후 하나로 통합
```

##### E. 대용량 압축 파일 (100MB 이상)
**발견된 대용량 파일**:
- Partner.7z (161MB) - 2개
- android.zip (176MB) - 2개
- 기타 프로젝트 압축 파일

**정리 방안**:
```
확인 필요:
1. 압축 해제하여 내용 확인 필요
2. 중복 여부 확인 후 결정
3. 사용하지 않는 파일은 E: 드라이브로 이동
```

---

## 📋 프로젝트 폴더 중복 분석

### 주요 중복 프로젝트

#### 1. Xamarin 프로젝트 중복
```
프로젝트명: infection
위치:
- D:\xamarin_slowside_whoandgo_water8\infection*
- F:\infection\
- G:\xamarin_project\project_infection\

권장사항:
- 최신 버전만 G: 드라이브에 유지
- 나머지는 E: 드라이브로 이동 후 압축
```

```
프로젝트명: uahan (우아한성장)
위치:
- F:\0.uahan_sungjang\ (다수 하위 폴더)
- F:\seniro_android_app\

권장사항:
- F:\0.uahan_sungjang\ 하나로 통합
- 중복 버전 정리 (xam_uahan2, 3, 5 시리즈)
```

#### 2. React/Node.js 프로젝트 중복
```
프로젝트명: react-client
위치:
- G:\blis_diet\react-client*
- G:\temp_blis\react-client*

권장사항:
- node_modules 전체 삭제 (npm install로 복구 가능)
- 중복 버전은 최신만 유지
```

```
프로젝트명: nfc_ble 관련
위치:
- F:\react_nfc_ble\
- F:\react_nfc_ble_run_ok\
- I:\react_nfc_ble\

권장사항:
- 작동하는 버전만 I: 드라이브에 유지
- 나머지는 삭제 또는 압축
```

#### 3. Server Backup 중복
```
프로젝트명: server_backup
위치:
- E:\carebaby_project\server_backup
- F:\vps30_server_backup\
- H:\carebaby_project\server_backup_dev (현재 위치)
- I:\ingram_server_backup_php\

권장사항:
- 각 서버별로 최신 백업 1개만 유지
- 날짜별 백업은 월 1회 스냅샷만 보존
- 나머지는 압축 후 아카이브
```

#### 4. 1native_packing 중복
```
위치:
- D:\1native_packing\
- F:\1native_packing\
- G:\0.android_reg\1native_packing_복사\

크기: 각 5~10GB 추정

권장사항:
- 최신 버전만 F: 또는 G:에 유지
- D: 드라이브 버전 삭제 (공간 확보 필요)
- 오래된 프로젝트는 개별 압축
```

---

## 🗂️ 종류별 정리 전략

### A. 개발 프로젝트 폴더
```
📁 xamarin 프로젝트 → G: 드라이브로 통합
📁 react/flutter 프로젝트 → I: 드라이브로 통합
📁 PHP/서버 프로젝트 → H: 또는 I: 드라이브로 통합
📁 안드로이드 네이티브 → F: 또는 G: 드라이브로 통합
```

### B. 샘플/학습 코드
```
📦 ios-samples-main → E: 드라이브로 압축 이동
📦 monodroid-samples-master → E: 드라이브로 압축 이동
📦 Xceed Samples → 사용하지 않으면 삭제
```

### C. 백업 파일
```
🗄️ server_backup 시리즈 → 최신 1개 + 월별 스냅샷만 유지
🗄️ config_html 백업 → 최신 3개만 유지
```

### D. 압축 파일
```
🔒 중복 압축 파일 → 최신 버전만 유지, 나머지 삭제
🔒 0MB 압축 파일 → 전체 삭제
🔒 오래된 압축 (2021년 이전) → 사용 여부 확인 후 삭제
```

---

## ⚡ 즉시 실행 가능한 정리 작업

### 우선순위 1: 안전하게 삭제 가능 (예상 확보: 20~40GB)

```powershell
# 1. node_modules 폴더 전체 삭제
Find-Command: node_modules 검색 후 전체 삭제
예상 절감: 10~30GB

# 2. 0MB 압축 파일 삭제
Find-Command: 0MB android.zip 파일 검색 후 삭제
예상 절감: 0.5GB

# 3. Screenshots 폴더 중 오래된 것 삭제
조건: 2022년 이전 샘플 프로젝트
예상 절감: 5~10GB
```

### 우선순위 2: 확인 후 정리 (예상 확보: 50~100GB)

```
1. 1native_packing 중복 → D: 드라이브 버전 삭제
   예상 절감: 10~15GB

2. xamarin 프로젝트 중복 정리
   예상 절감: 20~30GB

3. server_backup 중복 정리
   예상 절감: 10~20GB

4. 오래된 압축 파일 정리 (2021년 이전)
   예상 절감: 10~20GB
```

### 우선순위 3: 장기 아카이브 (예상 확보: 100~200GB)

```
1. ios-samples-main 전체 압축 후 E: 이동
   예상 절감: 30~50GB

2. monodroid-samples-master 전체 압축 후 E: 이동
   예상 절감: 20~40GB

3. 오래된 프로젝트 압축 후 아카이브
   예상 절감: 50~100GB
```

---

## 📌 드라이브별 정리 계획

### D: 드라이브 (94.7% 사용 중 → 목표 70%)
```
문제:
- 1native_packing 중복 (F:, G:에도 존재)
- xamarin 프로젝트 다수
- ios-samples-main (대용량)

정리 작업:
1. 1native_packing 삭제 (F: 또는 G:에 유지)
2. ios-samples-main 압축 후 E: 이동
3. monodroid-samples-master 압축 후 E: 이동
4. 오래된 xamarin 프로젝트 압축

예상 확보 공간: 100~150GB
```

### F: 드라이브 (94.1% 사용 중 → 목표 70%)
```
문제:
- 1native_packing (D:, G:에도 존재)
- xamarin 프로젝트 중복
- seniro_android_app (여러 버전)

정리 작업:
1. 1native_packing을 대표 위치로 유지 (D: 삭제)
2. 중복 xamarin 프로젝트 정리
3. react_nfc_ble 중복 정리

예상 확보 공간: 80~120GB
```

### G: 드라이브 (94.9% 사용 중 → 목표 70%)
```
문제:
- xamarin_project (다수 하위 프로젝트)
- blis_diet (react-client 중복)
- 0.android_reg (대용량)

정리 작업:
1. xamarin_project 내 오래된 프로젝트 압축
2. blis_diet의 node_modules 삭제
3. react-client 중복 버전 정리

예상 확보 공간: 80~100GB
```

### H: 드라이브 (95.4% 사용 중 → 목표 70%)
```
문제:
- iyumbuilder 여러 버전
- callback_server_php 중복
- carebaby_project (현재 작업 중)

정리 작업:
1. iyumbuilder 최신 버전만 유지
2. drawable.zip 중복 삭제
3. config_html 백업 정리

예상 확보 공간: 50~80GB
```

### I: 드라이브 (97.4% 사용 중 🚨 → 목표 70%)
```
문제:
- ingram_server_backup_php (다수 백업)
- 0aimautoplat (대용량 프로젝트)
- partner.zip, consumer.zip 중복

정리 작업:
1. server_backup 최신만 유지, 나머지 압축
2. partner/consumer 중복 정리
3. 오래된 압축 파일 삭제
4. node_modules 삭제

예상 확보 공간: 150~250GB
```

---

## ⚠️ 주의사항

### 절대 삭제하면 안 되는 것
```
❌ 현재 작업 중인 프로젝트
❌ 최근 1개월 이내 수정된 파일
❌ package.json, *.csproj 등 프로젝트 설정 파일
❌ 원본 소스 코드 (압축 전)
❌ 데이터베이스 파일
❌ 고객 데이터 백업
```

### 삭제 전 확인 필요
```
⚠️ server_backup → 복원 가능 여부 확인
⚠️ 대용량 압축 파일 → 내용 확인 후 결정
⚠️ 프로젝트 중복 → 최신 버전 확인
⚠️ 압축 파일 중복 → 크기 다르면 내용 비교
```

### 백업 권장
```
💾 중요 프로젝트는 외장 HDD에 추가 백업
💾 삭제 전 압축 파일로 변환
💾 정리 전 전체 목록 저장
```

---

## 📝 실행 체크리스트

### 1단계: 안전한 정리 (바로 실행 가능)
- [ ] node_modules 폴더 전체 검색 및 삭제
- [ ] 0MB 압축 파일 검색 및 삭제
- [ ] drawable.zip 중복 파일 정리
- [ ] ImageSets.zip 중복 파일 정리

### 2단계: 확인 후 정리 (1~2시간 소요)
- [ ] 1native_packing D: 드라이브 버전 확인 후 삭제
- [ ] xamarin 프로젝트 중복 확인 및 정리
- [ ] server_backup 중복 확인 및 통합
- [ ] partner.zip / consumer.zip 중복 정리

### 3단계: 대용량 정리 (반나절 소요)
- [ ] ios-samples-main 압축 후 E: 이동
- [ ] monodroid-samples-master 압축 후 E: 이동
- [ ] 오래된 프로젝트 압축 및 아카이브
- [ ] 2021년 이전 압축 파일 검토 및 삭제

### 4단계: 최종 점검
- [ ] 각 드라이브 사용량 확인
- [ ] 중요 프로젝트 정상 작동 확인
- [ ] 삭제 목록 문서화
- [ ] 외장 HDD 백업 완료

---

## 📈 예상 결과

### 정리 후 예상 용량
| 드라이브 | 현재 | 정리 후 | 확보 공간 |
|---------|------|---------|----------|
| D: | 94.7% | 70% | ~150GB |
| F: | 94.1% | 70% | ~120GB |
| G: | 94.9% | 70% | ~100GB |
| H: | 95.4% | 70% | ~80GB |
| I: | 97.4% | 70% | ~250GB |

**총 예상 확보 공간**: 약 600~700GB

---

## 🔧 추가 도구 및 스크립트

분석에 사용된 PowerShell 스크립트:
- `C:\Users\cr100\AppData\Local\Temp\find_duplicates.ps1` - 중복 폴더 검색
- `C:\Users\cr100\AppData\Local\Temp\find_archives.ps1` - 압축 파일 분석
- `C:\Users\cr100\AppData\Local\Temp\analyze_projects.ps1` - 프로젝트 분석

분석 결과 파일:
- `C:\Users\cr100\AppData\Local\Temp\duplicate_folders.txt`
- `C:\Users\cr100\AppData\Local\Temp\duplicate_archives.txt`
- `C:\Users\cr100\AppData\Local\Temp\project_analysis.txt`

---

## 📞 작업 시 참고사항

1. **점진적 정리**: 한 번에 모든 드라이브를 정리하지 말고, 드라이브별로 나누어 진행
2. **테스트 복원**: 삭제 전 압축 파일의 압축 해제 테스트로 무결성 확인
3. **문서화**: 삭제한 파일 목록을 별도로 기록
4. **백업**: 중요 데이터는 반드시 외장 HDD에 추가 백업

---

**작성일**: 2026-01-16
**다음 검토 예정일**: 정리 작업 완료 후
**담당자**: 시스템 분석 완료 (실행은 사용자 확인 후)
