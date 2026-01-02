#!/bin/bash
#
# Create Couchbase collections for administrative models (Phase 12)
# Usage: ./create-admin-collections.sh
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

echo "=== Creating Admin Collections (Phase 12) ==="
echo "Host: ${CB_HOST}"
echo "Bucket: ${CB_BUCKET}"
echo ""

# Create admin scope (if not exists)
echo "Creating admin scope..."
curl -s -X POST \
    "http://${CB_HOST}:8091/pools/default/buckets/${CB_BUCKET}/scopes" \
    -u "${CB_USER}:${CB_PASS}" \
    -d "name=admin" \
    2>/dev/null || echo "  (scope may already exist)"

echo ""
echo "Creating collections in admin scope..."

# Define all admin collections
COLLECTIONS=(
    # Audit tables (3 collections)
    "audit"
    "audit_action"
    "audit_type"
    
    # Settings tables (8 collections)
    "setting_metadata"
    "setting_installation"
    "setting_institution"
    "setting_site"
    "setting_firm"
    "setting_user"
    "setting_group"
    "setting_field_type"
    
    # Authentication tables (3 collections)
    "user_authentication"
    "institution_authentication"
    "user_authentication_method"
    
    # Authorization tables (2 collections)
    "auth_item"
    "auth_assignment"
)

# Create each collection
for coll in "${COLLECTIONS[@]}"; do
    echo "  Creating admin.${coll}"
    curl -s -X POST \
        "http://${CB_HOST}:8091/pools/default/buckets/${CB_BUCKET}/scopes/admin/collections" \
        -u "${CB_USER}:${CB_PASS}" \
        -d "name=${coll}" \
        2>/dev/null || echo "    (collection may already exist)"
done

echo ""
echo "=== Admin Collection Creation Complete ==="
echo ""
echo "Total collections created: ${#COLLECTIONS[@]}"
echo ""
echo "Verify collections at: http://${CB_HOST}:8091/ui/index.html#!/buckets/${CB_BUCKET}"
echo ""
echo "Or query via cbq:"
echo "  SELECT name FROM system:keyspaces WHERE bucket='${CB_BUCKET}' AND scope='admin' ORDER BY name;"
echo ""
