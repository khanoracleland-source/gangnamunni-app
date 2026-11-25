import type { IconName } from "@/components/Icon";
import type { Features, Scope } from "./api";
import { PHP_BASE_URL } from "./config";
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
  /** A page of this panel that only a super admin can open (its API refuses everyone else). */
  superOnly?: boolean;
  /** Shown only to a super admin while the server has this switch on. */
  requires?: "archive" | "moderation" | "banners" | "categoryBoard";
};

export type MenuGroup = { key: string; label: Label; icon: IconName; items: MenuItem[] };

/** Where the old panel lives (same origin). */
const LEGACY = PHP_BASE_URL;

const L = (ko: string, en: string): Label => ({ ko, en });
const legacy = (key: string, file: string, ko: string, en: string, icon: IconName): MenuItem => ({
  key,
  label: L(ko, en),
  icon,
  status: "legacy",
  href: `${LEGACY}${file}`,
});

/**
 * The whole sidebar and the only place that lists pages: same groups and names as the old panel for
 * a super admin. Moving a page here means changing its item to status "new" with an in-app href.
 */
const MENU: MenuGroup[] = [
  {
    key: "home",
    label: L("대시보드", "Dashboard"),
    icon: "gauge",
    items: [
      { key: "dashboard", label: L("대시보드", "Dashboard"), icon: "gauge", status: "new", href: "/", superOnly: true },
    ],
  },
  {
    key: "hospital_register",
    label: L("병원 등록", "Hospital registration"),
    icon: "hospital",
    items: [
      { ...legacy("hospital_apply", "hospital_apply.php", "병원 등록 신청", "Hospital registration request", "clipboard"), superOnly: true },
    ],
  },
  {
    key: "content",
    label: L("콘텐츠 관리", "Content management"),
    icon: "collection",
    items: [
      legacy("recommend", "admin_recommend.php", "메인 추천 병원", "Recommended hospitals", "star"),
      legacy("hospitals", "admin_hospitals.php", "병원 관리", "Hospitals", "hospital"),
      { key: "hospital_archive", label: L("삭제 병원 보관함", "Archive"), icon: "collection", status: "new", href: "/hospital-archive", superOnly: true, requires: "archive" },
      legacy("doctors", "admin_doctors.php", "의사 관리", "Doctors", "people"),
      // 인력 관리(admin_personnel.php): 임시로 메뉴에서 숨김 — 다시 보이려면 아래 줄의 주석을 해제
      // legacy("personnel", "admin_personnel.php", "인력 관리", "Staff", "people"),
      { key: "content_review", label: L("콘텐츠 심사", "Content review"), icon: "shield", status: "new", href: "/moderation", requires: "moderation" },
      { key: "banners", label: L("배너 관리", "Banners"), icon: "image", status: "new", href: "/banners", superOnly: true, requires: "banners" },
      legacy("categories", "admin_categories.php", "카테고리 관리", "Categories", "tag"),
      legacy("events", "admin_events.php", "이벤트 관리", "Events", "calendar"),
      legacy("columns", "admin_columns.php", "칼럼/뉴스 관리", "Columns / news", "sheet"),
    ],
  },
  {
    key: "ad_management",
    label: L("광고 관리", "Ad management"),
    icon: "megaphone",
    items: [
      legacy("ad_pricing", "admin_ad_pricing.php", "광고 단가 관리", "Ad pricing", "coin"),
      legacy("ad_slots", "admin_ad_slots.php", "광고 슬롯 관리", "Ad slots", "collection"),
      legacy("ad_stats", "admin_ad_stats.php", "광고 통계", "Ad statistics", "chart"),
      legacy("advertisers", "admin_advertisers.php", "광고주 관리", "Advertisers", "building"),
      legacy("ad_settings", "admin_ad_settings.php", "광고·디스플레이 설정", "Ad and display settings", "gear"),
      legacy("promos", "admin_promos.php", "프로모션 관리", "Promotions", "tag"),
    ],
  },
  {
    key: "wizard",
    label: L("신청 관리", "Request management"),
    icon: "clipboard",
    items: [
      legacy("hospital_wizard", "admin_hospital_wizard.php", "병원 등록 마법사", "Add-hospital wizard", "hospital"),
      legacy("bookings", "admin_bookings.php", "예약 관리", "Bookings", "calendar"),
      legacy("consultations", "admin_consultations.php", "상담 관리", "Consultations", "headset"),
    ],
  },
  {
    key: "register",
    label: L("등록 관리", "Registration"),
    icon: "userPlus",
    items: [
      legacy("staff_wizard", "admin_staff_wizard.php", "직원 등록 마법사", "Add-staff wizard", "userPlus"),
      legacy("appointment_wizard", "admin_appointment_wizard.php", "예약 등록 마법사", "Add-booking wizard", "calendar"),
    ],
  },
  {
    key: "treatment_mgmt",
    label: L("시술 관리", "Treatment management"),
    icon: "heart",
    items: [
      { ...legacy("treatment_units", "admin_treatment_units.php", "단위 관리", "Units", "tag"), superOnly: true },
      // Becomes a page of this panel (super admin only) once the server has the treatment master switched on.
      { ...legacy("treatment_master", "admin_treatments.php", "시술 마스터 관리", "Treatment master list", "heart"), superOnly: true },
      { key: "home_categories", label: L("홈 카테고리 관리", "Home categories"), icon: "tag", status: "new", href: "/categories", superOnly: true, requires: "categoryBoard" },
      legacy("hospital_treatments", "admin_hospital_treatments.php", "병원별 시술 배정 · 이미지 검수", "Assign treatments to hospitals and review images", "image"),
      legacy("doctor_treatments", "admin_doctor_treatments.php", "의사별 시술 전문도", "Doctor treatment expertise", "people"),
      legacy("treatment_packages", "admin_treatment_packages.php", "시술 패키지 관리", "Treatment packages", "collection"),
    ],
  },
  {
    key: "excel",
    label: L("엑셀 관리", "Excel management"),
    icon: "sheet",
    items: [
      legacy("excel_import", "admin_excel_import_wizard.php", "엑셀 대량 등록", "Bulk upload via Excel", "sheet"),
      legacy("excel_export", "admin_excel_export.php", "데이터 검색/다운로드", "Search and download data", "sheet"),
    ],
  },
  {
    key: "members_group",
    label: L("회원 관리", "Member management"),
    icon: "people",
    items: [legacy("members", "admin_members.php", "회원 관리", "Members", "people")],
  },
  {
    key: "report_block",
    label: L("신고/차단 관리", "Reports and blocking"),
    icon: "shield",
    items: [
      legacy("content_reports", "admin_content_reports.php", "신고 관리", "Reports", "alert"),
      legacy("blocked_users", "admin_blocked_users.php", "차단 관리", "Blocked users", "lock"),
      legacy("terms", "terms_of_service.php", "이용약관", "Terms of service", "sheet"),
      legacy("guidelines", "community_guidelines.php", "커뮤니티 가이드라인", "Community guidelines", "sheet"),
    ],
  },
  {
    key: "customer_service",
    label: L("고객 서비스", "Customer service"),
    icon: "headset",
    items: [
      legacy("faq", "admin_faq.php", "FAQ 관리", "FAQ", "help"),
      legacy("qna", "admin_qna.php", "Q&A 관리", "Q&A", "help"),
      legacy("coupons", "admin_coupons.php", "쿠폰 관리", "Coupons", "tag"),
      legacy("points", "admin_points.php", "포인트 관리", "Points", "coin"),
      legacy("membership", "admin_membership.php", "회원등급 관리", "Membership levels", "star"),
      legacy("rankings", "admin_rankings.php", "병원 랭킹 관리", "Hospital rankings", "chart"),
      legacy("hospital_news", "admin_hospital_news.php", "병원 소식 관리", "Hospital news", "sheet"),
    ],
  },
  {
    key: "mailserver",
    label: L("메일 관리", "Mail management"),
    icon: "mail",
    items: [
      legacy("admin_mail", "admin_mail.php", "메일 관리", "Mail", "mail"),
      legacy("mail_templates", "admin_mail_templates.php", "상용구 관리", "Canned replies", "sheet"),
      legacy("mail_signatures", "admin_mail_signatures.php", "서명 관리", "Signatures", "sheet"),
      legacy("mail_accounts", "admin_mail_accounts.php", "계정 설정", "Account settings", "gear"),
    ],
  },
  {
    key: "site_settings",
    label: L("사이트 설정", "Site settings"),
    icon: "gear",
    items: [
      legacy("site_settings_main", "admin_site_settings.php", "통합 설정", "General settings", "gear"),
      legacy("theme", "admin_theme.php", "테마 설정", "Theme settings", "gear"),
      legacy("theme_advanced", "admin_theme_advanced.php", "고급 테마 관리", "Advanced theme settings", "gear"),
      legacy("theme_wizard", "admin_theme_wizard.php", "테마 설정 마법사", "Theme setup wizard", "gear"),
      legacy("design_mode", "admin_design_mode.php", "디자인 모드 설정", "Design mode", "gear"),
      legacy("industry", "admin_industry.php", "업종 프리셋 전환", "Switch industry preset", "gear"),
      legacy("map_settings", "admin_map_settings.php", "지도 API 설정", "Map API settings", "gear"),
      legacy("navigation", "admin_navigation.php", "네비게이션 관리", "Navigation", "gear"),
      legacy("nav_settings", "admin_nav_settings.php", "하단 탭 설정 (실제 반영)", "Bottom tab settings (applied live)", "gear"),
      legacy("editor_settings", "admin_editor_settings.php", "에디터 설정", "Editor settings", "gear"),
      legacy("maintenance", "admin_maintenance.php", "점검 모드 관리", "Maintenance mode", "gear"),
      legacy("upgrade", "admin_upgrade_plan.php", "확장 계획", "Expansion plan", "gear"),
    ],
  },
  {
    key: "admin_notify_group",
    label: L("관리자 / 알림", "Admins and notifications"),
    icon: "shield",
    items: [
      legacy("admin_accounts", "admin_admins.php", "관리자 관리", "Admins", "shield"),
      legacy("admins", "admin_hospital_suspension.php", "병원 정지/요금 관리", "Hospital suspension and fees", "lock"),
      legacy("grade_permissions", "admin_grade_permissions.php", "등급별 기능 관리", "Permissions by level", "check"),
      legacy("notifications", "admin_notifications.php", "Admin Center Alert", "Admin Center Alert", "alert"),
      legacy("send_notification", "admin_send_notification.php", "회원 푸시 발송", "Send push to members", "phone"),
    ],
  },
  {
    key: "cpv_group",
    label: L("CPV 과금 관리", "CPV billing"),
    icon: "coin",
    items: [
      legacy("cpv_settings", "admin_cpv_settings.php", "CPV 단가 설정", "CPV price settings", "gear"),
      legacy("cpv_wallet", "admin_wallet.php", "지갑 관리", "Wallets", "card"),
      legacy("cpv_rates", "admin_cpv_rates.php", "CPV 단가 규칙 관리", "CPV price rules", "coin"),
      legacy("cpv_dashboard", "admin_cpv_dashboard.php", "CPV 대시보드", "CPV dashboard", "chart"),
      legacy("cpv_settlement", "admin_settlement.php", "CPV 정산 보고서", "CPV settlement report", "sheet"),
    ],
  },
  {
    key: "payment_group",
    label: L("결제 관리", "Payments"),
    icon: "card",
    items: [legacy("booking_revenue", "admin_booking_revenue.php", "예약 결제", "Booking payments", "card")],
  },
  {
    key: "app_download_group",
    label: L("앱 다운로드", "App download"),
    icon: "phone",
    items: [
      legacy("app_patient", "admin_app_downloads.php?app=patient", "서울미녀", "Seoul Beauty", "phone"),
      legacy("app_admin", "admin_app_downloads.php?app=admin", "서울미녀 파트너", "Seoul Beauty Partner", "phone"),
    ],
  },
];

