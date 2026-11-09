import styles from "./hospitalCardClasses";

/** `rounded`: the placeholder rows take the bordered card shape (hospital browse list), so nothing jumps when the real cards arrive. */
export default function ListSkeleton({ rows = 6, rounded = false }: { rows?: number; rounded?: boolean }) {
  return (
    <div style={{ padding: rounded ? "36px 0 0" : "48px 20px 0" }} aria-busy="true" aria-label="불러오는 중">
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} className={`${styles.card} ${rounded ? styles.rounded : ""}`}>
          <div className={`${styles.thumb} skeleton`} />
          <div className={styles.info}>
            <div className="skeleton" style={{ height: 18, width: "60%", marginBottom: 8 }} />
            <div className="skeleton" style={{ height: 14, width: "85%", marginBottom: 8 }} />
            <div className="skeleton" style={{ height: 14, width: "40%" }} />
          </div>
        </div>
      ))}
    </div>
  );
}
