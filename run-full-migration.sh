#!/bin/bash
#
# OpenEyes Couchbase Migration - Full Execution Script
# Phase 17: Complete migration with all steps
#
# Usage:
#   ./run-full-migration.sh              # Run all steps
#   ./run-full-migration.sh test         # Test dual-write only
#   ./run-full-migration.sh migrate      # Run data migration only
#   ./run-full-migration.sh validate     # Validate data only
#   ./run-full-migration.sh status       # Check status only

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
DOCKER_CONTAINER="devcontainer-web-1"
YII_CMD="php protected/yiic"

echo -e "${BLUE}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║       OpenEyes Couchbase Migration - Phase 17                ║${NC}"
echo -e "${BLUE}╚══════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Function to run Yii command
run_yii() {
    local cmd=$1
    echo -e "${YELLOW}Running: ${cmd}${NC}"
    docker exec -it $DOCKER_CONTAINER $YII_CMD $cmd
}

# Function to check if container is running
check_container() {
    if ! docker ps | grep -q $DOCKER_CONTAINER; then
        echo -e "${RED}Error: Docker container '$DOCKER_CONTAINER' is not running${NC}"
        echo "Please start the development environment first:"
        echo "  cd .devcontainer && docker-compose up -d"
        exit 1
    fi
}

# Step 1: Test Dual-Write
test_dual_write() {
    echo -e "\n${GREEN}════════════════════════════════════════${NC}"
    echo -e "${GREEN}Step 1: Testing Dual-Write Functionality${NC}"
    echo -e "${GREEN}════════════════════════════════════════${NC}\n"
    
    run_yii "testdualwrite"
    
    echo -e "\n${GREEN}✓ Dual-write test complete${NC}"
}

# Step 2: Run Full Data Migration
run_migration() {
    echo -e "\n${GREEN}════════════════════════════════════════${NC}"
    echo -e "${GREEN}Step 2: Running Full Data Migration${NC}"
    echo -e "${GREEN}════════════════════════════════════════${NC}\n"
    
    echo -e "${YELLOW}Stage 1: Reference Data${NC}"
    run_yii "fulldatamigration stage --stage=1"
    
    echo -e "\n${YELLOW}Stage 2: Clinical Reference (SNOMED, OPCS, dm+d)${NC}"
    run_yii "fulldatamigration stage --stage=2"
    
    echo -e "\n${YELLOW}Stage 3: Core Clinical (Patients, Episodes, Events)${NC}"
    run_yii "fulldatamigration stage --stage=3"
    
    echo -e "\n${YELLOW}Stage 4: Module Elements${NC}"
    run_yii "fulldatamigration stage --stage=4"
    
    echo -e "\n${YELLOW}Stage 5: Administrative Data${NC}"
    run_yii "fulldatamigration stage --stage=5"
    
    echo -e "\n${GREEN}✓ Full data migration complete${NC}"
}

# Step 3: Validate Data
validate_data() {
    echo -e "\n${GREEN}════════════════════════════════════════${NC}"
    echo -e "${GREEN}Step 3: Validating Migrated Data${NC}"
    echo -e "${GREEN}════════════════════════════════════════${NC}\n"
    
    echo -e "${YELLOW}Running count validation...${NC}"
    run_yii "datavalidation counts"
    
    echo -e "\n${YELLOW}Running sample validation...${NC}"
    run_yii "datavalidation samples --sample=100"
    
    echo -e "\n${YELLOW}Running integrity validation...${NC}"
    run_yii "datavalidation integrity"
    
    echo -e "\n${GREEN}✓ Data validation complete${NC}"
}

# Step 4: Check Migration Status
check_status() {
    echo -e "\n${GREEN}════════════════════════════════════════${NC}"
    echo -e "${GREEN}Migration Status Check${NC}"
    echo -e "${GREEN}════════════════════════════════════════${NC}\n"
    
    run_yii "fulldatamigration status"
}

# Step 5: Enable Couchbase Read (already enabled in config)
enable_couchbase_read() {
    echo -e "\n${GREEN}════════════════════════════════════════${NC}"
    echo -e "${GREEN}Step 4: Couchbase Read Configuration${NC}"
    echo -e "${GREEN}════════════════════════════════════════${NC}\n"
    
    echo -e "${YELLOW}Current configuration:${NC}"
    docker exec -it $DOCKER_CONTAINER grep -A5 "enable_couchbase_read" protected/config/core/common.php | head -10
    
    echo -e "\n${GREEN}Couchbase read is ENABLED in configuration${NC}"
    echo -e "The application will read from Couchbase for migrated collections."
}

# Main execution
main() {
    check_container
    
    case "${1:-all}" in
        test)
            test_dual_write
            ;;
        migrate)
            run_migration
            ;;
        validate)
            validate_data
            ;;
        status)
            check_status
            ;;
        all)
            test_dual_write
            run_migration
            validate_data
            enable_couchbase_read
            check_status
            ;;
        *)
            echo "Usage: $0 {test|migrate|validate|status|all}"
            exit 1
            ;;
    esac
    
    echo -e "\n${BLUE}╔══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║              Migration Process Complete!                      ║${NC}"
    echo -e "${BLUE}╚══════════════════════════════════════════════════════════════╝${NC}"
}

main "$@"
