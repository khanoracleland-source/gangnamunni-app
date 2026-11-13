"use client";
import Loading from "./Loading";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, qk, type BookingChangeInfo, type BookingContext, type BookingOption, type BookingPackage, type BookingRefusal, type BookingRequest, type NoshowInfo } from "@/lib/api";
import { bookableDays, dday, formatDateTime, koTopic, parseDay, priceRange, timeSlots, won, WEEKDAYS } from "@/lib/booking";
import { BASE_PATH } from "@/lib/phpLink";
import BottomSheet, { type BottomSheetHandle } from "./BottomSheet";
import LoginPrompt from "./LoginPrompt";
import styles from "./Booking.module.css";

export interface BookingParams {
  id: number; treatmentId: number; optionId: number; treatmentName: string; rebook: boolean; via: string; sourceId: number;
  /** booking_change.php: the booking being rescheduled (its hospital and treatment come from the API). */
  change?: number;
  /** Opened from the hospital page: back returns there with its tab as it was (history), not to a fresh page. */
  fromHospital?: boolean;
}

const CloseIcon = () => <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>;
const BackIcon = () => <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg>;

/**
 * Leaving the booking page for where it was opened from. Opened from the hospital page (`from=hospital`, or the referrer is that hospital's page,
 * e.g. the app's native/PHP entry): pop the history, so the hospital page comes back with its tab, scroll and the list's sort behind it.
 * Any other entry: `replace` the destination in, so the booking page never stays in the history for a later 뒤로 to land on.
 */
function useLeave(p: BookingParams, back: string) {
  const router = useRouter();
  return () => {
    let fromHospital = !!p.fromHospital;
    if (!fromHospital && p.id > 0) {
      try {
        const r = new URL(document.referrer);
        fromHospital = new RegExp(`/hospitals/${p.id}/?$`).test(r.pathname) || (/\/detail\.php$/.test(r.pathname) && r.searchParams.get("id") === String(p.id));
      } catch { /* no referrer */ }
    }
    if (fromHospital && !p.change && window.history.length > 1) router.back();
    else router.replace(back);
  };
}

/** 예약 선택 (design 05-09 → 05-10 → 05-11; refusals 05-12 and the other sheets): choose treatments, a day and a time, answer the clinic's questions, confirm. */
export default function Booking(p: BookingParams) {
  const changeId = p.change ?? 0;
  const info = useQuery({ queryKey: qk.bookingChangeInfo(changeId), queryFn: () => api.bookingChangeInfo(changeId), enabled: changeId > 0, staleTime: 0, gcTime: 0, retry: false, refetchOnReconnect: false, refetchOnWindowFocus: false });
  const hospitalId = changeId ? info.data?.hospitalId ?? 0 : p.id;
  const { data, error, refetch } = useQuery({ queryKey: qk.bookingContext(hospitalId), queryFn: () => api.bookingContext(hospitalId), enabled: hospitalId > 0, staleTime: 0, gcTime: 0, retry: false, refetchOnReconnect: false, refetchOnWindowFocus: false }); // a refetch (reconnect, app back to foreground) must not reset what the member chose
  const title = changeId ? "예약 변경" : p.rebook && p.via !== "again" ? "다시 예약" : "예약 선택";
  const back = changeId ? "/mypage/bookings" : p.rebook && p.sourceId > 0 ? `/mypage/bookings/${p.sourceId}` : `/hospitals/${p.id}`;
  const leave = useLeave(p, back);
  const err = info.error ?? error;
  const loggedOut = err instanceof ApiError && err.status === 401;
  const params: BookingParams = changeId && info.data ? { ...p, id: info.data.hospitalId, treatmentName: info.data.treatment, rebook: true } : p;
  const body = loggedOut ? <LoginPrompt text="로그인하면 예약할 수 있습니다." />
    : info.error instanceof ApiError && (info.error.status === 404 || info.error.status === 409) ? <div className={styles.empty}>변경할 수 없는 예약입니다. <Link className={styles.retry} href="/mypage/bookings">내 예약으로</Link></div>
    : error instanceof ApiError && error.status === 404 ? <div className={styles.empty}>병원 정보를 찾을 수 없습니다.</div>
    : !data || (changeId > 0 && !info.data) ? <div className={styles.empty}>{err ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => { void info.refetch(); void refetch(); }}>다시 시도</button></> : <Loading />}</div>
    : null;
  if (body) {
    return (
      <div className={styles.root}>
        <PageHead title={title} back={back} onBack={leave} />
        {body}
      </div>
    );
  }
  return <BookingForm ctx={data!} p={params} title={title} back={back} leave={leave} change={changeId ? info.data : undefined} />;
}

/** 52px header shared by the three steps: back (44) · centred title 17/500 · 44px spacer. */
function PageHead({ title, back, onBack }: { title: string; back?: string; onBack?: () => void }) {
  return (
    <header className={styles.top}>
      {onBack ? <button type="button" className={styles.back} onClick={onBack} aria-label="뒤로"><BackIcon /></button> : <Link href={back ?? "/"} className={styles.back} aria-label="뒤로"><BackIcon /></Link>}
      <h1 className={styles.topTitle}>{title}</h1>
      <span className={styles.topSpacer} />
    </header>
  );
}

interface Picked { name: string; range: string; min_age: number; options: BookingOption[]; option_id: number }
type Stage = null | "pre" | "med" | "consent" | "confirm" | "success";

