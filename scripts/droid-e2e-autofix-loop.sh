#!/bin/bash
set -euo pipefail

# Droid E2E Autofix Loop v2 - Test, Fix, Retest cycle with:
# - Admin page discovery (filters models with actual admin UI)
# - Multiple test accounts for parallel execution
# - Explicit feedback loop
#
# Usage: ./droid-e2e-autofix-loop.sh [options] [concurrency]

DEFAULT_CONCURRENCY=3
MAX_RETRIES=3
LOG_LEVEL="info"
LOG_FILE=""
USE_COLOR=1
FILTER_PATTERN=""
DRY_RUN=0
MAX_MODELS=0
BASE_URL="http://localhost:7777"
RERUN_FAILED=""  # Path to previous results CSV to rerun failed tests

START_TS=$(date +%Y%m%d_%H%M%S)
PROJECT_DIR="/Users/asahu/Desktop/untitled folder/openeyes"
PROTECTED_DIR="$PROJECT_DIR/protected"
LOG_DIR="$PROJECT_DIR/logs/e2e-autofix-${START_TS}"
RESULTS_CSV="$LOG_DIR/results.csv"
MODEL_LIST="$LOG_DIR/models.txt"
ADMIN_MODELS_LIST="$LOG_DIR/admin_models.txt"
PROGRESS_FILE=""
WATCHER_PID=""

mkdir -p "$LOG_DIR"

usage() {
  cat >&2 <<USAGE
Usage: $0 [options] [concurrency]

E2E Autofix Loop v2 - Test -> Fix -> Retest with admin page discovery

Options:
  -v, --verbose        Verbose (debug) logging
  -q, --quiet          Only errors
  --log-file <path>    Also write logs to file
  --no-color           Disable ANSI colors
  -f, --filter <pat>   Filter models by pattern (e.g., Allergy)
  -n, --limit <N>      Process only first N models (default: all)
  -r, --retries <N>    Max retry attempts per model (default: 3)
  --dry-run            Report issues only, don't apply fixes
  --skip-discovery     Skip admin page discovery (test all models)
  --rerun-failed <csv> Rerun only FAIL/PARTIAL models from previous results CSV
  -h, --help           Show this help

Examples:
  $0 --filter Allergy 2       # Test allergy models, 2 parallel
  $0 -n 10 -r 2 3             # Test 10 models, 2 retries, 3 parallel
  $0 -v --filter OphCi 2      # Verbose, test OphCi models
  $0 --rerun-failed /path/to/results.csv -r 5 3  # Rerun failed with 5 retries

Features:
  - Discovers models with admin pages (skips internal models)
  - Creates separate test accounts for each parallel droid
  - Explicit test -> fix -> retest feedback loop
  - Per-model logging for debugging
USAGE
}

_level_num() {
  case "${1:-info}" in
    error) echo 0;; warn) echo 1;; info) echo 2;; debug) echo 3;; *) echo 2;;
  esac
}

_ts() { date +%H:%M:%S; }

_maybe_color() {
  local code="$1"; shift || true
  if [[ "$USE_COLOR" -eq 1 && -t 2 ]]; then printf "\033[%sm" "$code"; fi
}

_color_reset() { [[ "$USE_COLOR" -eq 1 && -t 2 ]] && printf "\033[0m" || true; }

_log_base() {
  local level="$1"; shift
  local msg="$*"
  local want=$(_level_num "$LOG_LEVEL")
  local have=$(_level_num "$level")
  if [[ "$have" -le "$want" ]]; then
    local color=""; case "$level" in
      error) color=31;; warn) color=33;; info) color=36;; debug) color=90;;
    esac
    { _maybe_color "$color"; printf "%s [%s] %s" "$(_ts)" "$level" "$msg"; _color_reset; printf "\n"; } >&2
    if [[ -n "$LOG_FILE" ]]; then
      printf "%s [%s] %s\n" "$(_ts)" "$level" "$msg" >> "$LOG_FILE" || true
    fi
  fi
}

log_info()  { _log_base info  "$*"; }
log_warn()  { _log_base warn  "$*"; }
log_error() { _log_base error "$*"; }
log_debug() { _log_base debug "$*"; }

SKIP_DISCOVERY=0

