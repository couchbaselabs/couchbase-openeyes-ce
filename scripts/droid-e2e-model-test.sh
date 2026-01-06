#!/bin/bash
set -euo pipefail

# Droid E2E Model Test Script - Parallel browser-based CRUD testing with auto-fix
# Uses Playwright to test read/write operations for each model's admin page
# Automatically fixes issues (missing collections, scope mismatches, hooks) and re-verifies
#
# Usage: ./droid-e2e-model-test.sh [options] [concurrency]
# Options:
#   -v|--verbose       Verbose (debug) logging
#   -q|--quiet         Only errors
#   --log-file <path>  Also write logs to file
#   --no-color         Disable ANSI colors
#   -f|--filter <pat>  Filter models by pattern
#   -n|--limit <N>     Process only first N models
#   --dry-run          Report only, don't fix
#   -h|--help          Show this help

DEFAULT_CONCURRENCY=3  # Lower default since browser tests are heavier
LOG_LEVEL="info"
LOG_FILE=""
USE_COLOR=1
FILTER_PATTERN=""
DRY_RUN=0
MAX_MODELS=0
BASE_URL="http://localhost:7777"

START_TS=$(date +%Y%m%d_%H%M%S)
PROJECT_DIR="/Users/asahu/Desktop/untitled folder/openeyes"
PROTECTED_DIR="$PROJECT_DIR/protected"
RESULTS_CSV="$PROJECT_DIR/logs/${START_TS}_e2e_test_results.csv"
MODEL_LIST="$PROJECT_DIR/logs/${START_TS}_models_to_test.txt"
ADMIN_ROUTES_FILE="$PROJECT_DIR/logs/${START_TS}_admin_routes.txt"
PROGRESS_FILE=""
FAIL_FILE=""
FIXED_FILE=""
WATCHER_PID=""

mkdir -p "$PROJECT_DIR/logs"

usage() {
  cat >&2 <<USAGE
Usage: $0 [options] [concurrency]

E2E Model Testing - Tests CRUD operations via browser and auto-fixes issues

Options:
  -v, --verbose        Verbose (debug) logging
  -q, --quiet          Only errors
  --log-file <path>    Also write logs to file
  --no-color           Disable ANSI colors
  -f, --filter <pat>   Filter models by pattern (e.g., Allergy)
  -n, --limit <N>      Process only first N models (default: all)
  --dry-run            Report issues only, don't apply fixes
  -h, --help           Show this help

Examples:
  $0 --filter Allergy 2       # Test allergy models with 2 parallel droids
  $0 -n 10 3                  # Test first 10 models with 3 parallel droids
  $0 --filter Reaction 1      # Test reaction models sequentially
  $0 -v --dry-run 3           # Verbose dry-run, 3 parallel

The script will:
1. Find admin pages for each model
2. Use Playwright to create a test record
3. Verify the record persists after page refresh
4. Check if data exists in Couchbase
5. If failing, diagnose and fix issues (scope, collection, hooks)
6. Re-verify until fixed or max retries reached
USAGE
}

_level_num() {
  case "${1:-info}" in
    error) echo 0;;
    warn)  echo 1;;
    info)  echo 2;;
    debug) echo 3;;
    *)     echo 2;;
  esac
}

_ts() { date +%H:%M:%S; }

_maybe_color() {
  local code="$1"; shift || true
  if [[ "$USE_COLOR" -eq 1 && -t 2 ]]; then printf "\033[%sm" "$code"; fi
}

_color_reset() { [[ "$USE_COLOR" -eq 1 && -t 2 ]] && printf "\033[0m" || true; }

_log_base() {
  local level="$1"; shift
  local msg="$*"
  local want=$(_level_num "$LOG_LEVEL")
  local have=$(_level_num "$level")
  if [[ "$have" -le "$want" ]]; then
    local color=""; case "$level" in
      error) color=31;;
      warn)  color=33;;
      info)  color=36;;
      debug) color=90;;
    esac
    {
      _maybe_color "$color"; printf "%s [%s] %s" "$(_ts)" "$level" "$msg"; _color_reset; printf "\n"
    } >&2
    if [[ -n "$LOG_FILE" ]]; then
      printf "%s [%s] %s\n" "$(_ts)" "$level" "$msg" >> "$LOG_FILE" || true
    fi
  fi
}

