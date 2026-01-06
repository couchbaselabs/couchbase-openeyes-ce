#!/bin/bash
set -euo pipefail

# Resume page verification - skips pages that already passed in a previous run
# Usage: ./droid-page-verify-resume.sh <previous_results_csv> [other options...]

if [[ $# -lt 1 ]]; then
  echo "Usage: $0 <previous_results_csv> [droid-page-verify options...]"
  echo "Example: $0 logs/20260106_172203_page_verify_results.csv --limit 20"
  exit 1
fi

PREV_RESULTS="$1"
shift

if [[ ! -f "$PREV_RESULTS" ]]; then
  echo "Error: Previous results file not found: $PREV_RESULTS"
  exit 1
fi

PROJECT_DIR="/Users/asahu/Desktop/untitled folder/openeyes"
SKIP_FILE=$(mktemp)

# Extract URLs that already passed (final_status == "PASS")
awk -F, 'NR>1 && $5=="\"PASS\"" { gsub(/"/, "", $1); print $1 }' "$PREV_RESULTS" > "$SKIP_FILE"

SKIP_COUNT=$(wc -l < "$SKIP_FILE" | tr -d ' ')
echo "Found $SKIP_COUNT pages that already passed - will skip them"

# Show what will be skipped
if [[ "$SKIP_COUNT" -gt 0 ]]; then
  echo "Skipping:"
  head -10 "$SKIP_FILE" | sed 's/^/  - /'
  if [[ "$SKIP_COUNT" -gt 10 ]]; then
    echo "  ... and $((SKIP_COUNT - 10)) more"
  fi
fi

# Export skip file for the main script to use
export SKIP_PAGES_FILE="$SKIP_FILE"

# Run the main script with remaining arguments
"$PROJECT_DIR/scripts/droid-page-verify.sh" "$@"

# Cleanup
rm -f "$SKIP_FILE"
