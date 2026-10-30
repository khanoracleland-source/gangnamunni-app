import { Suspense } from "react";
import { HospitalDetailRoute } from "@/components/Routes";
import DetailSkeleton from "@/components/DetailSkeleton";

/** Static shell: the URL is read in the browser and the data loads from the query cache / API (Nest JSON). */
export default function Page() {
  return <Suspense fallback={<DetailSkeleton />}><HospitalDetailRoute /></Suspense>;
}