log_info()  { _log_base info  "$*"; }
log_warn()  { _log_base warn  "$*"; }
log_error() { _log_base error "$*"; }
log_debug() { _log_base debug "$*"; }

# Parse args
CONCURRENCY="$DEFAULT_CONCURRENCY"
while [[ $# -gt 0 ]]; do
  case "$1" in
    -v|--verbose) LOG_LEVEL="debug"; shift ;;
    -q|--quiet)   LOG_LEVEL="error"; shift ;;
    --log-file)   LOG_FILE="${2:-}"; [[ -z "$LOG_FILE" ]] && { log_error "--log-file requires a path"; exit 2; }; shift 2 ;;
    --no-color)   USE_COLOR=0; shift ;;
    -f|--filter)  FILTER_PATTERN="${2:-}"; shift 2 ;;
    -n|--limit)   MAX_MODELS="${2:-0}"; shift 2 ;;
    --dry-run)    DRY_RUN=1; shift ;;
    -h|--help)    usage; exit 0 ;;
    ''|*[!0-9]*)  log_error "Unknown argument: $1"; usage; exit 2 ;;
    *)            CONCURRENCY="$1"; shift ;;
  esac
done

# Dependencies
for dep in xargs awk sed mktemp grep curl; do
  command -v "$dep" >/dev/null 2>&1 || log_warn "Dependency missing: $dep"
done
command -v droid >/dev/null 2>&1 || { log_error "'droid' CLI not found"; exit 1; }

# Find models with admin pages
log_info "Finding models with CouchbaseModelBridge trait and admin pages..."

# First, find all models with CouchbaseModelBridge
grep -rl "use.*CouchbaseModelBridge\|CouchbaseModelBridge" "$PROTECTED_DIR" --include="*.php" 2>/dev/null | \
    grep -E "/models/[^/]+\.php$" | \
    grep -v "/traits/" | \
    grep -v "/couchbase/" | \
    grep -v "Test\.php$" | \
    sort -u > "$MODEL_LIST.tmp"

# Apply filter if specified
if [[ -n "$FILTER_PATTERN" ]]; then
    log_info "Filtering models by pattern: $FILTER_PATTERN"
    grep -i "$FILTER_PATTERN" "$MODEL_LIST.tmp" > "$MODEL_LIST.filtered" || true
    mv "$MODEL_LIST.filtered" "$MODEL_LIST.tmp"
fi

# Apply limit if specified
if [[ "$MAX_MODELS" -gt 0 ]]; then
    log_info "Limiting to first $MAX_MODELS models"
    head -n "$MAX_MODELS" "$MODEL_LIST.tmp" > "$MODEL_LIST"
    rm -f "$MODEL_LIST.tmp"
else
    mv "$MODEL_LIST.tmp" "$MODEL_LIST"
fi

TOTAL_MODELS=$(wc -l < "$MODEL_LIST" | tr -d ' ')

if [[ "$TOTAL_MODELS" -eq 0 ]]; then
    log_error "No models found matching criteria"
    exit 1
fi

# CSV header
echo "model_name,model_path,table_name,admin_url,test_status,couchbase_status,issues_found,fixes_applied,final_status" > "$RESULTS_CSV"

log_info "Start: ts=$START_TS concurrency=$CONCURRENCY models=$TOTAL_MODELS filter=${FILTER_PATTERN:-<none>} limit=${MAX_MODELS:-all} dry_run=$DRY_RUN"
log_info "Results: $RESULTS_CSV"

cleanup() {
  local ec=$?
  if [[ -n "${WATCHER_PID:-}" ]] && kill -0 "$WATCHER_PID" >/dev/null 2>&1; then kill "$WATCHER_PID" >/dev/null 2>&1 || true; fi
  [[ -n "${PROGRESS_FILE:-}" ]] && rm -f "$PROGRESS_FILE" || true
  [[ -n "${FAIL_FILE:-}" ]] && rm -f "$FAIL_FILE" || true
  [[ -n "${FIXED_FILE:-}" ]] && rm -f "$FIXED_FILE" || true
  log_debug "Cleanup done (exit=$ec)"
}
trap cleanup EXIT INT TERM

