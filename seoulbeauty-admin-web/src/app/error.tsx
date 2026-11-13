"use client";

import ErrorView from "@/components/ErrorView";

// Catches a render error inside the page (the layout survives).
export default function Error({ error, reset }: { error: Error & { digest?: string }; reset: () => void }) {
  return <ErrorView error={error} reset={reset} />;
}
