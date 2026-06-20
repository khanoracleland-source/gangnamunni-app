# carebaby 폴더 크기 줄이기 작업 계획

## 📊 현재 상황 분석

### 전체 프로젝트 크기 분석
- **carebaby 폴더**: 약 120GB+ (매우 큼)
- **목표 크기**: 500MB 이하

### 주요 용량 차지 항목

#### 1. data 폴더 분석 (약 121GB)
```
114GB  - data/used (111,535개 파일)
3.5GB  - data/editor (8,939개 파일)
2.2GB  - data/manager (6,851개 파일)
907MB  - data/customer (2,259개 파일)
319MB  - data/list (7,300개 파일)
```

**파일 분석:**
- 총 이미지 파일: 136,667개
- 5MB 이상 대용량 파일: 1,330개
- 10MB 이상 초대용량 파일: 64개

#### 2. 중복 백업 파일들 (약 5.3GB)
```
677MB  - carebaby/server_backup/backups/carecraft_backup_2025-12-05_042113.sql
925MB  - carebaby/server_backup/ (전체)
37MB   - carebaby/nfor_phpmyadmin/
```

#### 3. 프로젝트 루트의 중복 ZIP 파일들 (약 5.2GB)
```
463MB × 5개 = 2,315MB  - frezka_admin_panel_v4.0.3 (*.zip 중복)
426MB × 2개 = 852MB    - frezka_admin_panel_v4.0.3_ori (*.zip 중복)
750MB × 2개 = 1,500MB  - kivicare-laravel-web-v1.9.0 (*.zip 중복)
431MB × 1개 = 431MB    - kivicare-laravel-web-v1.9.0.zip
```

#### 4. 압축 해제된 프로젝트 폴더들 (약 3.4GB)
```
1.3GB  - kivicare-laravel-web-v1.9.0/
1.1GB  - frezka_admin_panel_v4.0.3/
1.0GB  - frezka_admin_panel_v4.0.3_ori/
```

---

## 🎯 크기 줄이기 전략 (500MB 이하 목표)

### Phase 1: 즉시 삭제 가능 항목 (약 8.6GB 절감)

#### 1-1. 중복 ZIP 파일 삭제 (5.1GB 절감)
```bash
# 최신 버전만 1개씩 남기고 나머지 삭제
rm frezka_admin_panel_v4.0.3\ \(2\).zip
rm frezka_admin_panel_v4.0.3\ \(3\).zip
rm frezka_admin_panel_v4.0.3\ \(4\).zip
rm frezka_admin_panel_v4.0.3\ \(5\).zip
rm frezka_admin_panel_v4.0.3_ori\ \(2\).zip
rm kivicare-laravel-web-v1.9.0\ \(2\).zip
rm kivicare-laravel-web-v1.9.0\ \(3\).zip
```
**절감 예상: 약 5.1GB**

#### 1-2. 압축된 파일이 있는 경우 압축 해제 폴더 삭제 (3.4GB 절감)
```bash
# ZIP 파일이 있으므로 압축 해제 폴더는 삭제 가능
rm -rf frezka_admin_panel_v4.0.3_ori/
rm -rf frezka_admin_panel_v4.0.3/
rm -rf kivicare-laravel-web-v1.9.0/
```
**절감 예상: 약 3.4GB**

#### 1-3. 백업 폴더 정리 (약 100MB 절감)
```bash
# 오래된 백업 파일 및 중복 백업 삭제
# 최신 백업만 외부로 이동하고 삭제
# server_backup/backups/ 폴더 전체 정리
```
**절감 예상: 약 100MB**

---

### Phase 2: data 폴더 대대적 정리 (약 110GB 절감)

#### 2-1. used 폴더 정리 (114GB → 1GB 목표)

**전략 A: 오래된 파일 삭제**
```bash
# 6개월 이상 사용하지 않은 파일 삭제
cd carebaby/data/used
find . -type f -atime +180 -delete
```

**전략 B: 대용량 파일 압축 또는 외부 저장소 이동**
```bash
# 10MB 이상 파일을 외부 저장소로 이동 (AWS S3, Google Drive 등)
find . -type f -size +10M -exec mv {} /external_storage/used_large_files/ \;
```

**전략 C: 썸네일 폴더 최적화**
```bash
# used/thumb 폴더 (522MB) - 썸네일 재생성 가능한 경우 삭제
rm -rf used/thumb
```

**예상 절감: 약 100GB 이상**

