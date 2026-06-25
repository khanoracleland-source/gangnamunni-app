<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

// Kivicare 연동을 위한 클래스 imports
require_once __DIR__ . '/../app/Services/KivicareAPI.php';
require_once __DIR__ . '/../app/Services/DataTransformer.php';
require_once __DIR__ . '/../app/Helpers/SettingsHelper.php';

use App\Services\KivicareAPI;
use App\Services\DataTransformer;
use App\Helpers\SettingsHelper;

/*
|--------------------------------------------------------------------------
| API Routes with Kivicare Integration
|--------------------------------------------------------------------------
|
| 이 라우트 파일은 Kivicare API 연동을 포함한 조건부 라우팅을 구현합니다.
| 사용자 설정(use_kivicare)에 따라 Kivicare API 또는 새 시스템을 사용합니다.
|
*/

// Health Check
Route::get('/health', function () {
    return response()->json(['status' => 'OK', 'timestamp' => now()]);
});

// Hospital APIs (Always use new system - no Kivicare equivalent)
Route::prefix('hospitals')->group(function () {

    // Get all hospitals with pagination and filters
    Route::get('/', function (Request $request) {
        $query = DB::table('gangnamunni_hospitals');

        // Search by name
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Filter by category
        if ($request->has('category')) {
            $category = $request->input('category');
            $query->whereRaw("JSON_CONTAINS(categories, ?)", [json_encode($category)]);
        }

        // Filter by minimum rating
        if ($request->has('min_rating')) {
            $query->where('rating', '>=', $request->input('min_rating'));
        }

        // Sort
        $sortBy = $request->input('sort_by', 'rating');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->input('per_page', 20);
        $hospitals = $query->paginate($perPage);

        return response()->json([
            'data' => $hospitals->items(),
            'pagination' => [
                'total' => $hospitals->total(),
                'per_page' => $hospitals->perPage(),
                'current_page' => $hospitals->currentPage(),
                'last_page' => $hospitals->lastPage(),
            ],
        ]);
    });

    // Get hospital by ID
    Route::get('/{id}', function ($id) {
        $hospital = DB::table('gangnamunni_hospitals')->where('id', $id)->first();

        if (!$hospital) {
            return response()->json(['error' => 'Hospital not found'], 404);
        }

        // Parse JSON fields
        $hospital->categories = json_decode($hospital->categories);
        $hospital->badges = json_decode($hospital->badges);
        $hospital->treatment_tags = json_decode($hospital->treatment_tags);
        $hospital->subway_names = json_decode($hospital->subway_names);
        $hospital->business_hours = json_decode($hospital->business_hours);

        return response()->json($hospital);
    });

    // Get hospital reviews
    Route::get('/{id}/reviews', function ($id) {
        $reviews = DB::table('hospital_reviews')
            ->join('users', 'hospital_reviews.user_id', '=', 'users.id')
            ->where('hospital_reviews.hospital_id', $id)
            ->select(
                'hospital_reviews.*',
                'users.name as user_name',
                'users.email as user_email'
            )
            ->orderBy('hospital_reviews.created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'count' => $reviews->count(),
            'reviews' => $reviews
        ]);
    });

    // Search nearby hospitals by location
    Route::get('/nearby', function (Request $request) {
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $radius = $request->input('radius', 5); // km

        if (!$lat || !$lng) {
            return response()->json(['error' => 'lat and lng parameters required'], 400);
        }

        $hospitals = DB::select("
            SELECT *,
            (6371 * acos(
                cos(radians(?)) * cos(radians(latitude)) *
                cos(radians(longitude) - radians(?)) +
                sin(radians(?)) * sin(radians(latitude))
            )) AS distance
            FROM gangnamunni_hospitals
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL
            HAVING distance < ?
            ORDER BY distance
            LIMIT 50
        ", [$lat, $lng, $lat, $radius]);

        return response()->json($hospitals);
    });
});

