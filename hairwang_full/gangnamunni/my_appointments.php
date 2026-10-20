<?php
/**
 * ========================================
 * 서울미녀 - 내 예약 조회 페이지
 * ========================================
 *
 * 전화번호로 예약/상담 내역을 조회하고
 * HMS 연동된 예약의 실시간 상태를 확인
 *
 * @file    gangnamunni/my_appointments.php
 * @created 2026-02-10 (Phase 2-3)
 */

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/infyhms_api.php';

// 조회 처리
$phone = isset($_GET['phone']) ? trim($_GET['phone']) : '';
$bookings = [];
$consultations = [];
$hms_statuses = [];  // HMS 예약 상태 캐시

if (!empty($phone)) {
    $phone_esc = mysqli_real_escape_string($connect, $phone);

    // gangnamunni_bookings 조회
    $result = mysqli_query($connect,
        "SELECT b.*, h.name AS hospital_name_display, h.hms_hospital_id
         FROM gangnamunni_bookings b
         LEFT JOIN gangnamunni_hospitals h ON b.hospital_id = h.id
         WHERE b.phone LIKE '%{$phone_esc}%'
         ORDER BY b.created_at DESC
         LIMIT 20");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $bookings[] = $row;
        }
    }

    // gangnamunni_consultations 조회
    $result = mysqli_query($connect,
        "SELECT c.*, h.name AS hospital_name_display, h.hms_hospital_id
         FROM gangnamunni_consultations c
         LEFT JOIN gangnamunni_hospitals h ON c.hospital_id = h.id
         WHERE c.phone LIKE '%{$phone_esc}%'
         ORDER BY c.created_at DESC
         LIMIT 20");
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $consultations[] = $row;
        }
    }

    // HMS 예약 상태 실시간 조회
    // HMS 독립 모드 체크
    if (!function_exists("is_hms_standalone") || !is_hms_standalone()) {
    $hms_api = get_infyhms_api();
    $hms_ids = [];
    foreach ($bookings as $b) {
        if (!empty($b['hms_appointment_id'])) {
            $hms_ids[] = (int)$b['hms_appointment_id'];
        }
    }
    foreach ($consultations as $c) {
        if (!empty($c['hms_appointment_id'])) {
            $hms_ids[] = (int)$c['hms_appointment_id'];
        }
    }

    foreach (array_unique($hms_ids) as $hms_id) {
        try {
            $status_result = $hms_api->getAppointmentStatus($hms_id);
            if (!empty($status_result['success']) && !empty($status_result['data'])) {
                $hms_statuses[$hms_id] = $status_result['data'];
            }
        } catch (Exception $e) {
            // API 실패 무시
        }
    }
}

    } // end standalone check
// HMS 예약 상태 라벨
function getStatusLabel($hms_data) {
    if (empty($hms_data)) return ['label' => '접수됨', 'color' => '#2196F3'];
    $is_completed = $hms_data['is_completed'] ?? 0;
    if ($is_completed == 1) return ['label' => '진료완료', 'color' => '#4CAF50'];
    if ($is_completed == 2) return ['label' => '취소됨', 'color' => '#F44336'];
    return ['label' => '대기중', 'color' => '#FF9800'];
}

// 레이아웃 변수
$page_title = '내 ' . term('appointment') . ' 조회';
function getPatientStatusLabel($local_status, $hms_data, $consultation = false) {
    $labels = $consultation
        ? [
            'pending' => ['label' => '접수대기', 'color' => '#9E9E9E'],
            'approved' => ['label' => '상담확정', 'color' => '#198754'],
            'completed' => ['label' => '상담완료', 'color' => '#4CAF50'],
            'rejected' => ['label' => '상담거절', 'color' => '#F44336'],
            'cancelled' => ['label' => '추소됨', 'color' => '#F44336'],
        ]
        : [
            'pending' => ['label' => '예약대기', 'color' => '#FF9800'],
            'confirmed' => ['label' => '예약확정', 'color' => '#198754'],
            'completed' => ['label' => '진료완료', 'color' => '#4CAF50'],
            'cancelled' => ['label' => '추소됨', 'color' => '#F44336'],
            'no_show' => ['label' => '노쇼', 'color' => '#F44336'],
        ];
    return $labels[(string)$local_status] ?? getStatusLabel($hms_data);
}

