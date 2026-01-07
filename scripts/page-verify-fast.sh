#!/bin/bash
set -euo pipefail

# Fast Deterministic Page Verification - Tier 1: HTTP Health Checks
# Checks all pages in parallel for HTTP errors and PHP exceptions
# Target: ~1,413 pages in ~2 minutes

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
PROTECTED_DIR="$PROJECT_DIR/protected"

BASE_URL="${OPENEYES_URL:-http://localhost:7777}"
LOGIN_USER="${OPENEYES_USER:-admin}"
LOGIN_PASS="${OPENEYES_PASS:-admin}"
CONCURRENCY="${1:-50}"

START_TS=$(date +%Y%m%d_%H%M%S)
LOG_DIR="$PROJECT_DIR/logs"
RESULTS_CSV="$LOG_DIR/${START_TS}_http_verify_results.csv"
COOKIE_FILE="$LOG_DIR/${START_TS}_cookies.txt"
URL_LIST="$LOG_DIR/${START_TS}_urls.txt"

mkdir -p "$LOG_DIR"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info()  { echo -e "${CYAN}[INFO]${NC} $*"; }
log_pass()  { echo -e "${GREEN}[PASS]${NC} $*"; }
log_fail()  { echo -e "${RED}[FAIL]${NC} $*"; }
log_warn()  { echo -e "${YELLOW}[WARN]${NC} $*"; }

# Login and get session cookie using CypressHelper endpoint (most reliable)
login() {
    log_info "Logging in as $LOGIN_USER..."
    
    # Use CypressHelper login endpoint (same as Cypress tests use)
    local login_response
    login_response=$(curl -s -w "\n%{http_code}" -c "$COOKIE_FILE" -b "$COOKIE_FILE" \
        -X POST "$BASE_URL/CypressHelper/Default/login" \
        -d "username=$LOGIN_USER" \
        -d "password=$LOGIN_PASS" \
        -d "site_id=1" \
        -d "institution_id=1" \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -L 2>/dev/null)
    
    local status="${login_response##*$'\n'}"
    local body="${login_response%$'\n'*}"
    
    if [[ "$status" == "200" ]] && echo "$body" | grep -q "success\|true"; then
        log_info "Login successful (via CypressHelper)"
        return 0
    elif [[ "$status" == "200" ]]; then
        # CypressHelper returns 200 even on success, check if we have session
        log_info "Login completed (status 200)"
        return 0
    else
        log_warn "CypressHelper login returned status $status, trying standard login..."
        
        # Fallback to standard login form
        local login_page
        login_page=$(curl -s -c "$COOKIE_FILE" -b "$COOKIE_FILE" "$BASE_URL/site/login")
        local csrf_token
        csrf_token=$(echo "$login_page" | grep -o 'name="YII_CSRF_TOKEN" value="[^"]*"' | sed 's/.*value="\([^"]*\)".*/\1/' || echo "")
        
        login_response=$(curl -s -w "\n%{http_code}" -c "$COOKIE_FILE" -b "$COOKIE_FILE" \
            -X POST "$BASE_URL/site/login" \
            -d "LoginForm[username]=$LOGIN_USER" \
            -d "LoginForm[password]=$LOGIN_PASS" \
            -d "LoginForm[site_id]=1" \
            -d "LoginForm[institution_id]=1" \
            -d "YII_CSRF_TOKEN=$csrf_token" \
            -H "Content-Type: application/x-www-form-urlencoded" \
            -L 2>/dev/null)
        
        status="${login_response##*$'\n'}"
        
        if [[ "$status" == "200" ]] || [[ "$status" == "302" ]]; then
            log_info "Login successful (via standard form)"
            return 0
        else
            log_fail "Login failed with status $status"
            return 1
        fi
    fi
}

