#!/bin/bash
set -euo pipefail

# Full Page Verification Orchestrator
# Runs both Tier 1 (HTTP checks) and Tier 2 (Cypress CRUD tests)
# Target: Complete verification in ~10 minutes

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Configuration
TIER1_CONCURRENCY="${TIER1_CONCURRENCY:-10}"
SKIP_TIER1="${SKIP_TIER1:-0}"
SKIP_TIER2="${SKIP_TIER2:-0}"
CYPRESS_BROWSER="${CYPRESS_BROWSER:-chrome}"

START_TS=$(date +%Y%m%d_%H%M%S)
LOG_DIR="$PROJECT_DIR/logs"
REPORT_FILE="$LOG_DIR/${START_TS}_verification_report.txt"

mkdir -p "$LOG_DIR"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

log_header() { echo -e "\n${BOLD}${CYAN}=== $* ===${NC}\n"; }
log_info()   { echo -e "${CYAN}[INFO]${NC} $*"; }
log_pass()   { echo -e "${GREEN}[PASS]${NC} $*"; }
log_fail()   { echo -e "${RED}[FAIL]${NC} $*"; }
log_warn()   { echo -e "${YELLOW}[WARN]${NC} $*"; }

usage() {
    cat <<EOF
Usage: $0 [options]

Options:
  --skip-tier1       Skip HTTP health checks (Tier 1)
  --skip-tier2       Skip Cypress CRUD tests (Tier 2)
  --tier1-only       Run only Tier 1
  --tier2-only       Run only Tier 2
  --concurrency N    Tier 1 parallel requests (default: 50)
  --browser BROWSER  Cypress browser (default: chrome)
  -h, --help         Show this help

Environment Variables:
  OPENEYES_URL       Base URL (default: http://localhost:7777)
  OPENEYES_USER      Login username (default: admin)
  OPENEYES_PASS      Login password (default: admin)

Examples:
  $0                           # Run full verification
  $0 --tier1-only              # Only HTTP checks
  $0 --tier2-only              # Only Cypress tests
  $0 --concurrency 100         # Faster HTTP checks
EOF
}

# Parse arguments
while [[ $# -gt 0 ]]; do
    case "$1" in
        --skip-tier1)    SKIP_TIER1=1; shift ;;
        --skip-tier2)    SKIP_TIER2=1; shift ;;
        --tier1-only)    SKIP_TIER2=1; shift ;;
        --tier2-only)    SKIP_TIER1=1; shift ;;
        --concurrency)   TIER1_CONCURRENCY="$2"; shift 2 ;;
        --browser)       CYPRESS_BROWSER="$2"; shift 2 ;;
        -h|--help)       usage; exit 0 ;;
        *)               log_fail "Unknown option: $1"; usage; exit 1 ;;
    esac
done

# Initialize report
{
    echo "========================================"
    echo "Page Verification Report"
    echo "========================================"
    echo "Timestamp: $(date)"
    echo "Base URL: ${OPENEYES_URL:-http://localhost:7777}"
    echo ""
} > "$REPORT_FILE"

OVERALL_STATUS=0
TIER1_STATUS=0
TIER2_STATUS=0
tier1_total=0
tier1_pass=0
tier1_fail=0
tier1_warn=0
tier1_csv=""

log_header "Page Verification - Fast Deterministic Mode"
log_info "Timestamp: $START_TS"
log_info "Report: $REPORT_FILE"

START_TIME=$(date +%s)

