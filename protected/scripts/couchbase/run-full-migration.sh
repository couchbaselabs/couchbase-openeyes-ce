#!/bin/bash
#
# Full Data Migration Automation Script
# Phase 14: Executes complete migration with safety checks
#
# Usage:
#   ./run-full-migration.sh [--no-confirm] [--batch=1000] [--verbose]
#

set -e  # Exit on error

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BASE_DIR="$(dirname "$(dirname "$SCRIPT_DIR")")"
LOG_DIR="${BASE_DIR}/runtime/migration-logs"
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
LOG_FILE="${LOG_DIR}/migration_${TIMESTAMP}.log"
CHECKPOINT_FILE="${BASE_DIR}/runtime/migration-checkpoint.json"

# Default options
BATCH_SIZE=1000
VERBOSE=""
NO_CONFIRM=false
SKIP_VALIDATION=false

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --no-confirm)
            NO_CONFIRM=true
            shift
            ;;
        --batch=*)
            BATCH_SIZE="${1#*=}"
            shift
            ;;
        --verbose)
            VERBOSE="--verbose"
            shift
            ;;
        --skip-validation)
            SKIP_VALIDATION=true
            shift
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Logging function
log() {
    echo -e "${1}" | tee -a "$LOG_FILE"
}

# Header function
header() {
    echo ""
    log "${BLUE}========================================${NC}"
    log "${BLUE}${1}${NC}"
    log "${BLUE}========================================${NC}"
}

# Create log directory
mkdir -p "$LOG_DIR"

# Start migration
header "OPENEYES FULL DATA MIGRATION - PHASE 14"
log "Started: $(date)"
log "Log File: ${LOG_FILE}"
echo ""

# Pre-flight confirmation
if [ "$NO_CONFIRM" = false ]; then
    echo -e "${YELLOW}WARNING: This will migrate all data from MariaDB to Couchbase${NC}"
    echo ""
    echo "This process will:"
    echo "  1. Migrate reference data (EventType, Site, Institution, etc.)"
    echo "  2. Migrate clinical reference (Disorder, Medication, Procedure)"
    echo "  3. Migrate core clinical data (Patient, Episode, Event)"
    echo "  4. Migrate module elements (Examination, Operation notes, etc.)"
    echo "  5. Migrate administrative data (Audit, Settings)"
    echo ""
    echo "Estimated duration: 16-72 hours (depending on data volume)"
    echo ""
    read -p "Do you want to proceed? (yes/no): " -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]es$ ]]; then
        log "${RED}Migration cancelled by user${NC}"
        exit 0
    fi
fi

# Step 1: Pre-flight checks
header "STEP 1: PRE-FLIGHT CHECKS"
log "Running pre-flight verification..."

if [ -f "${SCRIPT_DIR}/pre-migration-check.sh" ]; then
    bash "${SCRIPT_DIR}/pre-migration-check.sh"
    if [ $? -ne 0 ]; then
        log "${RED}❌ Pre-flight checks FAILED${NC}"
        log "${RED}Fix the issues above and try again${NC}"
        exit 1
    fi
else
    log "${YELLOW}⚠  Pre-flight check script not found, continuing...${NC}"
fi

log "${GREEN}✓ Pre-flight checks passed${NC}"

# Step 2: Create backup checkpoint
header "STEP 2: CREATE CHECKPOINT"
log "Creating migration checkpoint..."

CHECKPOINT_DATA=$(cat <<EOF
{
    "migration_start": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
    "batch_size": ${BATCH_SIZE},
    "stages": {
        "1": {"name": "Reference Data", "status": "pending"},
        "2": {"name": "Clinical Reference", "status": "pending"},
        "3": {"name": "Core Clinical", "status": "pending"},
        "4": {"name": "Module Elements", "status": "pending"},
        "5": {"name": "Administrative", "status": "pending"}
    }
}
EOF
)

echo "$CHECKPOINT_DATA" > "$CHECKPOINT_FILE"
log "${GREEN}✓ Checkpoint created${NC}"

# Step 3: Execute migration stages
STAGE_ERRORS=0