# Parse args
CONCURRENCY="$DEFAULT_CONCURRENCY"
while [[ $# -gt 0 ]]; do
  case "$1" in
    -v|--verbose) LOG_LEVEL="debug"; shift ;;
    -q|--quiet)   LOG_LEVEL="error"; shift ;;
    --log-file)   LOG_FILE="${2:-}"; shift 2 ;;
    --no-color)   USE_COLOR=0; shift ;;
    -f|--filter)  FILTER_PATTERN="${2:-}"; shift 2 ;;
    -n|--limit)   MAX_MODELS="${2:-0}"; shift 2 ;;
    -r|--retries) MAX_RETRIES="${2:-3}"; shift 2 ;;
    --dry-run)    DRY_RUN=1; shift ;;
    --skip-discovery) SKIP_DISCOVERY=1; shift ;;
    --rerun-failed) RERUN_FAILED="${2:-}"; shift 2 ;;
    -h|--help)    usage; exit 0 ;;
    ''|*[!0-9]*)  log_error "Unknown argument: $1"; usage; exit 2 ;;
    *)            CONCURRENCY="$1"; shift ;;
  esac
done

command -v droid >/dev/null 2>&1 || { log_error "'droid' CLI not found"; exit 1; }

# ============================================================
# STEP 1: Discover models with admin pages (or rerun failed)
# ============================================================

