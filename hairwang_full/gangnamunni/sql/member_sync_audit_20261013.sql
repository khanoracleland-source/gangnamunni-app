-- member_sync 감사 (2026-10-13): 이메일 충돌로 그누보드 회원이 직원/관리자 HMS 계정에 연결된 매핑 찾기
-- 이전 코드는 가입 이메일이 같은 HMS users 행(최고관리자 포함)에 자동 연결하고 이름·username·비밀번호를 덮어썼다.

-- 1) 환자 전용이 아닌 HMS 계정에 연결된 매핑 (가장 위험 — 결과가 있으면 즉시 확인)
SELECT s.mb_no, s.mb_id, s.hms_user_id, s.sync_status, s.last_synced_at,
       u.email, u.username, u.owner_type, u.updated_at,
       GROUP_CONCAT(r.name) AS roles,
       m.mb_datetime AS member_joined, m.mb_ip AS member_join_ip
  FROM member_sync s
  JOIN users u ON u.id = s.hms_user_id
  LEFT JOIN model_has_roles mr ON mr.model_id = u.id AND mr.model_type = 'App\\Models\\User'
  LEFT JOIN roles r ON r.id = mr.role_id
  LEFT JOIN g5_member m ON m.mb_no = s.mb_no
 WHERE u.owner_type IS NULL OR u.owner_type <> 'App\\Models\\Patient'
    OR EXISTS (SELECT 1 FROM model_has_roles mr2 JOIN roles r2 ON r2.id = mr2.role_id
                WHERE mr2.model_id = u.id AND mr2.model_type = 'App\\Models\\User' AND r2.name <> 'Patient')
 GROUP BY s.id
 ORDER BY s.last_synced_at DESC;

-- 2) 위 결과가 있으면: 매핑 해제 + 해당 회원의 SSO 토큰 삭제 후, 그 HMS 계정 비밀번호를 재설정하고
--    username/이름을 원래대로 복구하세요 (이전 동기화가 회원 값으로 덮어썼을 수 있음).
-- UPDATE member_sync SET sync_status='unlinked', hms_user_id=NULL, hms_patient_id=NULL WHERE mb_no = <mb_no>;
-- DELETE FROM sso_tokens WHERE g5_mb_id = '<mb_id>' AND user_type <> 'hms';

-- 3) 참고: 이메일 충돌로 이번 수정 이후 연결이 보류된 회원
SELECT mb_no, mb_id, error_message, updated_at FROM member_sync
 WHERE sync_status = 'error' AND (error_message LIKE 'email_conflict%' OR error_message LIKE 'hms_user_not_patient%');
