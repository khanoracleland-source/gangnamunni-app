# Hairwang DB 전체 분석 및 작업 계획

**분석일**: 2026-02-06
**DB Host**: 124.111.85.242:3306
**DB Name**: hairwang
**총 테이블**: 164개

---

## 1. DB 테이블 분류

### 카테고리별 요약

| 카테고리 | 테이블 수 | 데이터 건수 | 용량 | 용도 |
|----------|----------|------------|------|------|
| crawled_* | 6개 | 2,658건 | 1.7MB | 크롤링 원본 데이터 |
| gangnamunni_* | 12개 | 257건 | 1.8MB | 강남언니 서비스 |
| hospital_* | 5개 | 221건 | 128KB | 병원 상세 정보 |
| g5_* | 56개 | 31,618건 | 7.2MB | 그누보드 코어 |
| rb_* | 32개 | 159건 | 544KB | 라이믹스 빌더 |
| addon_* | 10개 | 16건 | 160KB | 앱 관리 애드온 |
| sms5_* | 7개 | 1건 | 112KB | SMS 모듈 |
| api_* | 5개 | 0건 | 80KB | API 관리 |
| 기타 | 8개 | 17건 | 128KB | 기타 설정 |

---

## 2. 테이블 상세 분류

### A. 크롤링 원본 데이터 (crawled_*) - 읽기 전용
```
crawled_sites        (3건)    - 크롤링된 사이트 목록
crawled_hospitals    (196건)  - 크롤링된 병원 데이터
crawled_events       (2,278건)- 크롤링된 이벤트 데이터
crawled_doctors      (141건)  - 크롤링된 의사 데이터
crawled_tips         (40건)   - 크롤링된 팁/칼럼 데이터
crawled_images       (0건)    - 크롤링된 이미지 메타데이터
```

### B. 강남언니 서비스 데이터 (gangnamunni_*) - 서비스용
```
gangnamunni_hospitals       (103건) - 서비스 병원 데이터
gangnamunni_events          (43건)  - 서비스 이벤트 데이터
gangnamunni_tips            (53건)  - 서비스 팁/칼럼
gangnamunni_categories      (11건)  - 카테고리 목록
gangnamunni_bookings        (0건)   - 예약 데이터
gangnamunni_consultations   (0건)   - 상담 데이터
gangnamunni_ads             (3건)   - 광고 데이터
gangnamunni_promos          (3건)   - 프로모션 데이터
gangnamunni_theme_settings  (35건)  - 테마 설정
gangnamunni_theme_presets   (5건)   - 테마 프리셋
gangnamunni_theme_admins    (1건)   - 테마 관리자
gangnamunni_theme_history   (0건)   - 테마 변경 이력
```

### C. 병원 상세 데이터 (hospital_*) - 서비스용
```
hospital_images        (204건) - 병원 이미지
hospital_youtube       (6건)   - 병원 유튜브 영상
hospital_features      (5건)   - 병원 특징
hospital_reviews       (5건)   - 병원 리뷰
hospital_consultations (1건)   - 병원 상담
```

### D. 그누보드 코어 (g5_*) - 프레임워크
```
g5_member     (88건)   - 회원 정보 ★중요
g5_config     (1건)    - 사이트 설정 ★중요
g5_board      (12건)   - 게시판 설정
g5_write_*    (708건)  - 게시글 데이터
g5_point      (253건)  - 포인트 내역
g5_visit      (26,611건) - 방문 기록
...
```

### E. 라이믹스 빌더 (rb_*) - UI 빌더
```
rb_config     (1건)    - 빌더 설정
rb_banner     (19건)   - 배너 관리
rb_module     (16건)   - 모듈 설정
rb_pwa_*      (85건)   - PWA 설정
...
```

### F. 기타 (addon_*, sms5_*, api_*, 기타)
```
addon_app_admin_*  - 앱 관리 애드온
sms5_*             - SMS 모듈
api_*              - API 관리
navigation_menu    - 네비게이션 메뉴
push_settings      - 푸시 설정
...
```

---

## 3. 데이터 흐름