PROGRESS_FILE=$(mktemp)
FAIL_FILE=$(mktemp)
FIXED_FILE=$(mktemp)

watch_progress() {
  while :; do
    local processed failures fixed
    processed=$(wc -l < "$PROGRESS_FILE" | tr -d ' ')
    failures=$(wc -l < "$FAIL_FILE" 2>/dev/null | tr -d ' ' || echo 0)
    fixed=$(wc -l < "$FIXED_FILE" 2>/dev/null | tr -d ' ' || echo 0)
    log_info "Progress: ${processed}/${TOTAL_MODELS} processed; fixed: ${fixed}; failures: ${failures}"
    if [[ "$processed" -ge "$TOTAL_MODELS" ]]; then break; fi
    sleep 10
  done
}

watch_progress &
WATCHER_PID=$!

run_e2e_test() {
  local model_file="$1"
  local model_name model_path table_name
  model_name=$(basename "$model_file" .php)
  model_path="${model_file#$PROTECTED_DIR/}"
  
  # Extract table name from model
  table_name=$(grep -E "function tableName|return\s*['\"][a-z_]+['\"]" "$model_file" 2>/dev/null | grep -oE "['\"][a-z_]+['\"]" | head -1 | tr -d "'" | tr -d '"' || echo "unknown")
  
  local tmpout start_s end_s duration_s status
  tmpout=$(mktemp)
  start_s=$(date +%s)

  # Child logger
  _child_level_num() { case "$1" in error) echo 0;; warn) echo 1;; info) echo 2;; debug) echo 3;; *) echo 2;; esac; }
  _child_log() {
    local level="$1"; shift; local msg="$*"
    local want=$(_child_level_num "${LOG_LEVEL}")
    local have=$(_child_level_num "$level")
    if [[ "$have" -le "$want" ]]; then
      printf "%s [%s] %s\n" "$(date +%H:%M:%S)" "$level" "$msg" >&2
      if [[ -n "${LOG_FILE}" ]]; then printf "%s [%s] %s\n" "$(date +%H:%M:%S)" "$level" "$msg" >> "${LOG_FILE}" || true; fi
    fi
  }

  _child_log info "START ${model_name}"

  local dry_run_flag=""
  if [[ "$DRY_RUN" -eq 1 ]]; then
    dry_run_flag="IMPORTANT: This is a DRY RUN. Report issues but do NOT modify any files or create test data."
  fi

  local prompt
  prompt=$(cat <<'PROMPT_END'
E2E Test and Auto-Fix for Model: MODEL_NAME_PLACEHOLDER
Model File: MODEL_FILE_PLACEHOLDER
Table Name: TABLE_NAME_PLACEHOLDER
DRY_RUN_PLACEHOLDER

## YOUR TASK
Perform end-to-end CRUD testing for this model using Playwright browser automation.
If tests fail, diagnose the issue, fix it, and re-verify until it works.

## STEP 1: Find Admin Page
Search the codebase to find if this model has an admin page. Look for:
- Controllers in modules/*/controllers/*Controller.php that reference this model
- Admin routes in config files
- Views in modules/*/views/admin/

Common admin URL patterns:
- /OphCiExamination/admin/ModelName/index
- /admin/editModelName
- /oeadmin/ModelName/list

## STEP 2: Test via Playwright (if admin page exists)
Use Playwright browser tools to:
1. Navigate to the admin page (login first if needed - user: admin, pass: admin, institution: OpenEyes Default Institution)
2. Click "Add" button to create a new record
3. Fill in required fields with test data (use "E2E Test [timestamp]" format)
4. Click "Save"
5. Refresh the page
6. Verify the test record appears in the list

## STEP 3: Verify Couchbase
Run this curl command to check if data reached Couchbase:
```
curl -s "http://localhost:8093/query/service" -u Administrator:password -d "statement=SELECT * FROM \`openeyes\`.\`<scope>\`.\`TABLE_NAME_PLACEHOLDER\` WHERE name LIKE '%E2E Test%' LIMIT 5"
```
Try scopes: reference, core, clinical, admin

## STEP 4: If Test Fails - Diagnose & Fix
Common issues and fixes:

### Issue: Collection doesn't exist in Couchbase
Fix: Create the collection
```
curl -X POST "http://localhost:8091/pools/default/buckets/openeyes/scopes/<scope>/collections" -u Administrator:password -d "name=TABLE_NAME_PLACEHOLDER"
```
Then create primary index:
```
curl -s "http://localhost:8093/query/service" -u Administrator:password -d "statement=CREATE PRIMARY INDEX idx_TABLE_NAME_PLACEHOLDER_primary ON \`openeyes\`.\`<scope>\`.\`TABLE_NAME_PLACEHOLDER\`"
```

### Issue: Scope mismatch between model and CouchbaseAdapter
Check model's couchbaseScope() method vs CouchbaseAdapter.php scopeMapping.
Fix: Update the model's couchbaseScope() to match CouchbaseAdapter mapping.

### Issue: Missing afterSave/afterDelete hooks
Check if model has:
- afterSave() calling $this->saveToCouchbase()
- afterDelete() calling $this->deleteFromCouchbase()
Fix: Add the missing hooks.

### Issue: Missing couchbaseScope()/couchbaseCollection() methods
Fix: Add the methods to the model.

## STEP 5: Re-verify After Fix
After applying any fix, repeat Steps 2-3 to verify the fix worked.
Maximum 3 retry attempts.

## OUTPUT FORMAT
Return ONLY pipe-delimited rows (no prose), one row with final results:
model_name|model_path|table_name|admin_url|test_status|couchbase_status|issues_found|fixes_applied|final_status

Where:
- admin_url: The admin page URL found, or "NOT_FOUND" if no admin page
- test_status: PASS, FAIL, SKIP (if no admin page)
- couchbase_status: DATA_FOUND, NO_DATA, COLLECTION_MISSING, ERROR
- issues_found: Comma-separated list of issues (e.g., "SCOPE_MISMATCH,NO_COLLECTION")
- fixes_applied: Comma-separated list of fixes applied (e.g., "CREATED_COLLECTION,FIXED_SCOPE")
- final_status: PASS, FAIL, PARTIAL, SKIP

For model_name use "MODEL_NAME_PLACEHOLDER" and model_path use "MODEL_PATH_PLACEHOLDER".
PROMPT_END
)

  # Replace placeholders
  prompt="${prompt//MODEL_NAME_PLACEHOLDER/$model_name}"
  prompt="${prompt//MODEL_FILE_PLACEHOLDER/$model_file}"
  prompt="${prompt//MODEL_PATH_PLACEHOLDER/$model_path}"
  prompt="${prompt//TABLE_NAME_PLACEHOLDER/$table_name}"
  prompt="${prompt//DRY_RUN_PLACEHOLDER/$dry_run_flag}"

  status="ok"
  if ! droid exec --skip-permissions-unsafe "$prompt" > "$tmpout" 2>/dev/null; then
    echo "\"$model_name\",\"$model_path\",\"$table_name\",\"UNKNOWN\",\"ERROR\",\"UNKNOWN\",\"DROID_EXEC_FAILED\",\"NONE\",\"FAIL\"" >> "$RESULTS_CSV"
    echo "$model_file" >> "$FAIL_FILE"
    status="fail"
  else
    local processed_output
    processed_output=$(awk -v mn="$model_name" -v mp="$model_path" -v tn="$table_name" '
      BEGIN { FS = "|"; found = 0 }
      /^[[:space:]]*$/ { next }
      /^model_name\|model_path/ { next }
      /^[A-Za-z]/ && NF < 5 { next }
      function csvq(s) { gsub(/"/,"\"\"",s); return "\"" s "\"" }
      NF >= 7 {
        v1 = $1; v2 = $2; v3 = $3; v4 = $4; v5 = $5; v6 = $6; v7 = $7; v8 = (NF >= 8 ? $8 : "-"); v9 = (NF >= 9 ? $9 : "-")
        if (v1 == "" || v1 == "-") v1 = mn
        if (v2 == "" || v2 == "-") v2 = mp
        if (v3 == "" || v3 == "-") v3 = tn
        print csvq(v1) "," csvq(v2) "," csvq(v3) "," csvq(v4) "," csvq(v5) "," csvq(v6) "," csvq(v7) "," csvq(v8) "," csvq(v9)
        found = 1
      }
      END {
        if (!found) {
          print csvq(mn) "," csvq(mp) "," csvq(tn) ",PARSE_ERROR,ERROR,UNKNOWN,PARSE_FAILED,NONE,FAIL"
        }
      }
    ' "$tmpout")

    if [[ -n "$processed_output" ]]; then
      printf "%s\n" "$processed_output" >> "$RESULTS_CSV"
      
      # Check if fixes were applied or if it passed
      if echo "$processed_output" | grep -qi "PASS"; then
        if echo "$processed_output" | grep -qi "CREATED_COLLECTION\|FIXED_SCOPE\|ADDED_HOOK"; then
          echo "$model_file" >> "$FIXED_FILE"
        fi
      else
        echo "$model_file" >> "$FAIL_FILE"
        status="fail"
      fi
    fi
  fi

  end_s=$(date +%s)
  duration_s=$(( end_s - start_s ))

  if [[ "$status" == "ok" ]]; then
    _child_log info "DONE ${model_name} in ${duration_s}s"
  else
    _child_log error "FAIL ${model_name} in ${duration_s}s"
  fi

  echo "$model_file" >> "$PROGRESS_FILE"
  rm -f "$tmpout"
}

