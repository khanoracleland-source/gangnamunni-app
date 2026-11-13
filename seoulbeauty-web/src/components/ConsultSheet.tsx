"use client";
import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { useToast } from "./Toast";
import { useMutation, useQuery } from "@tanstack/react-query";
import { api, qk, type HospitalDetail } from "@/lib/api";
import { useMe } from "@/lib/me";
import { addrDistrict } from "./HospitalCard";
import { bookableDays, formatDateTime, parseDay, timeSlots, WEEKDAYS } from "@/lib/booking";
import BottomSheet, { type BottomSheetHandle } from "./BottomSheet";
import styles from "./ConsultSheet.module.css";
import hospitalIcon from "@/assets/icons/hospital.png";

type Method = "phone" | "kakao" | "visit";
const METHODS: { key: Method; label: string }[] = [{ key: "phone", label: "전화 상담" }, { key: "kakao", label: "카카오톡 채널" }, { key: "visit", label: "방문 상담" }];
const TIMES = ["오전", "오후", "저녁", "언제든 괜찮아요"];
const DEFAULT_TREATS = ["보톡스", "필러", "리프팅", "스킨부스터"];

/**
 * design 05-05 상담 신청서 bottom sheet. What 신청하기 does depends on the 상담 방법:
 *  - 전화 상담: the request is saved (the hospital calls back in the chosen time of day) → 05-06.
 *  - 방문 상담: the visitor picks a day and a time like on the booking page; the request is saved with it → 05-06.
 *  - 카카오톡 채널: nothing is saved — the hospital cannot message first, so the visitor is sent to the channel → 05-07.
 * Saved requests carry the chip answers as the message prefix (the admin list shows those lines).
 */
