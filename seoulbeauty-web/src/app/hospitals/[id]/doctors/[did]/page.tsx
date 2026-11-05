import { Suspense } from "react";
import { DoctorDetailRoute } from "@/components/Routes";
import DetailSkeleton from "@/components/DetailSkeleton";

/** Static shell (design 04-02 의료진 상세): the doctor comes from the hospital's doctors JSON, the hospital card from the hospital JSON. */
export default function Page() {
  return <Suspense fallback={<DetailSkeleton />}><DoctorDetailRoute /></Suspense>;
}
