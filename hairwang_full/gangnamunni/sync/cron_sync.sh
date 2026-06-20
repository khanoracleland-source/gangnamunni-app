#!/bin/bash
# HMS ↔ 서울미녀 DB 동기화 크론잡 스크립트
#
# 크론탭 설정:
#   */5 * * * * /path/to/gangnamunni/sync/cron_sync.sh >> /var/log/gangnamunni_sync.log 2>&1

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PHP_BIN="/usr/bin/php"

echo "=== DB 동기화 시작: $(date) ==="

# 1. HMS → 서울미녀 (병원, 의사 정보)
echo "1. HMS → 서울미녀 동기화..."
$PHP_BIN "$SCRIPT_DIR/sync_hms_to_gangnamunni.php"

# 2. 서울미녀 → HMS (상담 신청)
echo "2. 서울미녀 → HMS 동기화..."
$PHP_BIN "$SCRIPT_DIR/sync_gangnamunni_to_hms.php"

# 3. 회원 양방향 동기화 (Gnuboard ↔ HMS)
echo "3. 회원 양방향 동기화..."
$PHP_BIN "$SCRIPT_DIR/sync_members.php"

echo "=== DB 동기화 완료: $(date) ==="
echo ""

# Step 4: hospital_type <-> gangnamunni_categories bidirectional sync
php /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/hairwang_full/gangnamunni/sync/sync_hospital_type.php >> /var/log/gangnamunni_sync.log 2>&1
