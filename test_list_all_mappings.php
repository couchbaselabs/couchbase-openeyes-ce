<?php
// Simple script to list all mappings
// This is a read-only test file

require_once 'protected/components/BaseController.php';

class TestController extends BaseController {
    public function actionListMappings() {
        // Get all mappings
        $mappings = SiteSubspecialtyAnaestheticAgent::model()->findAll();
        echo "Total mappings: " . count($mappings) . "\n";
        foreach ($mappings as $mapping) {
            echo "ID: " . $mapping->id . ", Site: " . $mapping->site_id . ", Subspecialty: " . $mapping->subspecialty_id . ", Agent: " . $mapping->anaesthetic_agent_id . "\n";
        }
    }
}

// Initialize Yii
$config = require 'protected/config/main.php';
$app = Yii::createWebApplication($config);

// Create and run the controller
$controller = new TestController();
$controller->actionListMappings();
?>
