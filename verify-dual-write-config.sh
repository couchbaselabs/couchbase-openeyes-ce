#!/bin/bash
# Verification script for dual-write configuration

echo "========================================="
echo "  Dual-Write Configuration Verification"
echo "========================================="
echo ""

echo "1. Checking environment variables in web container..."
docker exec devcontainer-web-1 env | grep -E "OPENEYES_ENABLE|COUCHBASE_HOST" | sort
echo ""

echo "2. Checking Couchbase container status..."
if docker ps | grep -q couchbase; then
    echo "✓ Couchbase container is running"
    COUCHBASE_ID=$(docker ps | grep couchbase | awk '{print $1}')
    echo "  Container ID: $COUCHBASE_ID"
else
    echo "✗ Couchbase container is NOT running"
fi
echo ""

echo "3. Testing network connectivity..."
if docker exec devcontainer-web-1 ping -c 1 host.docker.internal > /dev/null 2>&1; then
    echo "✓ Web container can reach host.docker.internal"
else
    echo "✗ Web container CANNOT reach host.docker.internal"
fi
echo ""

echo "4. Checking PHP Couchbase extension..."
if docker exec devcontainer-web-1 php -m | grep -q couchbase; then
    echo "✓ Couchbase PHP extension is installed"
    docker exec devcontainer-web-1 php -i | grep "couchbase support" | head -1
else
    echo "✗ Couchbase PHP extension is NOT installed"
fi
echo ""

echo "5. Checking migrated collections in database..."
EVENT_COUNT=$(docker exec devcontainer-web-1 mysql -h db -u openeyes -popeneyes openeyes -se "SELECT COUNT(*) FROM event" 2>/dev/null)
echo "  Events in MariaDB: $EVENT_COUNT"
echo ""

echo "6. Summary:"
echo "  - If all checks pass, dual-write is properly configured"
echo "  - To test: Create an event via the web UI at http://localhost:7777"
echo "  - Verify in Couchbase: docker exec -it couchbase cbq"
echo ""
echo "========================================="
