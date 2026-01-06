#!/bin/bash
#
# droid-scope-mismatch-fix.sh
# Detects and fixes Couchbase scope mismatches between model definitions and actual data location
#
# Usage:
#   ./scripts/droid-scope-mismatch-fix.sh [options]
#
# Options:
#   -c, --check     Check only, don't fix (default)
#   -f, --fix       Auto-fix mismatches
#   -p, --parallel  Number of parallel workers (default: 4)
#   -v, --verbose   Verbose output
#   -h, --help      Show this help

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
COUCHBASE_CONTAINER="openeyes-couchbase"
COUCHBASE_USER="Administrator"
COUCHBASE_PASS="password"
BUCKET="openeyes"

# Default options
CHECK_ONLY=true
PARALLEL_WORKERS=4
VERBOSE=false
TEMP_DIR="/tmp/scope-mismatch-$$"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

usage() {
    head -20 "$0" | tail -18 | sed 's/^# //' | sed 's/^#//'
    exit 0
}

log() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

success() {
    echo -e "${GREEN}[OK]${NC} $1"
}

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -c|--check) CHECK_ONLY=true; shift ;;
        -f|--fix) CHECK_ONLY=false; shift ;;
        -p|--parallel) PARALLEL_WORKERS="$2"; shift 2 ;;
        -v|--verbose) VERBOSE=true; shift ;;
        -h|--help) usage ;;
        *) error "Unknown option: $1"; usage ;;
    esac
done

mkdir -p "$TEMP_DIR"

# Step 1: Get all collections and their scopes from Couchbase
log "Fetching Couchbase collections..."

docker exec "$COUCHBASE_CONTAINER" cbq -u "$COUCHBASE_USER" -p "$COUCHBASE_PASS" -s \
    "SELECT \`scope\`, name FROM system:keyspaces WHERE \`bucket\`='$BUCKET'" 2>/dev/null | \
    grep -E '"scope"|"name"' | \
    paste - - | \
    sed 's/.*"scope": "\([^"]*\)".*"name": "\([^"]*\)".*/\2:\1/' > "$TEMP_DIR/couchbase_collections.txt"

COLLECTION_COUNT=$(wc -l < "$TEMP_DIR/couchbase_collections.txt")
log "Found $COLLECTION_COUNT collections in Couchbase"

# Step 2: Find all PHP models with CouchbaseModelBridge trait
log "Finding PHP models with CouchbaseModelBridge..."

find "$PROJECT_ROOT/protected" -name "*.php" -type f | while read -r file; do
    if grep -q "use.*CouchbaseModelBridge" "$file" 2>/dev/null || \
       grep -q "CouchbaseModelBridge;" "$file" 2>/dev/null; then
        echo "$file"
    fi
done > "$TEMP_DIR/models_with_bridge.txt"

MODEL_COUNT=$(wc -l < "$TEMP_DIR/models_with_bridge.txt")
log "Found $MODEL_COUNT models with CouchbaseModelBridge"

# Step 3: Extract scope and collection info from each model
extract_model_info() {
    local file="$1"
    local basename=$(basename "$file" .php)
    
    # Extract tableName
    local table_name=$(grep -A3 "function tableName" "$file" 2>/dev/null | grep "return" | head -1 | sed "s/.*return ['\"]\\([^'\"]*\\)['\"].*/\\1/")
    
    # Extract couchbaseScope
    local scope=$(grep -A3 "function couchbaseScope" "$file" 2>/dev/null | grep "return" | head -1 | sed "s/.*return ['\"]\\([^'\"]*\\)['\"].*/\\1/")
    
    # Extract couchbaseCollection (usually same as tableName)
    local collection=$(grep -A3 "function couchbaseCollection" "$file" 2>/dev/null | grep "return" | head -1 | sed "s/.*return ['\"]\\([^'\"]*\\)['\"].*/\\1/")
    
    # If collection returns $this->tableName(), use table_name
    if [[ -z "$collection" ]] || [[ "$collection" == *"tableName"* ]]; then
        collection="$table_name"
    fi
    
    # Default scope if not found
    if [[ -z "$scope" ]]; then
        scope="clinical"  # Most common default
    fi
    
    if [[ -n "$table_name" ]]; then
        echo "$file|$table_name|$scope|$collection"
    fi
}

export -f extract_model_info

log "Extracting model configurations..."
cat "$TEMP_DIR/models_with_bridge.txt" | while read -r file; do
    extract_model_info "$file"
done > "$TEMP_DIR/model_configs.txt"

# Step 4: Check for mismatches
log "Checking for scope mismatches..."

