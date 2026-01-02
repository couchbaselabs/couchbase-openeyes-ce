#!/bin/bash
# Wait for Couchbase container to be ready, then run automated setup

echo "======================================================================="
echo "Phase 5: Waiting for Couchbase Container"
echo "======================================================================="
echo ""

# Check if container is downloading/extracting
echo "Checking Docker status..."
if docker compose -f docker-compose.couchbase.yml ps 2>&1 | grep -q "couchbase"; then
    echo "Container process exists"
else
    echo "Starting Couchbase container..."
    docker compose -f docker-compose.couchbase.yml up -d > /dev/null 2>&1 &
fi

echo ""
echo "Waiting for Couchbase to be accessible..."
echo "(This may take 2-5 minutes for first-time download and startup)"
echo ""

MAX_WAIT=300  # 5 minutes
WAIT_TIME=0

while [ $WAIT_TIME -lt $MAX_WAIT ]; do
    # Check if Couchbase API is accessible
    if curl -s http://localhost:8091/pools > /dev/null 2>&1; then
        echo ""
        echo "✓ Couchbase is ready!"
        echo ""
        echo "Running automated setup..."
        echo ""
        
        # Run setup script
        ./protected/scripts/couchbase/setup-phase5.sh
        exit 0
    fi
    
    # Show progress
    echo -n "."
    sleep 5
    WAIT_TIME=$((WAIT_TIME + 5))
    
    # Show status every 30 seconds
    if [ $((WAIT_TIME % 30)) -eq 0 ]; then
        echo ""
        echo "Still waiting... (${WAIT_TIME}s elapsed)"
        
        # Check container status
        if docker ps | grep -q couchbase; then
            echo "  Container is running, waiting for Couchbase to initialize..."
        else
            echo "  Container not yet running, still downloading/extracting..."
        fi
    fi
done

echo ""
echo "Timeout waiting for Couchbase (${MAX_WAIT}s)"
echo ""
echo "Please check:"
echo "  docker ps | grep couchbase"
echo "  docker logs openeyes-couchbase"
echo ""
echo "Once ready, run:"
echo "  ./protected/scripts/couchbase/setup-phase5.sh"
echo ""
