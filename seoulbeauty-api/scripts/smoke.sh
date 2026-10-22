#!/usr/bin/env bash
# Usage: BASE=http://127.0.0.1:3100 ./scripts/smoke.sh
# Compares nothing destructive: only GET requests.
B=${BASE:-http://127.0.0.1:3110}
for p in "/health" "/v1/hospitals?limit=3" "/v1/hospitals?sort=rating&limit=3" "/v1/hospitals?region=%EA%B0%95%EB%82%A8&limit=3" "/v1/hospitals/1" "/v1/hospitals/1/reviews?limit=3"; do
  printf '%-60s ' "$p"; curl -s -o /dev/null -w '%{http_code} %{time_total}s\n' "$B$p"
done
