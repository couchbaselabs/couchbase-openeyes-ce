<?php
require_once 'index.php';

try {
    $db = Yii::app()->db;
    
    // Check if OphInDnasample event type already exists
    $existing = $db->createCommand("SELECT id FROM event_type WHERE class_name = 'OphInDnasample'")->queryRow();
    
    if ($existing) {
        echo "OphInDnasample event type already exists with ID: " . $existing['id'] . "\n";
    } else {
        echo "Creating OphInDnasample event type...\n";
        
        // First, check if the Investigation events group exists, if not just use NULL or find another group
        $group_id = null;
        try {
            $group = $db->createCommand("SELECT id FROM event_group WHERE name = 'Investigation events'")->queryRow();
            if ($group) {
                $group_id = $group['id'];
                echo "Using existing Investigation events group with ID: " . $group_id . "\n";
            } else {
                // Try to find any event group
                $any_group = $db->createCommand("SELECT id FROM event_group LIMIT 1")->queryRow();
                if ($any_group) {
                    $group_id = $any_group['id'];
                    echo "Using first available event_group with ID: " . $group_id . "\n";
                } else {
                    echo "No event_group found, creating one without group\n";
                }
            }
        } catch (Exception $e) {
            echo "Warning: Could not check event_group table: " . $e->getMessage() . "\n";
        }
        
        // Create the OphInDnasample event type
        if ($group_id) {
            $db->createCommand("INSERT INTO event_type (name, class_name, event_group_id, display_order) 
                                VALUES ('DNA sample', 'OphInDnasample', :group_id, 1)")
                ->bindParam(':group_id', $group_id)
                ->execute();
        } else {
            $db->createCommand("INSERT INTO event_type (name, class_name, display_order) 
                                VALUES ('DNA sample', 'OphInDnasample', 1)")
                ->execute();
        }
        
        // Retrieve the newly created event type
        $new_event_type = $db->createCommand("SELECT id FROM event_type WHERE class_name = 'OphInDnasample'")->queryRow();
        if ($new_event_type) {
            $event_type_id = $new_event_type['id'];
            echo "Created OphInDnasample event type with ID: " . $event_type_id . "\n";
        } else {
            throw new Exception("Failed to create OphInDnasample event type");
        }
    }
    
    // Now check if the Element_OphInDnasample_Sample element type exists
    $event_type = $db->createCommand("SELECT id FROM event_type WHERE class_name = 'OphInDnasample'")->queryRow();
    $event_type_id = $event_type['id'];
    
    $element_type = $db->createCommand("SELECT id FROM element_type WHERE class_name = 'Element_OphInDnasample_Sample'")->queryRow();
    
    if ($element_type) {
        echo "Element type already exists with ID: " . $element_type['id'] . "\n";
    } else {
        echo "Creating Element_OphInDnasample_Sample element type...\n";
        
        $db->createCommand("INSERT INTO element_type (name, class_name, event_type_id, display_order) 
                            VALUES ('Sample', 'Element_OphInDnasample_Sample', :event_type_id, 1)")
            ->bindParam(':event_type_id', $event_type_id)
            ->execute();
        
        $element_id = $db->lastInsertID;
        echo "Created Element_OphInDnasample_Sample element type with ID: " . $element_id . "\n";
    }
    
    echo "\nSetup complete! OphInDnasample event type is now properly configured.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
?>
