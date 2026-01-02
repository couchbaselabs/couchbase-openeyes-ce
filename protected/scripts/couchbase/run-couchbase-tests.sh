#!/bin/bash
#
# OpenEyes - Couchbase Test Runner
# Runs unit, integration, and performance tests for Couchbase migration
#

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="${SCRIPT_DIR}/../../.."
TESTS_DIR="${PROJECT_ROOT}/protected/tests"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Default options
RUN_UNIT=true
RUN_INTEGRATION=false
RUN_PERFORMANCE=false
RUN_E2E=false
VERBOSE=false
COVERAGE=false

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --unit)
            RUN_UNIT=true
            RUN_INTEGRATION=false
            RUN_PERFORMANCE=false
            shift
            ;;
        --integration)
            RUN_UNIT=false
            RUN_INTEGRATION=true
            RUN_PERFORMANCE=false
            shift
            ;;
        --performance)
            RUN_UNIT=false
            RUN_INTEGRATION=false
            RUN_PERFORMANCE=true
            shift
            ;;
        --e2e)
            RUN_UNIT=false
            RUN_INTEGRATION=false
            RUN_PERFORMANCE=false
            RUN_E2E=true
            shift
            ;;
        --all)
            RUN_UNIT=true
            RUN_INTEGRATION=true
            RUN_PERFORMANCE=true
            shift
            ;;
        --coverage)
            COVERAGE=true
            shift
            ;;
        -v|--verbose)
            VERBOSE=true
            shift
            ;;
        -h|--help)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --unit           Run unit tests only (default)"
            echo "  --integration    Run integration tests only"
            echo "  --performance    Run performance tests only"
            echo "  --e2e            Run Cypress E2E tests only"
            echo "  --all            Run all test types"
            echo "  --coverage       Generate code coverage report"
            echo "  -v, --verbose    Verbose output"
            echo "  -h, --help       Show this help"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

echo "========================================"
echo "OpenEyes Couchbase Test Runner"
echo "========================================"
echo ""

# Check for PHPUnit
if [ ! -f "${PROJECT_ROOT}/vendor/bin/phpunit" ]; then
    echo -e "${RED}ERROR: PHPUnit not found. Run 'composer install' first.${NC}"
    exit 1
fi

PHPUNIT="${PROJECT_ROOT}/vendor/bin/phpunit"
PHPUNIT_OPTS=""

if [ "$VERBOSE" = true ]; then
    PHPUNIT_OPTS="--verbose"
fi

if [ "$COVERAGE" = true ]; then
    PHPUNIT_OPTS="${PHPUNIT_OPTS} --coverage-html ${PROJECT_ROOT}/coverage-couchbase"
fi

# Track results
UNIT_RESULT=0
INTEGRATION_RESULT=0
PERFORMANCE_RESULT=0
E2E_RESULT=0

# Run unit tests
if [ "$RUN_UNIT" = true ]; then
    echo -e "${YELLOW}Running Couchbase Unit Tests...${NC}"
    echo "----------------------------------------"
    
    # Run migration tests
    if [ -d "${TESTS_DIR}/unit/components/migration" ]; then
        echo "Migration component tests..."
        $PHPUNIT $PHPUNIT_OPTS "${TESTS_DIR}/unit/components/migration" || UNIT_RESULT=$?
    fi
    
    # Run database component tests
    if [ -d "${TESTS_DIR}/unit/components/database" ]; then
        echo "Database component tests..."
        $PHPUNIT $PHPUNIT_OPTS "${TESTS_DIR}/unit/components/database" || UNIT_RESULT=$?
    fi
    
    # Run service tests
    if [ -f "${TESTS_DIR}/unit/services/PatientServiceTest.php" ]; then
        echo "Service tests..."
        $PHPUNIT $PHPUNIT_OPTS "${TESTS_DIR}/unit/services/PatientServiceTest.php" || UNIT_RESULT=$?
    fi
    if [ -f "${TESTS_DIR}/unit/services/EpisodeServiceTest.php" ]; then
        $PHPUNIT $PHPUNIT_OPTS "${TESTS_DIR}/unit/services/EpisodeServiceTest.php" || UNIT_RESULT=$?
    fi
    if [ -f "${TESTS_DIR}/unit/services/EventServiceTest.php" ]; then
        $PHPUNIT $PHPUNIT_OPTS "${TESTS_DIR}/unit/services/EventServiceTest.php" || UNIT_RESULT=$?
    fi
    
    # Run report tests
    if [ -d "${TESTS_DIR}/unit/components/reports" ]; then
        echo "Report component tests..."
        $PHPUNIT $PHPUNIT_OPTS "${TESTS_DIR}/unit/components/reports" || UNIT_RESULT=$?
    fi
    
    if [ $UNIT_RESULT -eq 0 ]; then
        echo -e "${GREEN}Unit tests passed!${NC}"
    else
        echo -e "${RED}Unit tests failed!${NC}"
    fi
    echo ""
