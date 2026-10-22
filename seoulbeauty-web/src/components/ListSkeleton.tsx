import styles from "./HospitalCard.module.css";

export default function ListSkeleton({ rows = 6 }: { rows?: number }) {
  return (
    <div style={{ padding: "48px 20px 0" }} aria-busy="true" aria-label="불러오는 중">
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} className={styles.card}>
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
