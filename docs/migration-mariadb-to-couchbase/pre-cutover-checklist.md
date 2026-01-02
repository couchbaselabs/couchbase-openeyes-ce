# Pre-Cutover Validation Checklist

## Overview

This checklist must be completed before switching production read operations from MariaDB to Couchbase. All items must pass before proceeding with the cutover.

---

## 1. Data Migration Validation

### 1.1 Record Counts
- [ ] **Patient Count**: Couchbase patient count matches MariaDB (tolerance: 0%)
- [ ] **Episode Count**: Couchbase episode count matches MariaDB (tolerance: 0%)
- [ ] **Event Count**: Couchbase event count matches MariaDB (tolerance: 0%)
- [ ] **User Count**: Couchbase user count matches MariaDB (tolerance: 0%)

### 1.2 Sample Validation
- [ ] **Random Patient Sample**: Validate 100 random patients have matching data
- [ ] **Random Episode Sample**: Validate 100 random episodes have matching data
- [ ] **Random Event Sample**: Validate 100 random events have matching data
- [ ] **All Fields Match**: All validated samples have 100% field accuracy

### 1.3 Relationship Integrity
- [ ] **Patient-Episode Links**: All episodes reference valid patients
- [ ] **Episode-Event Links**: All events reference valid episodes
- [ ] **User References**: All created_by/modified_by reference valid users

---

## 2. Performance Validation

### 2.1 Query Performance
- [ ] **Single Patient Retrieval**: < 100ms average
- [ ] **Patient Search by HOS Number**: < 200ms average
- [ ] **Patient Search by NHS Number**: < 200ms average
- [ ] **Patient Search by Name**: < 500ms average
- [ ] **Episode List for Patient**: < 300ms average
- [ ] **Event List for Episode**: < 300ms average

### 2.2 Aggregation Performance
- [ ] **Patient Count Queries**: < 500ms
- [ ] **Episode Statistics**: < 1000ms
- [ ] **Event Count by Type**: < 1000ms

### 2.3 Concurrent Load Testing
- [ ] **10 Concurrent Users**: Response times within threshold
- [ ] **50 Concurrent Users**: Response times within 2x threshold
- [ ] **100 Concurrent Users**: No errors, response times within 5x threshold

---

## 3. Functional Validation

### 3.1 Patient Operations
- [ ] **Search by Hospital Number**: Returns correct patient
- [ ] **Search by NHS Number**: Returns correct patient
- [ ] **Search by Name**: Returns relevant patients
- [ ] **Patient View Page**: All data displays correctly
- [ ] **Patient Summary**: Contact, GP, Practice info correct

### 3.2 Episode Operations
- [ ] **Episode List**: Shows all patient episodes
- [ ] **Episode Details**: All episode data accurate
- [ ] **Episode Creation**: New episodes visible immediately
- [ ] **Episode Updates**: Changes reflected correctly

### 3.3 Event Operations
- [ ] **Event List**: Shows all episode events
- [ ] **Event Details**: All event data accurate
- [ ] **Event Timeline**: Events in correct chronological order
- [ ] **Event Search**: Returns relevant events

---

## 4. Dual-Write Validation

### 4.1 Write Consistency
- [ ] **Patient Creates**: Both databases updated
- [ ] **Patient Updates**: Both databases updated
- [ ] **Episode Creates**: Both databases updated
- [ ] **Episode Updates**: Both databases updated
- [ ] **Event Creates**: Both databases updated
- [ ] **Event Updates**: Both databases updated

### 4.2 Sync Verification
- [ ] **No Orphaned Records**: All MariaDB records exist in Couchbase
- [ ] **Last Modified Dates**: Match within 1 second tolerance
- [ ] **Incremental Sync**: Running without errors

---

## 5. Fallback Validation

### 5.1 Graceful Degradation
- [ ] **Couchbase Timeout**: Falls back to MariaDB within 2s
- [ ] **Couchbase Down**: Application continues with MariaDB
- [ ] **Partial Couchbase Data**: Falls back for missing records

### 5.2 Error Handling
- [ ] **No User-Visible Errors**: Database errors not shown to users
- [ ] **Proper Logging**: Fallback events logged for monitoring
- [ ] **Alert Generation**: Critical fallback events trigger alerts

---

## 6. Infrastructure Validation

### 6.1 Couchbase Cluster
- [ ] **Node Health**: All nodes healthy
- [ ] **Replication**: Data replicated across nodes
- [ ] **Bucket Memory**: Usage below 80%
- [ ] **Disk Space**: Usage below 70%

### 6.2 Indexes
- [ ] **Primary Indexes**: Created on all collections
- [ ] **Secondary Indexes**: Created for common queries
- [ ] **Index Build Status**: All indexes built
- [ ] **Index Usage**: Queries using indexes (no full scans)

### 6.3 Monitoring
- [ ] **Query Latency Monitoring**: Dashboard configured
- [ ] **Error Rate Monitoring**: Alerts configured
- [ ] **Resource Monitoring**: CPU, memory, disk alerts set

---

## 7. Security Validation

### 7.1 Access Control
- [ ] **Bucket Credentials**: Properly secured
- [ ] **User Permissions**: Minimum necessary access
- [ ] **Connection Encryption**: TLS enabled

### 7.2 Data Protection
- [ ] **PHI Protection**: Sensitive data handled correctly
- [ ] **Audit Logging**: Database access logged
- [ ] **Backup Verification**: Backups tested and working

---

## 8. Test Suite Validation

### 8.1 Unit Tests
- [ ] **Migration Tests**: All passing
- [ ] **Service Tests**: All passing
- [ ] **Component Tests**: All passing

### 8.2 Integration Tests
- [ ] **Dual-Write Tests**: All passing
- [ ] **Query Tests**: All passing
- [ ] **Consistency Tests**: All passing

### 8.3 E2E Tests
- [ ] **Patient Search**: All passing
- [ ] **Patient Workflow**: All passing
- [ ] **Data Consistency**: All passing

---

## 9. Documentation Validation

### 9.1 Runbooks
- [ ] **Cutover Runbook**: Reviewed and approved
- [ ] **Rollback Runbook**: Tested and verified
- [ ] **Incident Response**: Procedures documented

### 9.2 Communication
- [ ] **Stakeholder Notification**: All stakeholders informed
- [ ] **Support Team Briefed**: On-call team aware of cutover
- [ ] **User Communication**: Users notified if needed

---

## 10. Final Approval

### Sign-off Required
- [ ] **Engineering Lead**: ______________________ Date: __________
- [ ] **QA Lead**: ______________________ Date: __________
- [ ] **Operations**: ______________________ Date: __________
- [ ] **Security**: ______________________ Date: __________
- [ ] **Product Owner**: ______________________ Date: __________

---

## Cutover Decision

**All checklist items must be completed before proceeding.**

- Total Items: 79
- Items Passed: ___
- Items Failed: ___
- Items Skipped (with justification): ___

**Decision**: [ ] PROCEED WITH CUTOVER  [ ] DELAY CUTOVER

**Reason (if delayed)**: __________________________________________________

---

## Notes

_Additional observations or concerns:_

