# 전체 개발 시나리오

**작성일**: 2026-02-07
**프로젝트**: gangnamunni (강남언니 클론)
**소스 위치**: H:\carebaby_project\server_backup_dev\hairwang_dev_sns_shop_2026_01_18\gangnamunni\

---

## 현재 상태 요약

| 항목 | 상태 |
|------|------|
| gangnamunni 소스 | PHP 43개 파일, includes 8개, assets 40개 |
| DB (hairwang) | 164개 테이블, 17개 필수 테이블 확인 |
| hospital_reviews 테이블 | 존재 (기본 구조 + 샘플 5건) |
| GitHub | 미업로드 |
| Rocky Linux 서버 | 미배포 |
| 후기 에디터 | 미개발 (기존: 단순 텍스트만) |
| Flutter 연동 | 미개발 |

---

## 전체 개발 로드맵

```
Phase 1: 로컬 준비 ──→ Phase 2: 서버 배포 ──→ Phase 3: 테스트
    │                      │                      │
    ▼                      ▼                      ▼
Phase 4: 후기 에디터 기본 ──→ Phase 5: API 개발 ──→ Phase 6: Flutter 연동
    │                           │                      │
    ▼                           ▼                      ▼
Phase 7: 추가 기능 ──→ Phase 8: 최적화/보안 ──→ Phase 9: 최종 테스트
```

---

## Phase 1: 로컬 준비 작업

### 1-1. GitHub 레포 생성 및 푸시
```
작업 위치: 로컬
예상 시간: 10분
```

| # | 작업 | 상세 |
|---|------|------|
| 1 | 레포 생성 | gangnamunni-service (private) |
| 2 | .gitignore | dbconfig.php, *.log, node_modules/, vendor/, *.sql, .DS_Store, Thumbs.db |
| 3 | dbconfig.php.example | 비밀번호 제거한 예제 파일 |
| 4 | README.md | 프로젝트 설명, 설치 방법 |
| 5 | 초기 커밋 | 전체 소스 푸시 |

### 1-2. DB 덤프 생성
```
작업 위치: 로컬
DB: 124.111.85.242 → hairwang
```

| # | 작업 | 상세 |
|---|------|------|
| 1 | 필수 테이블 덤프 | 17개 테이블 (gangnamunni_* 11 + hospital_* 5 + navigation_menu) |
| 2 | 저장 위치 | sql_dumps/gangnamunni_essential_2026_02_07.sql |
| 3 | 옵션 | UTF8MB4, DROP TABLE IF EXISTS, 구조+데이터 |

**덤프 대상 테이블 (17개):**
```
gangnamunni_hospitals        gangnamunni_events
gangnamunni_tips             gangnamunni_categories
gangnamunni_bookings         gangnamunni_consultations
gangnamunni_ads              gangnamunni_promos
gangnamunni_theme_settings   gangnamunni_theme_presets
gangnamunni_theme_admins
hospital_images              hospital_youtube
hospital_features            hospital_reviews
hospital_consultations       navigation_menu
```

---

## Phase 2: Rocky Linux 서버 배포

### 2-1. 서버 환경 확인
```
필요 정보: 서버 IP, SSH 접속 정보
```

| # | 확인 항목 |
|---|----------|
| 1 | OS 버전 (Rocky Linux) |
| 2 | PHP 버전 (8.0+ 필요) |
| 3 | MySQL/MariaDB 버전 |
| 4 | Apache/Nginx 상태 |
| 5 | 방화벽 (firewalld) |
| 6 | SELinux 상태 |
| 7 | PHP 확장 (mysql, gd, mbstring, json, fileinfo) |

### 2-2. 소스 배포
| # | 작업 | 명령어 |
|---|------|--------|
| 1 | git clone | `git clone [repo-url] /var/www/html/gangnamunni` |
| 2 | 권한 설정 | `chown -R apache:apache`, `chmod -R 755` |
| 3 | dbconfig.php | 서버 DB 정보로 생성 |
| 4 | uploads 폴더 | `mkdir -p uploads/reviews`, `chmod 775` |

