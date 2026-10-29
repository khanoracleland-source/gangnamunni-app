import { GUIDELINES_HTML } from "@/content/legal";
import LegalDoc from "./LegalDoc";
import SupportHeader from "./SupportHeader";
import styles from "./Legal.module.css";

/** community_guidelines.php */
export default function Guidelines() {
  return (
    <div className={styles.wrap}>
      <SupportHeader title="커뮤니티 가이드라인" fallback="/community" />
      <div className={styles.body}><LegalDoc html={GUIDELINES_HTML} /></div>
    </div>
  );
}
