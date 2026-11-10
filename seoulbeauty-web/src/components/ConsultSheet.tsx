"use client";
import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { useToast } from "./Toast";
import { useMutation, useQuery } from "@tanstack/react-query";
import { api, qk, type HospitalDetail } from "@/lib/api";
import { useMe } from "@/lib/me";
import { addrDistrict } from "./HospitalCard";
import BottomSheet, { type BottomSheetHandle } from "./BottomSheet";
import styles from "./ConsultSheet.module.css";

type Method = "phone" | "kakao" | "visit";
const METHODS: { key: Method; label: string }[] = [{ key: "phone", label: "전화 상담" }, { key: "kakao", label: "카카오톡 채널" }, { key: "visit", label: "방문 상담" }];
const TIMES = ["오전", "오후", "저녁", "언제든 괜찮아요"];
const DEFAULT_TREATS = ["보톡스", "필러", "리프팅", "스킨부스터"];

/**
 * design 05-05 상담 신청서 bottom sheet → 05-06 (전화/방문: 완료 + 병원 번호) or 05-07 (카카오톡: 채널 열기).
 * Posts the same consultation_submit.php payload as before (chips become the message prefix); photos not ported.
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
  const [done, setDone] = useState<null | { method: Method; time: string }>(null);
  const [err, setErr] = useState<string | null>(null);
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
  type Submission = { treat: string; method: Method; time: string; name: string; phone: string; msg: string };
  const m = useMutation({
    mutationFn: (sub: Submission) => api.consult({
      hospitalId: hospital.id,
      type: sub.method === "kakao" ? "kakao" : "doctor",
      name: sub.name,
      phone: sub.phone,
      message: `[관심 시술] ${sub.treat}\n[상담 방법] ${methodLabel(sub.method)}\n[연락 가능 시간] ${sub.time}` + (sub.msg ? `\n\n${sub.msg}` : ""),
    }),
    onSuccess: (_r, sub) => { if (!openRef.current) return; setDone({ method: sub.method, time: sub.time }); setErr(null); },
    onError: (e) => { if (openRef.current) setErr(e instanceof Error ? e.message : "상담 신청 중 오류가 발생했습니다."); },
  });

  if (!open) return null;
  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selTreat) return setErr("관심 시술을 선택해주세요.");
    if (!name.trim() || !phone.trim()) return setErr("이름과 연락처를 입력해주세요.");
    if (!agree) return setErr("개인정보 병원 제공 동의가 필요합니다.");
    m.mutate({ treat: selTreat, method: sel.method, time: sel.time, name: name.trim(), phone: phone.trim(), msg: msg.trim() });
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
            <div className={styles.doneIcon} aria-hidden="true"><KakaoIcon /></div>
            <div className={styles.doneTitle}>카카오톡 채널에서<br />상담을 시작해주세요</div>
            <p className={styles.doneText}>병원이 먼저 카톡을 보낼 수 없어요.<br />채널로 이동해 대화를 시작하면 답변해드려요</p>
            <div className={styles.doneBtns}>
              <a href={kakaoUrl ?? "#"} target="_blank" rel="noopener" className={styles.kakaoBtn}><KakaoIcon />카카오톡 채널 열기</a>
              <button type="button" className={styles.ghostBtn} onClick={closeAnimated}>나중에 할게요</button>
            </div>
            <p className={styles.doneFoot}>진행 상황은 <Link href="/mypage/consultations" className={styles.footLink}>마이페이지 &gt; 상담 내역</Link>에서 알려드려요</p>
          </div>
        ) : done ? (
          /* 05-06 */
          <div className={styles.done}>
            <div className={styles.doneIcon} aria-hidden="true">
              <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#fff" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
            </div>
            <div className={styles.doneTitle}>상담 신청이 완료되었어요</div>
            <p className={styles.doneText}>{hospital.name}에서 확인 후<br />{done.time === "언제든 괜찮아요" ? "가능한 시간" : `${done.time} 시간`}에 {done.method === "visit" ? "방문 일정을 안내" : "전화로 연락"}드려요</p>
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
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#1A1718" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M4 21V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v16" /><path d="M9 21v-5h6v5M12 7v4M10 9h4" /></svg>
              <b>{hospital.name}</b>{district && <span className={styles.hospDist}>{district}</span>}
            </div>

            <div className={styles.label}>관심 시술 <em>*</em></div>
            <div className={styles.chips}>{treatChips.map((v) => <Chip key={v} on={selTreat === v} onClick={() => setSel((s) => ({ ...s, treat: v }))}>{v}</Chip>)}</div>

            <div className={styles.label}>상담 방법 <em>*</em></div>
            <div className={styles.chips}>{methods.map((v) => <Chip key={v.key} on={sel.method === v.key} onClick={() => setSel((s) => ({ ...s, method: v.key }))}>{v.label}</Chip>)}</div>
            {sel.method === "phone" && <div className={styles.note}>{hospital.phone ? `병원 번호(${hospital.phone})로 전화드려요. ` : "병원에서 전화드려요. "}모르는 번호 · 스팸 차단 중이면 번호를 저장해주세요</div>}
            {sel.method === "kakao" && <div className={styles.note}>신청 후 카카오톡 채널로 이동해 대화를 시작하면 답변해드려요</div>}

            <div className={styles.label}>연락 가능한 시간 <em>*</em></div>
            <div className={styles.chips}>{TIMES.map((v) => <Chip key={v} on={sel.time === v} onClick={() => setSel((s) => ({ ...s, time: v }))}>{v}</Chip>)}</div>

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
            {err && <div className={styles.err} role="alert">{err}</div>}
            <button type="submit" className={styles.submit} disabled={m.isPending} aria-busy={m.isPending}>{m.isPending ? "전송중..." : "신청하기"}</button>
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

const KakaoIcon = () => (
  <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path fill="#191919" d="M12 3C6.5 3 2 6.6 2 11c0 2.8 1.8 5.2 4.5 6.6L5.6 21c-.1.3.3.6.6.4l4.2-2.8c.5.1 1 .1 1.6.1 5.5 0 10-3.6 10-8S17.5 3 12 3z" /></svg>
);
