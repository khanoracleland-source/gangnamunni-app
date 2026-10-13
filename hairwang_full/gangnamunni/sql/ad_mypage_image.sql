-- 광고 배너: 마이페이지 전용 이미지 (2026-10-13)
-- index.php 는 image_url, mypage.php 는 mypage_image_url 을 사용한다.
-- mypage_image_url 이 비어 있으면 mypage.php 도 image_url 로 대체 표시한다.
-- MariaDB 10.2+ (admin_ads.php 를 열면 컬럼이 없을 때 자동으로 추가하기도 한다)

ALTER TABLE gangnamunni_ads
    ADD COLUMN IF NOT EXISTS mypage_image_url VARCHAR(500) DEFAULT NULL AFTER image_url;
