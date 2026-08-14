-- Back up both review tables before running this migration.
START TRANSACTION;

-- Fail closed even when a legacy/import script omits is_active.
ALTER TABLE hospital_reviews ALTER is_active SET DEFAULT 0;

-- Every submitted review is published as a record, but only admin-approved
-- reviews (is_active=1) are public and participate in hospital ratings.
UPDATE hospital_reviews
SET review_status = 'published';

-- Keep one five-point scale. Invalid legacy values are constrained to 1..5.
UPDATE hospital_reviews
SET rating = LEAST(5, GREATEST(1, rating))
WHERE rating IS NOT NULL;

-- Remove imported/legacy 10-point hospital scores and rebuild the cache solely
-- from approved reviews. Hospitals without approved reviews receive 0.0.
UPDATE gangnamunni_hospitals h
LEFT JOIN (
    SELECT hospital_id,
           COUNT(*) AS approved_review_count,
           AVG(LEAST(5, GREATEST(1, rating))) AS approved_rating
    FROM hospital_reviews
    WHERE is_active = 1
    GROUP BY hospital_id
) r ON r.hospital_id = h.id
SET h.review_count = COALESCE(r.approved_review_count, 0),
    h.rating = COALESCE(r.approved_rating, 0);

COMMIT;

-- Run this only after checking that no required data exists solely in the old table:
-- SELECT COUNT(*) AS old_review_count FROM gangnamunni_reviews;
-- DROP TABLE IF EXISTS gangnamunni_reviews;
