<?php
/**
 * 의사 스케줄 AJAX API 엔드포인트
 *
 * GET ?doctor_id=X&date=YYYY-MM-DD → 의사의 해당 날짜 가용 시간대
 *
 * HMS getDoctorSchedule API를 호출하여
 * - 해당 요일 진료 시간대 (schedule_days)
 * - 해당 날짜 휴일 여부 (holidays)
 * - 이미 예약된 시간 (booked_slots)
 * 를 종합하여 가용 시간대를 반환
 *
 * @file    gangnamunni/api_schedule.php
 * @created 2026-02-10 (Phase 2-2)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/infyhms_api.php';
// HMS 독립 모드: 자체 스케줄 테이블 사용if (function_exists("is_hms_standalone") && is_hms_standalone()) {    $doctor_id = intval($_GET["doctor_id"] ?? 0);    $date = $_GET["date"] ?? date("Y-m-d");    $dow = date("w", strtotime($date));    $sch_q = mysqli_prepare($connect, "SELECT start_time, end_time, slot_minutes FROM gangnamunni_doctor_schedules WHERE doctor_id = ? AND day_of_week = ? AND is_active = 1");    mysqli_stmt_bind_param($sch_q, "ii", $doctor_id, $dow);    mysqli_stmt_execute($sch_q);    $sch_r = mysqli_stmt_get_result($sch_q);    $slots = [];    if ($s = mysqli_fetch_assoc($sch_r)) {        $t = strtotime($s["start_time"]);        $end = strtotime($s["end_time"]);        while ($t < $end) { $slots[] = date("H:i", $t); $t += $s["slot_minutes"] * 60; }    }    echo json_encode(["success" => true, "slots" => $slots]);    exit;}

$doctor_id = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
$date = isset($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');

// 입력 검증
if ($doctor_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'doctor_id required']);
    exit;
}

// 날짜 형식 검증
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format (YYYY-MM-DD)']);
    exit;
}

try {
    $api = get_infyhms_api();
    $result = $api->getDoctorSchedule($doctor_id, $date);

    if (!empty($result['success']) && isset($result['data'])) {
        $schedule_data = $result['data'];

        // 가용 시간대 계산
        $available_slots = [];
        $day_of_week = strtolower(date('l', strtotime($date)));

        // 휴일 체크
        $is_holiday = false;
        if (!empty($schedule_data['holidays'])) {
            foreach ($schedule_data['holidays'] as $holiday) {
                if (isset($holiday['date']) && $holiday['date'] === $date) {
                    $is_holiday = true;
                    break;
                }
            }
        }

        if ($is_holiday) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'date' => $date,
                    'day_of_week' => $day_of_week,
                    'is_holiday' => true,
                    'message' => '해당 날짜는 휴일입니다.',
                    'available_slots' => [],
                ],
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // 해당 요일의 진료 시간 찾기
        $day_schedule = null;
        if (!empty($schedule_data['schedule_days'])) {
            foreach ($schedule_data['schedule_days'] as $sd) {
                if (isset($sd['available_on']) && strtolower($sd['available_on']) === $day_of_week) {
                    $day_schedule = $sd;
                    break;
                }
            }
        }

        if (!$day_schedule) {
            // 해당 요일에 스케줄 없음 → 기본 시간대 제공
            $available_slots = generateDefaultSlots($date);
        } else {
            // 스케줄이 있으면 시간대 생성
            $start_time = $day_schedule['available_from'] ?? '09:00';
            $end_time = $day_schedule['available_to'] ?? '18:00';
            $available_slots = generateTimeSlots($date, $start_time, $end_time);
        }

        // 이미 예약된 시간대 제외
        $booked_times = [];
        if (!empty($schedule_data['booked_slots'])) {
            foreach ($schedule_data['booked_slots'] as $booked) {
                $booked_time = isset($booked['opd_date'])
                    ? date('H:i', strtotime($booked['opd_date']))
                    : null;
                if ($booked_time) {
                    $booked_times[] = $booked_time;
                }
            }
        }

        // 예약된 시간 필터링
        $final_slots = [];
        foreach ($available_slots as $slot) {
            $slot['is_booked'] = in_array($slot['time'], $booked_times);
            $final_slots[] = $slot;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'date' => $date,
                'day_of_week' => $day_of_week,
                'is_holiday' => false,
                'doctor_id' => $doctor_id,
                'available_slots' => $final_slots,
                'schedule' => $day_schedule,
            ],
        ], JSON_UNESCAPED_UNICODE);

    } else {
        // HMS API 실패 시 기본 시간대 반환 (폴백)
        echo json_encode([
            'success' => true,
            'data' => [
                'date' => $date,
                'is_holiday' => false,
                'available_slots' => generateDefaultSlots($date),
                'fallback' => true,
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Exception $e) {
    // 오류 시 기본 시간대 반환
    echo json_encode([
        'success' => true,
        'data' => [
            'date' => $date,
            'available_slots' => generateDefaultSlots($date),
            'fallback' => true,
            'error' => $e->getMessage(),
        ],
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 시작/종료 시간으로 30분 간격 시간대 생성
 */
function generateTimeSlots($date, $start, $end) {
    $slots = [];
    $current = strtotime($date . ' ' . $start);
    $end_ts = strtotime($date . ' ' . $end);
    $now = time();

    while ($current < $end_ts) {
        $time = date('H:i', $current);
        $label = date('H:i', $current);

        // 오전/오후 라벨
        $hour = (int) date('H', $current);
        if ($hour < 12) {
            $label = "오전 {$time}";
        } elseif ($hour == 12) {
            $label = "오후 12:" . date('i', $current);
        } else {
            $pm_hour = $hour - 12;
            $label = "오후 " . str_pad($pm_hour, 2, '0', STR_PAD_LEFT) . ":" . date('i', $current);
        }

        $is_past = ($current < $now && date('Y-m-d', $current) === date('Y-m-d'));

        $slots[] = [
            'time' => $time,
            'label' => $label,
            'is_past' => $is_past,
            'is_booked' => false,
        ];

        $current += 1800; // 30분 간격
    }

    return $slots;
}

/**
 * 기본 시간대 (09:00~18:00, 1시간 간격)
 */
function generateDefaultSlots($date) {
    return generateTimeSlots($date, '09:00', '18:00');
}
