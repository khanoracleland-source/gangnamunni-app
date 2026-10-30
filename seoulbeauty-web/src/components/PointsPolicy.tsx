"use client";
import { useState } from "react";
import Link from "next/link";
import { POINTS_POLICY } from "@/content/legal";
import SupportHeader from "./SupportHeader";
import styles from "./Points.module.css";

type Item = { name: string; value: string; text?: string; href?: string };
type Pane = { tab: string; title: string; desc: string; sub: string; items: Item[] };

/** my_points_policy.php: the $POLICY array (earn items come from the shared policy content; 소멸 uses the configured days). */
export function policyPanes(expireDays: number): Record<string, Pane> {
  return {
    earn: {
      tab: "적립", title: "포인트 적립",
      desc: "후기 작성, 앱 결제 등 상황에 따라 포인트가 적립됩니다. 적립 기준과 상황은 별도 고지 없이 추가·변경될 수 있습니다.",
      sub: "적립 유형별 세부 정책",
      items: Object.entries(POINTS_POLICY).map(([k, e]) => ({ name: e.label, value: e.summary, href: `/points-policy/${k}` })),
    },
    revoke: {
      tab: "회수", title: "포인트 회수", desc: "적립 사유가 취소되면 해당 건으로 적립된 포인트가 회수됩니다.", sub: "회수 사유",
      items: [
        { name: "후기 삭제·거부", value: "적립 금액 전액", text: "포인트가 적립된 후기를 삭제하거나, 운영 정책 위반으로 후기가 거부되면 해당 후기로 적립된 포인트가 회수됩니다." },
        { name: "예약 취소", value: "적립 금액 전액", text: "포인트가 적립된 예약이 취소되면 해당 예약으로 적립된 포인트가 회수됩니다." },
      ],
    },
    expire: {
      tab: "소멸", title: "포인트 소멸", desc: "사용 기한이 지난 포인트는 자동으로 소멸되며, 소멸된 포인트는 복구되지 않습니다.", sub: "소멸 기준",
      items: [
        { name: "사용 기한", value: expireDays > 0 ? `적립일로부터 ${expireDays.toLocaleString("ko-KR")}일` : "적립건별로 상이", text: "포인트는 적립건마다 사용 기한이 있으며, 기한이 지나면 남은 포인트가 소멸됩니다. 건별 기한은 포인트 내역에서 확인할 수 있습니다." },
        { name: "소멸 순서", value: "먼저 적립된 순", text: "사용 기한이 먼저 도래하는 포인트부터 소멸됩니다." },
      ],
    },
    use: {
      tab: "사용", title: "포인트 사용", desc: "보유 포인트는 앱 결제 시 현금처럼 사용할 수 있습니다.", sub: "사용 기준",
      items: [
        { name: "사용 단위", value: "1P = 1원", text: "결제 금액에서 사용한 포인트만큼 차감됩니다." },
        { name: "사용 순서", value: "기한 임박 순", text: "사용 기한이 먼저 도래하는 포인트부터 사용됩니다." },
      ],
    },
    refund: {
      tab: "환불", title: "포인트 환불", desc: "포인트를 사용한 결제가 취소되면 사용한 포인트가 다시 적립됩니다.", sub: "환불 기준",
      items: [
        { name: "결제 취소", value: "사용 포인트 복구", text: "결제 취소 시 사용한 포인트가 보유 포인트로 복구됩니다. 단, 사용 기한이 지난 포인트는 복구 즉시 소멸될 수 있습니다." },
        { name: "현금 환불", value: "불가", text: "포인트는 현금으로 환불되지 않습니다." },
      ],
    },
  };
}

export default function PointsPolicy({ expireDays, initialTab }: { expireDays: number; initialTab: string }) {
  const panes = policyPanes(expireDays);
  const [tab, setTab] = useState(Object.hasOwn(panes, initialTab) ? initialTab : "earn");
  const pick = (k: string) => { setTab(k); try { window.history.replaceState(null, "", `?tab=${k}`); } catch { /* ignore */ } };
  const p = panes[tab];
  return (
    <div className={styles.wrap}>
      <SupportHeader title="포인트 정책" fallback="/mypage/points" />
      <div className={styles.ptabs} role="tablist">
        {Object.entries(panes).map(([k, v]) => <button key={k} type="button" role="tab" aria-selected={tab === k} className={`${styles.ptab} ${tab === k ? styles.ptabOn : ""}`} onClick={() => pick(k)}>{v.tab}</button>)}
      </div>
      <div className={styles.band} />
      <section className={styles.pane} role="tabpanel">
        <h2>{p.title}</h2>
        <p className={styles.desc}>{p.desc}</p>
        <h3>{p.sub}</h3>
        <div className={styles.rows}>
          {p.items.map((it) => it.href ? (
            <div key={it.name}><Link className={styles.prowLink} href={it.href}><span>{it.name}</span><span className={styles.pv}>{it.value}</span><span className={styles.chev} aria-hidden="true">›</span></Link></div>
          ) : (
            <details key={it.name} className={styles.prow}>
              <summary><span>{it.name}</span><span className={styles.pv}>{it.value}</span><span className={styles.chev} aria-hidden="true">›</span></summary>
              <p>{it.text}</p>
            </details>
          ))}
        </div>
        <div className={styles.pfacts}>
          <div className={styles.pfact}><span>충전·출금</span><span>불가</span></div>
          <div className={styles.pfact}><span>사용 기한</span><span>적립건별로 상이</span></div>
          <p className={styles.pnote}>적립건별 사용 기한은 포인트 내역에서 확인할 수 있습니다. 그 외 적립 방법은 고객센터로 문의해 주세요.</p>
        </div>
      </section>
      <div className={styles.band} />
      <Link className={styles.linkRow} href="/qna">포인트 문의하기<span aria-hidden="true">›</span></Link>
    </div>
  );
}
