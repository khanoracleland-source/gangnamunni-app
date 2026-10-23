-- 마이페이지 "상담 내역"용: 상담 신청을 회원과 연결한다. (기존 신청은 mb_id 가 비어 있어 목록에 나오지 않는다.)
ALTER TABLE gangnamunni_consultations
  ADD COLUMN mb_id VARCHAR(50) NULL DEFAULT NULL AFTER hospital_name,
  ADD KEY idx_consult_mb (mb_id);
