#!/usr/bin/env bash
set -euo pipefail

# Require bash 4+ for associative arrays, or use workaround
if [[ "${BASH_VERSINFO[0]}" -lt 4 ]]; then
  # macOS ships with bash 3.x, use simple variables instead
  USE_ASSOC_ARRAYS=0
else
  USE_ASSOC_ARRAYS=1
fi

# ============================================================
# Droid E2E Spot Check Script
# Browser-based testing with auto-fix and comprehensive reporting
# ============================================================

VERSION="1.0.0"
START_TS=$(date +%Y%m%d_%H%M%S)
START_EPOCH=$(date +%s)

# Configuration
PROJECT_DIR="/Users/asahu/Desktop/untitled folder/openeyes"
LOG_DIR="$PROJECT_DIR/logs/spot-check-${START_TS}"
RESULTS_CSV="$LOG_DIR/results.csv"
REPORT_MD="$LOG_DIR/report.md"
SUMMARY_JSON="$LOG_DIR/summary.json"

BASE_URL="http://localhost:7777"
USERNAME="admin"
PASSWORD="admin"
INSTITUTION="OpenEyes Default Institution"
MAX_RETRIES=3
DRY_RUN=0
PARALLEL=1
MODULE_FILTER=""
TEST_FILTER=""
CLEANUP=0

mkdir -p "$LOG_DIR"