### 2-3. DB 설정
| # | 작업 |
|---|------|
| 1 | DB 생성: `CREATE DATABASE gangnamunni CHARACTER SET utf8mb4` |
| 2 | 사용자 생성: `CREATE USER 'gangnamunni'@'localhost'` |
| 3 | 권한 부여: `GRANT ALL PRIVILEGES` |
| 4 | SQL 덤프 임포트 |
| 5 | 테이블 확인 (17개) |

### 2-4. 웹서버 설정 (Apache)
| # | 작업 |
|---|------|
| 1 | VirtualHost 설정 (`/etc/httpd/conf.d/gangnamunni.conf`) |
| 2 | mod_rewrite 확인 |
| 3 | 방화벽 80/443 포트 오픈 |
| 4 | SELinux 정책 설정 |
| 5 | Apache 재시작 |

### 2-5. SSL 설정
| # | 작업 |
|---|------|
| 1 | certbot 설치 |
| 2 | Let's Encrypt 인증서 발급 |
| 3 | 자동 갱신 설정 |
| 4 | HTTPS 리다이렉트 설정 |

---

## Phase 3: 기본 서비스 테스트

| # | 테스트 항목 | URL |
|---|------------|-----|
| 1 | 메인 페이지 | / |
| 2 | 병원 목록 | /hospitals.php |
| 3 | 병원 상세 | /detail.php?id=1 |
| 4 | 이벤트 | /events.php |
| 5 | 칼럼/팁 | /tips.php |
| 6 | 예약 | /booking.php |
| 7 | 관리자 | /admin_theme.php |
| 8 | 이미지 로딩 | /assets/ 경로 확인 |
| 9 | DB 연결 | 전 페이지 에러 확인 |

---

## Phase 4: 후기 에디터 - 기본 구조

### 4-1. 폴더 구조 생성
```
gangnamunni/
├── api/                        ← 신규
│   ├── upload_image.php
│   ├── save_review.php
│   ├── list_reviews.php
│   ├── get_review.php
│   ├── delete_review.php
│   └── like_review.php
├── editor/                     ← 신규
│   ├── review_editor.php
│   └── assets/
│       ├── editor.css
│       └── editor.js
├── includes/
│   ├── db.php                  ← 신규 (PDO 래퍼)
│   └── functions.php           ← 신규 (공통 함수)
├── uploads/                    ← 신규
│   └── reviews/
└── (기존 파일들...)
```

### 4-2. DB 테이블 확장
```sql
-- 기존 hospital_reviews 테이블 확장 (컬럼 추가)
ALTER TABLE hospital_reviews
  ADD COLUMN title VARCHAR(200) AFTER event_id,
  ADD COLUMN editor_content LONGTEXT AFTER content,
  ADD COLUMN editor_type ENUM('text','quill') DEFAULT 'text' AFTER editor_content;

-- 후기 이미지 테이블 (신규)
CREATE TABLE review_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  review_id INT NOT NULL,
  image_url VARCHAR(500) NOT NULL,
  image_order INT DEFAULT 0,
  file_size INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_review_id (review_id)
);

-- 후기 댓글 테이블 (신규)
CREATE TABLE review_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  review_id INT NOT NULL,
  parent_id INT DEFAULT NULL,
  user_name VARCHAR(50) NOT NULL,
  content TEXT NOT NULL,
  like_count INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_review_id (review_id)
);

-- 좋아요 중복 방지 테이블 (신규)
CREATE TABLE review_likes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  review_id INT NOT NULL,
  user_id VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_review_user (review_id, user_id)
);

-- 후기 신고 테이블 (신규)
CREATE TABLE review_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  review_id INT NOT NULL,
  reporter_id VARCHAR(100) NOT NULL,
  reason ENUM('spam','abuse','fake','inappropriate','other') NOT NULL,
  description TEXT,
  status ENUM('pending','reviewed','dismissed') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_review_id (review_id)
);
```