```
[크롤링 원본]              [서비스 데이터]           [사용자 데이터]
crawled_sites         →   (변환 없음)
crawled_hospitals     →   gangnamunni_hospitals  →  hospital_*
crawled_events        →   gangnamunni_events
crawled_doctors       →   (미구현)
crawled_tips          →   gangnamunni_tips

[이미지 변환 결과]
site_get/output/screenshots/  →  서버에 업로드  →  DB image_url 업데이트
```

---

## 4. 작업 분리 계획

### Phase 1: DB 준비 (로컬)
- [ ] 현재 DB 구조 스냅샷 저장 ✅ 완료
- [ ] 필요한 테이블만 SQL 덤프
- [ ] 이미지 URL 필드 확인

### Phase 2: GitHub 업로드
- [ ] gangnamunni 폴더 GitHub 레포 생성
- [ ] .gitignore 설정 (dbconfig.php 제외)
- [ ] 초기 커밋 및 푸시

### Phase 3: Rocky Linux 서버 설정
- [ ] PHP/MySQL 환경 확인
- [ ] GitHub에서 소스 클론
- [ ] dbconfig.php 서버용으로 설정
- [ ] 웹서버 설정 (Apache/Nginx)

### Phase 4: 데이터 마이그레이션
- [ ] DB 덤프 파일 서버에 업로드
- [ ] DB 임포트
- [ ] 이미지 파일 업로드

### Phase 5: 기능 테스트
- [ ] 메인 페이지 확인
- [ ] 병원 목록/상세
- [ ] 이벤트 목록/상세
- [ ] 예약 기능
- [ ] 관리자 페이지

---

## 5. 필수 테이블 목록 (서버 배포용)

### 최소 필수 테이블 (17개)
```sql
-- 강남언니 서비스
gangnamunni_hospitals
gangnamunni_events
gangnamunni_tips
gangnamunni_categories
gangnamunni_bookings
gangnamunni_consultations
gangnamunni_ads
gangnamunni_promos
gangnamunni_theme_settings
gangnamunni_theme_presets
gangnamunni_theme_admins

-- 병원 상세
hospital_images
hospital_youtube
hospital_features
hospital_reviews
hospital_consultations

-- 기타
navigation_menu
```

### 선택적 테이블 (나중에 추가)
```sql
-- 크롤링 원본 (참조용)
crawled_*

-- 그누보드 (회원 관리 시)
g5_member
g5_config
g5_point
```

---

## 6. SQL 덤프 명령어

### 필수 테이블만 덤프
```bash
mysqldump -h 124.111.85.242 -u root -p hairwang \
  gangnamunni_hospitals \
  gangnamunni_events \
  gangnamunni_tips \
  gangnamunni_categories \
  gangnamunni_bookings \
  gangnamunni_consultations \
  gangnamunni_ads \
  gangnamunni_promos \
  gangnamunni_theme_settings \
  gangnamunni_theme_presets \
  gangnamunni_theme_admins \
  hospital_images \
  hospital_youtube \
  hospital_features \
  hospital_reviews \
  hospital_consultations \
  navigation_menu \
  > gangnamunni_essential.sql
```

### 전체 덤프
```bash
mysqldump -h 124.111.85.242 -u root -p hairwang > hairwang_full_backup.sql
```

---

## 7. 변경 추적 체크리스트

### DB 구조 변경 시 확인 사항
- [ ] 테이블 추가/삭제
- [ ] 컬럼 추가/삭제/타입 변경
- [ ] 인덱스 변경
- [ ] 외래키 변경
- [ ] 레코드 수 변화

### 비교 명령어
```bash
# 테이블 목록 비교
mysql -h HOST -u USER -p DB -e "SHOW TABLES;" > tables_new.txt
diff tables_old.txt tables_new.txt

# 테이블 구조 비교
mysql -h HOST -u USER -p DB -e "DESCRIBE gangnamunni_hospitals;" > struct_new.txt
diff struct_old.txt struct_new.txt
```

---

**생성일**: 2026-02-06
**다음 단계**: Rocky Linux 서버 작업 프롬프트 참조