# Tier 1: HTTP Health Checks
if [[ "$SKIP_TIER1" -eq 0 ]]; then
    log_header "Tier 1: HTTP Health Checks"
    log_info "Running parallel HTTP checks with concurrency $TIER1_CONCURRENCY..."
    
    TIER1_START=$(date +%s)
    
    if "$SCRIPT_DIR/page-verify-fast.sh" "$TIER1_CONCURRENCY"; then
        TIER1_STATUS=0
        log_pass "Tier 1 completed successfully"
    else
        TIER1_STATUS=1
        OVERALL_STATUS=1
        log_fail "Tier 1 found failures"
    fi
    
    TIER1_END=$(date +%s)
    TIER1_DURATION=$((TIER1_END - TIER1_START))
    
    # Get Tier 1 stats from the latest CSV
    tier1_csv=$(ls -t "$LOG_DIR"/*_http_verify_results.csv 2>/dev/null | head -1)
    if [[ -f "$tier1_csv" ]]; then
        tier1_total=$(awk -F, 'NR>1 {c++} END{print c+0}' "$tier1_csv")
        tier1_pass=$(awk -F, 'NR>1 && $6=="\"PASS\"" {c++} END{print c+0}' "$tier1_csv")
        tier1_warn=$(awk -F, 'NR>1 && $6=="\"WARN\"" {c++} END{print c+0}' "$tier1_csv")
        tier1_fail=$(awk -F, 'NR>1 && $6=="\"FAIL\"" {c++} END{print c+0}' "$tier1_csv")
    fi
    
    {
        echo "Tier 1: HTTP Health Checks"
        echo "Status: $([ $TIER1_STATUS -eq 0 ] && echo 'PASS' || echo 'FAIL')"
        echo "Duration: ${TIER1_DURATION}s"
        echo "Pages Checked: $tier1_total"
        echo "Passed: $tier1_pass"
        echo "Warnings: $tier1_warn"
        echo "Failed: $tier1_fail"
        if [[ "$tier1_fail" -gt 0 && -f "$tier1_csv" ]]; then
            echo ""
            echo "Top Failures:"
            awk -F, 'NR>1 && $6=="\"FAIL\"" {
                gsub(/"/, "", $1); gsub(/"/, "", $8);
                if ($1 !~ /^\[/) print "  - " $1 ": " $8
            }' "$tier1_csv" | head -15
        fi
        echo ""
    } >> "$REPORT_FILE"
    
    log_info "Tier 1 duration: ${TIER1_DURATION}s"
else
    log_info "Skipping Tier 1 (HTTP checks)"
fi

# Tier 2: Cypress CRUD Tests
if [[ "$SKIP_TIER2" -eq 0 ]]; then
    log_header "Tier 2: Cypress CRUD Verification"
    log_info "Running Cypress admin CRUD tests..."
    
    TIER2_START=$(date +%s)
    
    cd "$PROJECT_DIR"
    
    # Check if Cypress is available
    if command -v npx >/dev/null 2>&1; then
        # Override baseUrl to include port (cypress.config.js has localhost without port)
        if npx cypress run \
            --browser "$CYPRESS_BROWSER" \
            --spec "cypress/e2e/admin/admin-crud-verification.cy.js" \
            --config "video=false,baseUrl=${OPENEYES_URL:-http://localhost:7777}" \
            --reporter spec 2>&1 | tee "$LOG_DIR/${START_TS}_cypress.log"; then
            TIER2_STATUS=0
            log_pass "Tier 2 completed successfully"
        else
            TIER2_STATUS=1
            OVERALL_STATUS=1
            log_fail "Tier 2 found failures"
        fi
    else
        log_warn "Cypress not found, skipping Tier 2"
        log_info "Install with: npm install cypress"
        TIER2_STATUS=2
    fi
    
    TIER2_END=$(date +%s)
    TIER2_DURATION=$((TIER2_END - TIER2_START))
    
    {
        echo "Tier 2: Cypress CRUD Tests"
        echo "Status: $([ $TIER2_STATUS -eq 0 ] && echo 'PASS' || [ $TIER2_STATUS -eq 2 ] && echo 'SKIPPED' || echo 'FAIL')"
        echo "Duration: ${TIER2_DURATION}s"
        echo "Log: $LOG_DIR/${START_TS}_cypress.log"
        echo ""
    } >> "$REPORT_FILE"
    
    log_info "Tier 2 duration: ${TIER2_DURATION}s"
else
    log_info "Skipping Tier 2 (Cypress tests)"
fi

END_TIME=$(date +%s)
TOTAL_DURATION=$((END_TIME - START_TIME))

# Final Summary
log_header "Verification Complete"

# Calculate percentages
pass_rate="N/A"
if [[ "$tier1_total" -gt 0 ]]; then
    pass_rate=$(awk "BEGIN {printf \"%.1f\", ($tier1_pass / $tier1_total) * 100}")
fi

{
    echo "========================================"
    echo "Summary"
    echo "========================================"
    echo "Total Duration: ${TOTAL_DURATION}s (~$(( TOTAL_DURATION / 60 )) min)"
    echo "Overall Status: $([ $OVERALL_STATUS -eq 0 ] && echo 'PASS' || echo 'FAIL')"
    echo ""
    echo "Tier 1 Pass Rate: ${pass_rate}% ($tier1_pass/$tier1_total pages)"
    if [[ "$SKIP_TIER2" -eq 1 ]]; then
        echo "Tier 2 Status: SKIPPED (--tier1-only)"
    elif [[ "$TIER2_STATUS" -eq 0 ]]; then
        echo "Tier 2 Status: PASS"
    elif [[ "$TIER2_STATUS" -eq 2 ]]; then
        echo "Tier 2 Status: SKIPPED (Cypress not found)"
    else
        echo "Tier 2 Status: FAIL"
    fi
    echo ""
    echo "Files Generated:"
    echo "  Report: $REPORT_FILE"
    ls "$LOG_DIR"/${START_TS}*.csv 2>/dev/null | while read f; do echo "  CSV: $f"; done || true
    ls "$LOG_DIR"/${START_TS}*.log 2>/dev/null | while read f; do echo "  Log: $f"; done || true
    echo ""
    echo "========================================"
    echo "Quick Commands:"
    echo "  View failures: grep FAIL $REPORT_FILE"
    echo "  CSV analysis:  cat ${tier1_csv:-N/A} | head -50"
    echo "========================================"
} >> "$REPORT_FILE"

log_info "Total duration: ${TOTAL_DURATION}s"

if [[ "$OVERALL_STATUS" -eq 0 ]]; then
    log_pass "All verifications passed!"
else
    log_fail "Some verifications failed - check report: $REPORT_FILE"
fi

log_info "Report: $REPORT_FILE"

exit $OVERALL_STATUS