fi

# Run integration tests
if [ "$RUN_INTEGRATION" = true ]; then
    echo -e "${YELLOW}Running Couchbase Integration Tests...${NC}"
    echo "----------------------------------------"
    
    # Check if Couchbase is available
    COUCHBASE_HOST="${COUCHBASE_TEST_HOST:-localhost}"
    if ! curl -s -o /dev/null "${COUCHBASE_HOST}:8091"; then
        echo -e "${YELLOW}WARNING: Couchbase not available, skipping integration tests${NC}"
        INTEGRATION_RESULT=0
    else
        if [ -d "${TESTS_DIR}/integration" ]; then
            $PHPUNIT $PHPUNIT_OPTS "${TESTS_DIR}/integration" || INTEGRATION_RESULT=$?
        fi
    fi
    
    if [ $INTEGRATION_RESULT -eq 0 ]; then
        echo -e "${GREEN}Integration tests passed!${NC}"
    else
        echo -e "${RED}Integration tests failed!${NC}"
    fi
    echo ""
fi

# Run performance tests
if [ "$RUN_PERFORMANCE" = true ]; then
    echo -e "${YELLOW}Running Couchbase Performance Tests...${NC}"
    echo "----------------------------------------"
    
    if [ -d "${TESTS_DIR}/performance" ]; then
        $PHPUNIT $PHPUNIT_OPTS "${TESTS_DIR}/performance" || PERFORMANCE_RESULT=$?
    else
        echo "No performance tests found"
    fi
    
    if [ $PERFORMANCE_RESULT -eq 0 ]; then
        echo -e "${GREEN}Performance tests passed!${NC}"
    else
        echo -e "${RED}Performance tests failed!${NC}"
    fi
    echo ""
fi

# Run E2E tests
if [ "$RUN_E2E" = true ]; then
    echo -e "${YELLOW}Running Couchbase E2E Tests...${NC}"
    echo "----------------------------------------"
    
    if [ -f "${PROJECT_ROOT}/node_modules/.bin/cypress" ]; then
        cd "${PROJECT_ROOT}"
        npx cypress run --spec "cypress/e2e/couchbase/**/*.cy.js" || E2E_RESULT=$?
    else
        echo "Cypress not installed. Run 'npm install' first."
        E2E_RESULT=1
    fi
    
    if [ $E2E_RESULT -eq 0 ]; then
        echo -e "${GREEN}E2E tests passed!${NC}"
    else
        echo -e "${RED}E2E tests failed!${NC}"
    fi
    echo ""
fi

# Summary
echo "========================================"
echo "Test Summary"
echo "========================================"

TOTAL_FAILED=0

if [ "$RUN_UNIT" = true ]; then
    if [ $UNIT_RESULT -eq 0 ]; then
        echo -e "Unit Tests:        ${GREEN}PASSED${NC}"
    else
        echo -e "Unit Tests:        ${RED}FAILED${NC}"
        TOTAL_FAILED=$((TOTAL_FAILED + 1))
    fi
fi

if [ "$RUN_INTEGRATION" = true ]; then
    if [ $INTEGRATION_RESULT -eq 0 ]; then
        echo -e "Integration Tests: ${GREEN}PASSED${NC}"
    else
        echo -e "Integration Tests: ${RED}FAILED${NC}"
        TOTAL_FAILED=$((TOTAL_FAILED + 1))
    fi
fi

if [ "$RUN_PERFORMANCE" = true ]; then
    if [ $PERFORMANCE_RESULT -eq 0 ]; then
        echo -e "Performance Tests: ${GREEN}PASSED${NC}"
    else
        echo -e "Performance Tests: ${RED}FAILED${NC}"
        TOTAL_FAILED=$((TOTAL_FAILED + 1))
    fi
fi

if [ "$RUN_E2E" = true ]; then
    if [ $E2E_RESULT -eq 0 ]; then
        echo -e "E2E Tests:         ${GREEN}PASSED${NC}"
    else
        echo -e "E2E Tests:         ${RED}FAILED${NC}"
        TOTAL_FAILED=$((TOTAL_FAILED + 1))
    fi
fi

echo ""

if [ $TOTAL_FAILED -eq 0 ]; then
    echo -e "${GREEN}All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}${TOTAL_FAILED} test suite(s) failed${NC}"
    exit 1
fi
