// ignore_for_file: constant_identifier_names


// ═══════════════════════════════════════════════════
// Seoul Beauty App Configuration
// ═══════════════════════════════════════════════════

const APP_NAME = '서울미녀';

/// Every Android installation subscribes to this topic, including guests.
/// Keep this value in sync with SEOULBEAUTY_ANDROID_BROADCAST_TOPIC on PHP.
const ANDROID_BROADCAST_TOPIC = 'seoulbeauty_android_all';

/// Every iOS installation subscribes to this topic, including guests.
/// Keep this value in sync with SEOULBEAUTY_IOS_BROADCAST_TOPIC on PHP.
const IOS_BROADCAST_TOPIC = 'seoulbeauty_ios_all';
const APP_LOGO_URL = '$DOMAIN_URL/img/logo/mini_logo.png';
const DEFAULT_LANGUAGE = 'ko';
// Firebase 콘솔 > 프로젝트 설정 > 웹 API 키에서 가져오기
const FIREBASE_SERVER_CLIENT_ID =
    "YOUR_FIREBASE_SERVER_CLIENT_ID"; // TODO: Firebase 콘솔에서 실제 클라이언트 ID 입력

///Live Url
const DOMAIN_URL = "https://kivicare2.seoulbeauty.app";
const BASE_URL = '$DOMAIN_URL/api/';
const HOSPITAL_API_BASE_URL = String.fromEnvironment(
  'HOSPITAL_API_BASE_URL',
  defaultValue: BASE_URL,
);

// Korean Market Settings
const DEFAULT_CURRENCY_CODE = 'KRW';
const DEFAULT_CURRENCY_SYMBOL = '₩';
const COUNTRY_CODE = 'KR';

const APP_PLAY_STORE_URL =
    'https://play.google.com/store/apps/details?id=com.celebhospital.patient';
const APP_APPSTORE_URL =
    'https://apps.apple.com/app/id0000000000'; // TODO: 실제 App Store ID로 업데이트 필요

const TERMS_CONDITION_URL = '$DOMAIN_URL/page/terms-conditions';
const PRIVACY_POLICY_URL = '$DOMAIN_URL/page/privacy-policy';
const INQUIRY_SUPPORT_EMAIL = 'support@seoulbeauty.app';

/// Seoul Beauty Customer Support (Korean)
const HELP_LINE_NUMBER = '+82-2-1234-5678'; // Update with actual number

//region WebView Settings
/// Show WebView on first launch
const SHOW_WEBVIEW_ON_STARTUP = true;

/// WebView URL to load on startup
const STARTUP_WEBVIEW_URL =
    'https://hms.seoulbeauty.app/hairwang/gangnamunni/?app=patient';

/// WebView Type: 'general' or 'sns'
const STARTUP_WEBVIEW_TYPE = 'general'; // 'general' | 'sns'

/// 웹사이트 하단 네비게이션 바 숨김 여부 (CSS 주입)
const HIDE_WEBVIEW_BOTTOM_NAV = false;

/// WebView 상단 새로고침 버튼 숨김 여부
const HIDE_WEBVIEW_REFRESH_BUTTON = true;
//endregion
