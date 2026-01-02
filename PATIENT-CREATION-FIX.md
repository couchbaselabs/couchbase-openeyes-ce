# Patient Creation Fix - OphCoDocument Module Null Safety

## Issue Fixed
**Problem**: Patient creation crashed with "Attempt to read property 'id' on null" error when OphCoDocument module was not installed or not fully migrated.

**Error Location**: `PatientController::actionPerformReferralDoc()` at line 2458

## Root Cause
The method assumed that:
1. EventType with name 'Document' exists
2. OphCoDocument_Sub_Types with name 'Referral Letter' exists

When either was missing, accessing `->id` on null caused a crash.

## Solution Implemented
Added null-safety checks at the beginning of `actionPerformReferralDoc()` method in `protected/controllers/PatientController.php`:

1. **Check EventType 'Document' exists** before use
2. **Check OphCoDocument_Sub_Types 'Referral Letter' exists** before use
3. **Early return with success** if either is missing (graceful degradation)
4. **Log warnings** when documents can't be saved due to missing module

## Changes Made

### File: `protected/controllers/PatientController.php`

**Lines 2447-2466** (added):
```php
// Check if OphCoDocument module is installed and migrated
$documentEventType = EventType::model()->findByAttributes(array('name' => 'Document'));
if (!$documentEventType) {
    Yii::log(
        'Cannot save referral documents: OphCoDocument module not installed (EventType "Document" not found)',
        CLogger::LEVEL_WARNING,
        'application.controllers.PatientController'
    );
    return true; // Don't fail patient creation, just skip documents
}

$referralLetterSubType = OphCoDocument_Sub_Types::model()->findByAttributes(array('name' => 'Referral Letter'));
if (!$referralLetterSubType) {
    Yii::log(
        'Cannot save referral documents: OphCoDocument_Sub_Types "Referral Letter" not found',
        CLogger::LEVEL_WARNING,
        'application.controllers.PatientController'
    );
    return true; // Don't fail patient creation, just skip documents
}
```

**Lines 2479, 2481** (modified):
```php
$event->event_type_id = $documentEventType->id;  // Use cached object instead of re-querying
$referral_letter_type_id = $referralLetterSubType->id;  // Use cached object instead of re-querying
```

## Benefits

1. **No more crashes** - Patient creation works even without OphCoDocument module
2. **Graceful degradation** - Referral documents are skipped, but patient is still created
3. **Clear logging** - Admins are warned via logs when documents can't be saved
4. **Backward compatible** - No behavior change when module is properly installed
5. **Performance improvement** - Reuses queried objects instead of re-querying

## Testing

✅ PHP syntax validation passed
✅ No syntax errors in modified file

## Dependencies

The OphCoDocument module creates:
- **Event Type**: 'Document' (created in migration `m161117_143520_event_creation.php`)
- **Sub Type**: 'Referral Letter' (created in migration `m161117_212912_add_left_right_document_ids.php`)

If these migrations haven't run, the fix ensures patient creation doesn't crash.

## Date
December 23, 2025