/** The bookable days only, seven per screen: starts at today (or the first open day), no past days and no days the hospital is closed. */
function weekGrid(days: string[]): string[][] {
  const weeks: string[][] = [];
  for (let i = 0; i < days.length; i += 7) weeks.push(days.slice(i, i + 7));
  return weeks;
}

function BookingForm({ ctx, p, title, back, leave, change }: { ctx: BookingContext; p: BookingParams; title: string; back: string; leave: () => void; change?: BookingChangeInfo }) {
  const qc = useQueryClient();
  const router = useRouter();
  const today = ctx.now.slice(0, 10);
  const days = bookableDays(ctx.hours, today);

  // the treatment chosen on the hospital page (by id, else by name) starts selected
  const initial = (): Picked[] => {
    const t = ctx.treatments.find((x) => (p.treatmentId > 0 ? x.treatment_id === p.treatmentId : p.treatmentName !== "" && x.name === p.treatmentName));
    if (!t) return [];
    // booking_change.php: the saved "시술명 · 옵션명" picks the option by its name, display name or dosage
    const byName = change?.optionName ? t.options.find((o) => [o.option_name, o.name, o.dosage].map((v) => v.trim()).includes(change.optionName)) : undefined;
    const byId = byName ?? t.options.find((o) => o.id === p.optionId);
    return [{ name: t.name, range: priceRange(t.price_min, t.price_max), min_age: t.min_age, options: t.options, option_id: byId ? byId.id : t.options.length === 1 ? t.options[0].id : 0 }];
  };
  const [picked, setPicked] = useState<Picked[]>(initial);
  const [pkg, setPkg] = useState<BookingPackage | null>(null);
  const [date, setDate] = useState(change && days.includes(change.date) ? change.date : days.includes(today) ? today : days[0] ?? today);
  const [time, setTime] = useState(change?.time ?? "");
  const [sheet, setSheet] = useState(false);
  const [optOpen, setOptOpen] = useState<string | null>(null);
  const [stage, setStage] = useState<Stage>(null);
  const [refusal, setRefusal] = useState<BookingRefusal | null>(null);
  const [answers, setAnswers] = useState<Record<string, 0 | 1>>({});
  const [flagged, setFlagged] = useState<string[]>([]);
  const [message, setMessage] = useState("");
  const [splitHint, setSplitHint] = useState("");
  const [busy, setBusy] = useState(false);
  const [phone, setPhone] = useState(ctx.member.phone);
  const [name, setName] = useState(ctx.member.name);
  const [showErr, setShowErr] = useState(false);
  const [confirmErr, setConfirmErr] = useState("");
  const [noshowOpen, setNoshowOpen] = useState<NoshowInfo | null>(change ? null : ctx.noshow);
  /** booking_change.php: new_treatment is sent only when the chosen names differ from what was chosen on entry (no package on entry). */
  const [origNames] = useState(() => (change ? picked.map((t) => t.name).join(", ") : null));
  const flags = useRef({ dup: false, rev: false });
  const asked = useRef<string | null>(null);
  const consented = useRef(false);
  const preOut = useRef<string>("[]");

  const { closed, slots } = timeSlots(ctx.hours, date, ctx.now);
  const optionsReady = picked.every((t) => t.options.length <= 1 || t.option_id > 0);
  const hasChoice = !!pkg || picked.length > 0;
  const ready = !!date && !!time && hasChoice && optionsReady;
  const complete = change ? !!date && !!time && optionsReady : ready;
  const pickedSummary = [...(pkg ? [`패키지 ${pkg.name}`] : []), ...picked.map((t) => t.name + (t.option_id ? ` · ${t.options.find((o) => o.id === t.option_id)?.name ?? ""}` : ""))].join(", ");

  const toggle = (name: string) => {
    setPicked((cur) => {
      if (cur.some((t) => t.name === name)) return cur.filter((t) => t.name !== name);
      if (cur.length >= 20) { setMessage("시술은 한 번에 최대 20개까지 선택할 수 있습니다."); return cur; }
      const t = ctx.treatments.find((x) => x.name === name);
      if (!t) return cur;
      return [...cur, { name: t.name, range: priceRange(t.price_min, t.price_max), min_age: t.min_age, options: t.options, option_id: t.options.length === 1 ? t.options[0].id : 0 }];
    });
  };
  const setOption = (name: string, id: number) => { setPicked((cur) => cur.map((t) => (t.name === name ? { ...t, option_id: id } : t))); setOptOpen(null); };

  const request = (): BookingRequest => ({
    hospital_id: ctx.hospital.id,
    ...(ctx.member.name ? {} : { name: name.trim() }),
    phone: phone.trim(), date, time,
    selection_type: pkg ? "package" : "treatments", package_id: pkg ? pkg.id : 0,
    treatments: picked.map((t) => ({ name: t.name, option_id: t.option_id })),
    pre_answers: preOut.current,
    confirm_duplicate: flags.current.dup ? "1" : "", confirm_revisit: flags.current.rev ? "1" : "",
  });

  /** Refusals the server answers with a code: each has its own sheet. Returns true when one was shown. */
  const refused = (e: unknown): boolean => {
    if (e instanceof ApiError && e.status === 422 && e.body?.code) { setRefusal(e.body as unknown as BookingRefusal); return true; }
    return false;
  };

  const questions = () => {
    const out: string[] = [];
    picked.forEach((t) => (ctx.preQuestions[t.name] ?? []).forEach((q) => { if (!out.includes(q)) out.push(q); }));
    return out;
  };
  const afterPre = () => { if (consented.current) setStage("confirm"); else setStage("consent"); };
  const afterPrecheck = () => {
    const qs = questions();
    if (!qs.length) { preOut.current = "[]"; afterPre(); return; }
    if (asked.current === JSON.stringify(qs)) { afterPre(); return; }
    setStage("pre");
  };
  const precheckThenConfirm = async () => {
    if (busy) return;
    setBusy(true);
    try {
      await api.bookingPrecheck(request());
    } catch (e) {
      if (refused(e)) { setBusy(false); return; }
      if (e instanceof ApiError && e.status === 401) { setMessage("로그인이 필요합니다. 다시 로그인해주세요."); setBusy(false); return; }
      // anything else (network): the final request checks everything again
    }
    setBusy(false);
    afterPrecheck();
  };

  const namesText = (pkg ? `[패키지] ${pkg.name}${picked.length ? ", " : ""}` : "") + picked.map((t) => t.name).join(", ");
  const submitChange = async () => {
    if (!change || busy) return;
    setBusy(true); setMessage("");
    try {
      await api.bookingChange(change.id, { date, time, ...(namesText && namesText !== origNames ? { treatment: namesText } : {}) });
      void qc.invalidateQueries({ queryKey: ["mypage"] });
      router.replace(`/mypage/bookings/${change.id}?changed=1`);
      return;
    } catch (e) {
      setMessage(e instanceof ApiError && e.status === 401 ? "로그인이 필요합니다. 다시 로그인해주세요." : e instanceof Error && e.message && !/^\d{3} /.test(e.message) ? e.message : "변경 처리 중 오류가 발생했습니다.");
    }
    setBusy(false);
  };
  const submit = () => {
    if (!complete) {
      setMessage(!change && !hasChoice ? "시술 또는 패키지를 선택해주세요." : !optionsReady ? "시술의 세부 옵션을 선택해주세요." : !date ? "날짜를 선택해주세요." : "시간을 선택해주세요.");
      return;
    }
    setMessage("");
    if (change) { void submitChange(); return; }
    void precheckThenConfirm();
  };

  const finish = async () => {
    if (busy) return;
    if (!phone.trim() || (!ctx.member.name && !name.trim())) { setShowErr(true); return; }
    setBusy(true); setConfirmErr("");
    try {
      await api.bookingCreate(request());
      void qc.invalidateQueries({ queryKey: ["mypage"] });
      setStage("success");
    } catch (e) {
      if (refused(e)) { setStage(null); flags.current.dup = false; }
      else setConfirmErr(e instanceof ApiError && e.status === 401 ? "로그인이 필요합니다. 다시 로그인해주세요." : e instanceof Error && e.message && !/^\d{3} /.test(e.message) ? e.message : "예약 중 오류가 발생했습니다. 다시 시도해주세요.");
    }
    setBusy(false);
  };

  const closeRefusal = () => setRefusal(null);
  const proceedFlag = (k: "dup" | "rev") => { flags.current[k] = true; setRefusal(null); void precheckThenConfirm(); };

  // 05-09 day strip: bookable days only, 7 cells per screen, snapping screen by screen; the month tabs scroll to the first screen that has a day of that month
  const weeks = weekGrid(days);
  const months = [...new Set(days.map((d) => d.slice(0, 7)))];
  const curMonth = date.slice(0, 7);
  const weekRef = useRef<HTMLDivElement>(null);
  const scrollToWeek = (i: number, behavior: ScrollBehavior) => {
    const el = weekRef.current;
    if (el) el.scrollTo({ left: el.clientWidth * i, behavior });
  };
  const jumpMonth = (m: string) => {
    const i = weeks.findIndex((w) => w.some((d) => d.slice(0, 7) === m));
    if (i >= 0) scrollToWeek(i, "smooth");
  };
  const weekOfDate = weeks.findIndex((w) => w.includes(date));
  useEffect(() => { if (weekOfDate > 0) scrollToWeek(weekOfDate, "auto"); }, []); // eslint-disable-line react-hooks/exhaustive-deps -- the initial week only (a changed booking's date can be weeks away)

  // ---- 05-10 이대로 예약을 진행할까요? (05-11 success sheet opens over it) ----
  if (stage === "confirm" || stage === "success") {
    return (
      <div className={styles.root}>
        <PageHead title="이대로 예약을 진행할까요?" onBack={() => { if (stage === "success") return; flags.current.dup = false; setStage(null); }} />
        <div className={styles.badgeRow}><span className={styles.badge}>시술예정<i className={styles.badgeBar} />D-{dday(date, today)}</span></div>
        <div className={styles.rows}>
          <div className={styles.row}><span className={styles.rowLabel}>예약번호</span><span className={styles.rowValue}>접수 후 발급</span></div>
          <div className={styles.row}><span className={styles.rowLabel}>날짜/시간</span><span className={styles.rowValue}>{formatDateTime(date, time)}</span></div>
          <div className={styles.row}><span className={styles.rowLabel}>선택시술</span><span className={styles.rowValue}>{pickedSummary}</span></div>
          <div className={styles.row}><span className={styles.rowLabel}>병원</span><span className={styles.rowValue}>{ctx.hospital.name}</span></div>
        </div>
        <div className={styles.person}>
          <div className={styles.personRow}>
            <span className={styles.personLabel}>예약자</span>
            {ctx.member.name ? <b>{ctx.member.name}</b>
              : <input className={styles.input} maxLength={100} placeholder="예약자명을 입력해주세요" autoComplete="name" value={name} onChange={(e) => setName(e.target.value)} aria-label="예약자명" aria-invalid={showErr && !name.trim()} />}
          </div>
          {showErr && !ctx.member.name && !name.trim() && <span className={styles.fieldErr} role="alert">예약자명을 입력해주세요.</span>}
          <div className={styles.personRow}>
            <label className={styles.personLabel} htmlFor="bk-phone">연락처</label>
            <input id="bk-phone" className={styles.input} type="tel" inputMode="tel" autoComplete="tel" maxLength={30} placeholder="연락처를 입력해주세요" value={phone} onChange={(e) => setPhone(e.target.value)} aria-invalid={showErr && !phone.trim()} />
          </div>
          {showErr && !phone.trim() && <span className={styles.fieldErr} role="alert">연락처를 입력해주세요.</span>}
          {!ctx.member.hasSavedPhone && (
            <p className={styles.phoneHelp}>매번 예약할 때마다 연락처를 입력하고 싶지 않으시면 <Link href="/mypage/profile">내 정보 수정</Link>에서 휴대폰 번호를 미리 등록해 두세요.</p>
          )}
        </div>
        <div className={styles.notice}>
          <div className={styles.noticeTitle}>고객님 꼭 알아두세요!</div>
          <ul>
            <li>예약 가능 여부는 병원 상황에 따라 달라질 수 있어요</li>
            <li>예약이 불가능한 경우, 상담원이 다른 일정으로 안내해드려요</li>
            <li>예약 확정 전까지는 결제가 진행되지 않아요</li>
            <li>결제는 예약이 가능하다고 확인된 후에 진행됩니다</li>
            <li>예약 현황 / 예약 변경 / 취소는 <em>내 예약 결제내역</em>에서 직접 관리할 수 있어요</li>
          </ul>
        </div>
        {confirmErr && <p className={styles.msg} role="alert">{confirmErr}</p>}
        <div className={styles.bottom}>
          <button type="button" className={styles.primary} disabled={busy} onClick={finish}>{busy ? "접수 중..." : "확인했어요"}</button>
        </div>
        {/* 05-11: over the confirm page until 확인했어요 / 내 예약 현황 */}
        {stage === "success" && <SuccessSheet onDone={leave} onList={() => router.replace("/mypage/bookings")} />}
      </div>
    );
  }

  if (stage === "pre") {
    return (
      <div className={styles.root}>
        <PageHead title="시술 전 확인" onBack={() => setStage(null)} />
        <PreCheck questions={questions()} answers={answers} setAnswers={setAnswers}
          onNext={() => {
            const qs = questions();
            const out = qs.filter((q) => answers[q] === 0 || answers[q] === 1).map((q) => ({ q, a: answers[q] }));
            preOut.current = JSON.stringify(out);
            asked.current = JSON.stringify(qs);
            const yes = out.filter((x) => x.a === 1).map((x) => x.q);
            if (yes.length) { setFlagged(yes); setStage("med"); } else afterPre();
          }} />
      </div>
    );
  }

  // ---- 05-09 예약 선택 (also behind the "med" / "consent" / "success" sheets) ----
  return (
    <div className={styles.root}>
      <PageHead title={title} back={back} onBack={leave} />
      <form onSubmit={(e) => { e.preventDefault(); submit(); }}>
        <div className={styles.headRow}>
          <span className={styles.headTitle}>예약을 진행해볼까요?</span>
          <button type="button" className={styles.pill} onClick={() => setSheet(true)}>옵션 변경</button>
        </div>
        <div className={styles.chosen}>
          {!hasChoice && <p className={styles.help}>{p.treatmentName || p.treatmentId ? "선택한 시술의 세부 옵션을 확인해주세요." : "원하는 시술 1개를 선택해주세요."}</p>}
          {pkg && (
            <div className={styles.entry}>
              <div className={styles.itemRow}>
                <span className={styles.itemName}>[패키지] {pkg.name}</span>
                <button type="button" className={styles.remove} onClick={() => setPkg(null)} aria-label={`${pkg.name} 삭제`}><CloseIcon /></button>
              </div>
              <div className={styles.itemPrice}>{won(pkg.package_price * 10000)}</div>
            </div>
          )}
          {picked.map((t) => {
            const sel = t.options.find((o) => o.id === t.option_id);
            const many = t.options.length > 1;
            const open = optOpen === t.name;
            return (
              <div key={t.name} className={styles.entry}>
                <div className={styles.itemRow}>
                  {many ? (
                    <button type="button" className={`${styles.itemName} ${styles.itemBtn}`} onClick={() => setOptOpen(open ? null : t.name)} aria-expanded={open}>
                      <span>{t.name}{sel ? ` ${sel.name}` : ""}{!sel && <span className={styles.itemHint}> 옵션 선택</span>}</span>
                      <svg className={`${styles.chev} ${open ? styles.chevUp : ""}`} width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
                    </button>
                  ) : (
                    <span className={styles.itemName}>{t.name}{sel && sel.name && sel.name !== t.name ? ` ${sel.name}` : ""}</span>
                  )}
                  <button type="button" className={styles.remove} onClick={() => toggle(t.name)} aria-label={`${t.name} 삭제`}><CloseIcon /></button>
                </div>
                <div className={styles.itemPrice}>{sel ? won(sel.price) : t.range}</div>
                {many && open && (
                  <div className={styles.optList} role="radiogroup" aria-label={`${t.name} 옵션`}>
                    {t.options.map((o) => (
                      <label key={o.id} className={`${styles.opt} ${t.option_id === o.id ? styles.optOn : ""}`}>
                        <input type="radio" name={`opt-${t.name}`} checked={t.option_id === o.id} onChange={() => setOption(t.name, o.id)} />
                        <span className={styles.optCopy}>
                          {(o.option_name || (!o.body_part && !o.dosage ? o.name : "")) && <span className={styles.optName}>{o.option_name || o.name}</span>}
                          {o.body_part && <span className={styles.optDose}>{o.body_part}</span>}
                          {o.dosage && <span className={styles.optDose}>{o.dosage}</span>}
                        </span>
                        <span className={styles.optPrice}>{won(o.price)}</span>
                      </label>
                    ))}
                  </div>
                )}
              </div>
            );
          })}
          {splitHint && <p className={styles.splitHint}>{splitHint}</p>}
        </div>

        <h2 className={styles.secTitle}>언제 방문하시겠어요?</h2>
        <div className={styles.months}>
          {months.map((m) => (
            <button type="button" key={m} className={`${styles.month} ${m === curMonth ? styles.monthOn : ""}`} aria-pressed={m === curMonth} onClick={() => jumpMonth(m)}>{Number(m.slice(5))}월</button>
          ))}
        </div>
        <div className={styles.weeks} ref={weekRef}>
          {weeks.map((w) => (
            <div className={styles.week} key={w[0]}>
              {w.map((d) => {
                const dt = parseDay(d);
                return (
                  <button type="button" key={d} className={`${styles.day} ${d === date ? styles.dayOn : ""}`} aria-pressed={d === date} aria-label={`${dt.getMonth() + 1}월 ${dt.getDate()}일`}
                    onClick={() => { setDate(d); setTime(""); }}>
                    <span className={styles.dayWd}>{WEEKDAYS[dt.getDay()]}</span>
                    <span className={`${styles.dayNum} ${dt.getDay() === 0 ? styles.dayMuted : ""}`}>{dt.getDate()}</span>
                    <span className={styles.dayToday}>{d === today ? "오늘" : ""}</span>
                  </button>
                );
              })}
            </div>
          ))}
        </div>

        <h2 className={styles.secTitle} style={{ paddingBottom: 6 }}>예약 시간을 선택해주세요</h2>
        <div className={styles.howTitle}>예약 확정 방식</div>
        <div className={styles.howDesc}>병원에서 확인 후 일정이 확정돼요</div>
        <div className={styles.grid}>
          {closed ? <div className={styles.gridNote}>선택한 날짜는 휴무일입니다.</div>
            : slots.length === 0 ? <div className={styles.gridNote}>선택 가능한 시간이 없습니다.</div>
            : slots.map((s) => (
              <button type="button" key={s.value} disabled={s.disabled} className={`${styles.slot} ${time === s.value ? styles.slotOn : ""}`}
                aria-pressed={time === s.value} onClick={() => setTime(s.value)}>{s.value}</button>
            ))}
        </div>
        {message && <p className={styles.msg} role="alert">{message}</p>}

        <div className={styles.bottom}>
          <button type="submit" className={`${styles.primary} ${complete ? "" : styles.primaryOff}`} disabled={busy}>예약하기</button>
        </div>
      </form>

      {sheet && (
        <TreatmentSheet ctx={ctx} picked={picked} pkg={pkg} toggle={toggle} pickPkg={(x) => setPkg((cur) => (cur && cur.id === x.id ? null : x))} onClose={() => setSheet(false)} />
      )}

      {stage === "med" && (
        <Sheet onClose={() => setStage(null)} title={<>의료진 상담 후<br />예약할 수 있어요</>} icon="warn"
          desc={<>답변 내용을 병원에 먼저 전달할게요.<br />상담 후 병원에서 예약 가능 여부를 알려드려요.</>}
          actions={(close) => <><button type="button" className={styles.act} onClick={() => close(() => { asked.current = null; setStage(null); setSheet(true); })}>다른 시술 보기</button><button type="button" className={`${styles.act} ${styles.actPri}`} onClick={() => close(afterPre)}>상담 신청하기</button></>}>
          <div className={styles.mcLabel}>해당 항목</div>
          {flagged.map((q) => <div key={q} className={styles.mcItem}>{q}</div>)}
        </Sheet>
      )}
      {stage === "consent" && <Consent onGo={() => { consented.current = true; setStage("confirm"); }} />}

      {refusal && (
        <Refusal r={refusal} hospitalId={ctx.hospital.id} onClose={closeRefusal} onProceed={proceedFlag}
          onPickOne={() => { closeRefusal(); setSheet(true); }}
          onSplit={(names, pairs) => {
            closeRefusal();
            const clash = (a: string, b: string) => pairs.some((x) => (x[0] === a && x[1] === b) || (x[0] === b && x[1] === a));
            const kept: string[] = []; const removed: string[] = [];
            names.forEach((n) => { if (kept.some((k) => clash(k, n))) removed.push(n); else kept.push(n); });
            setPicked((cur) => cur.filter((t) => !removed.includes(t.name)));
            setSplitHint(`"${removed.join('", "')}" 시술은 다른 날짜로 따로 예약해주세요. 이번 예약은 "${kept.join(", ")}" 으로 진행돼요.`);
          }} />
      )}
      {noshowOpen && <NoshowSheet info={noshowOpen} onOk={() => { setNoshowOpen(null); leave(); }} />}
    </div>
  );
}

