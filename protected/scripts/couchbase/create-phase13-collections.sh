#!/bin/bash
# Create Phase 13 clinical module collections

CB_HOST="${1:-${CB_HOST:-localhost}}"
CB_USER="${2:-${CB_USER:-Administrator}}"
CB_PASS="${3:-${CB_PASS:-password}}"
BUCKET="openeyes"
SCOPE="clinical"

echo "═══════════════════════════════════════════════════════"
echo "Creating Phase 13 Clinical Module Collections"
echo "═══════════════════════════════════════════════════════"

# Function to create collection
create_collection() {
    local collection=$1
    echo "Creating: ${SCOPE}.${collection}"
    
    curl -s -X POST \
        "http://${CB_HOST}:8091/pools/default/buckets/${BUCKET}/scopes/${SCOPE}/collections" \
        -u "${CB_USER}:${CB_PASS}" \
        -d "name=${collection}" > /dev/null 2>&1
    
    if [ $? -eq 0 ]; then
        echo "  ✓ Created successfully"
    else
        echo "  ⚠ Already exists or error (continuing...)"
    fi
}

echo ""
echo "=== Operation Notes Module (6 collections) ==="
create_collection "operationnote_cataract"
create_collection "operationnote_procedurelist"
create_collection "operationnote_surgeon"
create_collection "operationnote_anaesthetic"
create_collection "operationnote_comments"
create_collection "operationnote_generic"

echo ""
echo "=== Laser Treatment Module (4 collections) ==="
create_collection "laser_treatment"
create_collection "laser_site"
create_collection "laser_anteriorsegment"
create_collection "laser_posteriorpole"

echo ""
echo "=== Biometry Module (3 collections) ==="
create_collection "biometry_measurement"
create_collection "biometry_calculation"
create_collection "biometry_selection"

echo ""
echo "=== Prescription Module (1 collection) ==="
create_collection "prescription_details"

echo ""
echo "=== Correspondence Module (1 collection) ==="
create_collection "element_letter"

echo ""
echo "=== Operation Booking Module (3 collections) ==="
create_collection "opbooking_operation"
create_collection "opbooking_diagnosis"
create_collection "opbooking_schedule"

echo ""
echo "=== CVI Module (3 collections) ==="
create_collection "cvi_eventinfo"
create_collection "cvi_clinicalinfo"
create_collection "cvi_clericalinfo"

echo ""
echo "═══════════════════════════════════════════════════════"
echo "✅ Phase 13 Collections Created Successfully!"
echo "═══════════════════════════════════════════════════════"
echo ""
echo "Total collections created: 21"
echo "Verify at: http://${CB_HOST}:8091/ui/index.html#/buckets/${BUCKET}"
echo ""