### 4-3. includes/db.php (PDO 연결)
```
- PDO 기반 DB 연결 클래스
- utf8mb4 설정
- 에러 핸들링
- 기존 dbconfig.php 설정 활용
```

### 4-4. includes/functions.php (공통 함수)
```
- sanitizeInput() - XSS 필터링
- jsonResponse() - API 공통 응답
- validateToken() - 인증 확인
- uploadImage() - 이미지 업로드 공통
- formatDate() - 날짜 포맷
```

---

## Phase 5: 후기 에디터 - 에디터 페이지

### 5-1. review_editor.php
```
기능:
- Quill.js 리치 텍스트 에디터
- 이미지 업로드 버튼 (최대 10장, 각 10MB)
- 업로드된 이미지 미리보기 (드래그 정렬)
- 별점 선택 UI (★★★★★ 1~5점)
- 시술 카테고리 태그 선택
- 자동저장 (30초마다 localStorage)
- 제출/임시저장 버튼
- 모바일 반응형 (강남언니 스타일)
```

### 5-2. editor/assets/editor.css
```
- 강남언니 디자인 기반
- 모바일 퍼스트 반응형
- 별점 인터랙션 CSS
- Quill 에디터 커스텀 스타일
- 이미지 미리보기 그리드
```

### 5-3. editor/assets/editor.js
```
- Quill 에디터 초기화 및 설정
- 이미지 업로드 핸들러
- 자동저장/복원 로직
- 별점 UI 인터랙션
- 폼 유효성 검사
- 수정 모드 지원
```

---

## Phase 6: 후기 API 개발

### 6-1. api/upload_image.php
| 항목 | 내용 |
|------|------|
| 메서드 | POST (multipart/form-data) |
| 허용 타입 | jpg, png, gif, webp |
| 최대 크기 | 10MB |
| 리사이즈 | 1920px 초과 시 자동 축소 |
| WebP 변환 | 원본 유지 + WebP 복사본 생성 |
| 파일명 | uniqid + hash로 유니크 생성 |
| EXIF 회전 | 자동 보정 |
| 응답 | `{ success, url, thumbnail_url, file_size }` |

### 6-2. api/save_review.php
| 항목 | 내용 |
|------|------|
| 메서드 | POST (JSON) |
| 필드 | hospital_id, title, content, editor_content, rating, images[], treatments[] |
| 검증 | 필수값, XSS 필터, 이미지 수 제한 |
| 모드 | 신규 작성 / 수정 (review_id 유무로 구분) |
| 응답 | `{ success, review_id, message }` |

### 6-3. api/list_reviews.php
| 항목 | 내용 |
|------|------|
| 메서드 | GET |
| 파라미터 | hospital_id, page, limit, sort(latest/rating/popular) |
| 페이징 | offset + limit 방식 |
| 응답 | `{ success, reviews[], total, page, has_more }` |

### 6-4. api/get_review.php
| 항목 | 내용 |
|------|------|
| 메서드 | GET |
| 파라미터 | review_id |
| 기능 | 조회수 증가, 이미지/댓글 포함 |
| 응답 | `{ success, review: { ...data, images[], comments[] } }` |

### 6-5. api/delete_review.php
| 항목 | 내용 |
|------|------|
| 메서드 | POST |
| 파라미터 | review_id, user_id |
| 기능 | 소프트 삭제 (is_active=0) |
| 응답 | `{ success, message }` |

### 6-6. api/like_review.php
| 항목 | 내용 |
|------|------|
| 메서드 | POST |
| 파라미터 | review_id, user_id |
| 기능 | 토글 (좋아요/취소), 중복 방지 |
| 응답 | `{ success, liked, like_count }` |

---

## Phase 7: Flutter 연동

### 7-1. Flutter WebView 페이지
```dart
// 사용 패키지: flutter_inappwebview
기능:
- 에디터 URL 호출 (review_editor.php)
- URL 파라미터로 hospital_id, user_id 전달
- 수정 모드: review_id도 전달
- JS→Flutter 결과 콜백 수신
- 로딩 인디케이터
- 네트워크 에러 처리
```

