"use client";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useRef, useState } from "react";
import { useQuery } from "@tanstack/react-query";
import { api, ApiError, qk, type BookingChangeInfo, type BookingContext, type BookingOption, type BookingPackage, type BookingRefusal, type BookingRequest, type NoshowInfo } from "@/lib/api";
import { bookableDays, dday, formatDateTime, koTopic, parseDay, priceRange, timeSlots, won, WEEKDAYS } from "@/lib/booking";
import { phpUrl, usePhpBase } from "@/lib/php";
import LoginPrompt from "./LoginPrompt";
import styles from "./Booking.module.css";

export interface BookingParams {
  id: number; treatmentId: number; optionId: number; treatmentName: string; rebook: boolean; via: string; sourceId: number;
  /** booking_change.php: the booking being rescheduled (its hospital and treatment come from the API). */
  change?: number;
}

/** 예약하기 (booking.php): choose treatments, a day and a time, answer the clinic's questions, confirm. */
export default function Booking(p: BookingParams) {
  const changeId = p.change ?? 0;
  const info = useQuery({ queryKey: qk.bookingChangeInfo(changeId), queryFn: () => api.bookingChangeInfo(changeId), enabled: changeId > 0, staleTime: 0, gcTime: 0, retry: false, refetchOnReconnect: false, refetchOnWindowFocus: false });
  const hospitalId = changeId ? info.data?.hospitalId ?? 0 : p.id;
  const { data, error, refetch } = useQuery({ queryKey: qk.bookingContext(hospitalId), queryFn: () => api.bookingContext(hospitalId), enabled: hospitalId > 0, staleTime: 0, gcTime: 0, retry: false, refetchOnReconnect: false, refetchOnWindowFocus: false }); // a refetch (reconnect, app back to foreground) must not reset what the member chose
  const title = changeId ? "예약 변경" : p.rebook ? (p.via === "again" ? "예약 선택" : "다시 예약") : "예약하기";
  const back = changeId ? "/mypage/bookings" : p.rebook && p.sourceId > 0 ? `/mypage/bookings/${p.sourceId}` : `/hospitals/${p.id}`;
  const err = info.error ?? error;
  const loggedOut = err instanceof ApiError && err.status === 401;
  const params: BookingParams = changeId && info.data ? { ...p, id: info.data.hospitalId, treatmentName: info.data.treatment, rebook: true } : p;
  return (
    <div className={styles.wrap}>
      <div className={styles.head}>
        <Link href={back} className={styles.back} aria-label="뒤로">‹</Link>
        <span className={styles.title}>{title}</span>
      </div>
      {loggedOut ? <LoginPrompt text="로그인하면 예약할 수 있습니다." />
        : info.error instanceof ApiError && (info.error.status === 404 || info.error.status === 409) ? <div className={styles.empty}>변경할 수 없는 예약입니다. <Link className={styles.retry} href="/mypage/bookings">내 예약으로</Link></div>
        : error instanceof ApiError && error.status === 404 ? <div className={styles.empty}>병원 정보를 찾을 수 없습니다.</div>
        : !data || (changeId > 0 && !info.data) ? <div className={styles.empty}>{err ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => { void info.refetch(); void refetch(); }}>다시 시도</button></> : "불러오는 중…"}</div>
        : <BookingForm ctx={data} p={params} back={back} change={changeId ? info.data : undefined} />}
    </div>
  );
}

interface Picked { name: string; range: string; min_age: number; options: BookingOption[]; option_id: number }
type Stage = null | "pre" | "med" | "consent" | "confirm" | "success";

