-- 51-kun (2026-11-12): sign-in sessions owned by seoulbeauty-api (cookie sb_sid). Only the SHA-256 of the cookie value is stored.
-- Written and read by Nest (NEST_SESSIONS=1); PHP only deletes a row on logout / member switch (extend/sb_nest_session.extend.php).
CREATE TABLE IF NOT EXISTS sb_sessions (
  token_hash   CHAR(64)     NOT NULL,
  mb_id        VARCHAR(20)  NOT NULL,
  cred_epoch   INT          NOT NULL DEFAULT 0,
  remember     TINYINT(1)   NOT NULL DEFAULT 0,
  source       VARCHAR(20)  NOT NULL DEFAULT '',
  user_agent   VARCHAR(255) NOT NULL DEFAULT '',
  created_at   DATETIME     NOT NULL,
  last_seen_at DATETIME     NOT NULL,
  expires_at   DATETIME     NOT NULL,
  PRIMARY KEY (token_hash),
  KEY idx_sb_sessions_mb (mb_id),
  KEY idx_sb_sessions_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
