#!/usr/bin/env bash

# bash scripts/close-implemented-issues.sh creativecrafts/laravel-ai-agent-kit
# bash scripts/close-implemented-issues.sh creativecrafts/laravel-ai-agent-kit scripts/issues-catalog.json --apply
set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Usage: $0 <owner/repo> [catalog-path] [--apply]" >&2
  exit 1
fi

REPO="$1"
CATALOG_PATH="${2:-scripts/issues-catalog.json}"
MODE="dry-run"

if [[ "${*: -1}" == "--apply" ]]; then
  MODE="apply"
fi

if [[ ! -f "$CATALOG_PATH" ]]; then
  echo "Catalog not found: $CATALOG_PATH" >&2
  exit 1
fi

if ! command -v gh >/dev/null 2>&1; then
  echo "GitHub CLI (gh) is required." >&2
  exit 1
fi

if ! command -v jq >/dev/null 2>&1; then
  echo "jq is required." >&2
  exit 1
fi

jq empty "$CATALOG_PATH" >/dev/null

STRICT_SSOT_IMPLEMENTED_IDS=(
  P0-I1 P0-I2 P0-I3 P0-I4 P0-I5 P0-I6 P0-I7 P0-I8 P0-I9 P0-I10
  P1-I1 P1-I2 P1-I3 P1-I4 P1-I5 P1-I6 P1-I7 P1-I8 P1-I9 P1-I10 P1-I11
  P2-I1 P2-I2 P2-I3 P2-I4 P2-I5 P2-I6 P2-I7 P2-I8 P2-I9
  P3-I1 P3-I2 P3-I3 P3-I4 P3-I5 P3-I6 P3-I7 P3-I8
  P4-I1 P4-I2 P4-I3 P4-I4 P4-I5
  P5-I1 P5-I2 P5-I3
  P6-I1 P6-I2 P6-I3 P6-I4
  P9-I1
)

IMPLEMENTED_IDS=()
while IFS= read -r line; do
  [[ -n "$line" ]] && IMPLEMENTED_IDS+=("$line")
done < <(
  printf '%s\n' "${STRICT_SSOT_IMPLEMENTED_IDS[@]}" "${EXTRA_IMPLEMENTED_IDS[@]}" | awk 'NF && !seen[$0]++'
)

IDS_JSON="$(printf '%s\n' "${IMPLEMENTED_IDS[@]}" | jq -R . | jq -s .)"

IMPLEMENTED_TITLES=()
while IFS= read -r line; do
  [[ -n "$line" ]] && IMPLEMENTED_TITLES+=("$line")
done < <(
  jq -r --argjson ids "$IDS_JSON" '
    .issues[]
    | .title as $title
    | ($title | split(" ")[0]) as $id
    | select($ids | index($id))
    | $title
  ' "$CATALOG_PATH"
)

if [[ ${#IMPLEMENTED_TITLES[@]} -eq 0 ]]; then
  echo "No matching issue titles were resolved from $CATALOG_PATH." >&2
  exit 1
fi

EXISTING_ISSUES=()
while IFS= read -r line; do
  [[ -n "$line" ]] && EXISTING_ISSUES+=("$line")
done < <(
  gh issue list --repo "$REPO" --state all --limit 1000 --json number,title,state \
    | jq -r '.[] | [.number, .state, .title] | @tsv'
)

close_count=0
skip_count=0
missing_count=0

for title in "${IMPLEMENTED_TITLES[@]}"; do
  match_line="$(printf '%s\n' "${EXISTING_ISSUES[@]}" | awk -F '\t' -v target="$title" '$3 == target { print; exit }')"

  if [[ -z "$match_line" ]]; then
    echo "[missing] $title"
    missing_count=$((missing_count + 1))
    continue
  fi

  issue_number="$(printf '%s' "$match_line" | cut -f1)"
  issue_state="$(printf '%s' "$match_line" | cut -f2)"

  if [[ "$issue_state" == "CLOSED" ]]; then
    echo "[skip]    #$issue_number already closed - $title"
    skip_count=$((skip_count + 1))
    continue
  fi

  if [[ "$MODE" == "dry-run" ]]; then
    echo "[dry-run] #$issue_number would close - $title"
  else
    gh issue close "$issue_number" \
      --repo "$REPO" \
      --comment "Closing based on the current SSOT audit against plan/PLAN.md and scripts/issues-catalog.json." >/dev/null
    echo "[closed]  #$issue_number $title"
  fi

  close_count=$((close_count + 1))
done

echo
echo "Mode: $MODE"
echo "Matched implemented issues: ${#IMPLEMENTED_TITLES[@]}"
echo "Close actions: $close_count"
echo "Already closed: $skip_count"
echo "Missing on GitHub: $missing_count"