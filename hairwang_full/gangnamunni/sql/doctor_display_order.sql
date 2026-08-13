-- 병원별 의사 사용자 화면 노출 순서
-- NULL: 순서 지정 의사 뒤에서 이름순으로 표시
ALTER TABLE gangnamunni_doctors
    ADD COLUMN display_order INT NULL AFTER status;

