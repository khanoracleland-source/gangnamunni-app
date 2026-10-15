-- ============================================================================
-- 비밀번호 변경 시 자격증명 일괄 폐기 (2026-10-15)
--
-- 배경: 비밀번호를 바꿔도 remember 쿠키(web_remember), 앱 리프레시
-- (patient_app_remember), 액세스/브릿지/교환 토큰, SSO 토큰, 다른 기기의 PHP
-- 세션, HMS(Laravel) Sanctum 토큰이 모두 그대로 살아 있었다. 즉 비밀번호가
-- 유출돼 이미 로그인된 침입자는 비밀번호를 바꿔도 쫓아낼 수 없었다.
--
-- 세션은 서버에서 열거할 수 없으므로 "자격증명 세대"(epoch)를 쓴다. 세션이
-- 만들어질 때 그 시점의 epoch 를 세션에 복사해 두고, 매 요청마다 레코드의
-- epoch 와 비교한다. 비밀번호를 바꿀 때 epoch 를 +1 하면 이전 세대의 세션은
-- 전부 무효가 된다.
--
-- ※ 코드보다 먼저 적용한다. 컬럼이 없으면 폐기가 세션을 끊지 못하고 비교도
--    건너뛴다(에러는 나지 않지만 보호도 없다).
-- ※ 둘 다 맨 뒤에 추가하는 형태이므로 MariaDB 10.3+ 에서 ALGORITHM=INSTANT
--    로 처리된다(테이블 복사 없음).
-- ============================================================================

ALTER TABLE g5_member
  ADD COLUMN mb_cred_epoch INT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE gangnamunni_admins
  ADD COLUMN cred_epoch INT UNSIGNED NOT NULL DEFAULT 0;

-- 확인
-- SHOW COLUMNS FROM g5_member LIKE 'mb_cred_epoch';
-- SHOW COLUMNS FROM gangnamunni_admins LIKE 'cred_epoch';

-- ----------------------------------------------------------------------------
-- (선택) 이미 유출됐을 수 있는 자격증명 일괄 폐기
--
-- 이 수정 이전에 비밀번호를 바꾼 계정들은, 그 시점에 침입자의 토큰/세션이
-- 폐기되지 않았다. 과거 토큰을 모두 끊으려면 아래를 실행한다 — 모든 회원이
-- 다시 로그인해야 하므로(앱 포함) 영향 범위를 확인한 뒤 결정한다.
--
-- DELETE FROM sso_tokens;
-- UPDATE g5_member SET mb_cred_epoch = mb_cred_epoch + 1;
-- UPDATE gangnamunni_admins SET cred_epoch = cred_epoch + 1;
-- DELETE FROM personal_access_tokens WHERE tokenable_type = 'App\\Models\\User';
-- ----------------------------------------------------------------------------

-- 롤백
-- ALTER TABLE g5_member DROP COLUMN mb_cred_epoch;
-- ALTER TABLE gangnamunni_admins DROP COLUMN cred_epoch;
