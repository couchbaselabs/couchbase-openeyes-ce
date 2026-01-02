# Phase 14: Migration Monitoring Guide

## Overview

This guide provides N1QL queries and monitoring strategies for tracking migration progress and data quality in real-time.

---

## Table of Contents

1. [Real-Time Progress Monitoring](#real-time-progress-monitoring)
2. [Data Quality Queries](#data-quality-queries)
3. [Performance Monitoring](#performance-monitoring)
4. [Issue Detection](#issue-detection)
5. [Reporting Queries](#reporting-queries)

---

## Real-Time Progress Monitoring

### Migration Progress by Scope

**Query: Count records by scope and type**

```sql
SELECT 
    META().id AS doc_key,
    type,
    COUNT(*) AS record_count
FROM openeyes._default.clinical
GROUP BY type
ORDER BY record_count DESC;
```

**Expected Output:**
```
{
  "type": "patient",
  "record_count": 12450
},
{
  "type": "episode",
  "record_count": 34567
},
{
  "type": "event",
  "record_count": 89123
}
```

### Recent Writes

**Query: Records written in last hour**

```sql
SELECT 
    type,
    COUNT(*) AS recent_count,
    MAX(created_date) AS last_write
FROM openeyes._default.clinical
WHERE created_date >= DATE_ADD_STR(NOW_STR(), -1, 'hour')
GROUP BY type;
```

### Records Written Today

```sql
SELECT 
    type,
    COUNT(*) AS today_count
FROM openeyes._default.clinical
WHERE created_date >= DATE_TRUNC_STR(NOW_STR(), 'day')
GROUP BY type
ORDER BY today_count DESC;
```

### Migration Rate

**Query: Calculate records/minute**

```sql
SELECT 
    type,
    COUNT(*) AS total_records,
    DATE_DIFF_STR(MAX(created_date), MIN(created_date), 'minute') AS duration_minutes,
    ROUND(COUNT(*) / DATE_DIFF_STR(MAX(created_date), MIN(created_date), 'minute'), 2) AS records_per_minute
FROM openeyes._default.clinical
WHERE created_date >= DATE_ADD_STR(NOW_STR(), -1, 'hour')
GROUP BY type;
```

---

## Data Quality Queries

### Count Comparison by Table

**Query: Compare with expected counts**

```sql
-- Patient count
SELECT 
    'patient' AS table_name,
    COUNT(*) AS couchbase_count
FROM openeyes._default.clinical
WHERE type = 'patient';

-- Episode count
SELECT 
    'episode' AS table_name,
    COUNT(*) AS couchbase_count
FROM openeyes._default.clinical
WHERE type = 'episode';

-- Event count
SELECT 
    'event' AS table_name,
    COUNT(*) AS couchbase_count
FROM openeyes._default.clinical
WHERE type = 'event';
```

### Check for Missing Embedded Relations

**Query: Patients without contact embedding**

```sql
SELECT 
    id,
    hos_num
FROM openeyes._default.clinical
WHERE type = 'patient'
AND contact_id IS NOT NULL
AND (contact IS NULL OR contact IS MISSING)
LIMIT 100;
```

**Query: Episodes without firm embedding**

```sql
SELECT 
    id,
    patient_id
FROM openeyes._default.clinical
WHERE type = 'episode'
AND firm_id IS NOT NULL
AND (firm IS NULL OR firm IS MISSING)
LIMIT 100;
```

**Query: Events without event_type embedding**

```sql
SELECT 
    id,
    episode_id
FROM openeyes._default.clinical
WHERE type = 'event'
AND event_type_id IS NOT NULL
AND (event_type IS NULL OR event_type IS MISSING)
LIMIT 100;
```

### Verify SNOMED Codes

**Query: Disorders with SNOMED codes**

```sql
SELECT 
    COUNT(*) AS total,
    COUNT(CASE WHEN snomed_code IS NOT NULL THEN 1 END) AS with_snomed,
    ROUND(COUNT(CASE WHEN snomed_code IS NOT NULL THEN 1 END) * 100.0 / COUNT(*), 2) AS snomed_percentage
FROM openeyes._default.reference
WHERE type = 'disorder';
```

**Query: Procedures with OPCS codes**

```sql
SELECT 
    COUNT(*) AS total,
    COUNT(CASE WHEN opcs_code IS NOT NULL THEN 1 END) AS with_opcs,
    ROUND(COUNT(CASE WHEN opcs_code IS NOT NULL THEN 1 END) * 100.0 / COUNT(*), 2) AS opcs_percentage
FROM openeyes._default.reference
WHERE type = 'procedure';
```

### Check Data Integrity

**Query: Orphaned episodes (patient not in Couchbase)**

```sql
SELECT 
    e.id AS episode_id,
    e.patient_id
FROM openeyes._default.clinical e
WHERE e.type = 'episode'
AND e.patient_id NOT IN (
    SELECT RAW p.id
    FROM openeyes._default.clinical p
    WHERE p.type = 'patient'
)
LIMIT 100;
```

**Query: Orphaned events (episode not in Couchbase)**

```sql
SELECT 
    ev.id AS event_id,
    ev.episode_id
FROM openeyes._default.clinical ev
WHERE ev.type = 'event'
AND ev.episode_id NOT IN (
    SELECT RAW ep.id
    FROM openeyes._default.clinical ep
    WHERE ep.type = 'episode'
)
LIMIT 100;
```

---

## Performance Monitoring

### Document Size Distribution

**Query: Average document size by type**

```sql
SELECT 
    type,
    COUNT(*) AS doc_count,
    AVG(LENGTH(META().id)) AS avg_key_length,
    MIN(created_date) AS earliest,
    MAX(created_date) AS latest
FROM openeyes._default.clinical
GROUP BY type;
```

### Index Usage

**Query: Check if indexes are being used**

```sql
EXPLAIN SELECT * 
FROM openeyes._default.clinical 
WHERE type = 'patient' 
AND hos_num = 'ABC123';
```

**Look for:**
- `"index"` field showing index name
- `"index_scan"` instead of `"primary_scan"`

### Query Performance

**Query: Measure query execution time**

```sql
-- Patient lookup by hospital number
\set -query_context "openeyes._default";

SELECT * 
FROM clinical 
WHERE type = 'patient' 
AND hos_num = 'ABC123';

-- Check query stats
SELECT * FROM system:completed_requests 
WHERE statement LIKE '%hos_num%' 
ORDER BY requestTime DESC 
LIMIT 1;
```

---

## Issue Detection

### Find Duplicates

**Query: Duplicate patient IDs**

```sql
SELECT 
    id,
    COUNT(*) AS duplicate_count
FROM openeyes._default.clinical
WHERE type = 'patient'
GROUP BY id
HAVING COUNT(*) > 1;
```

### Find NULL Critical Fields

**Query: Patients without hos_num**

```sql
SELECT 
    id,
    contact_id
FROM openeyes._default.clinical
WHERE type = 'patient'
AND (hos_num IS NULL OR hos_num IS MISSING)
LIMIT 100;
```

**Query: Episodes without patient_id**

```sql
SELECT 
    id
FROM openeyes._default.clinical
WHERE type = 'episode'
AND (patient_id IS NULL OR patient_id IS MISSING)
LIMIT 100;
```

### Find Incomplete Records

**Query: Records with minimal fields**

```sql
SELECT 
    META().id,
    type,
    OBJECT_LENGTH(self) AS field_count
FROM openeyes._default.clinical self
WHERE OBJECT_LENGTH(self) < 5  -- Very few fields
LIMIT 100;
```

### Date Range Anomalies

**Query: Records with future dates**

```sql
SELECT 
    id,
    type,
    created_date
FROM openeyes._default.clinical
WHERE created_date > NOW_STR()
LIMIT 100;
```

**Query: Records with very old dates (potential data issues)**

```sql
SELECT 
    id,
    type,
    created_date
FROM openeyes._default.clinical
WHERE created_date < '1900-01-01'
LIMIT 100;
```

---

## Reporting Queries

### Migration Summary Report

```sql
SELECT 
    'Clinical' AS scope,
    type,
    COUNT(*) AS record_count,
    MIN(created_date) AS earliest_record,
    MAX(created_date) AS latest_record
FROM openeyes._default.clinical
GROUP BY type

UNION ALL

SELECT 
    'Reference' AS scope,
    type,
    COUNT(*) AS record_count,
    MIN(created_date) AS earliest_record,
    MAX(created_date) AS latest_record
FROM openeyes._default.reference
GROUP BY type

UNION ALL

SELECT 
    'Admin' AS scope,
    type,
    COUNT(*) AS record_count,
    MIN(created_date) AS earliest_record,
    MAX(created_date) AS latest_record
FROM openeyes._default.admin
GROUP BY type

ORDER BY scope, type;
```

### Daily Migration Progress

```sql
SELECT 
    DATE_TRUNC_STR(created_date, 'day') AS migration_date,
    type,
    COUNT(*) AS records_migrated
FROM openeyes._default.clinical
WHERE created_date >= DATE_ADD_STR(NOW_STR(), -7, 'day')
GROUP BY DATE_TRUNC_STR(created_date, 'day'), type
ORDER BY migration_date DESC, type;
```

### Embedding Completeness Report

```sql
-- Patient contact embedding
SELECT 
    'Patient Contact' AS embedding_type,
    COUNT(*) AS total_records,
    COUNT(CASE WHEN contact IS NOT NULL AND contact IS NOT MISSING THEN 1 END) AS with_embedding,
    ROUND(COUNT(CASE WHEN contact IS NOT NULL AND contact IS NOT MISSING THEN 1 END) * 100.0 / COUNT(*), 2) AS percentage
FROM openeyes._default.clinical
WHERE type = 'patient'
AND contact_id IS NOT NULL

UNION ALL

-- Episode firm embedding
SELECT 
    'Episode Firm' AS embedding_type,
    COUNT(*) AS total_records,
    COUNT(CASE WHEN firm IS NOT NULL AND firm IS NOT MISSING THEN 1 END) AS with_embedding,
    ROUND(COUNT(CASE WHEN firm IS NOT NULL AND firm IS NOT MISSING THEN 1 END) * 100.0 / COUNT(*), 2) AS percentage
FROM openeyes._default.clinical
WHERE type = 'episode'
AND firm_id IS NOT NULL

UNION ALL

-- Event type embedding
SELECT 
    'Event Type' AS embedding_type,
    COUNT(*) AS total_records,
    COUNT(CASE WHEN event_type IS NOT NULL AND event_type IS NOT MISSING THEN 1 END) AS with_embedding,
    ROUND(COUNT(CASE WHEN event_type IS NOT NULL AND event_type IS NOT MISSING THEN 1 END) * 100.0 / COUNT(*), 2) AS percentage
FROM openeyes._default.clinical
WHERE type = 'event'
AND event_type_id IS NOT NULL;
```

---

## Monitoring Scripts

### Bash Script: Watch Migration Progress

Save as `monitor-migration.sh`:

```bash
#!/bin/bash
#
# Monitor migration progress in real-time
#

COUCHBASE_HOST="localhost"
COUCHBASE_USER="Admin"
COUCHBASE_PASS="password"

while true; do
    clear
    echo "========================================="
    echo "MIGRATION PROGRESS MONITOR"
    echo "Time: $(date)"
    echo "========================================="
    echo ""
    
    echo "Record Counts:"
    cbq -quiet -u "$COUCHBASE_USER" -p "$COUCHBASE_PASS" -e \
        "SELECT type, COUNT(*) as count 
         FROM openeyes._default.clinical 
         GROUP BY type 
         ORDER BY type"
    
    echo ""
    echo "Recent Activity (last 5 minutes):"
    cbq -quiet -u "$COUCHBASE_USER" -p "$COUCHBASE_PASS" -e \
        "SELECT type, COUNT(*) as recent_count 
         FROM openeyes._default.clinical 
         WHERE created_date >= DATE_ADD_STR(NOW_STR(), -5, 'minute') 
         GROUP BY type"
    
    echo ""
    echo "Refreshing in 30 seconds... (Ctrl+C to stop)"
    sleep 30
done
```

### Python Script: Migration Dashboard

Save as `migration-dashboard.py`:

```python
#!/usr/bin/env python3
"""
Real-time migration dashboard
"""

import time
import subprocess
import json
from datetime import datetime

def run_query(query):
    """Execute N1QL query and return results"""
    cmd = [
        'cbq', '-quiet', '-u', 'Admin', '-p', 'password',
        '-e', query
    ]
    result = subprocess.run(cmd, capture_output=True, text=True)
    return json.loads(result.stdout)

def get_counts():
    """Get record counts by type"""
    query = """
    SELECT type, COUNT(*) as count 
    FROM openeyes._default.clinical 
    GROUP BY type
    """
    return run_query(query)

def get_recent_activity():
    """Get records written in last 5 minutes"""
    query = """
    SELECT type, COUNT(*) as recent_count 
    FROM openeyes._default.clinical 
    WHERE created_date >= DATE_ADD_STR(NOW_STR(), -5, 'minute') 
    GROUP BY type
    """
    return run_query(query)

def display_dashboard():
    """Display migration dashboard"""
    print("\033[2J\033[H")  # Clear screen
    print("=" * 60)
    print("MIGRATION DASHBOARD")
    print(f"Time: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("=" * 60)
    print()
    
    # Record counts
    print("Record Counts:")
    counts = get_counts()
    for row in counts.get('results', []):
        print(f"  {row['type']:<20}: {row['count']:>10,}")
    
    print()
    
    # Recent activity
    print("Recent Activity (last 5 minutes):")
    recent = get_recent_activity()
    for row in recent.get('results', []):
        print(f"  {row['type']:<20}: {row['recent_count']:>10,}")
    
    print()
    print("Refreshing in 30 seconds... (Ctrl+C to stop)")

if __name__ == '__main__':
    try:
        while True:
            display_dashboard()
            time.sleep(30)
    except KeyboardInterrupt:
        print("\nMonitoring stopped.")
```

---

## Alert Queries

### Critical Issues

**Query: Records with errors (if error field exists)**

```sql
SELECT 
    type,
    id,
    error_message
FROM openeyes._default.clinical
WHERE error_message IS NOT NULL
AND error_message IS NOT MISSING
LIMIT 100;
```

**Query: Incomplete migrations (records without required fields)**

```sql
-- Patients without critical fields
SELECT 
    id,
    CASE 
        WHEN hos_num IS MISSING THEN 'Missing hos_num'
        WHEN contact_id IS MISSING THEN 'Missing contact_id'
        ELSE 'Other issue'
    END AS issue
FROM openeyes._default.clinical
WHERE type = 'patient'
AND (hos_num IS MISSING OR contact_id IS MISSING)
LIMIT 100;
```

---

## Performance Optimization Tips

### 1. Create Monitoring Indexes

```sql
-- Index for date range queries
CREATE INDEX idx_clinical_created_date 
ON openeyes._default.clinical(created_date) 
WHERE type IS NOT MISSING;

-- Index for type-specific queries
CREATE INDEX idx_clinical_type_id 
ON openeyes._default.clinical(type, id);

-- Index for patient lookups
CREATE INDEX idx_patient_hos_num 
ON openeyes._default.clinical(hos_num) 
WHERE type = 'patient';
```

### 2. Query Optimization

**Use covering indexes:**

```sql
-- Bad: Fetches full documents
SELECT * FROM openeyes._default.clinical WHERE type = 'patient';

-- Good: Uses index only
SELECT id, hos_num FROM openeyes._default.clinical WHERE type = 'patient';
```

**Use appropriate WHERE clauses:**

```sql
-- Bad: Scans all documents
SELECT COUNT(*) FROM openeyes._default.clinical;

-- Good: Uses index
SELECT COUNT(*) FROM openeyes._default.clinical WHERE type IS NOT MISSING;
```

### 3. Batch Queries

Instead of querying each type separately, use UNION ALL:

```sql
SELECT 'patient' as source, COUNT(*) as count 
FROM openeyes._default.clinical WHERE type = 'patient'
UNION ALL
SELECT 'episode', COUNT(*) 
FROM openeyes._default.clinical WHERE type = 'episode'
UNION ALL
SELECT 'event', COUNT(*) 
FROM openeyes._default.clinical WHERE type = 'event';
```

---

## Troubleshooting Query Issues

### Slow Queries

**Check query plan:**

```sql
EXPLAIN SELECT * FROM openeyes._default.clinical 
WHERE type = 'patient' AND hos_num = 'ABC123';
```

**Look for:**
- `primary_scan` (bad - full collection scan)
- `index_scan` (good - using index)

### Index Not Being Used

**Reasons:**
1. Index doesn't cover query fields
2. WHERE clause doesn't match index predicate
3. Index not fully built

**Check index status:**

```sql
SELECT * FROM system:indexes 
WHERE keyspace_id = 'openeyes';
```

---

**Document Version:** 1.0  
**Last Updated:** December 24, 2025  
**Maintained By:** OpenEyes Development Team
