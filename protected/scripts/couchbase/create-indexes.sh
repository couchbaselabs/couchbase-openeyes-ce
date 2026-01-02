#!/bin/bash
# Create Couchbase indexes for OpenEyes
# Usage: ./create-indexes.sh [host] [username] [password]

set -e

CB_HOST="${1:-${CB_HOST:-localhost}}"
CB_USER="${2:-${CB_USER:-Administrator}}"
CB_PASS="${3:-${CB_PASS:-password}}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "Creating Couchbase indexes..."

# Execute index creation via REST API
execute_n1ql() {
    local query=$1
    echo "Executing: ${query:0:80}..."
    
    curl -s -X POST "http://${CB_HOST}:8093/query/service" \
        -u "${CB_USER}:${CB_PASS}" \
        -d "statement=${query}" > /dev/null 2>&1 || true
}

# Read and execute N1QL file
while IFS= read -r line; do
    # Skip comments and empty lines
    [[ "$line" =~ ^--.*$ ]] && continue
    [[ -z "$line" ]] && continue
    
    execute_n1ql "$line"
done < "${SCRIPT_DIR}/indexes/create-primary-indexes.n1ql"

echo ""
echo "Index creation complete!"
echo "Verify indexes at: http://${CB_HOST}:8091/ui/index.html#/query"