# Generate URLs from controllers
generate_urls() {
    # Log to stderr so it doesn't pollute stdout
    echo "[INFO] Generating URL list from controllers..." >&2
    
    # Use a temp file to avoid subshell issues with pipes
    local controller_list=$(mktemp)
    find "$PROTECTED_DIR" -path "*/controllers/*Controller.php" -type f | \
        grep -v "/tests/" | \
        grep -vE "Base.*Controller|BaseController" | \
        sort -u > "$controller_list"
    
    while read -r controller_file; do
        [[ -z "$controller_file" ]] && continue
        
        local rel_path="${controller_file#$PROTECTED_DIR/}"
        local controller_name=$(basename "$controller_file" .php)
        local controller_id=$(echo "$controller_name" | sed 's/Controller$//' | awk '{print tolower(substr($0,1,1)) substr($0,2)}')
        
        # Determine route prefix for modules / controller subdirectories
        local route_prefix=""
        if [[ "$rel_path" == modules/* ]]; then
            local module=$(echo "$rel_path" | cut -d'/' -f2)
            if [[ "$rel_path" == modules/*/modules/* ]]; then
                local parent_module=$(echo "$rel_path" | cut -d'/' -f2)
                local sub_module=$(echo "$rel_path" | cut -d'/' -f4)
                route_prefix="/$parent_module/$sub_module"
            elif [[ "$rel_path" == *"/oeadmin/"* ]]; then
                route_prefix="/$module/oeadmin"
            else
                route_prefix="/$module"
            fi
        elif [[ "$rel_path" == controllers/oeadmin/* ]]; then
            route_prefix="/oeadmin"
        fi

        # Extract action methods (with args)
        local actions
        actions=$(grep -oE "public function action[A-Z][a-zA-Z0-9_]*\([^)]*\)" "$controller_file" 2>/dev/null | \
            sed -E 's/public function action([A-Za-z0-9_]+)\(([^)]*)\)/\1|\2/' || true)

        while IFS='|' read -r action_name action_args; do
            [[ -z "$action_name" ]] && continue

            local category="SAFE_GET"
            local has_args=0
            if [[ -n "${action_args// /}" ]]; then
                has_args=1
                category="PARAMETERIZED"
            fi

            if [[ "$action_name" =~ (Create|Update|Delete|Add|Remove|Save|Sort|Assign|Cancel|Confirm|Submit|Reorder|Merge|Upload|Download|Export|Import|Generate|Run|Rebuild|Reset|Enable|Disable|Set|Clear|Send|Resend|Approve|Reject)$ ]]; then
                category="MUTATING"
            fi

            local action_url=$(echo "$action_name" | awk '{print tolower(substr($0,1,1)) substr($0,2)}')
            local full_url="${route_prefix}/${controller_id}/${action_url}"
            if [[ "$has_args" -eq 1 ]]; then
                full_url="${full_url}/1"
            fi
            full_url=$(echo "$full_url" | sed 's|//|/|g')

            echo "$full_url|$controller_name|$action_name|$rel_path|$category"
        done <<< "$actions"
    done < "$controller_list"
    
    rm -f "$controller_list"
}

# Check a single page
check_page() {
    local entry="$1"
    local cookie_file="$2"
    local base_url="$3"
    
    IFS='|' read -r url controller action file category <<< "$entry"
    
    local full_url="${base_url}${url}"
    local start_s=$(date +%s)
    
    # Fetch page with timeout
    local response
    response=$(curl -s -w "\n%{http_code}" -b "$cookie_file" \
        --max-time 10 \
        --connect-timeout 5 \
        "$full_url" 2>/dev/null) || response=$'\n000'
    
    local end_s=$(date +%s)
    local duration=$((end_s - start_s))
    
    local status="${response##*$'\n'}"
    local body="${response%$'\n'*}"
    
    # Check for various error conditions
    local result="PASS"
    local error_msg=""
    
    if echo "$body" | grep -qE "Fatal error|PHP Fatal"; then
        result="FAIL"
        error_msg="PHP Fatal Error"
    elif echo "$body" | grep -qE "CException|CHttpException|Exception.*Stack trace"; then
        result="FAIL"
        error_msg="PHP Exception"
    elif echo "$body" | grep -qE "Parse error|syntax error"; then
        result="FAIL"
        error_msg="PHP Parse Error"
    elif [[ "$status" == "000" ]]; then
        result="FAIL"
        error_msg="Connection timeout/refused"
    elif [[ "$status" =~ ^5 ]]; then
        result="FAIL"
        error_msg="HTTP $status"
    elif [[ "$status" == "404" && "$category" == "SAFE_GET" ]]; then
        result="FAIL"
        error_msg="HTTP 404"
    elif [[ "$status" =~ ^4 ]]; then
        result="WARN"
        error_msg="HTTP $status"
    elif echo "$body" | grep -qE "Call to undefined|Undefined variable|Undefined index"; then
        result="WARN"
        error_msg="PHP Notice/Warning"
    fi
    
    # Output CSV row
    echo "\"$url\",\"$controller\",\"$action\",\"$category\",\"$status\",\"$result\",\"$duration\",\"$error_msg\""
}

export -f check_page

# Main execution
main() {
    log_info "============================================"
    log_info "Fast Page Verification - Tier 1 HTTP Checks"
    log_info "============================================"
    log_info "Base URL: $BASE_URL"
    log_info "Concurrency: $CONCURRENCY"
    log_info "============================================"
    
    # Login first
    if ! login; then
        log_fail "Cannot proceed without login"
        exit 1
    fi
    
    # Generate URL list
    generate_urls > "$URL_LIST"
    local total_urls=$(wc -l < "$URL_LIST" | tr -d ' ')
    log_info "Generated $total_urls URLs to check"
    
    if [[ "$total_urls" -eq 0 ]]; then
        log_fail "No URLs found"
        exit 1
    fi
    
    # Initialize CSV
    echo "url,controller,action,category,http_status,result,duration_ms,error" > "$RESULTS_CSV"
    
    # Run parallel checks
    log_info "Starting parallel HTTP checks..."
    local start_time=$(date +%s)
    
    cat "$URL_LIST" | xargs -P "$CONCURRENCY" -I {} bash -c \
        'check_page "$1" "$2" "$3"' _ {} "$COOKIE_FILE" "$BASE_URL" >> "$RESULTS_CSV"
    
    local end_time=$(date +%s)
    local elapsed=$((end_time - start_time))
    
    # Generate summary
    local total=$(awk -F, 'NR>1 {c++} END{print c+0}' "$RESULTS_CSV")
    local passed=$(awk -F, 'NR>1 && $6=="\"PASS\"" {c++} END{print c+0}' "$RESULTS_CSV")
    local failed=$(awk -F, 'NR>1 && $6=="\"FAIL\"" {c++} END{print c+0}' "$RESULTS_CSV")
    local warned=$(awk -F, 'NR>1 && $6=="\"WARN\"" {c++} END{print c+0}' "$RESULTS_CSV")
    
    log_info "============================================"
    log_info "Results Summary"
    log_info "============================================"
    log_info "Total pages checked: $total"
    log_pass "Passed: $passed"
    [[ "$warned" -gt 0 ]] && log_warn "Warnings: $warned"
    [[ "$failed" -gt 0 ]] && log_fail "Failed: $failed"
    log_info "Time elapsed: ${elapsed}s"
    log_info "Results: $RESULTS_CSV"
    log_info "============================================"
    
    # Show failures
    if [[ "$failed" -gt 0 ]]; then
        log_fail "Failed pages:"
        awk -F, 'NR>1 && $6=="\"FAIL\"" {
            gsub(/"/, "", $1); gsub(/"/, "", $8);
            print "  " $1 " - " $8
        }' "$RESULTS_CSV" | head -30
    fi
    
    # Cleanup
    rm -f "$COOKIE_FILE" "$URL_LIST"
    
    # Exit with failure if any pages failed
    [[ "$failed" -gt 0 ]] && exit 1
    exit 0
}

main "$@"
