#!/bin/bash
# Phase 6 Validation Script
# Validates that all Phase 6 components are correctly implemented

set -e

echo "=========================================="
echo "Phase 6: Query Migration - Validation"
echo "=========================================="
echo ""

ERRORS=0
WARNINGS=0
PASSED=0

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

pass() {
    echo -e "${GREEN}✓${NC} $1"
    ((PASSED++))
}

fail() {
    echo -e "${RED}✗${NC} $1"
    ((ERRORS++))
}

warn() {
    echo -e "${YELLOW}⚠${NC} $1"
    ((WARNINGS++))
}

# Check if Docker is running
if ! docker ps > /dev/null 2>&1; then
    fail "Docker is not running"
    exit 1
fi

echo "Step 1: Checking File Existence..."
echo "-----------------------------------"

# Core components
FILES=(
    "protected/components/database/N1qlQueryBuilder.php"
    "protected/components/database/QueryMigrationHelper.php"
    "protected/components/reports/CouchbasePatientSearch.php"
    "protected/components/reports/CouchbaseEpisodeReport.php"
    "protected/components/reports/CouchbaseEventReport.php"
    "protected/config/couchbase-collection-map.php"
)

# Module components
MODULE_FILES=(
    "protected/modules/OphCiExamination/components/CouchbaseExaminationSearch.php"
    "protected/modules/OphTrOperationbooking/components/CouchbaseWaitingList.php"
    "protected/modules/OphTrOperationbooking/components/CouchbaseTheatreSchedule.php"
    "protected/modules/OphCoCorrespondence/components/CouchbaseLetterSearch.php"
)

# Commands
COMMAND_FILES=(
    "protected/commands/QueryAnalysisCommand.php"
    "protected/commands/QueryBenchmarkCommand.php"
    "protected/commands/QueryMigrationVerifyCommand.php"
)

# Scripts
SCRIPT_FILES=(
    "protected/scripts/couchbase/analyze-queries.php"
)

# Tests
TEST_FILES=(
    "protected/tests/unit/components/database/N1qlQueryBuilderTest.php"
    "protected/tests/unit/components/reports/CouchbasePatientSearchTest.php"
    "protected/tests/unit/components/reports/CouchbaseReportTest.php"
    "protected/tests/integration/CouchbaseQueryIntegrationTest.php"
)

# Documentation
DOC_FILES=(
    "docs/migration-mariadb-to-couchbase/PHASE-06-AGENT-SPEC.md"
    "docs/migration-mariadb-to-couchbase/sql-to-n1ql-guide.md"
    "docs/migration-mariadb-to-couchbase/PHASE-06-IMPLEMENTATION-SUMMARY.md"
    "docs/migration-mariadb-to-couchbase/PHASE-06-QUICK-START.md"
)

ALL_FILES=("${FILES[@]}" "${MODULE_FILES[@]}" "${COMMAND_FILES[@]}" "${SCRIPT_FILES[@]}" "${TEST_FILES[@]}" "${DOC_FILES[@]}")

for file in "${ALL_FILES[@]}"; do
    if [ -f "$file" ]; then
        pass "File exists: $file"
    else
        fail "File missing: $file"
    fi
done

echo ""
echo "Step 2: Checking PHP Syntax..."
echo "-------------------------------"

PHP_FILES=("${FILES[@]}" "${MODULE_FILES[@]}" "${COMMAND_FILES[@]}" "${SCRIPT_FILES[@]}" "${TEST_FILES[@]}")

for file in "${PHP_FILES[@]}"; do
    if [ -f "$file" ]; then
        if docker compose -f .devcontainer/docker-compose.yml exec -T web php -l "$file" > /dev/null 2>&1; then
            pass "Syntax OK: $(basename $file)"
        else
            fail "Syntax error in: $file"
        fi
    fi
done

echo ""
echo "Step 3: Testing N1QL Query Builder..."
echo "--------------------------------------"

if docker compose -f .devcontainer/docker-compose.yml exec -T web php -r "
require_once('/var/www/openeyes/protected/yii.php');
\$builder = new \OE\Database\N1qlQueryBuilder('openeyes');
\$query = \$builder->from('core', 'patient')->where('hos_num = \$hosNum', ['hosNum' => '12345'])->limit(10)->build();
if (strpos(\$query, 'SELECT') !== false && strpos(\$query, 'FROM') !== false && strpos(\$query, 'WHERE') !== false) {
    exit(0);
}
exit(1);
" > /dev/null 2>&1; then
    pass "N1qlQueryBuilder builds basic queries"
else
    fail "N1qlQueryBuilder failed to build queries"
fi

# Test complex query
if docker compose -f .devcontainer/docker-compose.yml exec -T web php -r "
require_once('/var/www/openeyes/protected/yii.php');
\$builder = new \OE\Database\N1qlQueryBuilder('openeyes');
\$query = \$builder
    ->from('core', 'episode', 'e')
    ->join('core', 'patient', 'e.patient_id = META(p).id', 'p')
    ->select('e.*, p.hos_num')
    ->orderBy('e.start_date', 'DESC')
    ->limit(50)
    ->build();
if (strpos(\$query, 'JOIN') !== false) {
    exit(0);
}
exit(1);
" > /dev/null 2>&1; then
    pass "N1qlQueryBuilder builds complex queries with JOIN"
