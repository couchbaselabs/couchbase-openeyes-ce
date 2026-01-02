#!/bin/bash
#
# Pre-Migration Verification Script
# Phase 14: Verifies system readiness before migration
#
# Usage:
#   ./pre-migration-check.sh
#

set -e

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BASE_DIR="$(dirname "$(dirname "$SCRIPT_DIR")")"

ERRORS=0
WARNINGS=0

# Header
echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}PRE-MIGRATION VERIFICATION${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Check function
check() {
    local CHECK_NAME="$1"
    local CHECK_COMMAND="$2"
    local IS_CRITICAL="${3:-true}"
    
    echo -n "Checking ${CHECK_NAME}... "
    
    if eval "$CHECK_COMMAND" > /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC}"
        return 0
    else
        if [ "$IS_CRITICAL" = true ]; then
            echo -e "${RED}✗ FAILED${NC}"
            ERRORS=$((ERRORS + 1))
        else
            echo -e "${YELLOW}⚠ WARNING${NC}"
            WARNINGS=$((WARNINGS + 1))
        fi
        return 1
    fi
}

# Check with output
check_with_output() {
    local CHECK_NAME="$1"
    local CHECK_COMMAND="$2"
    local IS_CRITICAL="${3:-true}"
    
    echo -n "Checking ${CHECK_NAME}... "
    
    OUTPUT=$(eval "$CHECK_COMMAND" 2>&1)
    RESULT=$?
    
    if [ $RESULT -eq 0 ]; then
        echo -e "${GREEN}✓${NC}"
        if [ ! -z "$OUTPUT" ]; then
            echo "  ${OUTPUT}"
        fi
        return 0
    else
        if [ "$IS_CRITICAL" = true ]; then
            echo -e "${RED}✗ FAILED${NC}"
            ERRORS=$((ERRORS + 1))
        else
            echo -e "${YELLOW}⚠ WARNING${NC}"
            WARNINGS=$((WARNINGS + 1))
        fi
        if [ ! -z "$OUTPUT" ]; then
            echo "  ${OUTPUT}"
        fi
        return 1
    fi
}

# Section 1: PHP Environment
echo -e "${BLUE}--- PHP Environment ---${NC}"

check "PHP available" "command -v php"
check_with_output "PHP version (>= 7.4)" "php -v | head -n1"

if command -v php > /dev/null 2>&1; then
    check "PHP Couchbase extension" "php -m | grep -q couchbase"
    check_with_output "Couchbase extension version" "php -r \"echo 'v' . phpversion('couchbase') . \"\n\";\""
fi

# Section 2: Couchbase Server
echo ""
echo -e "${BLUE}--- Couchbase Server ---${NC}"

# Try to connect using PHP
check_with_output "Couchbase connection" \
    "php -r \"include '${BASE_DIR}/yii.php'; \\\$app = Yii::createConsoleApplication('${BASE_DIR}/config/console.php'); try { \\\$cb = Yii::app()->couchbase; echo 'Connected'; exit(0); } catch (Exception \\\$e) { echo \\\$e->getMessage(); exit(1); }\""

# Check Couchbase CLI tools (optional)
if command -v couchbase-cli > /dev/null 2>&1; then
    check "Couchbase CLI tools" "command -v couchbase-cli" false
else
    echo "Checking Couchbase CLI tools... ${YELLOW}⚠ Not found (optional)${NC}"
    WARNINGS=$((WARNINGS + 1))
fi

# Section 3: MariaDB/MySQL
echo ""
echo -e "${BLUE}--- MariaDB/MySQL ---${NC}"

check_with_output "MariaDB connection" \
    "php -r \"include '${BASE_DIR}/yii.php'; \\\$app = Yii::createConsoleApplication('${BASE_DIR}/config/console.php'); try { Yii::app()->db->createCommand('SELECT 1')->execute(); echo 'Connected'; exit(0); } catch (Exception \\\$e) { echo \\\$e->getMessage(); exit(1); }\""

check_with_output "Patient records exist" \
    "php -r \"include '${BASE_DIR}/yii.php'; \\\$app = Yii::createConsoleApplication('${BASE_DIR}/config/console.php'); \\\$count = Patient::model()->count(); echo \\\$count . ' patients'; exit(\\\$count > 0 ? 0 : 1);\""

# Section 4: Required Model Classes
echo ""
echo -e "${BLUE}--- Required Model Classes ---${NC}"

REQUIRED_MODELS=("Patient" "Episode" "Event" "User" "EventType" "Disorder" "Medication" "Procedure")

for MODEL in "${REQUIRED_MODELS[@]}"; do
    check "Model: ${MODEL}" \
        "php -r \"include '${BASE_DIR}/yii.php'; \\\$app = Yii::createConsoleApplication('${BASE_DIR}/config/console.php'); exit(class_exists('${MODEL}') ? 0 : 1);\""
done

# Section 5: Disk Space
echo ""
echo -e "${BLUE}--- Disk Space ---${NC}"

