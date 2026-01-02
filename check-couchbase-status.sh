#!/bin/bash
# Check Couchbase container download/startup status

echo "========================================"
echo "Couchbase Container Status Checker"
echo "========================================"
echo ""

echo "1. Checking if Couchbase image is downloaded..."
if docker images | grep -q "couchbase.*enterprise-7.2.0"; then
    echo "   ✓ Image downloaded!"
    docker images | grep couchbase
else
    echo "   ✗ Image not downloaded yet"
fi

echo ""
echo "2. Checking if container exists..."
if docker ps -a | grep -q "openeyes-couchbase"; then
    echo "   ✓ Container exists"
    docker ps -a | grep couchbase
else
    echo "   ✗ Container not created yet"
fi

echo ""
echo "3. Checking if container is running..."
if docker ps | grep -q "openeyes-couchbase"; then
    echo "   ✓ Container is running!"
    docker ps | grep couchbase
else
    echo "   ✗ Container not running"
fi

echo ""
echo "4. Checking if Couchbase is accessible..."
if curl -s http://localhost:8091/pools > /dev/null 2>&1; then
    echo "   ✓ Couchbase is accessible at http://localhost:8091"
else
    echo "   ✗ Couchbase not accessible yet"
fi

echo ""
echo "========================================"
echo "Summary"
echo "========================================"

if curl -s http://localhost:8091/pools > /dev/null 2>&1; then
    echo "Status: ✓ READY! Run ./RUN-THIS-WHEN-READY.sh"
elif docker ps | grep -q "openeyes-couchbase"; then
    echo "Status: ⏳ Container running, initializing..."
    echo "Action: Wait 30 seconds, then re-run this script"
elif docker ps -a | grep -q "openeyes-couchbase"; then
    echo "Status: ⚠️ Container exists but not running"
    echo "Action: docker compose -f docker-compose.couchbase.yml start"
elif docker images | grep -q "couchbase.*enterprise-7.2.0"; then
    echo "Status: ⏳ Image downloaded, container not started"
    echo "Action: docker compose -f docker-compose.couchbase.yml up -d"
else
    echo "Status: ⏳ Image not downloaded"
    echo "Action: See below to start download"
fi

echo ""
echo "Quick Commands:"
echo "  Check status:   ./check-couchbase-status.sh"
echo "  Start download: docker compose -f docker-compose.couchbase.yml pull"
echo "  Start container: docker compose -f docker-compose.couchbase.yml up -d"
echo "  View logs:      docker logs openeyes-couchbase -f"
echo ""
