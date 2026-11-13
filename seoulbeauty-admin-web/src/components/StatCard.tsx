import type { ReactNode } from "react";
import { isLongValue } from "@/lib/format";

/** Left stripe colours, in the order the cards of a row use them. */
const STRIPES = ["#667eea", "#f093fb", "#fa709a", "#4facfe", "#43e97b"];

export function stripeColor(index: number): string {
  return STRIPES[index % STRIPES.length];
}

type Props = {
  label: string;
  /** Already formatted, e.g. "12,345,678". */
  value: string;
  /** "원", "건", "명" ... drawn smaller, right after the value. */
  unit?: string;
  /** Bottom line: a caption or the change against the previous period. */
  note?: ReactNode;
  /** Position inside the row: picks the stripe colour. */
  index: number;
};

export default function StatCard({ label, value, unit, note, index }: Props) {
  const long = isLongValue(value + (unit ?? ""));
  return (
    <div className="stat-card">
      <span className="stat-card__stripe" style={{ background: stripeColor(index) }} aria-hidden="true" />
      <div className="stat-card__body">
        <div className="stat-card__label">{label}</div>
        <div className={long ? "stat-card__value is-long" : "stat-card__value"}>
          {value}
          {unit ? <span className="stat-card__unit">{unit}</span> : null}
        </div>
        {note ? <div className="stat-card__note">{note}</div> : null}
      </div>
    </div>
  );
}

/** Placeholder with the same box as a real card, shown until the first numbers arrive. */
export function StatCardSkeleton({ index }: { index: number }) {
  return (
    <div className="stat-card" aria-hidden="true">
      <span className="stat-card__stripe" style={{ background: stripeColor(index) }} />
      <div className="stat-card__body">
        <div className="stat-card__label"><span className="skeleton" style={{ width: "45%" }} /></div>
        <div className="stat-card__value"><span className="skeleton" style={{ width: "60%" }} /></div>
        <div className="stat-card__note"><span className="skeleton" style={{ width: "70%" }} /></div>
      </div>
    </div>
  );
}