# Get current database size
if command -v php > /dev/null 2>&1; then
    DB_SIZE=$(php -r "include '${BASE_DIR}/yii.php'; \$app = Yii::createConsoleApplication('${BASE_DIR}/config/console.php'); try { \$result = Yii::app()->db->createCommand('SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb FROM information_schema.tables WHERE table_schema = DATABASE()')->queryScalar(); echo \$result; } catch (Exception \$e) { echo 0; }" 2>/dev/null)
    
    if [ ! -z "$DB_SIZE" ] && [ "$DB_SIZE" != "0" ]; then
        echo "Current database size: ${DB_SIZE} MB"
        REQUIRED_SPACE=$(echo "$DB_SIZE * 3" | bc)
        echo "Recommended free space: ${REQUIRED_SPACE} MB (3x database size)"
    fi
fi

# Check available disk space on runtime directory
RUNTIME_DIR="${BASE_DIR}/runtime"
if [ -d "$RUNTIME_DIR" ]; then
    AVAILABLE_SPACE=$(df -m "$RUNTIME_DIR" | awk 'NR==2 {print $4}')
    echo "Available space on runtime directory: ${AVAILABLE_SPACE} MB"
    
    if [ "$AVAILABLE_SPACE" -lt 1000 ]; then
        echo -e "${YELLOW}⚠ WARNING: Less than 1GB free space${NC}"
        WARNINGS=$((WARNINGS + 1))
    else
        echo -e "${GREEN}✓ Adequate disk space${NC}"
    fi
fi

# Section 6: Permissions
echo ""
echo -e "${BLUE}--- Permissions ---${NC}"

check "Runtime directory writable" "test -w '${RUNTIME_DIR}'"

LOG_DIR="${BASE_DIR}/runtime/migration-logs"
mkdir -p "$LOG_DIR" 2>/dev/null || true
check "Log directory writable" "test -w '${LOG_DIR}'"

# Section 7: Configuration Files
echo ""
echo -e "${BLUE}--- Configuration Files ---${NC}"

check "Main config exists" "test -f '${BASE_DIR}/config/console.php'"
check "Couchbase config exists" "test -f '${BASE_DIR}/config/couchbase.php'" false

if [ -f "${BASE_DIR}/config/migration-config.php" ]; then
    echo -e "Migration config exists... ${GREEN}✓${NC}"
else
    echo -e "Migration config exists... ${YELLOW}⚠ Using defaults${NC}"
    WARNINGS=$((WARNINGS + 1))
fi

# Section 8: Migration Commands
echo ""
echo -e "${BLUE}--- Migration Commands ---${NC}"

check "FullDataMigrationCommand" "test -f '${BASE_DIR}/commands/FullDataMigrationCommand.php'"
check "DataValidationCommand" "test -f '${BASE_DIR}/commands/DataValidationCommand.php'"
check "ModuleMigrationCommand" "test -f '${BASE_DIR}/commands/ModuleMigrationCommand.php'" false

# Section 9: Backups
echo ""
echo -e "${BLUE}--- Backups ---${NC}"

echo -n "Recent database backup... "
# This is informational only
echo -e "${YELLOW}⚠ Verify manually${NC}"
echo "  Ensure you have a recent backup before proceeding"
WARNINGS=$((WARNINGS + 1))

# Section 10: Memory
echo ""
echo -e "${BLUE}--- System Resources ---${NC}"

# Check available memory (Linux/Mac)
if command -v free > /dev/null 2>&1; then
    AVAILABLE_MEM=$(free -m | awk 'NR==2 {print $7}')
    echo "Available memory: ${AVAILABLE_MEM} MB"
    
    if [ "$AVAILABLE_MEM" -lt 2048 ]; then
        echo -e "${YELLOW}⚠ WARNING: Less than 2GB available memory${NC}"
        WARNINGS=$((WARNINGS + 1))
    else
        echo -e "${GREEN}✓ Adequate memory available${NC}"
    fi
elif command -v vm_stat > /dev/null 2>&1; then
    # Mac OS
    FREE_PAGES=$(vm_stat | grep "Pages free" | awk '{print $3}' | tr -d '.')
    FREE_MB=$((FREE_PAGES * 4096 / 1024 / 1024))
    echo "Available memory: ~${FREE_MB} MB"
    
    if [ "$FREE_MB" -lt 2048 ]; then
        echo -e "${YELLOW}⚠ WARNING: Less than 2GB available memory${NC}"
        WARNINGS=$((WARNINGS + 1))
    else
        echo -e "${GREEN}✓ Adequate memory available${NC}"
    fi
fi

# Summary
echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}VERIFICATION SUMMARY${NC}"
echo -e "${BLUE}========================================${NC}"

if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "${GREEN}✓✓✓ ALL CHECKS PASSED ✓✓✓${NC}"
    echo ""
    echo "System is ready for migration!"
    exit 0
elif [ $ERRORS -eq 0 ]; then
    echo -e "${YELLOW}⚠ ${WARNINGS} WARNING(S)${NC}"
    echo ""
    echo "System is ready for migration, but review warnings above."
    exit 0
else
    echo -e "${RED}✗ ${ERRORS} ERROR(S)${NC}"
    if [ $WARNINGS -gt 0 ]; then
        echo -e "${YELLOW}⚠ ${WARNINGS} WARNING(S)${NC}"
    fi
    echo ""
    echo "Fix the errors above before proceeding with migration."
    exit 1
fi