const WarnIcon = () => <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#fff" strokeWidth="2.4" strokeLinecap="round" aria-hidden="true"><path d="M12 6v8" /><circle cx="12" cy="18" r="1" fill="#fff" stroke="none" /></svg>;
const CheckIcon = () => <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#fff" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>;

type CloseThen = (after?: () => void) => void;

/** `close` reaches the buttons as a prop: it touches the sheet's ref only when a button is pressed, never while rendering. */
function SheetActions({ render, close }: { render: (close: CloseThen) => React.ReactNode; close: CloseThen }) {
  return <div className={styles.actions}>{render(close)}</div>;
}

/**
 * Message sheet on the BottomSheet standard (05-11 / 05-12 layout): 48px icon, 22px title, 15px text, body, a row of 52px buttons.
 * `actions` gets `close(after)`: the sheet slides out (0.4 s) and then runs `after` — or `onClose` when nothing is passed.
 * `locked` = a decision sheet: no dim tap, Esc or grip; only its buttons close it.
 */
function Sheet({ title, desc, icon, children, actions, onClose, locked, label }: {
  title: React.ReactNode; desc?: React.ReactNode; icon?: "warn" | "ok"; children?: React.ReactNode; actions: (close: CloseThen) => React.ReactNode; onClose: () => void; locked?: boolean; label?: string;
}) {
  const ref = useRef<BottomSheetHandle>(null);
  const pending = useRef<(() => void) | null>(null);
  const close: CloseThen = (after) => { pending.current = after ?? null; ref.current?.close(); };
  const afterClose = () => { const f = pending.current ?? onClose; pending.current = null; f(); };
  return (
    <BottomSheet ref={ref} open onClose={afterClose} label={label ?? (typeof title === "string" ? title : "안내")} dismissible={!locked}>
      <div className={styles.sheetBody}>
        {icon && <div className={styles.sheetIcon} aria-hidden="true">{icon === "ok" ? <CheckIcon /> : <WarnIcon />}</div>}
        <h2 className={styles.sheetTitle}>{title}</h2>
        {desc && <p className={styles.sheetDesc}>{desc}</p>}
        {children}
        <SheetActions render={actions} close={close} />
      </div>
    </BottomSheet>
  );
}

