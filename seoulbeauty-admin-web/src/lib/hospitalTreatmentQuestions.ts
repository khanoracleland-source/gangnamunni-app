// Pure rules of the "pre-treatment questions" window: the draft rows, the checks the server also makes, and what a refused save says. No DOM.

import type { WriteFailure } from "./api";
import type { MessageKey } from "./i18n";

export const MAX_QUESTIONS = 10;
/** Characters (code points) of one question after trimming and collapsing white space. */
export const MAX_QUESTION_LENGTH = 200;
/** From COUNTER_FROM characters on a row shows its character counter; from COUNTER_WARN_FROM the counter warns. */
export const COUNTER_FROM = 160;
export const COUNTER_WARN_FROM = 180;

/** One row of the window as it is typed. */
export type QuestionDraft = { key: number; text: string };

let nextKey = 1;
export const newQuestionDraft = (text = ""): QuestionDraft => ({ key: nextKey++, text });
export const draftsFromQuestions = (questions: readonly string[]): QuestionDraft[] => questions.map((text) => newQuestionDraft(text));

/** The text as the server keeps it: trimmed, every run of white space turned into one space. */
export const normalizeQuestion = (text: string): string => text.trim().replace(/\s+/gu, " ");

/** Length as people (and the server) count it: code points, not UTF-16 units. */
export const questionLength = (text: string): number => Array.from(text).length;

/** What the window shows of the typed rows when it is compared with its first state. */
export const draftSignature = (drafts: readonly QuestionDraft[]): string => JSON.stringify(drafts.map((d) => d.text));

export type RowInfo = {
  /** The row is empty after normalizing: it is not saved. */
  blank: boolean;
  /** 1-based number of the first earlier row with the same text: this row is not saved. */
  duplicateOf: number | null;
};

/** Per row: is it empty, is it the same as an earlier row. Same text means equal after normalizing. */
export function rowInfos(drafts: readonly QuestionDraft[]): RowInfo[] {
  const first = new Map<string, number>();
  return drafts.map((d, i) => {
    const text = normalizeQuestion(d.text);
    if (text === "") return { blank: true, duplicateOf: null };
    const seen = first.get(text);
    if (seen !== undefined) return { blank: false, duplicateOf: seen + 1 };
    first.set(text, i);
    return { blank: false, duplicateOf: null };
  });
}

export type QuestionErrorCode = "too_long" | "too_many" | "invalid_field";
export type QuestionError = { code: QuestionErrorCode; /** The screen row of the refused question. */ index: number | null; length: number | null };

export type QuestionCheck = { ok: true; questions: string[]; rows: number[] } | { ok: false; error: QuestionError };

/**
 * The same checks as the server: normalize, drop empty rows and repeats, then refuse a question over 200 characters
 * and more than 10 questions. `rows[i]` is the screen row of the i-th question that will be sent, so a refusal that
 * names a position in the sent list can be shown on the right row.
 */
export function checkQuestions(drafts: readonly QuestionDraft[]): QuestionCheck {
  const questions: string[] = [];
  const rows: number[] = [];
  const seen = new Set<string>();
  for (let i = 0; i < drafts.length; i += 1) {
    const text = normalizeQuestion(drafts[i].text);
    if (text === "" || seen.has(text)) continue;
    const length = questionLength(text);
    if (length > MAX_QUESTION_LENGTH) return { ok: false, error: { code: "too_long", index: i, length } };
    seen.add(text);
    questions.push(text);
    rows.push(i);
  }
  if (questions.length > MAX_QUESTIONS) return { ok: false, error: { code: "too_many", index: null, length: null } };
  return { ok: true, questions, rows };
}

/** Moves one row; out of range returns the list unchanged. */
export function moveDraft<T>(list: readonly T[], from: number, to: number): T[] {
  if (from === to || from < 0 || to < 0 || from >= list.length || to >= list.length) return [...list];
  const next = [...list];
  const [row] = next.splice(from, 1);
  next.splice(to, 0, row);
  return next;
}

/** What a refused save says. `refresh`: the list on screen is probably out of date, so the card reads it again. */
export type QuestionFailure = {
  /** The line above the list. */
  key: MessageKey;
  vars: Record<string, number>;
  error: QuestionError | null;
  refresh: boolean;
};

/** A refused save (from the server) mapped back to the screen: a named position of the sent list becomes its screen row through `rows`. */
export function questionSaveFailure(f: WriteFailure, rows: readonly number[], drafts: readonly QuestionDraft[]): QuestionFailure {
  const generic: QuestionFailure = { key: "h.e.tq.err.generic", vars: {}, error: null, refresh: false };
  if (f.status === 0) return { ...generic, key: "h.e.tr.err.network" };
  if (f.status === 400) {
    const row = typeof f.index === "number" && f.index >= 0 && f.index < rows.length ? rows[f.index] : null;
    if (f.error === "too_many") return { key: "h.e.tq.err.too_many", vars: {}, error: { code: "too_many", index: null, length: null }, refresh: false };
    if (f.error === "too_long") {
      const length = row === null ? null : questionLength(normalizeQuestion(drafts[row]?.text ?? ""));
      if (row === null) return generic;
      return { key: "h.e.tq.err.sum.too_long", vars: { i: row + 1 }, error: { code: "too_long", index: row, length }, refresh: false };
    }
    if (f.error === "invalid_field") {
      if (row === null) return generic;
      return { key: "h.e.tq.err.invalid_field", vars: { i: row + 1 }, error: { code: "invalid_field", index: row, length: null }, refresh: false };
    }
    return generic;
  }
  if (f.status === 403) return { ...generic, key: "h.e.tq.err.forbidden" };
  if (f.status === 404) return { ...generic, key: "h.e.tq.err.notfound", refresh: true };
  if (f.status === 503) {
    if (f.error === "questions_table_unavailable") return { ...generic, key: "h.e.tq.err.questions_table_unavailable" };
    if (f.error === "write_unavailable") return { ...generic, key: "h.e.tq.err.write_unavailable" };
    return { ...generic, key: "h.e.tq.err.write_unavailable" };
  }
  return generic;
}

/** True when the treatment row shows the question chip as a button. */
export const canEditQuestions = (set: { canManage?: boolean; questionsEnabled?: boolean }): boolean => set.canManage === true && set.questionsEnabled === true;
