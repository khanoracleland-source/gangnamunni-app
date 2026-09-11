-- Run on the application's database before deploying the PHP changes.
-- Existing published/deleted states are preserved. New posts default to pending.
ALTER TABLE gangnamunni_community_posts
    MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'pending';