// Categories API
Route::get('/categories', function () {
    $categories = DB::select("
        SELECT
            JSON_UNQUOTE(JSON_EXTRACT(categories, CONCAT('$[', numbers.idx, ']'))) as name,
            COUNT(*) as hospital_count
        FROM gangnamunni_hospitals
        CROSS JOIN (
            SELECT 0 as idx UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
            UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
            UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14
        ) as numbers
        WHERE JSON_EXTRACT(categories, CONCAT('$[', numbers.idx, ']')) IS NOT NULL
        GROUP BY name
        ORDER BY hospital_count DESC
    ");

    return response()->json($categories);
});

// Stats API
Route::get('/stats', function () {
    $stats = [
        'total_hospitals' => DB::table('gangnamunni_hospitals')->count(),
        'avg_rating' => DB::table('gangnamunni_hospitals')->avg('rating'),
        'total_reviews' => DB::table('gangnamunni_hospitals')->sum('review_count'),
        'top_rated' => DB::table('gangnamunni_hospitals')
            ->orderBy('rating', 'desc')
            ->orderBy('review_count', 'desc')
            ->limit(5)
            ->get(),
        'most_reviewed' => DB::table('gangnamunni_hospitals')
            ->orderBy('review_count', 'desc')
            ->limit(5)
            ->get(),
    ];

    return response()->json($stats);
});

// Settings API
Route::prefix('settings')->group(function () {

    // Get user settings
    Route::get('/', function (Request $request) {
        $sessionId = $request->input('session_id');

        if (!$sessionId) {
            return response()->json(['error' => 'session_id is required'], 400);
        }

        // Get settings from database
        $settings = DB::table('user_settings')
            ->where('session_id', $sessionId)
            ->first();

        // If no settings found, return defaults
        if (!$settings) {
            return response()->json([
                'navigation' => [
                    'pc' => ['mode' => 'same-tab'],
                    'mobile' => ['mode' => 'same-page']
                ],
                'display' => [
                    'theme' => 'light',
                    'fontSize' => 'medium',
                    'loadingType' => 'spinner'
                ],
                'filters' => [
                    'categoryType' => 'checkbox'
                ],
                'features' => [
                    'useKivicare' => false
                ]
            ]);
        }

        // Convert database format to frontend format
        return response()->json([
            'navigation' => [
                'pc' => ['mode' => $settings->navigation_pc_mode],
                'mobile' => ['mode' => $settings->navigation_mobile_mode]
            ],
            'display' => [
                'theme' => $settings->theme,
                'fontSize' => $settings->font_size,
                'loadingType' => $settings->loading_type
            ],
            'filters' => [
                'categoryType' => $settings->category_type ?? 'checkbox'
            ],
            'features' => [
                'useKivicare' => (bool)($settings->use_kivicare ?? false)
            ]
        ]);
    });

    // Save user settings
    Route::post('/', function (Request $request) {
        $sessionId = $request->input('session_id');
        $settings = $request->input('settings');

        if (!$sessionId) {
            return response()->json(['error' => 'session_id is required'], 400);
        }

        if (!$settings) {
            return response()->json(['error' => 'settings data is required'], 400);
        }

        // Extract settings from frontend format
        $navigationPcMode = $settings['navigation']['pc']['mode'] ?? 'same-tab';
        $navigationMobileMode = $settings['navigation']['mobile']['mode'] ?? 'same-page';
        $theme = $settings['display']['theme'] ?? 'light';
        $fontSize = $settings['display']['fontSize'] ?? 'medium';
        $loadingType = $settings['display']['loadingType'] ?? 'spinner';
        $categoryType = $settings['filters']['categoryType'] ?? 'checkbox';
        $useKivicare = $settings['features']['useKivicare'] ?? false;

        // Check if settings already exist
        $existing = DB::table('user_settings')
            ->where('session_id', $sessionId)
            ->first();

        if ($existing) {
            // Update existing settings
            DB::table('user_settings')
                ->where('session_id', $sessionId)
                ->update([
                    'navigation_pc_mode' => $navigationPcMode,
                    'navigation_mobile_mode' => $navigationMobileMode,
                    'theme' => $theme,
                    'font_size' => $fontSize,
                    'loading_type' => $loadingType,
                    'category_type' => $categoryType,
                    'use_kivicare' => $useKivicare ? 1 : 0,
                    'updated_at' => now()
                ]);
        } else {
            // Insert new settings
            DB::table('user_settings')->insert([
                'session_id' => $sessionId,
                'navigation_pc_mode' => $navigationPcMode,
                'navigation_mobile_mode' => $navigationMobileMode,
                'theme' => $theme,
                'font_size' => $fontSize,
                'loading_type' => $loadingType,
                'category_type' => $categoryType,
                'use_kivicare' => $useKivicare ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings saved successfully'
        ]);
    });
});

// ============================================
// Authentication APIs (WITH KIVICARE SUPPORT)
// ============================================
Route::prefix('auth')->group(function () {

    // Register
    Route::post('/register', function (Request $request) {
        $userId = $request->input('user_id');
        $useKivicare = SettingsHelper::useKivicare($userId);

        if ($useKivicare) {
            // Kivicare 회원가입
            try {
                $userData = DataTransformer::userToKivicare($request->all());
                $userData['password'] = $request->input('password');
                $userData['password_confirmation'] = $request->input('password');

                $result = KivicareAPI::register($userData);

                return response()->json(DataTransformer::successResponse(
                    DataTransformer::userFromKivicare($result['user'] ?? $result),
                    '회원가입이 완료되었습니다.'
                ));

            } catch (\Exception $e) {
                return response()->json(DataTransformer::errorResponse(
                    'Kivicare 회원가입 실패: ' . $e->getMessage()
                ), 500);
            }
        }

        // 새 시스템 회원가입
        $name = $request->input('name');
        $email = $request->input('email');
        $password = $request->input('password');
        $phone = $request->input('phone');
        $birthDate = $request->input('birth_date');
        $gender = $request->input('gender');

        // Validation
        if (!$name || !$email || !$password) {
            return response()->json([
                'success' => false,
                'message' => '이름, 이메일, 비밀번호는 필수입니다.'
            ], 400);
        }

        // Check if email already exists
        $existing = DB::table('users')->where('email', $email)->first();
        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => '이미 사용 중인 이메일입니다.'
            ], 400);
        }

        // Create user
        $userId = DB::table('users')->insertGetId([
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'phone' => $phone,
            'birth_date' => $birthDate,
            'gender' => $gender,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $user = DB::table('users')->where('id', $userId)->first();

        return response()->json([
            'success' => true,
            'message' => '회원가입이 완료되었습니다.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'birth_date' => $user->birth_date,
                'gender' => $user->gender
            ]
        ]);
    });

    // Login
    Route::post('/login', function (Request $request) {
        $email = $request->input('email');
        $password = $request->input('password');

        if (!$email || !$password) {
            return response()->json([
                'success' => false,
                'message' => '이메일과 비밀번호를 입력해주세요.'
            ], 400);
        }

        // 먼저 DB에서 사용자 확인
        $user = DB::table('users')->where('email', $email)->first();

        if ($user) {
            // 사용자의 Kivicare 사용 설정 확인
            $useKivicare = SettingsHelper::useKivicare($user->id);

            if ($useKivicare) {
                // Kivicare 로그인
                try {
                    $result = KivicareAPI::login($email, $password);

                    // Kivicare 토큰 저장
                    if (isset($result['token'])) {
                        KivicareAPI::setToken($result['token']);
                    }

                    return response()->json([
                        'success' => true,
                        'message' => '로그인되었습니다. (Kivicare)',
                        'token' => $result['token'] ?? bin2hex(random_bytes(32)),
                        'user' => DataTransformer::userFromKivicare($result['user'] ?? $result),
                        'using_kivicare' => true
                    ]);

                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Kivicare 로그인 실패: ' . $e->getMessage()
                    ], 401);
                }
            }
        }

        // 새 시스템 로그인
        if (!$user || !password_verify($password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => '이메일 또는 비밀번호가 올바르지 않습니다.'
            ], 401);
        }

        // Generate session token
        $token = bin2hex(random_bytes(32));

        return response()->json([
            'success' => true,
            'message' => '로그인되었습니다.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'birth_date' => $user->birth_date,
                'gender' => $user->gender,
                'profile_image' => $user->profile_image
            ],
            'using_kivicare' => false
        ]);
    });

    // Get current user
    Route::get('/me', function (Request $request) {
        $userId = $request->input('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => '인증이 필요합니다.'
            ], 401);
        }

        $useKivicare = SettingsHelper::useKivicare($userId);

        if ($useKivicare) {
            // Kivicare에서 사용자 정보 조회
            try {
                $result = KivicareAPI::getCurrentUser();

                return response()->json([
                    'success' => true,
                    'user' => DataTransformer::userFromKivicare($result['user'] ?? $result),
                    'using_kivicare' => true
                ]);

            } catch (\Exception $e) {
                // Fallback to new system
            }
        }

        // 새 시스템에서 사용자 정보 조회
        $user = DB::table('users')->where('id', $userId)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '사용자를 찾을 수 없습니다.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'birth_date' => $user->birth_date,
                'gender' => $user->gender,
                'profile_image' => $user->profile_image
            ],
            'using_kivicare' => false
        ]);
    });

    // Logout
    Route::post('/logout', function (Request $request) {
        $userId = $request->input('user_id');
        $useKivicare = SettingsHelper::useKivicare($userId);

        if ($useKivicare) {
            try {
                KivicareAPI::logout();
            } catch (\Exception $e) {
                // Silent fail
            }
        }

        return response()->json([
            'success' => true,
            'message' => '로그아웃되었습니다.'
        ]);
    });
});