export default function ConsultSheet({ hospital, open, onClose }: { hospital: HospitalDetail; open: boolean; onClose: () => void }) {
  const me = useMe();
  const treat = useQuery({ queryKey: qk.treatments(hospital.id), queryFn: () => api.treatments(hospital.id), enabled: open });
  const chips = (treat.data?.items.map((t) => t.name) ?? []).filter((v, i, a) => a.indexOf(v) === i).slice(0, 7);
  const treatChips = [...(chips.length ? chips : DEFAULT_TREATS), "기타"];
  const kakaoUrl = hospital.kakaoChannelUrl ?? null;
  const methods = METHODS.filter((m) => m.key !== "kakao" || kakaoUrl);   // no channel registered → no 카카오톡 chip
  const [sel, setSel] = useState<{ treat: string | null; method: Method; time: string }>({ treat: null, method: "phone", time: TIMES[1] });
  // null = untouched → falls back to the member profile (detail.php prefills the same way)
  const [nameEdit, setName] = useState<string | null>(null);
  const [phoneEdit, setPhone] = useState<string | null>(null);
  const name = nameEdit ?? me.data?.user?.name ?? "";
  const phone = phoneEdit ?? me.data?.user?.phone ?? "";
  const selTreat = sel.treat ?? treatChips[0] ?? "";
  const [msg, setMsg] = useState("");
  const [agree, setAgree] = useState(false);
  const [showPolicy, setShowPolicy] = useState(false);
  const [done, setDone] = useState<null | { method: Method; time: string; visit: string }>(null);
  // 방문 상담: the day and time, from the same opening hours as the booking page
  const visitCtx = useQuery({ queryKey: qk.bookingContext(hospital.id), queryFn: () => api.bookingContext(hospital.id), enabled: open && sel.method === "visit", staleTime: 60_000 });
  const [visitDate, setVisitDate] = useState("");
  const [visitTime, setVisitTime] = useState("");
  const [err, setErr] = useState<string | null>(null);
  const [checking, setChecking] = useState(false);   // 방문 상담: re-checking the chosen time before sending
  const showToast = useToast();
  // The sheet itself (dim, grip drag, 0.4 s slide, Esc) is BottomSheet; buttons here close it through the ref so they animate too.
  const sheet = useRef<BottomSheetHandle>(null);
  const closeAnimated = () => sheet.current?.close();
  // After the slide-out: reset so the next open starts on the form, not on the last 완료 screen;
  // a request still in flight is ignored when it lands (the consultation is saved either way, 마이페이지 shows it)
  const openRef = useRef(open);
  useEffect(() => { openRef.current = open; }, [open]);
  const afterClose = () => { setDone(null); setErr(null); onClose(); };

  const methodLabel = (k: Method) => METHODS.find((m) => m.key === k)!.label;
  // The submitted values travel with the mutation, so the 완료 screen describes what was sent even if a chip is changed while the request is in flight.
  type Submission = { treat: string; method: "phone" | "visit"; time: string; visit: string; name: string; phone: string; msg: string };
  const m = useMutation({
    mutationFn: (sub: Submission) => api.consult({
      hospitalId: hospital.id,
      type: sub.method === "phone" ? "general" : "doctor",   // consultation_type: general = 전화, doctor = 방문
      name: sub.name,
      phone: sub.phone,
      message: `[관심 시술] ${sub.treat}\n[상담 방법] ${methodLabel(sub.method)}\n` + (sub.method === "visit" ? `[방문 희망 일시] ${sub.visit}` : `[연락 가능 시간] ${sub.time}`) + (sub.msg ? `\n\n${sub.msg}` : ""),
    }),
    onSuccess: (_r, sub) => { if (!openRef.current) return; setDone({ method: sub.method, time: sub.time, visit: sub.visit }); setErr(null); },
    onError: (e) => { if (openRef.current) setErr(e instanceof Error ? e.message : "상담 신청 중 오류가 발생했습니다."); },
  });

  if (!open) return null;
  // 방문 상담 choices: the bookable days of the next month and the half-hour slots of the chosen day
  const vc = visitCtx.data;
  const vToday = vc ? vc.now.slice(0, 10) : "";
  const vDays = vc ? bookableDays(vc.hours, vToday) : [];
  const vDate = visitDate && vDays.includes(visitDate) ? visitDate : vDays[0] ?? "";
  const vSlots = vc && vDate ? timeSlots(vc.hours, vDate, vc.now).slots : [];
  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (checking || m.isPending) return;
    if (!selTreat) return setErr("관심 시술을 선택해주세요.");
    // 카카오톡 채널: nothing is sent to the hospital — the conversation starts in the channel
    if (sel.method === "kakao") { setErr(null); return setDone({ method: "kakao", time: "", visit: "" }); }
    if (sel.method === "visit" && (!vDate || !visitTime)) return setErr("방문 날짜와 시간을 선택해주세요.");
    if (!name.trim() || !phone.trim()) return setErr("이름과 연락처를 입력해주세요.");
    if (!agree) return setErr("개인정보 병원 제공 동의가 필요합니다.");
    if (sel.method === "visit") {
      // The slots on screen were built when the sheet opened: ask the server for the time again, so a slot that has passed since (or a day that closed) is not sent
      setChecking(true);
      const fresh = (await visitCtx.refetch()).data;
      setChecking(false);
      if (!fresh) return setErr("방문 가능 시간을 확인하지 못했습니다. 다시 시도해주세요.");
      const ok = bookableDays(fresh.hours, fresh.now.slice(0, 10)).includes(vDate)
        && timeSlots(fresh.hours, vDate, fresh.now).slots.some((t) => t.value === visitTime && !t.disabled);
      if (!ok) { setVisitTime(""); return setErr("선택한 시간이 지났습니다. 방문 시간을 다시 선택해주세요."); }
    }
    m.mutate({ treat: selTreat, method: sel.method, time: sel.time, visit: sel.method === "visit" ? formatDateTime(vDate, visitTime) : "", name: name.trim(), phone: phone.trim(), msg: msg.trim() });
  };
  const copyPhone = async () => {
    try { await navigator.clipboard.writeText(hospital.phone ?? ""); showToast("번호를 복사했습니다. 연락처에 저장해주세요."); } catch { showToast("번호를 복사하지 못했습니다."); }
  };
  const district = addrDistrict(hospital.address);

  return (
    <BottomSheet ref={sheet} open={open} onClose={afterClose} label="상담 신청">
      <div className={styles.inner}>

        {done && done.method === "kakao" ? (
          /* 05-07 */
          <div className={styles.done}>
            <div className={styles.doneIcon} aria-hidden="true"><CheckIcon /></div>
            <div className={styles.doneTitle}>카카오톡 채널에서<br />상담을 시작해주세요</div>
            <p className={styles.doneText}>병원이 먼저 카톡을 보낼 수 없어요.<br />채널로 이동해 대화를 시작하면 답변해드려요</p>
            <div className={styles.doneBtns}>
              <a href={kakaoUrl ?? "#"} target="_blank" rel="noopener" className={styles.kakaoBtn}><ChatIcon />카카오톡 채널 열기</a>
              <button type="button" className={styles.ghostBtn} onClick={closeAnimated}>나중에 할게요</button>
            </div>
          </div>
        ) : done ? (
          /* 05-06 */
          <div className={styles.done}>
            <div className={styles.doneIcon} aria-hidden="true"><CheckIcon /></div>
            <div className={styles.doneTitle}>상담 신청이 완료되었어요</div>
            <p className={styles.doneText}>{hospital.name}에서 확인 후<br />{done.method === "visit" ? `${done.visit} 방문 일정을 확정해드려요` : `${done.time === "언제든 괜찮아요" ? "가능한 시간" : `${done.time} 시간`}에 전화로 연락드려요`}</p>
            {hospital.phone && (
              <div className={styles.phoneBox}>
                <div className={styles.phoneInfo}>
                  <span className={styles.phoneLabel}>연락 번호 <b>{hospital.phone}</b></span>
                  <span className={styles.phoneNote}>모르는 번호 차단 중이면 저장해주세요</span>
                </div>
                <button type="button" className={styles.saveBtn} onClick={copyPhone}>번호 저장</button>
              </div>
            )}
            <button type="button" className={styles.submit} onClick={closeAnimated}>확인</button>
            <p className={styles.doneFoot}>연락이 닿지 않으면 앱 알림과 <Link href="/mypage/consultations" className={styles.footLink}>마이페이지 &gt; 상담 내역</Link>에서 알려드려요</p>
          </div>
        ) : (
          /* 05-05 */
          <form onSubmit={submit} className={styles.body}>
            <div className={styles.title}>상담 신청서</div>
            <div className={styles.sub}>신청하면 병원에서 확인 후 연락드려요</div>
            <div className={styles.hosp}>
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={hospitalIcon.src} alt="" width={18} height={18} className={styles.hospIco} />
              <b>{hospital.name}</b>{district && <span className={styles.hospDist}>{district}</span>}
            </div>

            <div className={styles.label}>관심 시술 <em>*</em></div>
            <div className={styles.chips}>{treatChips.map((v) => <Chip key={v} on={selTreat === v} onClick={() => setSel((s) => ({ ...s, treat: v }))}>{v}</Chip>)}</div>

            <div className={styles.label}>상담 방법 <em>*</em></div>
            <div className={styles.chips}>{methods.map((v) => <Chip key={v.key} on={sel.method === v.key} onClick={() => setSel((s) => ({ ...s, method: v.key }))}>{v.label}</Chip>)}</div>
            {sel.method === "phone" && <div className={styles.note}>{hospital.phone ? `병원 번호(${hospital.phone})로 전화드려요. ` : "병원에서 전화드려요. "}모르는 번호 · 스팸 차단 중이면 번호를 저장해주세요</div>}
            {sel.method === "kakao" && <div className={styles.note}>카카오톡 채널로 이동해 대화를 시작하면 답변해드려요</div>}

            {sel.method === "phone" && (
              <>
                <div className={styles.label}>연락 가능한 시간 <em>*</em></div>
                <div className={styles.chips}>{TIMES.map((v) => <Chip key={v} on={sel.time === v} onClick={() => setSel((s) => ({ ...s, time: v }))}>{v}</Chip>)}</div>
              </>
            )}
            {sel.method === "visit" && (
              <>
                <div className={styles.label}>방문 날짜 · 시간 <em>*</em></div>
                {visitCtx.isPending ? <div className={styles.visitNote}>방문 가능한 시간을 불러오는 중이에요</div>
                  : !vc ? <div className={styles.visitNote}>방문 가능한 시간을 불러오지 못했습니다. <button type="button" className={styles.footLink} onClick={() => visitCtx.refetch()}>다시 시도</button></div>
                  : vDays.length === 0 ? <div className={styles.visitNote}>방문 가능한 날짜가 없습니다.</div>
                  : (
                    <>
                      <div className={styles.days}>
                        {vDays.map((d) => {
                          const dt = parseDay(d);
                          return (
                            <button type="button" key={d} className={`${styles.day} ${d === vDate ? styles.dayOn : ""}`} aria-pressed={d === vDate} aria-label={`${dt.getMonth() + 1}월 ${dt.getDate()}일`}
                              onClick={() => { setVisitDate(d); setVisitTime(""); }}>
                              <span className={styles.dayWd}>{d === vToday ? "오늘" : WEEKDAYS[dt.getDay()]}</span>
                              <span className={styles.dayNum}>{dt.getDate()}</span>
                            </button>
                          );
                        })}
                      </div>
                      <div className={styles.slots}>
                        {vSlots.length === 0 ? <div className={styles.visitNote}>선택 가능한 시간이 없습니다.</div>
                          : vSlots.map((t) => (
                            <button type="button" key={t.value} disabled={t.disabled} className={`${styles.slot} ${visitTime === t.value ? styles.slotOn : ""}`} aria-pressed={visitTime === t.value} onClick={() => setVisitTime(t.value)}>{t.value}</button>
                          ))}
                      </div>
                      <div className={styles.note}>병원에서 확인 후 일정이 확정돼요</div>
                    </>
                  )}
              </>
            )}

            {/* 카카오톡 채널: nothing is sent to the hospital, so no contact fields and no consent */}
            {sel.method !== "kakao" && (
              <>
              <div className={styles.label}>이름 · 연락처 <em>*</em></div>
              <div className={styles.row}>
                <input className={`${styles.input} ${styles.inputName}`} placeholder="이름" value={name} onChange={(e) => setName(e.target.value)} maxLength={100} autoComplete="name" required />
                <input className={styles.input} type="tel" placeholder="010-0000-0000" value={phone} onChange={(e) => setPhone(e.target.value)} maxLength={30} autoComplete="tel" required />
              </div>

              <div className={styles.label}>상담 내용 <span className={styles.opt}>(선택)</span></div>
              <textarea className={styles.textarea} placeholder="궁금한 점이나 원하는 부위를 적어주세요" value={msg} onChange={(e) => setMsg(e.target.value)} maxLength={2500} rows={2} />

              <div className={styles.agreeRow}>
                <label className={styles.agree}>
                  <input type="checkbox" className={styles.check} checked={agree} onChange={(e) => setAgree(e.target.checked)} />
                  <span className={styles.checkBox} aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
                  </span>
                  <span><span className={styles.req}>(필수)</span> 개인정보 병원 제공 동의</span>
                </label>
                <button type="button" className={styles.view} onClick={() => setShowPolicy((v) => !v)} aria-expanded={showPolicy}>{showPolicy ? "닫기" : "보기"}</button>
              </div>
              {showPolicy && (
                <div className={styles.policy}>
                  상담 처리를 위해 이름·연락처·상담 내용을 <b>{hospital.name}</b>에 제공합니다. 보유 기간은 상담 완료 후 3년(소비자 분쟁해결 기준)이며, 동의하지 않으면 상담 신청이 제한됩니다.{" "}
                  <Link href="/privacy" className={styles.footLink}>개인정보처리방침</Link>
                </div>
              )}
              </>
            )}
            {err && <div className={styles.err} role="alert">{err}</div>}
            <button type="submit" className={styles.submit} disabled={m.isPending || checking} aria-busy={m.isPending || checking}>{m.isPending || checking ? "전송중..." : "신청하기"}</button>
          </form>
        )}
      </div>
    </BottomSheet>
  );
}

/** Module-level so React keeps the same element across renders (focus survives a selection or a keystroke). */
function Chip({ on, onClick, children }: { on: boolean; onClick: () => void; children: React.ReactNode }) {
  return <button type="button" className={`${styles.chip} ${on ? styles.chipOn : ""}`} aria-pressed={on} onClick={onClick}>{children}</button>;
}

/** White check inside the 44px accent circle (05-06, 05-07). */
const CheckIcon = () => (
  <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="#fff" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
);

/** Outlined speech bubble (two short lines, tail at the bottom left) on the 채널 열기 button. */
const ChatIcon = () => (
  <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#191919" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
    <path d="M6 4h12a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9.5L4 21V6a2 2 0 0 1 2-2z" /><path d="M8.5 9h7M8.5 12.5h4.5" />
  </svg>
);
