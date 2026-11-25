-- 부위(시술 부위) 목록: 그룹과 항목을 DB에서 관리한다. 항목 이름은 가격 옵션 행의 gangnamunni_hospital_treatment_prices.body_part 에
-- '||' 로 이어진 글자로 저장되므로 이름은 서로 달라야 하고(UNIQUE), '||' 를 포함하지 않으며 100자 이하여야 한다.
-- 여러 번 실행해도 안전하다(IF NOT EXISTS / INSERT IGNORE). 기존 5개 그룹·항목과 새로 추가된 7개(자갈턱, 광대, 거미스마일, 립플립, 나비존, 손바닥, 발바닥)를 넣는다.
CREATE TABLE IF NOT EXISTS gangnamunni_body_part_groups (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_body_part_group_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gangnamunni_body_parts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  group_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_body_part_name (name),
  KEY idx_body_part_group (group_id, sort_order),
  CONSTRAINT fk_body_part_group FOREIGN KEY (group_id) REFERENCES gangnamunni_body_part_groups (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO gangnamunni_body_part_groups (name, sort_order) VALUES ('얼굴 전체 및 헤어', 10);
INSERT IGNORE INTO gangnamunni_body_part_groups (name, sort_order) VALUES ('상안면부 (눈/이마 주변)', 20);
INSERT IGNORE INTO gangnamunni_body_part_groups (name, sort_order) VALUES ('중안면부 (코/볼/팔자 주변)', 30);
INSERT IGNORE INTO gangnamunni_body_part_groups (name, sort_order) VALUES ('하안면부 (입술/턱 주변)', 40);
INSERT IGNORE INTO gangnamunni_body_part_groups (name, sort_order) VALUES ('바디 (체형 및 레이저 제모 등)', 50);

-- 얼굴 전체 및 헤어
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '얼굴 전체 (풀페이스)', 10 FROM gangnamunni_body_part_groups WHERE name = '얼굴 전체 및 헤어';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '헤어라인', 20 FROM gangnamunni_body_part_groups WHERE name = '얼굴 전체 및 헤어';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '두피', 30 FROM gangnamunni_body_part_groups WHERE name = '얼굴 전체 및 헤어';

-- 상안면부 (눈/이마 주변)
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '이마', 10 FROM gangnamunni_body_part_groups WHERE name = '상안면부 (눈/이마 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '미간', 20 FROM gangnamunni_body_part_groups WHERE name = '상안면부 (눈/이마 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '눈가 (눈웃음 주름)', 30 FROM gangnamunni_body_part_groups WHERE name = '상안면부 (눈/이마 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '눈밑 (다크서클, 눈밑 꺼짐)', 40 FROM gangnamunni_body_part_groups WHERE name = '상안면부 (눈/이마 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '애교살', 50 FROM gangnamunni_body_part_groups WHERE name = '상안면부 (눈/이마 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '관자놀이', 60 FROM gangnamunni_body_part_groups WHERE name = '상안면부 (눈/이마 주변)';

-- 중안면부 (코/볼/팔자 주변)
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '광대', 10 FROM gangnamunni_body_part_groups WHERE name = '중안면부 (코/볼/팔자 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '앞광대', 20 FROM gangnamunni_body_part_groups WHERE name = '중안면부 (코/볼/팔자 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '옆광대', 30 FROM gangnamunni_body_part_groups WHERE name = '중안면부 (코/볼/팔자 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '코 (콧대, 코끝)', 40 FROM gangnamunni_body_part_groups WHERE name = '중안면부 (코/볼/팔자 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '콧볼 (콧볼 축소 등)', 50 FROM gangnamunni_body_part_groups WHERE name = '중안면부 (코/볼/팔자 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '팔자주름', 60 FROM gangnamunni_body_part_groups WHERE name = '중안면부 (코/볼/팔자 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '인중', 70 FROM gangnamunni_body_part_groups WHERE name = '중안면부 (코/볼/팔자 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '나비존', 80 FROM gangnamunni_body_part_groups WHERE name = '중안면부 (코/볼/팔자 주변)';

-- 하안면부 (입술/턱 주변)
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '입술 (입술 볼륨)', 10 FROM gangnamunni_body_part_groups WHERE name = '하안면부 (입술/턱 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '립플립', 20 FROM gangnamunni_body_part_groups WHERE name = '하안면부 (입술/턱 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '입꼬리', 30 FROM gangnamunni_body_part_groups WHERE name = '하안면부 (입술/턱 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '거미스마일', 40 FROM gangnamunni_body_part_groups WHERE name = '하안면부 (입술/턱 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '사각턱 (턱 근육)', 50 FROM gangnamunni_body_part_groups WHERE name = '하안면부 (입술/턱 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '자갈턱', 60 FROM gangnamunni_body_part_groups WHERE name = '하안면부 (입술/턱 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '턱끝 (무턱)', 70 FROM gangnamunni_body_part_groups WHERE name = '하안면부 (입술/턱 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '이중턱 (턱밑 뱃살)', 80 FROM gangnamunni_body_part_groups WHERE name = '하안면부 (입술/턱 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '심부볼 (불독살)', 90 FROM gangnamunni_body_part_groups WHERE name = '하안면부 (입술/턱 주변)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '턱선 (마리오넷 주름, 페이스라인)', 100 FROM gangnamunni_body_part_groups WHERE name = '하안면부 (입술/턱 주변)';

-- 바디 (체형 및 레이저 제모 등)
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '목 (목주름)', 10 FROM gangnamunni_body_part_groups WHERE name = '바디 (체형 및 레이저 제모 등)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '승모근 (어깨 라인)', 20 FROM gangnamunni_body_part_groups WHERE name = '바디 (체형 및 레이저 제모 등)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '쇄골', 30 FROM gangnamunni_body_part_groups WHERE name = '바디 (체형 및 레이저 제모 등)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '겨드랑이', 40 FROM gangnamunni_body_part_groups WHERE name = '바디 (체형 및 레이저 제모 등)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '팔 (팔뚝)', 50 FROM gangnamunni_body_part_groups WHERE name = '바디 (체형 및 레이저 제모 등)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '복부 (윗배, 아랫배)', 60 FROM gangnamunni_body_part_groups WHERE name = '바디 (체형 및 레이저 제모 등)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '러브핸들 (옆구리)', 70 FROM gangnamunni_body_part_groups WHERE name = '바디 (체형 및 레이저 제모 등)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '등', 80 FROM gangnamunni_body_part_groups WHERE name = '바디 (체형 및 레이저 제모 등)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '허벅지 (안쪽, 바깥쪽)', 90 FROM gangnamunni_body_part_groups WHERE name = '바디 (체형 및 레이저 제모 등)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '종아리 (종아리 알)', 100 FROM gangnamunni_body_part_groups WHERE name = '바디 (체형 및 레이저 제모 등)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '손등 / 발등', 110 FROM gangnamunni_body_part_groups WHERE name = '바디 (체형 및 레이저 제모 등)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '손바닥', 120 FROM gangnamunni_body_part_groups WHERE name = '바디 (체형 및 레이저 제모 등)';
INSERT IGNORE INTO gangnamunni_body_parts (group_id, name, sort_order) SELECT id, '발바닥', 130 FROM gangnamunni_body_part_groups WHERE name = '바디 (체형 및 레이저 제모 등)';
