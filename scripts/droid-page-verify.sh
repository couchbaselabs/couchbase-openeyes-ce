#!/bin/bash
set -euo pipefail

# Droid Page Verification Script - Sitemap generation and auto-fix page verification
# Usage: ./droid-page-verify.sh [options] [concurrency]
# Options:
#   -v|--verbose         Verbose (debug) logging
#   -q|--quiet           Only errors
#   --log-file <path>    Also write logs to file
#   --no-color           Disable ANSI colors
#   -f|--filter <pat>    Filter pages by pattern
#   -l|--limit <n>       Limit to first N pages
#   --skip-auth          Skip pages requiring authentication
#   --sitemap-only       Only generate sitemap, don't verify
#   --no-fix             Verify only, don't attempt fixes
#   --max-fix-attempts   Max fix attempts per page (default: 3)
#   --dry-run            Show what would be done
#   -h|--help            Show this help

DEFAULT_CONCURRENCY=3
LOG_LEVEL="info"
LOG_FILE=""
USE_COLOR=1
FILTER_PATTERN=""
LIMIT=0
DRY_RUN=0
SITEMAP_ONLY=0
NO_FIX=0
SKIP_AUTH=0
MAX_FIX_ATTEMPTS=10

BASE_URL="${OPENEYES_URL:-http://localhost:7777}"
LOGIN_USER="${OPENEYES_USER:-admin}"
LOGIN_PASS="${OPENEYES_PASS:-admin}"

START_TS=$(date +%Y%m%d_%H%M%S)
PROJECT_DIR="/Users/asahu/Desktop/untitled folder/openeyes"
PROTECTED_DIR="$PROJECT_DIR/protected"
SITEMAP_JSON="$PROJECT_DIR/logs/${START_TS}_sitemap.json"
RESULTS_CSV="$PROJECT_DIR/logs/${START_TS}_page_verify_results.csv"
PAGE_LIST="$PROJECT_DIR/logs/${START_TS}_pages_to_verify.txt"
PROGRESS_FILE=""
FAIL_FILE=""
WATCHER_PID=""

mkdir -p "$PROJECT_DIR/logs"

usage() {
  cat >&2 <<USAGE
Usage: $0 [options] [concurrency]
Options:
  -v, --verbose          Verbose (debug) logging
  -q, --quiet            Only errors
  --log-file <path>      Also write logs to file
  --no-color             Disable ANSI colors
  -f, --filter <pat>     Filter pages by pattern (e.g., patient, admin)
  -l, --limit <n>        Limit to first N pages
  --skip-auth            Skip pages requiring authentication
  --sitemap-only         Only generate sitemap, don't verify
  --no-fix               Verify only, don't attempt fixes
  --max-fix-attempts <n> Max fix attempts per page (default: 10)
  --dry-run              Show what would be done without running droids
  -h, --help             Show this help

Environment Variables:
  OPENEYES_URL           Base URL (default: http://localhost:7777)
  OPENEYES_USER          Login username (default: admin)
  OPENEYES_PASS          Login password (default: admin)

Examples:
  $0 --filter patient 3      # Verify patient pages with 3 parallel droids
  $0 -v --limit 10           # Verify first 10 pages, verbose
  $0 --sitemap-only          # Only generate sitemap
  $0 --no-fix 5              # Verify without auto-fix, 5 parallel
  $0 --dry-run               # Show what would be verified
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
    --skip-auth)  SKIP_AUTH=1; shift ;;
    --sitemap-only) SITEMAP_ONLY=1; shift ;;
    --no-fix)     NO_FIX=1; shift ;;
    --max-fix-attempts) MAX_FIX_ATTEMPTS="${2:-3}"; shift 2 ;;
    --dry-run)    DRY_RUN=1; shift ;;
    -h|--help)    usage; exit 0 ;;
    ''|*[!0-9]*)  log_error "Unknown argument: $1"; usage; exit 2 ;;
    *)            CONCURRENCY="$1"; shift ;;
  esac
done

# Check dependencies
for dep in xargs awk sed mktemp grep php; do
  command -v "$dep" >/dev/null 2>&1 || log_warn "Dependency missing: $dep"
