-- ========================================
-- 후기 에디터 관련 테이블
-- 실행: mysql -u root -p gangnamunni < review_tables.sql
-- ========================================

-- hospital_reviews 테이블 컬럼 추가 (기존 테이블 확장)
-- 참고: IF NOT EXISTS는 MariaDB 전용. MySQL에서는 각 ALTER를 개별 실행
-- 이미 컬럼이 존재하면 에러 발생 (무시 가능)
ALTER TABLE hospital_reviews
  ADD COLUMN title VARCHAR(200) DEFAULT NULL AFTER event_id;
ALTER TABLE hospital_reviews
  ADD COLUMN editor_content LONGTEXT DEFAULT NULL AFTER content;
ALTER TABLE hospital_reviews
  ADD COLUMN editor_type ENUM('text','quill') DEFAULT 'text' AFTER editor_content;
ALTER TABLE hospital_reviews
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 후기 이미지 테이블
CREATE TABLE IF NOT EXISTS review_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  review_id INT NOT NULL,
  image_url VARCHAR(500) NOT NULL,
  thumbnail_url VARCHAR(500) DEFAULT NULL,
  image_order INT DEFAULT 0,
  file_size INT UNSIGNED DEFAULT 0,
  original_name VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_review_id (review_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 후기 댓글 테이블
CREATE TABLE IF NOT EXISTS review_comments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  review_id INT NOT NULL,
  parent_id INT DEFAULT NULL,
  user_id VARCHAR(100) DEFAULT NULL,
  user_name VARCHAR(50) NOT NULL,
  content TEXT NOT NULL,
  like_count INT DEFAULT 0,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_review_id (review_id),
  INDEX idx_parent_id (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 좋아요 중복 방지 테이블
CREATE TABLE IF NOT EXISTS review_likes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  review_id INT NOT NULL,
  user_id VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_review_user (review_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 후기 신고 테이블
CREATE TABLE IF NOT EXISTS review_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  review_id INT NOT NULL,
  reporter_id VARCHAR(100) NOT NULL,
  reason ENUM('spam','abuse','fake','inappropriate','other') NOT NULL,
  description TEXT DEFAULT NULL,
  status ENUM('pending','reviewed','dismissed') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  processed_at TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_review_id (review_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
