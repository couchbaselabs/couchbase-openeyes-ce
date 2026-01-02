#!/bin/bash
#
# Parallel Droid Model Verification
# Spins up parallel Factory droid sessions to verify Couchbase functionality for each model
#
# Usage: ./droid-parallel-verify.sh [--max-parallel N] [--filter PATTERN] [--start-from N]
#
# Requirements:
#   - Factory CLI installed (factory command)
#   - OR droid command available
#

set -e

# Configuration
MAX_PARALLEL=${MAX_PARALLEL:-3}
PROJECT_DIR="/Users/asahu/Desktop/untitled folder/openeyes"
TASKS_FILE="$PROJECT_DIR/model-verification-tasks.txt"
LOG_DIR="$PROJECT_DIR/logs/droid-verify-$(date +%Y%m%d-%H%M%S)"
FILTER_PATTERN=""
START_FROM=1
DRY_RUN=false

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --max-parallel|-p)
            MAX_PARALLEL="$2"
            shift 2
            ;;
        --filter|-f)
            FILTER_PATTERN="$2"
            shift 2
            ;;
        --start-from|-s)
            START_FROM="$2"
            shift 2
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --help|-h)
            echo "Parallel Droid Model Verification"
            echo ""
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  -p, --max-parallel N    Max parallel droids (default: 3)"
            echo "  -f, --filter PATTERN    Filter models by pattern"
            echo "  -s, --start-from N      Start from model number N"
            echo "  --dry-run               Show what would be done without running"
            echo "  -h, --help              Show this help"
            echo ""
            echo "Examples:"
            echo "  $0 --filter Allergy              # Only verify allergy-related models"
            echo "  $0 --max-parallel 5              # Run 5 droids in parallel"
            echo "  $0 --start-from 100 --filter Op  # Start from model 100, filter by 'Op'"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Create directories
mkdir -p "$LOG_DIR"

echo "==========================================="
echo "  Parallel Droid Model Verification"
echo "==========================================="
echo ""
echo "Configuration:"
echo "  Max Parallel: $MAX_PARALLEL"
echo "  Filter: ${FILTER_PATTERN:-<none>}"
echo "  Start From: $START_FROM"
echo "  Log Dir: $LOG_DIR"
echo "  Dry Run: $DRY_RUN"
echo ""

# Generate tasks if not exists
if [ ! -f "$TASKS_FILE" ]; then
    echo "Generating task file..."
    "$PROJECT_DIR/scripts/generate-model-tasks.sh" --output "$TASKS_FILE"
fi

# Read and filter tasks
TASKS=$(grep -v "^#" "$TASKS_FILE" | grep -v "^$")
if [ -n "$FILTER_PATTERN" ]; then
    TASKS=$(echo "$TASKS" | grep -i "$FILTER_PATTERN" || true)
fi

TOTAL=$(echo "$TASKS" | grep -c . || echo 0)
echo "Total models to verify: $TOTAL"
echo ""

if [ "$TOTAL" -eq 0 ]; then
    echo "No models found matching criteria."
    exit 0
fi

# Function to create verification prompt
create_prompt() {
    local model_name="$1"
    local model_path="$2"
    local table_name="$3"
    
    cat << PROMPT
Verify Couchbase functionality for model: $model_name
File: protected/$model_path
Table: $table_name

## Verification Steps:

1. **Read the model file** and check:
   - Has CouchbaseModelBridge trait? 
   - Has afterSave() calling saveToCouchbase()?
   - Has afterDelete() calling deleteFromCouchbase()?
   - Has couchbaseScope() method?
   - Has couchbaseCollection() method?

2. **Check CouchbaseAdapter scope mapping**:
   - Is '$table_name' in the scopeMapping array?
   - What scope is it mapped to?

3. **Check Couchbase collection exists**:
   Run: curl -s "http://localhost:8093/query/service" -u Administrator:password -d "statement=SELECT COUNT(*) FROM \\\`openeyes\\\`.\\\`<scope>\\\`.\\\`$table_name\\\` LIMIT 1"

4. **Report findings**:
   - STATUS: PASS / FAIL / INCOMPLETE
   - Missing: List any missing hooks/methods
   - Action: What needs to be fixed

Be concise. Focus on Couchbase integration only.
PROMPT
}