$show_search = false;

$page_css = '
.page-header {
    padding: 12px 20px;
}

.page-header-content {
    max-width: 640px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    gap: 12px;
}

.back-btn {
    font-size: 24px;
    cursor: pointer;
    text-decoration: none;
    color: #000;
}

.page-title {
    font-size: 18px;
    font-weight: bold;
}

.search-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.search-form {
    display: flex;
    gap: 10px;
}

.search-form input {
    flex: 1;
    padding: 12px 16px;
    border: 1px solid #E0E0E0;
    border-radius: 8px;
    font-size: 14px;
}

.search-form input:focus {
    outline: none;
    border-color: #AC1830;
}

.search-form button {
    padding: 12px 24px;
    background: #AC1830;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    white-space: nowrap;
}

.results-section {
    margin-bottom: 20px;
}

.results-title {
    font-size: 16px;
    font-weight: bold;
    color: #333;
    margin-bottom: 12px;
    padding-left: 4px;
}

.appointment-card {
    background: white;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-left: 4px solid #AC1830;
}

.appointment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.appointment-hospital {
    font-size: 16px;
    font-weight: bold;
    color: #333;
}

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    color: white;
}

.appointment-info {
    font-size: 13px;
    color: #666;
    line-height: 1.8;
}

.appointment-info strong {
    color: #333;
    min-width: 70px;
    display: inline-block;
}

.hms-id {
    font-size: 12px;
    color: #999;
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #f0f0f0;
}

.no-results {
    text-align: center;
    padding: 40px 20px;
    color: #999;
    font-size: 14px;
}

.no-results-icon {
    font-size: 48px;
    margin-bottom: 12px;
}
';

$page_js = '
// 전화번호 자동 포맷팅
var phoneInput = document.getElementById("searchPhone");
if (phoneInput) {
    phoneInput.addEventListener("input", function(e) {
        var value = e.target.value.replace(/[^0-9]/g, "");
        if (value.length > 3 && value.length <= 7) {
            value = value.slice(0, 3) + "-" + value.slice(3);
        } else if (value.length > 7) {
            value = value.slice(0, 3) + "-" + value.slice(3, 7) + "-" + value.slice(7, 11);
        }
        e.target.value = value;
    });
}
';

include __DIR__ . '/includes/header.php';
?>
<meta name="csrf-token" content="<?php echo htmlspecialchars(hash('sha256', session_id() . 'gangnamunni_csrf')); ?>">

    <!-- 페이지 헤더 -->
    <div class="page-header">
        <div class="page-header-content">
            <a href="index.php" class="back-btn">&#8592;</a>
            <div class="page-title">내 예약 조회</div>
        </div>
    </div>

    <!-- 전화번호 검색 -->
    <div class="search-card">
        <form method="GET" class="search-form">
            <input type="tel" name="phone" id="searchPhone"
                   placeholder="전화번호 입력 (010-0000-0000)"
                   value="<?php echo htmlspecialchars($phone); ?>" required>
            <button type="submit">조회</button>
        </form>
    </div>

    <?php if (!empty($phone)): ?>

        <?php if (empty($bookings) && empty($consultations)): ?>
        <!-- 결과 없음 -->
        <div class="no-results">
            <div class="no-results-icon">&#x1F50D;</div>
            <p>"<?php echo htmlspecialchars($phone); ?>"로 등록된 예약/상담 내역이 없습니다.</p>
            <p style="margin-top:8px; font-size:12px;">전화번호를 다시 확인해주세요.</p>
        </div>
        <?php endif; ?>

        <?php if (!empty($bookings)): ?>
        <!-- 예약 내역 -->
        <div class="results-section">
            <div class="results-title">&#x1F4C5; 예약 내역 (<?php echo count($bookings); ?>건)</div>
            <?php foreach ($bookings as $b):
                $hms_id = $b['hms_appointment_id'] ?? null;
                $hms_data = $hms_id ? ($hms_statuses[$hms_id] ?? null) : null;
                $status = getPatientStatusLabel($b['status'] ?? '', $hms_data);
            ?>
            <div class="appointment-card">
                <div class="appointment-header">
                    <div class="appointment-hospital">
                        <?php echo htmlspecialchars($b['hospital_name_display'] ?? $b['hospital_name'] ?? ''); ?>
                    </div>
                    <span class="status-badge" style="background:<?php echo $status['color']; ?>">
                        <?php echo $status['label']; ?>
                    </span>
                </div>
                <div class="appointment-info">
                    <div><strong>예약자:</strong> <?php echo htmlspecialchars($b['name']); ?></div>
                    <div><strong>시술:</strong> <?php echo htmlspecialchars($b['treatment'] ?? ''); ?></div>
                    <div><strong>예약일:</strong> <?php echo htmlspecialchars(($b['booking_date'] ?? '') . ' ' . ($b['booking_time'] ?? '')); ?></div>
                    <div><strong>신청일:</strong> <?php echo htmlspecialchars($b['created_at'] ?? ''); ?></div>
                    <?php if (!empty($b['memo'])): ?>
                    <div><strong>메모:</strong> <?php echo htmlspecialchars($b['memo']); ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($hms_id): ?>
                <div class="hms-id">
                    예약번호: #<?php echo $hms_id; ?>
                    <?php if ($hms_data): ?>
                     | 의사: <?php echo htmlspecialchars($hms_data['doctor_name'] ?? '배정중'); ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            
