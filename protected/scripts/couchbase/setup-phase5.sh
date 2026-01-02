#!/bin/bash
# Phase 5 Automated Setup Script
# This script automates the complete Couchbase setup for Phase 5 testing

set -e  # Exit on error

# Configuration
CB_HOST="${CB_HOST:-localhost}"
CB_PORT="${CB_PORT:-8091}"
CB_USER="${CB_USER:-Administrator}"
CB_PASS="${CB_PASS:-password}"
CB_BUCKET="${CB_BUCKET:-openeyes}"
CB_RAM="${CB_RAM:-512}"

echo "======================================================================="
echo "Phase 5: Automated Couchbase Setup"
echo "======================================================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to check if Couchbase is ready
check_couchbase() {
    echo -n "Checking if Couchbase is accessible..."
    if curl -s "http://${CB_HOST}:${CB_PORT}/pools" > /dev/null 2>&1; then
        echo -e " ${GREEN}✓${NC}"
        return 0
    else
        echo -e " ${RED}✗${NC}"
        return 1
    fi
}

# Function to check if cluster is initialized
check_initialized() {
    response=$(curl -s -u "${CB_USER}:${CB_PASS}" "http://${CB_HOST}:${CB_PORT}/pools/default" 2>&1)
    if echo "$response" | grep -q "nodes"; then
        return 0
    else
        return 1
    fi
}

# Wait for Couchbase to be ready
echo "Waiting for Couchbase to be ready..."
MAX_WAIT=60
WAIT_COUNT=0
while ! check_couchbase; do
    if [ $WAIT_COUNT -ge $MAX_WAIT ]; then
        echo -e "${RED}Error: Couchbase not accessible after ${MAX_WAIT} seconds${NC}"
        echo "Please ensure Couchbase container is running:"
        echo "  docker ps | grep couchbase"
        exit 1
    fi
    sleep 2
    WAIT_COUNT=$((WAIT_COUNT + 2))
    echo "  Waiting... (${WAIT_COUNT}s)"
done

echo ""
echo "======================================================================="
echo "Step 1: Initialize Cluster"
echo "======================================================================="

if check_initialized; then
    echo -e "${YELLOW}Cluster already initialized, skipping...${NC}"
else
    echo "Initializing Couchbase cluster..."
    
    # Initialize cluster
    curl -s -X POST "http://${CB_HOST}:${CB_PORT}/clusterInit" \
        -d "username=${CB_USER}" \
        -d "password=${CB_PASS}" \
        -d "port=8091" \
        -d "services=kv,n1ql,index" \
        -d "indexerStorageMode=plasma" > /dev/null
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Cluster initialized${NC}"
    else
        echo -e "${RED}✗ Failed to initialize cluster${NC}"
        exit 1
    fi
    
    # Wait for cluster to be ready
    sleep 5
fi

echo ""
echo "======================================================================="
echo "Step 2: Create Bucket"
echo "======================================================================="

# Check if bucket exists
if curl -s -u "${CB_USER}:${CB_PASS}" "http://${CB_HOST}:${CB_PORT}/pools/default/buckets/${CB_BUCKET}" > /dev/null 2>&1; then
    echo -e "${YELLOW}Bucket '${CB_BUCKET}' already exists, skipping...${NC}"
else
    echo "Creating bucket '${CB_BUCKET}'..."
    
    curl -s -X POST "http://${CB_HOST}:${CB_PORT}/pools/default/buckets" \
        -u "${CB_USER}:${CB_PASS}" \
        -d "name=${CB_BUCKET}" \
        -d "bucketType=couchbase" \
        -d "ramQuota=${CB_RAM}" \
        -d "replicaNumber=0" \
        -d "flushEnabled=0" > /dev/null
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Bucket created (${CB_RAM}MB RAM)${NC}"
    else
        echo -e "${RED}✗ Failed to create bucket${NC}"
        exit 1
    fi
    
    # Wait for bucket to be ready
    echo "Waiting for bucket to be ready..."
    sleep 10
fi