# Function to run a single droid
run_droid() {
    local idx="$1"
    local model_name="$2"
    local model_path="$3"
    local table_name="$4"
    local log_file="$LOG_DIR/${model_name}.log"
    
    local prompt=$(create_prompt "$model_name" "$model_path" "$table_name")
    
    echo -e "${YELLOW}[$idx/$TOTAL]${NC} Starting: $model_name"
    
    if [ "$DRY_RUN" = true ]; then
        echo "[DRY RUN] Would verify: $model_name" > "$log_file"
        echo "Prompt:" >> "$log_file"
        echo "$prompt" >> "$log_file"
        sleep 0.5
    else
        # Try factory droid first, then fall back to droid command
        if command -v factory &> /dev/null; then
            echo "$prompt" | timeout 180 factory droid -m claude-sonnet --no-interactive > "$log_file" 2>&1 || true
        elif command -v droid &> /dev/null; then
            echo "$prompt" | timeout 180 droid --no-interactive > "$log_file" 2>&1 || true
        else
            echo "ERROR: Neither 'factory' nor 'droid' command found" > "$log_file"
            echo "Install Factory CLI: https://docs.factory.ai" >> "$log_file"
        fi
    fi
    
    # Check result
    if grep -qi "PASS\|Status: PASS" "$log_file" 2>/dev/null; then
        echo -e "${GREEN}[$idx/$TOTAL]${NC} PASS: $model_name"
    elif grep -qi "FAIL\|Status: FAIL" "$log_file" 2>/dev/null; then
        echo -e "${RED}[$idx/$TOTAL]${NC} FAIL: $model_name"
    else
        echo -e "${YELLOW}[$idx/$TOTAL]${NC} DONE: $model_name"
    fi
}

# Export for parallel
export -f run_droid create_prompt
export LOG_DIR DRY_RUN TOTAL

# Process models
CURRENT=0
PIDS=()

echo "$TASKS" | while IFS='|' read -r model_name model_path table_name; do
    CURRENT=$((CURRENT + 1))
    
    # Skip if before start position
    if [ "$CURRENT" -lt "$START_FROM" ]; then
        continue
    fi
    
    # Run in background
    run_droid "$CURRENT" "$model_name" "$model_path" "$table_name" &
    PIDS+=($!)
    
    # Limit parallelism
    if [ ${#PIDS[@]} -ge $MAX_PARALLEL ]; then
        wait -n 2>/dev/null || wait "${PIDS[0]}"
        # Clean up finished PIDs
        NEW_PIDS=()
        for pid in "${PIDS[@]}"; do
            if kill -0 "$pid" 2>/dev/null; then
                NEW_PIDS+=($pid)
            fi
        done
        PIDS=("${NEW_PIDS[@]}")
    fi
done

# Wait for remaining
wait

# Generate summary
echo ""
echo "==========================================="
echo "  Verification Summary"
echo "==========================================="

PASS_COUNT=$(grep -rli "PASS\|Status: PASS" "$LOG_DIR"/*.log 2>/dev/null | wc -l | tr -d ' ')
FAIL_COUNT=$(grep -rli "FAIL\|Status: FAIL" "$LOG_DIR"/*.log 2>/dev/null | wc -l | tr -d ' ')

echo "Total: $TOTAL"
echo -e "Pass:  ${GREEN}$PASS_COUNT${NC}"
echo -e "Fail:  ${RED}$FAIL_COUNT${NC}"
echo ""
echo "Logs: $LOG_DIR"

# List failures
if [ "$FAIL_COUNT" -gt 0 ]; then
    echo ""
    echo "Failed models:"
    grep -rli "FAIL\|Status: FAIL" "$LOG_DIR"/*.log 2>/dev/null | while read f; do
        echo "  - $(basename "$f" .log)"
    done
fi

# Create summary file
SUMMARY_FILE="$LOG_DIR/SUMMARY.md"
cat > "$SUMMARY_FILE" << EOF
# Model Verification Summary

**Date:** $(date)
**Total Models:** $TOTAL
**Pass:** $PASS_COUNT
**Fail:** $FAIL_COUNT

## Failed Models

$(grep -rli "FAIL" "$LOG_DIR"/*.log 2>/dev/null | while read f; do echo "- $(basename "$f" .log)"; done || echo "None")

## Models Needing Attention

$(grep -rli "INCOMPLETE\|missing\|needs" "$LOG_DIR"/*.log 2>/dev/null | while read f; do echo "- $(basename "$f" .log)"; done || echo "None")
EOF

echo ""
echo "Summary: $SUMMARY_FILE"
