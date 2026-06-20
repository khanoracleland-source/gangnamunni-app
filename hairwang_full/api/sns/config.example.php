<?php
/**
 * ========================================
 * SNS 로그인 API 설정 파일 (예시)
 * ========================================
 *
 * 이 파일을 config.php로 복사한 후 실제 API 키를 입력하세요
 * cp config.example.php config.php
 *
 * @file    config.example.php
 * @created 2026-01-29
 * @version 1.0.0
 *
 * @security
 *   - config.php는 절대 Git에 커밋하지 마세요
 *   - .gitignore에 config.php를 추가하세요
 *   - 파일 권한을 600으로 설정하세요: chmod 600 config.php
 */

/**
 * ========================================
 * Naver 로그인 설정
 * ========================================
 *
 * @document https://developers.naver.com/docs/login/api/
 * @guide
 *   1. https://developers.naver.com/apps/#/register 접속
 *   2. 애플리케이션 등록
 *   3. 사용 API: 네아로 (네이버 아이디로 로그인)
 *   4. Callback URL 설정: https://yourdomain.com/api/sns/callback.php?provider=naver
 */
define('NAVER_CLIENT_ID', 'your_naver_client_id_here');
define('NAVER_CLIENT_SECRET', 'your_naver_client_secret_here');
define('NAVER_CALLBACK_URL', 'https://yourdomain.com/api/sns/callback.php?provider=naver');

/**
 * ========================================
 * Kakao 로그인 설정
 * ========================================
 *
 * @document https://developers.kakao.com/docs/latest/ko/kakaologin/common
 * @guide
 *   1. https://developers.kakao.com/console/app 접속
 *   2. 애플리케이션 추가
 *   3. 플랫폼 설정 > Web 플랫폼 등록
 *   4. 카카오 로그인 활성화
 *   5. Redirect URI 설정: https://yourdomain.com/api/sns/callback.php?provider=kakao
 */
define('KAKAO_REST_API_KEY', 'your_kakao_rest_api_key_here');
define('KAKAO_JAVASCRIPT_KEY', 'your_kakao_javascript_key_here');
define('KAKAO_ADMIN_KEY', 'your_kakao_admin_key_here');
define('KAKAO_CALLBACK_URL', 'https://yourdomain.com/api/sns/callback.php?provider=kakao');

/**
 * ========================================
 * Google 로그인 설정
 * ========================================
 *
 * @document https://developers.google.com/identity/sign-in/web/sign-in
 * @guide
 *   1. https://console.cloud.google.com/ 접속
 *   2. 프로젝트 생성
 *   3. API 및 서비스 > OAuth 동의 화면 구성
 *   4. API 및 서비스 > 사용자 인증 정보 > OAuth 2.0 클라이언트 ID 생성
 *   5. 승인된 리디렉션 URI: https://yourdomain.com/api/sns/callback.php?provider=google
 */
