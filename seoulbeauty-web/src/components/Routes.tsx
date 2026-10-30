"use client";
import { useEffect } from "react";
import { notFound, useParams, useRouter, useSearchParams } from "next/navigation";
import { useQuery } from "@tanstack/react-query";
import { api, toDetailTab } from "@/lib/api";
import { POINTS_POLICY } from "@/content/legal";
import Booking from "./Booking";
import BookingCancel from "./BookingCancel";
import BookingPayment from "./BookingPayment";
import BookingPaymentSuccess from "./BookingPaymentSuccess";
import CommunityDetail from "./CommunityDetail";
import CommunityList from "./CommunityList";
import CommunityWrite from "./CommunityWrite";
import EventDetail from "./EventDetail";
import EventList from "./EventList";
import Faq from "./Faq";
import HospitalDetail from "./HospitalDetail";
import ListSkeleton from "./ListSkeleton";
import PointsPolicy from "./PointsPolicy";
import PointsPolicyDetail from "./PointsPolicyDetail";
import RegisterTerms from "./RegisterTerms";
import ReviewDetail from "./ReviewDetail";
import ReviewWrite from "./ReviewWrite";
import SiteHeader from "./SiteHeader";

/**
 * Route wrappers: the page files are static shells (no server data), so the URL (params / query) is read here, in the browser,
 * and each screen loads its JSON from the query cache / API. Same parsing rules the server pages used to apply.
 */
const posInt = (v: string | null | undefined, max = 2147483647): number => { const n = Number(v ?? ""); return Number.isInteger(n) && n >= 1 && n <= max ? n : 0; };
const useRouteId = (): number => {
  const id = posInt(useParams<{ id: string }>().id);
  if (!id) notFound();
  return id;
};

export const HospitalDetailRoute = () => {
  const sp = useSearchParams();
  return <HospitalDetail id={useRouteId()} initialTab={toDetailTab(sp.get("tab") ?? undefined)} />;
};

export const BookingRoute = () => {
  const sp = useSearchParams();
  const change = posInt(sp.get("change"));
  if (change) return <Booking id={0} change={change} treatmentId={0} optionId={0} treatmentName="" rebook={false} via="" sourceId={0} />;
  const id = posInt(sp.get("id"));
  if (!id) return <div style={{ padding: 60, textAlign: "center", color: "#777" }}>병원 정보를 찾을 수 없습니다.</div>;
  return <Booking id={id} treatmentId={posInt(sp.get("treatment_id"))} optionId={posInt(sp.get("option_id"))} treatmentName={(sp.get("treatment") ?? "").trim().slice(0, 100)}
    rebook={sp.get("rebook") === "1"} via={sp.get("via") ?? ""} sourceId={posInt(sp.get("source_booking_id"))} />;
};

export const EventsRoute = () => {
  const sp = useSearchParams();
  const category = (sp.get("category") ?? "").trim().slice(0, 100);
  return (
    <>
      <SiteHeader showSearch={false} />
      <EventList category={category} raffle={sp.get("filter") === "raffle"} />
    </>
  );
};

export const EventDetailRoute = () => {
  const id = useRouteId();
  return (
    <>
      <SiteHeader showSearch={false} />
      <EventDetail id={id} />
    </>
  );
};

export const CommunityRoute = () => {
  const sp = useSearchParams();
  const tabRaw = sp.get("tab");
  const tab = tabRaw === "popular" || tabRaw === "review" ? tabRaw : "latest";
  const category = tab === "review" ? "" : (sp.get("category") ?? "").trim().slice(0, 100);
  const region = (sp.get("region") ?? "").trim().slice(0, 500);
  const q = (sp.get("q") ?? "").trim().slice(0, 100);
  const page = posInt(sp.get("page"), 100000) || 1;
  return (
    <>
      <SiteHeader target="community" q={q} />
      <CommunityList params={{ tab, category, region, q, page }} />
    </>
  );
};

export const CommunityPostRoute = () => <CommunityDetail id={useRouteId()} />;
export const CommunityWriteRoute = () => <CommunityWrite id={posInt(useSearchParams().get("id")) || null} />;
export const FaqRoute = () => <Faq cat={(useSearchParams().get("cat") ?? "").trim().slice(0, 50)} />;
export const ReviewDetailRoute = () => <ReviewDetail id={useRouteId()} />;
export const RegisterTermsRoute = () => <RegisterTerms />;

/** The points menu can be paused: everybody goes to 마이페이지 (as the PHP pages do). Nothing is shown until the switch is known. */
function usePointsVisible() {
  const router = useRouter();
  const info = useQuery({ queryKey: ["legal", "points-policy-info"], queryFn: () => api.pointsPolicyInfo(), retry: 1 });
  const hidden = info.isError || (!!info.data && !info.data.visible);
  useEffect(() => { if (hidden) router.replace("/mypage"); }, [hidden, router]);
  return info.data?.visible ? info.data : null;
}
export const PointsPolicyRoute = () => {
  const info = usePointsVisible();
  const tab = useSearchParams().get("tab");
  return info ? <PointsPolicy expireDays={info.expireDays} initialTab={tab ?? "earn"} /> : <ListSkeleton rows={2} />;
};
export const PointsPolicyDetailRoute = () => {
  const { type } = useParams<{ type: string }>();
  const policy = Object.hasOwn(POINTS_POLICY, type) ? POINTS_POLICY[type] : null;
  if (!policy) notFound();
  return usePointsVisible() ? <PointsPolicyDetail policy={policy} /> : <ListSkeleton rows={2} />;
};

export const ReviewWriteRoute = () => {
  const sp = useSearchParams();
  return <ReviewWrite bookingId={posInt(sp.get("booking_id"))} hospitalId={posInt(sp.get("hospital_id"))} editId={posInt(sp.get("id"))} />;
};
export const BookingCancelRoute = () => <BookingCancel id={useRouteId()} reason={(useSearchParams().get("reason") ?? "").trim().slice(0, 255)} />;
export const BookingPaymentRoute = () => <BookingPayment id={useRouteId()} payError={(useSearchParams().get("pay_error") ?? "").replace(/[^A-Z_]/g, "")} />;
export const BookingPaymentSuccessRoute = () => <BookingPaymentSuccess id={useRouteId()} />;
