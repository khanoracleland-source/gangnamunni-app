"use client";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { usePathname } from "next/navigation";
import { Suspense, useEffect, useRef, useState } from "react";
import { notifyPageChanged } from "@/lib/bridge";

/**
 * In-memory cache = the "Redux store": a page visited once renders instantly from cache on
 * back/forward or re-visit, and refetches in the background when older than staleTime.
 */
function makeClient() {
  return new QueryClient({
    defaultOptions: {
      queries: { staleTime: 60_000, gcTime: 15 * 60_000, retry: 1, refetchOnWindowFocus: false },
    },
  });
}

function PageChangeNotifier() {
  const pathname = usePathname();
  const first = useRef(true);
  useEffect(() => {
    if (first.current) {
      first.current = false; // initial load: Flutter already saw onPageFinished
      return;
    }
    notifyPageChanged();
  }, [pathname]);
  return null;
}

export default function Providers({ children }: { children: React.ReactNode }) {
  const [client] = useState(makeClient);
  return (
    <QueryClientProvider client={client}>
      <Suspense fallback={null}>
        <PageChangeNotifier />
      </Suspense>
      {children}
    </QueryClientProvider>
  );
}
