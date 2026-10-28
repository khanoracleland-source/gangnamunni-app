import Link from "next/link";

/** detail_community.php: "공개된 게시글을 찾을 수 없습니다." */
export default function NotFound() {
  return (
    <div style={{ textAlign: "center", padding: "80px 20px" }}>
      <div style={{ fontSize: 72, color: "#ccc", marginBottom: 16 }} aria-hidden>😕</div>
      <h2 style={{ fontSize: 20, fontWeight: 700, margin: "0 0 8px" }}>공개된 게시글을 찾을 수 없습니다</h2>
      <p style={{ color: "#888", fontSize: 14, margin: "0 0 24px" }}>삭제되었거나 볼 수 없는 게시글입니다.</p>
      <Link href="/community" style={{ display: "inline-block", padding: "10px 28px", background: "#ac1830", color: "#fff", borderRadius: 10, fontWeight: 600, fontSize: 14 }}>커뮤니티로</Link>
    </div>
  );
}
