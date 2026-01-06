#!/bin/bash
#
# Droid Model Verification Script
# Spins up parallel droids to verify Couchbase functionality for each data model
#
# Usage: ./droid-model-verify.sh [--max-parallel N] [--filter PATTERN]
#

set -e

# Configuration
MAX_PARALLEL=${MAX_PARALLEL:-5}
PROJECT_DIR="/Users/asahu/Desktop/untitled folder/openeyes"
PROTECTED_DIR="$PROJECT_DIR/protected"
LOG_DIR="$PROJECT_DIR/logs/model-verify"
RESULTS_FILE="$LOG_DIR/results-$(date +%Y%m%d-%H%M%S).json"
FILTER_PATTERN=""

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --max-parallel)
            MAX_PARALLEL="$2"
            shift 2
            ;;
        --filter)
            FILTER_PATTERN="$2"
            shift 2
            ;;
        --help)
            echo "Usage: $0 [--max-parallel N] [--filter PATTERN]"
            echo ""
            echo "Options:"
            echo "  --max-parallel N    Maximum parallel droids (default: 5)"
            echo "  --filter PATTERN    Filter models by pattern (e.g., 'Allergy')"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Create log directory
mkdir -p "$LOG_DIR"

echo "==========================================="
echo "  Droid Model Verification Script"
echo "==========================================="
echo "Max Parallel: $MAX_PARALLEL"
echo "Project Dir: $PROJECT_DIR"
echo "Log Dir: $LOG_DIR"
echo ""

# Find all models with CouchbaseModelBridge trait
echo "Finding models with CouchbaseModelBridge trait..."

MODELS_FILE=$(mktemp)

# Find PHP files that use CouchbaseModelBridge
grep -rl "use.*CouchbaseModelBridge\|CouchbaseModelBridge" "$PROTECTED_DIR" --include="*.php" 2>/dev/null | \
    grep -E "/models/[^/]+\.php$" | \
    grep -v "/traits/" | \
    grep -v "/couchbase/" | \
    grep -v "Test\.php$" | \
    sort -u > "$MODELS_FILE"

# Apply filter if specified
if [ -n "$FILTER_PATTERN" ]; then
    echo "Filtering models by pattern: $FILTER_PATTERN"
    grep -i "$FILTER_PATTERN" "$MODELS_FILE" > "${MODELS_FILE}.filtered" || true
    mv "${MODELS_FILE}.filtered" "$MODELS_FILE"
fi

TOTAL_MODELS=$(wc -l < "$MODELS_FILE" | tr -d ' ')
echo "Found $TOTAL_MODELS models to verify"
echo ""

if [ "$TOTAL_MODELS" -eq 0 ]; then
    echo "No models found to verify."
    rm "$MODELS_FILE"
    exit 0
fi

# Initialize results
echo "{" > "$RESULTS_FILE"
echo '  "started": "'$(date -Iseconds)'",' >> "$RESULTS_FILE"
echo '  "total_models": '"$TOTAL_MODELS"',' >> "$RESULTS_FILE"
echo '  "models": [' >> "$RESULTS_FILE"

# Function to extract model info from file path
get_model_info() {
    local file_path="$1"
    local rel_path="${file_path#$PROTECTED_DIR/}"
    local model_name=$(basename "$file_path" .php)
    
    # Determine admin URL based on model
    local admin_url=""
    case "$model_name" in
        OphCiExaminationAllergy)
            admin_url="/OphCiExamination/admin/Allergies/index"
            ;;
        OphCiExaminationAllergyReaction)
            admin_url="/OphCiExamination/admin/AllergyReactions/index"
            ;;
        Disorder)
            admin_url="/admin/editdiagnosis"
            ;;
        Procedure)
            admin_url="/admin/editprocedure"
            ;;
        Medication)
            admin_url="/medication/admin"
            ;;
        User)
            admin_url="/admin/users"
            ;;
        Firm)
            admin_url="/admin/firms"
            ;;
        Site)
            admin_url="/admin/sites"
            ;;
        Institution)
            admin_url="/admin/institutions"
            ;;
        *)
            # Try to derive admin URL from model name
            admin_url=""
            ;;
    esac
    
    echo "$model_name|$rel_path|$admin_url"
}

# Function to create the prompt for a model verification
create_verification_prompt() {
    local model_name="$1"
    local model_path="$2"
    local admin_url="$3"
    
    cat << EOF
You are verifying Couchbase functionality for the data model: $model_name

Model file: $model_path

Please perform the following verification steps:

1. **Check Model Configuration**:
   - Verify the model has CouchbaseModelBridge trait
   - Check if afterSave() and afterDelete() hooks are implemented
   - Verify couchbaseScope() and couchbaseCollection() methods exist
   - Check the scope mapping in CouchbaseAdapter.php

2. **Check Couchbase Collection**:
   - Verify the collection exists in Couchbase
   - Check if a primary index exists for the collection
   - Query to see if there's any data in the collection

3. **Functional Test** (if admin URL is available):
   - Navigate to the admin page$([ -n "$admin_url" ] && echo ": http://localhost:7777$admin_url")
   - Try to create a test record
   - Verify it persists after page refresh
   - Check the Couchbase collection for the new record

4. **Report Issues**:
   - List any missing hooks or methods
   - Report if collection is missing
   - Note any errors in the logs

Provide a summary with:
- Status: PASS/FAIL/NEEDS_WORK
- Issues found (if any)
- Fixes applied (if any)
- Recommendations

Start by reading the model file to understand its current implementation.
EOF
}

