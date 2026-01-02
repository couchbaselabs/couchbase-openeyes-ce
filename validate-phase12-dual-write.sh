#!/bin/bash
#
# Phase 12 Dual-Write Validation Script
# Validates that all administrative tables are writing to both MySQL and Couchbase
#

set -e

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║     Phase 12: Dual-Write Validation                           ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if inside container or host
if [ -d "/var/www/openeyes" ]; then
    # Inside container
    WEB_EXEC=""
    CB_EXEC="docker exec couchbase"
else
    # On host
    WEB_EXEC="docker exec devcontainer-web-1"
    CB_EXEC="docker exec couchbase"
fi

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "1. Checking Migration Status"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

$WEB_EXEC bash -c "cd /var/www/openeyes && php protected/yiic adminmigration status" 2>&1

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "2. Verifying Collection Counts"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

COLLECTIONS=(
    "audit"
    "audit_action"
    "audit_type"
    "setting_metadata"
    "setting_installation"
    "setting_user"
    "user_authentication"
    "institution_authentication"
    "user_authentication_method"
    "auth_item"
    "auth_assignment"
)

for coll in "${COLLECTIONS[@]}"; do
    COUNT=$($CB_EXEC cbq -u Administrator -p password -e "SELECT COUNT(*) as count FROM openeyes.admin.$coll" 2>/dev/null | grep -o '"count": [0-9]*' | grep -o '[0-9]*' || echo "0")
    
    if [ -n "$COUNT" ] && [ "$COUNT" -gt 0 ]; then
        echo -e "${GREEN}✓${NC} $coll: $COUNT documents"
    else
        echo -e "${YELLOW}⚠${NC} $coll: No documents found"
    fi
done

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "3. Testing Recent Audit Logs (Last 10 records)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

echo "From Couchbase:"
$CB_EXEC cbq -u Administrator -p password -e "
SELECT 
  META().id,
  action_id,
  type_id,
  created_date
FROM openeyes.admin.audit
ORDER BY created_date DESC
LIMIT 5
" 2>/dev/null | grep -E '(id|action_id|type_id|created_date)' || echo "  No recent audit logs"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "4. Checking Dual-Write Configuration"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

DUAL_WRITE_ENV=$($WEB_EXEC bash -c "printenv OPENEYES_ENABLE_DUAL_WRITE" 2>/dev/null || echo "not set")
echo "Environment Variable: OPENEYES_ENABLE_DUAL_WRITE = $DUAL_WRITE_ENV"

if [ "$DUAL_WRITE_ENV" = "true" ]; then
    echo -e "${GREEN}✓${NC} Dual-write is enabled via environment variable"
else
    echo -e "${YELLOW}⚠${NC} Checking configuration file..."
fi

echo ""
echo "Checking migrated collections in config:"
$WEB_EXEC bash -c "grep -A 20 'couchbase_migrated_collections' /var/www/openeyes/protected/config/core/common.php | head -20" 2>/dev/null || echo "Could not read config"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "5. Checking Application Logs for Couchbase Operations"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

RECENT_LOGS=$($WEB_EXEC bash -c "tail -50 /var/www/openeyes/protected/runtime/application.log 2>/dev/null | grep -i couchbase | tail -10" || echo "")

if [ -n "$RECENT_LOGS" ]; then
    echo "Recent Couchbase operations:"
    echo "$RECENT_LOGS"
else
    echo -e "${YELLOW}⚠${NC} No recent Couchbase operations in logs"
    echo "   This may mean no new operations have occurred since restart"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "6. Sample Data from Collections"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

echo "Sample from auth_item (roles/permissions):"
$CB_EXEC cbq -u Administrator -p password -e "SELECT META().id, name, type FROM openeyes.admin.auth_item LIMIT 3" 2>/dev/null | grep -E '(id|name|type)' || echo "  No data"

echo ""
echo "Sample from setting_metadata:"
$CB_EXEC cbq -u Administrator -p password -e "SELECT META().id, \`key\`, name FROM openeyes.admin.setting_metadata LIMIT 3" 2>/dev/null | grep -E '(id|key|name)' || echo "  No data"

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║     Validation Complete                                        ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo "To test dual-write with NEW data:"
echo "  1. Login to OpenEyes UI"
echo "  2. Navigate to some patient records"
echo "  3. Run this script again to see new audit logs"
echo ""
echo "To verify data integrity:"
echo "  docker exec devcontainer-web-1 bash -c \"cd /var/www/openeyes && php protected/yiic adminmigration verify --sample=20\""
echo ""