# ============================================================
# Test Case Definitions
# Format: test_id|module|url|description|test_type
# test_type: crud (create/read/update), view, search
# ============================================================
declare -a TEST_CASES=(
  # Patient Module
  "patient_search|patient|/patient/search|Patient Search|search"
  "patient_summary|patient|/patient/summary/13|Patient Summary View|view"
  
  # Admin - OphCiExamination Core (using correct URLs from admin menu config)
  "admin_allergies|admin|/OphCiExamination/admin/Allergies/index|Allergies Admin|crud"
  "admin_allergy_reactions|admin|/OphCiExamination/admin/AllergyReactions/index|Allergy Reactions Admin|crud"
  "admin_risks|admin|/OphCiExamination/risksAdmin/list|Risks Admin|crud"
  "admin_family_history|admin|/OphCiExamination/admin/FamilyHistory|Family History Admin|view"
  "admin_social_history|admin|/OphCiExamination/admin/SocialHistory|Social History Admin|view"
  "admin_stop_reasons|admin|/OphCiExamination/admin/MedicationStopReason/index|Medication Stop Reasons|crud"
  "admin_clinic_outcomes|admin|/OphCiExamination/admin/manageClinicOutcomesStatus|Clinic Outcome Status|crud"
  "admin_post_op_complications|admin|/OphCiExamination/admin/postOpComplications|Post-Op Complications|crud"
  
  # Admin - Social History Sub-pages (CRUD)
  "admin_driving_status|admin|/OphCiExamination/admin/socialHistoryDrivingStatus|Driving Status|crud"
  "admin_smoking_status|admin|/OphCiExamination/admin/socialHistorySmokingStatus|Smoking Status|crud"
  "admin_occupation|admin|/OphCiExamination/admin/socialHistoryOccupation|Employment/Occupation|crud"
  "admin_accommodation|admin|/OphCiExamination/admin/socialHistoryAccommodation|Accommodation|crud"
  
  # Admin - Visual Acuity
  "admin_va_fixations|admin|/OphCiExamination/admin/VisualAcuityFixations|Visual Acuity Fixations|crud"
  "admin_va_occluders|admin|/OphCiExamination/admin/VisualAcuityOccluders|Visual Acuity Occluders|crud"
  "admin_va_sources|admin|/OphCiExamination/admin/VisualAcuitySources|Visual Acuity Sources|crud"
  
  # Admin - Colour Vision
  "admin_colour_vision_methods|admin|/OphCiExamination/admin/ColourVisionMethods|Colour Vision Methods|crud"
  "admin_colour_vision_values|admin|/OphCiExamination/admin/ColourVisionValues|Colour Vision Values|crud"
  
  # Admin - Sensory Function
  "admin_sensory_types|admin|/OphCiExamination/admin/SensoryFunctionEntryTypes|Sensory Function Test Types|crud"
  "admin_sensory_distances|admin|/OphCiExamination/admin/SensoryFunctionDistances|Sensory Function Distances|crud"
  "admin_sensory_results|admin|/OphCiExamination/admin/SensoryFunctionResults|Sensory Function Results|crud"
  
  # Admin - Cover and Prism
  "admin_cover_distance|admin|/OphCiExamination/admin/CoverAndPrismCoverDistance|Cover And Prism Distance|crud"
  "admin_cover_h_prism|admin|/OphCiExamination/admin/CoverAndPrismCoverHorizontalPrism|Cover Horizontal Prism|crud"
  "admin_cover_v_prism|admin|/OphCiExamination/admin/CoverAndPrismCoverVerticalPrism|Cover Vertical Prism|crud"
  
  # Admin - Synoptophore
  "admin_synoptophore_direction|admin|/OphCiExamination/admin/SynoptophoreDirection|Synoptophore Direction|crud"
  "admin_synoptophore_deviation|admin|/OphCiExamination/admin/SynoptophoreDeviation|Synoptophore Deviation|crud"
  
  # Admin - Strabismus Management
  "admin_strab_treatments|admin|/OphCiExamination/admin/StrabismusManagementTreatments|Strabismus Treatments|crud"
  "admin_strab_options|admin|/OphCiExamination/admin/StrabismusManagementTreatmentOptions|Strabismus Options|crud"
  "admin_strab_reasons|admin|/OphCiExamination/admin/StrabismusManagementReasons|Strabismus Reasons|crud"
  
  # Admin - Nine Positions
  "admin_nine_pos_movement|admin|/OphCiExamination/admin/NinePositionsMovement|Nine Positions Movement|crud"
  "admin_nine_pos_h_e_dev|admin|/OphCiExamination/admin/NinePositionsHorizontalEDeviation|Nine Positions H-E Deviation|crud"
  "admin_nine_pos_h_x_dev|admin|/OphCiExamination/admin/NinePositionsHorizontalXDeviation|Nine Positions H-X Deviation|crud"
  "admin_nine_pos_v_dev|admin|/OphCiExamination/admin/NinePositionsVerticalDeviation|Nine Positions V Deviation|crud"
  
  # Admin - Other Lookups
  "admin_correction_types|admin|/OphCiExamination/admin/CorrectionTypes|Correction Types|crud"
  "admin_refraction_type|admin|/OphCiExamination/admin/RefractionType|Refraction Type|crud"
  "admin_contrast_type|admin|/OphCiExamination/admin/ContrastSensitivityType|Contrast Sensitivity Type|crud"
  "admin_stereo_methods|admin|/OphCiExamination/admin/StereoAcuityMethods|Stereo Acuity Methods|crud"
  "admin_pupillary|admin|/OphCiExamination/admin/PupillaryAbnormalities/index|Pupillary Abnormalities|crud"
  
  # Admin - Clinical Management
  "admin_discharge_status|admin|/OphCiExamination/admin/manageDischargeStatuses|Discharge Statuses|crud"
  "admin_discharge_dest|admin|/OphCiExamination/admin/manageDischargeDestinations|Discharge Destinations|crud"
  "admin_glaucoma_status|admin|/OphCiExamination/admin/manageGlaucomaStatuses|Glaucoma Statuses|crud"
  "admin_visit_intervals|admin|/OphCiExamination/admin/manageVisitIntervals|Visit Intervals|crud"
  "admin_overall_periods|admin|/OphCiExamination/admin/manageOverallPeriods|Overall Periods|crud"
  "admin_target_iop|admin|/OphCiExamination/admin/manageTargetIOPs|Target IOP Values|crud"
  
  # Admin - Drops & Surgery
  "admin_drop_problems|admin|/OphCiExamination/admin/manageDropRelProbs|Drop-related Problems|crud"
  "admin_drops_options|admin|/OphCiExamination/admin/manageDrops|Drops Options|crud"
  "admin_surgery_options|admin|/OphCiExamination/admin/manageManagementSurgery|Surgery Management Options|crud"
  "admin_cataract_reasons|admin|/OphCiExamination/admin/primaryReasonForSurgery|Cataract Surgery Reasons|crud"
  
  # Admin - Advice & Leaflets
  "admin_advice_leaflets|admin|/OphCiExamination/admin/adviceLeaflets|Advice Leaflets|crud"
  "admin_advice_categories|admin|/OphCiExamination/admin/adviceLeafletCategories|Advice Leaflet Categories|crud"
  
  # Admin - Workflows & Macros
  "admin_workflows|admin|/OphCiExamination/admin/viewWorkflows|Examination Workflows|view"
  "admin_workflow_rules|admin|/OphCiExamination/admin/viewWorkflowRules|Workflow Rules|view"
  "admin_history_macros|admin|/OphCiExamination/admin/HistoryMacro/list|History Macros|view"
  
  # Admin - IOP & Dilation
  "admin_iop_instruments|admin|/OphCiExamination/admin/ViewIOPInstruments|IOP Instruments|crud"
  "admin_dilation_drugs|admin|/OphCiExamination/admin/Drug/dilationDrugs|Dilation Drugs|crud"
  
  # Admin - Correspondence
  "admin_letter_macros|admin|/OphCoCorrespondence/admin/letterMacros|Letter Macros|crud"
  
  # Admin - Injection Management
  "admin_inject_no_treat|admin|/OphCiExamination/admin/viewAllOphCiExamination_InjectionManagementComplex_NoTreatmentReason|Injection No Treatment Reasons|crud"
  
  # Admin - Follow-up
  "admin_followup_roles|admin|/OphCiExamination/admin/ClinicOutcomeRoles/index|Follow-up Roles|crud"
  
  # ========================================
  # PATIENT MODULE - Additional Tests
  # ========================================
  "patient_episodes|patient|/patient/episodes/13|Patient Episodes|view"
  "patient_create|patient|/patient/create|Create New Patient|view"
  
  # ========================================
  # EVENTS MODULE - Examination
  # ========================================
  "event_examination_create|events|/patientEvent/create?patient_id=13&event_type_id=1002&context_id=1&episode_id=12|Create Examination|create_event"
  "event_examination_view|events|/OphCiExamination/default/view/15|View Examination|view"
  
  # ========================================
  # EVENTS MODULE - Correspondence
  # ========================================
  "event_correspondence_view|events|/OphCoCorrespondence/default/view/7|View Correspondence|view"
  
  # ========================================
  # EVENTS MODULE - Prescription
  # ========================================
  "event_prescription_view|events|/OphDrPrescription/default/view/1|View Prescription|view"
  
  # ========================================
  # OPERATION BOOKING MODULE
  # ========================================
  "opbooking_waiting_list|opbooking|/OphTrOperationbooking/waitingList/index|Waiting List|view"
  "opbooking_theatre_diary|opbooking|/OphTrOperationbooking/theatreDiary/index|Theatre Diary|view"
  
  # Operation Booking Admin
  "opbooking_admin_wards|opbooking|/OphTrOperationbooking/admin/viewWards|Wards Admin|view"
  "opbooking_admin_theatres|opbooking|/OphTrOperationbooking/admin/viewTheatres|Theatres Admin|view"
  "opbooking_admin_priorities|opbooking|/OphTrOperationbooking/admin/operationPriorities|Operation Priorities|crud"
  "opbooking_admin_schedule|opbooking|/OphTrOperationbooking/admin/scheduleOptions|Scheduling Options|crud"
  "opbooking_admin_whiteboard|opbooking|/OphTrOperationbooking/oeadmin/WhiteboardSettings/settings|Whiteboard Settings|view"
  
  # ========================================
  # OPERATION NOTE MODULE
  # ========================================
  "opnote_admin_postop|opnote|/OphTrOperationnote/admin/postOpInstructions|Post-Op Instructions|crud"
  "opnote_admin_incision|opnote|/OphTrOperationnote/admin/viewIncisionLengthDefaults|Incision Length Defaults|view"
  "opnote_admin_devices|opnote|/OphTrOperationnote/OperativeDevice/list|Operative Devices|crud"
  
  # ========================================
  # CORRESPONDENCE MODULE
  # ========================================
  "corresp_admin_macros|corresp|/OphCoCorrespondence/admin/letterMacros|Letter Macros|view"
  "corresp_admin_snippets|corresp|/OphCoCorrespondence/oeadmin/snippet/list|Letter Snippets|crud"
  "corresp_admin_snippet_groups|corresp|/OphCoCorrespondence/oeadmin/snippetGroup/list|Snippet Groups|crud"
  "corresp_admin_settings|corresp|/OphCoCorrespondence/admin/letterSettings|Letter Settings|view"
  
  # ========================================
  # LASER MODULE
  # ========================================
  "laser_admin_procedures|laser|/OphTrLaser/admin/managelaserprocedures|Laser Procedures|crud"
  "laser_admin_types|laser|/OphTrLaser/admin/managelasertypes|Laser Types|crud"
  "laser_admin_operators|laser|/OphTrLaser/admin/managelasersitelaser|Site Lasers|crud"
  
  # ========================================
  # INTRAVITREAL INJECTION MODULE
  # ========================================
  "inject_admin_drugs|inject|/OphTrIntravitrealinjection/admin/viewTreatmentDrugs|Treatment Drugs|crud"
  "inject_admin_complications|inject|/OphTrIntravitrealinjection/admin/viewComplications|Complications|crud"
  
  # ========================================
  # CONSENT MODULE
  # ========================================
  "consent_admin_leaflets|consent|/OphTrConsent/oeadmin/Leaflets/list|Consent Leaflets|crud"
  
  # ========================================
  # BIOMETRY MODULE
  # ========================================
  "biometry_admin_lens|biometry|/OphInBiometry/lensTypeAdmin/list|Lens Types|crud"
  
  # ========================================
  # LAB RESULTS MODULE
  # ========================================
  "labresults_admin_types|labresults|/OphInLabResults/oeadmin/resultType/list|Result Types|crud"
  
  # ========================================
  # PATIENT TICKETING MODULE
  # ========================================
  "ticketing_index|ticketing|/PatientTicketing/default/index|Patient Ticketing|view"
  "ticketing_admin_clinics|ticketing|/PatientTicketing/PatientTicketingAdmin/ClinicLocations/index|Clinic Locations|crud"
  
  # ========================================
  # WORKLIST MODULE
  # ========================================
  "worklist_view|worklist|/worklist/view|Worklist View|view"
  
  # ========================================
  # MESSAGING MODULE
  # ========================================
  "messaging_index|messaging|/OphCoMessaging/default/index|Messaging Inbox|view"
  
  # ========================================
  # TRIALS MODULE
  # ========================================
  "trials_index|trials|/OETrial/default/index|Trials Index|view"
)

