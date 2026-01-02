#!/bin/bash
# Create OpenEyes buckets in Couchbase
# Usage: ./create-buckets.sh [host] [username] [password]

set -e

CB_HOST="${1:-${CB_HOST:-localhost}}"
CB_USER="${2:-${CB_USER:-Administrator}}"
CB_PASS="${3:-${CB_PASS:-password}}"

echo "Creating Couchbase buckets..."

# Function to create a bucket
create_bucket() {
    local name=$1
    local ram=$2
    
    echo "Creating bucket: ${name} (${ram}MB RAM)"
    
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" -X POST \
        "http://${CB_HOST}:8091/pools/default/buckets" \
        -u "${CB_USER}:${CB_PASS}" \
        -d "name=${name}" \
        -d "ramQuotaMB=${ram}" \
        -d "bucketType=couchbase" \
        -d "durabilityMinLevel=none" \
        -d "replicaNumber=0")
    
    if [ "$HTTP_CODE" = "202" ]; then
        echo "  SUCCESS: Bucket '${name}' created"
    elif [ "$HTTP_CODE" = "400" ]; then
        echo "  WARNING: Bucket '${name}' may already exist"
    else
        echo "  ERROR: Failed to create bucket '${name}' (HTTP ${HTTP_CODE})"
        return 1
    fi
}

# Main data bucket
create_bucket "openeyes" 512

# Test bucket (for testing environment)
create_bucket "openeyes_test" 256

echo ""
echo "Bucket creation complete!"
echo "Verify at: http://${CB_HOST}:8091/ui/index.html#/buckets"