echo ""
echo "======================================================================="
echo "Step 3: Create Scopes"
echo "======================================================================="

SCOPES=("core" "clinical" "booking" "correspondence" "admin" "reference")

for scope in "${SCOPES[@]}"; do
    echo -n "Creating scope '${scope}'..."
    
    # Check if scope exists
    if curl -s -u "${CB_USER}:${CB_PASS}" \
        "http://${CB_HOST}:${CB_PORT}/pools/default/buckets/${CB_BUCKET}/scopes" \
        | grep -q "\"name\":\"${scope}\""; then
        echo -e " ${YELLOW}already exists${NC}"
    else
        curl -s -X POST "http://${CB_HOST}:${CB_PORT}/pools/default/buckets/${CB_BUCKET}/scopes" \
            -u "${CB_USER}:${CB_PASS}" \
            -d "name=${scope}" > /dev/null
        
        if [ $? -eq 0 ]; then
            echo -e " ${GREEN}✓${NC}"
        else
            echo -e " ${RED}✗${NC}"
        fi
    fi
done

# Wait for scopes to be ready
sleep 3

echo ""
echo "======================================================================="
echo "Step 4: Create Collections"
echo "======================================================================="

# Clinical collections
echo "Creating clinical collections..."

# Define collections for each scope
create_collection() {
    local scope=$1
    local collection=$2
    
    echo -n "  Creating ${scope}.${collection}..."
    
    # Check if collection exists
    if curl -s -u "${CB_USER}:${CB_PASS}" \
        "http://${CB_HOST}:${CB_PORT}/pools/default/buckets/${CB_BUCKET}/scopes/${scope}/collections" \
        | grep -q "\"name\":\"${collection}\""; then
        echo -e " ${YELLOW}exists${NC}"
    else
        curl -s -X POST "http://${CB_HOST}:${CB_PORT}/pools/default/buckets/${CB_BUCKET}/scopes/${scope}/collections" \
            -u "${CB_USER}:${CB_PASS}" \
            -d "name=${collection}" > /dev/null
        
        if [ $? -eq 0 ]; then
            echo -e " ${GREEN}✓${NC}"
        else
            echo -e " ${RED}✗${NC}"
        fi
    fi
}

# Create collections for clinical scope
create_collection "clinical" "examination"

# Create collections for booking scope
create_collection "booking" "operation"
create_collection "booking" "session"
create_collection "booking" "whiteboard"

# Create collections for correspondence scope
create_collection "correspondence" "letter"
create_collection "correspondence" "message"
create_collection "correspondence" "document"

# Wait for collections to be ready
sleep 5

echo ""
echo "======================================================================="
echo "Step 5: Create Indexes"
echo "======================================================================="

echo "Creating N1QL indexes..."
echo "(This may take 2-3 minutes)"

# Path to index file
INDEX_FILE="$(dirname "$0")/indexes/module-indexes.n1ql"

if [ ! -f "$INDEX_FILE" ]; then
    echo -e "${RED}Error: Index file not found: ${INDEX_FILE}${NC}"
    exit 1
fi

# Execute indexes one by one
INDEX_COUNT=0
ERROR_COUNT=0
STATEMENT=""

