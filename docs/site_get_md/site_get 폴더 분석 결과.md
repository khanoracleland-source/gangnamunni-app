# site_get 폴더 분석 결과

## 이 폴더의 역할

`site_get`은 **개발/데이터 수집용 도구 폴더**입니다. 서비스 코드가 아닙니다.

```
carebaby_project/
├── site_get/              ← 🔧 데이터 수집 도구 (여기)
│   ├── crawler.js         → 강남언니 등 사이트 크롤링
│   ├── extract_hospital_data.js → 병원 데이터 추출
│   ├── generate_sql.js    → SQL 생성
│   ├── import_to_mysql.js → DB에 임포트
│   ├── hospital_data.json → 추출된 99개 병원 데이터
│   ├── output/            → 크롤링된 원본 파일 (312MB+)
│   └── *.sql              → 생성된 SQL 파일
│
├── hospital-api/          ← ✅ 실서비스 백엔드
├── hospital-frontend/     ← ✅ 실서비스 프론트엔드
└── hospital-admin/        ← ✅ 실서비스 관리자
```

## 데이터 흐름

```
site_get (크롤링) → SQL/JSON 생성 → hospital-api DB에 임포트 완료
```

이미 99개 병원 데이터가 DB(`kivicare_laravel`)에 임포트된 상태입니다.

## 결론: 서비스 시 필요 없음

| 항목 | 필요 여부 |
|------|-----------|
| `hospital-api/` | **필요** - 백엔드 API |
| `hospital-frontend/` | **필요** - 프론트엔드 |
| `hospital-admin/` | **필요** - 관리자 대시보드 |
| **`site_get/`** | **불필요** - 데이터 수집 도구 |

`site_get`은 **일회성 데이터 파이프라인**입니다:
- 크롤링 → 추출 → SQL 생성 → DB 임포트 순서로 이미 작업이 완료됨
- `output/` 폴더만 312MB 이상 차지 (크롤링된 HTML/CSS/이미지)
- 서비스 배포 시 이 폴더 전체를 제외해도 무방

단, 나중에 **병원 데이터를 다시 크롤링/업데이트**해야 할 경우를 대비해 개발 환경에서는 보관해 두는 것이 좋습니다. 서버 배포에는 포함시키지 않으면 됩니다.