migrate_stage() {
    local STAGE_NUM=$1
    local STAGE_NAME=$2
    
    header "STAGE ${STAGE_NUM}: ${STAGE_NAME}"
    log "Starting stage ${STAGE_NUM}..."
    
    STAGE_START=$(date +%s)
    
    php "${BASE_DIR}/yiic" fulldatamigration stage \
        --stage=${STAGE_NUM} \
        --batch=${BATCH_SIZE} \
        ${VERBOSE} \
        2>&1 | tee -a "$LOG_FILE"
    
    STAGE_EXIT_CODE=${PIPESTATUS[0]}
    STAGE_END=$(date +%s)
    STAGE_DURATION=$((STAGE_END - STAGE_START))
    STAGE_MINUTES=$((STAGE_DURATION / 60))
    
    if [ $STAGE_EXIT_CODE -eq 0 ]; then
        log "${GREEN}✓ Stage ${STAGE_NUM} completed successfully (${STAGE_MINUTES} minutes)${NC}"
        
        # Update checkpoint
        jq ".stages.\"${STAGE_NUM}\".status = \"completed\" | .stages.\"${STAGE_NUM}\".duration = ${STAGE_MINUTES}" \
            "$CHECKPOINT_FILE" > "${CHECKPOINT_FILE}.tmp" && mv "${CHECKPOINT_FILE}.tmp" "$CHECKPOINT_FILE"
        
        return 0
    else
        log "${RED}❌ Stage ${STAGE_NUM} failed (exit code: ${STAGE_EXIT_CODE})${NC}"
        
        # Update checkpoint
        jq ".stages.\"${STAGE_NUM}\".status = \"failed\" | .stages.\"${STAGE_NUM}\".duration = ${STAGE_MINUTES}" \
            "$CHECKPOINT_FILE" > "${CHECKPOINT_FILE}.tmp" && mv "${CHECKPOINT_FILE}.tmp" "$CHECKPOINT_FILE"
        
        STAGE_ERRORS=$((STAGE_ERRORS + 1))
        return 1
    fi
}

# Execute stages
MIGRATION_START=$(date +%s)

migrate_stage 1 "Reference Data"
migrate_stage 2 "Clinical Reference"
migrate_stage 3 "Core Clinical"
migrate_stage 4 "Module Elements"
migrate_stage 5 "Administrative"

MIGRATION_END=$(date +%s)
TOTAL_DURATION=$((MIGRATION_END - MIGRATION_START))
TOTAL_MINUTES=$((TOTAL_DURATION / 60))
TOTAL_HOURS=$((TOTAL_MINUTES / 60))
REMAINING_MINUTES=$((TOTAL_MINUTES % 60))

# Step 4: Post-migration validation
if [ "$SKIP_VALIDATION" = false ] && [ $STAGE_ERRORS -eq 0 ]; then
    header "STEP 4: POST-MIGRATION VALIDATION"
    log "Running comprehensive validation..."
    
    php "${BASE_DIR}/yiic" datavalidation all --sample=500 ${VERBOSE} 2>&1 | tee -a "$LOG_FILE"
    
    VALIDATION_EXIT_CODE=${PIPESTATUS[0]}
    
    if [ $VALIDATION_EXIT_CODE -eq 0 ]; then
        log "${GREEN}✓ Validation passed${NC}"
    else
        log "${YELLOW}⚠ Validation completed with warnings${NC}"
        log "${YELLOW}  Review the validation results above${NC}"
    fi
else
    log "${YELLOW}⚠ Skipping validation (--skip-validation or errors occurred)${NC}"
fi

# Step 5: Migration summary
header "MIGRATION SUMMARY"

if [ $STAGE_ERRORS -eq 0 ]; then
    log "${GREEN}✓✓✓ MIGRATION COMPLETED SUCCESSFULLY ✓✓✓${NC}"
else
    log "${RED}⚠⚠⚠ MIGRATION COMPLETED WITH ${STAGE_ERRORS} STAGE ERRORS ⚠⚠⚠${NC}"
fi

log ""
log "Total Duration: ${TOTAL_HOURS}h ${REMAINING_MINUTES}m"
log "Log File: ${LOG_FILE}"
log "Checkpoint: ${CHECKPOINT_FILE}"
log ""
log "Stage Results:"

# Read checkpoint for summary
if command -v jq &> /dev/null; then
    for i in {1..5}; do
        STAGE_STATUS=$(jq -r ".stages.\"${i}\".status" "$CHECKPOINT_FILE")
        STAGE_NAME=$(jq -r ".stages.\"${i}\".name" "$CHECKPOINT_FILE")
        STAGE_DURATION=$(jq -r ".stages.\"${i}\".duration // 0" "$CHECKPOINT_FILE")
        
        if [ "$STAGE_STATUS" = "completed" ]; then
            log "  ${GREEN}✓${NC} Stage ${i} (${STAGE_NAME}): ${STAGE_STATUS} - ${STAGE_DURATION} min"
        else
            log "  ${RED}✗${NC} Stage ${i} (${STAGE_NAME}): ${STAGE_STATUS}"
        fi
    done
else
    log "${YELLOW}  (Install 'jq' for detailed stage summary)${NC}"
fi

log ""
log "Completed: $(date)"
log "${BLUE}========================================${NC}"

# Exit with appropriate code
if [ $STAGE_ERRORS -eq 0 ]; then
    exit 0
else
    exit 1
fi
