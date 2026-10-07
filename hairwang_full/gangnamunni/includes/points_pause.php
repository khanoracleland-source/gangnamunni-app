<?php
/**
 * 포인트 기능 일시 중지 스위치 (2026-10-07)
 *
 * POINTS_MENU_HIDDEN        : 마이페이지에서 '포인트' 메뉴(my_points.php 링크)를 숨기고,
 *                             my_points.php / my_points_policy.php / my_points_policy_detail.php 직접 접근 시 mypage.php 로 이동.
 * POINTS_ADMIN_GRANT_PAUSED : 관리자 화면에서 포인트를 지급하는 동작을 멈춘다.
 *                             - 포인트 관리: 수동 지급/차감, 데모 데이터 생성
 *                             - 후기 승인 적립, 경과 후기 승인 적립, 예약 시술 완료 적립
 *                             (회수/취소 처리는 계속 동작한다)
 * 다시 켜려면 아래 값을 false 로 바꾼다.
 */
if (!defined('POINTS_MENU_HIDDEN')) define('POINTS_MENU_HIDDEN', true);
if (!defined('POINTS_ADMIN_GRANT_PAUSED')) define('POINTS_ADMIN_GRANT_PAUSED', true);
