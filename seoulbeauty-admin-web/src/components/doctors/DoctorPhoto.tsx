import { doctorInitial, doctorPhotoUrl } from "@/lib/doctors";

/** The doctor's picture (rounded square), or the first letter of the name when there is none or the address is not one of ours. */
export default function DoctorPhoto({ name, url, size = "md" }: { name: string; url: string | null; size?: "md" | "lg" }) {
  const src = doctorPhotoUrl(url);
  const cls = size === "lg" ? "dc-photo dc-photo--lg" : "dc-photo";
  // eslint-disable-next-line @next/next/no-img-element
  if (src) return <img className={cls} src={src} alt="" loading="lazy" decoding="async" referrerPolicy="no-referrer" />;
  return <span className={`${cls} dc-photo--ini`} aria-hidden="true">{doctorInitial(name)}</span>;
}
