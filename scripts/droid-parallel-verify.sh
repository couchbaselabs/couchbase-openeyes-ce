#!/bin/bash
set -euo pipefail

# Droid Model Verification Script - Parallel Couchbase model verification
# Usage: ./droid-parallel-verify.sh [options] [concurrency]
# Options:
#   -v|--verbose       Verbose (debug) logging
#   -q|--quiet         Only errors
#   --log-file <path>  Also write logs to file
#   --no-color         Disable ANSI colors
#   -f|--filter <pat>  Filter models by pattern
#   -l|--limit <n>     Limit to first N models
#   --dry-run          Show what would be done
#   -h|--help          Show this help

DEFAULT_CONCURRENCY=5
LOG_LEVEL="info"   # error,warn,info,debug
LOG_FILE=""
USE_COLOR=1
FILTER_PATTERN=""
LIMIT=0
DRY_RUN=0

START_TS=$(date +%Y%m%d_%H%M%S)
PROJECT_DIR="/Users/asahu/Desktop/untitled folder/openeyes"
PROTECTED_DIR="$PROJECT_DIR/protected"
RESULTS_CSV="$PROJECT_DIR/logs/${START_TS}_model_verify_results.csv"
MODEL_LIST="$PROJECT_DIR/logs/${START_TS}_models_to_verify.txt"
PROGRESS_FILE=""
FAIL_FILE=""
WATCHER_PID=""

mkdir -p "$PROJECT_DIR/logs"

usage() {
  cat >&2 <<USAGE
Usage: $0 [options] [concurrency]
Options:
  -v, --verbose        Verbose (debug) logging
  -q, --quiet          Only errors
  --log-file <path>    Also write logs to file
  --no-color           Disable ANSI colors
  -f, --filter <pat>   Filter models by pattern (e.g., Allergy)
  -l, --limit <n>      Limit to first N models
  --dry-run            Show what would be done without running droids
  -h, --help           Show this help

Examples:
  $0 --filter Allergy 3     # Verify allergy models with 3 parallel droids
  $0 -v 10                  # Verify all models with 10 parallel droids, verbose
  $0 --limit 10 5           # Verify first 10 models with 5 parallel droids
  $0 --dry-run              # Show what would be verified
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
    -l|--limit)   LIMIT="${2:-0}"; [[ ! "$LIMIT" =~ ^[0-9]+$ ]] && { log_error "--limit requires a number"; exit 2; }; shift 2 ;;
    --dry-run)    DRY_RUN=1; shift ;;
    -h|--help)    usage; exit 0 ;;
    ''|*[!0-9]*)  log_error "Unknown argument: $1"; usage; exit 2 ;;
    *)            CONCURRENCY="$1"; shift ;;
  esac
done

# Check dependencies
for dep in xargs awk sed mktemp grep; do
  command -v "$dep" >/dev/null 2>&1 || log_warn "Dependency missing: $dep"
done
command -v droid >/dev/null 2>&1 || { log_error "'droid' CLI not found"; exit 1; }

# Find all models with CouchbaseModelBridge
log_info "Finding models with CouchbaseModelBridge trait..."

grep -rl "use.*CouchbaseModelBridge\|CouchbaseModelBridge" "$PROTECTED_DIR" --include="*.php" 2>/dev/null | \
    grep -E "/models/[^/]+\.php$" | \
    grep -v "/traits/" | \
    grep -v "/couchbase/" | \
    grep -v "Test\.php$" | \
    sort -u > "$MODEL_LIST.tmp"

# Apply filter if specified
if [[ -n "$FILTER_PATTERN" ]]; then
    log_info "Filtering models by pattern: $FILTER_PATTERN"
    grep -i "$FILTER_PATTERN" "$MODEL_LIST.tmp" > "$MODEL_LIST" || true
    rm -f "$MODEL_LIST.tmp"
else
    mv "$MODEL_LIST.tmp" "$MODEL_LIST"
fi

# Apply limit if specified
if [[ "$LIMIT" -gt 0 ]]; then
    log_info "Limiting to first $LIMIT models"
    head -n "$LIMIT" "$MODEL_LIST" > "$MODEL_LIST.limited"
    mv "$MODEL_LIST.limited" "$MODEL_LIST"
fi

TOTAL_MODELS=$(wc -l < "$MODEL_LIST" | tr -d ' ')

