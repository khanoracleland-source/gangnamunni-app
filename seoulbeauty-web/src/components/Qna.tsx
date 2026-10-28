import SupportHeader from "./SupportHeader";
import styles from "./Support.module.css";

/** qna.php: the Q&A service is switched off in PHP ($qna_service_enabled = false), so this is the same "coming soon" notice. */
export default function Qna() {
  return (
    <div className={styles.wrap}>
      <SupportHeader title="Q&A" />
      <div className={styles.qnaEmpty}>
        <span className={styles.qnaIcon} aria-hidden="true">🛠</span>
        <strong className={styles.qnaTitle}>1:1 문의 기능을 준비하고 있습니다</strong>
        서비스 오픈 후 다시 이용해 주세요.
      </div>
    </div>
  );
}
