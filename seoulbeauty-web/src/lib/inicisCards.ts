/**
 * Card companies shown in the payment page's card sheet (design 05-30), with KG Inicis' "결제요청 시 카드코드"
 * (manual.inicis.com/pay/code.html — the request codes, which differ from the approval-response codes).
 * 토스뱅크 and 기업 are in the design but have no request code in the manual, so they are not offered.
 * The server accepts only these codes (seoulbeauty-api DIRECT_CARD_CODES).
 */
export const INICIS_CARDS: { code: string; name: string }[] = [
  { code: "14", name: "신한" }, { code: "04", name: "현대" }, { code: "12", name: "삼성" }, { code: "03", name: "롯데" },
  { code: "01", name: "하나" }, { code: "06", name: "국민" }, { code: "11", name: "비씨" }, { code: "41", name: "농협" },
  { code: "02", name: "우리" }, { code: "56", name: "카카오뱅크" }, { code: "55", name: "케이뱅크" }, { code: "43", name: "씨티" },
  { code: "54", name: "새마을" }, { code: "71", name: "우체국" }, { code: "48", name: "신협" }, { code: "32", name: "광주은행" },
  { code: "33", name: "전북" }, { code: "35", name: "산업" }, { code: "52", name: "제주" }, { code: "95", name: "저축" },
];

/** Card installments need a payment of 50,000 won or more; 일시불 ("00") is always possible. */
export const INSTALLMENT_MIN_AMOUNT = 50000;
export const quotaLabel = (q: string) => (q === "00" ? "일시불" : `${Number(q)}개월`);
export const quotaOptions = (amount: number): string[] =>
  ["00", ...(amount >= INSTALLMENT_MIN_AMOUNT ? Array.from({ length: 11 }, (_, i) => String(i + 2).padStart(2, "0")) : [])];
