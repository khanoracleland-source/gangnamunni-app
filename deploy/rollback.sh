#!/bin/bash
################################################################################
# 배포 롤백 스크립트
#
# 사용법:
#   ./rollback.sh                          # 대화형 모드 (백업 선택)
#   ./rollback.sh backup_20260208_143052   # 특정 백업으로 롤백
################################################################################

set -e

# 색상 코드
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# 경로 설정
BASE_DIR="/var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas"
PROD_DIR="$BASE_DIR/hairwang_full"
DEPLOY_DIR="$BASE_DIR/deploy"
BACKUP_DIR="$DEPLOY_DIR/backups"
LOG_DIR="$DEPLOY_DIR/logs"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
LOG_FILE="$LOG_DIR/rollback_$TIMESTAMP.log"

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
echo "║  배포 롤백                             ║"
echo "╚════════════════════════════════════════╝"
echo ""

# 백업 목록 조회
BACKUPS=($(ls -t "$BACKUP_DIR"/backup_*.tar.gz 2>/dev/null))

if [ ${#BACKUPS[@]} -eq 0 ]; then
    log_error "사용 가능한 백업이 없습니다."
    exit 1
fi

# 백업 선택
SELECTED_BACKUP=""

if [ -n "$1" ]; then
    # 명령행 인수로 백업 지정
    SELECTED_BACKUP="$BACKUP_DIR/$1.tar.gz"
    if [ ! -f "$SELECTED_BACKUP" ]; then
        log_error "백업 파일을 찾을 수 없습니다: $SELECTED_BACKUP"
        exit 1
    fi
else
    # 대화형 선택
    echo "사용 가능한 백업:"
    echo ""
    for i in "${!BACKUPS[@]}"; do
        backup="${BACKUPS[$i]}"
        backup_name=$(basename "$backup" .tar.gz)
        backup_date=$(echo "$backup_name" | sed 's/backup_//' | sed 's/_/ /')
        backup_size=$(du -h "$backup" | cut -f1)
        backup_time=$(stat -c %y "$backup" | cut -d'.' -f1)

        if [ $i -eq 0 ]; then
            echo -e "${GREEN}[$((i+1))]${NC} $backup_date ($backup_size) - ${GREEN}최신${NC}"
        else
            echo "[$((i+1))] $backup_date ($backup_size)"
        fi
    done

    echo ""
    read -p "롤백할 백업 번호 (1-${#BACKUPS[@]}): " choice

    if [[ ! "$choice" =~ ^[0-9]+$ ]] || [ "$choice" -lt 1 ] || [ "$choice" -gt ${#BACKUPS[@]} ]; then
        log_error "잘못된 선택입니다."
        exit 1
    fi

    SELECTED_BACKUP="${BACKUPS[$((choice-1))]}"
fi

log "선택된 백업: $(basename $SELECTED_BACKUP)"

# 최종 확인
echo ""
echo -e "${YELLOW}⚠️  경고:${NC}"
echo "현재 운영 환경의 모든 파일이 삭제되고 백업으로 복원됩니다."
echo ""
read -p "정말 롤백하시겠습니까? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    log "롤백이 취소되었습니다."
    exit 0
fi

# 롤백 시작
log ""
log "[1/5] 점검 모드 활성화..."
"$DEPLOY_DIR/maintenance.sh" on "긴급 롤백 중입니다"
sleep 2

log "[2/5] 현재 상태 백업 중..."
EMERGENCY_BACKUP="$BACKUP_DIR/emergency_backup_$TIMESTAMP.tar.gz"
tar -czf "$EMERGENCY_BACKUP" -C "$PROD_DIR" . >> "$LOG_FILE" 2>&1
log "긴급 백업 생성: $EMERGENCY_BACKUP"

log "[3/5] 운영 환경 복원 중..."

# 기존 파일 삭제 (주의!)
rm -rf "$PROD_DIR"/*

# 백업 복원
tar -xzf "$SELECTED_BACKUP" -C "$PROD_DIR" >> "$LOG_FILE" 2>&1

if [ $? -eq 0 ]; then
    log "백업 복원 완료"
else
    log_error "백업 복원 실패"
    log "긴급 백업에서 복구 시도 중..."
    tar -xzf "$EMERGENCY_BACKUP" -C "$PROD_DIR"
    exit 1
fi

log "[4/5] 권한 설정 중..."
chown -R apache:apache "$PROD_DIR"
find "$PROD_DIR" -type d -exec chmod 755 {} \;
find "$PROD_DIR" -type f -exec chmod 644 {} \;

log "[5/5] 점검 모드 해제..."
"$DEPLOY_DIR/maintenance.sh" off

# 완료
log ""
log "✅ 롤백 완료!"
log ""
log "📁 복원된 백업: $(basename $SELECTED_BACKUP)"
log "💾 긴급 백업: $EMERGENCY_BACKUP"
log "📝 로그 파일: $LOG_FILE"
log ""

echo ""
echo -e "${YELLOW}롤백 후 확인:${NC}"
echo "  [ ] 메인 페이지 접속 확인"
echo "  [ ] 주요 기능 동작 확인"
echo "  [ ] 에러 로그 확인"
echo ""

# 구문 검사
log "PHP 구문 검사 중..."
ERROR_COUNT=0
for php_file in $(find "$PROD_DIR/gangnamunni" -name "*.php" -type f | head -10); do
    php -l "$php_file" >> "$LOG_FILE" 2>&1
    if [ $? -ne 0 ]; then
        ERROR_COUNT=$((ERROR_COUNT + 1))
    fi
done

if [ $ERROR_COUNT -eq 0 ]; then
    log "✓ PHP 구문 검사 통과"
else
    log_warning "⚠️  $ERROR_COUNT 개 파일에서 구문 오류 발견"
fi
