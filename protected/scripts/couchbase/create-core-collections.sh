#!/bin/bash
#
# Create Couchbase collections for core models
# Usage: ./create-core-collections.sh
#
# Environment variables:
#   CB_HOST - Couchbase host (default: localhost)
#   CB_USER - Couchbase admin username (default: Administrator)
#   CB_PASS - Couchbase admin password (default: password)
#   CB_BUCKET - Bucket name (default: openeyes)
#

set -e

CB_HOST="${CB_HOST:-localhost}"
CB_USER="${CB_USER:-Administrator}"
CB_PASS="${CB_PASS:-password}"
CB_BUCKET="${CB_BUCKET:-openeyes}"

echo "=== Creating Core Collections ==="
echo "Host: ${CB_HOST}"
echo "Bucket: ${CB_BUCKET}"
echo ""

# Define scopes and their collections
declare -A SCOPE_COLLECTIONS=(
    ["core"]="patient user episode event firm site institution contact address"
    ["clinical"]="examination diagnosis"
    ["correspondence"]="letter message document"
    ["booking"]="operation session theatre"
    ["admin"]="audit setting"
    ["reference"]="event_type element_type specialty subspecialty disorder ethnic_group gender eye event_group country drug procedure medication allergy medication_route medication_form medication_frequency medication_duration medication_laterality benefit complication common_ophthalmic_disorder opcs_code"
)

# First, ensure scopes exist
echo "Creating scopes..."
for scope in "${!SCOPE_COLLECTIONS[@]}"; do
    echo "  Creating scope: ${scope}"
    curl -s -X POST \
        "http://${CB_HOST}:8091/pools/default/buckets/${CB_BUCKET}/scopes" \
        -u "${CB_USER}:${CB_PASS}" \
        -d "name=${scope}" \
        2>/dev/null || true
done

echo ""
echo "Creating collections..."

# Create collections in each scope
for scope in "${!SCOPE_COLLECTIONS[@]}"; do
    collections=${SCOPE_COLLECTIONS[$scope]}
    for coll in $collections; do
        echo "  Creating ${scope}.${coll}"
        curl -s -X POST \
            "http://${CB_HOST}:8091/pools/default/buckets/${CB_BUCKET}/scopes/${scope}/collections" \
            -u "${CB_USER}:${CB_PASS}" \
            -d "name=${coll}" \
            2>/dev/null || true
    done
done

echo ""
echo "=== Collection Creation Complete ==="
echo ""
echo "Verify collections at: http://${CB_HOST}:8091/ui/index.html#!/buckets/${CB_BUCKET}"
