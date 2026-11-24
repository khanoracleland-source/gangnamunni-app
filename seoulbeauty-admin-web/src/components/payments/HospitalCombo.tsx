"use client";

import { useCallback, useEffect, useId, useRef, useState, type KeyboardEvent, type ReactNode } from "react";
import Icon from "../Icon";
import { useT } from "@/lib/locale";

type Option = { id: number; name: string };

/** The part of `name` that matches the typed text is drawn bold-accent; the rest stays plain. */
function highlighted(name: string, needle: string): ReactNode {
  if (needle === "") return name;
  const at = name.toLowerCase().indexOf(needle);
  if (at < 0) return name;
  return (
    <>
      {name.slice(0, at)}
      <span className="hpick__hit">{name.slice(at, at + needle.length)}</span>
      {name.slice(at + needle.length)}
    </>
  );
}

/** The nearest ancestor that scrolls vertically (a dialog body), or null when the page itself scrolls. */
function scrollParent(el: HTMLElement | null): HTMLElement | null {
  for (let p = el?.parentElement ?? null; p; p = p.parentElement) {
    const y = getComputedStyle(p).overflowY;
    if ((y === "auto" || y === "scroll") && p.scrollHeight > p.clientHeight) return p;
  }
  return null;
}

/**
 * A hospital picker you can type into. With an "all" row (default) an empty box means all hospitals and clearing the text goes back to them;
 * with `allLabel={null}` the box always keeps one hospital: clearing only empties the text and the old choice returns when the box is left.
 */