// ============================================
// Booking APIs (WITH KIVICARE SUPPORT)
// ============================================
Route::prefix('bookings')->group(function () {

    // Get user bookings
    Route::get('/', function (Request $request) {
        $userId = $request->input('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => '인증이 필요합니다.'
            ], 401);
        }

        $useKivicare = SettingsHelper::useKivicare($userId);

        if ($useKivicare) {
            // Kivicare에서 예약 목록 조회
            try {
                $params = [
                    'patient_id' => $userId,
                    'per_page' => $request->input('per_page', 20),
                    'page' => $request->input('page', 1)
                ];

                $result = KivicareAPI::getAppointments($params);
                $bookings = DataTransformer::bookingListFromKivicare($result);

                return response()->json([
                    'success' => true,
                    'bookings' => $bookings['bookings'],
                    'total' => $bookings['total'],
                    'using_kivicare' => true
                ]);

            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kivicare 예약 조회 실패: ' . $e->getMessage()
                ], 500);
            }
        }

        // 새 시스템에서 예약 목록 조회
        $bookings = DB::table('bookings')
            ->leftJoin('gangnamunni_hospitals', 'bookings.hospital_id', '=', 'gangnamunni_hospitals.id')
            ->where('bookings.user_id', $userId)
            ->select(
                'bookings.*',
                'gangnamunni_hospitals.name as hospital_name',
                'gangnamunni_hospitals.address as hospital_address',
                'gangnamunni_hospitals.phone as hospital_phone'
            )
            ->orderBy('bookings.booking_date', 'desc')
            ->orderBy('bookings.booking_time', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'bookings' => $bookings,
            'using_kivicare' => false
        ]);
    });

    // Create booking
    Route::post('/', function (Request $request) {
        $userId = $request->input('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => '인증이 필요합니다.'
            ], 401);
        }

        $useKivicare = SettingsHelper::useKivicare($userId);

        if ($useKivicare) {
            // Kivicare에 예약 생성
            try {
                $bookingData = DataTransformer::bookingToKivicare($request->all());
                $result = KivicareAPI::createBooking($bookingData);

                return response()->json([
                    'success' => true,
                    'message' => '예약이 접수되었습니다. (Kivicare)',
                    'booking' => DataTransformer::bookingFromKivicare($result['data'] ?? $result),
                    'using_kivicare' => true
                ]);

            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kivicare 예약 생성 실패: ' . $e->getMessage()
                ], 500);
            }
        }

        // 새 시스템에 예약 생성
        $hospitalId = $request->input('hospital_id');
        $bookingDate = $request->input('booking_date');
        $bookingTime = $request->input('booking_time');
        $category = $request->input('category');
        $serviceName = $request->input('service_name');
        $patientName = $request->input('patient_name');
        $patientPhone = $request->input('patient_phone');
        $patientEmail = $request->input('patient_email');
        $notes = $request->input('notes');

        if (!$hospitalId || !$bookingDate || !$bookingTime || !$patientName || !$patientPhone) {
            return response()->json([
                'success' => false,
                'message' => '필수 정보를 모두 입력해주세요.'
            ], 400);
        }

        $bookingId = DB::table('bookings')->insertGetId([
            'user_id' => $userId,
            'hospital_id' => $hospitalId,
            'booking_date' => $bookingDate,
            'booking_time' => $bookingTime,
            'category' => $category,
            'service_name' => $serviceName,
            'patient_name' => $patientName,
            'patient_phone' => $patientPhone,
            'patient_email' => $patientEmail,
            'notes' => $notes,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $booking = DB::table('bookings')
            ->leftJoin('gangnamunni_hospitals', 'bookings.hospital_id', '=', 'gangnamunni_hospitals.id')
            ->where('bookings.id', $bookingId)
            ->select(
                'bookings.*',
                'gangnamunni_hospitals.name as hospital_name'
            )
            ->first();

        return response()->json([
            'success' => true,
            'message' => '예약이 접수되었습니다.',
            'booking' => $booking,
            'using_kivicare' => false
        ]);
    });

    // Cancel booking
    Route::put('/{id}/cancel', function (Request $request, $id) {
        $userId = $request->input('user_id');
        $useKivicare = SettingsHelper::useKivicare($userId);

        if ($useKivicare) {
            // Kivicare에서 예약 취소
            try {
                $result = KivicareAPI::cancelAppointment($id);

                return response()->json([
                    'success' => true,
                    'message' => '예약이 취소되었습니다. (Kivicare)',
                    'using_kivicare' => true
                ]);

            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kivicare 예약 취소 실패: ' . $e->getMessage()
                ], 500);
            }
        }

        // 새 시스템에서 예약 취소
        $booking = DB::table('bookings')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => '예약을 찾을 수 없습니다.'
            ], 404);
        }

        if ($booking->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => '이미 취소된 예약입니다.'
            ], 400);
        }

        DB::table('bookings')
            ->where('id', $id)
            ->update([
                'status' => 'cancelled',
                'updated_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => '예약이 취소되었습니다.',
            'using_kivicare' => false
        ]);
    });
});

