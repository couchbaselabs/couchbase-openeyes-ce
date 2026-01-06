<?php
/**
 * Script to create a test letter setting
 */

// Initialize Yii
require_once(dirname(__FILE__) . '/index.php');

try {
    // Find or create a SettingFieldType for text
    $field_type = SettingFieldType::model()->find('name = ?', array('Text'));
    if (!$field_type) {
        echo "Creating SettingFieldType...\n";
        $field_type = new SettingFieldType();
        $field_type->name = 'Text';
        if (!$field_type->save()) {
            throw new Exception("Failed to create SettingFieldType: " . print_r($field_type->errors, true));
        }
    }
    echo "SettingFieldType ID: " . $field_type->id . "\n";
    
    // Create a test letter setting
    echo "Creating OphCoCorrespondenceLetterSettings...\n";
    $setting = new OphCoCorrespondenceLetterSettings();
    $setting->key = 'test_setting_key';
    $setting->name = 'Test Setting';
    $setting->field_type_id = $field_type->id;
    $setting->default_value = 'Default Test Value';
    $setting->data = serialize(array('option1' => 'Option 1', 'option2' => 'Option 2'));
    $setting->display_order = 1;
    $setting->created_user_id = 1;  // admin user
    
    if (!$setting->save()) {
        throw new Exception("Failed to save setting: " . print_r($setting->errors, true));
    }
    
    echo "Successfully created letter setting with ID: " . $setting->id . "\n";
    echo "Key: " . $setting->key . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