# ============================================================
# Utility Functions
# ============================================================
usage() {
  cat <<USAGE
Droid E2E Spot Check Script v${VERSION}

Usage: $0 [options]

Options:
  -m, --module <name>    Filter by module (patient, admin, events)
  -t, --test <id>        Run specific test by ID
  -r, --retries <N>      Max retry attempts (default: 3)
  -i, --interactive      Prompt before each test (run/skip/quit)
  --dry-run              Report only, don't apply fixes
  --parallel <N>         Parallel execution (default: 1, sequential)
  --cleanup              Delete test data after run
  -h, --help             Show this help

Examples:
  $0                           # Run all spot checks
  $0 --module admin            # Run admin tests only
  $0 --test admin_allergies    # Run specific test
  $0 -i -r 2                   # Interactive mode with 1 retry
  $0 --dry-run                 # Report without fixes

Test IDs:
$(printf '  - %s\n' "${TEST_CASES[@]}" | cut -d'|' -f1)
USAGE
}

log_info()  { printf "\033[36m%s [INFO]\033[0m %s\n" "$(date +%H:%M:%S)" "$*" >&2; }
log_warn()  { printf "\033[33m%s [WARN]\033[0m %s\n" "$(date +%H:%M:%S)" "$*" >&2; }
log_error() { printf "\033[31m%s [ERROR]\033[0m %s\n" "$(date +%H:%M:%S)" "$*" >&2; }
log_pass()  { printf "\033[32m%s [PASS]\033[0m %s\n" "$(date +%H:%M:%S)" "$*" >&2; }
log_fail()  { printf "\033[31m%s [FAIL]\033[0m %s\n" "$(date +%H:%M:%S)" "$*" >&2; }