// Favorites APIs (No Kivicare equivalent - always use new system)
Route::prefix('favorites')->group(function () {

    // Get user favorites
    Route::get('/', function (Request $request) {
        $userId = $request->input('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => '인증이 필요합니다.'
            ], 401);
        }

        $favorites = DB::table('favorites')
            ->join('gangnamunni_hospitals', 'favorites.hospital_id', '=', 'gangnamunni_hospitals.id')
            ->where('favorites.user_id', $userId)
            ->select('gangnamunni_hospitals.*', 'favorites.created_at as favorited_at')
            ->orderBy('favorites.created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'favorites' => $favorites
        ]);
    });

    // Add favorite
    Route::post('/', function (Request $request) {
        $userId = $request->input('user_id');
        $hospitalId = $request->input('hospital_id');

        if (!$userId || !$hospitalId) {
            return response()->json([
                'success' => false,
                'message' => '필수 정보가 누락되었습니다.'
            ], 400);
        }

        // Check if already favorited
        $existing = DB::table('favorites')
            ->where('user_id', $userId)
            ->where('hospital_id', $hospitalId)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => '이미 찜한 병원입니다.'
            ], 400);
        }

        DB::table('favorites')->insert([
            'user_id' => $userId,
            'hospital_id' => $hospitalId,
            'created_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => '찜 목록에 추가되었습니다.'
        ]);
    });

    // Remove favorite
    Route::delete('/{hospitalId}', function (Request $request, $hospitalId) {
        $userId = $request->input('user_id');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => '인증이 필요합니다.'
            ], 401);
        }

        $deleted = DB::table('favorites')
            ->where('user_id', $userId)
            ->where('hospital_id', $hospitalId)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => '찜 목록에 없는 병원입니다.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => '찜 목록에서 제거되었습니다.'
        ]);
    });

    // Check if favorited
    Route::get('/check/{hospitalId}', function (Request $request, $hospitalId) {
        $userId = $request->input('user_id');

        if (!$userId) {
            return response()->json([
                'success' => true,
                'is_favorited' => false
            ]);
        }

        $exists = DB::table('favorites')
            ->where('user_id', $userId)
            ->where('hospital_id', $hospitalId)
            ->exists();

        return response()->json([
            'success' => true,
            'is_favorited' => $exists
        ]);
    });
});

