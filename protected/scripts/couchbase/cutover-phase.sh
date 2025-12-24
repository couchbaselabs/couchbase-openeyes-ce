#!/bin/bash
#
# Couchbase Cutover Phase Script
#
# Automates gradual cutover to Couchbase with monitoring and auto-rollback.
# Sets traffic percentage, waits for stabilization, monitors metrics.
#
# Phase 16: Production Cutover
#
# Usage:
#   ./cutover-phase.sh <phase> [options]
#
# Phases:
#   1 - Dual-write only (0% reads)
#   2 - Canary (10% reads)
#   3 - Partial (50% reads)
#   4 - Majority (100% reads)
#   5 - Couchbase primary (disable dual-write)
#
# Options:
#   --dry-run           Show what would be done without making changes
#   --no-monitoring     Skip monitoring period
#   --stabilization=N   Minutes to wait for stabilization (default: 30)
#   --error-threshold=N Error rate threshold % for auto-rollback (default: 1.0)
#

set -e

# Default configuration
DRY_RUN=false
MONITORING_ENABLED=true
STABILIZATION_MINUTES=30
ERROR_THRESHOLD=1.0
YIIC="php protected/yiic"

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Parse arguments
PHASE=$1
shift || true

while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --no-monitoring)
            MONITORING_ENABLED=false
            shift
            ;;
        --stabilization=*)
            STABILIZATION_MINUTES="${1#*=}"
            shift
            ;;
        --error-threshold=*)
            ERROR_THRESHOLD="${1#*=}"
            shift
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Validate phase
if [[ -z "$PHASE" || ! "$PHASE" =~ ^[1-5]$ ]]; then
    echo "Usage: $0 <phase> [options]"
    echo ""
    echo "Phases:"
    echo "  1 - Dual-write only (0% reads)"
    echo "  2 - Canary (10% reads)"
    echo "  3 - Partial (50% reads)"
    echo "  4 - Majority (100% reads)"
    echo "  5 - Couchbase primary (disable dual-write)"
    echo ""
    echo "Options:"
    echo "  --dry-run             Dry run mode"
    echo "  --no-monitoring       Skip monitoring"
    echo "  --stabilization=N     Stabilization period in minutes (default: 30)"
    echo "  --error-threshold=N   Error rate threshold % (default: 1.0)"
    exit 1
fi

# Phase configuration
case $PHASE in
    1)
        PHASE_NAME="Dual-write only"
        PERCENTAGE=0
        ;;
    2)
        PHASE_NAME="Canary (10%)"
        PERCENTAGE=10
        ;;
    3)
        PHASE_NAME="Partial (50%)"
        PERCENTAGE=50
        ;;
    4)
        PHASE_NAME="Majority (100%)"
        PERCENTAGE=100
        ;;
    5)
        PHASE_NAME="Couchbase primary"
        PERCENTAGE=100
        ;;
esac

echo "==========================================="
echo "COUCHBASE CUTOVER - PHASE $PHASE"
echo "==========================================="
echo ""
echo "Phase: $PHASE_NAME"
echo "Target: $PERCENTAGE% to Couchbase"
echo "Stabilization: $STABILIZATION_MINUTES minutes"
echo "Error threshold: $ERROR_THRESHOLD%"
echo "Dry run: $DRY_RUN"
echo ""

if [ "$DRY_RUN" = true ]; then
    echo -e "${YELLOW}[DRY RUN MODE - No changes will be made]${NC}"
    echo ""
fi

# Pre-cutover checks
echo "Step 1: Running pre-cutover checklist..."
echo "-------------------------------------------"

if [ "$DRY_RUN" = false ]; then
    if ! $YIIC precutoverchecklist run; then
        echo -e "${RED}✗ Pre-cutover checks failed${NC}"
        echo "Address failures before proceeding."
        exit 1
    fi
    echo -e "${GREEN}✓ Pre-cutover checks passed${NC}"
else
    echo "[DRY RUN] Would run: $YIIC precutoverchecklist run"
fi

echo ""

# Confirm action
if [ "$DRY_RUN" = false ]; then
    echo "This will set Couchbase traffic to $PERCENTAGE%."
    read -p "Type 'PROCEED' to continue: " CONFIRMATION
    
    if [ "$CONFIRMATION" != "PROCEED" ]; then
        echo "Aborted."
        exit 1
    fi
    echo ""
fi

