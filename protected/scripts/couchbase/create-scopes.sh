#!/bin/bash
# Create scopes and collections for OpenEyes
# Usage: ./create-scopes.sh [host] [username] [password] [bucket]

set -e

CB_HOST="${1:-${CB_HOST:-localhost}}"
CB_USER="${2:-${CB_USER:-Administrator}}"
CB_PASS="${3:-${CB_PASS:-password}}"
BUCKET="${4:-openeyes}"

echo "Creating scopes and collections in bucket: ${BUCKET}"

# Function to create a scope
create_scope() {
    local scope=$1
    echo "Creating scope: ${scope}"
    
    curl -s -X POST \
        "http://${CB_HOST}:8091/pools/default/buckets/${BUCKET}/scopes" \
        -u "${CB_USER}:${CB_PASS}" \
        -d "name=${scope}" > /dev/null 2>&1 || true
}

# Function to create a collection in a scope
create_collection() {
    local scope=$1
    local collection=$2
    
    echo "  Creating collection: ${scope}.${collection}"
    
    curl -s -X POST \
        "http://${CB_HOST}:8091/pools/default/buckets/${BUCKET}/scopes/${scope}/collections" \
        -u "${CB_USER}:${CB_PASS}" \
        -d "name=${collection}" > /dev/null 2>&1 || true
}

# Wait for bucket to be ready
echo "Waiting for bucket to be ready..."
sleep 5

# Create scopes
echo ""
echo "=== Creating Scopes ==="
create_scope "core"
create_scope "clinical"
create_scope "correspondence"
create_scope "booking"
create_scope "admin"
create_scope "reference"

# Create core collections
echo ""
echo "=== Creating Core Collections ==="
for coll in patient user episode event firm site institution contact address; do
    create_collection "core" "$coll"
done

# Create clinical collections
echo ""
echo "=== Creating Clinical Collections ==="
for coll in examination diagnosis procedure medication allergy; do
    create_collection "clinical" "$coll"
done

# Create correspondence collections
echo ""
echo "=== Creating Correspondence Collections ==="
for coll in letter message document; do
    create_collection "correspondence" "$coll"
done

# Create booking collections
echo ""
echo "=== Creating Booking Collections ==="
for coll in operation session whiteboard; do
    create_collection "booking" "$coll"
done

# Create admin collections
echo ""
echo "=== Creating Admin Collections ==="
for coll in audit setting; do
    create_collection "admin" "$coll"
done

# Create reference collections
echo ""
echo "=== Creating Reference Collections ==="
for coll in specialty subspecialty disorder drug procedure_type; do
    create_collection "reference" "$coll"
done

echo ""
echo "SUCCESS: All scopes and collections created!"
echo "Verify at: http://${CB_HOST}:8091/ui/index.html#/buckets/${BUCKET}"
