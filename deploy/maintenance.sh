#!/bin/bash
################################################################################
# 점검 모드 제어 스크립트
#
# 사용법:
#   ./maintenance.sh on "점검 메시지"          # 점검 모드 활성화
#   ./maintenance.sh off                       # 점검 모드 비활성화
#   ./maintenance.sh status                    # 점검 모드 상태 확인
#   ./maintenance.sh schedule "메시지" 02:00 04:00  # 점검 예약
################################################################################

set -e

# 색상 코드
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# 경로 설정
GANGNAMUNNI_DIR="/var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/hairwang_full/gangnamunni"
MAINTENANCE_FILE="$GANGNAMUNNI_DIR/.maintenance"

# 함수: 점검 모드 활성화
enable_maintenance() {
    local message="${1:-시스템 점검 중입니다}"
    local start_time="${2:-$(date '+%Y-%m-%d %H:%M:%S')}"
    local end_time="${3:-}"

    cat > "$MAINTENANCE_FILE" <<EOF
{
    "enabled": true,
    "message": "$message",
    "start_time": "$start_time",
    "end_time": "$end_time",
    "admin_bypass": true,
    "auto_end": true,
    "allowed_ips": ["127.0.0.1", "5.104.82.35"],
    "contact_email": "support@seoulbeauty.app",
    "created_at": "$(date '+%Y-%m-%d %H:%M:%S')",
    "created_by": "cli"
}
EOF

    echo -e "${GREEN}✓${NC} 점검 모드 활성화됨"
    echo "메시지: $message"
    if [ -n "$end_time" ]; then
        echo "종료 예정: $end_time"
    fi
}

# 함수: 점검 모드 비활성화
disable_maintenance() {
    if [ -f "$MAINTENANCE_FILE" ]; then
        rm "$MAINTENANCE_FILE"
        echo -e "${GREEN}✓${NC} 점검 모드 비활성화됨"
    else
        echo -e "${YELLOW}!${NC} 점검 모드가 활성화되어 있지 않습니다"
    fi
}

# 함수: 점검 모드 상태 확인
check_status() {
    if [ -f "$MAINTENANCE_FILE" ]; then
        echo -e "${YELLOW}점검 모드:${NC} 활성화됨"
        echo ""
        cat "$MAINTENANCE_FILE" | python3 -m json.tool
    else
        echo -e "${GREEN}점검 모드:${NC} 비활성화됨"
    fi
}

# 함수: 점검 예약 (cron 스케줄 추가)
schedule_maintenance() {
    local message="$1"
    local start_time="$2"
    local end_time="$3"

    # 오늘 날짜 + 시간
    local today=$(date '+%Y-%m-%d')
    local start_datetime="$today $start_time:00"
    local end_datetime="$today $end_time:00"

    # 시작 시간의 cron 표현식 생성
    local start_hour=$(echo "$start_time" | cut -d':' -f1)
    local start_min=$(echo "$start_time" | cut -d':' -f2)

    # 종료 시간의 cron 표현식 생성
    local end_hour=$(echo "$end_time" | cut -d':' -f1)
    local end_min=$(echo "$end_time" | cut -d':' -f2)

    # 현재 crontab 백업
    crontab -l > /tmp/crontab_backup 2>/dev/null || true

    # 새 cron 작업 추가
    (crontab -l 2>/dev/null; echo "$start_min $start_hour * * * $0 on \"$message\" \"$start_datetime\" \"$end_datetime\"") | crontab -
    (crontab -l 2>/dev/null; echo "$end_min $end_hour * * * $0 off") | crontab -

    echo -e "${GREEN}✓${NC} 점검 예약 완료"
    echo "시작: $start_time (매일)"
    echo "종료: $end_time (매일)"
    echo ""
    echo "crontab에 다음 작업이 추가되었습니다:"
    crontab -l | tail -2
}

# 메인 로직
case "${1:-}" in
    on|enable)
        enable_maintenance "$2" "$3" "$4"
        ;;
    off|disable)
        disable_maintenance
        ;;
    status)
        check_status
        ;;
    schedule)
        if [ $# -lt 4 ]; then
            echo "사용법: $0 schedule \"메시지\" HH:MM HH:MM"
            echo "예시: $0 schedule \"정기 점검\" 02:00 04:00"
            exit 1
        fi
        schedule_maintenance "$2" "$3" "$4"
        ;;
    check_schedule)
        # cron에서 호출되어 예약된 점검 시간인지 확인
        check_status
        ;;
    *)
        echo "점검 모드 제어 스크립트"
        echo ""
        echo "사용법:"
        echo "  $0 on \"메시지\"                     # 즉시 점검 모드 활성화"
        echo "  $0 on \"메시지\" \"시작\" \"종료\"    # 시간 지정 점검"
        echo "  $0 off                               # 점검 모드 비활성화"
        echo "  $0 status                            # 현재 상태 확인"
        echo "  $0 schedule \"메시지\" HH:MM HH:MM   # 점검 예약 (매일)"
        echo ""
        echo "예시:"
        echo "  $0 on \"긴급 점검 중입니다\""
        echo "  $0 on \"DB 업그레이드\" \"2026-02-09 02:00:00\" \"2026-02-09 04:00:00\""
        echo "  $0 schedule \"정기 점검\" 02:00 04:00"
        exit 1
        ;;
esac