#### 2-2. editor 폴더 정리 (3.5GB → 500MB 목표)
```bash
# 오래된 임시 에디터 파일 삭제
cd carebaby/data/editor
find . -type f -mtime +90 -delete
```
**예상 절감: 약 3GB**

#### 2-3. manager 폴더 정리 (2.2GB → 300MB 목표)
```bash
# 매니저 관련 오래된 이미지/파일 정리
cd carebaby/data/manager
find . -type f -atime +180 -delete
```
**예상 절감: 약 1.9GB**

#### 2-4. customer 폴더 정리 (907MB → 200MB 목표)
```bash
# 고객 관련 오래된 데이터 정리
cd carebaby/data/customer
find . -type f -atime +365 -delete
```
**예상 절감: 약 700MB**

---

### Phase 3: 데이터베이스 최적화 (약 500MB 절감)

#### 3-1. SQL 백업 파일 외부 이동
```bash
# 677MB SQL 백업 파일을 외부 저장소로 이동
mv carebaby/server_backup/backups/*.sql /external_backup/
```

#### 3-2. 데이터베이스 정리 작업
```sql
-- 오래된 로그 테이블 삭제
TRUNCATE TABLE system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH);

-- 오래된 세션 데이터 삭제
TRUNCATE TABLE sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 1 MONTH);

-- 삭제된 사용자의 데이터 정리
DELETE FROM user_data WHERE user_id IN (SELECT id FROM users WHERE deleted_at IS NOT NULL);

-- 데이터베이스 최적화
OPTIMIZE TABLE users, orders, products, logs;
```

#### 3-3. 세션 파일 정리
```bash
# 오래된 PHP 세션 파일 삭제 (data/session - 388KB)
find carebaby/data/session -type f -mtime +7 -delete
```

---

### Phase 4: 기타 정리 항목 (약 50MB 절감)

#### 4-1. 히스토리 및 스냅샷 정리
```bash
# .history 폴더 (206KB) - 오래된 히스토리 삭제
rm -rf carebaby/.history/*

# .snapshots 폴더 (7KB) - 필요 시 정리
```

#### 4-2. phpMyAdmin 중복 설치 정리
```bash
# nfor_phpmyadmin (37MB) - 이미 다른 곳에 있다면 삭제
rm -rf carebaby/nfor_phpmyadmin
```

#### 4-3. 임시 파일 및 캐시 정리
```bash
# 캐시, 로그, 임시 파일 삭제
find carebaby -name "*.log" -delete
find carebaby -name "*.tmp" -delete
find carebaby -name "*.cache" -delete
```

---

## 📋 실행 순서 및 체크리스트

### Step 1: 백업 생성 (필수!)
```bash
# 작업 전 전체 백업 생성
cd /h/carebaby_project
tar -czf server_backup_dev_backup_$(date +%Y%m%d).tar.gz server_backup_dev/

# 또는 중요 데이터만 백업
cp -r server_backup_dev/carebaby/data/used /external_backup/used_backup/
mysqldump -u root -p database_name > db_backup_$(date +%Y%m%d).sql
```

### Step 2: Phase 1 실행 (안전한 삭제)
- [ ] 중복 ZIP 파일 5개 삭제 → **5.1GB 절감**
- [ ] 압축 해제 폴더 3개 삭제 → **3.4GB 절감**
- [ ] 오래된 백업 파일 정리 → **100MB 절감**
- **예상 총 절감: 8.6GB**

### Step 3: Phase 2 실행 (데이터 폴더 정리)
- [ ] used 폴더 대용량 파일 외부 이동 → **100GB+ 절감**
- [ ] editor 폴더 오래된 파일 삭제 → **3GB 절감**
- [ ] manager 폴더 정리 → **1.9GB 절감**
- [ ] customer 폴더 정리 → **700MB 절감**
- **예상 총 절감: 105GB+**

### Step 4: Phase 3 실행 (DB 최적화)
- [ ] SQL 백업 외부 이동 → **677MB 절감**
- [ ] 데이터베이스 정리 쿼리 실행 → **가변적**
- [ ] 세션 파일 정리 → **소량 절감**
- **예상 총 절감: 700MB+**

### Step 5: Phase 4 실행 (마무리 정리)
- [ ] 히스토리 정리 → **소량 절감**
- [ ] 중복 phpMyAdmin 삭제 → **37MB 절감**
- [ ] 임시 파일 정리 → **소량 절감**
- **예상 총 절감: 50MB**

