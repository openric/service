#!/usr/bin/env bash
#
# Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
# Email: johan@plainsailingisystems.co.za
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Cron-friendly health check for the OpenRiC reference API. Intended to be
# run every few minutes via cron; emails on failure; silent on success.
#
# Install:
#   sudo cp /usr/share/nginx/OpenRiC/deploy/uptime-check.sh /usr/local/sbin/openric-uptime-check
#   sudo chmod +x /usr/local/sbin/openric-uptime-check
#   sudo crontab -e
#   # add:
#   */5 * * * * MAILTO=johan@theahg.co.za /usr/local/sbin/openric-uptime-check
#
# Override the endpoint via env:
#   URL=https://ric.theahg.co.za/api/ric/v1/health /usr/local/sbin/openric-uptime-check
#
# Exit codes:
#   0 — healthy
#   1 — HTTP non-200 or non-JSON response
#   2 — network failure (DNS, TLS, unreachable)

set -u

URL="${URL:-https://ric.theahg.co.za/api/ric/v1/health}"
TIMEOUT="${TIMEOUT:-10}"
EXPECT_STATUS="${EXPECT_STATUS:-ok}"
STATE_FILE="${STATE_FILE:-/var/lib/openric-uptime-check.state}"

# Query; collect body + status separately so we can report precisely.
body="$(curl --silent --show-error --max-time "$TIMEOUT" --write-out '\nSTATUS:%{http_code}' "$URL" 2>&1)" || rc=$?
rc=${rc:-0}

http_code=""
response_body="$body"
if [[ "$body" == *$'\nSTATUS:'* ]]; then
  http_code="${body##*$'\nSTATUS:'}"
  response_body="${body%$'\nSTATUS:'*}"
fi

# Decide health
healthy=false
if [[ $rc -eq 0 ]] && [[ "$http_code" == "200" ]]; then
  if command -v jq >/dev/null 2>&1; then
    actual_status="$(printf '%s' "$response_body" | jq -r '.status // empty' 2>/dev/null)"
    [[ "$actual_status" == "$EXPECT_STATUS" ]] && healthy=true
  else
    # jq-less fallback: grep for the expected status string
    printf '%s' "$response_body" | grep -q "\"status\":\"$EXPECT_STATUS\"" && healthy=true
  fi
fi

# State-transition reporting — only alert on transitions (healthy→unhealthy
# or unhealthy→healthy). Keeps the mail volume down.
prev_state="unknown"
[[ -f "$STATE_FILE" ]] && prev_state="$(cat "$STATE_FILE" 2>/dev/null || echo unknown)"

new_state="$([[ "$healthy" == true ]] && echo "healthy" || echo "unhealthy")"
mkdir -p "$(dirname "$STATE_FILE")" 2>/dev/null || true
printf '%s' "$new_state" > "$STATE_FILE"

if [[ "$new_state" == "$prev_state" ]]; then
  # No state change — stay quiet.
  [[ "$new_state" == "healthy" ]] && exit 0 || exit 1
fi

# Emit a report on transition. cron captures stdout → emails MAILTO.
{
  echo "OpenRiC reference API state change: $prev_state → $new_state"
  echo "URL:  $URL"
  echo "Date: $(date -u +'%Y-%m-%dT%H:%M:%SZ')"
  echo "HTTP: ${http_code:-(none)}"
  if [[ $rc -ne 0 ]]; then
    echo "curl exit: $rc"
  fi
  echo ""
  echo "--- response body ---"
  printf '%s\n' "$response_body" | head -20
} >&2

[[ "$new_state" == "healthy" ]] && exit 0

# Unhealthy — honour the curl exit code when relevant.
if [[ $rc -ne 0 ]]; then exit 2; fi
exit 1