function BookingForm({ ctx, p, back, change }: { ctx: BookingContext; p: BookingParams; back: string; change?: BookingChangeInfo }) {
  const router = useRouter();
  const php = usePhpBase();
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
  const setOption = (name: string, id: number) => setPicked((cur) => cur.map((t) => (t.name === name ? { ...t, option_id: id } : t)));

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
      router.replace(`/mypage/bookings/${change.id}?changed=1`);
      return;
    } catch (e) {
      setMessage(e instanceof ApiError && e.status === 401 ? "로그인이 필요합니다. 다시 로그인해주세요." : e instanceof Error && e.message && !/^\d{3} /.test(e.message) ? e.message : "변경 처리 중 오류가 발생했습니다.");
    }
    setBusy(false);
  };
  const submit = () => {
    if (change ? !date || !time || !optionsReady : !ready) {
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
      setStage("success");
    } catch (e) {
      if (refused(e)) { setStage(null); flags.current.dup = false; }
      else setConfirmErr(e instanceof ApiError && e.status === 401 ? "로그인이 필요합니다. 다시 로그인해주세요." : e instanceof Error && e.message && !/^\d{3} /.test(e.message) ? e.message : "예약 중 오류가 발생했습니다. 다시 시도해주세요.");
    }
    setBusy(false);
  };

  const closeRefusal = () => setRefusal(null);
  const proceedFlag = (k: "dup" | "rev") => { flags.current[k] = true; setRefusal(null); void precheckThenConfirm(); };

  const months = [...new Set(days.map((d) => d.slice(0, 7)))];
  const dateRef = useRef<HTMLDivElement>(null);
  const jumpMonth = (m: string) => {
    const el = dateRef.current?.querySelector<HTMLElement>(`[data-month="${m}"]`);
    if (el && dateRef.current) dateRef.current.scrollTo({ left: el.offsetLeft - dateRef.current.offsetLeft, behavior: "auto" });
  };
  const curMonth = date.slice(0, 7);

  return (
    <>
      {ctx.hospital.images.length > 0 && !p.rebook && !change && (
        <div className={styles.images}>
          <div className={styles.imagesScroll}>
            {ctx.hospital.images.map((src, i) => <div key={i} className={styles.image} style={{ backgroundImage: `url("${src}")` }} />)}
          </div>
          {ctx.hospital.images.length > 1 && <div className={styles.counter}>1 / {ctx.hospital.images.length}</div>}
        </div>
      )}
      {message && <div className={styles.errorBox} role="alert">{message}</div>}
      <form className={styles.form} onSubmit={(e) => { e.preventDefault(); submit(); }}>
        <div className={`${styles.group} ${styles.groupHead}`}>
          <div className={styles.headRow}>
            <span className={styles.headTitle}>{p.rebook ? "예약을 진행해볼까요?" : "예약하기"}</span>
            <button type="button" className={styles.optBtn} onClick={() => setSheet(true)}>옵션 변경</button>
          </div>
          {!hasChoice && <div className={styles.help}>{p.treatmentName || p.treatmentId ? "선택한 시술의 세부 옵션을 확인해주세요." : "원하는 시술 1개를 선택해주세요."}</div>}
          {hasChoice && (
            <div className={styles.chosen}>
              {pkg && (
                <div className={styles.stItem}>
                  <div className={styles.stTop}><span className={styles.stName}>[패키지] {pkg.name}</span><button type="button" className={styles.stRemove} onClick={() => setPkg(null)} aria-label="삭제">×</button></div>
                  <div className={styles.stRow}><span className={styles.help}>{pkg.item_names}</span><span className={styles.stPrice}>{won(pkg.package_price * 10000)}</span></div>
                </div>
              )}
              {picked.map((t) => (
                <div key={t.name} className={styles.stItem}>
                  <div className={styles.stTop}><span className={styles.stName}>{t.name}</span><button type="button" className={styles.stRemove} onClick={() => toggle(t.name)} aria-label="삭제">×</button></div>
                  <div className={styles.stRow}>
                    {t.options.length > 1 ? (
                      <div className={styles.optList}>
                        {t.options.map((o) => (
                          <label key={o.id} className={`${styles.optItem} ${t.option_id === o.id ? styles.optItemOn : ""}`}>
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
                    ) : t.range ? <span className={styles.stPrice}>{t.range}</span> : null}
                  </div>
                </div>
              ))}
            </div>
          )}
          {splitHint && <div className={styles.splitHint}>{splitHint}</div>}
        </div>

        <div className={styles.group}>
          <label className={styles.label}>{p.rebook ? "언제 방문하시겠어요?" : "날짜 선택"}</label>
          <div className={styles.months}>
            {months.map((m) => (
              <button type="button" key={m} className={`${styles.month} ${m === curMonth ? styles.monthOn : ""}`} aria-pressed={m === curMonth} onClick={() => jumpMonth(m)}>{Number(m.slice(5))}월</button>
            ))}
          </div>
          <div className={styles.dates} ref={dateRef}>
            {days.map((d) => {
              const dt = parseDay(d);
              return (
                <button type="button" key={d} data-month={d.slice(0, 7)} className={`${styles.chip} ${d === date ? styles.chipOn : ""}`} aria-pressed={d === date}
                  onClick={() => { setDate(d); setTime(""); }}>
                  <span className={styles.dcWd}>{WEEKDAYS[dt.getDay()]}</span>
                  <span className={styles.dcDay}>{dt.getDate()}</span>
                  {d === today && <span className={styles.dcToday}>오늘</span>}
                </button>
              );
            })}
          </div>
        </div>

        <div className={styles.group}>
          <label className={styles.label}>{p.rebook ? "예약 시간을 선택해주세요" : "시간 선택"}</label>
          <div className={styles.grid}>
            {closed ? <div className={styles.gridNote}>선택한 날짜는 휴무일입니다.</div>
              : slots.length === 0 ? <div className={styles.gridNote}>선택 가능한 시간이 없습니다.</div>
              : slots.map((s) => (
                <button type="button" key={s.value} disabled={s.disabled} className={`${styles.slot} ${time === s.value ? styles.slotOn : ""} ${s.disabled ? styles.slotOff : ""}`}
                  aria-pressed={time === s.value} onClick={() => setTime(s.value)}>{s.disabled ? s.label : s.value}</button>
              ))}
          </div>
        </div>

        <div className={styles.submit}>
          <button type="submit" className={`${styles.submitBtn} ${(change ? date && time && optionsReady : ready) ? "" : styles.incomplete}`} disabled={busy}>{change ? "예약하기" : "예약 신청하기"}</button>
        </div>
      </form>

      {sheet && (
        <TreatmentSheet ctx={ctx} picked={picked} pkg={pkg} toggle={toggle} pickPkg={(x) => setPkg((cur) => (cur && cur.id === x.id ? null : x))} onClose={() => setSheet(false)} />
      )}

      {stage === "pre" && (
        <PreCheck questions={questions()} answers={answers} setAnswers={setAnswers} onBack={() => setStage(null)}
          onNext={() => {
            const qs = questions();
            const out = qs.filter((q) => answers[q] === 0 || answers[q] === 1).map((q) => ({ q, a: answers[q] }));
            preOut.current = JSON.stringify(out);
            asked.current = JSON.stringify(qs);
            const yes = out.filter((x) => x.a === 1).map((x) => x.q);
            if (yes.length) { setFlagged(yes); setStage("med"); } else afterPre();
          }} />
      )}
      {stage === "med" && (
        <Sheet onClose={() => setStage(null)} title={<>의료진 상담 후<br />예약할 수 있어요</>} icon="!"
          desc={<>답변 내용을 병원에 먼저 전달할게요.<br />상담 후 병원에서 예약 가능 여부를 알려드려요.</>}
          actions={<><button type="button" className={styles.act} onClick={() => { asked.current = null; setStage(null); setSheet(true); }}>다른 시술 보기</button><button type="button" className={`${styles.act} ${styles.actPri}`} onClick={afterPre}>상담 신청하기</button></>}>
          <div className={styles.mcLabel}>해당 항목</div>
          {flagged.map((q) => <div key={q} className={styles.mcItem}>{q}</div>)}
        </Sheet>
      )}
      {stage === "consent" && <Consent onGo={() => { consented.current = true; setStage("confirm"); }} />}
      {stage === "confirm" && (
        <div className={styles.page} role="dialog" aria-modal="true" aria-labelledby="bk-confirm-title">
          <section className={styles.pageIn}>
            <header className={styles.pageHead}>
              <button type="button" onClick={() => { flags.current.dup = false; setStage(null); }} aria-label="뒤로가기">‹</button>
              <h2 className={styles.pageTitle} id="bk-confirm-title">이대로 예약을 진행할까요?</h2>
              <span />
            </header>
            <div className={styles.stateBadge}><span>시술예정</span><span>D-{dday(date, today)}</span></div>
            <div>
              <div className={styles.infoRow}><span className={styles.infoLabel}>예약번호</span><span className={styles.infoValue}>접수 후 발급</span></div>
              <div className={styles.infoRow}><span className={styles.infoLabel}>날짜/시간</span><span className={styles.infoValue}>{formatDateTime(date, time)}</span></div>
              <div className={styles.infoRow}><span className={styles.infoLabel}>선택시술</span><span className={styles.infoValue}>{pickedSummary}</span></div>
              <div className={styles.infoRow}><span className={styles.infoLabel}>병원</span><span className={styles.infoValue}>{ctx.hospital.name}</span></div>
            </div>
            <div className={styles.person}>
              <span className={styles.personLabel}>예약자</span>
              {ctx.member.name ? <span>{ctx.member.name}</span> : <input className={styles.input} maxLength={100} placeholder="예약자명을 입력해주세요" autoComplete="name" value={name} onChange={(e) => setName(e.target.value)} aria-label="예약자명" />}
              <label className={styles.personLabel} htmlFor="bk-phone">연락처</label>
              <input id="bk-phone" className={styles.input} type="tel" inputMode="tel" autoComplete="tel" maxLength={30} placeholder="연락처를 입력해주세요" value={phone} onChange={(e) => setPhone(e.target.value)} aria-invalid={showErr && !phone.trim()} />
              {showErr && !phone.trim() && <span className={styles.fieldErr} role="alert">연락처를 입력해주세요.</span>}
              {showErr && !ctx.member.name && !name.trim() && <span className={styles.fieldErr} role="alert">예약자명을 입력해주세요.</span>}
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
            {confirmErr && <div className={styles.errorBox} role="alert" style={{ margin: "0 0 12px" }}>{confirmErr}</div>}
            <div className={styles.pageFoot}>
              <button type="button" className={styles.confirmBtn} disabled={busy} onClick={finish}>{busy ? "접수 중..." : "확인했어요"}</button>
            </div>
          </section>
        </div>
      )}
      {stage === "success" && (
        <Sheet title="예약 요청이 접수되었어요!" icon="✓" onClose={() => router.replace(back)} locked
          desc={<>원하시는 일정으로<br />병원에 예약 가능 여부를 확인하고 있어요</>}
          actions={<><button type="button" className={`${styles.act} ${styles.actPri}`} onClick={() => router.replace(back)}>확인했어요</button><Link href="/mypage/bookings" replace className={styles.act}>내 예약 현황</Link></>}>
          <div className={styles.waitBox}>
            <div className={styles.waitTitle}>예상 확인 시간</div>
            <div className={styles.waitTime}>평균 1~3시간 이내<br />(병원 상황에 따라 달라질 수 있어요)</div>
            <div className={styles.alertBox}>
              <div className={styles.alertTitle}>알림을 꼭 확인해주세요!</div>
              <div className={styles.alertCopy}>예약 진행 상황은 알림(푸시)으로 안내해드려요<br />알림을 허용해두시면 빠르게 확인하실 수 있어요</div>
            </div>
          </div>
        </Sheet>
      )}

      {refusal && (
        <Refusal r={refusal} php={php} hospitalId={ctx.hospital.id} onClose={closeRefusal} onProceed={proceedFlag}
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
      {noshowOpen && <NoshowSheet info={noshowOpen} onOk={() => { setNoshowOpen(null); router.replace(back); }} />}
    </>
  );
}

/** Bottom sheet: dimmed backdrop, handle, body and a row of actions. `locked` = only the actions close it. */
function Sheet({ title, desc, icon, children, actions, onClose, locked }: { title: React.ReactNode; desc?: React.ReactNode; icon?: string; children?: React.ReactNode; actions: React.ReactNode; onClose: () => void; locked?: boolean }) {
  return (
    <div className={styles.overlay} onClick={locked ? undefined : onClose}>
      <section className={styles.sheet} role="dialog" aria-modal="true" onClick={(e) => e.stopPropagation()}>
        <div className={styles.handle} />
        <div className={styles.sheetBody}>
          {icon && <span className={styles.sheetIcon} aria-hidden="true">{icon}</span>}
          <h2 className={styles.sheetTitle}>{title}</h2>
          {desc && <p className={styles.sheetDesc}>{desc}</p>}
          {children}
        </div>
        <div className={styles.actions}>{actions}</div>
      </section>
    </div>
  );
}

function TreatmentSheet({ ctx, picked, pkg, toggle, pickPkg, onClose }: { ctx: BookingContext; picked: Picked[]; pkg: BookingPackage | null; toggle: (n: string) => void; pickPkg: (p: BookingPackage) => void; onClose: () => void }) {
  return (
    <div className={styles.overlay} onClick={onClose}>
      <div className={styles.sheet} role="dialog" aria-modal="true" aria-labelledby="bk-sheet-title" onClick={(e) => e.stopPropagation()}>
        <div className={styles.handle} />
        <div className={styles.sheetBody}>
          <h2 id="bk-sheet-title" style={{ fontSize: 14, fontWeight: 600, margin: "0 0 12px" }}>옵션 선택</h2>
          <div className={styles.sheetList} role="group" aria-labelledby="bk-sheet-title">
            {ctx.packages.map((x) => (
              <button type="button" key={`p${x.id}`} className={`${styles.item} ${styles.itemPkg} ${pkg?.id === x.id ? styles.itemOn : ""}`} aria-pressed={pkg?.id === x.id} onClick={() => pickPkg(x)}>
                <span className={styles.itemName}>{x.name}</span>
                <span className={styles.itemFoot}>
                  <span className={styles.itemDesc}>{x.item_names || x.description}</span>
                  <span className={styles.itemPrice}>
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
                <button type="button" key={t.hospital_treatment_id} className={`${styles.item} ${on ? styles.itemOn : ""}`} aria-pressed={on} onClick={() => toggle(t.name)}>
                  <span className={styles.itemName}>{t.name}</span>
                  <span className={styles.itemFoot}>{range && <span className={styles.itemPrice}><span className={styles.priceNow}>{range}</span></span>}</span>
                </button>
              );
            })}
          </div>
        </div>
        <button type="button" className={styles.sheetConfirm} onClick={onClose}>선택 완료</button>
      </div>
    </div>
  );
}

function PreCheck({ questions, answers, setAnswers, onBack, onNext }: { questions: string[]; answers: Record<string, 0 | 1>; setAnswers: React.Dispatch<React.SetStateAction<Record<string, 0 | 1>>>; onBack: () => void; onNext: () => void }) {
  const [truth, setTruth] = useState(false);
  const set = (q: string, v: 0 | 1) => setAnswers((cur) => { const n = { ...cur }; if (n[q] === v) delete n[q]; else n[q] = v; return n; });
  return (
    <div className={styles.page} role="dialog" aria-modal="true" aria-labelledby="bk-pre-title">
      <section className={styles.pageIn}>
        <header className={styles.pageHead}><button type="button" onClick={onBack} aria-label="뒤로 가기">‹</button><div className={styles.pageTitle}>시술 전 확인</div><span /></header>
        <h2 className={styles.pcTitle} id="bk-pre-title">시술 전에 몇 가지만 확인할게요</h2>
        <p className={styles.pcDesc}>답변은 병원 의료진에게만 전달돼요.<br />해당하는 항목이 있으면 의료진 상담 후 예약할 수 있어요.<br />답하기 어려우면 그대로 다음으로 넘어가도 돼요.</p>
        <div>
          {questions.map((q) => (
            <div key={q} className={styles.pcItem}>
              <span className={styles.pcQ}>{q}</span>
              <div className={styles.pcBtns}>
                <button type="button" className={`${styles.pcBtn} ${answers[q] === 1 ? styles.pcBtnOn : ""}`} onClick={() => set(q, 1)}>예</button>
                <button type="button" className={`${styles.pcBtn} ${answers[q] === 0 ? styles.pcBtnOn : ""}`} onClick={() => set(q, 0)}>아니오</button>
              </div>
            </div>
          ))}
        </div>
        <button type="button" className={styles.pcTruth} onClick={() => setTruth((v) => !v)} aria-pressed={truth}><i className={`${styles.tick} ${truth ? styles.tickOn : ""}`}>{truth ? "✓" : ""}</i><span>위 내용을 사실대로 답했어요</span></button>
        <div className={styles.pageFoot}><button type="button" className={styles.confirmBtn} onClick={onNext}>다음</button></div>
      </section>
    </div>
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
    <Sheet onClose={() => undefined} locked title="시술 전 확인해 주세요" actions={<button type="button" className={`${styles.act} ${styles.actPri}`} disabled={!all} style={all ? undefined : { opacity: 0.5 }} onClick={onGo}>동의하고 예약하기</button>}>
      <button type="button" className={`${styles.csRow} ${styles.csAll}`} onClick={() => setOn(all ? [false, false, false] : [true, true, true])} aria-pressed={all}><i className={`${styles.tick} ${all ? styles.tickOn : ""}`}>{all ? "✓" : ""}</i><span>전체동의</span></button>
      {rows.map((t, i) => (
        <div key={t} style={i === 2 ? { display: "flex", alignItems: "flex-start", gap: 8 } : undefined}>
          <button type="button" className={styles.csRow} onClick={() => setOn((cur) => cur.map((v, j) => (j === i ? !v : v)))} aria-pressed={on[i]}>
            <i className={`${styles.tick} ${on[i] ? styles.tickOn : ""}`}>{on[i] ? "✓" : ""}</i><span>{t} <span className={styles.csReq}>(필수)</span></span>
          </button>
          {i === 2 && <button type="button" className={styles.csView} onClick={() => setNote((v) => !v)}>보기</button>}
        </div>
      ))}
      {note && <p className={styles.csNote}>시술 전후 주의사항은 상담 시 병원에서 자세히 안내해 드려요.</p>}
    </Sheet>
  );
}

function NoshowSheet({ info, onOk }: { info: NoshowInfo; onOk: () => void }) {
  return (
    <Sheet onClose={onOk} locked icon="!" title="지금은 예약할 수 없어요"
      desc={<>최근 {info.months}개월 동안 {info.has_cancel ? "방문하지 않았거나 취소한" : "방문하지 않은"} 예약이 {info.count}건 있어요.<br />{info.available_from}부터 다시 예약할 수 있어요.</>}
      actions={<button type="button" className={`${styles.act} ${styles.actPri}`} onClick={onOk}>확인했어요</button>}>
      {info.items.map((it, i) => <div key={i} className={styles.nsRow}><span>{it.when}{it.hospital ? ` · ${it.hospital}` : ""}</span><span className={styles.nsTag}>{it.kind === "cancel" ? "취소" : "미방문"}</span></div>)}
    </Sheet>
  );
}

function Refusal({ r, php, hospitalId, onClose, onProceed, onPickOne, onSplit }: {
  r: BookingRefusal; php: string; hospitalId: number; onClose: () => void; onProceed: (k: "dup" | "rev") => void; onPickOne: () => void; onSplit: (names: string[], pairs: string[][]) => void;
}) {
  const ok = <button type="button" className={`${styles.act} ${styles.actPri}`} onClick={onClose}>확인했어요</button>;
  switch (r.code) {
    case "noshow_blocked": return <NoshowSheet info={r.noshow} onOk={onClose} />;
    case "treatment_conflict":
      return (
        <Sheet onClose={onClose} icon="!" title={<>같은 날 함께 예약하기<br />어려운 시술이 있어요</>} desc={<>병원 기준에 따라 한 가지만 예약하거나<br />날짜를 나눠서 예약해주세요.</>}
          actions={<><button type="button" className={styles.act} onClick={onPickOne}>하나 빼기</button><button type="button" className={`${styles.act} ${styles.actPri}`} onClick={() => onSplit(r.conflict.names, r.conflict.pairs)}>날짜 나누기</button></>}>
          {r.conflict.names.map((n) => <div key={n} className={styles.cfRow}>{n}</div>)}
        </Sheet>
      );
    case "duplicate_booking":
      return (
        <Sheet onClose={onClose} title="이미 예약된 시술이 있어요" desc={<>같은 시술을 한 번 더 예약하시려면<br />“새로 예약”을 선택해 주세요.</>}
          actions={<><button type="button" className={`${styles.act} ${styles.actPri}`} onClick={() => onProceed("dup")}>새로 예약</button><a className={styles.act} href={phpUrl(php, `booking_change.php?booking_id=${r.existing.id}`)}>기존 예약 변경</a></>}>
          <div className={styles.card}><div className={styles.cardName}>{r.existing.treatment}{r.existing.hospital_name ? ` · ${r.existing.hospital_name}` : ""}</div><div className={styles.cardWhen}>{r.existing.schedule}{r.existing.status ? ` · ${r.existing.status}` : ""}</div></div>
        </Sheet>
      );
    case "other_hospital_duplicate":
      return (
        <Sheet onClose={onClose} title={<>다른 병원에 같은 시술<br />예약이 있어요</>} desc={<>같은 시술은 한 번에 한 병원만 예약할 수 있어요.<br />기존 예약을 취소한 뒤 다시 예약해주세요.</>}
          actions={<>{ok}<Link className={styles.act} href={`/mypage/bookings/${r.existing.id}`}>기존 예약 보기</Link></>}>
          <div className={styles.card}>
            <div className={styles.badges}><span className={`${styles.badge} ${styles.badgeOn}`}>{r.existing.status}</span><span className={styles.badge}>다른 병원</span></div>
            <div className={styles.cardName}>{r.existing.treatment}{r.existing.hospital_name ? ` · ${r.existing.hospital_name}` : ""}</div><div className={styles.cardWhen}>{r.existing.schedule}</div>
          </div>
        </Sheet>
      );
    case "revisit_blocked":
      return (
        <Sheet onClose={onClose} title="아직 예약할 수 없어요" desc={<>이 시술은 안전을 위해 최소 {r.revisit.interval_label} 간격이 필요해요.<br />{r.revisit.available_from}부터 예약할 수 있어요.</>} actions={ok} />
      );
    case "revisit_warning":
      return (
        <Sheet onClose={onClose} title="아직 재시술 권장 시기가 아니에요"
          desc={<>{koTopic(r.revisit.treatment || "이 시술")} {r.revisit.interval_label} 이상 간격을 권장해요.<br />마지막 시술일로부터 {r.revisit.days_since}일 지났습니다.</>}
          actions={<><button type="button" className={styles.act} onClick={onClose}>취소</button><button type="button" className={`${styles.act} ${styles.actPri}`} onClick={() => onProceed("rev")}>그래도 예약</button></>} />
      );
    case "age_unknown":
    case "age_restricted": {
      const unknown = r.code === "age_unknown";
      return (
        <Sheet onClose={onClose}
          title={unknown ? <>나이 제한이 있는 시술이에요<br />생년월일 등록이 필요해요</> : <>{r.min_age > 0 ? `만 ${r.min_age}세 미만은` : "이 시술은"}<br />이 시술을 앱에서 예약할 수 없어요</>}
          desc={unknown ? <>{r.min_age > 0 && <>만 {r.min_age}세 이상만 앱에서 예약할 수 있어요.<br /></>}내 정보에서 생년월일을 등록해주세요.</> : <>병원 기준에 따라 보호자와 함께<br />병원에서 직접 상담을 받아주세요.</>}
          actions={<>{ok}{unknown ? <Link className={styles.act} href="/mypage/profile">생년월일 등록</Link> : <Link className={styles.act} href={`/hospitals/${hospitalId}`}>병원에 문의하기</Link>}</>} />
      );
    }
  }
}