check_mismatch() {
    local line="$1"
    local couchbase_collections="$2"
    
    IFS='|' read -r file table_name declared_scope collection <<< "$line"
    
    if [[ -z "$collection" ]]; then
        return
    fi
    
    # Find actual scope in Couchbase
    local actual_scope=$(grep "^${collection}:" "$couchbase_collections" | cut -d: -f2 | head -1)
    
    if [[ -z "$actual_scope" ]]; then
        # Collection doesn't exist - might be okay
        echo "MISSING|$file|$collection|$declared_scope|NOT_FOUND"
    elif [[ "$actual_scope" != "$declared_scope" ]]; then
        echo "MISMATCH|$file|$collection|$declared_scope|$actual_scope"
    else
        echo "OK|$file|$collection|$declared_scope|$actual_scope"
    fi
}

export -f check_mismatch

# Run checks
> "$TEMP_DIR/results.txt"
while read -r line; do
    check_mismatch "$line" "$TEMP_DIR/couchbase_collections.txt" >> "$TEMP_DIR/results.txt"
done < "$TEMP_DIR/model_configs.txt"

# Step 5: Report results
echo ""
echo "=========================================="
echo "         SCOPE MISMATCH REPORT"
echo "=========================================="
echo ""

MISMATCHES=$(grep "^MISMATCH" "$TEMP_DIR/results.txt" 2>/dev/null | wc -l)
MISSING=$(grep "^MISSING" "$TEMP_DIR/results.txt" 2>/dev/null | wc -l)
OK_COUNT=$(grep "^OK" "$TEMP_DIR/results.txt" 2>/dev/null | wc -l)

echo -e "${GREEN}OK:${NC} $OK_COUNT models correctly configured"
echo -e "${YELLOW}MISSING:${NC} $MISSING collections not found in Couchbase"
echo -e "${RED}MISMATCH:${NC} $MISMATCHES scope mismatches detected"
echo ""

if [[ "$MISMATCHES" -gt 0 ]]; then
    echo "MISMATCHED MODELS:"
    echo "------------------"
    grep "^MISMATCH" "$TEMP_DIR/results.txt" | while IFS='|' read -r status file collection declared actual; do
        rel_file=$(echo "$file" | sed "s|$PROJECT_ROOT/||")
        echo -e "  ${RED}$rel_file${NC}"
        echo "    Collection: $collection"
        echo "    Model says: $declared"
        echo "    Data is in: $actual"
        echo ""
    done
fi

# Step 6: Generate fixes or apply them
if [[ "$MISMATCHES" -gt 0 ]]; then
    echo ""
    
    if [[ "$CHECK_ONLY" == "true" ]]; then
        echo "=========================================="
        echo "         FIX COMMANDS"
        echo "=========================================="
        echo ""
        echo "Run with -f/--fix to auto-apply, or use these sed commands:"
        echo ""
        
        grep "^MISMATCH" "$TEMP_DIR/results.txt" | while IFS='|' read -r status file collection declared actual; do
            echo "# Fix $collection: $declared -> $actual"
            echo "sed -i \"s/return '$declared';/return '$actual';/\" \"$file\""
            echo ""
        done
        
        # Generate droid tasks file
        echo ""
        echo "=========================================="
        echo "    PARALLEL DROID TASKS FILE"
        echo "=========================================="
        
        TASKS_FILE="$TEMP_DIR/droid_tasks.txt"
        > "$TASKS_FILE"
        
        grep "^MISMATCH" "$TEMP_DIR/results.txt" | while IFS='|' read -r status file collection declared actual; do
            rel_file=$(echo "$file" | sed "s|$PROJECT_ROOT/||")
            echo "Fix scope mismatch in $rel_file: change couchbaseScope() from '$declared' to '$actual'" >> "$TASKS_FILE"
        done
        
        echo "Tasks file created: $TASKS_FILE"
        echo ""
        echo "To run with parallel droids:"
        echo "  cat $TASKS_FILE | xargs -P $PARALLEL_WORKERS -I {} droid \"{}\""
        
    else
        echo "=========================================="
        echo "         APPLYING FIXES"
        echo "=========================================="
        echo ""
        
        grep "^MISMATCH" "$TEMP_DIR/results.txt" | while IFS='|' read -r status file collection declared actual; do
            rel_file=$(echo "$file" | sed "s|$PROJECT_ROOT/||")
            log "Fixing $rel_file: $declared -> $actual"
            
            # Use sed to replace the scope
            if [[ "$(uname)" == "Darwin" ]]; then
                # macOS
                sed -i '' "s/return '$declared';/return '$actual';/" "$file"
            else
                # Linux
                sed -i "s/return '$declared';/return '$actual';/" "$file"
            fi
            
            success "Fixed $rel_file"
        done
        
        echo ""
        success "All $MISMATCHES mismatches fixed!"
        echo ""
        echo "Don't forget to reload Apache:"
        echo "  docker exec devcontainer-web-1 service apache2 reload"
    fi
fi

# Cleanup
if [[ "$VERBOSE" != "true" ]]; then
    rm -rf "$TEMP_DIR"
fi

echo ""
log "Done!"
