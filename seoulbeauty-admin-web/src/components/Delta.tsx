import { changeDirection, formatPercent, percentChange } from "@/lib/format";

const MARK = { up: "▲", down: "▼", flat: "―", none: "" } as const;
const SPOKEN = { up: "증가", down: "감소", flat: "변동 없음", none: "" } as const;

/**
 * "이전 7일 대비 ▲ 12.3%". Direction is told three ways (arrow, colour, hidden word),
 * so it does not depend on colour alone. Nothing to compare with -> "—".
 */
export default function Delta({ current, previous, days }: { current: number; previous: number; days: number }) {
  const change = percentChange(current, previous);
  const direction = changeDirection(change);
  const prefix = `이전 ${days}일 대비 `;
  if (direction === "none") {
    return (
      <>
        {prefix}
        <span className="delta" title="이전 기간 데이터가 없어 비교할 수 없습니다">
          <span aria-hidden="true">—</span>
          <span className="sr-only">비교 불가</span>
        </span>
      </>
    );
  }
  return (
    <>
      {prefix}
      <span className={`delta delta--${direction}`}>
        <span aria-hidden="true">{MARK[direction]} </span>
        {formatPercent(change)}
        <span className="sr-only"> {SPOKEN[direction]}</span>
      </span>
    </>
  );
}
