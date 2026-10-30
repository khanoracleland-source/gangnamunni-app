import Link from "next/link";
import type { PointsPolicy } from "@/content/legal";
import SupportHeader from "./SupportHeader";
import styles from "./Legal.module.css";

/** my_points_policy_detail.php?type=: one earn type — amount, facts and the rule sections. */
export default function PointsPolicyDetail({ policy }: { policy: PointsPolicy }) {
  return (
    <div className={styles.wrap}>
      <SupportHeader title={policy.title} fallback="/mypage" />
      <div className={styles.point}><div className={styles.k}>적립 포인트</div><div className={styles.v}>{policy.point}</div></div>
      <div className={styles.facts}>
        {policy.facts.map(([k, v]) => <div key={k} className={styles.fact}><span>{k}</span><b>{v}</b></div>)}
      </div>
      {policy.sections.map(([title, items]) => (
        <section key={title} className={styles.sec}>
          <h2>{title}</h2>
          <ul>{items.map((li) => <li key={li}>{li}</li>)}</ul>
        </section>
      ))}
      <p className={styles.notice}>적립 금액과 기준은 사전 안내 없이 변경될 수 있습니다.</p>
      <Link className={styles.linkRow} href="/points-policy"><span>포인트 정책 전체 보기</span><span aria-hidden="true">›</span></Link>
    </div>
  );
}