done
command -v droid >/dev/null 2>&1 || { log_error "'droid' CLI not found"; exit 1; }

# Generate sitemap by discovering controllers and actions
generate_sitemap() {
  log_info "Generating sitemap from controllers..."
  
  local sitemap_tmp=$(mktemp)
  : > "$sitemap_tmp"  # Create empty file
  
  # Find all controller files (only in controllers directories)
  find "$PROTECTED_DIR" -path "*/controllers/*Controller.php" -type f | \
    grep -v "/tests/" | \
    grep -vE "Base.*Controller|BaseController" | \
    sort -u | while read -r controller_file; do
    
    # Extract controller name and module
    local rel_path="${controller_file#$PROTECTED_DIR/}"
    local controller_name=$(basename "$controller_file" .php)
    local controller_id=$(echo "$controller_name" | sed 's/Controller$//' | awk '{print tolower(substr($0,1,1)) substr($0,2)}')
    
    # Determine if it's a module controller
    local module=""
    local route_prefix=""
    if [[ "$rel_path" == modules/* ]]; then
      module=$(echo "$rel_path" | cut -d'/' -f2)
      # Handle nested modules (e.g., modules/OphCiExamination/modules/ExaminationAdmin)
      if [[ "$rel_path" == modules/*/modules/* ]]; then
        local parent_module=$(echo "$rel_path" | cut -d'/' -f2)
        local sub_module=$(echo "$rel_path" | cut -d'/' -f4)
        route_prefix="/$parent_module/$sub_module"
      # Handle oeadmin controllers
      elif [[ "$rel_path" == *"/oeadmin/"* ]]; then
        route_prefix="/$module/oeadmin"
      else
        route_prefix="/$module"
      fi
    fi
    
    # Extract action methods from the controller
    local actions
    actions=$(grep -oE "public function action[A-Z][a-zA-Z0-9_]*" "$controller_file" 2>/dev/null | sed 's/public function action//' || true)
    
    [[ -z "$actions" ]] && continue
    
    echo "$actions" | while read -r action_name; do
      [[ -z "$action_name" ]] && continue
      
      # Convert action name to URL format (camelCase to lowercase)
      local action_url=$(echo "$action_name" | awk '{print tolower(substr($0,1,1)) substr($0,2)}')
      
      # Determine if action likely requires an ID parameter
      local requires_id="false"
      if [[ "$action_name" =~ ^(View|Edit|Update|Delete|Show) ]]; then
        requires_id="true"
      fi
      
      # Determine if action requires authentication (most do in OpenEyes)
      local requires_auth="true"
      if [[ "$action_name" =~ ^(Login|Error|Index|Health|Ping) ]] || [[ "$controller_id" == "site" ]]; then
        requires_auth="false"
      fi
      
      # Build the full URL
      local full_url
      if [[ -n "$route_prefix" ]]; then
        full_url="${route_prefix}/${controller_id}/${action_url}"
      else
        full_url="/${controller_id}/${action_url}"
      fi
      
      # Clean up double slashes
      full_url=$(echo "$full_url" | sed 's|//|/|g')
      
      # Output page entry (one per line for later processing)
      echo "$full_url|$controller_name|$rel_path|$action_name|$module|$requires_id|$requires_auth" >> "$sitemap_tmp"
    done
  done
  
  # Convert to JSON
  local json_out=$(mktemp)
  echo "[" > "$json_out"
  local first=1
  while IFS='|' read -r url ctrl file act mod req_id req_auth; do
    [[ -z "$url" ]] && continue
    if [[ "$first" -eq 1 ]]; then
      first=0
    else
      echo "," >> "$json_out"
    fi
    cat >> "$json_out" <<EOF
  {
    "url": "$url",
    "controller": "$ctrl",
    "controller_file": "$file",
    "action": "$act",
    "module": "$mod",
    "requires_id": $req_id,
    "requires_auth": $req_auth
  }
EOF
  done < "$sitemap_tmp"
  echo "]" >> "$json_out"
  
  rm -f "$sitemap_tmp"
  mv "$json_out" "$SITEMAP_JSON"
  log_info "Sitemap generated: $SITEMAP_JSON"
}

# Extract page list from sitemap for verification
extract_page_list() {
  log_info "Extracting page list from sitemap..."
  
  # Parse JSON and output page entries
  # Format: url|controller|action|requires_id|requires_auth|controller_file
  python3 -c "
import json
import sys

with open('$SITEMAP_JSON', 'r') as f:
    sitemap = json.load(f)

for page in sitemap:
    url = page.get('url', '')
    controller = page.get('controller', '')
    action = page.get('action', '')
    requires_id = 'true' if page.get('requires_id', False) else 'false'
    requires_auth = 'true' if page.get('requires_auth', True) else 'false'
    controller_file = page.get('controller_file', '')
    
    # Skip pages that require ID for now (need sample data)
    if requires_id == 'true':
        continue
    
    print(f'{url}|{controller}|{action}|{requires_id}|{requires_auth}|{controller_file}')
" > "$PAGE_LIST.tmp"

  # Apply filter if specified
  if [[ -n "$FILTER_PATTERN" ]]; then
    log_info "Filtering pages by pattern: $FILTER_PATTERN"
    grep -i "$FILTER_PATTERN" "$PAGE_LIST.tmp" > "$PAGE_LIST" || true
    rm -f "$PAGE_LIST.tmp"
  else
    mv "$PAGE_LIST.tmp" "$PAGE_LIST"
  fi
  
  # Apply skip-auth filter
  if [[ "$SKIP_AUTH" -eq 1 ]]; then
    log_info "Skipping pages requiring authentication"
    grep "|false|" "$PAGE_LIST" > "$PAGE_LIST.noauth" || true
    mv "$PAGE_LIST.noauth" "$PAGE_LIST"
  fi
  
  # Apply limit if specified
  if [[ "$LIMIT" -gt 0 ]]; then
    log_info "Limiting to first $LIMIT pages"
    head -n "$LIMIT" "$PAGE_LIST" > "$PAGE_LIST.limited"
    mv "$PAGE_LIST.limited" "$PAGE_LIST"
  fi
}

# Initialize CSV header
init_results_csv() {
  echo "page_url,controller,action,initial_status,final_status,load_time_ms,js_errors,fix_applied,files_modified,fix_description,attempts" > "$RESULTS_CSV"
}

cleanup() {
  local ec=$?
  if [[ -n "${WATCHER_PID:-}" ]] && kill -0 "$WATCHER_PID" >/dev/null 2>&1; then 
    kill "$WATCHER_PID" >/dev/null 2>&1 || true
  fi
  [[ -n "${PROGRESS_FILE:-}" ]] && rm -f "$PROGRESS_FILE" || true
  [[ -n "${FAIL_FILE:-}" ]] && rm -f "$FAIL_FILE" || true
  log_debug "Cleanup done (exit=$ec)"
}
trap cleanup EXIT INT TERM

watch_progress() {
  local total="$1"
  while :; do
    local processed failures
    processed=$(wc -l < "$PROGRESS_FILE" 2>/dev/null | tr -d ' ' || echo 0)
    failures=$(wc -l < "$FAIL_FILE" 2>/dev/null | tr -d ' ' || echo 0)
    log_info "Progress: ${processed}/${total} processed; failures: ${failures:-0}"
    if [[ "$processed" -ge "$total" ]]; then break; fi
    sleep 15
  done
}

run_droid_verify() {
  local page_entry="$1"
  
  # Parse page entry
  IFS='|' read -r page_url controller action requires_id requires_auth controller_file <<< "$page_entry"
  
  local tmpout start_s end_s duration_s status
  tmpout=$(mktemp)
  start_s=$(date +%s)

  # Child logger
  _child_log() {
    local level="$1"; shift; local msg="$*"
    printf "%s [%s] %s\n" "$(date +%H:%M:%S)" "$level" "$msg" >&2
    if [[ -n "${LOG_FILE}" ]]; then 
      printf "%s [%s] %s\n" "$(date +%H:%M:%S)" "$level" "$msg" >> "${LOG_FILE}" || true
    fi
  }

  _child_log info "START $page_url ($controller::$action)"

  local fix_instruction=""
  if [[ "$NO_FIX" -eq 0 ]]; then
    fix_instruction="
4. IF ERRORS FOUND:
   - Analyze error message and stack trace
   - Locate the problematic file (controller: $controller_file)
   - Read the relevant code and identify the issue
   - Apply a fix using the Edit tool
   - Refresh the page and re-verify
   - Repeat up to $MAX_FIX_ATTEMPTS times if still broken
   - Document what you fixed"
  else
    fix_instruction="
4. IF ERRORS FOUND:
   - Document the error but do not attempt to fix"
  fi

  local prompt
  prompt=$(cat <<EOF
Verify and fix page: $page_url
Full URL: ${BASE_URL}${page_url}
Controller: $controller
Controller File: protected/$controller_file
Action: action$action
Requires Auth: $requires_auth

Steps:
1. If requires_auth is true, first login at ${BASE_URL}/site/login with username: ${LOGIN_USER} and password: ${LOGIN_PASS}
2. Navigate to ${BASE_URL}${page_url}
3. Check for errors:
   - HTTP 4xx/5xx responses (check page content for error messages)
   - PHP errors/exceptions displayed on page
   - JavaScript console errors
   - Page not loading or timing out
   - Missing main content area
$fix_instruction
5. Record final status

IMPORTANT: Return ONLY a single pipe-delimited line in this EXACT format:
url|initial_status|final_status|load_ms|js_error_count|fix_applied|files_modified|fix_description|attempts

Where:
- url: The page URL tested
- initial_status: PASS, FAIL, or ERROR (before any fixes)
- final_status: PASS, FAIL, or ERROR (after fixes, same as initial if no fix attempted)
- load_ms: Page load time in milliseconds (estimate if not measurable)
- js_error_count: Number of JavaScript console errors (0 if none)
- fix_applied: YES or NO
- files_modified: Comma-separated list of modified files or NONE
- fix_description: Brief description of fix or N/A
- attempts: Number of fix attempts made (0 if no-fix mode)

Return ONLY the pipe-delimited row, no other text.
EOF
)

  status="ok"
  if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "$page_url|DRY_RUN|DRY_RUN|0|0|NO|NONE|Dry run mode|0" > "$tmpout"
  elif ! droid exec --skip-permissions-unsafe -m claude-haiku-4-5-20251001 -r high "$prompt" > "$tmpout" 2>/dev/null; then
    echo "$page_url|ERROR|ERROR|0|0|NO|NONE|Droid exec failed|0" > "$tmpout"
    echo "$page_entry" >> "$FAIL_FILE"
    status="fail"
  fi

  # Process output and append to CSV
  local processed_output
  processed_output=$(awk -v url="$page_url" -v ctrl="$controller" -v act="$action" '
    BEGIN { FS = "|" }
    /^[[:space:]]*$/ { next }
    /^url\|/ { next }
    /^page_url\|/ { next }
    function csvq(s) { gsub(/"/,"\"\"",s); return "\"" s "\"" }
    NF >= 8 {
      print csvq(url) "," csvq(ctrl) "," csvq(act) "," csvq($2) "," csvq($3) "," csvq($4) "," csvq($5) "," csvq($6) "," csvq($7) "," csvq($8) "," csvq($9)
      found = 1
      exit
    }
    END {
      if (!found) {
        print csvq(url) "," csvq(ctrl) "," csvq(act) ",PARSE_ERROR,PARSE_ERROR,0,0,NO,NONE,Could not parse droid output,0"
      }
    }
  ' "$tmpout")

  if [[ -n "$processed_output" ]]; then
    printf "%s\n" "$processed_output" >> "$RESULTS_CSV"
  fi

  end_s=$(date +%s)
  duration_s=$(( end_s - start_s ))

  if [[ "$status" == "ok" ]]; then
    _child_log info "DONE $page_url in ${duration_s}s"
  else
    _child_log error "FAIL $page_url in ${duration_s}s"
  fi

  echo "$page_entry" >> "$PROGRESS_FILE"
  rm -f "$tmpout"
}

export -f run_droid_verify
export LOG_LEVEL LOG_FILE PROGRESS_FILE FAIL_FILE RESULTS_CSV PROTECTED_DIR DRY_RUN NO_FIX MAX_FIX_ATTEMPTS BASE_URL LOGIN_USER LOGIN_PASS

# Main execution
log_info "============================================"
log_info "Droid Page Verification Script"
log_info "============================================"
log_info "Base URL: $BASE_URL"
log_info "Concurrency: $CONCURRENCY"
log_info "Auto-fix: $([ "$NO_FIX" -eq 0 ] && echo 'enabled' || echo 'disabled')"
log_info "Max fix attempts: $MAX_FIX_ATTEMPTS"
log_info "============================================"

# Phase 1: Generate sitemap
generate_sitemap

if [[ "$SITEMAP_ONLY" -eq 1 ]]; then
  log_info "Sitemap-only mode. Exiting."
  log_info "Sitemap: $SITEMAP_JSON"
  exit 0
fi

# Phase 2: Extract page list and verify
extract_page_list
init_results_csv

TOTAL_PAGES=$(wc -l < "$PAGE_LIST" | tr -d ' ')

if [[ "$TOTAL_PAGES" -eq 0 ]]; then
  log_error "No pages found matching criteria"
  exit 1
fi

log_info "Start: ts=$START_TS concurrency=$CONCURRENCY pages=$TOTAL_PAGES filter=${FILTER_PATTERN:-<none>}"
log_info "Results: $RESULTS_CSV"

PROGRESS_FILE=$(mktemp)
FAIL_FILE=$(mktemp)

watch_progress "$TOTAL_PAGES" &
WATCHER_PID=$!

# Parallelize verification using xargs -P
tr '\n' '\0' < "$PAGE_LIST" | xargs -0 -n 1 -P "$CONCURRENCY" bash -c 'run_droid_verify "$1"' _

# Wait for watcher
if [[ -n "${WATCHER_PID:-}" ]] && kill -0 "$WATCHER_PID" >/dev/null 2>&1; then
  wait "$WATCHER_PID" || true
fi

# Summary
TOTAL_ROWS=$(awk 'NR>1 {c++} END{print c+0}' "$RESULTS_CSV")
PASS_ROWS=$(awk -F, 'NR>1 && $5=="\"PASS\"" {c++} END{print c+0}' "$RESULTS_CSV")
FAIL_ROWS=$(awk -F, 'NR>1 && ($5=="\"FAIL\"" || $5=="\"ERROR\"") {c++} END{print c+0}' "$RESULTS_CSV")
FIXED_ROWS=$(awk -F, 'NR>1 && $8=="\"YES\"" {c++} END{print c+0}' "$RESULTS_CSV")

log_info "==========================================="
log_info "Summary"
log_info "==========================================="
log_info "Total pages verified: $TOTAL_ROWS"
log_info "Passed: $PASS_ROWS"
log_info "Failed/Error: $FAIL_ROWS"
log_info "Auto-fixed: $FIXED_ROWS"
log_info "==========================================="
log_info "Sitemap: $SITEMAP_JSON"
log_info "Results: $RESULTS_CSV"
log_info "==========================================="

# Show failures
if [[ "$FAIL_ROWS" -gt 0 ]]; then
  log_warn "Failed pages:"
  awk -F, 'NR>1 && ($5=="\"FAIL\"" || $5=="\"ERROR\"") { gsub(/"/, "", $1); print "  - " $1 }' "$RESULTS_CSV" | head -20
fi

# Show fixes applied
if [[ "$FIXED_ROWS" -gt 0 ]]; then
  log_info "Pages with fixes applied:"
  awk -F, 'NR>1 && $8=="\"YES\"" { 
    gsub(/"/, "", $1); 
    gsub(/"/, "", $9);
    gsub(/"/, "", $10);
    print "  - " $1 ": " $10 " (files: " $9 ")" 
  }' "$RESULTS_CSV" | head -20
fi