export -f run_e2e_test
export LOG_LEVEL LOG_FILE PROGRESS_FILE FAIL_FILE FIXED_FILE RESULTS_CSV PROTECTED_DIR DRY_RUN BASE_URL

# Parallelize E2E tests (use null-terminated strings to handle paths with spaces)
while IFS= read -r model_file; do
  printf '%s\0' "$model_file"
done < "$MODEL_LIST" | xargs -0 -n 1 -P "$CONCURRENCY" bash -c 'run_e2e_test "$1"' _

# Wait for watcher
if [[ -n "${WATCHER_PID:-}" ]] && kill -0 "$WATCHER_PID" >/dev/null 2>&1; then
  wait "$WATCHER_PID" || true
fi

# Summary
TOTAL_ROWS=$(awk 'NR>1 {c++} END{print c+0}' "$RESULTS_CSV")
PASS_ROWS=$(awk -F, 'NR>1 && $9~/PASS/ {c++} END{print c+0}' "$RESULTS_CSV")
FAIL_ROWS=$(awk -F, 'NR>1 && $9~/FAIL/ {c++} END{print c+0}' "$RESULTS_CSV")
SKIP_ROWS=$(awk -F, 'NR>1 && $9~/SKIP/ {c++} END{print c+0}' "$RESULTS_CSV")
FIXED_COUNT=$(wc -l < "$FIXED_FILE" 2>/dev/null | tr -d ' ' || echo 0)