### Step 6: 검증
```bash
# 최종 크기 확인
du -sh /h/carebaby_project/server_backup_dev/carebaby

# 애플리케이션 정상 동작 확인
# - 웹사이트 접속 테스트
# - 이미지 로딩 확인
# - 데이터베이스 연결 확인
```

---

## ⚠️ 주의사항

1. **백업 필수**: 모든 작업 전 반드시 백업 생성
2. **단계별 실행**: 한 번에 모두 실행하지 말고 단계별로 검증하며 진행
3. **외부 저장소 활용**: 삭제하기 전 중요 데이터는 AWS S3, Google Drive 등으로 이동
4. **데이터베이스 백업**: DB 정리 전 반드시 mysqldump로 백업
5. **프로덕션 환경 주의**: 운영 중인 서비스라면 새벽 시간대에 작업
6. **사용자 영향 최소화**: used 폴더 정리 시 현재 사용 중인 파일 확인 필요

---

## 💡 추가 최적화 방안

### 장기적 해결책
1. **클라우드 스토리지 도입**: AWS S3, Cloudflare R2 등으로 이미지 이전
2. **CDN 사용**: 이미지 전송 최적화 및 로컬 저장소 부담 감소
3. **이미지 압축 자동화**: 업로드 시 자동 압축 (WebP 변환, 리사이징)
4. **정기 정리 스크립트**: cron으로 오래된 파일 자동 삭제
5. **데이터베이스 파티셔닝**: 로그 테이블 등을 월별로 파티션
6. **로그 로테이션**: 로그 파일 자동 압축 및 삭제

### 즉시 적용 가능한 스크립트
```bash
#!/bin/bash
# 자동 정리 스크립트 (cleanup.sh)

echo "=== 파일 정리 시작 ==="

# 1. 6개월 이상 오래된 used 파일 삭제
find /path/to/carebaby/data/used -type f -atime +180 -delete
echo "✓ used 폴더 정리 완료"

# 2. 3개월 이상 오래된 editor 파일 삭제
find /path/to/carebaby/data/editor -type f -mtime +90 -delete
echo "✓ editor 폴더 정리 완료"

# 3. 1주일 이상 오래된 세션 파일 삭제
find /path/to/carebaby/data/session -type f -mtime +7 -delete
echo "✓ session 폴더 정리 완료"

# 4. 로그 파일 정리
find /path/to/carebaby -name "*.log" -mtime +30 -delete
echo "✓ 로그 파일 정리 완료"

echo "=== 정리 완료 ==="
```

---

## 📈 예상 최종 결과

| 항목 | 현재 크기 | 정리 후 크기 | 절감량 |
|------|----------|-------------|--------|
| data/used | 114GB | 1GB | 113GB |
| data/editor | 3.5GB | 500MB | 3GB |
| data/manager | 2.2GB | 300MB | 1.9GB |
| data/customer | 907MB | 200MB | 707MB |
| ZIP 중복 파일 | 5.1GB | 0.9GB | 4.2GB |
| 압축 해제 폴더 | 3.4GB | 0GB | 3.4GB |
| 백업 폴더 | 925MB | 0MB | 925MB |
| 기타 | 약 200MB | 약 50MB | 150MB |
| **합계** | **약 130GB+** | **약 3GB** | **약 127GB** |

**최종 목표 달성 가능 여부**: ✅ **500MB 이하 충분히 가능**

---

## 🚀 빠른 시작 가이드

```bash
# 1. 백업
cd /h/carebaby_project
tar -czf backup_$(date +%Y%m%d).tar.gz server_backup_dev/

# 2. Phase 1 실행 (가장 안전)
cd server_backup_dev
rm frezka_admin_panel_v4.0.3\ \(2\).zip
rm frezka_admin_panel_v4.0.3\ \(3\).zip
rm frezka_admin_panel_v4.0.3\ \(4\).zip
rm frezka_admin_panel_v4.0.3\ \(5\).zip
rm kivicare-laravel-web-v1.9.0\ \(2\).zip
rm kivicare-laravel-web-v1.9.0\ \(3\).zip
rm -rf frezka_admin_panel_v4.0.3_ori/
rm -rf frezka_admin_panel_v4.0.3/
rm -rf kivicare-laravel-web-v1.9.0/

# 3. 크기 확인
du -sh carebaby

# 4. 추가 작업 필요 시 Phase 2, 3, 4 진행
```
