import Link from "next/link";

/** event_detail.php "이벤트를 찾을 수 없습니다" */
export default function NotFound() {
  return (
    <div style={{ textAlign: "center", padding: "80px 20px" }}>
      <div style={{ fontSize: 72, color: "#ccc", marginBottom: 16 }} aria-hidden>😕</div>
      <h2 style={{ fontSize: 20, fontWeight: 700, margin: "0 0 8px" }}>이벤트를 찾을 수 없습니다</h2>
      <p style={{ color: "#888", fontSize: 14, margin: "0 0 24px" }}>요청하신 이벤트가 존재하지 않거나 종료되었습니다.</p>
      <Link href="/events" style={{ display: "inline-block", padding: "10px 28px", background: "#ac1830", color: "#fff", borderRadius: 10, fontWeight: 600, fontSize: 14 }}>이벤트 목록으로</Link>
    </div>
  );
}
