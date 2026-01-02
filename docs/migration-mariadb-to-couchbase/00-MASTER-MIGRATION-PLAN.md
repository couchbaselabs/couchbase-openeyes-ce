# OpenEyes MariaDB to Couchbase Migration - Master Plan

## Executive Summary

This document outlines the comprehensive migration strategy for transitioning the OpenEyes electronic medical record (EMR) system from MariaDB (MySQL-compatible) to Couchbase NoSQL database. The migration is divided into 10 phases to ensure minimal disruption, data integrity, and maintainability.

## Current Architecture Overview

### Database Layer
- **Current Database**: MariaDB/MySQL
- **ORM Framework**: Yii 1.x CActiveRecord
- **Connection Class**: `OEDbConnection` (extends `CDbConnection`)
- **Migration System**: Custom `OEMigration` class (extends `CDbMigration`)
- **Base Model**: `BaseActiveRecord` (extends `CActiveRecord`)

### Key Components Requiring Migration
1. **Core Models** (~290+ models in `/protected/models/`)
2. **Module Models** (~500+ models across 35+ modules)
3. **Migration Scripts** (~530+ migration files)
4. **Services Layer** (~45+ service classes)
5. **Database Configuration** (`/protected/config/core/common.php`)

## Migration Phases Overview

| Phase | Name | Duration Est. | Dependencies |
|-------|------|---------------|--------------|
| 1 | Infrastructure & Couchbase Setup | 1-2 weeks | None |
| 2 | Abstract Database Layer | 2-3 weeks | Phase 1 |
| 3 | Data Modeling & Schema Translation | 3-4 weeks | Phase 2 |
| 4 | Core Model Migration | 4-6 weeks | Phase 3 |
| 5 | Module Model Migration | 6-8 weeks | Phase 4 |
| 6 | Query Migration (SQL to N1QL) | 3-4 weeks | Phase 5 |
| 7 | Services Layer Migration | 2-3 weeks | Phase 6 |
| 8 | Data Migration Scripts | 2-3 weeks | Phase 7 |
| 9 | Testing & Validation | 3-4 weeks | Phase 8 |
| 10 | Deployment & Cutover | 1-2 weeks | Phase 9 |

**Total Estimated Duration**: 23-35 weeks

## Critical Success Factors

1. **Zero Data Loss**: All patient records, clinical data, and audit trails must be preserved
2. **Referential Integrity**: Document relationships must maintain logical consistency
3. **Performance**: Query performance must meet or exceed current MariaDB performance
4. **Rollback Capability**: Each phase must support rollback to previous state
5. **Compliance**: HIPAA and medical data regulations must be maintained

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Data Loss | Low | Critical | Comprehensive backup strategy, staged migration |
| Performance Degradation | Medium | High | Extensive benchmarking, index optimization |
| Application Downtime | Medium | High | Blue-green deployment, feature flags |
| Complex Query Failures | High | Medium | Query mapping documentation, testing |
| Team Knowledge Gap | Medium | Medium | Training, documentation, pair programming |

## Document Index

1. [Phase 1: Infrastructure & Couchbase Setup](./01-PHASE-INFRASTRUCTURE-SETUP.md)
2. [Phase 2: Abstract Database Layer](./02-PHASE-ABSTRACT-DATABASE-LAYER.md)
3. [Phase 3: Data Modeling & Schema Translation](./03-PHASE-DATA-MODELING.md)
4. [Phase 4: Core Model Migration](./04-PHASE-CORE-MODEL-MIGRATION.md)
5. [Phase 5: Module Model Migration](./05-PHASE-MODULE-MODEL-MIGRATION.md)
6. [Phase 6: Query Migration](./06-PHASE-QUERY-MIGRATION.md)
7. [Phase 7: Services Layer Migration](./07-PHASE-SERVICES-MIGRATION.md)
8. [Phase 8: Data Migration Scripts](./08-PHASE-DATA-MIGRATION.md)
9. [Phase 9: Testing & Validation](./09-PHASE-TESTING-VALIDATION.md)
10. [Phase 10: Deployment & Cutover](./10-PHASE-DEPLOYMENT-CUTOVER.md)

## Technology Stack

### Target Architecture
- **Database**: Couchbase Server 7.x
- **SDK**: Couchbase PHP SDK 4.x
- **Query Language**: SQL++ (N1QL)
- **PHP Version**: 8.1+ (required for SDK 4.x)

### Key Couchbase Concepts Mapping

| MySQL/MariaDB | Couchbase |
|---------------|-----------|
| Database | Bucket |
| Table | Collection (within Scope) |
| Row | Document (JSON) |
| Column | Field |
| Primary Key | Document Key |
| Foreign Key | Document Reference |
| Index | GSI (Global Secondary Index) |
| JOIN | NEST/UNNEST/JOIN |

## Governance

- **Technical Lead**: TBD
- **Review Cadence**: Weekly progress reviews
- **Approval Gates**: Each phase requires sign-off before proceeding
- **Documentation**: All changes must be documented with rationale

---

*Last Updated: [Current Date]*
*Version: 1.0*
