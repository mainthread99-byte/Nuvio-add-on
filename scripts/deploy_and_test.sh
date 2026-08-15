#!/usr/bin/env bash
set -euo pipefail

if [ -z "${RENDER_API_KEY:-}" ] || [ -z "${RENDER_SERVICE_ID:-}" ]; then
  echo "RENDER_API_KEY and RENDER_SERVICE_ID must be set" >&2
  exit 1
fi

echo "Creating deploy for service ${RENDER_SERVICE_ID}..."
resp=$(curl -s -X POST "https://api.render.com/v1/services/${RENDER_SERVICE_ID}/deploys" \
  -H "Authorization: Bearer ${RENDER_API_KEY}" -H "Content-Type: application/json" -d '{}')

deployId=""
if command -v jq >/dev/null 2>&1; then
  deployId=$(echo "$resp" | jq -r '.id // .deployId // empty')
else
  deployId=$(python3 - <<PY
import sys, json
try:
    j=json.load(sys.stdin)
    print(j.get('id') or j.get('deployId') or '')
except Exception:
    print('')
PY
  <<<"$resp")
fi

if [ -z "$deployId" ]; then
  echo "Failed creating deploy: $resp" >&2
  exit 1
fi

echo "Deploy id: $deployId"

echo "Polling deploy status..."
for i in $(seq 1 60); do
  status_resp=$(curl -s -H "Authorization: Bearer ${RENDER_API_KEY}" "https://api.render.com/v1/services/${RENDER_SERVICE_ID}/deploys/${deployId}")
  if command -v jq >/dev/null 2>&1; then
    state=$(echo "$status_resp" | jq -r '.state // .status // empty')
  else
    state=$(python3 - <<PY
import sys, json
try:
    j=json.load(sys.stdin)
    print(j.get('state') or j.get('status') or '')
except Exception:
    print('')
PY
    <<<"$status_resp")
  fi
  echo "[${i}] state=$state"
  if [ "$state" = "success" ] || [ "$state" = "succeeded" ]; then
    echo "Deploy succeeded"
    break
  fi
  if [ "$state" = "failed" ]; then
    echo "Deploy failed" >&2
    echo "$status_resp" >&2
    exit 1
  fi
  sleep 5
done

if [ -z "${APP_URL:-}" ]; then
  echo "APP_URL not set — skipping runtime test"
  exit 0
fi

if [ -z "${TEST_URL:-}" ]; then
  echo "TEST_URL not set — skipping scrape test"
  exit 0
fi

echo "Running runtime scrape test against ${APP_URL}..."
curl -s "${APP_URL}/?scrape_url=${TEST_URL}&__debug=1" -o /tmp/nuvio_test_output.json || true
echo "--- test output ---"
cat /tmp/nuvio_test_output.json || true

echo "Done"