### 7-2. 수정 모드 데이터 전달
```
URL: /editor/review_editor.php?mode=edit&review_id=123&hospital_id=45&user_id=abc
→ 에디터가 API로 기존 데이터 fetch 후 폼에 채움
```

### 7-3. 네이티브 이미지 처리
```
흐름:
1. 에디터 JS → Flutter: "openImagePicker" 호출
2. Flutter: 카메라/갤러리 선택 다이얼로그
3. Flutter: 이미지 선택 → 압축 → Base64 또는 FormData
4. Flutter → 에디터 JS: 이미지 데이터 전달
5. 에디터 JS → API: upload_image.php 호출
6. API 응답 → 에디터에 이미지 삽입
```

---

## Phase 8: 추가 기능

### 8-1. 댓글 기능
| 작업 | 상세 |
|------|------|
| 테이블 | review_comments (Phase 4에서 생성) |
| API | api/save_comment.php, api/list_comments.php, api/delete_comment.php |
| UI | 후기 상세에 댓글 목록 + 입력 폼 |
| 대댓글 | parent_id로 1단계 대댓글 지원 |

### 8-2. 좋아요 기능
| 작업 | 상세 |
|------|------|
| 테이블 | review_likes (Phase 4에서 생성) |
| API | api/like_review.php (Phase 6에서 개발) |
| UI | 하트 아이콘 토글 + 카운트 표시 |
| 중복 방지 | user_id + review_id UNIQUE 제약 |

### 8-3. 신고 기능
| 작업 | 상세 |
|------|------|
| 테이블 | review_reports (Phase 4에서 생성) |
| API | api/report_review.php |
| UI | 신고 사유 선택 모달 |
| 사유 | 스팸, 욕설, 허위 후기, 부적절, 기타 |

### 8-4. 관리자 후기 관리
| 작업 | 상세 |
|------|------|
| 페이지 | admin_reviews.php |
| 기능 | 후기 목록, 승인/거부, 신고 처리 |
| API | api/admin/approve_review.php, api/admin/reject_review.php |
| 필터 | 상태별, 병원별, 날짜별, 신고 있는 것 |

---

## Phase 9: 최적화 및 보안

### 9-1. 이미지 최적화
| 작업 | 상세 |
|------|------|
| WebP 변환 | 업로드 시 WebP 자동 생성 |
| 썸네일 | 300px 썸네일 자동 생성 |
| Lazy Loading | 이미지 목록 지연 로딩 |
| CDN | 정적 파일 CDN 연동 (선택) |

### 9-2. 보안 강화
| 작업 | 상세 |
|------|------|
| XSS 필터링 | DOMPurify 적용 (에디터 출력) |
| 파일 검증 | MIME 타입 + 매직 바이트 이중 검증 |
| CSRF 토큰 | API 요청 시 토큰 검증 |
| Rate Limiting | 분당 60회 제한 (IP 기반) |
| SQL Injection | PDO Prepared Statement (전 API) |

### 9-3. 성능 최적화
| 작업 | 상세 |
|------|------|
| 디바운스 | 자동저장 디바운스 (입력 중 렉 방지) |
| 페이지네이션 | 무한 스크롤 또는 페이지 버튼 |
| 캐싱 | 인기 후기 목록 캐싱 |
| DB 인덱스 | 쿼리 최적화, 복합 인덱스 추가 |

### 9-4. 모바일 이슈 대응
| 이슈 | 해결 |
|------|------|
| iOS Safari 줌 | viewport meta + touch-action CSS |
| 안드로이드 키보드 | visualViewport API로 에디터 높이 조절 |
| EXIF 회전 | 업로드 시 EXIF 읽고 자동 보정 |
| 한글 입력 끊김 | Quill compositionend 이벤트 처리 |
| 붙여넣기 스타일 | matchVisual: false 설정 |

---

## Phase 10: 최종 테스트

