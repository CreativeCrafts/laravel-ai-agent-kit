#!/usr/bin/env bash
set -euo pipefail

# Bootstrap GitHub milestones and issues from a structured JSON catalog.
#
# Usage:
#   ./scripts/bootstrap-issues.sh owner/repo [catalog-path]
#
# Example:
#   ./scripts/bootstrap-issues.sh creativecrafts/laravel-ai-agent-kit scripts/issues-catalog.json
#   ./scripts/bootstrap-issues.sh CreativeCrafts/laravel-ai-agent-kit scripts/issues-catalog-assistant-replacement.json
#
# Requirements:
# - gh CLI authenticated
# - jq installed
#
# Notes:
# - Idempotent by milestone title and issue title
# - Labels are created automatically if missing
# - Issue bodies are generated from the catalog fields using a fixed template

REPO="${1:-}"
CATALOG_PATH="${2:-scripts/issues-catalog.json}"

if [[ -z "${REPO}" ]]; then
  echo "Usage: $0 owner/repo [catalog-path]" >&2
  exit 1
fi

if [[ ! -f "${CATALOG_PATH}" ]]; then
  echo "Catalog not found: ${CATALOG_PATH}" >&2
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

if ! gh auth status >/dev/null 2>&1; then
  echo "GitHub CLI is not authenticated. Run: gh auth login" >&2
  exit 1
fi

validate_catalog_shape() {
  if ! jq empty "${CATALOG_PATH}" >/dev/null; then
    echo "Catalog JSON is invalid: ${CATALOG_PATH}" >&2
    exit 1
  fi

  if ! jq -e '
    has("labels") and (.labels | type == "array") and
    has("milestones") and (.milestones | type == "array") and
    has("issues") and (.issues | type == "array") and
    ((has("execution_order") | not) or (.execution_order | type == "array"))
  ' "${CATALOG_PATH}" >/dev/null; then
    echo "Catalog must contain top-level arrays: labels, milestones, issues, and optional execution_order." >&2
    exit 1
  fi

  if ! jq -e '
    all(.labels[];
      (.name | type == "string") and
      (.color | type == "string") and
      (.description | type == "string")
    )
  ' "${CATALOG_PATH}" >/dev/null; then
    echo "Catalog labels must each contain string fields: name, color, description." >&2
    exit 1
  fi

  if ! jq -e '
    all(.milestones[];
      (.title | type == "string") and
      (.description | type == "string")
    )
  ' "${CATALOG_PATH}" >/dev/null; then
    echo "Catalog milestones must each contain string fields: title, description." >&2
    exit 1
  fi

  if ! jq -e '
    all(.issues[];
      (.title | type == "string") and
      (.milestone | type == "string") and
      (.labels | type == "array") and
      (.summary | type == "string") and
      (.rationale | type == "string") and
      (.scope | type == "array") and
      (.out_of_scope | type == "array") and
      (.dependencies | type == "array") and
      (.acceptance_criteria | type == "array") and
      (.tests | type == "array") and
      (.risks | type == "array") and
      (.docs | type == "array") and
      ((has("execution_note") | not) or (.execution_note | type == "string"))
    )
  ' "${CATALOG_PATH}" >/dev/null; then
    echo "Catalog issues must contain the expected string and array fields used by bootstrap-issues.sh." >&2
    exit 1
  fi

  if ! jq -e '
    ((.execution_order // []) | all(.[]; type == "string")) and
    ((.execution_order // []) - [.issues[].title] | length == 0)
  ' "${CATALOG_PATH}" >/dev/null; then
    echo "Catalog execution_order must contain only issue titles that exist in .issues[].title." >&2
    exit 1
  fi

  if ! jq -e '
    ([.milestones[].title] | length) == ([.milestones[].title] | unique | length) and
    ([.issues[].title] | length) == ([.issues[].title] | unique | length) and
    ([.labels[].name] | length) == ([.labels[].name] | unique | length)
  ' "${CATALOG_PATH}" >/dev/null; then
    echo "Catalog labels, milestones, and issues must have unique names/titles." >&2
    exit 1
  fi
}

validate_catalog_shape

existing_labels="$({
  gh label list --repo "${REPO}" --limit 500 --json name --jq '.[].name' 2>/dev/null || true
})"
all_milestones_json="$({
  gh api -H "Accept: application/vnd.github+json" "repos/${REPO}/milestones?state=all&per_page=100" 2>/dev/null || echo '[]'
})"
all_issues_json="$({
  gh issue list --repo "${REPO}" --state all --limit 1000 --json number,title,state 2>/dev/null || echo '[]'
})"