# ============================================================
# Parse Arguments
# ============================================================
INTERACTIVE=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    -m|--module)    MODULE_FILTER="$2"; shift 2 ;;
    -t|--test)      TEST_FILTER="$2"; shift 2 ;;
    -r|--retries)   MAX_RETRIES="$2"; shift 2 ;;
    --dry-run)      DRY_RUN=1; shift ;;
    --parallel)     PARALLEL="$2"; shift 2 ;;
    --cleanup)      CLEANUP=1; shift ;;
    -i|--interactive) INTERACTIVE=1; shift ;;
    -h|--help)      usage; exit 0 ;;
    *)              log_error "Unknown option: $1"; usage; exit 1 ;;
  esac
done

# Check for droid CLI
command -v droid >/dev/null 2>&1 || { log_error "'droid' CLI not found"; exit 1; }

# ============================================================
# Initialize Results Tracking
# ============================================================
echo "test_id,module,url,description,status,first_try_pass,attempts,issues,fixes,items_before,items_after,duration_sec" > "$RESULTS_CSV"

# Simple counters (no associative arrays for macOS compatibility)
TOTAL_TESTS=0
PASSED_FIRST=0
PASSED_AFTER_FIX=0
FAILED=0

# ============================================================
# Run Single Test
# ============================================================
run_test() {
  local test_def="$1"
  local test_id=$(echo "$test_def" | cut -d'|' -f1)
  local module=$(echo "$test_def" | cut -d'|' -f2)
  local url=$(echo "$test_def" | cut -d'|' -f3)
  local description=$(echo "$test_def" | cut -d'|' -f4)
  local test_type=$(echo "$test_def" | cut -d'|' -f5)
  
  # Interactive mode: prompt user before each test
  if [[ "$INTERACTIVE" -eq 1 ]]; then
    echo ""
    printf "\033[33m>>> Next test: %s - %s\033[0m\n" "$test_id" "$description"
    printf "    URL: %s%s\n" "$BASE_URL" "$url"
    printf "    Type: %s\n" "$test_type"
    printf "\033[36m    [r]un | [s]kip | [q]uit: \033[0m"
    read -r user_choice </dev/tty
    case "$user_choice" in
      q|Q|quit)
        log_info "User quit. Generating reports..."
        return 255  # Special return code for quit
        ;;
      s|S|skip)
        log_warn "Skipped: $test_id"
        # Record as skipped
        echo "\"$test_id\",\"$module\",\"$url\",\"$description\",\"SKIP\",\"false\",0,\"USER_SKIPPED\",\"\",\"-\",\"-\",0" >> "$RESULTS_CSV"
        TOTAL_TESTS=$((TOTAL_TESTS + 1))
        return 0
        ;;
      r|R|run|"")
        # Continue to run the test
        ;;
      *)
        log_warn "Invalid choice '$user_choice', running test..."
        ;;
    esac
  fi
  
  local test_log="$LOG_DIR/${test_id}.log"
  local test_start=$(date +%s)
  local attempt=0
  local status="FAIL"
  local first_try_pass="false"
  local all_issues=""
  local all_fixes=""
  local items_before="-"
  local items_after="-"
  
  log_info "Testing: $test_id - $description"
  echo "[$(date +%H:%M:%S)] === Starting $test_id ===" > "$test_log"
  
  while [[ $attempt -lt $MAX_RETRIES ]]; do
    attempt=$((attempt + 1))
    echo "[$(date +%H:%M:%S)] Attempt $attempt/$MAX_RETRIES" >> "$test_log"
    
    # Build test prompt based on test type
    local test_prompt
    local timestamp=$(date +%s)
    case "$test_type" in
      crud)
        test_prompt="E2E CRUD Test using Playwright Browser