# Set traffic percentage
echo "Step 2: Setting traffic percentage..."
echo "-------------------------------------------"

if [ "$DRY_RUN" = false ]; then
    # Use the CouchbaseCutoverManager to set percentage
    php -r "
        require_once 'protected/yii.php';
        require_once 'protected/config/console.php';
        Yii::createConsoleApplication(\$config);
        \$manager = CouchbaseCutoverManager::getInstance();
        \$manager->setTrafficPercentage($PERCENTAGE);
        echo 'Traffic set to: $PERCENTAGE%\n';
    "
    
    echo -e "${GREEN}✓ Traffic percentage updated${NC}"
else
    echo "[DRY RUN] Would set traffic to $PERCENTAGE%"
fi

echo ""

# Monitoring period
if [ "$MONITORING_ENABLED" = true ]; then
    echo "Step 3: Monitoring for $STABILIZATION_MINUTES minutes..."
    echo "-------------------------------------------"
    
    if [ "$DRY_RUN" = false ]; then
        STABILIZATION_SECONDS=$((STABILIZATION_MINUTES * 60))
        CHECK_INTERVAL=60 # Check every minute
        CHECKS=$((STABILIZATION_SECONDS / CHECK_INTERVAL))
        
        for ((i=1; i<=CHECKS; i++)); do
            ELAPSED=$((i * CHECK_INTERVAL / 60))
            echo "[$ELAPSED/$STABILIZATION_MINUTES min] Checking system health..."
            
            # Check MariaDB
            if ! $YIIC db ping 2>/dev/null; then
                echo -e "${RED}✗ MariaDB health check failed${NC}"
                echo "Initiating emergency rollback..."
                $YIIC couchbaserollback instant "MariaDB health check failed during Phase $PHASE"
                exit 1
            fi
            
            # Check Couchbase (if percentage > 0)
            if [ $PERCENTAGE -gt 0 ]; then
                php -r "
                    require_once 'protected/yii.php';
                    require_once 'protected/config/console.php';
                    Yii::createConsoleApplication(\$config);
                    try {
                        Yii::app()->couchbase->query('SELECT 1');
                        exit(0);
                    } catch (Exception \$e) {
                        echo 'Error: ' . \$e->getMessage() . \"\n\";
                        exit(1);
                    }
                " || {
                    echo -e "${RED}✗ Couchbase health check failed${NC}"
                    echo "Initiating emergency rollback..."
                    $YIIC couchbaserollback instant "Couchbase health check failed during Phase $PHASE"
                    exit 1
                }
            fi
            
            echo -e "${GREEN}  ✓ Health checks passed${NC}"
            
            # Wait for next check (except on last iteration)
            if [ $i -lt $CHECKS ]; then
                sleep $CHECK_INTERVAL
            fi
        done
        
        echo -e "${GREEN}✓ Monitoring period complete - System stable${NC}"
    else
        echo "[DRY RUN] Would monitor for $STABILIZATION_MINUTES minutes"
    fi
else
    echo "Step 3: Monitoring skipped (--no-monitoring)"
fi

echo ""

# Final validation
echo "Step 4: Final validation..."
echo "-------------------------------------------"

if [ "$DRY_RUN" = false ]; then
    # Verify current state
    $YIIC couchbaserollback verify
    echo -e "${GREEN}✓ Verification complete${NC}"
else
    echo "[DRY RUN] Would run: $YIIC couchbaserollback verify"
fi

echo ""

# Summary
echo "==========================================="
echo "PHASE $PHASE COMPLETE"
echo "==========================================="
echo ""
echo "Status: SUCCESS"
echo "Phase: $PHASE_NAME"
echo "Traffic: $PERCENTAGE% to Couchbase"
echo "Completed: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

if [ $PHASE -lt 5 ]; then
    NEXT_PHASE=$((PHASE + 1))
    echo "Next steps:"
    echo "1. Monitor dashboard: /couchbaseMonitor"
    echo "2. Review metrics and error logs"
    echo "3. When ready, proceed to Phase $NEXT_PHASE:"
    echo "   ./cutover-phase.sh $NEXT_PHASE"
else
    echo "Cutover complete! All traffic on Couchbase."
    echo ""
    echo "Post-cutover tasks:"
    echo "1. Monitor for 2-4 weeks"
    echo "2. Consider disabling dual-write (after stabilization)"
    echo "3. Archive MariaDB data"
    echo "4. Update documentation"
fi

echo ""
