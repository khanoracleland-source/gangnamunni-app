"use client";
import { useSearchParams } from "next/navigation";
import type { HospitalListParams, HospitalSort } from "@/lib/api";
import HospitalList from "./HospitalList";
import HospitalSearchView from "./HospitalSearchView";
import SiteHeader from "./SiteHeader";

const toParams = (sp: URLSearchParams): HospitalListParams => ({
  q: sp.get("q") || "",
  category: sp.get("category") || "",
  treatment: sp.get("treatment") || "",
  region: sp.get("region") || "",
  dosage: sp.get("dosage") || "",
  sort: (sp.get("sort") || "") as HospitalSort,
});

/** hospitals.php: the page is a static shell; the list (or the hid search view) loads in the browser from the query cache. */
export default function HospitalsPage() {
  const sp = useSearchParams();
  const params = toParams(sp);
  const q = params.q || "";
  const hid = Number(sp.get("hid"));
  if (Number.isInteger(hid) && hid > 0) {
    return (
      <>
        <SiteHeader key={`hid-${hid}-${q}`} mode="results" q={q} />
        <HospitalSearchView hid={hid} q={q} tcat={Number(sp.get("tcat")) || 0} sort={sp.get("sort") || ""} />
      </>
    );
  }
  return (
    <>
      {/* keyed by the keyword so a new search remounts the header (overlay closed, keyword shown) */}
      <SiteHeader key={`list-${q}`} mode={q ? "results" : "nav"} q={q} />
      <HospitalList params={params} />
    </>
  );
}