refresh_labels_cache() {
  existing_labels="$({
    gh label list --repo "${REPO}" --limit 500 --json name --jq '.[].name' 2>/dev/null || true
  })"
}

refresh_milestones_cache() {
  all_milestones_json="$({
    gh api -H "Accept: application/vnd.github+json" "repos/${REPO}/milestones?state=all&per_page=100" 2>/dev/null || echo '[]'
  })"
}

refresh_issues_cache() {
  all_issues_json="$({
    gh issue list --repo "${REPO}" --state all --limit 1000 --json number,title,state 2>/dev/null || echo '[]'
  })"
}

ensure_label() {
  local name="$1"
  local color="$2"
  local description="$3"

  if echo "${existing_labels}" | grep -Fxq "${name}"; then
    return 0
  fi

  gh label create "${name}" \
    --repo "${REPO}" \
    --color "${color}" \
    --description "${description}" >/dev/null

  refresh_labels_cache
  echo "Created label: ${name}" >&2
}

create_labels_from_catalog() {
  while IFS= read -r label; do
    ensure_label \
      "$(echo "${label}" | jq -r '.name')" \
      "$(echo "${label}" | jq -r '.color')" \
      "$(echo "${label}" | jq -r '.description')"
  done < <(jq -c '.labels[]' "${CATALOG_PATH}")
}

find_milestone_number_by_title() {
  local title="$1"

  echo "${all_milestones_json}" \
    | jq -r --arg title "${title}" '.[] | select(.title == $title) | .number' \
    | head -n 1
}

ensure_milestone() {
  local title="$1"
  local description="$2"
  local milestone_number
  local payload_file

  milestone_number="$(find_milestone_number_by_title "${title}")"
  payload_file="$(mktemp)"

  jq -n \
    --arg title "${title}" \
    --arg description "${description}" \
    '{title:$title, description:$description}' > "${payload_file}"

  if [[ -n "${milestone_number}" ]]; then
    gh api -X PATCH \
      -H "Accept: application/vnd.github+json" \
      -H "Content-Type: application/json" \
      "repos/${REPO}/milestones/${milestone_number}" \
      --input "${payload_file}" >/dev/null
    rm -f "${payload_file}"
    echo "${milestone_number}"
    return 0
  fi

  gh api -X POST \
    -H "Accept: application/vnd.github+json" \
    -H "Content-Type: application/json" \
    "repos/${REPO}/milestones" \
    --input "${payload_file}" >/dev/null

  rm -f "${payload_file}"
  refresh_milestones_cache

  milestone_number="$(find_milestone_number_by_title "${title}")"

  if [[ -z "${milestone_number}" ]]; then
    echo "Failed to create milestone: ${title}" >&2
    exit 1
  fi

  echo "Created milestone: ${title}" >&2
  echo "${milestone_number}"
}

create_milestones_from_catalog() {
  while IFS= read -r milestone; do
    local_title="$(echo "${milestone}" | jq -r '.title')"
    local_description="$(echo "${milestone}" | jq -r '.description')"
    ensure_milestone "${local_title}" "${local_description}" >/dev/null
  done < <(jq -c '.milestones[]' "${CATALOG_PATH}")
}

find_issue_number_by_title() {
  local title="$1"

  echo "${all_issues_json}" \
    | jq -r --arg title "${title}" '.[] | select(.title == $title) | .number' \
    | head -n 1
}

