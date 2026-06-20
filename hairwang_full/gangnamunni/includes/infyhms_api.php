<?php
/**
 * ========================================
 * InfyHMS External API Client
 * ========================================
 *
 * 강남언니 PHP에서 InfyHMS Laravel API 호출용 클라이언트
 *
 * @file    gangnamunni/includes/infyhms_api.php
 * @created 2026-02-06
 */

class InfyHmsApi {
    private $baseUrl;
    private $apiKey;
    private $timeout;

    /**
     * Constructor
     *
     * @param string|null $baseUrl API base URL (default: from config)
     * @param string|null $apiKey API key for authentication
     */
    public function __construct($baseUrl = null, $apiKey = null) {
        // 같은 서버: localhost로 직접 호출 (빠름), 외부: https://hms.seoulbeauty.app/api/external
        $this->baseUrl = $baseUrl ?? 'http://127.0.0.1:8000/api/external';
        $this->apiKey = $apiKey ?? 'seoulbeauty-api-key-2026';
        $this->timeout = 30;
    }

    /**
     * Get list of hospitals
     *
     * @param array $filters Optional filters (search, per_page)
     * @return array Response data
     */
    public function getHospitals($filters = []) {
        $query = http_build_query($filters);
        return $this->request('GET', '/hospitals' . ($query ? '?' . $query : ''));
    }

    /**
     * Get hospital details
     *
     * @param string $hospitalId Hospital ID
     * @return array Response data
     */
    public function getHospital($hospitalId) {
        return $this->request('GET', '/hospitals/' . $hospitalId);
    }

    /**
     * Get departments list
     *
     * @param string|null $tenantId Optional tenant ID filter
     * @return array Response data
     */
    public function getDepartments($tenantId = null) {
        $query = $tenantId ? '?tenant_id=' . urlencode($tenantId) : '';
        return $this->request('GET', '/departments' . $query);
    }

    /**
     * Get doctors by department
     *
     * @param int $departmentId Department ID
     * @return array Response data
     */
    public function getDoctorsByDepartment($departmentId) {
        return $this->request('GET', '/departments/' . $departmentId . '/doctors');
    }

    /**
     * Get doctor's available schedule
     *
     * @param int $doctorId Doctor ID
     * @param string|null $date Date in Y-m-d format
     * @return array Response data
     */
    public function getDoctorSchedule($doctorId, $date = null) {
        $query = $date ? '?date=' . urlencode($date) : '';
        return $this->request('GET', '/doctors/' . $doctorId . '/schedule' . $query);
    }

    /**
     * Create new appointment
     *
     * @param array $data Appointment data
     *   - patient_name: string (required)
     *   - patient_phone: string (required)
     *   - patient_email: string (optional)
     *   - doctor_id: int (required)
     *   - department_id: int (required)
     *   - appointment_datetime: string Y-m-d H:i:s (required)
     *   - problem: string (optional)
     *   - tenant_id: string (optional)
     * @return array Response data
     */
    public function createAppointment($data) {
        return $this->request('POST', '/appointments', $data);
    }

    /**
     * Get appointment status
     *
     * @param int $appointmentId Appointment ID
     * @return array Response data
     */
    public function getAppointmentStatus($appointmentId) {
        return $this->request('GET', '/appointments/' . $appointmentId);
    }

    /**
     * Execute API request
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint
     * @param array $data Request data for POST/PUT
     * @return array Response data
     */
    private function request($method, $endpoint, $data = []) {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();

        $headers = [
            'X-API-Key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For development

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'message' => 'cURL error: ' . $error,
                'http_code' => 0
            ];
        }

        $result = json_decode($response, true);

        if ($result === null) {
            return [
                'success' => false,
                'message' => 'Invalid JSON response',
                'http_code' => $httpCode,
                'raw_response' => $response
            ];
        }

        $result['http_code'] = $httpCode;
        return $result;
    }


    /**
     * Get reviews for a hospital
     *
     * @param string $hospitalId HMS Hospital/Tenant ID
     * @param array $filters Optional filters (status, page, per_page, search)
     * @return array Response data
     */
    public function getReviews($hospitalId, $filters = []) {
        $query = http_build_query(array_merge(['hospital_id' => $hospitalId], $filters));
        return $this->request('GET', '/reviews' . ($query ? '?' . $query : ''));
    }

    /**
     * Get single review detail
     *
     * @param int $reviewId Review ID
     * @return array Response data
     */
    public function getReview($reviewId) {
        return $this->request('GET', '/reviews/' . $reviewId);
    }

    /**
     * Update a review (admin)
     *
     * @param int $reviewId Review ID
     * @param array $data Updated fields
     * @return array Response data
     */
    public function updateReview($reviewId, $data) {
        return $this->request('PUT', '/reviews/' . $reviewId, $data);
    }

    /**
     * Approve or reject a review
     *
     * @param int $reviewId Review ID
     * @param string $action 'approve' or 'reject'
     * @return array Response data
     */
    public function toggleReviewStatus($reviewId, $action) {
        return $this->request('POST', '/reviews/' . $reviewId . '/toggle-status', ['action' => $action]);
    }

    /**
     * Get review statistics for a hospital
     *
     * @param string $hospitalId HMS Hospital/Tenant ID
     * @return array Stats (total, active, inactive, reported, avg_rating)
     */
    public function getReviewStats($hospitalId) {
        return $this->request('GET', '/reviews/stats?hospital_id=' . urlencode($hospitalId));
    }

    /**
     * Set API timeout
     *
     * @param int $seconds Timeout in seconds
     */
    public function setTimeout($seconds) {
        $this->timeout = $seconds;
    }
}

// Helper function for quick API access
function get_infyhms_api() {
    static $api = null;
    if ($api === null) {
        $api = new InfyHmsApi();
    }
    return $api;
}

/**
 * HMS 독립 모드 확인
 * gangnamunni_settings.hms_standalone_mode = '1' 이면 HMS API 호출하지 않음
 */
function is_hms_standalone() {
    static $standalone = null;
    if ($standalone === null) {
        global $connect;
        if ($connect) {
            $r = mysqli_query($connect, "SELECT setting_value FROM gangnamunni_settings WHERE setting_key='hms_standalone_mode' LIMIT 1");
            $standalone = ($r && ($row = mysqli_fetch_assoc($r))) ? ($row['setting_value'] === '1') : false;
        } else {
            $standalone = false;
        }
    }
    return $standalone;
}
