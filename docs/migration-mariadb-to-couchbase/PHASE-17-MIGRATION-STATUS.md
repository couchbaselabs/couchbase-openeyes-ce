# Phase 17: Complete Model Migration Status

## Summary

This phase focuses on adding CouchbaseModelBridge trait to ALL remaining models (1,177) to ensure complete migration from MariaDB to Couchbase.

## Progress

### Completed
| Category | Models | Status |
|----------|--------|--------|
| AddCouchbaseBridgeCommand | 1 | Created |
| Collection Map Update | - | Comprehensive mapping added |
| OphTrLaser Module | 11 | Migrated |
| Patient Core Models | 17 | Migrated |
| **Total Migrated This Session** | **28** | **Complete** |

### Previously Migrated (Phase 1-16)
| Category | Models |
|----------|--------|
| Core Clinical Models | 41 |
| **Total Previously Migrated** | **41** |

### Overall Progress
- **Total Migrated:** 69 models (41 previous + 28 this session)
- **Remaining:** ~1,149 models
- **Progress:** ~5.7% complete

## Files Created/Modified

### New Files
1. `protected/commands/AddCouchbaseBridgeCommand.php` - Automation script
2. `protected/config/couchbase-collection-map.php` - Comprehensive scope/collection mapping (400+ lines)

### Modified Models - OphTrLaser Module (11)
- Element_OphTrLaser_AnteriorSegment.php
- Element_OphTrLaser_Comments.php
- Element_OphTrLaser_Fundus.php
- Element_OphTrLaser_PosteriorPole.php
- Element_OphTrLaser_Site.php
- Element_OphTrLaser_Treatment.php
- OphTrLaser_LaserProcedure.php
- OphTrLaser_LaserProcedureAssignment.php
- OphTrLaser_LaserProcedure_Institution.php
- OphTrLaser_Site_Laser.php (original gap discovery)
- OphTrLaser_Type.php

### Modified Models - Patient Core (17)
- PatientAllergyAssignment.php
- PatientContactAssignment.php
- PatientContactAssociate.php
- PatientIdentifier.php
- PatientIdentifierStatus.php
- PatientIdentifierType.php
- PatientIdentifierTypeDisplayOrder.php
- PatientMeasurement.php
- PatientMergeRequest.php
- PatientOphInfo.php
- PatientOphInfoCviStatus.php
- PatientReferral.php
- PatientRiskAssignment.php
- PatientShortcode.php
- PatientStatistic.php
- PatientStatisticDatapoint.php
- PatientStatisticType.php
- PatientUserReferral.php

## Remaining Work

### High Priority (Phase 2)
| Module | Est. Models | Priority |
|--------|-------------|----------|
| OphCiExamination | ~300 | CRITICAL |
| OphTrOperationnote | ~86 | HIGH |
| OphTrOperationbooking | ~46 | HIGH |
| OphCoCorrespondence | ~32 | HIGH |

### Medium Priority (Phase 3)
| Module | Est. Models |
|--------|-------------|
| OphTrConsent | ~56 |
| OphCoTherapyapplication | ~29 |
| OphTrOperationchecklists | ~29 |
| OphCoCvi | ~23 |
| OphTrIntravitrealinjection | ~20 |
| OphDrPrescription | ~12 |
| OphInBiometry | ~14 |
| Other modules | ~300 |

### Core Models Remaining
- Audit models (~7)
- User models (~9)
- Settings models (~6)
- Reference data models (~80)
- Event/Episode models (~11)
- Worklist/Pathway models (~25)
- Other core models (~80)

## Automation Tool Usage

```bash
# Scan all models and show migration status
php protected/yiic addcouchbasebridge scan

# Add bridge to all models (with confirmation)
php protected/yiic addcouchbasebridge add

# Add bridge to all models in a specific module
php protected/yiic addcouchbasebridge addModule OphCiExamination

# Add bridge to a specific model
php protected/yiic addcouchbasebridge addModel PatientIdentifier

# Dry run (preview changes without modification)
php protected/yiic addcouchbasebridge add --dryRun
```

## Commits

| Hash | Description |
|------|-------------|
| 05a3257 | feat: Phase 17 - Add CouchbaseModelBridge to 28 models |

## Next Steps

1. **Continue Core Models**
   - Migrate remaining ~220 core models
   - Priority: Audit, User, Settings, Reference data

2. **Critical Modules**
   - OphCiExamination (largest module)
   - OphTrOperationnote
   - OphTrOperationbooking

3. **Create Couchbase Collections**
   - Create scopes for each module
   - Create collections for each model

4. **Data Migration**
   - Run full data migration after all models have trait
   - Validate data integrity

## Timeline Estimate

| Phase | Duration | Status |
|-------|----------|--------|
| Core Models | 1 week | In Progress |
| Critical Modules | 1.5 weeks | Pending |
| Secondary Modules | 1.5 weeks | Pending |
| Testing/Validation | 1 week | Pending |
| **Total** | **5 weeks** | - |
