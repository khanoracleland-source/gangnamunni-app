// Sample data of the bookings screen draft. Nothing here is read from or written to the server.

export const BOOKING_STATUSES = ["pending", "hospital_checking", "confirmed", "completed", "cancelled", "refunded", "no_show"] as const;
export type BookingStatus = (typeof BOOKING_STATUSES)[number];

export type MockBooking = {
  id: number;
  name: string;
  phone: string;
  hospitalId: number;
  treatment: string;
  date: string;
  time: string;
  photos: number;
  status: BookingStatus;
  doctor: string | null;
  memo: string;
  cancelReason: string;
  answers: { q: string; a: string }[];
  receivedAt: string;
};

export const MOCK_TODAY = "2026-11-20";
export const MOCK_HOSPITALS = [
  { id: 12, name: "서울미녀 강남점" },
  { id: 27, name: "청담 라인의원" },
  { id: 31, name: "압구정 더스킨" },
];
export const MOCK_DOCTORS: Record<number, string[]> = {
  12: ["김서연 원장", "박지훈 원장"],
  27: ["이도현 원장", "최유나 원장", "정민재 원장"],
  31: ["한지우 원장"],
};

const NAMES = ["김하늘", "이서윤", "박지민", "최수아", "정하은", "강예린", "조민서", "윤지우", "장서현", "임채원", "한소율", "오다은"];
const TREATMENTS = ["보톡스 (이마)", "필러 (팔자)", "리쥬란 힐러", "울쎄라 300샷", "레이저 토닝", "쌍꺼풀 매몰법", "코필러", "윤곽 주사"];
const STATUS_CYCLE: BookingStatus[] = ["pending", "hospital_checking", "confirmed", "completed", "cancelled", "hospital_checking", "confirmed", "pending", "refunded", "no_show"];

export const MOCK_BOOKINGS: MockBooking[] = Array.from({ length: 47 }, (_, i) => {
  const id = 190 - i;
  const hospitalId = MOCK_HOSPITALS[i % 3].id;
  const status = STATUS_CYCLE[i % STATUS_CYCLE.length];
  const day = 28 - (i % 20);
  const hasDoctor = status !== "pending" && status !== "hospital_checking" && i % 4 !== 1;
  return {
    id,
    name: NAMES[i % NAMES.length],
    phone: `010-${String(2000 + ((i * 137) % 7000)).padStart(4, "0")}-${String(1000 + ((i * 911) % 9000)).padStart(4, "0")}`,
    hospitalId,
    treatment: TREATMENTS[i % TREATMENTS.length],
    date: `2026-11-${String(day).padStart(2, "0")}`,
    time: `${String(10 + (i % 8)).padStart(2, "0")}:${i % 2 ? "30" : "00"}`,
    photos: i % 5 === 0 ? 3 : i % 3 === 0 ? 1 : 0,
    status,
    doctor: hasDoctor ? MOCK_DOCTORS[hospitalId][i % MOCK_DOCTORS[hospitalId].length] : null,
    memo: i % 4 === 0 ? "오후 시간대를 선호합니다. 상담 먼저 받고 싶어요." : "",
    cancelReason: status === "cancelled" ? "고객 일정 변경" : "",
    answers: [
      { q: "이전에 같은 시술을 받은 적이 있나요?", a: i % 2 ? "예" : "아니오" },
      { q: "복용 중인 약이나 알레르기가 있나요?", a: i % 3 ? "없음" : "항생제 알레르기" },
    ],
    receivedAt: `2026-11-${String(Math.max(1, 20 - (i % 15))).padStart(2, "0")} ${String(9 + (i % 10)).padStart(2, "0")}:${i % 2 ? "15" : "40"}`,
  };
});

export type Action = "checking" | "confirm" | "cancel" | "complete";
const NEXT: Record<string, Action[]> = {
  pending: ["checking"],
  hospital_checking: ["confirm", "cancel"],
  confirmed: ["complete"],
};
export const actionsFor = (status: BookingStatus): Action[] => NEXT[status] ?? [];
export const targetOf = (a: Action): BookingStatus => (a === "checking" ? "hospital_checking" : a === "confirm" ? "confirmed" : a === "complete" ? "completed" : "cancelled");

export type Filters = { hospitalId: number; status: BookingStatus | ""; from: string; to: string; q: string };
export const EMPTY_FILTERS: Filters = { hospitalId: 0, status: "", from: "", to: "", q: "" };
export const PAGE_SIZE = 20;

export function applyFilters(rows: MockBooking[], f: Filters, scopeHospital: number | null): MockBooking[] {
  const q = f.q.trim().toLowerCase();
  return rows
    .filter((r) => (scopeHospital ? r.hospitalId === scopeHospital : true))
    .filter((r) => (f.hospitalId ? r.hospitalId === f.hospitalId : true))
    .filter((r) => (f.status ? r.status === f.status : true))
    .filter((r) => (f.from ? r.date >= f.from : true))
    .filter((r) => (f.to ? r.date <= f.to : true))
    .filter((r) => (q ? r.name.toLowerCase().includes(q) || r.phone.replace(/-/g, "").includes(q.replace(/-/g, "")) : true))
    .sort((a, b) => (a.receivedAt < b.receivedAt ? 1 : a.receivedAt > b.receivedAt ? -1 : b.id - a.id));
}

export function countByStatus(rows: MockBooking[], hospitalId: number, scopeHospital: number | null) {
  const scoped = rows.filter((r) => (scopeHospital ? r.hospitalId === scopeHospital : true)).filter((r) => (hospitalId ? r.hospitalId === hospitalId : true));
  const by: Record<string, number> = {};
  for (const r of scoped) by[r.status] = (by[r.status] ?? 0) + 1;
  return { all: scoped.length, today: scoped.filter((r) => r.date === MOCK_TODAY).length, by };
}
