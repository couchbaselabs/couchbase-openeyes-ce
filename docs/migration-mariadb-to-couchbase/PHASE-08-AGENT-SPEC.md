# Phase 8: Data Migration - Agent Executable Specification

**Version**: 1.0.0  
**Date**: December 22, 2025  
**Status**: IMPLEMENTED  
**Total Tasks**: 18 tasks across 6 sections

---

## Implementation Summary

Phase 8 implements a comprehensive data migration framework for transferring data from MariaDB to Couchbase. The implementation includes:

### Components Created

| File | Purpose |
|------|---------|
| `protected/components/migration/TypeTransformer.php` | MySQL to PHP/Couchbase type transformation |
| `protected/components/migration/TableMigrator.php` | Migrator interface |
| `protected/components/migration/DefaultTableMigrator.php` | Generic table migrator |
| `protected/components/migration/PatientMigrator.php` | Patient migrator with embedded data |
| `protected/components/migration/EpisodeMigrator.php` | Episode migrator with firm/diagnosis |
| `protected/components/migration/EventMigrator.php` | Event migrator with denormalized refs |
| `protected/commands/DataMigrationCommand.php` | Full migration command |
| `protected/commands/DataValidationCommand.php` | Data validation command |
| `protected/commands/IncrementalSyncCommand.php` | Incremental sync command |
| `protected/scripts/couchbase/run-full-migration.sh` | Migration shell script |

### Unit Tests Created

| File | Purpose |
|------|---------|
| `protected/tests/unit/components/migration/TypeTransformerTest.php` | Type transformation tests |
| `protected/tests/unit/components/migration/DefaultTableMigratorTest.php` | Migrator tests |
| `protected/tests/unit/components/migration/DataValidationTest.php` | Validation tests |

---

## Usage

### Full Migration

```bash
# Run full migration
php protected/yiic datamigration run

# With custom batch size
php protected/yiic datamigration run --batch=500

# Migrate specific tables
php protected/yiic datamigration run --tables=patient,episode,event

# Resume from checkpoint
php protected/yiic datamigration run --resume

# Dry run (no changes)
php protected/yiic datamigration run --dryRun
```

### Validation

```bash
# Validate all tables
php protected/yiic datavalidation run

# Show count comparison
php protected/yiic datavalidation counts

# Sample comparison
php protected/yiic datavalidation sample --table=patient --size=50
```

### Incremental Sync

```bash
# Sync since last run
php protected/yiic incrementalsync run

# Sync from specific date
php protected/yiic incrementalsync run --since="2024-01-01"

# Show pending changes
php protected/yiic incrementalsync status

# Run as daemon
php protected/yiic incrementalsync daemon --interval=60
```

### Shell Script

```bash
# Run full migration with validation
./protected/scripts/couchbase/run-full-migration.sh

# Dry run
./protected/scripts/couchbase/run-full-migration.sh --dry-run

# Skip validation
./protected/scripts/couchbase/run-full-migration.sh --skip-validation
```

---

## Features

### Migration Features
- Batch processing with configurable size
- Resumable migrations with progress checkpoints
- Migration order respects foreign key dependencies
- Specialized migrators for Patient, Episode, Event
- Error handling continues on individual record failures
- Detailed logging with timestamps

### Validation Features
- Count comparison between databases
- Sample-based integrity validation
- Field-by-field comparison
- Mismatch reporting with details
- Integrity score calculation

### Incremental Sync Features
- Syncs only changed records based on last_modified_date
- Daemon mode for continuous sync
- Status command shows pending changes
- Supports table-specific sync

---

## Definition of Done

- [x] TypeTransformer handles all MySQL types
- [x] TableMigrator interface defined
- [x] DefaultTableMigrator handles generic tables
- [x] PatientMigrator embeds contact/addresses
- [x] EpisodeMigrator embeds firm/diagnosis
- [x] EventMigrator includes denormalized references
- [x] DataMigrationCommand with batch/resume/dry-run
- [x] DataValidationCommand with counts/sample
- [x] IncrementalSyncCommand with daemon mode
- [x] Shell script for automation
- [x] Unit tests created

---

## Next Steps

1. Run full migration on test environment
2. Validate with >99% integrity score
3. Set up incremental sync cron job
4. Proceed to Phase 9: Testing & Validation
