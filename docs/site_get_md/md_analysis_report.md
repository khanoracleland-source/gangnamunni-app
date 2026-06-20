# 🤖 MD 파일 자동 분석 리포트

> 생성일: 2026. 1. 29. 오후 3:37:16
> 분석 도구: MD Auto Debugger v1.0

---

## 📊 통계

- **총 파일 수**: 9
- **정상**: 1 (11.1%)
- **개선 필요**: 8 (88.9%)
- **에러**: 0 (0.0%)

---

## ⚠️  개선 필요 파일 (8개)

### 📄 gangnamunni_report.md

- **file_size**: 파일 크기 경고: 라인 수 과다 (1020). 분할 고려

### 📄 md_analysis_report.md

- **header_hierarchy**: H1 제목 6개 (권장: 1개)

### 📄 README_AUTO_DEBUGGER.md

- **checklist_progress**: 미완료 작업: 5개 (100.0%)
- **header_hierarchy**: H1 제목 26개 (권장: 1개), 라인 44: H1→H3 점프, 라인 59: H1→H3 점프
- **code_block_language**: 코드 블록 3개에 언어 미지정
- **duplicate_headers**: 중복 제목 2개: 환경변수 설정, 전체 프로젝트 분석

### 📄 www_yeoshin_co_kr_report.md

- **file_size**: 파일 크기 경고: 라인 수 과다 (1024). 분할 고려

### 📄 자동디버깅방법.md

- **checklist_progress**: 미완료 작업: 14개 (100.0%)
- **header_hierarchy**: H1 제목 17개 (권장: 1개), 라인 69: H1→H3 점프, 라인 548: H1→H4 점프
- **code_block_language**: 코드 블록 11개에 언어 미지정
- **file_size**: 파일 크기 경고: 라인 수 과다 (1091). 분할 고려

### 📄 크롤링된_사이트별_폴더_위치.md

- **code_block_language**: 코드 블록 1개에 언어 미지정

### 📄 크롤링코드작성방법.md

- **header_hierarchy**: H1 제목 3개 (권장: 1개)
- **code_block_language**: 코드 블록 3개에 언어 미지정
- **duplicate_headers**: 중복 제목 1개: 대상 사이트

### 📄 프롬프트.md

- **header_hierarchy**: H1 제목 4개 (권장: 1개)
- **code_block_language**: 코드 블록 5개에 언어 미지정
- **duplicate_headers**: 중복 제목 3개: 대상 URL

---

## 📋 체크리스트 진행률 (낮은 순위 TOP 15)

| 순위 | 파일 | 진행률 | 완료/전체 | 상태 |
|------|------|--------|----------|------|
| 1 | README_AUTO_DEBUGGER.md | 0.0% | 0/5 | 🔴 |
| 2 | 자동디버깅방법.md | 0.0% | 0/14 | 🔴 |

---

## 🎯 가장 많은 문제 유형

1. **header_hierarchy**: 5개 파일 (62.5%)
2. **code_block_language**: 5개 파일 (62.5%)
3. **file_size**: 3개 파일 (37.5%)
4. **duplicate_headers**: 3개 파일 (37.5%)
5. **checklist_progress**: 2개 파일 (25.0%)

---

## 💡 권장 조치사항

### 우선순위별 작업

1. **🔴 긴급**: 체크리스트 진행률 50% 미만 파일 2개
3. **📝 제목 구조 개선**: 5개 파일
4. **💻 코드 블록 언어 지정**: 5개 파일

---

## 🔄 다음 실행 방법

```bash
# 기본 분석
node auto_debugger.js

# AI 분석 추가 (Claude API 필요)
node auto_debugger.js --ai --api-key=YOUR_KEY

# 또는 환경변수 사용
set ANTHROPIC_API_KEY=YOUR_KEY
node auto_debugger.js --ai

# 특정 폴더만 분석
node auto_debugger.js --root=../hospital-api

# 상세 출력
node auto_debugger.js --verbose
```

---

**생성 시간**: 2026. 1. 29. 오후 3:37:16
**분석 경로**: `h:\carebaby_project\site_get`
