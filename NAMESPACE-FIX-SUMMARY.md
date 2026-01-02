# TypeTransformer Namespace Fix

## Issue
The application was throwing the error:
```
Class "OE\Couchbase\Transformers\TypeTransformer" not found
```

## Root Cause
The `OE\Couchbase\Transformers\TypeTransformer` class exists at `protected/models/couchbase/transformers/TypeTransformer.php`, but it wasn't being autoloaded because the Composer PSR-4 autoloader didn't have a mapping for this namespace.

The autoloader had:
```json
"OE\\Couchbase\\": "protected/components"
```

Which would look for the class at `protected/components/Transformers/TypeTransformer.php` (incorrect path).

## Fix Applied

### 1. Updated composer.json
Added a more specific namespace mapping that takes precedence:
```json
"OE\\Couchbase\\Transformers\\": "protected/models/couchbase/transformers"
```

This tells Composer to look for `OE\Couchbase\Transformers\*` classes in the correct directory.

### 2. Regenerated Autoloader
Ran `composer dump-autoload` inside the container to regenerate the autoloader with the new namespace mapping.

## Files Modified

- `composer.json` - Added PSR-4 autoload mapping for `OE\Couchbase\Transformers\`
- Regenerated `vendor/composer/autoload_psr4.php` and related files

## Note on TypeTransformer Classes

There are **two** TypeTransformer classes in the codebase:

1. **`OE\Couchbase\Transformers\TypeTransformer`** (at `protected/models/couchbase/transformers/TypeTransformer.php`)
   - Used by models for dual-write to Couchbase
   - Has methods: `toJson()`, `toMysql()`, `transformRow()`, `getJsonSchemaType()`
   - This is the one used by `CouchbaseModelBridge` trait

2. **`OE\Migration\TypeTransformer`** (at `protected/components/migration/TypeTransformer.php`)
   - Used by migration commands
   - Has method: `transform()`
   - Used during data migration from MariaDB to Couchbase

Both classes serve different purposes and should remain separate.

## Verification

After this fix, the models (Patient, Episode, Event, User) should be able to:
- Transform data types when saving to Couchbase
- Convert data types when reading from Couchbase
- Properly dual-write to both MariaDB and Couchbase

## Testing

To verify the fix works:

1. Try creating/updating an event via the web UI
2. Check application logs for any TypeTransformer errors
3. Run: `docker exec devcontainer-web-1 php -r "echo class_exists('OE\\Couchbase\\Transformers\\TypeTransformer') ? 'OK' : 'FAIL';"`

Expected output: `OK`