if [[ "$TOTAL_MODELS" -eq 0 ]]; then
    log_error "No models found matching criteria"
    exit 1
fi

# CSV header
echo "model_name,model_path,table_name,status,has_trait,has_afterSave,has_afterDelete,has_scope_method,has_collection_method,scope_mapping,collection_exists,notes" > "$RESULTS_CSV"

log_info "Start: ts=$START_TS concurrency=$CONCURRENCY models=$TOTAL_MODELS filter=${FILTER_PATTERN:-<none>}"
log_info "Results: $RESULTS_CSV"

cleanup() {
  local ec=$?
  if [[ -n "${WATCHER_PID:-}" ]] && kill -0 "$WATCHER_PID" >/dev/null 2>&1; then kill "$WATCHER_PID" >/dev/null 2>&1 || true; fi
  [[ -n "${PROGRESS_FILE:-}" ]] && rm -f "$PROGRESS_FILE" || true
  [[ -n "${FAIL_FILE:-}" ]] && rm -f "$FAIL_FILE" || true
  log_debug "Cleanup done (exit=$ec)"
}
trap cleanup EXIT INT TERM

PROGRESS_FILE=$(mktemp)
FAIL_FILE=$(mktemp)

watch_progress() {
  while :; do
    local processed failures
    processed=$(wc -l < "$PROGRESS_FILE" 2>/dev/null | tr -d ' ' || echo 0)
    failures=$(wc -l < "$FAIL_FILE" 2>/dev/null | tr -d ' ' || echo 0)
    log_info "Progress: ${processed}/${TOTAL_MODELS} processed; failures: ${failures:-0}"
    if [[ "$processed" -ge "$TOTAL_MODELS" ]]; then break; fi
    sleep 10
  done
}

watch_progress &
WATCHER_PID=$!