/** 05-11 예약 요청 접수 */
function SuccessSheet({ onDone, onList }: { onDone: () => void; onList: () => void }) {
  return (
    <Sheet title="예약 요청이 접수되었어요!" icon="ok" onClose={onDone} locked label="예약 요청 접수"
      desc={<>원하시는 일정으로<br />병원에 예약 가능 여부를 확인하고 있어요</>}
      actions={(close) => <><button type="button" className={`${styles.act} ${styles.actPri}`} onClick={() => close(onDone)}>확인했어요</button><button type="button" className={styles.act} onClick={() => close(onList)}>내 예약 현황</button></>}>
      <div className={styles.waitBox}>
        <div className={styles.waitTitle}>예상 확인 시간</div>
        <div className={styles.waitText}>평균 1~3시간 이내<br />(병원 상황에 따라 달라질 수 있어요)</div>
        <div className={styles.alertBox}>
          <div className={styles.alertTitle}>알림을 꼭 확인해주세요!</div>
          <div className={styles.waitText}>예약 진행 상황은 알림(푸시)으로 안내해드려요<br />알림을 허용해두시면 빠르게 확인하실 수 있어요</div>
        </div>
      </div>
    </Sheet>
  );
}

/** 옵션 변경: pick packages / treatments (multi-select, up to 20). 선택 완료 closes with the slide-out. */
function TreatmentSheet({ ctx, picked, pkg, toggle, pickPkg, onClose }: { ctx: BookingContext; picked: Picked[]; pkg: BookingPackage | null; toggle: (n: string) => void; pickPkg: (p: BookingPackage) => void; onClose: () => void }) {
  const ref = useRef<BottomSheetHandle>(null);
  return (
    <BottomSheet ref={ref} open onClose={onClose} label="옵션 변경">
      <div className={styles.sheetBody}>
        <h2 className={styles.listTitle} id="bk-sheet-title">옵션 변경</h2>
        <div className={styles.list} role="group" aria-labelledby="bk-sheet-title">
          {ctx.packages.map((x) => (
            <button type="button" key={`p${x.id}`} className={`${styles.card} ${pkg?.id === x.id ? styles.cardOn : ""}`} aria-pressed={pkg?.id === x.id} onClick={() => pickPkg(x)}>
              <span className={styles.cardName}><span className={styles.cardTag}>패키지</span>{x.name}</span>
              <span className={styles.cardFoot}>
                <span className={styles.cardDesc}>{x.item_names || x.description}</span>
                <span className={styles.cardPrice}>
                  {x.original_price > x.package_price && <span className={styles.priceWas}>{won(x.original_price * 10000)}</span>}
                  <span className={styles.priceNow}>{won(x.package_price * 10000)}</span>
                </span>
              </span>
            </button>
          ))}
          {ctx.treatments.map((t) => {
            const on = picked.some((x) => x.name === t.name);
            const range = priceRange(t.price_min, t.price_max);
            return (
              <button type="button" key={t.hospital_treatment_id} className={`${styles.card} ${on ? styles.cardOn : ""}`} aria-pressed={on} onClick={() => toggle(t.name)}>
                <span className={styles.cardName}>{t.name}</span>
                {range && <span className={styles.cardFoot}><span className={styles.cardPrice}><span className={styles.priceNow}>{range}</span></span></span>}
              </button>
            );
          })}
        </div>
        <div className={styles.actions}><button type="button" className={`${styles.act} ${styles.actPri}`} onClick={() => ref.current?.close()}>선택 완료</button></div>
      </div>
    </BottomSheet>
  );
}

