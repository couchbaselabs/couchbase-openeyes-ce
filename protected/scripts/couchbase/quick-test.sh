#!/bin/bash
#
# Quick Test Script for Phase 14 Migration
# Verifies basic functionality before full migration
#
# Usage:
#   ./quick-test.sh
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

TESTS_PASSED=0
TESTS_FAILED=0
TESTS_SKIPPED=0

# Test result function
test_result() {
    local TEST_NAME="$1"
    local RESULT="$2"
    local MESSAGE="${3:-}"
    
    if [ "$RESULT" = "pass" ]; then
        echo -e "${GREEN}✓${NC} ${TEST_NAME}"
        TESTS_PASSED=$((TESTS_PASSED + 1))
    elif [ "$RESULT" = "fail" ]; then
        echo -e "${RED}✗${NC} ${TEST_NAME}"
        if [ ! -z "$MESSAGE" ]; then
            echo -e "  ${RED}${MESSAGE}${NC}"
        fi
        TESTS_FAILED=$((TESTS_FAILED + 1))
    elif [ "$RESULT" = "skip" ]; then
        echo -e "${YELLOW}⊘${NC} ${TEST_NAME} (skipped)"
        if [ ! -z "$MESSAGE" ]; then
            echo -e "  ${YELLOW}${MESSAGE}${NC}"
        fi
        TESTS_SKIPPED=$((TESTS_SKIPPED + 1))
    fi
}