Test ID: $test_id
URL: ${BASE_URL}${url}
Login: $USERNAME / $PASSWORD
Institution: $INSTITUTION

IMPORTANT: You MUST use Playwright browser tools (playwright___browser_navigate, playwright___browser_snapshot, playwright___browser_click, playwright___browser_type, etc.) to perform this test.

## STEPS:

1. Navigate to ${BASE_URL}${url} using playwright___browser_navigate
2. Take a snapshot using playwright___browser_snapshot to see the page
3. If you see a login form, fill in username '$USERNAME' and password '$PASSWORD', then click login
4. If you see institution selector, select '$INSTITUTION'
5. Once on the admin page, count existing items in the table (ITEMS_BEFORE)
6. Look for 'Add' or 'Add New' or '+' button and click it
7. Fill the form - find the 'name' or 'Name' field and enter: 'SpotCheck_${timestamp}'
8. Click 'Save' or 'Add' button to submit
9. Verify you're back on the list page and the new item appears
10. Count items again (ITEMS_AFTER - should be ITEMS_BEFORE + 1)

After completing all steps, output EXACTLY this line:
PASS|NONE|<items_before>|<items_after>

If any step fails, output:
FAIL|<issue_type>|<items_before>|<items_after>

Issue types: PAGE_ERROR, LOGIN_FAILED, CREATE_FAILED, FORM_ERROR, NOT_SAVED"
        ;;
      
      search)
        test_prompt="E2E Search Test using Playwright Browser

Test ID: $test_id
URL: ${BASE_URL}${url}
Login: $USERNAME / $PASSWORD
Institution: $INSTITUTION

IMPORTANT: You MUST use Playwright browser tools to perform this test.

## STEPS:

1. Navigate to ${BASE_URL}${url} using playwright___browser_navigate
2. Take a snapshot using playwright___browser_snapshot
3. If login form appears, enter '$USERNAME' and '$PASSWORD', click login
4. If institution selector appears, select '$INSTITUTION'
5. Find the search input field
6. Type 'Parker' into the search field using playwright___browser_type
7. Submit the search (press Enter or click Search button)
8. Take another snapshot to see results
9. Count how many patient results appear

After completing, output:
PASS|NONE|<results_count>|0

If search fails:
FAIL|<issue>|0|0"
        ;;
      
      view)
        test_prompt="E2E View Page Test using Playwright Browser

Test ID: $test_id
URL: ${BASE_URL}${url}
Login: $USERNAME / $PASSWORD
Institution: $INSTITUTION

IMPORTANT: You MUST use Playwright browser tools to perform this test.

## STEPS:

1. Navigate to ${BASE_URL}${url} using playwright___browser_navigate
2. Take a snapshot using playwright___browser_snapshot
3. If login form appears, enter '$USERNAME' and '$PASSWORD', click login
4. If institution selector appears, select '$INSTITUTION'
5. Take another snapshot of the actual page
6. Check if page loaded correctly:
   - No 'TypeError' or 'Fatal error' or 'Exception' in the page
   - Page has meaningful content (not blank)
   - Key elements are visible

After completing, output:
PASS|NONE|0|0

If page has errors:
FAIL|PAGE_ERROR|0|0"
        ;;
      
      create_event)
        test_prompt="E2E Event Creation Test using Playwright Browser

Test ID: $test_id
URL: ${BASE_URL}${url}
Login: $USERNAME / $PASSWORD
Institution: $INSTITUTION

IMPORTANT: You MUST use Playwright browser tools to perform this test.

## STEPS:

1. Navigate to ${BASE_URL}${url} using playwright___browser_navigate
2. Take a snapshot using playwright___browser_snapshot
3. If login form appears, enter '$USERNAME' and '$PASSWORD', click login
4. If institution selector appears, select '$INSTITUTION'
5. Take another snapshot to see the event creation form
6. Verify:
   - No 'TypeError' or 'Fatal error' or 'Exception' in the page
   - Event creation form is visible
   - Form elements (inputs, buttons) are present
7. Do NOT actually save the event (just verify form loads)

After completing, output:
PASS|NONE|0|0