export default function HospitalCombo({
  hospitals,
  value,
  onChange,
  label,
  placeholder,
  clearLabel,
  noneLabel,
  allLabel,
  disabled = false,
  invalid = false,
}: {
  hospitals: Option[];
  value: number;
  onChange: (id: number) => void;
  /** Texts default to the payments page's. */
  label?: string;
  placeholder?: string;
  clearLabel?: string;
  noneLabel?: string;
  /** Text of the first row (value 0, "all hospitals"). `null` = no such row: the box then picks exactly one hospital. */
  allLabel?: string | null;
  disabled?: boolean;
  invalid?: boolean;
}) {
  const t = useT();
  const id = useId();
  const hasAll = allLabel !== null;
  const rootRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const nameOf = (v: number): string => (v === 0 ? "" : (hospitals.find((h) => h.id === v)?.name ?? ""));
  const [text, setText] = useState(() => nameOf(value));
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(0);
  // The row just picked: blur (fired right after picking on a phone) must not put the old name back.
  const pickedRef = useRef<Option | null>(null);
  // The chosen hospital changed from outside (e.g. the page reloaded its filter): the box shows the new name.
  const [seen, setSeen] = useState(value);
  if (seen !== value) {
    setSeen(value);
    setText(nameOf(value));
  }

  // While the box still shows the chosen name nothing is filtered out; typing narrows the list.
  const needle = text.trim().toLowerCase();
  const filtering = needle !== "" && text !== nameOf(value);
  const rows = hospitals.filter((h) => !filtering || h.name.toLowerCase().includes(needle));
  const options: Option[] = hasAll ? [{ id: 0, name: allLabel ?? t("pay.f.hospitalAll") }, ...rows] : rows;

  /** Opens the list with the chosen hospital highlighted (so a long list starts at it, not at the top). */
  const openList = () => {
    if (open) return;
    const at = options.findIndex((o) => o.id === value && (o.id !== 0 || hasAll));
    setActive(Math.max(0, at));
    setOpen(true);
  };
  const pick = (o: Option) => {
    pickedRef.current = o;
    onChange(o.id);
    setText(o.id === 0 ? "" : o.name);
    setOpen(false);
    // On a phone the keyboard goes away, so the sheet has its height back.
    if (window.matchMedia("(pointer: coarse)").matches) inputRef.current?.blur();
    pickedRef.current = null;
  };
  const clear = () => {
    if (hasAll) onChange(0);
    setText("");
    setActive(0);
    setOpen(true);
    inputRef.current?.focus();
  };

  const onKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
    const last = options.length - 1;
    if (e.key === "ArrowDown" || e.key === "ArrowUp") {
      e.preventDefault();
      if (!open) {
        openList();
        return;
      }
      setActive((i) => (e.key === "ArrowDown" ? Math.min(last, i + 1) : Math.max(0, i - 1)));
    } else if (open && (e.key === "PageDown" || e.key === "PageUp")) {
      e.preventDefault();
      setActive((i) => (e.key === "PageDown" ? Math.min(last, i + 8) : Math.max(0, i - 8)));
    } else if (open && e.key === "Home" && e.ctrlKey) {
      e.preventDefault();
      setActive(0);
    } else if (open && e.key === "End" && e.ctrlKey) {
      e.preventDefault();
      setActive(Math.max(0, last));
    } else if (e.key === "Enter") {
      e.preventDefault();
      if (!open) openList();
      else if (options[active]) pick(options[active]);
    } else if (e.key === "Escape" && open) {
      // The first Esc only closes the list; the dialog around the box keeps open.
      e.preventDefault();
      e.stopPropagation();
      setOpen(false);
      setText(nameOf(value));
    }
  };

  // The highlighted row scrolls into view while moving with the arrows.
  useEffect(() => {
    if (open) document.getElementById(`${id}-${active}`)?.scrollIntoView({ block: "nearest" });
  }, [active, open, id]);

  // Room below the box (inside a scrolling dialog body, above the on-screen keyboard): the list never grows past it.
  const measure = useCallback(() => {
    const root = rootRef.current;
    const input = inputRef.current;
    if (!root || !input) return;
    const parent = scrollParent(root);
    const vv = window.visualViewport;
    const viewBottom = vv ? vv.offsetTop + vv.height : window.innerHeight;
    const bottom = Math.min(parent ? parent.getBoundingClientRect().bottom : viewBottom, viewBottom);
    let room = bottom - input.getBoundingClientRect().bottom - 12;
    if (room < 220 && parent) {
      parent.scrollTop += input.getBoundingClientRect().top - parent.getBoundingClientRect().top - 72;
      room = bottom - input.getBoundingClientRect().bottom - 12;
    }
    root.style.setProperty("--hc-room", `${Math.max(120, Math.floor(room))}px`);
  }, []);
  useEffect(() => {
    if (!open) return;
    measure();
    const vv = window.visualViewport;
    window.addEventListener("resize", measure);
    vv?.addEventListener("resize", measure);
    vv?.addEventListener("scroll", measure);
    return () => {
      window.removeEventListener("resize", measure);
      vv?.removeEventListener("resize", measure);
      vv?.removeEventListener("scroll", measure);
    };
  }, [open, measure]);

  const countText = filtering ? t("d.hc.count", { n: rows.length }) : "";

  return (
    <div className="field hcombo" ref={rootRef}>
      <label className="field__label" htmlFor={`${id}-input`}>{label ?? t("pay.f.hospital")}</label>
      <div className="hpick__box">
        <Icon name="search" size={18} className="hpick__icon" />
        <input
          ref={inputRef}
          id={`${id}-input`}
          className="input hpick__input hcombo__input"
          type="text"
          role="combobox"
          aria-expanded={open}
          aria-controls={`${id}-list`}
          aria-autocomplete="list"
          aria-activedescendant={open && options[active] ? `${id}-${active}` : undefined}
          value={text}
          title={text}
          placeholder={placeholder ?? t("pay.f.hospitalSearch")}
          disabled={disabled}
          aria-invalid={invalid || undefined}
          autoComplete="off"
          autoCapitalize="off"
          spellCheck={false}
          onFocus={(e) => { openList(); e.currentTarget.select(); }}
          onClick={openList}
          onBlur={() => { setOpen(false); const p = pickedRef.current; setText(p ? (p.id === 0 ? "" : p.name) : nameOf(value)); }}
          onChange={(e) => {
            const v = e.target.value;
            setText(v);
            setOpen(true);
            setActive(v.trim() === "" || !hasAll ? 0 : 1);
            // Nothing left in the box: show all hospitals again (only when there is an "all" row).
            if (hasAll && v.trim() === "" && value !== 0) onChange(0);
          }}
          onKeyDown={onKeyDown}
        />
        {!disabled && (text !== "" || (hasAll && value !== 0)) ? (
          <button type="button" className="hcombo__clear" aria-label={clearLabel ?? t("pay.f.hospitalClear")} onMouseDown={(e) => e.preventDefault()} onClick={clear}><Icon name="close" size={16} /></button>
        ) : null}
      </div>
      <div className="sr-only" role="status" aria-live="polite">{open ? countText : ""}</div>
      {open ? (
        <ul id={`${id}-list`} className="hpick__list hcombo__list" role="listbox" aria-label={label ?? t("pay.f.hospital")} onMouseDown={(e) => e.preventDefault()}>
          {countText !== "" ? <li className="hcombo__count" role="presentation">{countText}</li> : null}
          {options.map((o, i) => (
            <li
              key={o.id}
              id={`${id}-${i}`}
              role="option"
              aria-selected={o.id === value}
              className={`hcombo__opt${i === active ? " is-active" : ""}${hasAll && o.id === 0 ? " hcombo__opt--all" : ""}`}
              onMouseMove={() => { if (i !== active) setActive(i); }}
              onClick={() => pick(o)}
            >
              <span translate="no">{o.id === 0 && hasAll ? o.name : highlighted(o.name, filtering ? needle : "")}</span>{o.id === value && (o.id !== 0 || hasAll) ? <Icon name="check" size={16} className="hpick__tick" /> : null}
            </li>
          ))}
          {filtering && rows.length === 0 ? <li className="hcombo__none" role="presentation">{noneLabel ?? t("pay.f.hospitalNone")}</li> : null}
        </ul>
      ) : null}
    </div>
  );
}
