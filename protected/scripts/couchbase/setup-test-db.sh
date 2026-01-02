#!/bin/bash
#
# OpenEyes - Couchbase Test Database Setup
# Sets up scopes and collections for the test environment
#

set -e

# Configuration
COUCHBASE_HOST="${COUCHBASE_TEST_HOST:-localhost}"
COUCHBASE_PORT="${COUCHBASE_TEST_PORT:-8091}"
COUCHBASE_USER="${COUCHBASE_TEST_USER:-Administrator}"
COUCHBASE_PASS="${COUCHBASE_TEST_PASS:-password}"
COUCHBASE_BUCKET="${COUCHBASE_TEST_BUCKET:-openeyes_test}"

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
API_URL="http://${COUCHBASE_HOST}:${COUCHBASE_PORT}"

echo "========================================"
echo "OpenEyes Couchbase Test Setup"
echo "========================================"
echo "Host: ${COUCHBASE_HOST}:${COUCHBASE_PORT}"
echo "Bucket: ${COUCHBASE_BUCKET}"
echo ""

# Check if Couchbase is running
echo "Checking Couchbase availability..."
if ! curl -s -o /dev/null -w "%{http_code}" "${API_URL}/ui/index.html" | grep -q "200\|302"; then
    echo "ERROR: Couchbase is not available at ${API_URL}"
    echo "Please start Couchbase container first:"
    echo "  docker-compose -f docker-compose.couchbase.yml up -d"
    exit 1
fi
echo "OK: Couchbase is running"

# Wait for cluster to be ready
echo "Waiting for cluster to be ready..."
for i in {1..30}; do
    if curl -s -u "${COUCHBASE_USER}:${COUCHBASE_PASS}" "${API_URL}/pools/default" | grep -q "healthy"; then
        echo "OK: Cluster is healthy"
        break
    fi
    if [ $i -eq 30 ]; then
        echo "ERROR: Cluster not ready after 30 seconds"
        exit 1
    fi
    sleep 1
done

# Create test bucket if it doesn't exist
echo "Checking test bucket..."
if ! curl -s -u "${COUCHBASE_USER}:${COUCHBASE_PASS}" "${API_URL}/pools/default/buckets/${COUCHBASE_BUCKET}" | grep -q "${COUCHBASE_BUCKET}"; then
    echo "Creating test bucket: ${COUCHBASE_BUCKET}"
    curl -s -u "${COUCHBASE_USER}:${COUCHBASE_PASS}" \
        -X POST "${API_URL}/pools/default/buckets" \
        -d "name=${COUCHBASE_BUCKET}" \
        -d "ramQuotaMB=256" \
        -d "bucketType=couchbase" \
        -d "flushEnabled=1"
    
    echo "Waiting for bucket to be ready..."
    sleep 5
fi
echo "OK: Test bucket exists"

# Define scopes and collections
SCOPES=("core" "clinical" "admin" "audit")
CORE_COLLECTIONS=("patient" "contact" "address" "episode" "event")
CLINICAL_COLLECTIONS=("examination" "diagnosis" "treatment" "medication")
ADMIN_COLLECTIONS=("user" "firm" "institution" "site" "subspecialty")
AUDIT_COLLECTIONS=("audit_log")

# Create scopes
echo "Creating scopes..."
for scope in "${SCOPES[@]}"; do
    echo "  Creating scope: ${scope}"
    curl -s -u "${COUCHBASE_USER}:${COUCHBASE_PASS}" \
        -X POST "${API_URL}/pools/default/buckets/${COUCHBASE_BUCKET}/scopes" \
        -d "name=${scope}" || true
done
sleep 2

# Create collections
echo "Creating collections..."

for collection in "${CORE_COLLECTIONS[@]}"; do
    echo "  Creating core.${collection}"
    curl -s -u "${COUCHBASE_USER}:${COUCHBASE_PASS}" \
        -X POST "${API_URL}/pools/default/buckets/${COUCHBASE_BUCKET}/scopes/core/collections" \
        -d "name=${collection}" || true
done

for collection in "${CLINICAL_COLLECTIONS[@]}"; do
    echo "  Creating clinical.${collection}"
    curl -s -u "${COUCHBASE_USER}:${COUCHBASE_PASS}" \
        -X POST "${API_URL}/pools/default/buckets/${COUCHBASE_BUCKET}/scopes/clinical/collections" \
        -d "name=${collection}" || true
done

for collection in "${ADMIN_COLLECTIONS[@]}"; do
    echo "  Creating admin.${collection}"
    curl -s -u "${COUCHBASE_USER}:${COUCHBASE_PASS}" \
        -X POST "${API_URL}/pools/default/buckets/${COUCHBASE_BUCKET}/scopes/admin/collections" \
        -d "name=${collection}" || true
done

for collection in "${AUDIT_COLLECTIONS[@]}"; do
    echo "  Creating audit.${collection}"
    curl -s -u "${COUCHBASE_USER}:${COUCHBASE_PASS}" \
        -X POST "${API_URL}/pools/default/buckets/${COUCHBASE_BUCKET}/scopes/audit/collections" \
        -d "name=${collection}" || true
done

echo "Waiting for collections to be ready..."
sleep 3

# Create primary indexes for test queries
echo "Creating primary indexes..."
QUERY_URL="http://${COUCHBASE_HOST}:8093/query/service"

for scope in "${SCOPES[@]}"; do
    case $scope in
        core)
            collections=("${CORE_COLLECTIONS[@]}")
            ;;
        clinical)
            collections=("${CLINICAL_COLLECTIONS[@]}")
            ;;
        admin)
            collections=("${ADMIN_COLLECTIONS[@]}")
            ;;
        audit)
            collections=("${AUDIT_COLLECTIONS[@]}")
            ;;
    esac
    
    for collection in "${collections[@]}"; do
        echo "  Creating index on ${scope}.${collection}"
        curl -s -u "${COUCHBASE_USER}:${COUCHBASE_PASS}" \
            -X POST "${QUERY_URL}" \
            -d "statement=CREATE PRIMARY INDEX IF NOT EXISTS ON \`${COUCHBASE_BUCKET}\`.\`${scope}\`.\`${collection}\`" || true
    done
done

echo ""
echo "========================================"
echo "Test Database Setup Complete!"
echo "========================================"
echo ""
echo "Connection String: couchbase://${COUCHBASE_HOST}:${COUCHBASE_PORT}"
echo "Bucket: ${COUCHBASE_BUCKET}"
echo "Username: ${COUCHBASE_USER}"
echo ""
echo "Run tests with:"
echo "  ./protected/scripts/couchbase/run-couchbase-tests.sh"
echo ""