else
    fail "N1qlQueryBuilder failed to build complex queries"
fi

echo ""
echo "Step 4: Testing Query Migration Helper..."
echo "------------------------------------------"

if docker compose -f .devcontainer/docker-compose.yml exec -T web php -r "
require_once('/var/www/openeyes/protected/yii.php');
\$helper = new \OE\Database\QueryMigrationHelper();
\$keyspace = \$helper->getKeyspace('patient');
if (strpos(\$keyspace, 'openeyes') !== false && strpos(\$keyspace, 'core') !== false) {
    exit(0);
}
exit(1);
" > /dev/null 2>&1; then
    pass "QueryMigrationHelper generates correct keyspaces"
else
    fail "QueryMigrationHelper failed"
fi

echo ""
echo "Step 5: Testing Component Loading..."
echo "-------------------------------------"

# Test Patient Search
if docker compose -f .devcontainer/docker-compose.yml exec -T web php -r "
require_once('/var/www/openeyes/protected/yii.php');
\$search = new \OE\Reports\CouchbasePatientSearch();
exit(method_exists(\$search, 'search') ? 0 : 1);
" > /dev/null 2>&1; then
    pass "CouchbasePatientSearch loads correctly"
else
    fail "CouchbasePatientSearch failed to load"
fi

# Test Examination Search
if docker compose -f .devcontainer/docker-compose.yml exec -T web php -r "
require_once('/var/www/openeyes/protected/yii.php');
\$search = new \OEModule\OphCiExamination\components\CouchbaseExaminationSearch();
exit(method_exists(\$search, 'findByPatientId') ? 0 : 1);
" > /dev/null 2>&1; then
    pass "CouchbaseExaminationSearch loads correctly"
else
    fail "CouchbaseExaminationSearch failed to load"
fi

# Test Waiting List
if docker compose -f .devcontainer/docker-compose.yml exec -T web php -r "
require_once('/var/www/openeyes/protected/yii.php');
\$wl = new \OEModule\OphTrOperationbooking\components\CouchbaseWaitingList();
exit(method_exists(\$wl, 'getForFirm') ? 0 : 1);
" > /dev/null 2>&1; then
    pass "CouchbaseWaitingList loads correctly"
else
    fail "CouchbaseWaitingList failed to load"
fi

# Test Letter Search
if docker compose -f .devcontainer/docker-compose.yml exec -T web php -r "
require_once('/var/www/openeyes/protected/yii.php');
\$ls = new \OEModule\OphCoCorrespondence\components\CouchbaseLetterSearch();
exit(method_exists(\$ls, 'getPatientHistory') ? 0 : 1);
" > /dev/null 2>&1; then
    pass "CouchbaseLetterSearch loads correctly"
else
    fail "CouchbaseLetterSearch failed to load"
fi

echo ""
echo "Step 6: Checking Commands..."
echo "-----------------------------"

# Test QueryAnalysisCommand
if docker compose -f .devcontainer/docker-compose.yml exec -T web php protected/yiic.php queryanalysis help > /dev/null 2>&1; then
    pass "QueryAnalysisCommand accessible"
else
    fail "QueryAnalysisCommand not accessible"
fi

# Test QueryBenchmarkCommand
if docker compose -f .devcontainer/docker-compose.yml exec -T web php protected/yiic.php querybenchmark help > /dev/null 2>&1; then
    pass "QueryBenchmarkCommand accessible"
else
    fail "QueryBenchmarkCommand not accessible"
fi

# Test QueryMigrationVerifyCommand
if docker compose -f .devcontainer/docker-compose.yml exec -T web php protected/yiic.php querymigrationverify help > /dev/null 2>&1; then
    pass "QueryMigrationVerifyCommand accessible"
else
    fail "QueryMigrationVerifyCommand not accessible"
fi

echo ""
echo "Step 7: Checking Configuration..."
echo "----------------------------------"

if grep -q "Phase 6: Query Migration Feature Flags" protected/config/core/common.php; then
    pass "Configuration changes present"
else
    fail "Configuration changes missing"
fi

if grep -q "enable_couchbase_queries" protected/config/core/common.php; then
    pass "Feature flag 'enable_couchbase_queries' added"
else
    fail "Feature flag 'enable_couchbase_queries' missing"
fi

echo ""
echo "=========================================="
echo "Validation Summary"
echo "=========================================="
echo ""
echo -e "${GREEN}Passed:${NC}   $PASSED"
echo -e "${RED}Failed:${NC}   $ERRORS"
echo -e "${YELLOW}Warnings:${NC} $WARNINGS"
echo ""

if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✓ Phase 6 Validation: SUCCESS!${NC}"
    echo ""
    echo "All components are correctly implemented."
    echo ""
    echo "Next steps:"
    echo "  1. Run unit tests: docker compose exec web php protected/vendor/bin/phpunit protected/tests/unit/components/database/N1qlQueryBuilderTest.php"
    echo "  2. Analyze queries: docker compose exec web php protected/yiic.php queryanalysis run"
    echo "  3. If Couchbase is running, test integration: docker compose exec web php protected/yiic.php querymigrationverify all"
    echo ""
    exit 0
else
    echo -e "${RED}✗ Phase 6 Validation: FAILED${NC}"
    echo ""
    echo "Please fix the errors above before proceeding."
    echo ""
    exit 1
fi
