#!/bin/bash
# Initialize Couchbase cluster for OpenEyes
# Usage: ./init-cluster.sh [host] [username] [password] [ram_quota_mb]

set -e

CB_HOST="${1:-${CB_HOST:-localhost}}"
CB_PORT="${CB_PORT:-8091}"
CB_USER="${2:-${CB_USER:-Administrator}}"
CB_PASS="${3:-${CB_PASS:-password}}"
CB_RAM_QUOTA="${4:-${CB_RAM_QUOTA:-1024}}"

echo "Initializing Couchbase cluster..."
echo "Host: ${CB_HOST}:${CB_PORT}"

# Wait for Couchbase to be ready
echo "Waiting for Couchbase to start..."
MAX_RETRIES=30
RETRY_COUNT=0
until curl -s http://${CB_HOST}:${CB_PORT}/ui/index.html > /dev/null 2>&1; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
        echo "ERROR: Couchbase did not start within expected time"
        exit 1
    fi
    echo "  Attempt ${RETRY_COUNT}/${MAX_RETRIES}..."
    sleep 5
done

echo "Couchbase is ready. Initializing cluster..."

# Initialize cluster
curl -s -X POST "http://${CB_HOST}:${CB_PORT}/clusterInit" \
    -d "hostname=${CB_HOST}" \
    -d "dataPath=/opt/couchbase/var/lib/couchbase/data" \
    -d "indexPath=/opt/couchbase/var/lib/couchbase/data" \
    -d "username=${CB_USER}" \
    -d "password=${CB_PASS}" \
    -d "port=SAME" \
    -d "sendStats=false" \
    -d "services=kv,n1ql,index,fts" \
    -d "clusterName=openeyes-cluster" \
    -d "memoryQuota=${CB_RAM_QUOTA}" \
    -d "indexMemoryQuota=512" \
    -d "ftsMemoryQuota=256"

if [ $? -eq 0 ]; then
    echo "SUCCESS: Cluster initialized successfully"
    echo "  Admin UI: http://${CB_HOST}:${CB_PORT}"
    echo "  Username: ${CB_USER}"
else
    echo "ERROR: Cluster initialization failed"
    exit 1
fi