# Handle --rerun-failed option
if [[ -n "$RERUN_FAILED" ]]; then
  if [[ ! -f "$RERUN_FAILED" ]]; then
    log_error "Rerun CSV not found: $RERUN_FAILED"
    exit 1
  fi
  
  log_info "=== Phase 1: Rerunning FAIL/PARTIAL models from previous run ==="
  log_info "Source: $RERUN_FAILED"
  
  # Extract failed/partial models from previous CSV
  # CSV format: model_name,model_path,table_name,admin_url,account_used,attempt,test_result,issues,fixes,final_status
  # Note: Also handles malformed CSVs where final_status might not be in column 10
  > "$ADMIN_MODELS_LIST"
  
  # Look for rows that DON'T have PASS or SKIP as their final status
  # Also check if any field contains FAIL, PARTIAL, or error indicators
  awk -F, 'NR>1 {
    line = $0
    # Check if this row looks like a failure (not ending with PASS or SKIP)
    is_pass = (line ~ /"PASS"$/ || line ~ /,PASS$/ || $10 ~ /PASS/)
    is_skip = (line ~ /"SKIP"$/ || line ~ /,SKIP$/ || $10 ~ /SKIP/)
    
    if (!is_pass && !is_skip) {
      gsub(/"/, "", $2);  # model_path
      gsub(/"/, "", $4);  # admin_url
      if ($2 != "" && $4 != "") {
        print "'"$PROTECTED_DIR"'/" $2 "|" $4
      }
    }
  }' "$RERUN_FAILED" >> "$ADMIN_MODELS_LIST"
  
  FAILED_COUNT=$(wc -l < "$ADMIN_MODELS_LIST" | tr -d ' ')
  log_info "Found $FAILED_COUNT failed/partial models to rerun"
  
  if [[ "$FAILED_COUNT" -eq 0 ]]; then
    log_info "No failed models to rerun - all passed!"
    exit 0
  fi
  
  # Skip the normal discovery
  SKIP_DISCOVERY=1
fi

# Skip discovery if we already have models from --rerun-failed
if [[ -z "$RERUN_FAILED" ]]; then

log_info "=== Phase 1: Discovering models with admin pages ==="

# Find all admin controller files and extract model references
ADMIN_CONTROLLERS=$(find "$PROTECTED_DIR" \( -path "*/controllers/*Admin*Controller.php" -o -name "AdminController.php" \) 2>/dev/null)

# Known admin page mappings (model -> admin URL pattern)
# Format: MODEL_CLASS|ADMIN_URL_PATTERN
cat > "$LOG_DIR/known_admin_pages.txt" << 'ADMIN_PAGES'
OphCiExaminationAllergy|/OphCiExamination/admin/Allergies
OphCiExaminationAllergyReaction|/OphCiExamination/admin/AllergyReactions
OphCiExaminationRisk|/OphCiExamination/oeadmin/Risks/list
OphCiExamination_Workflow|/OphCiExamination/admin/Workflows
OphCiExamination_ElementSet|/OphCiExamination/admin/ElementSets
OphCiExamination_Instrument|/OphCiExamination/admin/viewIOPInstruments
OphCiExamination_VisualAcuityUnit|/OphCiExamination/admin/VisualAcuityUnits
OphCiExamination_ColourVision_Method|/OphCiExamination/admin/ColourVisionMethods
StereoAcuity_Method|/OphCiExamination/admin/StereoAcuityMethods
OphCiExamination_ClinicOutcome_Status|/OphCiExamination/admin/ClinicOutcomeStatus
OphCiExamination_ClinicProcedure|/OphCiExamination/admin/ClinicProcedures
AdviceLeaflet|/OphCiExamination/admin/AdviceLeaflets
AdviceLeafletCategory|/OphCiExamination/admin/AdviceLeafletCategories
OphCiExamination_Dilation_Drugs|/OphCiExamination/admin/DilationDrugs
FamilyHistoryCondition|/OphCiExamination/admin/FamilyHistoryConditions
FamilyHistoryRelative|/OphCiExamination/admin/FamilyHistoryRelatives
HistoryMedicationsStopReason|/OphCiExamination/admin/HistoryMedicationsStopReasons
OphCiExamination_Comorbidities_Item|/OphCiExamination/admin/ComorbidityItems
SocialHistoryOccupation|/OphCiExamination/admin/SocialHistoryOccupations
SocialHistoryDrivingStatus|/OphCiExamination/admin/SocialHistoryDrivingStatuses
OphCiExamination_PupillaryAbnormalities_Abnormality|/OphCiExamination/admin/PupillaryAbnormalities
OphCiExamination_PostOpComplications|/OphCiExamination/admin/PostOpComplications
OphCiExamination_Safeguarding_Concern|/OphCiExamination/admin/SafeguardingConcerns
OphCiExamination_Safeguarding_Outcome|/OphCiExamination/admin/SafeguardingOutcomes
OphCiExamination_Refraction_Type|/OphCiExamination/admin/RefractionTypes
CorrectionType|/OphCiExamination/admin/CorrectionTypes
OphTrOperationnote_PostopDrug|/OphTrOperationnote/admin/PostopDrugs
OphTrOperationnote_Template|/OphTrOperationnote/admin/Templates
OphTrOperationnote_CataractComplication|/OphTrOperationnote/admin/CataractComplications
OphTrOperationnote_IOLType|/OphTrOperationnote/admin/IOLTypes
OphTrOperationbooking_Operation_Theatre|/OphTrOperationbooking/admin/viewTheatres
OphTrOperationbooking_Operation_Ward|/OphTrOperationbooking/admin/viewWards
OphTrOperationbooking_Operation_Sequence|/OphTrOperationbooking/admin/viewSequences
OphTrLaser_LaserProcedure|/OphTrLaser/admin/viewProcedures
OphTrLaser_Site_Laser|/OphTrLaser/admin/viewLasers
OphInBiometry_LensType_Lens|/OphInBiometry/admin/lensTypes
OphDrPrescription_DispenseCondition|/OphDrPrescription/DispenseCondition/list
OphDrPrescription_DispenseLocation|/OphDrPrescription/DispenseLocation/list
LetterMacro|/OphCoCorrespondence/admin/letterMacros
LetterStringGroup|/OphCoCorrespondence/admin/letterStringGroups
OphCoMessaging_Message_MessageType|/OphCoMessaging/admin/messageTypes
Mailbox|/OphCoMessaging/admin/mailboxes
ADMIN_PAGES

# Extract model names that have known admin pages
ADMIN_MODEL_NAMES=$(cut -d'|' -f1 "$LOG_DIR/known_admin_pages.txt")

# Find all models with CouchbaseModelBridge
grep -rl "CouchbaseModelBridge" "$PROTECTED_DIR" --include="*.php" 2>/dev/null | \
    grep -E "/models/[^/]+\.php$" | \
    grep -v "/traits/\|/couchbase/\|Test\.php$" | \
    sort -u > "$MODEL_LIST.all"

# Filter to models with admin pages (unless --skip-discovery)
if [[ "$SKIP_DISCOVERY" -eq 0 ]]; then
  log_info "Filtering to models with known admin pages..."
  > "$ADMIN_MODELS_LIST"
  
  while IFS= read -r model_file; do
    model_name=$(basename "$model_file" .php)
    # Check if this model has a known admin page
    if echo "$ADMIN_MODEL_NAMES" | grep -qx "$model_name"; then
      admin_url=$(grep "^${model_name}|" "$LOG_DIR/known_admin_pages.txt" | cut -d'|' -f2)
      echo "${model_file}|${admin_url}" >> "$ADMIN_MODELS_LIST"
    fi
  done < "$MODEL_LIST.all"
  
  log_info "Found $(wc -l < "$ADMIN_MODELS_LIST" | tr -d ' ') models with admin pages out of $(wc -l < "$MODEL_LIST.all" | tr -d ' ') total"
else
  log_info "Skipping admin page discovery (testing all models)"
  while IFS= read -r model_file; do
    echo "${model_file}|DISCOVER" >> "$ADMIN_MODELS_LIST"
  done < "$MODEL_LIST.all"
fi

fi  # End of: if [[ -z "$RERUN_FAILED" ]]

# Apply filter if specified
if [[ -n "$FILTER_PATTERN" ]]; then
  log_info "Filtering models by pattern: $FILTER_PATTERN"
  grep -i "$FILTER_PATTERN" "$ADMIN_MODELS_LIST" > "$ADMIN_MODELS_LIST.filtered" || true
  mv "$ADMIN_MODELS_LIST.filtered" "$ADMIN_MODELS_LIST"
fi

# Apply limit if specified  
if [[ "$MAX_MODELS" -gt 0 ]]; then
  log_info "Limiting to first $MAX_MODELS models"
  head -n "$MAX_MODELS" "$ADMIN_MODELS_LIST" > "$ADMIN_MODELS_LIST.limited"
  mv "$ADMIN_MODELS_LIST.limited" "$ADMIN_MODELS_LIST"
fi

TOTAL_MODELS=$(wc -l < "$ADMIN_MODELS_LIST" | tr -d ' ')
[[ "$TOTAL_MODELS" -eq 0 ]] && { log_error "No models found with admin pages"; exit 1; }

# ============================================================
# STEP 2: Setup test accounts for parallel execution
# ============================================================
log_info "=== Phase 2: Setting up test accounts ==="

# Generate account info for each parallel slot
# Account format: droid_test_N / Droid@Test123!
ACCOUNTS_FILE="$LOG_DIR/test_accounts.txt"
ACCOUNT_SETUP_SCRIPT="$LOG_DIR/setup_accounts.php"
> "$ACCOUNTS_FILE"

# Password must meet requirements: upper, lower, number, special char
TEST_PASSWORD="Droid@Test123!"

for i in $(seq 1 "$CONCURRENCY"); do
  echo "droid_test_${i}|${TEST_PASSWORD}|Droid|Tester${i}" >> "$ACCOUNTS_FILE"
done

log_info "Generated $CONCURRENCY test account configurations"

# Create PHP script to setup test accounts
cat > "$ACCOUNT_SETUP_SCRIPT" << 'PHPSCRIPT'
<?php
/**
 * Script to create test accounts for E2E parallel testing
 * Run with: php setup_accounts.php <accounts_file>
 */

// Bootstrap Yii
$yiic = dirname(__FILE__) . '/../../protected/yiic.php';
if (!file_exists($yiic)) {
    // Try alternative path
    $yiic = '/Users/asahu/Desktop/untitled folder/openeyes/protected/yiic.php';
}

// Get the config
$config = dirname(__FILE__) . '/../../protected/config/main.php';
if (!file_exists($config)) {
    $config = '/Users/asahu/Desktop/untitled folder/openeyes/protected/config/main.php';
}

require_once(dirname(__FILE__) . '/../../protected/yii/framework/yii.php');
$app = Yii::createConsoleApplication($config);

// Read accounts file
$accountsFile = $argv[1] ?? '';
if (!file_exists($accountsFile)) {
    echo "ERROR: Accounts file not found: $accountsFile\n";
    exit(1);
}

$lines = file($accountsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$created = 0;
$existing = 0;
$errors = 0;

// Get default institution authentication
$defaultInstitutionAuth = InstitutionAuthentication::model()->find([
    'condition' => 'active = 1',
    'order' => 'id ASC'
]);

if (!$defaultInstitutionAuth) {
    echo "ERROR: No active institution authentication found\n";
    exit(1);
}

echo "Using institution_authentication_id: {$defaultInstitutionAuth->id}\n";

foreach ($lines as $line) {
    list($username, $password, $firstName, $lastName) = explode('|', $line);
    
    // Check if user already exists
    $existingAuth = UserAuthentication::model()->findByAttributes(['username' => $username]);
    if ($existingAuth) {
        echo "EXISTS: $username (user_id: {$existingAuth->user_id})\n";
        $existing++;
        continue;
    }
    
    $transaction = Yii::app()->db->beginTransaction();
    try {
        // Create User record first
        $user = new User();
        $user->first_name = $firstName;
        $user->last_name = $lastName;
        $user->email = "{$username}@test.openeyes.local";
        $user->active = 1;
        $user->global_firm_rights = 1;  // Full access
        $user->correspondence_sign_off_text = "$firstName $lastName";
        $user->title = 'Mr';
        $user->role = 'Droid Test User';
        
        if (!$user->save(false)) {
            throw new Exception("Failed to create user: " . print_r($user->getErrors(), true));
        }
        
        // Create UserAuthentication record
        $auth = new UserAuthentication();
        $auth->institution_authentication_id = $defaultInstitutionAuth->id;
        $auth->user_id = $user->id;
        $auth->username = $username;
        $auth->password = $password;
        $auth->password_repeat = $password;
        $auth->password_status = 'current';
        $auth->active = 1;
        
        if (!$auth->save(false)) {
            throw new Exception("Failed to create auth: " . print_r($auth->getErrors(), true));
        }
        
        // Assign admin role (AuthAssignment)
        $adminRole = Yii::app()->db->createCommand()
            ->select('name')
            ->from('authitem')
            ->where("name = 'admin' OR name = 'Admin'")
            ->queryScalar();
        
        if ($adminRole) {
            Yii::app()->db->createCommand()->insert('authassignment', [
                'itemname' => $adminRole,
                'userid' => $user->id,
            ]);
        }
        
        // Assign to default institution
        Yii::app()->db->createCommand()->insert('user_authentication_institution', [
            'user_authentication_id' => $auth->id,
            'institution_id' => $defaultInstitutionAuth->institution_id,
        ]);
        
        $transaction->commit();
        echo "CREATED: $username (user_id: {$user->id}, auth_id: {$auth->id})\n";
        $created++;
        
    } catch (Exception $e) {
        $transaction->rollback();
        echo "ERROR: $username - {$e->getMessage()}\n";
        $errors++;
    }
}

echo "\n=== Summary ===\n";
echo "Created: $created\n";
echo "Existing: $existing\n";
echo "Errors: $errors\n";
PHPSCRIPT

log_info "Creating test accounts in database..."

# Try to run the PHP script
if command -v php >/dev/null 2>&1; then
  cd "$PROJECT_DIR"
  php "$ACCOUNT_SETUP_SCRIPT" "$ACCOUNTS_FILE" 2>&1 | tee "$LOG_DIR/account_setup.log" || {
    log_warn "PHP account setup failed, will use admin/admin as fallback"
  }
else
  log_warn "PHP not found, accounts will be created by droids on first use"
fi

log_info "Test accounts ready (or will use admin/admin fallback)"

# ============================================================
# STEP 3: Initialize results and progress tracking
# ============================================================
echo "model_name,model_path,table_name,admin_url,account_used,attempt,test_result,issues,fixes,final_status" > "$RESULTS_CSV"

log_info "=== Phase 3: Running E2E Tests ==="
log_info "Models: $TOTAL_MODELS | Concurrency: $CONCURRENCY | Max Retries: $MAX_RETRIES"
log_info "Log Dir: $LOG_DIR"

cleanup() {
  [[ -n "${WATCHER_PID:-}" ]] && kill "$WATCHER_PID" 2>/dev/null || true
  [[ -n "${PROGRESS_FILE:-}" ]] && rm -f "$PROGRESS_FILE" || true
  rm -rf "$PROGRESS_FILE.lock" 2>/dev/null || true
}
trap cleanup EXIT

PROGRESS_FILE=$(mktemp)
echo "0 0 0 0" > "$PROGRESS_FILE"  # processed passed failed skipped

watch_progress() {
  while :; do
    read -r processed passed failed skipped < "$PROGRESS_FILE" 2>/dev/null || { processed=0; passed=0; failed=0; skipped=0; }
    log_info "Progress: ${processed}/${TOTAL_MODELS} | Passed: ${passed} | Failed: ${failed} | Skipped: ${skipped}"
    [[ "$processed" -ge "$TOTAL_MODELS" ]] && break
    sleep 15
  done
}
watch_progress &
WATCHER_PID=$!

# ============================================================
# CORE FUNCTION: Test-Fix-Retest Loop for a single model
# ============================================================
run_autofix_loop() {
  local model_entry="$1"
  local slot_id="$2"
  
  local model_file=$(echo "$model_entry" | cut -d'|' -f1)
  local known_admin_url=$(echo "$model_entry" | cut -d'|' -f2)
  local model_name=$(basename "$model_file" .php)
  local model_path="${model_file#$PROTECTED_DIR/}"
  local table_name=$(grep -oE "return\s*['\"][a-z_]+['\"]" "$model_file" 2>/dev/null | grep -oE "[a-z_]+" | tail -1 || echo "unknown")
  local model_log="$LOG_DIR/${model_name}.log"
  
  # Get account for this slot
  local account_line=$(sed -n "${slot_id}p" "$ACCOUNTS_FILE")
  local username=$(echo "$account_line" | cut -d'|' -f1)
  local password=$(echo "$account_line" | cut -d'|' -f2)
  local first_name=$(echo "$account_line" | cut -d'|' -f3)
  local last_name=$(echo "$account_line" | cut -d'|' -f4)
  
  local attempt=0
  local final_status="FAIL"
  local all_issues=""
  local all_fixes=""
  local admin_url="$known_admin_url"
  
  echo "[$(date +%H:%M:%S)] === Starting $model_name (slot $slot_id, account $username) ===" >> "$model_log"
  
  while [[ $attempt -lt $MAX_RETRIES ]]; do
    attempt=$((attempt + 1))
    echo "[$(date +%H:%M:%S)] Attempt $attempt/$MAX_RETRIES" >> "$model_log"
    
    # ---- STEP 1: TEST ----
    local test_ts=$(date +%s)
    local test_prompt="You are testing model: $model_name
File: $model_file
Table: $table_name
Admin URL (if known): $admin_url
Test Account: username=$username password=$password

## SETUP: Ensure Test Account Exists
Before testing, ensure the test account exists. If login fails with this account, 
you may need to use the default admin/admin account first to verify the system works.
For this test, try logging in with: $username / $password
If that fails, fall back to: admin / admin

## TASK: E2E TEST

1. Use Playwright to:
   - Navigate to $BASE_URL
   - Login with $username / $password (or admin/admin as fallback)
   - Select institution: OpenEyes Default Institution
   - Navigate to admin URL: $admin_url (or discover it if DISCOVER)
   - Click Add/Create button
   - Fill form with test data: 'E2E Test $model_name $test_ts'
   - Click Save
   - Refresh the page (Ctrl+R or navigate again)
   - Verify the test record appears in the list

2. Query Couchbase to verify data synced:
   curl -s http://localhost:8093/query/service -u Administrator:password --data-urlencode 'statement=SELECT COUNT(*) as cnt FROM openeyes.reference.\`$table_name\` WHERE name LIKE \"%E2E Test%\"'
   (also try scopes: core, clinical, admin)

## OUTPUT FORMAT (single line, pipe-delimited):
TEST_RESULT|ISSUES_FOUND

Where:
- TEST_RESULT: PASS or FAIL or SKIP
- ISSUES_FOUND: Comma-separated list or NONE
  Possible: NO_ADMIN_PAGE, LOGIN_FAILED, RECORD_NOT_PERSISTED, COUCHBASE_NO_DATA, 
            COLLECTION_MISSING, SCOPE_MISMATCH, MISSING_HOOKS, NO_ADD_BUTTON

Examples:
PASS|NONE
FAIL|RECORD_NOT_PERSISTED,COUCHBASE_NO_DATA
SKIP|NO_ADMIN_PAGE
FAIL|COLLECTION_MISSING"

    local test_output=$(echo "$test_prompt" | timeout 240 droid exec --skip-permissions-unsafe 2>>"$model_log" || echo "FAIL|DROID_TIMEOUT")
    
    # Parse output - look for the result line
    local test_result=$(echo "$test_output" | grep -oE "^(PASS|FAIL|SKIP)" | head -1 || echo "FAIL")
    local issues=$(echo "$test_output" | grep -oE "\|[A-Z_,]+" | sed 's/|//' | head -1 || echo "UNKNOWN")
    
    # Also check for result pattern anywhere in output
    if [[ "$test_result" != "PASS" && "$test_result" != "SKIP" ]]; then
      test_result=$(echo "$test_output" | grep -oE "(PASS|FAIL|SKIP)\|" | head -1 | tr -d '|' || echo "FAIL")
    fi
    
    echo "[$(date +%H:%M:%S)] Test result: $test_result | Issues: $issues" >> "$model_log"
    echo "[$(date +%H:%M:%S)] Raw output (last 500 chars): ${test_output: -500}" >> "$model_log"
    
    # ---- STEP 2: CHECK ----
    if [[ "$test_result" == "PASS" ]]; then
      final_status="PASS"
      echo "[$(date +%H:%M:%S)] SUCCESS on attempt $attempt" >> "$model_log"
      break
    fi
    
    if [[ "$test_result" == "SKIP" || "$issues" == *"NO_ADMIN_PAGE"* ]]; then
      final_status="SKIP"
      echo "[$(date +%H:%M:%S)] SKIPPED - No admin page found" >> "$model_log"
      break
    fi
    
    all_issues="${all_issues:+$all_issues,}$issues"
    
    # ---- STEP 3: FIX ----
    if [[ "$DRY_RUN" -eq 1 ]]; then
      echo "[$(date +%H:%M:%S)] DRY RUN - skipping fixes" >> "$model_log"
      break
    fi
    
    if [[ $attempt -lt $MAX_RETRIES ]]; then
      echo "[$(date +%H:%M:%S)] Attempting fix..." >> "$model_log"
      
      local fix_prompt="Fix Couchbase integration for model: $model_name
File: $model_file  
Table: $table_name
Issues found: $issues

## APPLY THESE FIXES based on issues:

### If COLLECTION_MISSING:
Create the collection in Couchbase:
curl -X POST http://localhost:8091/pools/default/buckets/openeyes/scopes/reference/collections -u Administrator:password -d name=$table_name
sleep 2
curl -s http://localhost:8093/query/service -u Administrator:password --data-urlencode 'statement=CREATE PRIMARY INDEX idx_${table_name}_primary ON openeyes.reference.\`$table_name\`'

### If SCOPE_MISMATCH:
1. Check model's couchbaseScope() method
2. Check CouchbaseAdapter.php scopeMapping for this table
3. Edit model to return the correct scope that matches CouchbaseAdapter

### If MISSING_HOOKS or COUCHBASE_NO_DATA:
Add/fix hooks in the model file:
- Ensure afterSave() calls \$this->saveToCouchbase()
- Ensure afterDelete() calls \$this->deleteFromCouchbase()

### If RECORD_NOT_PERSISTED (but collection exists):
Check if the model is correctly configured with CouchbaseModelBridge trait.

## OUTPUT (single line):
FIXES_APPLIED

Examples:
CREATED_COLLECTION,ADDED_INDEX
FIXED_SCOPE
ADDED_HOOKS
NO_FIX_POSSIBLE"

      local fix_output=$(echo "$fix_prompt" | timeout 180 droid exec --skip-permissions-unsafe 2>>"$model_log" || echo "FIX_TIMEOUT")
      local fixes=$(echo "$fix_output" | grep -oE "[A-Z_,]+" | head -1 || echo "UNKNOWN")
      
      echo "[$(date +%H:%M:%S)] Fixes applied: $fixes" >> "$model_log"
      all_fixes="${all_fixes:+$all_fixes,}$fixes"
      
      sleep 3
    fi
  done
  
  # Determine final status
  if [[ "$final_status" != "PASS" && "$final_status" != "SKIP" ]]; then
    if [[ -n "$all_fixes" && "$all_fixes" != "NO_FIX_POSSIBLE" && "$all_fixes" != "UNKNOWN" && "$all_fixes" != "FIX_TIMEOUT" ]]; then
      final_status="PARTIAL"
    else
      final_status="FAIL"
    fi
  fi
  
  # Sanitize fields for CSV (replace commas with semicolons, remove quotes)
  all_issues=$(echo "$all_issues" | tr ',' ';' | tr -d '"')
  all_fixes=$(echo "$all_fixes" | tr ',' ';' | tr -d '"')
  
  # Record results
  echo "\"$model_name\",\"$model_path\",\"$table_name\",\"$admin_url\",\"$username\",$attempt,\"$test_result\",\"$all_issues\",\"$all_fixes\",\"$final_status\"" >> "$RESULTS_CSV"
  
  # Update progress (mkdir-based locking for macOS)
  local lockdir="$PROGRESS_FILE.lock"
  while ! mkdir "$lockdir" 2>/dev/null; do sleep 0.1; done
  read -r processed passed failed skipped < "$PROGRESS_FILE" 2>/dev/null || { processed=0; passed=0; failed=0; skipped=0; }
  processed=$((processed + 1))
  [[ "$final_status" == "PASS" ]] && passed=$((passed + 1))
  [[ "$final_status" == "FAIL" || "$final_status" == "PARTIAL" ]] && failed=$((failed + 1))
  [[ "$final_status" == "SKIP" ]] && skipped=$((skipped + 1))
  echo "$processed $passed $failed $skipped" > "$PROGRESS_FILE"
  rmdir "$lockdir" 2>/dev/null || true
  
  # Print status
  local status_color=32  # green
  [[ "$final_status" == "FAIL" || "$final_status" == "PARTIAL" ]] && status_color=31  # red
  [[ "$final_status" == "SKIP" ]] && status_color=33  # yellow
  
  printf "%s [info] %s: \033[%sm%s\033[0m (attempts: %d, account: %s)\n" \
    "$(_ts)" "$model_name" "$status_color" "$final_status" "$attempt" "$username" >&2
}

export -f run_autofix_loop _ts _level_num _log_base log_info log_warn log_error log_debug _maybe_color _color_reset
export LOG_LEVEL LOG_FILE LOG_DIR RESULTS_CSV PROGRESS_FILE PROTECTED_DIR DRY_RUN MAX_RETRIES USE_COLOR BASE_URL ACCOUNTS_FILE

# Run models in parallel, assigning slot IDs (1 to CONCURRENCY) round-robin
SLOT=0
while IFS= read -r model_entry; do
  SLOT=$(( (SLOT % CONCURRENCY) + 1 ))
  printf '%s\t%s\0' "$model_entry" "$SLOT"
done < "$ADMIN_MODELS_LIST" | xargs -0 -n 1 -P "$CONCURRENCY" bash -c '
  entry_slot="$1"
  entry=$(echo "$entry_slot" | cut -f1)
  slot=$(echo "$entry_slot" | cut -f2)
  run_autofix_loop "$entry" "$slot"
' _

# Wait for watcher
wait "$WATCHER_PID" 2>/dev/null || true

# ============================================================
# FINAL SUMMARY
# ============================================================
echo ""
log_info "==========================================="
log_info "         FINAL SUMMARY"
log_info "==========================================="

PASS_COUNT=$(awk -F, 'NR>1 && $10~/PASS/ {c++} END{print c+0}' "$RESULTS_CSV")
FAIL_COUNT=$(awk -F, 'NR>1 && $10~/FAIL/ {c++} END{print c+0}' "$RESULTS_CSV")
PARTIAL_COUNT=$(awk -F, 'NR>1 && $10~/PARTIAL/ {c++} END{print c+0}' "$RESULTS_CSV")
SKIP_COUNT=$(awk -F, 'NR>1 && $10~/SKIP/ {c++} END{print c+0}' "$RESULTS_CSV")

log_info "Total:    $TOTAL_MODELS"
log_info "Passed:   $PASS_COUNT"
log_info "Failed:   $FAIL_COUNT"
log_info "Partial:  $PARTIAL_COUNT"
log_info "Skipped:  $SKIP_COUNT"
log_info "==========================================="

if [[ "$PASS_COUNT" -gt 0 ]]; then
  log_info "Passed models:"
  awk -F, 'NR>1 && $10~/PASS/ { gsub(/"/, "", $1); print "  + " $1 }' "$RESULTS_CSV"
fi

if [[ "$FAIL_COUNT" -gt 0 ]]; then
  log_warn "Failed models:"
  awk -F, 'NR>1 && $10~/FAIL/ { gsub(/"/, "", $1); gsub(/"/, "", $8); print "  - " $1 ": " $8 }' "$RESULTS_CSV"
fi

if [[ "$PARTIAL_COUNT" -gt 0 ]]; then
  log_warn "Partial (fixes applied but not verified):"
  awk -F, 'NR>1 && $10~/PARTIAL/ { gsub(/"/, "", $1); gsub(/"/, "", $9); print "  ~ " $1 ": " $9 }' "$RESULTS_CSV"
fi

log_info ""
log_info "Results: $RESULTS_CSV"
log_info "Logs:    $LOG_DIR/"
