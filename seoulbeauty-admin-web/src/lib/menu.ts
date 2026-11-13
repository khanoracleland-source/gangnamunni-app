import type { IconName } from "@/components/Icon";
import type { Locale } from "./i18n";

export type Label = Record<Locale, string>;

export type MenuItem = {
  key: string;
  label: Label;
  icon: IconName;
  /**
   * "new": the page exists in this panel (href is a route inside this app, without basePath).
   * "legacy": still in the old panel; the item opens that page (href is a site-absolute path).
   */
  status: "new" | "legacy";
  href: string;
};

export type MenuGroup = { key: string; label: Label; icon: IconName; items: MenuItem[] };

/** Where the old panel lives (same origin). */
const LEGACY = "/hairwang/gangnamunni/";

const L = (ko: string, uz: string): Label => ({ ko, uz });
const legacy = (key: string, file: string, ko: string, uz: string, icon: IconName): MenuItem => ({
  key,
  label: L(ko, uz),
  icon,
  status: "legacy",
  href: `${LEGACY}${file}`,
});

/**
 * The whole sidebar and the only place that lists pages: same groups and names as the old panel for
 * a super admin. Moving a page here means changing its item to status "new" with an in-app href.
 */
export const MENU: MenuGroup[] = [
  {
    key: "home",
    label: L("대시보드", "Boshqaruv paneli"),
    icon: "gauge",
    items: [
      { key: "dashboard", label: L("대시보드", "Boshqaruv paneli"), icon: "gauge", status: "new", href: "/" },
      legacy("sns_reviews", "admin_sns_reviews.php", "SNS / 앱스토어 심사 관리", "SNS / App Store tekshiruvi", "phone"),
      legacy("menu_guide", "admin_menu_guide.php", "메뉴 안내", "Menyu qo'llanmasi", "info"),
      legacy("menu_manager", "admin_menu_manager.php", "메뉴 관리", "Menyuni boshqarish", "gear"),
    ],
  },
  {
    key: "hospital_register",
    label: L("병원 등록", "Klinika ro'yxati"),
    icon: "hospital",
    items: [
      legacy("hospital_apply", "hospital_apply.php", "병원 등록 신청", "Klinika ro'yxatga olish arizasi", "clipboard"),
      legacy("hospital_apply_status", "hospital_apply_status.php", "내 신청 현황", "Mening arizalarim holati", "check"),
    ],
  },
  {
    key: "content",
    label: L("콘텐츠 관리", "Kontentni boshqarish"),
    icon: "collection",
    items: [
      legacy("content_hub", "admin_content_hub.php", "콘텐츠 허브", "Kontent markazi", "collection"),
      legacy("recommend", "admin_recommend.php", "메인 추천 병원", "Bosh sahifadagi tavsiya klinikalar", "star"),
      legacy("hospitals", "admin_hospitals.php", "병원 관리", "Klinikalar", "hospital"),
      legacy("hospital_request_review", "admin_requested_hospitals.php", "입점 신청 병원", "Joylashish so'ragan klinikalar", "clipboard"),
      legacy("hospital_locations", "admin_hospital_locations.php", "병원 노출 순서", "Klinikalar tartibi", "chart"),
      legacy("doctors", "admin_doctors.php", "의사 관리", "Shifokorlar", "people"),
      legacy("personnel", "admin_personnel.php", "인력 관리", "Xodimlar", "people"),
      legacy("community_moderation", "admin_community_posts.php", "커뮤니티 심사", "Hamjamiyat postlarini tekshirish", "shield"),
      legacy("reviews", "admin_reviews.php", "리뷰 관리", "Sharhlar", "star"),
      legacy("hospital_images", "admin_hospital_images.php", "병원 이미지 관리", "Klinika rasmlari", "image"),
      legacy("ads", "admin_ads.php", "광고 관리", "Reklamalar", "megaphone"),
      legacy("categories", "admin_categories.php", "카테고리 관리", "Kategoriyalar", "tag"),
      legacy("events", "admin_events.php", "이벤트 관리", "Tadbirlar", "calendar"),
      legacy("columns", "admin_columns.php", "칼럼/뉴스 관리", "Maqola / yangiliklar", "sheet"),
    ],
  },
  {
    key: "ad_management",
    label: L("광고 관리", "Reklama boshqaruvi"),
    icon: "megaphone",
    items: [
      legacy("ad_pricing", "admin_ad_pricing.php", "광고 단가 관리", "Reklama narxlari", "coin"),
      legacy("ad_slots", "admin_ad_slots.php", "광고 슬롯 관리", "Reklama o'rinlari", "collection"),
      legacy("ad_stats", "admin_ad_stats.php", "광고 통계", "Reklama statistikasi", "chart"),
      legacy("advertisers", "admin_advertisers.php", "광고주 관리", "Reklama beruvchilar", "building"),
      legacy("ad_settings", "admin_ad_settings.php", "광고·디스플레이 설정", "Reklama va ko'rinish sozlamalari", "gear"),
      legacy("promos", "admin_promos.php", "프로모션 관리", "Aksiyalar", "tag"),
    ],
  },
  {
    key: "wizard",
    label: L("신청 관리", "Arizalarni boshqarish"),
    icon: "clipboard",
    items: [
      legacy("hospital_wizard", "admin_hospital_wizard.php", "병원 등록 마법사", "Klinika qo'shish ustasi", "hospital"),
      legacy("bookings", "admin_bookings.php", "예약 관리", "Bronlar", "calendar"),
      legacy("consultations", "admin_consultations.php", "상담 관리", "Konsultatsiyalar", "headset"),
    ],
  },
  {
    key: "register",
    label: L("등록 관리", "Ro'yxatga olish"),
    icon: "userPlus",
    items: [
      legacy("staff_wizard", "admin_staff_wizard.php", "직원 등록 마법사", "Xodim qo'shish ustasi", "userPlus"),
      legacy("appointment_wizard", "admin_appointment_wizard.php", "예약 등록 마법사", "Bron qo'shish ustasi", "calendar"),
    ],
  },
  {
    key: "treatment_mgmt",
    label: L("시술 관리", "Muolajalarni boshqarish"),
    icon: "heart",
    items: [
      legacy("treatment_units", "admin_treatment_units.php", "단위 관리", "O'lchov birliklari", "tag"),
      legacy("treatment_master", "admin_treatments.php", "시술 마스터 관리", "Muolajalar bosh ro'yxati", "heart"),
      legacy("hospital_treatments", "admin_hospital_treatments.php", "병원별 시술 배정 · 이미지 검수", "Klinikaga muolaja biriktirish va rasm tekshiruvi", "image"),
      legacy("doctor_treatments", "admin_doctor_treatments.php", "의사별 시술 전문도", "Shifokorlarning muolaja mahorati", "people"),
      legacy("treatment_packages", "admin_treatment_packages.php", "시술 패키지 관리", "Muolaja paketlari", "collection"),
    ],
  },
  {
    key: "excel",
    label: L("엑셀 관리", "Excel boshqaruvi"),
    icon: "sheet",
    items: [
      legacy("excel_import", "admin_excel_import_wizard.php", "엑셀 대량 등록", "Excel orqali ommaviy yuklash", "sheet"),
      legacy("excel_export", "admin_excel_export.php", "데이터 검색/다운로드", "Ma'lumot qidirish va yuklab olish", "sheet"),
    ],
  },
  {
    key: "members_group",
    label: L("회원 관리", "A'zolarni boshqarish"),
    icon: "people",
    items: [legacy("members", "admin_members.php", "회원 관리", "A'zolar", "people")],
  },
  {
    key: "report_block",
    label: L("신고/차단 관리", "Shikoyat va bloklash"),
    icon: "shield",
    items: [
      legacy("content_reports", "admin_content_reports.php", "신고 관리", "Shikoyatlar", "alert"),
      legacy("blocked_users", "admin_blocked_users.php", "차단 관리", "Bloklanganlar", "lock"),
      legacy("terms", "terms_of_service.php", "이용약관", "Foydalanish shartlari", "sheet"),
      legacy("guidelines", "community_guidelines.php", "커뮤니티 가이드라인", "Hamjamiyat qoidalari", "sheet"),
    ],
  },
  {
    key: "customer_service",
    label: L("고객 서비스", "Mijozlarga xizmat"),
    icon: "headset",
    items: [
      legacy("faq", "admin_faq.php", "FAQ 관리", "FAQ", "help"),
      legacy("qna", "admin_qna.php", "Q&A 관리", "Savol-javob", "help"),
      legacy("coupons", "admin_coupons.php", "쿠폰 관리", "Kuponlar", "tag"),
      legacy("points", "admin_points.php", "포인트 관리", "Ballar", "coin"),
      legacy("membership", "admin_membership.php", "회원등급 관리", "A'zolik darajalari", "star"),
      legacy("rankings", "admin_rankings.php", "병원 랭킹 관리", "Klinikalar reytingi", "chart"),
      legacy("hospital_news", "admin_hospital_news.php", "병원 소식 관리", "Klinika yangiliklari", "sheet"),
    ],
  },
  {
    key: "mailserver",
    label: L("메일 관리", "Pochta boshqaruvi"),
    icon: "mail",
    items: [
      legacy("admin_mail", "admin_mail.php", "메일 관리", "Pochta", "mail"),
      legacy("mail_templates", "admin_mail_templates.php", "상용구 관리", "Tayyor matnlar", "sheet"),
      legacy("mail_signatures", "admin_mail_signatures.php", "서명 관리", "Imzolar", "sheet"),
      legacy("mail_accounts", "admin_mail_accounts.php", "계정 설정", "Akkaunt sozlamalari", "gear"),
    ],
  },
  {
    key: "site_settings",
    label: L("사이트 설정", "Sayt sozlamalari"),
    icon: "gear",
    items: [
      legacy("site_settings_main", "admin_site_settings.php", "통합 설정", "Umumiy sozlamalar", "gear"),
      legacy("theme", "admin_theme.php", "테마 설정", "Mavzu sozlamalari", "gear"),
      legacy("theme_advanced", "admin_theme_advanced.php", "고급 테마 관리", "Mavzuning kengaytirilgan sozlamalari", "gear"),
      legacy("theme_wizard", "admin_theme_wizard.php", "테마 설정 마법사", "Mavzu sozlash ustasi", "gear"),
      legacy("design_mode", "admin_design_mode.php", "디자인 모드 설정", "Dizayn rejimi", "gear"),
      legacy("industry", "admin_industry.php", "업종 프리셋 전환", "Soha andozasini almashtirish", "gear"),
      legacy("map_settings", "admin_map_settings.php", "지도 API 설정", "Xarita API sozlamalari", "gear"),
      legacy("navigation", "admin_navigation.php", "네비게이션 관리", "Navigatsiya", "gear"),
      legacy("nav_settings", "admin_nav_settings.php", "하단 탭 설정 (실제 반영)", "Pastki tab sozlamalari (amalda ishlaydi)", "gear"),
      legacy("editor_settings", "admin_editor_settings.php", "에디터 설정", "Muharrir sozlamalari", "gear"),
      legacy("maintenance", "admin_maintenance.php", "점검 모드 관리", "Texnik ish rejimi", "gear"),
      legacy("upgrade", "admin_upgrade_plan.php", "확장 계획", "Kengaytirish rejasi", "gear"),
    ],
  },
  {
    key: "admin_notify_group",
    label: L("관리자 / 알림", "Adminlar va xabarnomalar"),
    icon: "shield",
    items: [
      legacy("admin_accounts", "admin_admins.php", "관리자 관리", "Adminlar", "shield"),
      legacy("admins", "admin_hospital_suspension.php", "병원 정지/요금 관리", "Klinikani to'xtatish va to'lovlar", "lock"),
      legacy("grade_permissions", "admin_grade_permissions.php", "등급별 기능 관리", "Daraja bo'yicha ruxsatlar", "check"),
      legacy("notifications", "admin_notifications.php", "Admin Center Alert", "Admin xabarnomalari", "alert"),
      legacy("send_notification", "admin_send_notification.php", "회원 푸시 발송", "A'zolarga push yuborish", "phone"),
    ],
  },
  {
    key: "cpv_group",
    label: L("CPV 과금 관리", "CPV hisob-kitobi"),
    icon: "coin",
    items: [
      legacy("cpv_settings", "admin_cpv_settings.php", "CPV 단가 설정", "CPV narx sozlamalari", "gear"),
      legacy("cpv_wallet", "admin_wallet.php", "지갑 관리", "Hamyonlar", "card"),
      legacy("cpv_rates", "admin_cpv_rates.php", "CPV 단가 규칙 관리", "CPV narx qoidalari", "coin"),
      legacy("cpv_dashboard", "admin_cpv_dashboard.php", "CPV 대시보드", "CPV paneli", "chart"),
      legacy("cpv_settlement", "admin_settlement.php", "CPV 정산 보고서", "CPV hisob-kitob hisoboti", "sheet"),
    ],
  },
  {
    key: "payment_group",
    label: L("결제 관리", "To'lovlar"),
    icon: "card",
    items: [legacy("booking_revenue", "admin_booking_revenue.php", "예약 결제 매출", "Bron to'lovlari tushumi", "card")],
  },
  {
    key: "app_download_group",
    label: L("앱 다운로드", "Ilovani yuklab olish"),
    icon: "phone",
    items: [
      legacy("app_patient", "admin_app_downloads.php?app=patient", "서울미녀", "Seoul Beauty", "phone"),
      legacy("app_admin", "admin_app_downloads.php?app=admin", "서울미녀 파트너", "Seoul Beauty Partner", "phone"),
    ],
  },
];