function PreCheck({ questions, answers, setAnswers, onNext }: { questions: string[]; answers: Record<string, 0 | 1>; setAnswers: React.Dispatch<React.SetStateAction<Record<string, 0 | 1>>>; onNext: () => void }) {
  const [truth, setTruth] = useState(false);
  const set = (q: string, v: 0 | 1) => setAnswers((cur) => { const n = { ...cur }; if (n[q] === v) delete n[q]; else n[q] = v; return n; });
  return (
    <>
      <div className={styles.pre}>
        <h2 className={styles.pcTitle}>시술 전에 몇 가지만 확인할게요</h2>
        <p className={styles.pcDesc}>답변은 병원 의료진에게만 전달돼요.<br />해당하는 항목이 있으면 의료진 상담 후 예약할 수 있어요.<br />답하기 어려우면 그대로 다음으로 넘어가도 돼요.</p>
        {questions.map((q) => (
          <div key={q} className={styles.pcItem}>
            <span className={styles.pcQ}>{q}</span>
            <div className={styles.pcBtns}>
              <button type="button" className={`${styles.pcBtn} ${answers[q] === 1 ? styles.pcBtnOn : ""}`} aria-pressed={answers[q] === 1} onClick={() => set(q, 1)}>예</button>
              <button type="button" className={`${styles.pcBtn} ${answers[q] === 0 ? styles.pcBtnOn : ""}`} aria-pressed={answers[q] === 0} onClick={() => set(q, 0)}>아니오</button>
            </div>
          </div>
        ))}
        <button type="button" className={styles.tickRow} onClick={() => setTruth((v) => !v)} aria-pressed={truth}><i className={`${styles.tick} ${truth ? styles.tickOn : ""}`}>{truth ? "✓" : ""}</i><span>위 내용을 사실대로 답했어요</span></button>
      </div>
      <div className={styles.bottom}><button type="button" className={styles.primary} onClick={onNext}>다음</button></div>
    </>
  );
}

