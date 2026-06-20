#!/bin/bash
################################################################################
# 개발 환경을 운영 환경으로 배포
#
# 사용법:
#   ./deploy_to_production.sh              # 대화형 모드
#   ./deploy_to_production.sh --auto       # 자동 모드 (CI/CD용)
################################################################################

set -e

# 색상 코드
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# 경로 설정
BASE_DIR="/var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas"
PROD_DIR="$BASE_DIR/hairwang_full"
DEV_DIR="$BASE_DIR/hairwang_dev"
DEPLOY_DIR="$BASE_DIR/deploy"
BACKUP_DIR="$DEPLOY_DIR/backups"
LOG_DIR="$DEPLOY_DIR/logs"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
LOG_FILE="$LOG_DIR/deployment_$TIMESTAMP.log"

# 옵션
AUTO_MODE=false
ENABLE_MAINTENANCE=false
MAINTENANCE_MESSAGE="시스템 업데이트 중입니다"

for arg in "$@"; do
    case $arg in
        --auto)
            AUTO_MODE=true
            ENABLE_MAINTENANCE=true
            ;;
    esac
done

# 디렉토리 생성
mkdir -p "$BACKUP_DIR" "$LOG_DIR"

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
echo "║  개발 → 운영 환경 배포                 ║"
echo "╚════════════════════════════════════════╝"
echo ""

# 1. 사전 체크
log "[1/8] 사전 체크..."

if [ ! -d "$DEV_DIR" ]; then
    log_error "개발 환경이 존재하지 않습니다: $DEV_DIR"
    exit 1
fi

if [ ! -d "$PROD_DIR" ]; then
    log_error "운영 환경이 존재하지 않습니다: $PROD_DIR"
    exit 1
fi

# 변경된 파일 목록
log "변경된 파일 확인 중..."
CHANGED_FILES=$(rsync -avn --exclude='logs/*' --exclude='cache/*' --exclude='.git/*' "$DEV_DIR/" "$PROD_DIR/" | grep -E '^>f' | awk '{print $2}' | head -20)

if [ -z "$CHANGED_FILES" ]; then
    log_warning "변경된 파일이 없습니다."
    if [ "$AUTO_MODE" = false ]; then
        read -p "계속 진행할까요? (y/n): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 0
        fi
    fi
else
    log "변경된 파일:"
    echo "$CHANGED_FILES" | head -10
    FILE_COUNT=$(echo "$CHANGED_FILES" | wc -l)
    if [ $FILE_COUNT -gt 10 ]; then
        log "... 외 $(($FILE_COUNT - 10))개 파일"
    fi
fi

# 2. 점검 모드 확인
if [ "$AUTO_MODE" = false ]; then
    read -p "점검 모드를 활성화할까요? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        ENABLE_MAINTENANCE=true
        read -p "점검 메시지: " MAINTENANCE_MESSAGE
    fi
fi

# 3. 최종 확인
if [ "$AUTO_MODE" = false ]; then
    echo ""
    echo -e "${YELLOW}배포 요약:${NC}"
    echo "  운영 환경: $PROD_DIR"
    echo "  점검 모드: $([ "$ENABLE_MAINTENANCE" = true ] && echo "활성화" || echo "비활성화")"
    echo "  백업 위치: $BACKUP_DIR/backup_$TIMESTAMP.tar.gz"
    echo ""
    read -p "배포를 시작할까요? (y/n): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log "배포가 취소되었습니다."
        exit 0
    fi
fi

# 4. 점검 모드 활성화
if [ "$ENABLE_MAINTENANCE" = true ]; then
    log "[2/8] 점검 모드 활성화..."
    "$DEPLOY_DIR/maintenance.sh" on "$MAINTENANCE_MESSAGE"
    sleep 2  # 사용자 리다이렉트 대기
fi

# 5. 백업 생성
log "[3/8] 운영 환경 백업 중..."

BACKUP_FILE="$BACKUP_DIR/backup_$TIMESTAMP.tar.gz"
tar -czf "$BACKUP_FILE" -C "$PROD_DIR" . >> "$LOG_FILE" 2>&1

if [ $? -eq 0 ]; then
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    log "백업 완료: $BACKUP_FILE ($BACKUP_SIZE)"
else
    log_error "백업 실패"
    exit 1
fi

# 6. 배포 (rsync)
log "[4/8] 파일 배포 중..."

RSYNC_OPTS="-av --exclude='logs/*' --exclude='cache/*' --exclude='.git/*' --exclude='sessions/*' --exclude='.maintenance'"

rsync $RSYNC_OPTS "$DEV_DIR/" "$PROD_DIR/" >> "$LOG_FILE" 2>&1

if [ $? -eq 0 ]; then
    log "파일 배포 완료"
else
    log_error "파일 배포 실패"
    # 자동 롤백
    log "롤백 시도 중..."
    tar -xzf "$BACKUP_FILE" -C "$PROD_DIR"
    if [ "$ENABLE_MAINTENANCE" = true ]; then
        "$DEPLOY_DIR/maintenance.sh" off
    fi
    exit 1
fi

# 7. 권한 설정
log "[5/8] 권한 설정 중..."

chown -R apache:apache "$PROD_DIR"
find "$PROD_DIR" -type d -exec chmod 755 {} \;
find "$PROD_DIR" -type f -exec chmod 644 {} \;

# 8. 구문 오류 체크
log "[6/8] PHP 구문 검사 중..."

ERROR_COUNT=0
for php_file in $(find "$PROD_DIR/gangnamunni" -name "*.php" -type f | head -10); do
    php -l "$php_file" >> "$LOG_FILE" 2>&1
    if [ $? -ne 0 ]; then
        log_error "구문 오류: $php_file"
        ERROR_COUNT=$((ERROR_COUNT + 1))
    fi
done

if [ $ERROR_COUNT -gt 0 ]; then
    log_warning "$ERROR_COUNT 개 파일에서 구문 오류 발견"
fi

# 9. 캐시 클리어
log "[7/8] 캐시 클리어..."

rm -rf "$PROD_DIR/gangnamunni/cache/*" 2>/dev/null || true
log "캐시 클리어 완료"

# 10. 점검 모드 해제
if [ "$ENABLE_MAINTENANCE" = true ]; then
    log "[8/8] 점검 모드 해제..."
    "$DEPLOY_DIR/maintenance.sh" off
fi

# 완료
log ""
log "✅ 배포 완료!"
log ""
log "📁 운영 환경: $PROD_DIR"
log "💾 백업 파일: $BACKUP_FILE"
log "📝 로그 파일: $LOG_FILE"
log ""

if [ $ERROR_COUNT -gt 0 ]; then
    log_warning "⚠️  구문 오류가 발견되었습니다. 로그를 확인하세요."
fi

# 배포 후 검증 안내
echo ""
echo -e "${BLUE}배포 후 검증 체크리스트:${NC}"
echo "  [ ] 메인 페이지 접속 확인"
echo "  [ ] 주요 기능 동작 확인"
echo "  [ ] 에러 로그 확인: tail -f /var/log/httpd/error_log"
echo ""