<?php
                $bk_status = $b['status'] ?? ($b['booking_status'] ?? '');
                $no_cancel = ['cancelled', '취소됨', 'completed', '완료', '진료완료'];
                if (!in_array($bk_status, $no_cancel)): ?>
                <button class="btn-cancel-booking" onclick="event.stopPropagation(); cancelBooking(<?php echo (int)$b['id']; ?>, 'booking')">취소</button>
                <?php endif; ?>
            
<?php
                $cs_status = $c['status'] ?? '';
                $no_cancel_c = ['cancelled', '취소됨', 'completed', '완료'];
                if (!in_array($cs_status, $no_cancel_c)): ?>
                <button class="btn-cancel-booking" onclick="event.stopPropagation(); cancelBooking(<?php echo (int)$c['id']; ?>, 'consultation')">취소</button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($consultations)): ?>
        <!-- 상담 내역 -->
        <div class="results-section">
            <div class="results-title">&#x1F4AC; 상담 내역 (<?php echo count($consultations); ?>건)</div>
            <?php foreach ($consultations as $c):
                $hms_id = $c['hms_appointment_id'] ?? null;
                $hms_data = $hms_id ? ($hms_statuses[$hms_id] ?? null) : null;
                $status = getPatientStatusLabel($c['status'] ?? '', $hms_data, true);
            ?>
            <div class="appointment-card" style="border-left-color: #2196F3;">
                <div class="appointment-header">
                    <div class="appointment-hospital">
                        <?php echo htmlspecialchars($c['hospital_name_display'] ?? $c['hospital_name'] ?? ''); ?>
                    </div>
                    <?php if ($hms_id || isset($status)): ?>
                    <span class="status-badge" style="background:<?php echo $status['color']; ?>">
                        <?php echo $status['label']; ?>
                    </span>
                    <?php else: ?>
                    <span class="status-badge" style="background:#9E9E9E">접수대기</span>
                    <?php endif; ?>
                </div>
                <div class="appointment-info">
                    <div><strong>신청자:</strong> <?php echo htmlspecialchars($c['name']); ?></div>
                    <div><strong>상담유형:</strong> <?php echo ($c['consultation_type'] ?? 'general') === 'chat' ? '채팅상담' : '일반상담'; ?></div>
                    <?php if (!empty($c['treatment'])): ?>
                    <div><strong>관심시술:</strong> <?php echo htmlspecialchars($c['treatment']); ?></div>
                    <?php endif; ?>
                    <div><strong>신청일:</strong> <?php echo htmlspecialchars($c['created_at'] ?? ''); ?></div>
                    <?php if (!empty($c['message'])): ?>
                    <div><strong>내용:</strong> <?php echo nl2br(htmlspecialchars(mb_substr($c['message'], 0, 100))); ?><?php echo mb_strlen($c['message'] ?? '') > 100 ? '...' : ''; ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($hms_id): ?>
                <div class="hms-id">접수번호: #<?php echo $hms_id; ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    <?php else: ?>
    <!-- 안내 -->
    <div class="no-results">
        <div class="no-results-icon">&#x1F4CB;</div>
        <p>전화번호를 입력하면 예약/상담 내역을 확인할 수 있습니다.</p>
        <p style="margin-top:12px; font-size:12px; color:#999;">
            예약 시 입력한 전화번호로 검색됩니다.
        </p>
    </div>
    <?php endif; ?>


