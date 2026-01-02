#!/bin/bash
# Complete Couchbase setup for OpenEyes
# Usage: ./setup-all.sh [host] [username] [password]

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CB_HOST="${1:-${CB_HOST:-localhost}}"
CB_USER="${2:-${CB_USER:-Administrator}}"
CB_PASS="${3:-${CB_PASS:-password}}"

echo "========================================"
echo "OpenEyes Couchbase Setup"
echo "========================================"
echo "Host: ${CB_HOST}"
echo ""

# Step 1: Initialize cluster
echo "[1/4] Initializing cluster..."
bash "${SCRIPT_DIR}/init-cluster.sh" "${CB_HOST}" "${CB_USER}" "${CB_PASS}"
echo ""

# Step 2: Create buckets
echo "[2/4] Creating buckets..."
bash "${SCRIPT_DIR}/create-buckets.sh" "${CB_HOST}" "${CB_USER}" "${CB_PASS}"
echo ""

# Wait for bucket initialization
echo "Waiting for buckets to initialize..."
sleep 10

# Step 3: Create scopes and collections
echo "[3/4] Creating scopes and collections..."
bash "${SCRIPT_DIR}/create-scopes.sh" "${CB_HOST}" "${CB_USER}" "${CB_PASS}"
echo ""

# Wait for collections to be ready
echo "Waiting for collections to be ready..."
sleep 5

# Step 4: Create indexes
echo "[4/4] Creating indexes..."
bash "${SCRIPT_DIR}/create-indexes.sh" "${CB_HOST}" "${CB_USER}" "${CB_PASS}"
echo ""

echo "========================================"
echo "Setup Complete!"
echo "========================================"
echo ""
echo "Couchbase Admin UI: http://${CB_HOST}:8091"
echo "Username: ${CB_USER}"
echo ""
echo "Next steps:"
echo "1. Run 'composer update' to install PHP SDK"
echo "2. Verify PHP extension: php -m | grep couchbase"
echo "3. Test connection: curl http://localhost/couchbaseHealth"
