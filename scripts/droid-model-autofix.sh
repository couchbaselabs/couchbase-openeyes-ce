#!/bin/bash
set -euo pipefail

# Droid Model Autofix Script - Parallel Couchbase model verification AND fixing
# Usage: ./droid-model-autofix.sh [options] [concurrency]
# Options:
#   -v|--verbose       Verbose (debug) logging
#   -q|--quiet         Only errors
#   --log-file <path>  Also write logs to file
#   --no-color         Disable ANSI colors
#   -f|--filter <pat>  Filter models by pattern
#   --dry-run          Report only, don't fix
#   -h|--help          Show this help

DEFAULT_CONCURRENCY=10
LOG_LEVEL="info"   # error,warn,info,debug
LOG_FILE=""
USE_COLOR=1
FILTER_PATTERN=""
DRY_RUN=0
MAX_MODELS=0  # 0 means no limit

START_TS=$(date +%Y%m%d_%H%M%S)
PROJECT_DIR="/Users/asahu/Desktop/untitled folder/openeyes"
PROTECTED_DIR="$PROJECT_DIR/protected"
RESULTS_CSV="$PROJECT_DIR/logs/${START_TS}_model_autofix_results.csv"
MODEL_LIST="$PROJECT_DIR/logs/${START_TS}_models_to_fix.txt"
PROGRESS_FILE=""
FAIL_FILE=""
FIXED_FILE=""
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
  -n, --limit <N>      Process only first N models (default: all)
  --dry-run            Report issues only, don't apply fixes
  -h, --help           Show this help

Examples:
  $0 --filter Allergy 3     # Fix allergy models with 3 parallel droids
  $0 -v 10                  # Fix all models with 10 parallel droids, verbose
  $0 --dry-run 5            # Report issues without fixing
  $0 -n 10 3                # Process only first 10 models with 3 droids
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
echo "model_name,model_path,issue_id,issue_type,description,status,fix_applied" > "$RESULTS_CSV"

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
    sleep 5
  done
}

watch_progress &
WATCHER_PID=$!

