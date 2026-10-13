-- 관리자 ↔ 그누보드 회원 명시적 SSO 연결 (2026-10-13)
-- 이전: 회원 mb_id / mb_email 이 관리자 username / email 과 같으면 그 병원 관리자로 SSO 로그인됨 (계정 충돌 취약점).
-- 이후: gangnamunni_admins.g5_mb_id 에 연결된 회원만 SSO 로그인. 미연결 계정은 비밀번호 로그인만 가능.

ALTER TABLE gangnamunni_admins
    ADD COLUMN g5_mb_id VARCHAR(20) NULL DEFAULT NULL COMMENT 'SSO 연결 그누보드 회원 mb_id' AFTER email,
    ADD UNIQUE KEY uq_admins_g5_mb_id (g5_mb_id);

-- (선택) 기존 병원 관리자 연결 후보 검토 — 자동 실행하지 말고 결과를 확인한 뒤 한 건씩 연결하세요.
-- 병원 승인 시 자동 생성된 회원은 mb_1='hospital' 이고 이메일이 관리자 이메일과 같습니다.
-- 단, 공격자가 같은 이메일로 먼저 가입했을 수도 있으므로 가입일(mb_datetime)이 관리자 생성일과 가까운지,
-- 가입 IP 등을 확인하세요.
SELECT a.id AS admin_id, a.username, a.email, a.hospital_id, a.created_at AS admin_created,
       m.mb_id, m.mb_datetime AS member_created, m.mb_ip, m.mb_1
  FROM gangnamunni_admins a
  JOIN g5_member m ON LOWER(m.mb_email) = LOWER(a.email)
 WHERE a.role IN ('hospital_super_admin', 'hospital_admin')
   AND a.g5_mb_id IS NULL
 ORDER BY a.id;

-- 확인 후 연결 예:
-- UPDATE gangnamunni_admins SET g5_mb_id = '<mb_id>' WHERE id = <admin_id> AND g5_mb_id IS NULL;
-- 또는 관리자 관리(admin_admins.php) > 수정 > "SSO 연결 회원 아이디" 에 입력.