// Reviews APIs (No Kivicare equivalent - always use new system)
Route::prefix('reviews')->group(function () {

    // Create review
    Route::post('/', function (Request $request) {
        $userId = $request->input('user_id');
        $hospitalId = $request->input('hospital_id');
        $rating = $request->input('rating');
        $comment = $request->input('comment');
        $imageUrl = $request->input('image_url');

        if (!$userId || !$hospitalId || !$rating) {
            return response()->json([
                'success' => false,
                'message' => '필수 정보를 모두 입력해주세요.'
            ], 400);
        }

        if ($rating < 1 || $rating > 5) {
            return response()->json([
                'success' => false,
                'message' => '평점은 1~5 사이여야 합니다.'
            ], 400);
        }

        // Ensure image_url column exists
        if (!Schema::hasColumn('hospital_reviews', 'image_url')) {
            Schema::table('hospital_reviews', function ($table) {
                $table->string('image_url', 500)->nullable()->after('comment');
            });
        }

        $data = [
            'user_id' => $userId,
            'hospital_id' => $hospitalId,
            'rating' => $rating,
            'comment' => $comment,
            'created_at' => now(),
            'updated_at' => now()
        ];

        if ($imageUrl) {
            $data['image_url'] = $imageUrl;
        }

        $reviewId = DB::table('hospital_reviews')->insertGetId($data);

        $review = DB::table('hospital_reviews')
            ->join('users', 'hospital_reviews.user_id', '=', 'users.id')
            ->where('hospital_reviews.id', $reviewId)
            ->select(
                'hospital_reviews.*',
                'users.name as user_name'
            )
            ->first();

        return response()->json([
            'success' => true,
            'message' => '리뷰가 등록되었습니다.',
            'review' => $review
        ]);
    });

    // Update review
    Route::put('/{id}', function (Request $request, $id) {
        $userId = $request->input('user_id');
        $rating = $request->input('rating');
        $comment = $request->input('comment');

        $review = DB::table('hospital_reviews')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$review) {
            return response()->json([
                'success' => false,
                'message' => '리뷰를 찾을 수 없습니다.'
            ], 404);
        }

        DB::table('hospital_reviews')
            ->where('id', $id)
            ->update([
                'rating' => $rating ?? $review->rating,
                'comment' => $comment ?? $review->comment,
                'updated_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => '리뷰가 수정되었습니다.'
        ]);
    });

    // Delete review
    Route::delete('/{id}', function (Request $request, $id) {
        $userId = $request->input('user_id');

        $deleted = DB::table('hospital_reviews')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => '리뷰를 찾을 수 없습니다.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => '리뷰가 삭제되었습니다.'
        ]);
    });
});