run_droid_autofix() {
  local model_file="$1"
  local model_name model_path
  model_name=$(basename "$model_file" .php)
  model_path="${model_file#$PROTECTED_DIR/}"
  
  local tmpout start_s end_s duration_s status rows
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
    dry_run_flag="IMPORTANT: This is a DRY RUN. Report issues but do NOT modify any files."
  fi

  local prompt
  prompt=$(cat <<EOF
Verify and fix Couchbase integration for model: $model_name
File: $model_file
$dry_run_flag

## TASK
Check if this model has proper Couchbase integration and FIX any issues found.

## CHECKS TO PERFORM
1. Has CouchbaseModelBridge trait in the use statement?
2. Has afterSave() method that calls \$this->saveToCouchbase()?
3. Has afterDelete() method that calls \$this->deleteFromCouchbase()?
4. Has couchbaseScope() method returning the scope name?
5. Has couchbaseCollection() method returning the table/collection name?

## REFERENCE IMPLEMENTATION
A properly configured model should have:
\`\`\`php
use CouchbaseModelBridge;

public function couchbaseScope(): string
{
    return 'reference'; // or 'core', 'clinical', etc.
}

public function couchbaseCollection(): string
{
    return \$this->tableName();
}

protected function afterSave()
{
    parent::afterSave();
    \$this->saveToCouchbase();
}

protected function afterDelete()
{
    parent::afterDelete();
    \$this->deleteFromCouchbase();
}
\`\`\`

## OUTPUT FORMAT
Return ONLY pipe-delimited rows (no prose), one per issue found or fixed:
model_name|model_path|issue_id|issue_type|description|status|fix_applied

Where:
- issue_id: MISSING_TRAIT, MISSING_AFTER_SAVE, MISSING_AFTER_DELETE, MISSING_SCOPE_METHOD, MISSING_COLLECTION_METHOD, NO_ISSUES
- issue_type: ERROR, WARNING, INFO
- description: Brief description of the issue
- status: FIXED, SKIPPED, NO_ACTION_NEEDED
- fix_applied: YES or NO

Rules:
- If no issues found, output one row with issue_id=NO_ISSUES
- Do NOT use '|' character inside field values
- For model_name use "$model_name" and model_path use "$model_path"
EOF
)

  status="ok"
  if ! droid exec --skip-permissions-unsafe "$prompt" > "$tmpout" 2>/dev/null; then
    echo "\"$model_name\",\"$model_path\",\"DROID-ERROR\",\"ERROR\",\"Droid exec failed\",\"FAILED\",\"NO\"" >> "$RESULTS_CSV"
    echo "$model_file" >> "$FAIL_FILE"
    status="fail"
  else
    local processed_output
    processed_output=$(awk -v mn="$model_name" -v mp="$model_path" '
      BEGIN { FS = "|"; found = 0 }
      # Skip blank lines
      /^[[:space:]]*$/ { next }
      # Skip header line
      /^model_name\|model_path\|issue_id/ { next }
      # Skip prose lines (start with letter but not enough fields)
      /^[A-Za-z]/ && NF < 5 { next }
      function csvq(s) { gsub(/"/,"\"\"",s); return "\"" s "\"" }
      NF >= 5 {
        # Extract fields
        v1 = $1; v2 = $2; v3 = $3; v4 = $4; v5 = $5; v6 = (NF >= 6 ? $6 : "-"); v7 = (NF >= 7 ? $7 : "-")
        # Use provided model name/path if first fields are empty or different
        if (v1 == "" || v1 == "-") v1 = mn
        if (v2 == "" || v2 == "-") v2 = mp
        print csvq(v1) "," csvq(v2) "," csvq(v3) "," csvq(v4) "," csvq(v5) "," csvq(v6) "," csvq(v7)
        found = 1
      }
      END {
        if (!found) {
          print csvq(mn) "," csvq(mp) ",PARSE_ERROR,ERROR,Could not parse droid output,FAILED,NO"
        }
      }
    ' "$tmpout")

    if [[ -n "$processed_output" ]]; then
      printf "%s\n" "$processed_output" >> "$RESULTS_CSV"
      rows=$(printf "%s\n" "$processed_output" | sed '/^[[:space:]]*$/d' | wc -l | tr -d ' ')
      
      # Check if any fixes were applied
      if echo "$processed_output" | grep -q "FIXED"; then
        echo "$model_file" >> "$FIXED_FILE"
      fi
    else
      rows=0
    fi
  fi

  end_s=$(date +%s)
  duration_s=$(( end_s - start_s ))

  if [[ "$status" == "ok" ]]; then
    _child_log info "DONE ${model_name} in ${duration_s}s (rows=${rows:-0})"
  else
    _child_log error "FAIL ${model_name} in ${duration_s}s"
  fi

  echo "$model_file" >> "$PROGRESS_FILE"
  rm -f "$tmpout"
}

export -f run_droid_autofix
export LOG_LEVEL LOG_FILE PROGRESS_FILE FAIL_FILE FIXED_FILE RESULTS_CSV PROTECTED_DIR DRY_RUN

# Parallelize autofix (use null-terminated strings to handle paths with spaces)
while IFS= read -r model_file; do
  printf '%s\0' "$model_file"
done < "$MODEL_LIST" | xargs -0 -n 1 -P "$CONCURRENCY" bash -c 'run_droid_autofix "$1"' _

# Wait for watcher
if [[ -n "${WATCHER_PID:-}" ]] && kill -0 "$WATCHER_PID" >/dev/null 2>&1; then
  wait "$WATCHER_PID" || true
fi

# Summary
TOTAL_ROWS=$(awk 'NR>1 {c++} END{print c+0}' "$RESULTS_CSV")
ERROR_ROWS=$(awk -F, 'NR>1 && $3~/DROID-ERROR|PARSE_ERROR/ {c++} END{print c+0}' "$RESULTS_CSV")
FIXED_ROWS=$(awk -F, 'NR>1 && $6~/FIXED/ {c++} END{print c+0}' "$RESULTS_CSV")
NOISSUE_ROWS=$(awk -F, 'NR>1 && $3~/NO_ISSUES/ {c++} END{print c+0}' "$RESULTS_CSV")

log_info "==========================================="
log_info "Summary: models=$TOTAL_MODELS, issues=$TOTAL_ROWS, fixed=$FIXED_ROWS, no_issues=$NOISSUE_ROWS, errors=$ERROR_ROWS"
log_info "==========================================="

# Show top issues
log_debug "Top issues found:"
awk -F, 'NR>1 && $3!~/NO_ISSUES/ {gsub(/"/, "", $3); c[$3]++} END { for (k in c) printf("%s,%d\n", k, c[k]) }' "$RESULTS_CSV" |
  sort -t, -k2,2nr | head -n 10 | while IFS=, read -r issue cnt; do
    [[ -n "$issue" ]] && log_debug "  ${issue}: ${cnt}"
  done

# Show models that were fixed
if [[ "$FIXED_ROWS" -gt 0 ]]; then
  log_info "Models fixed:"
  awk -F, 'NR>1 && $6~/FIXED/ { gsub(/"/, "", $1); if (!seen[$1]++) print "  - " $1 }' "$RESULTS_CSV" | head -20
fi

# Show models with errors
if [[ "$ERROR_ROWS" -gt 0 ]]; then
  log_warn "Models with errors:"
  awk -F, 'NR>1 && $3~/DROID-ERROR|PARSE_ERROR/ { gsub(/"/, "", $1); if (!seen[$1]++) print "  - " $1 }' "$RESULTS_CSV"
fi

printf "\nResults written to %s\n" "$RESULTS_CSV"
