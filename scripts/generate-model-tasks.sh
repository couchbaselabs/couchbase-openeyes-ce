#!/bin/bash
#
# Generate Model Verification Tasks
# Creates a task file that can be used to spin up parallel droids
#
# Usage: ./generate-model-tasks.sh [--output FILE] [--filter PATTERN]
#

set -e

PROJECT_DIR="/Users/asahu/Desktop/untitled folder/openeyes"
PROTECTED_DIR="$PROJECT_DIR/protected"
OUTPUT_FILE="$PROJECT_DIR/model-verification-tasks.txt"
FILTER_PATTERN=""

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --output)
            OUTPUT_FILE="$2"
            shift 2
            ;;
        --filter)
            FILTER_PATTERN="$2"
            shift 2
            ;;
        --help)
            echo "Usage: $0 [--output FILE] [--filter PATTERN]"
            exit 0
            ;;
        *)
            shift
            ;;
    esac
done

echo "Generating model verification tasks..."
echo ""

# Find all models with CouchbaseModelBridge
MODELS=$(grep -rl "use.*CouchbaseModelBridge\|CouchbaseModelBridge" "$PROTECTED_DIR" --include="*.php" 2>/dev/null | \
    grep -E "/models/[^/]+\.php$" | \
    grep -v "/traits/" | \
    grep -v "/couchbase/" | \
    grep -v "Test\.php$" | \
    sort -u)

# Apply filter
if [ -n "$FILTER_PATTERN" ]; then
    MODELS=$(echo "$MODELS" | grep -i "$FILTER_PATTERN" || true)
fi

# Count models
TOTAL=$(echo "$MODELS" | grep -c . || echo 0)
echo "Found $TOTAL models with CouchbaseModelBridge"
echo ""

# Generate task file
echo "# Model Verification Tasks - Generated $(date)" > "$OUTPUT_FILE"
echo "# Total models: $TOTAL" >> "$OUTPUT_FILE"
echo "#" >> "$OUTPUT_FILE"
echo "# Each line is a task for a droid to verify a model's Couchbase functionality" >> "$OUTPUT_FILE"
echo "# Format: MODEL_NAME|MODEL_PATH|TABLE_NAME" >> "$OUTPUT_FILE"
echo "#" >> "$OUTPUT_FILE"

echo "$MODELS" | while read -r model_file; do
    if [ -z "$model_file" ]; then
        continue
    fi
    
    MODEL_NAME=$(basename "$model_file" .php)
    REL_PATH="${model_file#$PROTECTED_DIR/}"
    
    # Try to extract table name from file (macOS compatible)
    TABLE_NAME=$(grep -E "function tableName|return\s*['\"][a-z_]+['\"]" "$model_file" 2>/dev/null | grep -oE "['\"][a-z_]+['\"]" | head -1 | tr -d "'" | tr -d '"' || echo "unknown")
    
    echo "$MODEL_NAME|$REL_PATH|$TABLE_NAME" >> "$OUTPUT_FILE"
done

echo "Task file generated: $OUTPUT_FILE"
echo ""
echo "To verify a single model with droid:"
echo "  droid \"Verify Couchbase functionality for MODEL_NAME in protected/PATH\""
echo ""
echo "Sample commands for first 5 models:"
head -10 "$OUTPUT_FILE" | tail -5 | while IFS='|' read -r name path table; do
    echo "  droid \"Verify Couchbase for $name (table: $table) - check hooks, collection, CRUD\""
done
