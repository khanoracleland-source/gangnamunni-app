"use client";

import { useEffect, useId, useState, type KeyboardEvent } from "react";
import Icon from "../Icon";
import { useT } from "@/lib/locale";

type Option = { id: number; name: string };

/** A hospital picker you can type into. An empty box means all hospitals: clearing the text goes back to them. */
export default function HospitalCombo({ hospitals, value, onChange }: { hospitals: Option[]; value: number; onChange: (id: number) => void }) {
  const t = useT();
  const id = useId();
  const nameOf = (v: number): string => (v === 0 ? "" : (hospitals.find((h) => h.id === v)?.name ?? ""));
  const [text, setText] = useState(() => nameOf(value));
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(0);

  // While the box still shows the chosen name nothing is filtered out; typing narrows the list.
  const needle = text.trim().toLowerCase();
  const filtering = needle !== "" && text !== nameOf(value);
  const rows = hospitals.filter((h) => !filtering || h.name.toLowerCase().includes(needle));
  const options: Option[] = [{ id: 0, name: t("pay.f.hospitalAll") }, ...rows];

  const pick = (o: Option) => {
    onChange(o.id);
    setText(o.id === 0 ? "" : o.name);
    setOpen(false);
  };
  const clear = () => {
    onChange(0);
    setText("");
    setActive(0);
    setOpen(true);
  };

  const onKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === "ArrowDown" || e.key === "ArrowUp") {
      e.preventDefault();
      setOpen(true);
      setActive((i) => (e.key === "ArrowDown" ? Math.min(options.length - 1, i + 1) : Math.max(0, i - 1)));
    } else if (e.key === "Enter") {
      e.preventDefault();
      if (!open) setOpen(true);
      else if (options[active]) pick(options[active]);
    } else if (e.key === "Escape") {
      setOpen(false);
      setText(nameOf(value));
    }
  };

  // The highlighted row scrolls into view while moving with the arrows.
  useEffect(() => {
    if (open) document.getElementById(`${id}-${active}`)?.scrollIntoView({ block: "nearest" });
  }, [active, open, id]);

  return (
    <div className="field hcombo">
      <label className="field__label" htmlFor={`${id}-input`}>{t("pay.f.hospital")}</label>
      <div className="hpick__box">
        <Icon name="search" size={18} className="hpick__icon" />
        <input
          id={`${id}-input`}
          className="input hpick__input hcombo__input"
          type="text"
          role="combobox"
          aria-expanded={open}
          aria-controls={`${id}-list`}
          aria-autocomplete="list"
          aria-activedescendant={open && options[active] ? `${id}-${active}` : undefined}
          value={text}
          placeholder={t("pay.f.hospitalSearch")}
          autoComplete="off"
          autoCapitalize="off"
          spellCheck={false}
          onFocus={(e) => { setOpen(true); e.currentTarget.select(); }}
          onBlur={() => { setOpen(false); setText(nameOf(value)); }}
          onChange={(e) => {
            const v = e.target.value;
            setText(v);
            setOpen(true);
            setActive(v.trim() === "" ? 0 : 1);
            // Nothing left in the box: show all hospitals again.
            if (v.trim() === "" && value !== 0) onChange(0);
          }}
          onKeyDown={onKeyDown}
        />
        {text !== "" || value !== 0 ? (
          <button type="button" className="hcombo__clear" aria-label={t("pay.f.hospitalClear")} onMouseDown={(e) => e.preventDefault()} onClick={clear}><Icon name="close" size={16} /></button>
        ) : null}
      </div>
      {open ? (
        <ul id={`${id}-list`} className="hpick__list hcombo__list" role="listbox" aria-label={t("pay.f.hospital")}>
          {options.map((o, i) => (
            <li
              key={o.id}
              id={`${id}-${i}`}
              role="option"
              aria-selected={o.id === value}
              className={i === active ? "hcombo__opt is-active" : "hcombo__opt"}
              onMouseMove={() => { if (i !== active) setActive(i); }}
              onMouseDown={(e) => e.preventDefault()}
              onClick={() => pick(o)}
            >
              <span translate="no">{o.name}</span>{o.id === value ? <Icon name="check" size={16} className="hpick__tick" /> : null}
            </li>
          ))}
          {filtering && rows.length === 0 ? <li className="hcombo__none" role="presentation">{t("pay.f.hospitalNone")}</li> : null}
        </ul>
      ) : null}
    </div>
  );
}