# Function to run a single droid verification
run_droid_verification() {
    local model_name="$1"
    local model_path="$2"
    local admin_url="$3"
    local log_file="$LOG_DIR/${model_name}.log"
    
    echo "[$(date +%H:%M:%S)] Starting verification: $model_name"
    
    local prompt=$(create_verification_prompt "$model_name" "$model_path" "$admin_url")
    
    # Run droid exec with the verification prompt
    if command -v droid &> /dev/null; then
        # Use --auto medium to allow file reads and curl commands for Couchbase queries
        echo "$prompt" | timeout 300 droid exec --auto medium -m claude-sonnet-4-5-20250929 > "$log_file" 2>&1 || true
    else
        # Fallback: just log what would be done
        echo "DROID NOT FOUND - Would verify: $model_name" > "$log_file"
        echo "Prompt:" >> "$log_file"
        echo "$prompt" >> "$log_file"
    fi
    
    echo "[$(date +%H:%M:%S)] Completed verification: $model_name -> $log_file"
}

# Export functions for parallel execution
export -f run_droid_verification create_verification_prompt get_model_info
export PROTECTED_DIR LOG_DIR

# Process models in parallel
CURRENT=0
PIDS=()

while IFS= read -r model_file; do
    CURRENT=$((CURRENT + 1))
    
    # Get model info
    INFO=$(get_model_info "$model_file")
    MODEL_NAME=$(echo "$INFO" | cut -d'|' -f1)
    MODEL_PATH=$(echo "$INFO" | cut -d'|' -f2)
    ADMIN_URL=$(echo "$INFO" | cut -d'|' -f3)
    
    echo "[$CURRENT/$TOTAL_MODELS] Queuing: $MODEL_NAME"
    
    # Run verification in background
    run_droid_verification "$MODEL_NAME" "$MODEL_PATH" "$ADMIN_URL" &
    PIDS+=($!)
    
    # Wait if we've hit max parallel
    if [ ${#PIDS[@]} -ge $MAX_PARALLEL ]; then
        # Wait for any one process to finish
        wait -n "${PIDS[@]}" 2>/dev/null || true
        # Remove finished PIDs
        NEW_PIDS=()
        for pid in "${PIDS[@]}"; do
            if kill -0 "$pid" 2>/dev/null; then
                NEW_PIDS+=($pid)
            fi
        done
        PIDS=("${NEW_PIDS[@]}")
    fi
    
done < "$MODELS_FILE"

# Wait for all remaining processes
echo ""
echo "Waiting for remaining verifications to complete..."
for pid in "${PIDS[@]}"; do
    wait "$pid" 2>/dev/null || true
done

# Clean up
rm "$MODELS_FILE"

# Generate summary
echo ""
echo "==========================================="
echo "  Verification Complete"
echo "==========================================="
echo "Total models verified: $TOTAL_MODELS"
echo "Log directory: $LOG_DIR"
echo ""

# Count results
PASS_COUNT=$(grep -l "PASS\|Status: PASS" "$LOG_DIR"/*.log 2>/dev/null | wc -l | tr -d ' ')
FAIL_COUNT=$(grep -l "FAIL\|Status: FAIL" "$LOG_DIR"/*.log 2>/dev/null | wc -l | tr -d ' ')
NEEDS_WORK=$(grep -l "NEEDS_WORK\|needs work" "$LOG_DIR"/*.log 2>/dev/null | wc -l | tr -d ' ')

echo "Results:"
echo "  PASS: $PASS_COUNT"
echo "  FAIL: $FAIL_COUNT"
echo "  NEEDS_WORK: $NEEDS_WORK"
echo ""

# List failures
if [ "$FAIL_COUNT" -gt 0 ]; then
    echo "Failed models:"
    grep -l "FAIL\|Status: FAIL" "$LOG_DIR"/*.log 2>/dev/null | while read f; do
        echo "  - $(basename "$f" .log)"
    done
    echo ""
fi

# Finalize results file
echo '  ],' >> "$RESULTS_FILE"
echo '  "completed": "'$(date -Iseconds)'",' >> "$RESULTS_FILE"
echo '  "summary": {' >> "$RESULTS_FILE"
echo '    "pass": '"$PASS_COUNT"',' >> "$RESULTS_FILE"
echo '    "fail": '"$FAIL_COUNT"',' >> "$RESULTS_FILE"
echo '    "needs_work": '"$NEEDS_WORK"'' >> "$RESULTS_FILE"
echo '  }' >> "$RESULTS_FILE"
echo '}' >> "$RESULTS_FILE"

echo "Results saved to: $RESULTS_FILE"
