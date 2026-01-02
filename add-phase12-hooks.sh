#!/bin/bash
# Script to add afterSave and afterDelete hooks to Phase 12 models

HOOKS='
    /**
     * Hook: After saving to MariaDB, sync to Couchbase
     */
    protected function afterSave()
    {
        parent::afterSave();
        $this->saveToCouchbase();
    }

    /**
     * Hook: After deleting from MariaDB, delete from Couchbase
     */
    protected function afterDelete()
    {
        parent::afterDelete();
        $this->deleteFromCouchbase();
    }
'

# List of files that need hooks
FILES=(
    "protected/models/SettingInstitution.php"
    "protected/models/SettingSite.php"
    "protected/models/SettingFirm.php"
    "protected/models/SettingUser.php"
    "protected/models/SettingGroup.php"
    "protected/models/SettingFieldType.php"
    "protected/models/UserAuthentication.php"
    "protected/models/InstitutionAuthentication.php"
    "protected/models/UserAuthenticationMethod.php"
    "protected/models/AuthItem.php"
    "protected/models/AuthAssignment.php"
)

for file in "${FILES[@]}"; do
    if [ ! -f "$file" ]; then
        echo "❌ File not found: $file"
        continue
    fi
    
    # Check if hooks already exist
    if grep -q "function afterSave()" "$file"; then
        echo "⏭️  Hooks already exist in: $(basename $file)"
        continue
    fi
    
    # Add hooks before the last closing brace
    # Create temp file with hooks added
    head -n -1 "$file" > "${file}.tmp"
    echo "$HOOKS" >> "${file}.tmp"
    tail -n 1 "$file" >> "${file}.tmp"
    
    # Replace original file
    mv "${file}.tmp" "$file"
    echo "✅ Added hooks to: $(basename $file)"
done

echo ""
echo "Done! All hooks added."