If page has errors:
FAIL|PAGE_ERROR|0|0"
        ;;
    esac
    
    # Run test using Playwright via droid
    local result="FAIL"
    local issues=""
    local before="-"
    local after="-"
    
    # Save test prompt for execution
    echo "$test_prompt" > "$LOG_DIR/${test_id}_prompt.txt"
    
    echo "[$(date +%H:%M:%S)] Running Playwright test via droid..." >> "$test_log"
    
    # Run droid with the test prompt - it will use Playwright browser tools
    local test_output
    test_output=$(timeout 180 droid exec --skip-permissions-unsafe "$test_prompt" 2>&1 | tee -a "$test_log")
    
    echo "[$(date +%H:%M:%S)] Droid output received (${#test_output} chars)" >> "$test_log"
    
    # Parse the output for results
    # Look for the structured output line: TEST_RESULT|ISSUES|ITEMS_BEFORE|ITEMS_AFTER
    local result_line=$(echo "$test_output" | grep -oE "(PASS|FAIL)\|[^|]*\|[0-9-]+\|[0-9-]+" | tail -1)
    
    if [[ -n "$result_line" ]]; then
      result=$(echo "$result_line" | cut -d'|' -f1)
      issues=$(echo "$result_line" | cut -d'|' -f2)
      before=$(echo "$result_line" | cut -d'|' -f3)
      after=$(echo "$result_line" | cut -d'|' -f4)
    else
      # Try to infer result from output - check failure conditions FIRST
      if echo "$test_output" | grep -qiE "TypeError|Fatal error|Exception|500.*error"; then
        result="FAIL"
        issues=$(echo "$test_output" | grep -oE "(TypeError|FatalError|Exception)" | head -1)
        [[ -z "$issues" ]] && issues="PAGE_ERROR"
      elif echo "$test_output" | grep -qiE "not appear|not found|not visible|not showing|didn't appear|does not appear|cannot find|could not find|item.*missing|missing.*item"; then
        result="FAIL"
        issues="ITEM_NOT_PERSISTED"
      elif echo "$test_output" | grep -qiE "not.*persist|not.*saved|not.*stored|data.*lost|empty.*list|no.*items"; then
        result="FAIL"
        issues="DATA_NOT_SAVED"
      elif echo "$test_output" | grep -qiE "timeout|timed out"; then
        result="FAIL"
        issues="TIMEOUT"
      elif echo "$test_output" | grep -qiE "failed|error|could not|unable to"; then
        result="FAIL"
        issues=$(echo "$test_output" | grep -oiE "(CREATE_FAILED|UPDATE_FAILED|LOGIN_FAILED|NOT_FOUND)" | head -1)
        [[ -z "$issues" ]] && issues="TEST_FAILED"
      elif echo "$test_output" | grep -qiE "successfully|passed|verified|created.*item|updated.*item|appears.*list|visible.*table"; then
        result="PASS"
      fi
    fi
    
    # Default to FAIL if result is still not set (safer default)
    [[ -z "$result" || "$result" == "" ]] && result="FAIL" && issues="UNKNOWN_RESULT"
    
    [[ -z "$issues" ]] && issues="NONE"
    echo "[$(date +%H:%M:%S)] Parsed: result=$result issues=$issues before=$before after=$after" >> "$test_log"
    
    [[ -n "$before" && "$before" != "-" ]] && items_before="$before"
    [[ -n "$after" && "$after" != "-" ]] && items_after="$after"
    
    # Check result
    if [[ "$result" == "PASS" ]]; then
      status="PASS"
      [[ $attempt -eq 1 ]] && first_try_pass="true"
      echo "[$(date +%H:%M:%S)] SUCCESS on attempt $attempt" >> "$test_log"
      break
    fi
    
    all_issues="${all_issues:+$all_issues;}$issues"
    [[ -z "$all_issues" ]] && all_issues="$issues"
    
    # Try to fix if not dry run
    if [[ "$DRY_RUN" -eq 0 && $attempt -lt $MAX_RETRIES ]]; then
      echo "[$(date +%H:%M:%S)] Attempting fix..." >> "$test_log"
      
      local fix_prompt="Fix issues for E2E test: $test_id
URL: ${BASE_URL}${url}
Issues found: $issues
Test type: $test_type

IMPORTANT: Use Playwright browser tools to investigate the issue, then fix the code.

## DIAGNOSE AND FIX

### Step 1: Investigate with Playwright
1. Navigate to ${BASE_URL}${url} using playwright___browser_navigate
2. Take a snapshot using playwright___browser_snapshot to see current state
3. Look for error messages, empty lists, or missing data

### Step 2: Based on the issue type, apply fixes:

#### If ITEM_NOT_PERSISTED or DATA_NOT_SAVED:
This usually means Couchbase dual-write is failing. Check:
1. Find the model class for this admin page (look at the URL pattern)
2. Check if model has CouchbaseModelBridge trait with afterSave/afterDelete hooks
3. Check couchbaseScope() method - it MUST match CouchbaseAdapter's getScopeForCollection() mapping
   - For ophciexamination_* tables, CouchbaseAdapter returns 'clinical' scope
   - If model returns 'reference' but adapter expects 'clinical', data goes to wrong collection
4. Create primary index on the correct Couchbase collection if missing
5. Fix the model's couchbaseScope() to return the correct scope

#### If PAGE_ERROR or TypeError:
- Check for trait collisions (HasRelationOptions + CouchbaseModelBridge both define __get)
- Check for method signature mismatches (missing : string return types)
- Look at the specific error message and fix the PHP code

