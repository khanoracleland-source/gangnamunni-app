-- Deleted units remain inactive so migration re-runs never restore them.
CREATE TABLE IF NOT EXISTS gangnamunni_treatment_units (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO gangnamunni_treatment_units (name) VALUES
('Box'), ('cc'), ('IU'), ('kj'), ('ml'), ('기타'), ('라인'), ('병'), ('샷'), ('시린지'), ('회');
