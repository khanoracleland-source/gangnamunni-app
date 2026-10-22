export default function DetailSkeleton() {
  return (
    <div aria-busy="true" aria-label="불러오는 중">
      <div style={{ height: 48, borderBottom: "1px solid #ddd" }} />
      <div className="skeleton" style={{ aspectRatio: "16/9", borderRadius: 0 }} />
      <div style={{ padding: "16px 20px" }}>
        <div className="skeleton" style={{ height: 22, width: "55%", marginBottom: 10 }} />
        <div className="skeleton" style={{ height: 14, width: "80%", marginBottom: 8 }} />
        <div className="skeleton" style={{ height: 14, width: "45%" }} />
      </div>
    </div>
  );
}
