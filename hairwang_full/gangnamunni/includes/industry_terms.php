<?php
/**
 * 업종 추상화 레이어 (Industry Abstraction Layer)
 *
 * 멀티업종 SaaS 전환의 핵심 모듈
 * - 업종별 용어 번역: term('business') → "병원"(의료) / "매장"(미용실) / "스파"(스파)
 * - 업종별 설정 로드: 기능 on/off, 카테고리, 테마
 * - 기존 코드 하위 호환: 프리셋 없으면 medical_beauty 기본값
 *
 * 사용법:
 *   require_once __DIR__ . '/industry_terms.php';
 *   echo term('business');        // "병원" (medical_beauty 프리셋)
 *   echo term('staff');           // "의사" (medical_beauty 프리셋)
 *   echo site_name();             // getTheme('site_name') 값
 *   echo industry_config('sidebar_icon'); // "bi-hospital"
 *
 * @since 2026-02-12
 * @version 1.0.0
 */

class IndustryPreset {
    private static $instance = null;
    private $preset = [];
    private $presetId = 'medical_beauty';
    private $loaded = false;

    private function __construct() {}

    /**
     * 싱글톤 인스턴스
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 프리셋 로드
     * @param string|null $presetId 프리셋 ID (null이면 설정에서 자동 감지)
     */
    public function load($presetId = null) {
        if ($this->loaded && $presetId === null) {
            return;
        }

        // 프리셋 ID 결정 우선순위:
        // 1. 직접 지정된 값
        // 2. 관리자 화면(admin_industry.php)에서 저장한 활성 프리셋 데이터 파일 (presets/active_preset.txt)
        // 3. dbconfig.php의 INDUSTRY_PRESET 상수 (초기값 — 웹에서 더 이상 수정하지 않음)
        // 4. 기본값 'medical_beauty'
        $activeId = ($presetId === null) ? self::readActivePresetId() : null;
        if ($presetId !== null) {
            $this->presetId = $presetId;
        } elseif ($activeId !== null) {
            $this->presetId = $activeId;
        } elseif (defined('INDUSTRY_PRESET')) {
            $this->presetId = INDUSTRY_PRESET;
        } else {
            $this->presetId = 'medical_beauty';
        }

        // 경로 조작 방지: 프리셋 ID는 파일명 문자만 허용
        if (!preg_match('/^[a-z0-9_-]{1,64}$/i', (string)$this->presetId)) {
            error_log("[IndustryPreset] 잘못된 프리셋 ID 차단: {$this->presetId}");
            $this->presetId = 'medical_beauty';
        }

        $presetFile = __DIR__ . '/presets/' . $this->presetId . '.json';

        if (file_exists($presetFile)) {
            $json = file_get_contents($presetFile);
            $this->preset = json_decode($json, true);
            if ($this->preset === null) {
                // JSON 파싱 실패 시 기본값
                $this->preset = $this->getDefaults();
                error_log("[IndustryPreset] JSON 파싱 실패: {$presetFile}");
            }
        } else {
            // 프리셋 파일 없으면 기본값
            $this->preset = $this->getDefaults();
            if ($this->presetId !== 'medical_beauty') {
                error_log("[IndustryPreset] 프리셋 파일 없음: {$presetFile}");
            }
        }

        $this->loaded = true;
    }

    /**
     * 기본 프리셋 (medical_beauty 하드코딩 fallback)
     */
    private function getDefaults() {
        return [
            'industry_id' => 'medical_beauty',
            'display_name' => '의료미용',
            'display_name_en' => 'Medical Beauty',
            'terminology' => [
                'business' => '병원',
                'business_plural' => '병원 목록',
                'staff' => '의사',
                'staff_plural' => '의사 목록',
                'doctor' => '의사',
                'client' => '환자',
                'client_plural' => '환자 목록',
                'appointment' => '진료예약',
                'appointment_plural' => '예약 목록',
                'department' => '진료과',
                'department_plural' => '진료과 목록',
                'service' => '시술',
                'service_plural' => '시술 목록',
                'review' => '후기',
                'review_plural' => '후기 목록',
                'category' => '카테고리',
                'category_plural' => '카테고리 목록',
                'business_image' => '병원 이미지',
                'portfolio' => '비포/애프터',
                'member' => '회원',
                'member_plural' => '회원 목록',
                'booking' => '예약',
                'consultation' => '상담',
                'tips' => '꿀팁',
                'news' => '병원 소식',
                'price' => '가격',
                'ranking' => '랭킹',
                'location' => '주변 병원',
            ],
            'sidebar_icon' => 'bi-hospital',
            'admin_title_suffix' => '관리자',
            'default_theme' => [
                'primary_color' => '#FF6B9D',
                'accent_color' => '#C44569',
            ],
        ];
    }

    /**
     * 용어 가져오기
     * @param string $key 용어 키 (business, staff, client 등)
     * @param string|null $default 기본값
     * @return string
     */
    public function getTerm($key, $default = null) {
        $this->ensureLoaded();
        $terms = $this->preset['terminology'] ?? [];
        return $terms[$key] ?? $default ?? $key;
    }

