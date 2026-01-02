#!/bin/bash
# ============================================================================
# Create Medication Set Collections in Couchbase
# ============================================================================
# This script creates the medication_set and medication_set_item collections
# in the 'reference' scope of the Couchbase bucket.
# ============================================================================

set -e  # Exit on any error

# Configuration
BUCKET="${COUCHBASE_BUCKET:-openeyes}"
SCOPE="reference"
COUCHBASE_HOST="${COUCHBASE_HOST:-localhost}"
COUCHBASE_USER="${COUCHBASE_USER:-Administrator}"
COUCHBASE_PASS="${COUCHBASE_PASS:-password}"

echo "============================================================================"
echo "Creating Medication Set Collections"
echo "============================================================================"
echo "Bucket: $BUCKET"
echo "Scope: $SCOPE"
echo "Host: $COUCHBASE_HOST"
echo ""

# Function to check if running in Docker
check_docker() {
    if docker ps | grep -q couchbase; then
        echo "✓ Detected Couchbase running in Docker"
        USE_DOCKER=true
    else
        echo "○ Couchbase not found in Docker, using local couchbase-cli"
        USE_DOCKER=false
    fi
}

# Function to create a collection
create_collection() {
    local collection_name=$1
    
    echo "Creating collection: $SCOPE.$collection_name"
    
    if [ "$USE_DOCKER" = true ]; then
        docker exec couchbase couchbase-cli collection-manage \
            --cluster localhost \
            --username "$COUCHBASE_USER" \
            --password "$COUCHBASE_PASS" \
            --bucket "$BUCKET" \
            --create-collection "$SCOPE.$collection_name" 2>&1
    else
        couchbase-cli collection-manage \
            --cluster "$COUCHBASE_HOST" \
            --username "$COUCHBASE_USER" \
            --password "$COUCHBASE_PASS" \
            --bucket "$BUCKET" \
            --create-collection "$SCOPE.$collection_name" 2>&1
    fi
    
    local exit_code=$?
    if [ $exit_code -eq 0 ]; then
        echo "  ✓ Collection created: $collection_name"
        return 0
    else
        echo "  ○ Collection may already exist or error occurred"
        return 1
    fi
}

# Main execution
check_docker

echo ""
echo "Creating collections..."
echo "-------------------------------------------"

# Create medication_set collection
create_collection "medication_set"

# Create medication_set_item collection
create_collection "medication_set_item"

echo ""
echo "============================================================================"
echo "Collection Creation Complete!"
echo "============================================================================"
echo ""
echo "Next steps:"
echo "1. Run N1QL indexes: cbq -f protected/scripts/couchbase/medication-set-indexes.n1ql"
echo "2. Migrate data: php protected/yiic medicationSetMigration"
echo "3. Verify in Couchbase UI: http://localhost:8091"
echo ""
echo "To verify collections were created:"
echo "  docker exec couchbase couchbase-cli collection-manage \\"
echo "    --cluster localhost -u $COUCHBASE_USER -p $COUCHBASE_PASS \\"
echo "    --bucket $BUCKET --list-collections"
echo ""