<style>
.btn-cancel-booking {
    display: inline-block;
    padding: 6px 14px;
    background: #fff;
    color: #e53e3e;
    border: 1px solid #e53e3e;
    border-radius: 6px;
    font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 8px;
}
.btn-cancel-booking:hover {
    background: #e53e3e;
    color: #fff;
}
.cancel-modal-overlay {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    justify-content: center;
    align-items: center;
}
.cancel-modal-overlay.show {
    display: flex;
}
.cancel-modal {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    width: 90%;
    max-width: 400px;
    text-align: center;
}
.cancel-modal h3 {
    font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1));
    margin-bottom: 16px;
    color: #1a1a1a;
}
.cancel-modal textarea {
    width: 100%;
    min-height: 80px;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 10px;
    font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
    resize: vertical;
    margin-bottom: 16px;
    box-sizing: border-box;
}
.cancel-modal .modal-actions {
    display: flex;
    gap: 10px;
}
.cancel-modal .modal-actions button {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 8px;
    font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1));
    font-weight: 600;
    cursor: pointer;
}
.cancel-modal .btn-modal-cancel {
    background: #f5f5f5;
    color: #666;
}
.cancel-modal .btn-modal-confirm {
    background: #e53e3e;
    color: #fff;
}
</style>

<!-- 예약 취소 모달 -->
<div class="cancel-modal-overlay" id="cancelModal">
    <div class="cancel-modal">
        <h3>예약 취소</h3>
        <p style="font-size:14px;color:#666;margin-bottom:12px;">취소 사유를 입력해주세요.</p>
        <textarea id="cancelReason" placeholder="취소 사유 (선택)"></textarea>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="closeCancelModal()">돌아가기</button>
            <button class="btn-modal-confirm" onclick="confirmCancel()">취소하기</button>
        </div>
    </div>
</div>

<script>
var _cancelBookingId = 0;
var _cancelType = 'booking';

function cancelBooking(id, type) {
    _cancelBookingId = id;
    _cancelType = type || 'booking';
    document.getElementById('cancelReason').value = '';
    document.getElementById('cancelModal').classList.add('show');
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.remove('show');
}

function confirmCancel() {
    var reason = document.getElementById('cancelReason').value;
    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    csrfToken = csrfToken ? csrfToken.getAttribute('content') : '';

    fetch('api/cancel_booking.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
            booking_id: _cancelBookingId,
            type: _cancelType,
            reason: reason
        })
    })
    .then(function(res) { return res.json(); })
    .then(function(data) {
        if (data.success) {
            alert(data.data.message || '취소되었습니다.');
            window.location.reload();
        } else {
            alert(data.message || '취소에 실패했습니다.');
        }
        closeCancelModal();
    })
    .catch(function(err) {
        alert('네트워크 오류가 발생했습니다.');
        closeCancelModal();
    });
}

document.getElementById('cancelModal').addEventListener('click', function(e) {
    if (e.target === this) closeCancelModal();
});
</script>

<?php
echo '<div style="height:70px;"></div>';
include_once __DIR__ . '/includes/bottom_nav.php';
include __DIR__ . '/includes/footer.php';
if (isset($connect)) mysqli_close($connect);
?>