/** Pages that exist in this panel once the server has switched them on: menu key -> route (without basePath). */
const FEATURE_ROUTES: Record<"hospitals" | "doctors" | "treatments" | "treatmentUnits" | "hospitalApply" | "admins" | "bookings" | "payments", { key: string; href: string }> = {
  hospitals: { key: "hospitals", href: "/hospitals" },
  doctors: { key: "doctors", href: "/doctors" },
  hospitalApply: { key: "hospital_apply", href: "/hospital-apply" },
  treatments: { key: "treatment_master", href: "/treatments" },
  treatmentUnits: { key: "treatment_units", href: "/treatment-units" },
  admins: { key: "admin_accounts", href: "/admins" },
  bookings: { key: "bookings", href: "/bookings" },
  payments: { key: "booking_revenue", href: "/payments" },
};

/**
 * The sidebar for one viewer.
 * - A page behind a feature switch is "new" only while the switch is on; otherwise it stays a link to the old panel.
 * - scope "own" (hospital accounts): only the pages of this panel they can open. Everything else is reached
 *   through the single "old admin" link, where the old panel applies its own permissions.
 * Before the viewer is known (features and scope are null) the menu is the full one with every switch off.
 */
export function buildMenu(features: Features | null, scope: Scope | null): MenuGroup[] {
  const routes = new Map<string, string>();
  if (features) {
    for (const name of Object.keys(FEATURE_ROUTES) as (keyof typeof FEATURE_ROUTES)[]) {
      if (features[name]) routes.set(FEATURE_ROUTES[name].key, FEATURE_ROUTES[name].href);
    }
  }
  const showArchive = features?.archive === true && scope === "all";
  const showModeration = features?.moderation === true;
  const showBanners = features?.banners === true && scope === "all";
  const showCategoryBoard = features?.categoryBoard === true && scope === "all";
  const groups = MENU.map((group) => ({
    ...group,
    items: group.items.filter((item) => (item.requires !== "archive" || showArchive) && (item.requires !== "moderation" || showModeration) && (item.requires !== "banners" || showBanners) && (item.requires !== "categoryBoard" || showCategoryBoard)).map((item): MenuItem => {
      const href = routes.get(item.key);
      return href ? { ...item, status: "new", href } : item;
    }),
  }));
  if (scope !== "own") return groups;
  return groups
    .map((group) => ({ ...group, items: group.items.filter((item) => item.status === "new" && !item.superOnly) }))
    .filter((group) => group.items.length > 0);
}

/** Is `pathname` (basePath already removed, as usePathname returns it) the page `href` or one below it? */
export function isActiveRoute(pathname: string, href: string): boolean {
  const path = pathname.length > 1 ? pathname.replace(/\/+$/, "") : pathname;
  if (href === "/") return path === "/";
  return path === href || path.startsWith(`${href}/`);
}

/** Where the brand link and "home" lead: hospital accounts have no dashboard. */
export const homeRoute = (scope: Scope | null): string => (scope === "own" ? "/hospitals" : "/");