while IFS= read -r line || [ -n "$STATEMENT" ]; do
    # Skip empty lines and comments at the start of a statement
    if [[ -z "$STATEMENT" ]] && [[ -z "$line" || "$line" =~ ^-- ]]; then
        continue
    fi
    
    # Accumulate lines until we find a semicolon
    if [[ -n "$line" ]] && ! [[ "$line" =~ ^-- ]]; then
        STATEMENT="${STATEMENT} ${line}"
    fi
    
    # Check if we have a complete statement (ends with semicolon)
    if [[ "$STATEMENT" =~ \;[[:space:]]*$ ]] || [[ -z "$line" && -n "$STATEMENT" ]]; then
        # Remove semicolon and trim whitespace
        STATEMENT=$(echo "$STATEMENT" | sed 's/;[[:space:]]*$//' | xargs)
        
        if [ -n "$STATEMENT" ]; then
            # Extract index name if possible
            INDEX_NAME=$(echo "$STATEMENT" | grep -o "idx_[a-zA-Z0-9_]*" || echo "")
            
            if [ -n "$INDEX_NAME" ]; then
                echo -n "  Creating ${INDEX_NAME}..."
            else
                echo -n "  Creating index..."
            fi
            
            # Execute query using POST with proper URL encoding
            response=$(curl -s -X POST "http://${CB_HOST}:8093/query/service" \
                -u "${CB_USER}:${CB_PASS}" \
                --data-urlencode "statement=${STATEMENT}")
            
            if echo "$response" | grep -q '"status":"success"'; then
                echo -e " ${GREEN}✓${NC}"
                INDEX_COUNT=$((INDEX_COUNT + 1))
            elif echo "$response" | grep -q "already exists"; then
                echo -e " ${YELLOW}exists${NC}"
                INDEX_COUNT=$((INDEX_COUNT + 1))
            else
                echo -e " ${RED}✗${NC}"
                ERROR_COUNT=$((ERROR_COUNT + 1))
                # Uncomment to see error details:
                # echo "  Error: $(echo "$response" | python3 -c 'import json,sys; print(json.load(sys.stdin).get(\"errors\", [{}])[0].get(\"msg\", \"unknown\"))' 2>/dev/null || echo 'parse failed')"
            fi
        fi
        
        # Reset for next statement
        STATEMENT=""
    fi
    
done < "$INDEX_FILE"

echo ""
echo -e "Indexes created: ${GREEN}${INDEX_COUNT}${NC}"
if [ $ERROR_COUNT -gt 0 ]; then
    echo -e "Errors: ${RED}${ERROR_COUNT}${NC}"
fi

echo ""
echo "======================================================================="
echo "Step 6: Verify Setup"
echo "======================================================================="

# Verify bucket
echo -n "Bucket '${CB_BUCKET}' exists..."
if curl -s -u "${CB_USER}:${CB_PASS}" \
    "http://${CB_HOST}:${CB_PORT}/pools/default/buckets/${CB_BUCKET}" > /dev/null 2>&1; then
    echo -e " ${GREEN}✓${NC}"
else
    echo -e " ${RED}✗${NC}"
fi

# Count scopes
SCOPE_COUNT=$(curl -s -u "${CB_USER}:${CB_PASS}" \
    "http://${CB_HOST}:${CB_PORT}/pools/default/buckets/${CB_BUCKET}/scopes" \
    | grep -o '"name":"[^"]*"' | wc -l)
echo "Scopes created: ${SCOPE_COUNT}"

# Count indexes
INDEX_STATUS=$(curl -s -u "${CB_USER}:${CB_PASS}" \
    "http://${CB_HOST}:${CB_PORT}/query/service" \
    -d "statement=SELECT COUNT(*) as count FROM system:indexes WHERE bucket_id='${CB_BUCKET}'")
ONLINE_INDEXES=$(echo "$INDEX_STATUS" | grep -o '"count":[0-9]*' | grep -o '[0-9]*')
if [ -n "$ONLINE_INDEXES" ]; then
    echo "Indexes online: ${ONLINE_INDEXES}"
fi

echo ""
echo "======================================================================="
echo "Setup Complete!"
echo "======================================================================="
echo ""
echo "Next steps:"
echo ""
echo "1. Test sync with small batch:"
echo "   php protected/yiic.php couchbasemodulesync sync \\"
echo "     --module=OphCiExamination --from=1 --to=5 --verbose"
echo ""
echo "2. Verify sync:"
echo "   php protected/yiic.php couchbasemodulesync verify \\"
echo "     --module=OphCiExamination"
echo ""
echo "3. View data in Query Workbench:"
echo "   http://${CB_HOST}:${CB_PORT}"
echo "   SELECT * FROM \`${CB_BUCKET}\`.\`clinical\`.\`examination\` LIMIT 5;"
echo ""
echo -e "${GREEN}Phase 5 setup ready for testing!${NC}"
echo ""