### 10-1. 기능 테스트
| # | 항목 | 체크 |
|---|------|------|
| 1 | 후기 작성 (텍스트 + 이미지 + 별점) | [ ] |
| 2 | 후기 수정 | [ ] |
| 3 | 후기 삭제 | [ ] |
| 4 | 이미지 업로드 (최대 10장) | [ ] |
| 5 | 이미지 리사이즈/WebP 변환 | [ ] |
| 6 | 자동저장/복원 | [ ] |
| 7 | 댓글 작성/삭제 | [ ] |
| 8 | 좋아요 토글 | [ ] |
| 9 | 신고 기능 | [ ] |
| 10 | 관리자 승인/거부 | [ ] |
| 11 | Flutter WebView 연동 | [ ] |
| 12 | 네이티브 이미지 촬영/선택 | [ ] |

### 10-2. 호환성 테스트
| # | 항목 | 체크 |
|---|------|------|
| 1 | Chrome (데스크탑) | [ ] |
| 2 | Safari (iOS) | [ ] |
| 3 | Samsung Browser (안드로이드) | [ ] |
| 4 | Flutter WebView (iOS) | [ ] |
| 5 | Flutter WebView (안드로이드) | [ ] |
| 6 | 태블릿 해상도 | [ ] |

### 10-3. 보안 테스트
| # | 항목 | 체크 |
|---|------|------|
| 1 | XSS 공격 시도 (script 태그 등) | [ ] |
| 2 | SQL Injection 시도 | [ ] |
| 3 | 대용량 파일 업로드 시도 | [ ] |
| 4 | 잘못된 파일 형식 업로드 | [ ] |
| 5 | Rate Limiting 동작 확인 | [ ] |
| 6 | CSRF 토큰 검증 | [ ] |

---

## 개발 우선순위 정리

### 즉시 실행 가능 (로컬)
```
1-1 → 1-2 → 4-1~4-4 → 5-1~5-3 → 6-1~6-6
(GitHub 푸시 → DB 덤프 → 에디터 구조 → 에디터 UI → API 개발)
```

### 서버 정보 필요
```
2-1 → 2-2 → 2-3 → 2-4 → 2-5 → 3-*
(서버 확인 → 소스 배포 → DB 설정 → 웹서버 → SSL → 테스트)
```

### Flutter 환경 필요
```
7-1 → 7-2 → 7-3
(WebView → 수정모드 → 네이티브 이미지)
```

### 서비스 운영 후
```
8-1~8-4 → 9-1~9-4 → 10-*
(댓글/좋아요/신고 → 최적화/보안 → 최종 테스트)
```

---

## 파일 변경 예상 목록

### 신규 생성 파일 (약 20개)
```
api/upload_image.php
api/save_review.php
api/list_reviews.php
api/get_review.php
api/delete_review.php
api/like_review.php
api/save_comment.php
api/list_comments.php
api/delete_comment.php
api/report_review.php
api/admin/approve_review.php
api/admin/reject_review.php
editor/review_editor.php
editor/assets/editor.css
editor/assets/editor.js
includes/db.php
includes/functions.php
admin_reviews.php
uploads/reviews/.gitkeep
sql/review_tables.sql
```

### 수정 파일 (약 5개)
```
detail.php          ← 후기 탭에 에디터 연결
hospitals.php       ← 후기 수/별점 표시 연동
includes/init.php   ← PDO 연결 추가
config.php          ← 업로드 경로 등 설정 추가
.gitignore          ← uploads/ 패턴 추가
```

### DB 변경 (4개 테이블 추가, 1개 수정)
```
[수정] hospital_reviews    ← title, editor_content, editor_type 컬럼 추가
[추가] review_images       ← 후기 이미지
[추가] review_comments     ← 댓글
[추가] review_likes        ← 좋아요
[추가] review_reports      ← 신고
```

---

**작성일**: 2026-02-07
**다음 단계**: 사용자 컨펌 후 Phase 1부터 순서대로 실행
