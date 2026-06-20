#!/bin/bash
################################################################################
# 운영 환경을 개발 환경으로 클론
#
# 사용법:
#   ./clone_to_dev.sh              # 대화형 모드
#   ./clone_to_dev.sh --auto       # 자동 모드 (cron용)
#   ./clone_to_dev.sh --with-data  # 데이터 포함 DB 복사
################################################################################

set -e  # 오류 발생 시 스크립트 중단

# 색상 코드
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 경로 설정
BASE_DIR="/var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas"
PROD_DIR="$BASE_DIR/hairwang_full"
DEV_DIR="$BASE_DIR/hairwang_dev"
LOG_DIR="$BASE_DIR/deploy/logs"
LOG_FILE="$LOG_DIR/clone_$(date +%Y%m%d_%H%M%S).log"

# DB 설정
DB_PROD="hairwang"
DB_DEV="hairwang_dev"
DB_USER="root"
DB_PASS="<REMOVED_DB_PASSWORD>"

# 옵션 파싱
AUTO_MODE=false
WITH_DATA=false

for arg in "$@"; do
    case $arg in
        --auto)
            AUTO_MODE=true
            ;;
        --with-data)
            WITH_DATA=true
            ;;
    esac
done

# 로그 디렉토리 생성
mkdir -p "$LOG_DIR"

# 로그 함수
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a "$LOG_FILE"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a "$LOG_FILE"
}

# 배너
echo ""
echo "╔════════════════════════════════════════╗"
echo "║  운영 → 개발 환경 클론                 ║"
echo "╚════════════════════════════════════════╝"
echo ""

# 1. 사전 체크
log "[1/6] 사전 체크..."

if [ ! -d "$PROD_DIR" ]; then
    log_error "운영 환경이 존재하지 않습니다: $PROD_DIR"
    exit 1
fi

if [ -d "$DEV_DIR" ] && [ "$AUTO_MODE" = false ]; then
    read -p "개발 환경이 이미 존재합니다. 덮어쓸까요? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log "작업이 취소되었습니다."
        exit 0
    fi
fi

# 2. 파일 복사
log "[2/6] 파일 복사 중..."

# 제외할 파일/디렉토리
EXCLUDE_DIRS=(
    "logs/*"
    "cache/*"
    "sessions/*"
    ".git/*"
    ".maintenance"
    "*.log"
    "*.pid"
)

# rsync 옵션 구성
RSYNC_OPTS="-av --delete"
for exclude in "${EXCLUDE_DIRS[@]}"; do
    RSYNC_OPTS="$RSYNC_OPTS --exclude=$exclude"
done

# rsync 실행
rsync $RSYNC_OPTS "$PROD_DIR/" "$DEV_DIR/" >> "$LOG_FILE" 2>&1

if [ $? -eq 0 ]; then
    log "파일 복사 완료"
else
    log_error "파일 복사 실패"
    exit 1
fi

# 3. DB 클론
log "[3/6] DB 클론 중..."

# 개발 DB가 존재하면 삭제
mysql -u "$DB_USER" -p"$DB_PASS" -e "DROP DATABASE IF EXISTS $DB_DEV;" 2>> "$LOG_FILE"

# 새 DB 생성
mysql -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE $DB_DEV CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>> "$LOG_FILE"

if [ "$WITH_DATA" = true ]; then
    log "데이터 포함하여 DB 복사 중..."
    mysqldump -u "$DB_USER" -p"$DB_PASS" "$DB_PROD" | mysql -u "$DB_USER" -p"$DB_PASS" "$DB_DEV" 2>> "$LOG_FILE"
else
    log "테이블 구조만 복사 중..."
    mysqldump -u "$DB_USER" -p"$DB_PASS" --no-data "$DB_PROD" | mysql -u "$DB_USER" -p"$DB_PASS" "$DB_DEV" 2>> "$LOG_FILE"
fi

if [ $? -eq 0 ]; then
    log "DB 클론 완료"
else
    log_error "DB 클론 실패"
    exit 1
fi

# 4. 개발 환경 설정 적용
log "[4/6] 개발 환경 설정 적용 중..."

# dbconfig.php 수정 (DB명 변경)
if [ -f "$DEV_DIR/gangnamunni/dbconfig.php" ]; then
    sed -i "s/define('G5_MYSQL_DB', '$DB_PROD')/define('G5_MYSQL_DB', '$DB_DEV')/g" "$DEV_DIR/gangnamunni/dbconfig.php"
    log "DB 설정 업데이트 완료"
fi

# data/dbconfig.php도 수정 (Gnuboard 설정)
if [ -f "$DEV_DIR/data/dbconfig.php" ]; then
    sed -i "s/\$db_name = '$DB_PROD'/\$db_name = '$DB_DEV'/g" "$DEV_DIR/data/dbconfig.php"
    log "Gnuboard DB 설정 업데이트 완료"
fi

# 5. 개발 배너 추가
log "[5/6] 개발 환경 배너 추가 중..."

DEV_BANNER='<!-- DEV ENVIRONMENT BANNER -->
<div style="background: linear-gradient(135deg, #ff9800 0%, #f44336 100%); color: white; padding: 10px 20px; text-align: center; font-weight: 600; position: sticky; top: 0; z-index: 10000; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
    🔧 개발 환경 (DEV) - 테스트 전용
</div>
<!-- /DEV ENVIRONMENT BANNER -->'

# 주요 PHP 파일에 배너 추가 (이미 있으면 건너뜀)
for file in "$DEV_DIR/gangnamunni/index.php" "$DEV_DIR/gangnamunni/admin_ads.php"; do
    if [ -f "$file" ] && ! grep -q "DEV ENVIRONMENT BANNER" "$file"; then
        # <body> 태그 다음에 배너 삽입
        sed -i "s|<body[^>]*>|&\n$DEV_BANNER|I" "$file"
        log "배너 추가: $(basename $file)"
    fi
done

# 6. 권한 설정
log "[6/6] 권한 설정 중..."

chown -R apache:apache "$DEV_DIR"
find "$DEV_DIR" -type d -exec chmod 755 {} \;
find "$DEV_DIR" -type f -exec chmod 644 {} \;

# 로그 완료
log ""
log "✅ 클론 완료!"
log ""
log "📁 개발 환경 경로: $DEV_DIR"
log "🌐 개발 URL: https://hms.seoulbeauty.app/hairwang_dev/gangnamunni/"
log "🗄️  개발 DB: $DB_DEV"
log "📝 로그 파일: $LOG_FILE"
log ""

if [ "$WITH_DATA" = false ]; then
    log_warning "⚠️  DB 데이터는 복사되지 않았습니다. 테스트 데이터를 직접 입력하세요."
fi
