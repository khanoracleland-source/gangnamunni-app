<?php

namespace App\Services;

/**
 * Data Transformer Class
 *
 * Kivicare와 새 시스템 간 데이터 스키마 변환
 */
class DataTransformer
{
    // ============================================
    // 사용자/환자 데이터 변환
    // ============================================

    /**
     * 새 시스템 → Kivicare 형식 변환 (사용자)
     */
    public static function userToKivicare($userData)
    {
        return [
            'first_name' => self::extractFirstName($userData['name'] ?? ''),
            'last_name' => self::extractLastName($userData['name'] ?? ''),
            'user_email' => $userData['email'] ?? '',
            'mobile' => $userData['phone'] ?? '',
            'dob' => $userData['birth_date'] ?? null,
            'gender' => self::mapGender($userData['gender'] ?? null),
            'address' => $userData['address'] ?? '',
        ];
    }

    /**
     * Kivicare → 새 시스템 형식 변환 (사용자)
     */
    public static function userFromKivicare($kivicareData)
    {
        return [
            'name' => trim(($kivicareData['first_name'] ?? '') . ' ' . ($kivicareData['last_name'] ?? '')),
            'email' => $kivicareData['user_email'] ?? $kivicareData['email'] ?? '',
            'phone' => $kivicareData['mobile'] ?? '',
            'birth_date' => $kivicareData['dob'] ?? null,
            'gender' => self::mapGender($kivicareData['gender'] ?? null),
            'address' => $kivicareData['address'] ?? '',
        ];
    }

    // ============================================
    // 예약 데이터 변환
    // ============================================

    /**
     * 새 시스템 → Kivicare 형식 변환 (예약)
     */
    public static function bookingToKivicare($bookingData)
    {
        return [
            'clinic_id' => $bookingData['hospital_id'] ?? null,
            'doctor_id' => $bookingData['doctor_id'] ?? null,
            'patient_id' => $bookingData['patient_id'] ?? null,
            'appointment_start_date' => $bookingData['booking_date'] ?? null,
            'appointment_start_time' => $bookingData['booking_time'] ?? null,
            'appointment_end_time' => self::calculateEndTime(
                $bookingData['booking_time'] ?? '10:00:00',
                $bookingData['duration'] ?? 30
            ),
            'description' => $bookingData['notes'] ?? '',
            'status' => self::mapStatusToKivicare($bookingData['status'] ?? 'pending'),
            'visit_type' => $bookingData['treatment_type'] ?? '',

            // 환자 정보 (Kivicare는 예약 생성 시 환자 정보도 받음)
            'clinic_name' => $bookingData['hospital_name'] ?? '',
            'all_day' => '0',
        ];
    }

    /**
     * Kivicare → 새 시스템 형식 변환 (예약)
     */
    public static function bookingFromKivicare($kivicareData)
    {
        return [
            'id' => $kivicareData['id'] ?? null,
            'hospital_id' => $kivicareData['clinic_id'] ?? null,
            'hospital_name' => $kivicareData['clinic_name'] ?? '',
            'doctor_id' => $kivicareData['doctor_id'] ?? null,
            'patient_id' => $kivicareData['patient_id'] ?? null,
            'patient_name' => self::extractPatientName($kivicareData),
            'patient_phone' => $kivicareData['patient_phone'] ?? $kivicareData['mobile'] ?? '',
            'patient_email' => $kivicareData['patient_email'] ?? $kivicareData['user_email'] ?? '',
            'booking_date' => $kivicareData['appointment_start_date'] ?? null,
            'booking_time' => $kivicareData['appointment_start_time'] ?? null,
            'treatment_type' => $kivicareData['visit_type'] ?? '',
            'status' => self::mapStatusFromKivicare($kivicareData['status'] ?? '1'),
            'notes' => $kivicareData['description'] ?? '',
            'created_at' => $kivicareData['created_at'] ?? null,
            'updated_at' => $kivicareData['updated_at'] ?? null,
        ];
    }

    /**
     * 예약 목록 변환 (Kivicare → 새 시스템)
     */
    public static function bookingListFromKivicare($kivicareResponse)
    {
        $bookings = [];

        if (isset($kivicareResponse['data']) && is_array($kivicareResponse['data'])) {
            foreach ($kivicareResponse['data'] as $item) {
                $bookings[] = self::bookingFromKivicare($item);
            }
        }

        return [
            'bookings' => $bookings,
            'total' => $kivicareResponse['total'] ?? count($bookings),
            'page' => $kivicareResponse['current_page'] ?? 1,
            'per_page' => $kivicareResponse['per_page'] ?? 20,
        ];
    }

    // ============================================
    // 병원/클리닉 데이터 변환
    // ============================================

