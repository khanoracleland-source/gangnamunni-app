/** Content-shaped placeholder for a screen that is still loading. Invisible for the first ~120ms (see .skeleton), so a cached/fast answer never flashes it. */
export default function Loading({ rows = 4 }: { rows?: number }) {
  return (
    <div role="status" aria-busy="true" aria-label="불러오는 중" style={{ width: "100%", padding: "8px 20px", textAlign: "left" }}>
      <div className="skeleton" style={{ height: 20, width: "45%", marginBottom: 18 }} />
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} className="skeleton" style={{ height: 14, width: `${[92, 78, 86, 60, 70][i % 5]}%`, marginBottom: 12 }} />
      ))}
    </div>
  );
}
