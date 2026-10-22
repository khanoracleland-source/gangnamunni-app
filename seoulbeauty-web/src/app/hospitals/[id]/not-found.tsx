import Link from "next/link";

export default function NotFound() {
  return (
    <div style={{ textAlign: "center", padding: "80px 20px", color: "#999" }}>
      <p>병원을 찾을 수 없습니다.</p>
      <Link href="/hospitals" style={{ color: "#ac1830", textDecoration: "underline" }}>병원 목록으로</Link>
    </div>
  );
}