build_issue_body() {
  local issue_json="$1"

  local summary rationale scope out_of_scope dependencies acceptance tests risks docs execution_note

  summary="$(echo "${issue_json}" | jq -r '.summary')"
  rationale="$(echo "${issue_json}" | jq -r '.rationale')"
  scope="$(echo "${issue_json}" | jq -r '.scope[]' | sed 's/^/- /')"
  out_of_scope="$(echo "${issue_json}" | jq -r '.out_of_scope[]' | sed 's/^/- /')"
  dependencies="$(echo "${issue_json}" | jq -r '.dependencies[]' | sed 's/^/- /')"
  acceptance="$(echo "${issue_json}" | jq -r '.acceptance_criteria[]' | nl -w1 -s'. ')"
  tests="$(echo "${issue_json}" | jq -r '.tests[]' | sed 's/^/- /')"
  risks="$(echo "${issue_json}" | jq -r '.risks[]' | sed 's/^/- /')"
  docs="$(echo "${issue_json}" | jq -r '.docs[]' | sed 's/^/- /')"
  execution_note="$(echo "${issue_json}" | jq -r '.execution_note // empty')"

  cat <<EOF
## Summary
${summary}

## Rationale
${rationale}

## Scope
${scope}

## Out of Scope
${out_of_scope}

## Dependencies
${dependencies}

## Detailed Acceptance Criteria
${acceptance}

## Tests
${tests}

## Risks / Constraints
${risks}

## Docs / Upgrade Notes
${docs}
EOF

  if [[ -n "${execution_note}" ]]; then
    cat <<EOF

## Execution Note
${execution_note}
EOF
  fi
}

ordered_issues_stream() {
  if jq -e '.execution_order? | type == "array" and length > 0' "${CATALOG_PATH}" >/dev/null; then
    jq -c '
      . as $root
      | ($root.execution_order // []) as $order
      | [
          $order[] as $title
          | $root.issues[]
          | select(.title == $title)
        ]
        + [
          $root.issues[]
          | select((.title as $title | ($order | index($title))) == null)
        ]
      | .[]
    ' "${CATALOG_PATH}"
    return 0
  fi

  jq -c '.issues[]' "${CATALOG_PATH}"
}

upsert_issue() {
  local issue_json="$1"
  local title milestone_title issue_number body_file
  local -a labels create_label_args edit_label_args

  title="$(echo "${issue_json}" | jq -r '.title')"
  milestone_title="$(echo "${issue_json}" | jq -r '.milestone')"
  issue_number="$(find_issue_number_by_title "${title}")"
  body_file="$(mktemp)"

  build_issue_body "${issue_json}" > "${body_file}"

  labels=()
  while IFS= read -r label; do
    labels+=("${label}")
  done < <(echo "${issue_json}" | jq -r '.labels[]')

  create_label_args=()
  edit_label_args=()

  for label in "${labels[@]}"; do
    create_label_args+=(--label "${label}")
    edit_label_args+=(--add-label "${label}")
  done

  if [[ -n "${issue_number}" ]]; then
    gh issue edit "${issue_number}" \
      --repo "${REPO}" \
      --title "${title}" \
      --milestone "${milestone_title}" \
      --body-file "${body_file}" >/dev/null

    if [[ "${#edit_label_args[@]}" -gt 0 ]]; then
      gh issue edit "${issue_number}" \
        --repo "${REPO}" \
        "${edit_label_args[@]}" >/dev/null || true
    fi

    echo "Updated issue: ${title}" >&2
  else
    gh issue create \
      --repo "${REPO}" \
      --title "${title}" \
      --milestone "${milestone_title}" \
      --body-file "${body_file}" \
      "${create_label_args[@]}" >/dev/null

    echo "Created issue: ${title}" >&2
  fi

  rm -f "${body_file}"
  refresh_issues_cache
}

create_issues_from_catalog() {
  while IFS= read -r issue; do
    milestone_title="$(echo "${issue}" | jq -r '.milestone')"

    if ! jq -e --arg title "${milestone_title}" '.milestones[] | select(.title == $title)' "${CATALOG_PATH}" >/dev/null; then
      echo "Issue references unknown milestone: ${milestone_title}" >&2
      exit 1
    fi

    upsert_issue "${issue}"
  done < <(ordered_issues_stream)
}

main() {
  create_labels_from_catalog
  refresh_labels_cache

  create_milestones_from_catalog
  refresh_milestones_cache

  create_issues_from_catalog

  echo "Bootstrap complete for ${REPO} using catalog ${CATALOG_PATH}" >&2
}

main "$@"