#### If CREATE_FAILED or UPDATE_FAILED:
- Check form validation errors
- Verify database schema matches model

### Step 3: After fixing, output EXACTLY this line:
FIXED_<what_you_fixed>

Examples:
FIXED_SCOPE_MISMATCH
FIXED_CREATED_INDEX
FIXED_TRAIT_COLLISION
FIXED_RETURN_TYPE
FIXED_ADDED_HOOKS
NONE"

      # Save fix prompt
      echo "$fix_prompt" > "$LOG_DIR/${test_id}_fix_prompt_${attempt}.txt"
      
      # Execute fix via droid
      echo "[$(date +%H:%M:%S)] Executing fix via droid..." >> "$test_log"
      local fix_output
      fix_output=$(timeout 120 droid exec --skip-permissions-unsafe "$fix_prompt" 2>&1 | tee -a "$test_log")
      
      # Parse fix output
      local fixes="NONE"
      if echo "$fix_output" | grep -qiE "FIXED_|CREATED_|ADDED_|UPDATED_"; then
        fixes=$(echo "$fix_output" | grep -oiE "(FIXED_[A-Z_]+|CREATED_[A-Z_]+|ADDED_[A-Z_]+|UPDATED_[A-Z_]+)" | tr '\n' ';' | sed 's/;$//')
        [[ -z "$fixes" ]] && fixes="FIX_APPLIED"
      elif echo "$fix_output" | grep -qiE "no.*fix|nothing.*to.*fix|already.*correct"; then
        fixes="NO_FIX_NEEDED"
      else
        fixes="FIX_ATTEMPTED"
      fi
      
      all_fixes="${all_fixes:+$all_fixes;}$fixes"
      echo "[$(date +%H:%M:%S)] Fixes applied: $fixes - retesting..." >> "$test_log"
      sleep 2
    else
      break
    fi
  done
  
  # Calculate duration
  local test_end=$(date +%s)
  local duration=$((test_end - test_start))
  
  # Sanitize for CSV
  all_issues=$(echo "$all_issues" | tr ',' ';' | tr -d '"' | head -c 200)
  all_fixes=$(echo "$all_fixes" | tr ',' ';' | tr -d '"' | head -c 200)
  
  # Record result
  echo "\"$test_id\",\"$module\",\"$url\",\"$description\",\"$status\",\"$first_try_pass\",$attempt,\"$all_issues\",\"$all_fixes\",\"$items_before\",\"$items_after\",$duration" >> "$RESULTS_CSV"
  
  # Update counters
  TOTAL_TESTS=$((TOTAL_TESTS + 1))
  if [[ "$status" == "PASS" ]]; then
    if [[ "$first_try_pass" == "true" ]]; then
      PASSED_FIRST=$((PASSED_FIRST + 1))
      log_pass "$test_id (first try)"
    else
      PASSED_AFTER_FIX=$((PASSED_AFTER_FIX + 1))
      log_pass "$test_id (after fix)"
    fi
  else
    FAILED=$((FAILED + 1))
    log_fail "$test_id: $all_issues"
  fi
}

