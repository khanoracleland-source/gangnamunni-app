<?php
/**
 * KG이니시스 설정 — 이 파일을 hairwang_full/data/payment_config.php 로 복사해 값을 채운다 (data/ 는 git 밖).
 * booking_payment_setting() 이 define() 값을 읽는다. 요청값이나 저장소에는 절대 넣지 않는다.
 *
 * 값 위치: 이니시스 가맹점관리자 > Store Info > Contract information > PG API Integration Details
 *   INICIS_MID        상점아이디 (예: seoulbtapp). 테스트는 INIpayTest.
 *   INICIS_INIAPI_KEY "INIAPI Key" (환불 API 서명키). 결제창 Hash Key 와 다르다 — 그것은 Nest API .env 의 INICIS_HASHKEY.
 *   INICIS_MODE       live | test. live 는 테스트 MID 를 거부하고, test 는 실 MID 를 거부한다. Nest API .env 의 INICIS_MODE 와 같아야 한다.
 *   INICIS_CLIENT_IP  (필수) 환불 요청을 보내는 서버의 공인 IP. hashData 에 포함되며 이니시스에 등록된 IP 여야 한다.
 */
define('INICIS_MID', '');
define('INICIS_INIAPI_KEY', '');
define('INICIS_MODE', 'live');
define('INICIS_CLIENT_IP', '');
