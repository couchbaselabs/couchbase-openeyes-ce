#!/bin/bash
# Phase 5: One-Command Setup
# Run this script once Couchbase container finishes downloading

echo "=========================================="
echo "Phase 5: Automated Setup Starting..."
echo "=========================================="
echo ""

cd /Users/asahu/Desktop/OpenEyes/openeyes
./protected/scripts/couchbase/wait-and-setup.sh

echo ""
echo "=========================================="
echo "Setup complete! Running test sync..."
echo "=========================================="
echo ""

# Test sync with 5 examinations
php protected/yiic.php couchbasemodulesync sync \
  --module=OphCiExamination \
  --from=1 \
  --to=5 \
  --verbose

echo ""
echo "=========================================="
echo "Verifying sync..."
echo "=========================================="
echo ""

php protected/yiic.php couchbasemodulesync verify \
  --module=OphCiExamination

echo ""
echo "=========================================="
echo "✓ Phase 5 Setup & Testing Complete!"
echo "=========================================="
echo ""
echo "View data at: http://localhost:8091"
echo "  Username: Administrator"
echo "  Password: password"
echo ""
echo "Next: See PHASE-05-READY.md for more options"
echo ""