log_info "==========================================="
log_info "E2E Test Summary"
log_info "==========================================="
log_info "Total models: $TOTAL_MODELS"
log_info "Passed: $PASS_ROWS"
log_info "Failed: $FAIL_ROWS"
log_info "Skipped (no admin): $SKIP_ROWS"
log_info "Auto-fixed: $FIXED_COUNT"
log_info "==========================================="

# Show issues found
log_debug "Issues breakdown:"
awk -F, 'NR>1 && $7!~/^"-"$/ && $7!="" { gsub(/"/, "", $7); split($7, a, ","); for (i in a) c[a[i]]++ } END { for (k in c) printf("  %s: %d\n", k, c[k]) }' "$RESULTS_CSV" | sort -t: -k2 -rn | head -10

# Show models that were fixed
if [[ "$FIXED_COUNT" -gt 0 ]]; then
  log_info "Models auto-fixed:"
  awk -F, 'NR>1 && $8!~/^"-"$/ && $8!~/NONE/ { gsub(/"/, "", $1); gsub(/"/, "", $8); if (!seen[$1]++) print "  - " $1 ": " $8 }' "$RESULTS_CSV" | head -20
fi

# Show failures
if [[ "$FAIL_ROWS" -gt 0 ]]; then
  log_warn "Failed models:"
  awk -F, 'NR>1 && $9~/FAIL/ { gsub(/"/, "", $1); if (!seen[$1]++) print "  - " $1 }' "$RESULTS_CSV" | head -20
fi

printf "\nResults written to %s\n" "$RESULTS_CSV"
