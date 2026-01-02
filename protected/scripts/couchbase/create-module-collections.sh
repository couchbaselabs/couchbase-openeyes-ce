#!/bin/bash
# Create collections for module documents

CB_HOST="${CB_HOST:-localhost}"
CB_USER="${CB_USER:-Administrator}"
CB_PASS="${CB_PASS:-password}"
BUCKET="openeyes"

echo "Creating module collections in Couchbase..."

# Clinical collections (OphCiExamination)
echo "Creating clinical.examination collection..."
curl -s -X POST \
    "http://${CB_HOST}:8091/pools/default/buckets/${BUCKET}/scopes/clinical/collections" \
    -u ${CB_USER}:${CB_PASS} \
    -d name=examination || true

# Booking collections (OphTrOperationbooking)
echo "Creating booking collections..."
BOOKING_COLLECTIONS=("operation" "session" "whiteboard")
for COLL in "${BOOKING_COLLECTIONS[@]}"; do
    curl -s -X POST \
        "http://${CB_HOST}:8091/pools/default/buckets/${BUCKET}/scopes/booking/collections" \
        -u ${CB_USER}:${CB_PASS} \
        -d name=${COLL} || true
done

# Correspondence collections (OphCoCorrespondence)
echo "Creating correspondence collections..."
CORR_COLLECTIONS=("letter" "message" "document")
for COLL in "${CORR_COLLECTIONS[@]}"; do
    curl -s -X POST \
        "http://${CB_HOST}:8091/pools/default/buckets/${BUCKET}/scopes/correspondence/collections" \
        -u ${CB_USER}:${CB_PASS} \
        -d name=${COLL} || true
done

echo "Module collections created successfully"