define('GOOGLE_CLIENT_ID', 'your_google_client_id_here.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'your_google_client_secret_here');
define('GOOGLE_CALLBACK_URL', 'https://yourdomain.com/api/sns/callback.php?provider=google');

/**
 * ========================================
 * Facebook 로그인 설정
 * ========================================
 *
 * @document https://developers.facebook.com/docs/facebook-login/web
 * @guide
 *   1. https://developers.facebook.com/apps/ 접속
 *   2. 앱 만들기
 *   3. Facebook 로그인 제품 추가
 *   4. 설정 > 기본 설정에서 앱 ID, 시크릿 확인
 *   5. Facebook 로그인 > 설정 > 유효한 OAuth 리디렉션 URI:
 *      https://yourdomain.com/api/sns/callback.php?provider=facebook
 */
define('FACEBOOK_APP_ID', 'your_facebook_app_id_here');
define('FACEBOOK_APP_SECRET', 'your_facebook_app_secret_here');
define('FACEBOOK_CALLBACK_URL', 'https://yourdomain.com/api/sns/callback.php?provider=facebook');

/**
 * ========================================
 * Twitter (X) 로그인 설정
 * ========================================
 *
 * @document https://developer.twitter.com/en/docs/authentication/oauth-2-0
 * @guide
 *   1. https://developer.twitter.com/en/portal/dashboard 접속
 *   2. 프로젝트 및 앱 생성
 *   3. Keys and tokens > API Key and Secret 생성
 *   4. Authentication settings > OAuth 2.0 활성화
 *   5. Callback URLs: https://yourdomain.com/api/sns/callback.php?provider=twitter
 */
define('TWITTER_API_KEY', 'your_twitter_api_key_here');
define('TWITTER_API_SECRET', 'your_twitter_api_secret_here');
define('TWITTER_BEARER_TOKEN', 'your_twitter_bearer_token_here');
define('TWITTER_CALLBACK_URL', 'https://yourdomain.com/api/sns/callback.php?provider=twitter');

/**
 * ========================================
 * Payco 로그인 설정
 * ========================================
 *
 * @document https://developers.payco.com/
 * @guide
 *   1. https://developers.payco.com/ 접속
 *   2. 서비스 등록
 *   3. Client ID, Secret 발급
 *   4. Redirect URI 설정: https://yourdomain.com/api/sns/callback.php?provider=payco
 */
define('PAYCO_CLIENT_ID', 'your_payco_client_id_here');
define('PAYCO_CLIENT_SECRET', 'your_payco_client_secret_here');
define('PAYCO_CALLBACK_URL', 'https://yourdomain.com/api/sns/callback.php?provider=payco');

/**
 * ========================================
 * 공통 설정
 * ========================================
 */

// API 응답 형식 (json, xml)
define('SNS_API_RESPONSE_FORMAT', 'json');

// API 타임아웃 (초)
define('SNS_API_TIMEOUT', 30);

// 디버그 모드 (true: 에러 표시, false: 에러 숨김)
define('SNS_DEBUG_MODE', false);

// 로그 저장 여부
define('SNS_ENABLE_LOGGING', true);

// 로그 파일 경로
define('SNS_LOG_FILE', __DIR__ . '/logs/sns_api.log');

/**
 * ========================================
 * 보안 설정
 * ========================================
 */

// CSRF 토큰 활성화
define('SNS_ENABLE_CSRF', true);

// State 파라미터 활성화 (OAuth 2.0)
define('SNS_ENABLE_STATE', true);

// IP 화이트리스트 (비어있으면 모든 IP 허용)
// 예: ['127.0.0.1', '192.168.1.100']
define('SNS_IP_WHITELIST', []);

// API 키 검증 활성화
define('SNS_ENABLE_API_KEY_VALIDATION', true);

// API 키 (외부에서 API 호출 시 필요)
define('SNS_API_KEY', 'your_custom_api_key_here');

/**
 * ========================================
 * 데이터베이스 설정 (선택사항)
 * ========================================
 *
 * SNS 로그인 정보를 데이터베이스에 저장하려면 설정하세요
 */

// 데이터베이스 사용 여부
define('SNS_USE_DATABASE', true);

// 데이터베이스 연결 정보 (그누보드 사용 시 그누보드 설정 사용)
// 별도 설정이 필요한 경우 아래 주석 해제
/*
define('SNS_DB_HOST', '127.0.0.1');
define('SNS_DB_PORT', '3306');
define('SNS_DB_USER', 'your_db_user');
define('SNS_DB_PASSWORD', 'your_db_password');
define('SNS_DB_NAME', 'your_db_name');
define('SNS_DB_CHARSET', 'utf8mb4');
*/

// SNS 로그인 정보 저장 테이블
define('SNS_LOGIN_TABLE', 'g5_member_sns');

/**
 * ========================================
 * 주의사항
 * ========================================
 *
 * 1. 모든 Callback URL은 HTTPS여야 합니다
 * 2. Callback URL은 각 SNS 플랫폼 개발자 콘솔에 정확히 등록되어야 합니다
 * 3. Client Secret, API Secret은 절대 노출되면 안 됩니다
 * 4. config.php 파일은 웹에서 직접 접근할 수 없도록 설정하세요
 * 5. 정기적으로 API 키를 갱신하세요
 */
?>