# ============================================================
# Generate Reports
# ============================================================
generate_reports() {
  local end_epoch=$(date +%s)
  local total_duration=$((end_epoch - START_EPOCH))
  local total_mins=$((total_duration / 60))
  local total_secs=$((total_duration % 60))
  
  log_info "Generating reports..."
  
  # Markdown Report
  cat > "$REPORT_MD" <<REPORT
# E2E Spot Check Report

**Generated:** $(date '+%Y-%m-%d %H:%M:%S')  
**Duration:** ${total_mins}m ${total_secs}s  
**Script Version:** ${VERSION}

## Summary

| Metric | Count |
|--------|-------|
| Total Tests | $TOTAL_TESTS |
| Passed (1st try) | $PASSED_FIRST |
| Passed (after fix) | $PASSED_AFTER_FIX |
| Failed | $FAILED |
| **Pass Rate** | **$(( (PASSED_FIRST + PASSED_AFTER_FIX) * 100 / (TOTAL_TESTS > 0 ? TOTAL_TESTS : 1) ))%** |

## Results by Module

REPORT

  # Group results by module
  for mod in patient admin events; do
    local mod_tests=$(awk -F, -v m="$mod" 'NR>1 && $2~m {print}' "$RESULTS_CSV")
    if [[ -n "$mod_tests" ]]; then
      # Capitalize first letter (bash 3.x compatible)
      local mod_cap=$(echo "$mod" | awk '{print toupper(substr($0,1,1)) tolower(substr($0,2))}')
      echo "### ${mod_cap} Module" >> "$REPORT_MD"
      echo "" >> "$REPORT_MD"
      echo "| Test | Status | 1st Try | Issues | Fixes |" >> "$REPORT_MD"
      echo "|------|--------|---------|--------|-------|" >> "$REPORT_MD"
      
      echo "$mod_tests" | while IFS= read -r line; do
        local tid=$(echo "$line" | cut -d',' -f1 | tr -d '"')
        local status=$(echo "$line" | cut -d',' -f5 | tr -d '"')
        local first=$(echo "$line" | cut -d',' -f6 | tr -d '"')
        local issues=$(echo "$line" | cut -d',' -f8 | tr -d '"')
        local fixes=$(echo "$line" | cut -d',' -f9 | tr -d '"')
        
        local first_icon="✗"
        [[ "$first" == "true" ]] && first_icon="✓"
        local status_icon="❌"
        [[ "$status" == "PASS" ]] && status_icon="✅"
        
        echo "| $tid | $status_icon $status | $first_icon | ${issues:-—} | ${fixes:-—} |" >> "$REPORT_MD"
      done
      echo "" >> "$REPORT_MD"
    fi
  done

  # Issues Summary
  echo "## Issues Found" >> "$REPORT_MD"
  echo "" >> "$REPORT_MD"
  awk -F, 'NR>1 && $8!="" && $8!="\"\"" && $8!="NONE" {gsub(/"/, "", $8); split($8, a, ";"); for(i in a) if(a[i]!="") issues[a[i]]++} END {for(i in issues) print "- " i ": " issues[i]}' "$RESULTS_CSV" >> "$REPORT_MD" || echo "- None" >> "$REPORT_MD"
  echo "" >> "$REPORT_MD"

  # Fixes Applied
  echo "## Fixes Applied" >> "$REPORT_MD"
  echo "" >> "$REPORT_MD"
  awk -F, 'NR>1 && $9!="" && $9!="\"\"" && $9!="NONE" {gsub(/"/, "", $9); split($9, a, ";"); for(i in a) if(a[i]!="") fixes[a[i]]++} END {for(i in fixes) print "- " i ": " fixes[i]}' "$RESULTS_CSV" >> "$REPORT_MD" || echo "- None" >> "$REPORT_MD"
  echo "" >> "$REPORT_MD"

  # Footer
  cat >> "$REPORT_MD" <<FOOTER

---
**Log Directory:** \`$LOG_DIR\`  
**CSV Results:** \`$RESULTS_CSV\`
FOOTER

  # JSON Summary
  cat > "$SUMMARY_JSON" <<JSON
{
  "timestamp": "$(date -Iseconds)",
  "version": "$VERSION",
  "duration_seconds": $total_duration,
  "total_tests": $TOTAL_TESTS,
  "passed_first_try": $PASSED_FIRST,
  "passed_after_fix": $PASSED_AFTER_FIX,
  "failed": $FAILED,
  "pass_rate": $(( (PASSED_FIRST + PASSED_AFTER_FIX) * 100 / (TOTAL_TESTS > 0 ? TOTAL_TESTS : 1) )),
  "log_dir": "$LOG_DIR",
  "results_csv": "$RESULTS_CSV",
  "report_md": "$REPORT_MD"
}
JSON

  log_info "Reports generated:"
  log_info "  CSV: $RESULTS_CSV"
  log_info "  Markdown: $REPORT_MD"
  log_info "  JSON: $SUMMARY_JSON"
}

# ============================================================
# Main Execution
# ============================================================
log_info "=========================================="
log_info "  E2E Spot Check Script v${VERSION}"
log_info "=========================================="
log_info "Log Directory: $LOG_DIR"
log_info "Max Retries: $MAX_RETRIES"
log_info "Dry Run: $DRY_RUN"
log_info "Interactive: $INTERACTIVE"
[[ -n "$MODULE_FILTER" ]] && log_info "Module Filter: $MODULE_FILTER"
[[ -n "$TEST_FILTER" ]] && log_info "Test Filter: $TEST_FILTER"
echo ""

# Filter and run tests
USER_QUIT=0
for test_def in "${TEST_CASES[@]}"; do
  test_id=$(echo "$test_def" | cut -d'|' -f1)
  module=$(echo "$test_def" | cut -d'|' -f2)
  
  # Apply filters
  [[ -n "$MODULE_FILTER" && "$module" != "$MODULE_FILTER" ]] && continue
  [[ -n "$TEST_FILTER" && "$test_id" != "$TEST_FILTER" ]] && continue
  
  run_test "$test_def"
  ret=$?
  
  # Check if user quit
  if [[ $ret -eq 255 ]]; then
    USER_QUIT=1
    break
  fi
  
  echo ""
done

# Generate reports
generate_reports

# Final Summary
echo ""
log_info "=========================================="
log_info "  FINAL SUMMARY"
log_info "=========================================="
log_info "Total Tests:        $TOTAL_TESTS"
log_info "Passed (1st try):   $PASSED_FIRST"
log_info "Passed (after fix): $PASSED_AFTER_FIX"
log_info "Failed:             $FAILED"
log_info "Pass Rate:          $(( (PASSED_FIRST + PASSED_AFTER_FIX) * 100 / (TOTAL_TESTS > 0 ? TOTAL_TESTS : 1) ))%"
log_info "=========================================="

# Exit with appropriate code
[[ $FAILED -eq 0 ]] && exit 0 || exit 1