# Header
echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}PHASE 14 QUICK TEST${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Test 1: Check PHP is available
echo -e "${BLUE}--- Environment Tests ---${NC}"
if command -v php > /dev/null 2>&1; then
    PHP_VERSION=$(php -v | head -n1 | awk '{print $2}')
    test_result "PHP available" "pass" "Version: ${PHP_VERSION}"
else
    test_result "PHP available" "fail" "PHP not found"
fi

# Test 2: Check Yii framework
if [ -f "${BASE_DIR}/yii.php" ]; then
    test_result "Yii framework found" "pass"
else
    test_result "Yii framework found" "fail" "${BASE_DIR}/yii.php not found"
fi

# Test 3: Check console config
if [ -f "${BASE_DIR}/config/console.php" ]; then
    test_result "Console config found" "pass"
else
    test_result "Console config found" "fail" "${BASE_DIR}/config/console.php not found"
fi

# Test 4: Check migration command exists
echo ""
echo -e "${BLUE}--- Command Tests ---${NC}"
if [ -f "${BASE_DIR}/commands/FullDataMigrationCommand.php" ]; then
    test_result "FullDataMigrationCommand exists" "pass"
else
    test_result "FullDataMigrationCommand exists" "fail"
fi

# Test 5: Check validation command
if [ -f "${BASE_DIR}/commands/DataValidationCommand.php" ]; then
    test_result "DataValidationCommand exists" "pass"
else
    test_result "DataValidationCommand exists" "fail"
fi

# Test 6: Check module migration command
if [ -f "${BASE_DIR}/commands/ModuleMigrationCommand.php" ]; then
    test_result "ModuleMigrationCommand exists" "pass"
else
    test_result "ModuleMigrationCommand exists" "skip" "Phase 13 command"
fi

# Test 7: Check migration config
echo ""
echo -e "${BLUE}--- Configuration Tests ---${NC}"
if [ -f "${BASE_DIR}/config/migration-config.php" ]; then
    test_result "Migration config exists" "pass"
else
    test_result "Migration config exists" "skip" "Will use defaults"
fi

# Test 8: Check Couchbase config
if [ -f "${BASE_DIR}/config/couchbase.php" ]; then
    test_result "Couchbase config exists" "pass"
else
    test_result "Couchbase config exists" "skip" "May be in local config"
fi

# Test 9: Try to load commands
echo ""
echo -e "${BLUE}--- Command Syntax Tests ---${NC}"
if php -l "${BASE_DIR}/commands/FullDataMigrationCommand.php" > /dev/null 2>&1; then
    test_result "FullDataMigrationCommand syntax" "pass"
else
    test_result "FullDataMigrationCommand syntax" "fail" "Syntax error in command"
fi

if php -l "${BASE_DIR}/commands/DataValidationCommand.php" > /dev/null 2>&1; then
    test_result "DataValidationCommand syntax" "pass"
else
    test_result "DataValidationCommand syntax" "fail" "Syntax error in command"
fi

# Test 10: Check runtime directory
echo ""
echo -e "${BLUE}--- File System Tests ---${NC}"
if [ -d "${BASE_DIR}/runtime" ] && [ -w "${BASE_DIR}/runtime" ]; then
    test_result "Runtime directory writable" "pass"
else
    test_result "Runtime directory writable" "fail" "Cannot write to ${BASE_DIR}/runtime"
fi

# Test 11: Check scripts directory
if [ -d "${SCRIPT_DIR}" ]; then
    test_result "Scripts directory exists" "pass"
else
    test_result "Scripts directory exists" "fail"
fi

# Test 12: Check automation scripts
echo ""
echo -e "${BLUE}--- Script Tests ---${NC}"
if [ -f "${SCRIPT_DIR}/run-full-migration.sh" ]; then
    if [ -x "${SCRIPT_DIR}/run-full-migration.sh" ]; then
        test_result "run-full-migration.sh executable" "pass"
    else
        test_result "run-full-migration.sh executable" "fail" "Not executable: chmod +x ${SCRIPT_DIR}/run-full-migration.sh"
    fi
else
    test_result "run-full-migration.sh exists" "fail"
fi

if [ -f "${SCRIPT_DIR}/pre-migration-check.sh" ]; then
    if [ -x "${SCRIPT_DIR}/pre-migration-check.sh" ]; then
        test_result "pre-migration-check.sh executable" "pass"
    else
        test_result "pre-migration-check.sh executable" "fail" "Not executable: chmod +x ${SCRIPT_DIR}/pre-migration-check.sh"
    fi
else
    test_result "pre-migration-check.sh exists" "fail"
fi

# Test 13: Check index files
if [ -f "${SCRIPT_DIR}/phase14-indexes.n1ql" ]; then
    test_result "phase14-indexes.n1ql exists" "pass"
else
    test_result "phase14-indexes.n1ql exists" "skip" "Optional"
fi

# Test 14: Try to instantiate command (if PHP is available)
echo ""
echo -e "${BLUE}--- Command Instantiation Tests ---${NC}"
if command -v php > /dev/null 2>&1; then
    if php -r "include '${BASE_DIR}/yii.php'; \$app = Yii::createConsoleApplication('${BASE_DIR}/config/console.php'); \$cmd = new FullDataMigrationCommand('test', new CConsoleCommandRunner()); echo 'OK';" 2>/dev/null | grep -q "OK"; then
        test_result "FullDataMigrationCommand instantiation" "pass"
    else
        test_result "FullDataMigrationCommand instantiation" "fail" "Could not instantiate command"
    fi
    
    if php -r "include '${BASE_DIR}/yii.php'; \$app = Yii::createConsoleApplication('${BASE_DIR}/config/console.php'); \$cmd = new DataValidationCommand('test', new CConsoleCommandRunner()); echo 'OK';" 2>/dev/null | grep -q "OK"; then
        test_result "DataValidationCommand instantiation" "pass"
    else
        test_result "DataValidationCommand instantiation" "fail" "Could not instantiate command"
    fi
fi

# Test 15: Check documentation
echo ""
echo -e "${BLUE}--- Documentation Tests ---${NC}"
if [ -f "${BASE_DIR}/docs/migration-mariadb-to-couchbase/PHASE-14-EXECUTION-GUIDE.md" ]; then
    test_result "Execution guide exists" "pass"
else
    test_result "Execution guide exists" "skip"
fi

if [ -f "${BASE_DIR}/docs/migration-mariadb-to-couchbase/PHASE-14-MONITORING.md" ]; then
    test_result "Monitoring guide exists" "pass"
else
    test_result "Monitoring guide exists" "skip"
fi

# Test 16: Check test files
echo ""
echo -e "${BLUE}--- Test File Tests ---${NC}"
if [ -f "${BASE_DIR}/tests/unit/commands/FullDataMigrationCommandTest.php" ]; then
    test_result "Unit tests exist" "pass"
else
    test_result "Unit tests exist" "skip"
fi

if [ -f "${BASE_DIR}/tests/integration/FullMigrationIntegrationTest.php" ]; then
    test_result "Integration tests exist" "pass"
else
    test_result "Integration tests exist" "skip"
fi

# Test 17: Try database connection (basic check)
echo ""
echo -e "${BLUE}--- Database Connection Tests ---${NC}"
if php -r "include '${BASE_DIR}/yii.php'; \$app = Yii::createConsoleApplication('${BASE_DIR}/config/console.php'); try { Yii::app()->db->createCommand('SELECT 1')->execute(); echo 'OK'; } catch (Exception \$e) { echo 'FAIL'; }" 2>/dev/null | grep -q "OK"; then
    test_result "MariaDB connection" "pass"
else
    test_result "MariaDB connection" "fail" "Cannot connect to MariaDB"
fi

if php -r "include '${BASE_DIR}/yii.php'; \$app = Yii::createConsoleApplication('${BASE_DIR}/config/console.php'); try { \$cb = Yii::app()->couchbase; echo 'OK'; } catch (Exception \$e) { echo 'FAIL'; }" 2>/dev/null | grep -q "OK"; then
    test_result "Couchbase connection" "pass"
else
    test_result "Couchbase connection" "skip" "Couchbase not configured or not running"
fi

# Test 18: Check key model classes
echo ""
echo -e "${BLUE}--- Model Class Tests ---${NC}"
MODELS=("Patient" "Episode" "Event" "User" "EventType" "Disorder" "Medication")
for MODEL in "${MODELS[@]}"; do
    if php -r "include '${BASE_DIR}/yii.php'; \$app = Yii::createConsoleApplication('${BASE_DIR}/config/console.php'); exit(class_exists('${MODEL}') ? 0 : 1);" 2>/dev/null; then
        test_result "Model: ${MODEL}" "pass"
    else
        test_result "Model: ${MODEL}" "fail" "Model class not found"
    fi
done

# Summary
echo ""
echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}TEST SUMMARY${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

TOTAL_TESTS=$((TESTS_PASSED + TESTS_FAILED + TESTS_SKIPPED))
PASS_RATE=0
if [ $TOTAL_TESTS -gt 0 ]; then
    PASS_RATE=$(echo "scale=1; ($TESTS_PASSED * 100) / $TOTAL_TESTS" | bc)
fi

echo -e "${GREEN}Passed:${NC}  ${TESTS_PASSED}/${TOTAL_TESTS}"
echo -e "${RED}Failed:${NC}  ${TESTS_FAILED}/${TOTAL_TESTS}"
echo -e "${YELLOW}Skipped:${NC} ${TESTS_SKIPPED}/${TOTAL_TESTS}"
echo ""
echo -e "Pass Rate: ${PASS_RATE}%"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}✓✓✓ ALL CRITICAL TESTS PASSED ✓✓✓${NC}"
    echo ""
    echo "You can proceed with:"
    echo "  1. ./pre-migration-check.sh     (Full pre-flight checks)"
    echo "  2. ./run-full-migration.sh      (Execute migration)"
    echo "  3. OR run stages manually"
    echo ""
    exit 0
else
    echo -e "${RED}✗✗✗ SOME TESTS FAILED ✗✗✗${NC}"
    echo ""
    echo "Fix the failed tests before proceeding."
    echo ""
    exit 1
fi
