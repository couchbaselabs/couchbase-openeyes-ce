#!/bin/bash
#
# Execute Phase 14 Migration in Docker Dev Environment
# This script runs the migration inside your Docker container
#

set -e

echo "=========================================="
echo "Phase 14 Migration Execution"
echo "=========================================="
echo ""

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${BLUE}Environment: Development (Docker)${NC}"
echo -e "${GREEN}Containers running:${NC}"
docker ps --format "table {{.Names}}\t{{.Status}}"
echo ""

echo -e "${YELLOW}Choose execution option:${NC}"
echo "1. Test Stage 1 only (quick test - 5 min)"
echo "2. Run all stages one-by-one (recommended - 4-14 hours)"
echo "3. Run full automated migration (4-14 hours)"
echo "4. Check migration status"
echo "5. Run validation only"
echo ""
read -p "Enter choice (1-5): " choice

case $choice in
    1)
        echo ""
        echo -e "${BLUE}Testing Stage 1 (Reference Data)...${NC}"
        docker exec -it devcontainer-web-1 bash -c "cd /var/www/openeyes && php protected/yiic fulldatamigration stage --stage=1 --verbose"
        echo ""
        echo -e "${GREEN}Validating Stage 1...${NC}"
        docker exec -it devcontainer-web-1 bash -c "cd /var/www/openeyes && php protected/yiic datavalidation counts"
        ;;
    2)
        echo ""
        echo -e "${BLUE}Running Stage-by-Stage Migration...${NC}"
        echo ""
        
        echo -e "${BLUE}Stage 1: Reference Data...${NC}"
        docker exec -it devcontainer-web-1 bash -c "cd /var/www/openeyes && php protected/yiic fulldatamigration stage --stage=1 --verbose"
        docker exec -it devcontainer-web-1 bash -c "cd /var/www/openeyes && php protected/yiic datavalidation counts"
        
        echo ""
        read -p "Stage 1 complete. Continue with Stage 2? (y/n): " cont
        if [[ $cont != "y" ]]; then exit 0; fi
        
        echo -e "${BLUE}Stage 2: Clinical Reference...${NC}"
        docker exec -it devcontainer-web-1 bash -c "cd /var/www/openeyes && php protected/yiic fulldatamigration stage --stage=2 --verbose"
        docker exec -it devcontainer-web-1 bash -c "cd /var/www/openeyes && php protected/yiic datavalidation counts"
        
        echo ""
        read -p "Stage 2 complete. Continue with Stage 3? (y/n): " cont
        if [[ $cont != "y" ]]; then exit 0; fi
        
        echo -e "${BLUE}Stage 3: Core Clinical...${NC}"
        docker exec -it devcontainer-web-1 bash -c "cd /var/www/openeyes && php protected/yiic fulldatamigration stage --stage=3 --batch=100 --verbose"
        docker exec -it devcontainer-web-1 bash -c "cd /var/www/openeyes && php protected/yiic datavalidation samples --table=patient --sample=50"
        
        echo ""
        read -p "Stage 3 complete. Continue with Stage 4? (y/n): " cont
        if [[ $cont != "y" ]]; then exit 0; fi
        
        echo -e "${BLUE}Stage 4: Module Elements...${NC}"
        docker exec -it devcontainer-web-1 bash -c "cd /var/www/openeyes && php protected/yiic fulldatamigration stage --stage=4 --verbose"
        docker exec -it devcontainer-web-1 bash -c "cd /var/www/openeyes && php protected/yiic moduledata status"
        
        echo ""
        read -p "Stage 4 complete. Continue with Stage 5? (y/n): " cont
        if [[ $cont != "y" ]]; then exit 0; fi
        
        echo -e "${BLUE}Stage 5: Administrative...${NC}"
        docker exec -it devcontainer-web-1 bash -c "cd /var/www/openeyes && php protected/yiic fulldatamigration stage --stage=5 --verbose"
        
        echo ""
        echo -e "${GREEN}All stages complete! Running final validation...${NC}"
        docker exec -it devcontainer-web-1 bash -c "cd /var/www/openeyes && php protected/yiic datavalidation all --sample=500"
        ;;
    3)
        echo ""
        echo -e "${BLUE}Running Full Automated Migration...${NC}"
        docker exec -it devcontainer-web-1 bash -c "cd /var/www/openeyes && php protected/yiic fulldatamigration run --verbose"
        echo ""
        echo -e "${GREEN}Migration complete! Running validation...${NC}"
        docker exec -it devcontainer-web-1 bash -c "cd /var/www/openeyes && php protected/yiic datavalidation all --sample=500"
        ;;
    4)
        echo ""
        echo -e "${BLUE}Checking Migration Status...${NC}"
        docker exec -it devcontainer-web-1 bash -c "cd /var/www/openeyes && php protected/yiic fulldatamigration status"
        ;;
    5)
        echo ""
        echo -e "${BLUE}Running Comprehensive Validation...${NC}"
        docker exec -it devcontainer-web-1 bash -c "cd /var/www/openeyes && php protected/yiic datavalidation all --sample=500 --verbose"
        ;;
    *)
        echo "Invalid choice"
        exit 1
        ;;
esac

echo ""
echo -e "${GREEN}=========================================="
echo "Complete!"
echo "==========================================${NC}"
