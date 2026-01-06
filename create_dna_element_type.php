<?php

// Define Yii constants
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_TRACE_LEVEL') or define('YII_TRACE_LEVEL', 3);

// Get the dirname
$dirname = dirname(__FILE__);

// Load Yii framework
if (file_exists($dirname . '/vendor/autoload.php')) {
    require_once($dirname . '/vendor/autoload.php');
}
$yii = $dirname . '/vendor/yiisoft/yii/framework/yii.php';
require_once($yii);

// Load Yii configuration
$config = require_once $dirname . '/protected/config/main.php';
$app = Yii::createWebApplication($config);

// Get the database connection
$db = Yii::app()->db;

try {
    echo "<pre>";
    
    // Step 0: Get or create the Investigation events group
    echo "Step 0: Getting/Creating Investigation events group...\n";
    
    $group = $db->createCommand("SELECT id FROM event_group WHERE name = 'Investigation events'")->queryRow();
    
    if (!$group) {
        echo "  Group not found, creating it...\n";
        try {
            $db->createCommand("INSERT INTO event_group (name) VALUES ('Investigation events')")->execute();
            echo "  INSERT succeeded.\n";
        } catch (Exception $e) {
            echo "  INSERT failed: " . $e->getMessage() . "\n";
        }
        $group = $db->createCommand("SELECT id FROM event_group WHERE name = 'Investigation events'")->queryRow();
        if ($group && isset($group['id'])) {
            echo "  Created/Found group with ID: " . $group['id'] . "\n";
        } else {
            echo "  ERROR: Could not find group after creation.\n";
            echo "  Group value: " . var_export($group, true) . "\n";
            exit(1);
        }
    } else {
        if (isset($group['id'])) {
            echo "  Group exists with ID: " . $group['id'] . "\n";
        } else {
            echo "  ERROR: Group row missing 'id' column.\n";
            echo "  Group value: " . var_export($group, true) . "\n";
            exit(1);
        }
    }
    
    // First, get or create the OphInDnaextraction event type
    echo "\nStep 1: Getting/Creating OphInDnaextraction event type...\n";
    
    $event_type = $db->createCommand("SELECT id FROM event_type WHERE class_name = 'OphInDnaextraction'")->queryRow();
    
    if (!$event_type) {
        echo "  Event type not found, creating it...\n";
        
        // Insert the event type
        $sql = "INSERT INTO event_type (class_name, name, event_group_id) VALUES (:class_name, :name, :group_id)";
        $cmd = $db->createCommand($sql);
        $cmd->bindParam(':class_name', $class_name = 'OphInDnaextraction', PDO::PARAM_STR);
        $cmd->bindParam(':name', $name = 'DNA extraction', PDO::PARAM_STR);
        $cmd->bindParam(':group_id', $group_id = $group['id'], PDO::PARAM_INT);
        $cmd->execute();
        
        $event_type = $db->createCommand("SELECT id FROM event_type WHERE class_name = 'OphInDnaextraction'")->queryRow();
        echo "  Created event type with ID: " . $event_type['id'] . "\n";
    } else {
        echo "  Event type exists with ID: " . $event_type['id'] . "\n";
    }
    
    // Step 2: Create the DNA extraction element type
    echo "\nStep 2: Creating DNA extraction element type...\n";
    
    $element_type = $db->createCommand("SELECT id FROM element_type WHERE class_name = 'Element_OphInDnaextraction_DnaExtraction'")->queryRow();
    
    if ($element_type) {
        echo "  Element type already exists with ID: " . $element_type['id'] . "\n";
    } else {
        echo "  Element type not found, creating it...\n";
        
        $sql = "INSERT INTO element_type (event_type_id, class_name, name, display_order, `default`, required) VALUES (:event_type_id, :class_name, :name, :display_order, 1, 1)";
        $cmd = $db->createCommand($sql);
        $cmd->bindParam(':event_type_id', $event_type['id'], PDO::PARAM_INT);
        $cmd->bindParam(':class_name', $class_name = 'Element_OphInDnaextraction_DnaExtraction', PDO::PARAM_STR);
        $cmd->bindParam(':name', $name = 'DNA extraction', PDO::PARAM_STR);
        $cmd->bindParam(':display_order', $display_order = 1, PDO::PARAM_INT);
        $cmd->execute();
        
        $element_type = $db->createCommand("SELECT id FROM element_type WHERE class_name = 'Element_OphInDnaextraction_DnaExtraction'")->queryRow();
        echo "  Created element type with ID: " . $element_type['id'] . "\n";
    }
    
    // Step 3: Create the DNA tests (withdrawals) element type
    echo "\nStep 3: Creating DNA tests element type...\n";
    
    $dna_tests_type = $db->createCommand("SELECT id FROM element_type WHERE class_name = 'Element_OphInDnaextraction_DnaTests'")->queryRow();
    
    if ($dna_tests_type) {
        echo "  DNA Tests element type already exists with ID: " . $dna_tests_type['id'] . "\n";
    } else {
        echo "  DNA Tests element type not found, creating it...\n";
        
        $sql = "INSERT INTO element_type (event_type_id, class_name, name, display_order) VALUES (:event_type_id, :class_name, :name, :display_order)";
        $cmd = $db->createCommand($sql);
        $cmd->bindParam(':event_type_id', $event_type['id'], PDO::PARAM_INT);
        $cmd->bindParam(':class_name', $class_name = 'Element_OphInDnaextraction_DnaTests', PDO::PARAM_STR);
        $cmd->bindParam(':name', $name = 'DNA Withdrawals', PDO::PARAM_STR);
        $cmd->bindParam(':display_order', $display_order = 20, PDO::PARAM_INT);
        $cmd->execute();
        
        echo "  Created DNA Tests element type.\n";
    }
    
    echo "\nSUCCESS: All element types are now configured!\n";
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<pre>";
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    echo "</pre>";
    exit(1);
}
