#!/bin/bash
#
# Couchbase Cutover Monitor Script
#
# Continuously monitors Couchbase cutover metrics and alerts on issues.
# Tracks health, performance, error rates, and sync status.
#
# Phase 16: Production Cutover
#
# Usage:
#   ./monitor-cutover.sh [options]
#
# Options:
#   --interval=N        Check interval in seconds (default: 60)
#   --duration=N        Total monitoring duration in minutes (default: continuous)
#   --error-threshold=N Error rate threshold % for alerts (default: 1.0)
#   --latency-threshold=N Latency threshold in ms for alerts (default: 500)
#

set -e

# Default configuration
CHECK_INTERVAL=60
DURATION_MINUTES=0  # 0 = continuous
ERROR_THRESHOLD=1.0
LATENCY_THRESHOLD=500
LOG_FILE="runtime/cutover-monitor.log"
YIIC="php protected/yiic"

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --interval=*)
            CHECK_INTERVAL="${1#*=}"
            shift
            ;;
        --duration=*)
            DURATION_MINUTES="${1#*=}"
            shift
            ;;
        --error-threshold=*)
            ERROR_THRESHOLD="${1#*=}"
            shift
            ;;
        --latency-threshold=*)
            LATENCY_THRESHOLD="${1#*=}"
            shift
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Calculate total checks
if [ $DURATION_MINUTES -eq 0 ]; then
    TOTAL_CHECKS=-1  # Continuous
else
    TOTAL_CHECKS=$((DURATION_MINUTES * 60 / CHECK_INTERVAL))
fi

echo "==========================================="
echo "COUCHBASE CUTOVER MONITOR"
echo "==========================================="
echo ""
echo "Check interval: ${CHECK_INTERVAL}s"
if [ $TOTAL_CHECKS -eq -1 ]; then
    echo "Duration: Continuous (Ctrl+C to stop)"
else
    echo "Duration: $DURATION_MINUTES minutes ($TOTAL_CHECKS checks)"
fi
echo "Error threshold: $ERROR_THRESHOLD%"
echo "Latency threshold: ${LATENCY_THRESHOLD}ms"
echo "Log file: $LOG_FILE"
echo ""
echo "Starting monitoring at $(date '+%Y-%m-%d %H:%M:%S')..."
echo ""

# Logging function
log_message() {
    local level=$1
    local message=$2
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo "[$timestamp] [$level] $message" >> "$LOG_FILE"
}

# Alert function
alert() {
    local level=$1
    local message=$2
    
    case $level in
        ERROR)
            echo -e "${RED}✗ ERROR: $message${NC}"
            ;;
        WARNING)
            echo -e "${YELLOW}⚠ WARNING: $message${NC}"
            ;;
        INFO)
            echo -e "${BLUE}ℹ INFO: $message${NC}"
            ;;
        SUCCESS)
            echo -e "${GREEN}✓ $message${NC}"
            ;;
    esac
    
    log_message "$level" "$message"
}

# Check MariaDB health
check_mariadb() {
    local start=$(date +%s%N)
    
    if ! $YIIC db ping &>/dev/null; then
        return 1
    fi
    
    local end=$(date +%s%N)
    local latency=$(( (end - start) / 1000000 ))  # Convert to ms
    
    echo $latency
    return 0
}

# Check Couchbase health
check_couchbase() {
    local result=$(php -r "
        require_once 'protected/yii.php';
        require_once 'protected/config/console.php';
        Yii::createConsoleApplication(\$config);
        
        \$start = microtime(true);
        try {
            Yii::app()->couchbase->query('SELECT 1');
            \$latency = round((microtime(true) - \$start) * 1000, 2);
            echo \$latency;
            exit(0);
        } catch (Exception \$e) {
            exit(1);
        }
    " 2>&1)
    
    if [ $? -ne 0 ]; then
        return 1
    fi
    
    echo $result
    return 0
}

# Get cutover status
get_cutover_status() {
    php -r "
        require_once 'protected/yii.php';
        require_once 'protected/config/console.php';
        Yii::createConsoleApplication(\$config);
        
        try {
            \$manager = CouchbaseCutoverManager::getInstance();
            \$config = \$manager->getConfig();
            
            echo json_encode([
                'percentage' => \$config['couchbase_read_percentage'],
                'emergency' => \$config['emergency_disable'],
                'enabled' => \$config['enabled'],
            ]);
        } catch (Exception \$e) {
            echo json_encode(['error' => \$e->getMessage()]);
        }
    "
}

# Main monitoring loop
check_count=0

while true; do
    check_count=$((check_count + 1))
    
    echo "----------------------------------------"
    echo "Check #$check_count - $(date '+%H:%M:%S')"
    echo "----------------------------------------"
    
    # Get cutover status
    cutover_status=$(get_cutover_status)
    percentage=$(echo $cutover_status | php -r "echo json_decode(file_get_contents('php://stdin'), true)['percentage'] ?? 0;")
    emergency=$(echo $cutover_status | php -r "echo json_decode(file_get_contents('php://stdin'), true)['emergency'] ?? false;")
    enabled=$(echo $cutover_status | php -r "echo json_decode(file_get_contents('php://stdin'), true)['enabled'] ?? false;")
    
    echo "Status: ${percentage}% to Couchbase"
    
    # Check for emergency mode
    if [ "$emergency" = "1" ] || [ "$emergency" = "true" ]; then
        alert "WARNING" "Emergency disable is active!"
    fi
    
    # Check MariaDB
    mariadb_latency=$(check_mariadb)
    if [ $? -eq 0 ]; then
        if [ $mariadb_latency -gt $LATENCY_THRESHOLD ]; then
            alert "WARNING" "MariaDB latency high: ${mariadb_latency}ms"
        else
            echo "  MariaDB: OK (${mariadb_latency}ms)"
        fi
    else
        alert "ERROR" "MariaDB health check failed!"
    fi
    
    # Check Couchbase (if percentage > 0)
    if [ $percentage -gt 0 ]; then
        couchbase_latency=$(check_couchbase)
        if [ $? -eq 0 ]; then
            if [ $(echo "$couchbase_latency > $LATENCY_THRESHOLD" | bc -l) -eq 1 ]; then
                alert "WARNING" "Couchbase latency high: ${couchbase_latency}ms"
            else
                echo "  Couchbase: OK (${couchbase_latency}ms)"
            fi
        else
            alert "ERROR" "Couchbase health check failed!"
        fi
    fi
    
    # Check error logs
    error_count=$(tail -n 100 protected/runtime/application.log 2>/dev/null | grep -i "couchbase.*error" | wc -l | tr -d ' ')
    if [ $error_count -gt 10 ]; then
        alert "WARNING" "High error count in logs: $error_count recent errors"
    elif [ $error_count -gt 0 ]; then
        echo "  Errors: $error_count in last 100 log lines"
    else
        echo "  Errors: None"
    fi
    
    echo ""
    
    # Check if duration reached
    if [ $TOTAL_CHECKS -ne -1 ] && [ $check_count -ge $TOTAL_CHECKS ]; then
        echo "Monitoring duration reached ($DURATION_MINUTES minutes)"
        break
    fi
    
    # Wait for next check
    sleep $CHECK_INTERVAL
done

echo ""
echo "==========================================="
echo "MONITORING COMPLETE"
echo "==========================================="
echo ""
echo "Total checks: $check_count"
echo "Duration: $((check_count * CHECK_INTERVAL / 60)) minutes"
echo "Completed: $(date '+%Y-%m-%d %H:%M:%S')"
echo "Log file: $LOG_FILE"
echo ""
