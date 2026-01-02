#!/usr/bin/env python3
"""
Script to safely add Couchbase support to Phase 12 models.
This adds the trait, methods, and hooks to each model.
"""

import os
import re

# Template for simple models (no embedded relations)
SIMPLE_TEMPLATE = """
    /**
     * Get the Couchbase scope for this model
     * @return string
     */
    public function couchbaseScope()
    {
        return 'admin';
    }

    /**
     * Get the Couchbase collection name
     * @return string
     */
    public function couchbaseCollection()
    {
        return '{collection}';
    }

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
"""

# Models to update with their collection names
MODELS = {
    'protected/models/SettingInstitution.php': 'setting_institution',
    'protected/models/SettingSite.php': 'setting_site',
    'protected/models/SettingFirm.php': 'setting_firm',
    'protected/models/SettingUser.php': 'setting_user',
    'protected/models/SettingGroup.php': 'setting_group',
    'protected/models/SettingFieldType.php': 'setting_field_type',
    'protected/models/UserAuthentication.php': 'user_authentication',
    'protected/models/UserAuthenticationMethod.php': 'user_authentication_method',
    'protected/models/AuthItem.php': 'auth_item',
}

def add_couchbase_support(filepath, collection_name):
    """Add Couchbase support to a model file."""
    
    if not os.path.exists(filepath):
        print(f"❌ File not found: {filepath}")
        return False
    
    with open(filepath, 'r') as f:
        content = f.read()
    
    # Check if already has the trait
    if 'use CouchbaseModelBridge' in content:
        print(f"⏭️  {os.path.basename(filepath)} already has Couchbase support")
        return True
    
    # Add the use statement
    content = re.sub(
        r'(use OE\\factories\\models\\traits\\HasFactory;)',
        r'\1\nuse OE\\Models\\Traits\\CouchbaseModelBridge;',
        content
    )
    
    # Add the trait to the class
    content = re.sub(
        r'(use HasFactory;)',
        r'\1\n    use CouchbaseModelBridge;',
        content
    )
    
    # Add methods before the last closing brace
    methods = SIMPLE_TEMPLATE.replace('{collection}', collection_name)
    content = re.sub(r'(\n})$', methods + r'\1', content)
    
    # Write back
    with open(filepath, 'w') as f:
        f.write(content)
    
    print(f"✅ Added Couchbase support to: {os.path.basename(filepath)}")
    return True

def main():
    print("=" * 70)
    print("Adding Couchbase Support to Phase 12 Models")
    print("=" * 70)
    print()
    
    success_count = 0
    for filepath, collection in MODELS.items():
        if add_couchbase_support(filepath, collection):
            success_count += 1
        print()
    
    print("=" * 70)
    print(f"Complete! Successfully updated {success_count}/{len(MODELS)} files")
    print("=" * 70)

if __name__ == '__main__':
    main()
