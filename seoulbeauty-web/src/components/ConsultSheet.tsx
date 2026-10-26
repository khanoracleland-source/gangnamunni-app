"use client";
import { useEffect, useState } from "react";
import { useMutation, useQuery } from "@tanstack/react-query";
import { api, qk, type HospitalDetail } from "@/lib/api";
import { useMe } from "@/lib/me";
import styles from "./ConsultSheet.module.css";

const METHODS = ["전화 상담", "카카오톡 채널", "방문 상담"];
const TIMES = ["오전", "오후", "저녁", "언제든 괜찮아요"];
const DEFAULT_TREATS = ["보톡스", "필러", "리프팅", "스킨부스터"];

/** detail.php 상담 신청 bottom-sheet (chips → message prefix, same as the PHP form). Photos not ported yet. */
export default function ConsultSheet({ hospital, open, onClose }: { hospital: HospitalDetail; open: boolean; onClose: () => void }) {
  const me = useMe();
  const treat = useQuery({ queryKey: qk.treatments(hospital.id), queryFn: () => api.treatments(hospital.id), enabled: open });
  const chips = (treat.data?.items.map((t) => t.name) ?? []).slice(0, 8);
  const treatChips = chips.length ? chips : DEFAULT_TREATS;
  const [sel, setSel] = useState<{ treat: string | null; method: string; time: string }>({ treat: null, method: METHODS[0], time: TIMES[1] });
  // null = untouched → falls back to the member profile (detail.php prefills the same way)
  const [nameEdit, setName] = useState<string | null>(null);
  const [phoneEdit, setPhone] = useState<string | null>(null);
  const name = nameEdit ?? me.data?.user?.name ?? "";
  const phone = phoneEdit ?? me.data?.user?.phone ?? "";
  const selTreat = sel.treat ?? treatChips[0] ?? "";
  const [msg, setMsg] = useState("");
  const [agree, setAgree] = useState(false);
  const [done, setDone] = useState(false);
  const [err, setErr] = useState<string | null>(null);

  useEffect(() => { document.body.style.overflow = open ? "hidden" : ""; return () => { document.body.style.overflow = ""; }; }, [open]);

  const m = useMutation({
    mutationFn: () => api.consult({
      hospitalId: hospital.id,
      type: "doctor",
      name: name.trim(),
      phone: phone.trim(),
      message: `[관심 시술] ${selTreat}\n[상담 방법] ${sel.method}\n[연락 가능 시간] ${sel.time}` + (msg.trim() ? `\n\n${msg.trim()}` : ""),
    }),
    onSuccess: () => { setDone(true); setErr(null); },
    onError: (e) => setErr(e instanceof Error ? e.message : "상담 신청 중 오류가 발생했습니다."),
  });

  if (!open) return null;
  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selTreat) return setErr("관심 시술을 선택해주세요.");
    if (!agree) return setErr("개인정보 병원 제공 동의가 필요합니다.");
    if (!name.trim() || !phone.trim()) return setErr("이름과 연락처를 입력해주세요.");
    m.mutate();
  };
  const current = { treat: selTreat, method: sel.method, time: sel.time };
  const chipsFor = (items: string[], group: "treat" | "method" | "time") => (
    <div className={styles.chips}>
      {items.map((v) => (
        <button key={v} type="button" className={`${styles.chip} ${current[group] === v ? styles.on : ""}`} onClick={() => setSel((s) => ({ ...s, [group]: v }))}>{v}</button>
      ))}
    </div>
  );

  return (
    <div className={styles.backdrop} onClick={onClose} role="dialog" aria-modal="true" aria-label="상담 신청">
      <div className={styles.sheet} onClick={(e) => e.stopPropagation()}>
        <div className={styles.grab} />
        {done ? (
          <div className={styles.done}>
            <div className={styles.doneTitle}>상담 신청이 접수되었습니다</div>
            <p className={styles.doneText}>{sel.method} · {sel.time}<br />병원에서 확인 후 연락드려요. 진행 상황은 마이페이지 &gt; 상담 내역에서 알려드려요.</p>
            <button type="button" className={styles.submit} onClick={onClose}>확인</button>
          </div>
        ) : (
          <form onSubmit={submit} className={styles.body}>
            <div className={styles.title}>상담 신청서</div>
            <div className={styles.sub}>신청하면 병원에서 확인 후 연락드려요</div>
            <div className={styles.hosp}>🏥 <b>{hospital.name}</b></div>
            <div className={styles.label}>관심 시술 <em>*</em></div>
            {chipsFor(treatChips, "treat")}
            <div className={styles.label}>상담 방법 <em>*</em></div>
            {chipsFor(METHODS, "method")}
            <div className={styles.note}>{hospital.phone ? `병원 번호(${hospital.phone})로 전화드려요. ` : "병원에서 전화드려요. "}모르는 번호도 받아주세요.</div>
            <div className={styles.label}>연락 가능한 시간 <em>*</em></div>
            {chipsFor(TIMES, "time")}
            <div className={styles.label}>이름 · 연락처 <em>*</em></div>
            <div className={styles.row}>
              <input className={styles.input} placeholder="이름" value={name} onChange={(e) => setName(e.target.value)} maxLength={100} required />
              <input className={styles.input} type="tel" placeholder="연락처" value={phone} onChange={(e) => setPhone(e.target.value)} maxLength={30} required />
            </div>
            <div className={styles.label}>상담 내용 <span className={styles.opt}>(선택)</span></div>
            <textarea className={styles.textarea} placeholder="궁금한 점이나 원하는 부위를 적어주세요" value={msg} onChange={(e) => setMsg(e.target.value)} maxLength={2500} />
            <label className={styles.agree}>
              <input type="checkbox" checked={agree} onChange={(e) => setAgree(e.target.checked)} />
              <span>상담을 위해 이름·연락처·상담 내용을 병원에 제공하는 데 동의합니다.</span>
            </label>
            {err && <div className={styles.err} role="alert">{err}</div>}
            <button type="submit" className={styles.submit} disabled={m.isPending} aria-busy={m.isPending}>{m.isPending ? "전송중..." : "신청하기"}</button>
          </form>
        )}
      </div>
    </div>
  );
}