// Upload image (for reviews, posts, etc.)
Route::post('/upload/image', function (Request $request) {
    try {
        if (!$request->hasFile('image')) {
            return response()->json([
                'success' => false,
                'message' => '이미지 파일이 필요합니다.'
            ], 400);
        }

        $file = $request->file('image');

        // Validate file
        if (!$file->isValid()) {
            return response()->json([
                'success' => false,
                'message' => '유효하지 않은 파일입니다.'
            ], 400);
        }

        // Check file size (max 5MB)
        if ($file->getSize() > 5 * 1024 * 1024) {
            return response()->json([
                'success' => false,
                'message' => '파일 크기는 5MB 이하여야 합니다.'
            ], 400);
        }

        // Check mime type
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return response()->json([
                'success' => false,
                'message' => '지원하지 않는 이미지 형식입니다.'
            ], 400);
        }

        // Create uploads directory if it doesn't exist
        $uploadPath = public_path('uploads/images');
        if (!file_exists($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        // Generate unique filename
        $extension = $file->getClientOriginalExtension();
        $filename = uniqid('img_') . '_' . time() . '.' . $extension;

        // Move file
        $file->move($uploadPath, $filename);

        // Return URL
        $url = url('uploads/images/' . $filename);

        return response()->json([
            'success' => true,
            'message' => '이미지가 업로드되었습니다.',
            'url' => $url,
            'filename' => $filename
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to upload image: ' . $e->getMessage()
        ], 500);
    }
});

// Community APIs
Route::prefix('community')->group(function () {
    // Posts
    Route::get('/posts', [App\Http\Controllers\CommunityController::class, 'index']);
    Route::get('/posts/{id}', [App\Http\Controllers\CommunityController::class, 'show']);
    Route::post('/posts', [App\Http\Controllers\CommunityController::class, 'store']);
    Route::put('/posts/{id}', [App\Http\Controllers\CommunityController::class, 'update']);
    Route::delete('/posts/{id}', [App\Http\Controllers\CommunityController::class, 'destroy']);

    // Comments
    Route::get('/posts/{postId}/comments', [App\Http\Controllers\CommunityController::class, 'getComments']);
    Route::post('/posts/{postId}/comments', [App\Http\Controllers\CommunityController::class, 'storeComment']);
    Route::delete('/posts/{postId}/comments/{commentId}', [App\Http\Controllers\CommunityController::class, 'destroyComment']);
});

// Payment APIs
Route::prefix('payments')->group(function () {
    Route::get('/', [App\Http\Controllers\PaymentController::class, 'index']);
    Route::get('/{id}', [App\Http\Controllers\PaymentController::class, 'show']);
    Route::post('/prepare', [App\Http\Controllers\PaymentController::class, 'prepare']);
    Route::post('/callback', [App\Http\Controllers\PaymentController::class, 'callback']);
    Route::post('/{id}/cancel', [App\Http\Controllers\PaymentController::class, 'cancel']);
    Route::post('/{id}/refund', [App\Http\Controllers\PaymentController::class, 'refund']);
});

// Notification APIs
Route::prefix('notifications')->group(function () {
    // Statistics (must be before /{id} route)
    Route::get('/stats', [App\Http\Controllers\NotificationController::class, 'stats']);

    // Notification CRUD
    Route::get('/', [App\Http\Controllers\NotificationController::class, 'index']);
    Route::get('/{id}', [App\Http\Controllers\NotificationController::class, 'show']);
    Route::post('/send', [App\Http\Controllers\NotificationController::class, 'send']);
    Route::post('/send-template', [App\Http\Controllers\NotificationController::class, 'sendWithTemplate']);
    Route::delete('/{id}', [App\Http\Controllers\NotificationController::class, 'destroy']);

    // Mark as read
    Route::post('/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead']);
    Route::post('/read-all', [App\Http\Controllers\NotificationController::class, 'markAllAsRead']);

    // FCM token management
    Route::post('/tokens/register', [App\Http\Controllers\NotificationController::class, 'registerToken']);
    Route::post('/tokens/unregister', [App\Http\Controllers\NotificationController::class, 'unregisterToken']);
});

