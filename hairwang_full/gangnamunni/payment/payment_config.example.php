<?php
/**
 * KG이니시스 설정 — 이 파일을 hairwang_full/data/payment_config.php 로 복사해 값을 채운다 (data/ 는 git 밖).
 * booking_payment_setting() 이 define() 값을 읽는다. 요청값이나 저장소에는 절대 넣지 않는다.
 *
 * 값 위치: 이니시스 가맹점관리자 > Store Info > Contract information > PG API Integration Details
 *   INICIS_MID        상점아이디 (예: seoulbtapp). 테스트는 INIpayTest.
 *   INICIS_INIAPI_KEY "INIAPI Key" (환불 API 서명키). 결제창 Hash Key 와 다르다.
 *   INICIS_HASHKEY    "모바일 금액위변조 Hash Key" (Store Info > Contract information > KEY information). 카드 결제창 서명(P_CHKFAKE)·망취소에 쓴다.
 *                     Nest API .env 의 INICIS_HASHKEY 와 같은 값. 비어 있으면 PHP 카드 결제는 켜지지 않는다.
 *   INICIS_MODE       live | test. live 는 테스트 MID 를 거부하고, test 는 실 MID 를 거부한다. Nest API .env 의 INICIS_MODE 와 같아야 한다.
 *   INICIS_CLIENT_IP  (필수) 환불 요청을 보내는 서버의 공인 IP. hashData 에 포함되며 이니시스에 등록된 IP 여야 한다.
 */
define('INICIS_MID', '');
define('INICIS_INIAPI_KEY', '');
define('INICIS_MODE', 'live');
define('INICIS_CLIENT_IP', '');
define('INICIS_HASHKEY', '');

/*
 * PHP(iOS) 카드 결제를 켜는 스위치. 아래가 모두 있어야 결제 버튼이 열린다 (하나라도 없으면 지금처럼 '결제 없이 계속하기' 만 보인다).
 *   G5_PG_PAYMENTS_ENABLED   '1' 일 때만 실결제 화면을 연다.
 *   BOOKING_PAYMENT_METHODS  'card' (이니시스는 카드만 연결돼 있다).
 *   BOOKING_PAYMENT_BASE_URL 공개 https gangnamunni 주소. P_NEXT_URL(booking_payment_inicis_return.php)·P_CLOSE_URL 이 여기서 만들어진다.
 *   INICIS_LIVE_MEMBERS      live 모드에서 결제할 수 있는 mb_id (쉼표 구분). 빈 값 = 아무도 아님, '*' = 모두. 먼저 한 명으로 시험한다.
 *   INICIS_TEST_MEMBERS      test 모드(INIpayTest)에서 결제할 수 있는 mb_id.
 * 선택:
 *   INICIS_APP_SCHEME        iOS 카드 앱이 돌아올 앱 URL 스킴 (기본 wellness -> wellness://payment)
 *   INICIS_ALERT_WEBHOOK     https 웹훅(Slack 호환). 망취소 실패·승인 결과 불명 등 돈이 걸린 이벤트를 알린다. 로그에는 항상 남는다.
 */
// 주의: G5_PG_PAYMENTS_ENABLED 와 BOOKING_PAYMENT_METHODS 는 여기서 바꾸지 않는다. data/dbconfig.php 에서 바꾼다.
// dbconfig.php 가 이 두 값을 먼저 define('0', '') 하고, define() 은 먼저 정의된 값이 우선하므로 여기에 적어도 적용되지 않는다 (카드 결제가 조용히 꺼진 채로 남는다).
// define('BOOKING_PAYMENT_BASE_URL', 'https://seoulbeauty.app/gangnamunni');
// define('INICIS_LIVE_MEMBERS', 'member_id_here');
// define('INICIS_TEST_MEMBERS', '');
