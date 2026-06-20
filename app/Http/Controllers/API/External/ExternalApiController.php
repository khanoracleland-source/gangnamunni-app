<?php

namespace App\Http\Controllers\API\External;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * ========================================
 * External API Controller
 * ========================================
 *
 * 강남언니 등 외부 시스템과의 연동을 위한 REST API
 * X-API-Key 인증을 통해 접근 제어
 *
 * 제공 엔드포인트:
 * - GET  /api/external/hospitals           : 병원(테넌트) 목록
 * - GET  /api/external/hospitals/{id}      : 병원 상세 정보
 * - GET  /api/external/departments         : 진료과 목록
 * - GET  /api/external/departments/{id}/doctors : 진료과별 의사 목록
 * - GET  /api/external/doctors/{id}/schedule    : 의사 스케줄
 * - POST /api/external/appointments        : 예약 생성
 * - GET  /api/external/appointments/{id}   : 예약 상태 조회
 *
 * @file    app/Http/Controllers/API/External/ExternalApiController.php
 * @created 2026-02-10
 * @phase   Phase 1-1: HMS External API
 */
class ExternalApiController extends Controller
{
    /**
     * 병원(테넌트) 목록 조회
     *
     * HMS에서 병원 = 테넌트(tenant). tenants 테이블과 users 테이블을 조인하여
     * 병원명, 연락처, 주소 등의 정보를 반환
     *
     * @param Request $request
     *   - search: 병원명 검색어 (optional)
     *   - per_page: 페이지당 항목 수 (default: 50)
     *   - page: 페이지 번호 (default: 1)
     * @return JsonResponse
     */
    public function listHospitals(Request $request): JsonResponse
    {
        $search = $request->get('search', '');
        $perPage = min((int)$request->get('per_page', 50), 100);

        // tenants 테이블 + users 테이블 (병원 소유자 정보) 조인
        $query = DB::table('tenants')
            ->leftJoin('users', function ($join) {
                // 병원 소유자 = tenant_id가 같고 owner_type이 null인 최초 관리자
                $join->on('tenants.id', '=', 'users.tenant_id')
                    ->whereNull('users.owner_type')
                    ->where('users.is_admin_default', '=', 1);
            })
            ->select([
                'tenants.id',
                'tenants.hospital_name as name',
                'tenants.tenant_username as username',
                'users.phone',
                'users.city as address',
                'users.email',
                'tenants.created_at',
                'tenants.updated_at',
            ]);

        // 검색 필터
        if ($search) {
            $query->where('tenants.hospital_name', 'LIKE', "%{$search}%");
        }

        $hospitals = $query->orderBy('tenants.hospital_name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $hospitals->items(),
            'pagination' => [
                'total' => $hospitals->total(),
                'per_page' => $hospitals->perPage(),
                'current_page' => $hospitals->currentPage(),
                'last_page' => $hospitals->lastPage(),
            ],
        ]);
    }

    /**
     * 병원 상세 정보 조회
     *
     * 특정 테넌트(병원)의 상세 정보와 소속 의사 수, 진료과 수 포함
     *
     * @param string $id 테넌트 ID (UUID)
     * @return JsonResponse
     */
    public function getHospital(string $id): JsonResponse
    {
        // 병원(테넌트) 기본 정보
        $hospital = DB::table('tenants')
            ->leftJoin('users', function ($join) {
                $join->on('tenants.id', '=', 'users.tenant_id')
                    ->whereNull('users.owner_type')
                    ->where('users.is_admin_default', '=', 1);
            })
            ->where('tenants.id', $id)
            ->select([
                'tenants.id',
                'tenants.hospital_name as name',
                'tenants.tenant_username as username',
                'users.phone',
                'users.city as address',
                'users.email',
                'tenants.created_at',
                'tenants.updated_at',
            ])
            ->first();

        if (!$hospital) {
            return response()->json([
                'success' => false,
                'message' => 'Hospital not found',
            ], 404);
        }

        // 소속 의사 수
        $doctorCount = DB::table('doctors')->where('tenant_id', $id)->count();

        // 진료과 수
        $departmentCount = DB::table('doctor_departments')->where('tenant_id', $id)->count();

        // 병원 운영 시간
        $schedules = DB::table('hospital_schedules')
            ->where('tenant_id', $id)
            ->select(['day_of_week', 'start_time', 'end_time'])
            ->get();

        $result = (array) $hospital;
        $result['doctor_count'] = $doctorCount;
        $result['department_count'] = $departmentCount;
        $result['schedules'] = $schedules;

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * 진료과(doctor_departments) 목록 조회
     *
     * 특정 테넌트의 진료과 목록을 반환
     * tenant_id 필터 없으면 전체 진료과 반환
     *
     * @param Request $request
     *   - tenant_id: 테넌트 ID (optional)
     * @return JsonResponse
     */
    public function listDepartments(Request $request): JsonResponse
    {
        $tenantId = $request->get('tenant_id');

        $query = DB::table('doctor_departments')
            ->select(['id', 'title', 'description', 'tenant_id', 'created_at']);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $departments = $query->orderBy('title')->get();

        // 각 진료과의 의사 수 추가
        foreach ($departments as &$dept) {
            $dept->doctor_count = DB::table('doctors')
                ->where('doctor_department_id', $dept->id)
                ->count();
        }

        return response()->json([
            'success' => true,
            'data' => $departments,
        ]);
    }

    /**
     * 진료과별 의사 목록 조회
     *
     * 특정 진료과에 소속된 의사 목록을 반환
     * doctors 테이블 + users 테이블 조인 (이름, 연락처 등)
     *
     * @param int $departmentId 진료과 ID
     * @return JsonResponse
     */
    public function getDepartmentDoctors(int $departmentId): JsonResponse
    {
        // 진료과 존재 확인
        $department = DB::table('doctor_departments')->find($departmentId);
        if (!$department) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        }

        // 의사 목록 (users 테이블과 조인하여 이름/연락처 가져오기)
        $doctors = DB::table('doctors')
            ->join('users', 'doctors.user_id', '=', 'users.id')
            ->where('doctors.doctor_department_id', $departmentId)
            ->select([
                'doctors.id',
                'doctors.user_id',
                DB::raw("CONCAT(users.first_name, ' ', COALESCE(users.last_name, '')) as name"),
                'users.phone',
                'users.email',
                'users.qualification',
                'doctors.specialist as specialization',
                'doctors.appointment_charge',
                'doctors.tenant_id',
            ])
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'department' => $department,
                'doctors' => $doctors,
            ],
        ]);
    }

    /**
     * 의사 스케줄 조회
     *
     * 특정 의사의 근무 스케줄을 반환
     * schedules + schedule_days 테이블에서 요일별 가용 시간 조회
     * date 파라미터가 있으면 해당 날짜의 예약된 시간도 반환
     *
     * @param int $doctorId 의사 ID
     * @param Request $request
     *   - date: 조회할 날짜 (Y-m-d, optional)
     * @return JsonResponse
     */
    public function getDoctorSchedule(int $doctorId, Request $request): JsonResponse
    {
        // 의사 존재 확인
        $doctor = DB::table('doctors')
            ->join('users', 'doctors.user_id', '=', 'users.id')
            ->where('doctors.id', $doctorId)
            ->select([
                'doctors.id',
                DB::raw("CONCAT(users.first_name, ' ', COALESCE(users.last_name, '')) as name"),
                'doctors.tenant_id',
            ])
            ->first();

        if (!$doctor) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor not found',
            ], 404);
        }

        // 스케줄 (요일별 가용 시간)
        $scheduleDays = DB::table('schedule_days')
            ->join('schedules', 'schedule_days.schedule_id', '=', 'schedules.id')
            ->where('schedule_days.doctor_id', $doctorId)
            ->select([
                'schedule_days.available_on as day_of_week',
                'schedule_days.available_from',
                'schedule_days.available_to',
                'schedules.per_patient_time',
            ])
            ->get();

        // 의사 휴일
        $holidays = DB::table('doctor_holidays')
            ->where('doctor_id', $doctorId)
            ->select(['id', 'date', 'created_at'])
            ->get();

        $result = [
            'doctor' => $doctor,
            'schedule' => $scheduleDays,
            'holidays' => $holidays,
        ];

        // 특정 날짜가 지정된 경우 해당 날짜의 기존 예약 목록도 포함
        $date = $request->get('date');
        if ($date) {
            $bookedSlots = DB::table('appointments')
                ->where('doctor_id', $doctorId)
                ->whereDate('opd_date', $date)
                ->where('is_completed', 0) // 미완료 예약만
                ->select(['id', 'opd_date', 'is_completed'])
                ->get();

            $result['booked_slots'] = $bookedSlots;
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * 예약(Appointment) 생성
     *
     * 강남언니에서 상담/예약 신청 시 HMS에 예약 레코드 생성
     * 환자가 없으면 새로 생성, 있으면 기존 환자 연결
     *
     * @param Request $request
     *   - patient_name: 환자 이름 (required)
     *   - patient_phone: 환자 전화번호 (required)
     *   - patient_email: 환자 이메일 (optional)
     *   - doctor_id: 의사 ID (required)
     *   - department_id: 진료과 ID (required)
     *   - appointment_datetime: 예약 일시 Y-m-d H:i:s (required)
     *   - problem: 증상/문제 설명 (optional)
     *   - tenant_id: 테넌트 ID (required)
     *   - source: 예약 출처 (default: 'gangnamunni')
     * @return JsonResponse
     */
    /**
     * POST /api/external/appointments
     * 예약 생성 (doctor_id/department_id 미제공 시 자동 배정)
     *
     * Required: patient_name, patient_phone, appointment_datetime, tenant_id
     * Optional: doctor_id, department_id, problem, patient_email
     */
    public function createAppointment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_name'         => 'required|string|max:200',
            'patient_phone'        => 'required|string|max:50',
            'patient_email'        => 'nullable|string|max:191',
            'doctor_id'            => 'nullable|integer',
            'department_id'        => 'nullable|integer',
            'appointment_datetime' => 'required|date',
            'problem'              => 'nullable|string|max:2000',
            'tenant_id'            => 'required|string|max:191',
        ]);

        $tenantId = $validated['tenant_id'];

        try {
            // doctor_id 미제공 시 해당 테넌트의 첫 번째 의사 자동 배정
            $doctorId = $validated['doctor_id'] ?? null;
            $departmentId = $validated['department_id'] ?? null;

            if (!$doctorId) {
                $defaultDoctor = DB::table('doctors')
                    ->where('tenant_id', $tenantId)
                    ->first();
                if ($defaultDoctor) {
                    $doctorId = $defaultDoctor->id;
                    $departmentId = $departmentId ?? $defaultDoctor->doctor_department_id;
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'No doctors found for this tenant',
                    ], 422);
                }
            }

            if (!$departmentId) {
                $doctor = DB::table('doctors')->find($doctorId);
                $departmentId = $doctor ? ($doctor->doctor_department_id ?? 1) : 1;
            }

            // validated 배열에 자동 배정된 값 추가 (findOrCreatePatient 호환용)
            $validated['doctor_id'] = $doctorId;
            $validated['department_id'] = $departmentId;

            // 환자 사용자 찾기/생성
            $patientUserId = $this->findOrCreatePatient($validated);

            // 환자 레코드 확인/생성
            $patient = DB::table('patients')->where('user_id', $patientUserId)->first();
            if (!$patient) {
                $patientId = DB::table('patients')->insertGetId([
                    'user_id' => $patientUserId,
                    'tenant_id' => $tenantId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $patientId = $patient->id;
            }

            // 예약 생성
            $appointmentId = DB::table('appointments')->insertGetId([
                'patient_id' => $patientId,
                'doctor_id' => $doctorId,
                'department_id' => $departmentId,
                'opd_date' => $validated['appointment_datetime'],
                'problem' => $validated['problem'] ?? '',
                'is_completed' => 0,
                'payment_status' => 0,
                'tenant_id' => $tenantId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Appointment created successfully',
                'data' => [
                    'id' => $appointmentId,
                    'patient_id' => $patientId,
                    'doctor_id' => $doctorId,
                    'department_id' => $departmentId,
                    'opd_date' => $validated['appointment_datetime'],
                    'tenant_id' => $tenantId,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create appointment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 예약 상태 조회
     *
     * 특정 예약의 현재 상태를 반환
     *
     * @param int $id 예약 ID
     * @return JsonResponse
     */
    public function getAppointment(int $id): JsonResponse
    {
        $appointment = DB::table('appointments')
            ->leftJoin('doctors', 'appointments.doctor_id', '=', 'doctors.id')
            ->leftJoin('users as doctor_user', 'doctors.user_id', '=', 'doctor_user.id')
            ->leftJoin('patients', 'appointments.patient_id', '=', 'patients.id')
            ->leftJoin('users as patient_user', 'patients.user_id', '=', 'patient_user.id')
            ->leftJoin('doctor_departments', 'appointments.department_id', '=', 'doctor_departments.id')
            ->where('appointments.id', $id)
            ->select([
                'appointments.id',
                'appointments.opd_date',
                'appointments.problem',
                'appointments.is_completed',
                'appointments.payment_status',
                'appointments.tenant_id',
                'appointments.created_at',
                'appointments.updated_at',
                DB::raw("CONCAT(doctor_user.first_name, ' ', COALESCE(doctor_user.last_name, '')) as doctor_name"),
                DB::raw("CONCAT(patient_user.first_name, ' ', COALESCE(patient_user.last_name, '')) as patient_name"),
                'patient_user.phone as patient_phone',
                'doctor_departments.title as department_name',
            ])
            ->first();

        if (!$appointment) {
            return response()->json([
                'success' => false,
                'message' => 'Appointment not found',
            ], 404);
        }

        // 예약 상태 텍스트 변환
        $statusText = match(true) {
            (bool) $appointment->is_completed => 'completed',
            default => 'pending',
        };

        $result = (array) $appointment;
        $result['status_text'] = $statusText;

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * 환자 사용자 검색 또는 생성 (private helper)
     *
     * 전화번호로 기존 환자 검색, 없으면 새 사용자+환자 레코드 생성
     *
     * @param array $data 환자 정보 (patient_name, patient_phone, patient_email, tenant_id)
     * @return int 사용자 ID
     */
    private function findOrCreatePatient(array $data): int
    {
        // 전화번호로 기존 사용자 검색
        $existingUser = DB::table('users')
            ->where('phone', $data['patient_phone'])
            ->where('tenant_id', $data['tenant_id'])
            ->first();

        if ($existingUser) {
            return $existingUser->id;
        }

        // 이름 분리 (첫 단어 = first_name, 나머지 = last_name)
        $nameParts = explode(' ', $data['patient_name'], 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        // 새 사용자 생성
        $userId = DB::table('users')->insertGetId([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $data['patient_email'] ?? 'gangnamunni_' . time() . '_' . rand(1000, 9999) . '@temp.local',
            'phone' => $data['patient_phone'],
            'password' => bcrypt('gangnamunni_temp_' . time()),
            'gender' => 0, // 미지정
            'status' => 1,
            'hospital_name' => '',
            'tenant_id' => $data['tenant_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $userId;
    }
}