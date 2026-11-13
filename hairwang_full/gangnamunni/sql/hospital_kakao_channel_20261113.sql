-- 병원별 카카오톡 채널 URL (선택). 값이 있으면 앱 상담 신청서에 '카카오톡 채널' 상담 방법이 표시된다.
-- 여러 번 실행해도 안전하다 (MariaDB 10.5: ADD COLUMN IF NOT EXISTS).
ALTER TABLE gangnamunni_hospitals
  ADD COLUMN IF NOT EXISTS kakao_channel_url VARCHAR(255) NULL DEFAULT NULL COMMENT '카카오톡 채널 URL (선택)';
