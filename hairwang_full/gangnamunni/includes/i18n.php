<?php
/**
 * 다국어(i18n) 시스템
 * 10개 언어 지원: ko, en, ja, zh_CN, zh_TW, vi, th, id, es, ru
 * - lang/{locale}.json 파일에서 번역 로드
 * - __('key') 함수로 번역 텍스트 반환
 * - 쿠키/세션/URL 파라미터로 언어 선택
 * @created 2026-04-06
 */

class I18n {
    private static $instance = null;
    private $locale = 'ko';
    private $translations = [];
    private $fallback = [];
    private $supported = ['ko','en','ja','zh_CN','zh_TW','vi','th','id','es','ru'];

    private function __construct() {}

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->init();
        }
        return self::$instance;
    }

    private function init() {
        // 언어 결정 우선순위: URL ?lang= > 쿠키 > 세션 > DB설정 > 브라우저 > 기본값(ko)
        if (!empty($_GET['lang']) && in_array($_GET['lang'], $this->supported)) {
            $this->locale = $_GET['lang'];
            setcookie('sb_lang', $this->locale, time() + 86400 * 365, '/');
            if (isset($_SESSION)) $_SESSION['sb_lang'] = $this->locale;
        } elseif (!empty($_COOKIE['sb_lang']) && in_array($_COOKIE['sb_lang'], $this->supported)) {
            $this->locale = $_COOKIE['sb_lang'];
        } elseif (isset($_SESSION['sb_lang']) && in_array($_SESSION['sb_lang'], $this->supported)) {
            $this->locale = $_SESSION['sb_lang'];
        } else {
            // DB 기본 언어 설정
            global $connect;
            if ($connect) {
                $r = mysqli_query($connect, "SELECT setting_value FROM gangnamunni_settings WHERE setting_key='default_language' LIMIT 1");
                if ($r && $row = mysqli_fetch_assoc($r)) {
                    if (in_array($row['setting_value'], $this->supported)) {
                        $this->locale = $row['setting_value'];
                    }
                }
            }
        }

        // 번역 파일 로드
        $lang_dir = __DIR__ . '/../lang/';
        $file = $lang_dir . $this->locale . '.json';
        if (file_exists($file)) {
            $this->translations = json_decode(file_get_contents($file), true) ?? [];
        }

        // 한국어 fallback (키가 없을 때)
        if ($this->locale !== 'ko') {
            $ko_file = $lang_dir . 'ko.json';
            if (file_exists($ko_file)) {
                $this->fallback = json_decode(file_get_contents($ko_file), true) ?? [];
            }
        }
    }

    public function get($key, $default = null) {
        return $this->translations[$key] ?? $this->fallback[$key] ?? $default ?? $key;
    }

    public function getLocale() { return $this->locale; }
    public function getSupported() { return $this->supported; }

    // 언어 라벨
    public function getLanguageLabel($code = null) {
        $labels = [
            'ko' => '한국어', 'en' => 'English', 'ja' => '日本語',
            'zh_CN' => '简体中文', 'zh_TW' => '繁體中文', 'vi' => 'Tiếng Việt',
            'th' => 'ไทย', 'id' => 'Bahasa', 'es' => 'Español', 'ru' => 'Русский',
        ];
        $c = $code ?? $this->locale;
        return $labels[$c] ?? $c;
    }

    // 국기 이모지
    public function getFlag($code = null) {
        $flags = [
            'ko' => '🇰🇷', 'en' => '🇺🇸', 'ja' => '🇯🇵',
            'zh_CN' => '🇨🇳', 'zh_TW' => '🇹🇼', 'vi' => '🇻🇳',
            'th' => '🇹🇭', 'id' => '🇮🇩', 'es' => '🇪🇸', 'ru' => '🇷🇺',
        ];
        $c = $code ?? $this->locale;
        return $flags[$c] ?? '🌐';
    }
}

/**
 * 번역 함수 (숏컷)
 * 사용법: echo __('login'); // → "로그인"
 */
function __($key, $default = null) {
    return I18n::getInstance()->get($key, $default);
}

/**
 * 현재 언어
 */
function currentLang() {
    return I18n::getInstance()->getLocale();
}
