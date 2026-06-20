<?php

namespace App\Services;

/**
 * Kivicare API Wrapper Class
 *
 * Kivicare Laravel API와 통신하기 위한 래퍼 클래스
 * Laravel Sanctum 인증을 사용하여 모든 API 요청 처리
 */
class KivicareAPI
{
    private static $baseUrl = 'http://localhost:8000/api';
    private static $token = null;

    /**
     * API Base URL 설정
     */
    public static function setBaseUrl($url)
    {
        self::$baseUrl = rtrim($url, '/');
    }

    /**
     * 인증 토큰 설정
     */
    public static function setToken($token)
    {
        self::$token = $token;
    }

    /**
     * HTTP 요청 실행
     */
    private static function request($method, $endpoint, $data = null)
    {
        $url = self::$baseUrl . '/' . ltrim($endpoint, '/');

        $ch = curl_init();

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if (self::$token) {
            $headers[] = 'Authorization: Bearer ' . self::$token;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        if ($data !== null && in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("Kivicare API Error: " . $error);
        }

        $result = json_decode($response, true);

        if ($httpCode >= 400) {
            throw new \Exception("Kivicare API Error (HTTP $httpCode): " . ($result['message'] ?? 'Unknown error'));
        }

        return $result;
    }

    // ============================================
    // 인증 관련 메서드
    // ============================================

    /**
     * 로그인
     */
    public static function login($email, $password)
    {
        $response = self::request('POST', '/login', [
            'email' => $email,
            'password' => $password,
        ]);

        if (isset($response['token'])) {
            self::setToken($response['token']);
        }

        return $response;
    }

    /**
     * 회원가입
     */
    public static function register($userData)
    {
        return self::request('POST', '/register', $userData);
    }

    /**
     * 로그아웃
     */
    public static function logout()
    {
        return self::request('POST', '/logout');
    }

    /**
     * 현재 사용자 정보 조회
     */
    public static function getCurrentUser()
    {
        return self::request('GET', '/user/profile');
    }

    // ============================================
    // 환자 관련 메서드
    // ============================================

    /**
     * 환자 목록 조회
     */
    public static function getPatients($params = [])
    {
        $query = http_build_query($params);
        return self::request('GET', '/patients' . ($query ? '?' . $query : ''));
    }

    /**
     * 환자 상세 조회
     */
    public static function getPatient($id)
    {
        return self::request('GET', "/patients/{$id}");
    }

    /**
     * 환자 생성
     */
    public static function createPatient($patientData)
    {
        return self::request('POST', '/patients', $patientData);
    }

    /**
     * 환자 수정
     */
    public static function updatePatient($id, $patientData)
    {
        return self::request('PUT', "/patients/{$id}", $patientData);
    }

    // ============================================
    // 예약 관련 메서드
    // ============================================

    /**
     * 예약 목록 조회
     */
    public static function getAppointments($params = [])
    {
        $query = http_build_query($params);
        return self::request('GET', '/appointment-list' . ($query ? '?' . $query : ''));
    }

    /**
     * 예약 상세 조회
     */
    public static function getAppointment($id)
    {
        return self::request('GET', '/appointment-detail', ['id' => $id]);
    }

    /**
     * 예약 생성
     */
    public static function createBooking($bookingData)
    {
        return self::request('POST', '/save-booking', $bookingData);
    }

    /**
     * 예약 수정
     */
    public static function updateBooking($id, $bookingData)
    {
        return self::request('POST', "/update-booking/{$id}", $bookingData);
    }

    /**
     * 예약 상태 변경
     */
    public static function updateAppointmentStatus($id, $status)
    {
        return self::request('POST', "/update-status/{$id}", [
            'status' => $status,
        ]);
    }

    /**
     * 예약 취소
     */
    public static function cancelAppointment($id)
    {
        return self::request('POST', "/cancel-appointment/{$id}");
    }

    /**
     * 예약 재스케줄
     */
    public static function rescheduleBooking($data)
    {
        return self::request('POST', '/reschedule-booking', $data);
    }

    /**
     * 결제 저장
     */
    public static function savePayment($paymentData)
    {
        return self::request('POST', '/save-payment', $paymentData);
    }

    // ============================================
    // 클리닉/병원 관련 메서드
    // ============================================

    /**
     * 클리닉 목록 조회
     */
    public static function getClinics($params = [])
    {
        $query = http_build_query($params);
        return self::request('GET', '/clinics' . ($query ? '?' . $query : ''));
    }

    /**
     * 클리닉 상세 조회
     */
    public static function getClinic($id)
    {
        return self::request('GET', "/clinics/{$id}");
    }

    // ============================================
    // 의사 관련 메서드
    // ============================================

    /**
     * 의사 목록 조회
     */
    public static function getDoctors($params = [])
    {
        $query = http_build_query($params);
        return self::request('GET', '/doctors' . ($query ? '?' . $query : ''));
    }

    /**
     * 의사 상세 조회
     */
    public static function getDoctor($id)
    {
        return self::request('GET', "/doctors/{$id}");
    }

    // ============================================
    // 진료 기록 관련 메서드
    // ============================================

    /**
     * 진료 기록 목록 조회
     */
    public static function getEncounters($params = [])
    {
        $query = http_build_query($params);
        return self::request('GET', '/encounter-list' . ($query ? '?' . $query : ''));
    }

    /**
     * 진료 기록 생성
     */
    public static function createEncounter($encounterData)
    {
        return self::request('POST', '/save-encounter', $encounterData);
    }

    /**
     * 진료 기록 수정
     */
    public static function updateEncounter($id, $encounterData)
    {
        return self::request('POST', "/update-encounter/{$id}", $encounterData);
    }

    /**
     * 진료 기록 삭제
     */
    public static function deleteEncounter($id)
    {
        return self::request('POST', "/delete-encounter/{$id}");
    }

    // ============================================
    // 리뷰 관련 메서드 (Kivicare에 없을 경우 대비)
    // ============================================

    /**
     * 리뷰 조회 (폴백 지원)
     */
    public static function getReviews($params = [])
    {
        try {
            $query = http_build_query($params);
            return self::request('GET', '/reviews' . ($query ? '?' . $query : ''));
        } catch (\Exception $e) {
            // Kivicare에 리뷰 API가 없는 경우 빈 배열 반환
            return ['data' => [], 'total' => 0];
        }
    }

    /**
     * 리뷰 생성 (폴백 지원)
     */
    public static function createReview($reviewData)
    {
        try {
            return self::request('POST', '/reviews', $reviewData);
        } catch (\Exception $e) {
            throw new \Exception("Kivicare does not support reviews API");
        }
    }

    // ============================================
    // 통계 관련 메서드
    // ============================================

    /**
     * 수익 차트 데이터 조회
     */
    public static function getRevenueChartData($params = [])
    {
        $query = http_build_query($params);
        return self::request('GET', '/get-revenue-chart-data' . ($query ? '?' . $query : ''));
    }

    /**
     * 예약 통계 조회
     */
    public static function getAppointmentStats($params = [])
    {
        // Kivicare의 통계 API 엔드포인트 사용
        return self::request('GET', '/stats', $params);
    }
}