run_droid_verify() {
  local model_file="$1"
  local model_name model_path table_name
  
  model_name=$(basename "$model_file" .php)
  model_path="${model_file#$PROTECTED_DIR/}"
  
  # Try to extract table name from the model file
  table_name=$(grep -E "function tableName|return\s*['\"][a-z_]+['\"]" "$model_file" 2>/dev/null | grep -oE "['\"][a-z_]+['\"]" | head -1 | tr -d "'" | tr -d '"' || echo "unknown")
  
  local tmpout start_s end_s duration_s status
  tmpout=$(mktemp)
  start_s=$(date +%s)

  # Child logger
  _child_log() {
    local level="$1"; shift; local msg="$*"
    printf "%s [%s] %s\n" "$(date +%H:%M:%S)" "$level" "$msg" >&2
    if [[ -n "${LOG_FILE}" ]]; then printf "%s [%s] %s\n" "$(date +%H:%M:%S)" "$level" "$msg" >> "${LOG_FILE}" || true; fi
  }

  _child_log info "START $model_name ($model_path)"

  local prompt
  prompt=$(cat <<EOF
Verify Couchbase functionality for model: $model_name
File: $model_file
Table: $table_name

Check and report in this EXACT format (pipe-delimited, one line):
model_name|model_path|table_name|status|has_trait|has_afterSave|has_afterDelete|has_scope_method|has_collection_method|scope_mapping|collection_exists|notes

Where:
- status: PASS, FAIL, or INCOMPLETE
- has_trait: YES or NO (has CouchbaseModelBridge trait)
- has_afterSave: YES or NO (has afterSave calling saveToCouchbase)
- has_afterDelete: YES or NO (has afterDelete calling deleteFromCouchbase)
- has_scope_method: YES or NO (has couchbaseScope method)
- has_collection_method: YES or NO (has couchbaseCollection method)
- scope_mapping: The scope name from CouchbaseAdapter or MISSING
- collection_exists: YES, NO, or UNKNOWN
- notes: Brief notes about issues found
- after the code check is done open the corresponding page in the browser using playwright on openeyes website, add a new record and verify if it is saved to the couchbase database. refresh the page in openeyes and make sure it is also present there. 
- make sure any unit test case are also added and they are passing.
- openeyes website url is http://localhost:7777 couchbase database is running on localhost:8091
- you can use username: admin and password: admin to login to the openeyes website.
- if you encounter any bug in browser test or code, fix them along the way and add final output in notes.
Return ONLY the pipe-delimited row, no other text.
EOF
)

  status="ok"
  if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "$model_name|$model_path|$table_name|DRY_RUN|-|-|-|-|-|-|-|Dry run mode" > "$tmpout"
  elif ! droid exec --skip-permissions-unsafe -m claude-haiku-4-5-20251001 -r high "$prompt" > "$tmpout" 2>/dev/null; then
    echo "$model_name|$model_path|$table_name|ERROR|-|-|-|-|-|-|-|Droid exec failed" > "$tmpout"
    echo "$model_file" >> "$FAIL_FILE"
    status="fail"
  fi

  # Process output and append to CSV
  local processed_output
  processed_output=$(awk -v mn="$model_name" -v mp="$model_path" -v tn="$table_name" '
    BEGIN { FS = "|" }
    # Skip blank lines
    /^[[:space:]]*$/ { next }
    # Skip lines that look like headers or prose
    /^model_name\|/ { next }
    /^[A-Z][a-z]/ && NF < 5 { next }
    function csvq(s) { gsub(/"/,"\"\"",s); return "\"" s "\"" }
    NF >= 8 {
      print csvq($1) "," csvq($2) "," csvq($3) "," csvq($4) "," csvq($5) "," csvq($6) "," csvq($7) "," csvq($8) "," csvq($9) "," csvq($10) "," csvq($11) "," csvq($12)
      found = 1
      exit
    }
    END {
      if (!found) {
        print csvq(mn) "," csvq(mp) "," csvq(tn) ",PARSE_ERROR,-,-,-,-,-,-,-,Could not parse droid output"
      }
    }
  ' "$tmpout")

  if [[ -n "$processed_output" ]]; then
    printf "%s\n" "$processed_output" >> "$RESULTS_CSV"
  fi

  end_s=$(date +%s)
  duration_s=$(( end_s - start_s ))

  if [[ "$status" == "ok" ]]; then
    _child_log info "DONE $model_name in ${duration_s}s"
  else
    _child_log error "FAIL $model_name in ${duration_s}s"
  fi

  echo "$model_file" >> "$PROGRESS_FILE"
  rm -f "$tmpout"
}

export -f run_droid_verify
export LOG_LEVEL LOG_FILE PROGRESS_FILE FAIL_FILE RESULTS_CSV PROTECTED_DIR DRY_RUN

# Parallelize verification using xargs -P (same pattern as droid-autofix.sh)
# Use null-terminated strings to handle paths with spaces
tr '\n' '\0' < "$MODEL_LIST" | xargs -0 -n 1 -P "$CONCURRENCY" bash -c 'run_droid_verify "$1"' _

# Wait for watcher
if [[ -n "${WATCHER_PID:-}" ]] && kill -0 "$WATCHER_PID" >/dev/null 2>&1; then
  wait "$WATCHER_PID" || true
fi

# Summary
TOTAL_ROWS=$(awk 'NR>1 {c++} END{print c+0}' "$RESULTS_CSV")
PASS_ROWS=$(awk -F, 'NR>1 && $4=="\"PASS\"" {c++} END{print c+0}' "$RESULTS_CSV")
FAIL_ROWS=$(awk -F, 'NR>1 && ($4=="\"FAIL\"" || $4=="\"ERROR\"") {c++} END{print c+0}' "$RESULTS_CSV")
INCOMPLETE_ROWS=$(awk -F, 'NR>1 && $4=="\"INCOMPLETE\"" {c++} END{print c+0}' "$RESULTS_CSV")

log_info "==========================================="
log_info "Summary: models=$TOTAL_MODELS, pass=$PASS_ROWS, fail=$FAIL_ROWS, incomplete=$INCOMPLETE_ROWS"
log_info "Results: $RESULTS_CSV"
log_info "==========================================="

# Show failures
if [[ "$FAIL_ROWS" -gt 0 ]]; then
  log_warn "Failed/Error models:"
  awk -F, 'NR>1 && ($4=="\"FAIL\"" || $4=="\"ERROR\"") { gsub(/"/, "", $1); print "  - " $1 }' "$RESULTS_CSV"
fi

# Show incomplete
if [[ "$INCOMPLETE_ROWS" -gt 0 ]]; then
  log_warn "Incomplete models (missing hooks/methods):"
  awk -F, 'NR>1 && $4=="\"INCOMPLETE\"" { gsub(/"/, "", $1); print "  - " $1 }' "$RESULTS_CSV" | head -20
fi
