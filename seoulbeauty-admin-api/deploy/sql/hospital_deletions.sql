-- Archive of deleted hospitals (admin panel). Run once by the DB admin before turning ADMIN_HOSPITALS_DELETE on.
-- The API never creates this table. Safe to run again.
CREATE TABLE IF NOT EXISTS gangnamunni_hospital_deletions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  hospital_id INT NOT NULL,
  hospital_name VARCHAR(255) NOT NULL DEFAULT '',
  snapshot LONGTEXT NULL,
  bookings_total INT NOT NULL DEFAULT 0,
  bookings_cancelled INT NOT NULL DEFAULT 0,
  sales_paid BIGINT NOT NULL DEFAULT 0,
  sales_refunded BIGINT NOT NULL DEFAULT 0,
  sales_net BIGINT NOT NULL DEFAULT 0,
  deleted_by_admin_id INT NULL,
  deleted_by_name VARCHAR(100) NOT NULL DEFAULT '',
  deleted_at DATETIME NOT NULL,
  notice_acked_at DATETIME NULL,
  notice_acked_by VARCHAR(100) NULL,
  PRIMARY KEY (id),
  KEY idx_hospital_deletions_hospital (hospital_id),
  KEY idx_hospital_deletions_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
