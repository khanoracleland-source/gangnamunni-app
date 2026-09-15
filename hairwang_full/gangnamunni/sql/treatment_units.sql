-- Deleted units remain inactive so migration re-runs never restore them.
CREATE TABLE IF NOT EXISTS gangnamunni_treatment_units (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO gangnamunni_treatment_units (name) VALUES
('샷'), ('cc'), ('ml'), ('kj'), ('회'), ('병'), ('IU'), ('라인'), ('시린지'), ('Box'), ('기타');