    /**
     * Kivicare → 새 시스템 형식 변환 (병원)
     */
    public static function hospitalFromKivicare($kivicareData)
    {
        return [
            'id' => $kivicareData['id'] ?? null,
            'name' => $kivicareData['name'] ?? '',
            'description' => $kivicareData['extra']->specialties ?? '',
            'address' => $kivicareData['address'] ?? '',
            'phone' => $kivicareData['telephone_no'] ?? '',
            'email' => $kivicareData['email'] ?? '',
            'profile_image_url' => $kivicareData['profile_image'] ?? null,
            'rating' => 0, // Kivicare에 평점 필드가 없으면 0
            'review_count' => 0,
            'latitude' => null,
            'longitude' => null,
            'categories' => [],
            'badges' => [],
            'business_hours' => self::parseBusinessHours($kivicareData),
            'created_at' => $kivicareData['created_at'] ?? null,
            'updated_at' => $kivicareData['updated_at'] ?? null,
        ];
    }

    // ============================================
    // 리뷰 데이터 변환
    // ============================================

    /**
     * 새 시스템 → Kivicare 형식 변환 (리뷰)
     */
    public static function reviewToKivicare($reviewData)
    {
        // Kivicare에 리뷰 기능이 없으므로 커스텀 테이블 사용
        return [
            'clinic_id' => $reviewData['hospital_id'] ?? null,
            'patient_name' => $reviewData['patient_name'] ?? '',
            'rating' => $reviewData['rating'] ?? 0,
            'review_text' => $reviewData['content'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    // ============================================
    // 헬퍼 메서드
    // ============================================

    /**
     * 이름에서 성 추출
     */
    private static function extractFirstName($fullName)
    {
        $parts = explode(' ', trim($fullName));
        return $parts[0] ?? '';
    }

    /**
     * 이름에서 이름 추출
     */
    private static function extractLastName($fullName)
    {
        $parts = explode(' ', trim($fullName));
        return isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '';
    }

    /**
     * Kivicare에서 환자 이름 추출
     */
    private static function extractPatientName($kivicareData)
    {
        if (isset($kivicareData['patient_name'])) {
            return $kivicareData['patient_name'];
        }

        $firstName = $kivicareData['first_name'] ?? '';
        $lastName = $kivicareData['last_name'] ?? '';

        return trim($firstName . ' ' . $lastName);
    }

    /**
     * 성별 매핑
     */
    private static function mapGender($gender)
    {
        if (!$gender) return null;

        $gender = strtolower($gender);

        // Kivicare 형식: male, female
        // 새 시스템: M, F
        if (in_array($gender, ['m', 'male', '남성', '남'])) {
            return 'male';
        } elseif (in_array($gender, ['f', 'female', '여성', '여'])) {
            return 'female';
        }

        return $gender;
    }

    /**
     * 예약 상태 매핑 (새 시스템 → Kivicare)
     */
    private static function mapStatusToKivicare($status)
    {
        $statusMap = [
            'pending' => '1',      // Booked
            'confirmed' => '1',    // Booked
            'completed' => '3',    // Check Out
            'cancelled' => '0',    // Cancel
        ];

        return $statusMap[$status] ?? '1';
    }

    /**
     * 예약 상태 매핑 (Kivicare → 새 시스템)
     */
    private static function mapStatusFromKivicare($status)
    {
        // Kivicare 상태: 0=Cancel, 1=Booked, 2=Check In, 3=Check Out
        $statusMap = [
            '0' => 'cancelled',
            '1' => 'confirmed',
            '2' => 'confirmed',
            '3' => 'completed',
        ];

        return $statusMap[$status] ?? 'pending';
    }

    /**
     * 종료 시간 계산
     */
    private static function calculateEndTime($startTime, $durationMinutes = 30)
    {
        $start = new \DateTime($startTime);
        $start->modify("+{$durationMinutes} minutes");
        return $start->format('H:i:s');
    }

    /**
     * 영업시간 파싱
     */
    private static function parseBusinessHours($kivicareData)
    {
        // Kivicare에서 영업시간 정보 추출
        if (isset($kivicareData['time_slot'])) {
            return $kivicareData['time_slot'];
        }

        return null;
    }

    /**
     * 배열을 JSON 문자열로 변환
     */
    public static function arrayToJson($array)
    {
        return $array ? json_encode($array, JSON_UNESCAPED_UNICODE) : null;
    }

    /**
     * JSON 문자열을 배열로 변환
     */
    public static function jsonToArray($json)
    {
        return $json ? json_decode($json, true) : [];
    }

    /**
     * 에러 응답 변환
     */
    public static function errorResponse($message, $code = 400)
    {
        return [
            'success' => false,
            'error' => $message,
            'code' => $code,
        ];
    }

    /**
     * 성공 응답 변환
     */
    public static function successResponse($data, $message = 'Success')
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }
}