    /**
     * 설정값 가져오기
     * @param string $key 설정 키 (dot notation 지원: default_theme.primary_color)
     * @param mixed $default 기본값
     * @return mixed
     */
    public function getConfig($key, $default = null) {
        $this->ensureLoaded();

        // dot notation 지원
        $keys = explode('.', $key);
        $value = $this->preset;
        foreach ($keys as $k) {
            if (is_array($value) && isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        return $value;
    }

    /**
     * 프리셋 ID
     */
    public function getId() {
        $this->ensureLoaded();
        return $this->presetId;
    }

    /**
     * 업종 표시명
     */
    public function getDisplayName() {
        $this->ensureLoaded();
        return $this->preset['display_name'] ?? '의료미용';
    }

    /**
     * 활성화된 기능 ID 목록
     */
    public function getEnabledFeatures() {
        $this->ensureLoaded();
        return $this->preset['enabled_features'] ?? [];
    }

    /**
     * 비활성화된 기능 ID 목록
     */
    public function getDisabledFeatures() {
        $this->ensureLoaded();
        return $this->preset['disabled_features'] ?? [];
    }

    /**
     * 특정 기능이 활성화되어 있는지
     */
    public function isFeatureEnabled($featureId) {
        $disabled = $this->getDisabledFeatures();
        if (!empty($disabled) && in_array($featureId, $disabled)) {
            return false;
        }
        $enabled = $this->getEnabledFeatures();
        if (!empty($enabled)) {
            return in_array($featureId, $enabled);
        }
        return true; // 기본: 활성
    }

    /**
     * 기본 카테고리 목록
     */
    public function getDefaultCategories() {
        $this->ensureLoaded();
        return $this->preset['default_categories'] ?? [];
    }

    /**
     * 전체 프리셋 데이터
     */
    public function getAll() {
        $this->ensureLoaded();
        return $this->preset;
    }

    /** 활성 프리셋 ID 를 담는 평문 데이터 파일 (실행되는 PHP 가 아님) */
    private static function activePresetFile() {
        return __DIR__ . '/presets/active_preset.txt';
    }

    /**
     * 저장된 활성 프리셋 ID (없거나 형식이 잘못되면 null)
     */
    public static function readActivePresetId() {
        $file = self::activePresetFile();
        if (!is_file($file)) return null;
        $id = trim((string)@file_get_contents($file, false, null, 0, 128));
        if (!preg_match('/^[a-z0-9_-]{1,64}$/i', $id)) return null;
        if (!is_file(__DIR__ . '/presets/' . $id . '.json')) return null;
        return $id;
    }

    /**
     * 활성 프리셋 ID 저장 — 임시 파일에 쓴 뒤 rename 으로 원자적 교체.
     * dbconfig.php(실행 코드·DB 비밀번호)를 웹에서 다시 쓰지 않기 위한 대체 저장소.
     * @return bool
     */
    public static function saveActivePresetId($id) {
        $id = (string)$id;
        if (!preg_match('/^[a-z0-9_-]{1,64}$/i', $id)) return false;
        if (!is_file(__DIR__ . '/presets/' . $id . '.json')) return false;
        $file = self::activePresetFile();
        $tmp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($tmp, $id . "\n", LOCK_EX) === false) return false;
        if (!@rename($tmp, $file)) { @unlink($tmp); return false; }
        return true;
    }

    /**
     * 사용 가능한 프리셋 목록 (파일 스캔)
     */
    public static function getAvailablePresets() {
        $dir = __DIR__ . '/presets/';
        $presets = [];
        if (is_dir($dir)) {
            foreach (glob($dir . '*.json') as $file) {
                $json = file_get_contents($file);
                $data = json_decode($json, true);
                if ($data && isset($data['industry_id'])) {
                    $presets[] = [
                        'id' => $data['industry_id'],
                        'name' => $data['display_name'] ?? $data['industry_id'],
                        'name_en' => $data['display_name_en'] ?? '',
                        'description' => $data['description'] ?? '',
                        'icon' => $data['sidebar_icon'] ?? 'bi-building',
                    ];
                }
            }
        }
        return $presets;
    }

    private function ensureLoaded() {
        if (!$this->loaded) {
            $this->load();
        }
    }
}

// ============================================================
// 전역 헬퍼 함수 (기존 코드에서 간편하게 사용)
// ============================================================

/**
 * 업종 용어 반환
 * @param string $key 용어 키
 * @param string|null $default 기본값
 * @return string
 *
 * 사용 예:
 *   term('business')   → "병원" / "매장" / "스파" / "치과" / "센터"
 *   term('staff')      → "의사" / "디자이너" / "테라피스트" / "트레이너"
 *   term('client')     → "환자" / "고객" / "회원"
 */
function term($key, $default = null) {
    return IndustryPreset::getInstance()->getTerm($key, $default);
}

/**
 * 업종 설정값 반환
 * @param string $key 설정 키 (dot notation: 'default_theme.primary_color')
 * @param mixed $default 기본값
 * @return mixed
 */
function industry_config($key, $default = null) {
    return IndustryPreset::getInstance()->getConfig($key, $default);
}

/**
 * 현재 업종 ID
 * @return string 'medical_beauty', 'beauty_salon', 'spa', 'dental', 'fitness'
 */
function industry_id() {
    return IndustryPreset::getInstance()->getId();
}

/**
 * 사이트명 (테마 설정 → fallback 업종명)
 * theme_helper.php의 getTheme()이 있으면 그것 우선, 없으면 프리셋 display_name
 */
function site_name() {
    if (function_exists('getTheme')) {
        $name = getTheme('site_name');
        if (!empty($name)) return $name;
    }
    return IndustryPreset::getInstance()->getDisplayName();
}

/**
 * 관리자 페이지 타이틀 생성
 * @param string $pageTitle 페이지명 (예: "대시보드")
 * @return string "대시보드 | 서울미녀 관리자"
 */
function admin_page_title($pageTitle = '') {
    $suffix = industry_config('admin_title_suffix', '관리자');
    $name = site_name();
    if ($pageTitle) {
        return "{$pageTitle} | {$name} {$suffix}";
    }
    return "{$name} {$suffix}";
}

// 자동 초기화 (require 시 즉시 로드)
IndustryPreset::getInstance()->load();