function Consent({ onGo }: { onGo: () => void }) {
  const [on, setOn] = useState([false, false, false]);
  const [note, setNote] = useState(false);
  const all = on.every(Boolean);
  const rows = [
    "임신·수유 중이 아니며, 최근 2주 내 관련 시술을 받지 않았습니다.",
    "복용 중인 약물·알레르기를 상담 시 알리겠습니다.",
    "시술 전후 주의사항을 확인했습니다.",
  ];
  return (
    <Sheet onClose={onGo} locked title="시술 전 확인해 주세요" label="시술 전 확인"
      actions={(close) => <button type="button" className={`${styles.act} ${styles.actPri}`} disabled={!all} onClick={() => close(onGo)}>동의하고 예약하기</button>}>
      <div className={styles.consent}>
        <button type="button" className={`${styles.csRow} ${styles.csAll}`} onClick={() => setOn(all ? [false, false, false] : [true, true, true])} aria-pressed={all}><i className={`${styles.tick} ${all ? styles.tickOn : ""}`}>{all ? "✓" : ""}</i><span>전체동의</span></button>
        {rows.map((t, i) => (
          <div key={t} className={styles.csLine}>
            <button type="button" className={styles.csRow} onClick={() => setOn((cur) => cur.map((v, j) => (j === i ? !v : v)))} aria-pressed={on[i]}>
              <i className={`${styles.tick} ${on[i] ? styles.tickOn : ""}`}>{on[i] ? "✓" : ""}</i><span>{t} <span className={styles.csReq}>(필수)</span></span>
            </button>
            {i === 2 && <button type="button" className={styles.csView} onClick={() => setNote((v) => !v)}>보기</button>}
          </div>
        ))}
        {note && <p className={styles.csNote}>시술 전후 주의사항은 상담 시 병원에서 자세히 안내해 드려요.</p>}
      </div>
    </Sheet>
  );
}

