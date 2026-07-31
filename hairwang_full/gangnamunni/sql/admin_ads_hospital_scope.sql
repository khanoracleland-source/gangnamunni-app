-- admin_ads.php hospital scope migration
-- MariaDB 10.2+

ALTER TABLE gangnamunni_advertisers
    ADD COLUMN IF NOT EXISTS hospital_id BIGINT UNSIGNED DEFAULT NULL AFTER id;

ALTER TABLE gangnamunni_ads
    ADD COLUMN IF NOT EXISTS hospital_id BIGINT UNSIGNED DEFAULT NULL AFTER id;

-- Faqat nomi yagona mos keladigan eski yozuvlar avtomatik bog'lanadi.
UPDATE gangnamunni_advertisers adv
JOIN (
    SELECT name, MIN(id) AS hospital_id
    FROM gangnamunni_hospitals
    GROUP BY name
    HAVING COUNT(*) = 1
) h ON h.name = adv.hospital_name
SET adv.hospital_id = h.hospital_id
WHERE adv.hospital_id IS NULL;

UPDATE gangnamunni_ads ad
JOIN gangnamunni_advertisers adv ON adv.id = ad.advertiser_id
SET ad.hospital_id = adv.hospital_id
WHERE ad.hospital_id IS NULL
  AND adv.hospital_id IS NOT NULL;

ALTER TABLE gangnamunni_advertisers
    ADD INDEX IF NOT EXISTS idx_advertisers_hospital_id (hospital_id);

ALTER TABLE gangnamunni_ads
    ADD INDEX IF NOT EXISTS idx_ads_hospital_status (hospital_id, status);