function NoshowSheet({ info, onOk }: { info: NoshowInfo; onOk: () => void }) {
  return (
    <Sheet onClose={onOk} locked icon="warn" title="지금은 예약할 수 없어요" label="예약 불가"
      desc={<>최근 {info.months}개월 동안 {info.has_cancel ? "방문하지 않았거나 취소한" : "방문하지 않은"} 예약이 {info.count}건 있어요.<br />{info.available_from}부터 다시 예약할 수 있어요.</>}
      actions={(close) => <button type="button" className={`${styles.act} ${styles.actPri}`} onClick={() => close(onOk)}>확인했어요</button>}>
      {info.items.length > 0 && (
        <div className={styles.nsList}>
          {info.items.map((it, i) => <div key={i} className={styles.nsRow}><span>{it.when}{it.hospital ? ` · ${it.hospital}` : ""}</span><span className={styles.nsTag}>{it.kind === "cancel" ? "취소" : "미방문"}</span></div>)}
        </div>
      )}
    </Sheet>
  );
}

function Refusal({ r, hospitalId, onClose, onProceed, onPickOne, onSplit }: {
  r: BookingRefusal; hospitalId: number; onClose: () => void; onProceed: (k: "dup" | "rev") => void; onPickOne: () => void; onSplit: (names: string[], pairs: string[][]) => void;
}) {
  const ok = (close: CloseThen, pri = true) => <button type="button" className={`${styles.act} ${pri ? styles.actPri : ""}`} onClick={() => close()}>확인했어요</button>;
  switch (r.code) {
    case "noshow_blocked": return <NoshowSheet info={r.noshow} onOk={onClose} />;
    case "treatment_conflict":
      return (
        <Sheet onClose={onClose} icon="warn" title={<>같은 날 함께 예약하기<br />어려운 시술이 있어요</>} desc={<>병원 기준에 따라 한 가지만 예약하거나<br />날짜를 나눠서 예약해주세요.</>} label="시술 조합 안내"
          actions={(close) => <><button type="button" className={styles.act} onClick={() => close(onPickOne)}>하나 빼기</button><button type="button" className={`${styles.act} ${styles.actPri}`} onClick={() => close(() => onSplit(r.conflict.names, r.conflict.pairs))}>날짜 나누기</button></>}>
          <div className={styles.nsList}>{r.conflict.names.map((n) => <div key={n} className={styles.nsRow}><span>{n}</span></div>)}</div>
        </Sheet>
      );
    case "duplicate_booking":
      return (
        <Sheet onClose={onClose} title="이미 예약된 시술이 있어요" desc={<>같은 시술을 한 번 더 예약하시려면<br />“새로 예약”을 선택해 주세요.</>}
          actions={(close) => <><a className={styles.act} href={`${BASE_PATH}/booking?change=${r.existing.id}`}>기존 예약 변경</a><button type="button" className={`${styles.act} ${styles.actPri}`} onClick={() => close(() => onProceed("dup"))}>새로 예약</button></>}>
          <div className={styles.exist}><div className={styles.existName}>{r.existing.treatment}{r.existing.hospital_name ? ` · ${r.existing.hospital_name}` : ""}</div><div className={styles.existWhen}>{r.existing.schedule}{r.existing.status ? ` · ${r.existing.status}` : ""}</div></div>
        </Sheet>
      );
    case "other_hospital_duplicate":
      return (
        <Sheet onClose={onClose} title={<>다른 병원에 같은 시술<br />예약이 있어요</>} desc={<>같은 시술은 한 번에 한 병원만 예약할 수 있어요.<br />기존 예약을 취소한 뒤 다시 예약해주세요.</>}
          actions={(close) => <>{ok(close, false)}<Link className={`${styles.act} ${styles.actPri}`} href={`/mypage/bookings/${r.existing.id}`}>기존 예약 보기</Link></>}>
          <div className={styles.exist}>
            <div className={styles.existTags}><span className={`${styles.existTag} ${styles.existTagOn}`}>{r.existing.status}</span><span className={styles.existTag}>다른 병원</span></div>
            <div className={styles.existName}>{r.existing.treatment}{r.existing.hospital_name ? ` · ${r.existing.hospital_name}` : ""}</div><div className={styles.existWhen}>{r.existing.schedule}</div>
          </div>
        </Sheet>
      );
    case "revisit_blocked":
      return (
        <Sheet onClose={onClose} icon="warn" title="아직 예약할 수 없어요" desc={<>이 시술은 안전을 위해 최소 {r.revisit.interval_label} 간격이 필요해요.<br />{r.revisit.available_from}부터 예약할 수 있어요.</>} actions={(close) => ok(close)} />
      );
    case "revisit_warning":
      return (
        <Sheet onClose={onClose} title="아직 재시술 권장 시기가 아니에요"
          desc={<>{koTopic(r.revisit.treatment || "이 시술")} {r.revisit.interval_label} 이상 간격을 권장해요.<br />마지막 시술일로부터 {r.revisit.days_since}일 지났습니다.</>}
          actions={(close) => <><button type="button" className={styles.act} onClick={() => close()}>취소</button><button type="button" className={`${styles.act} ${styles.actPri}`} onClick={() => close(() => onProceed("rev"))}>그래도 예약</button></>} />
      );
    case "age_unknown":
    case "age_restricted": {
      // 05-12 나이 제한 시술
      const unknown = r.code === "age_unknown";
      return (
        <Sheet onClose={onClose} icon="warn" label="나이 제한 시술"
          title={unknown ? <>나이 제한이 있는 시술이에요<br />생년월일 등록이 필요해요</> : <>{r.min_age > 0 ? `만 ${r.min_age}세 미만은` : "이 시술은"}<br />이 시술을 앱에서 예약할 수 없어요</>}
          desc={unknown ? <>{r.min_age > 0 && <>만 {r.min_age}세 이상만 앱에서 예약할 수 있어요.<br /></>}내 정보에서 생년월일을 등록해주세요.</> : <>병원 기준에 따라 보호자와 함께<br />병원에서 직접 상담을 받아주세요.</>}
          actions={(close) => <>{ok(close, false)}{unknown ? <Link className={`${styles.act} ${styles.actPri}`} href="/mypage/profile">생년월일 등록</Link> : <Link className={`${styles.act} ${styles.actPri}`} href={`/hospitals/${hospitalId}`}>병원에 문의하기</Link>}</>} />
      );
    }
  }
}
