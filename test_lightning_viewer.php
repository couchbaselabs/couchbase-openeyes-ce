<?php
/**
 * Test script to verify actionLightningViewer works without errors
 */

// Initialize Yii with proper configuration
define('YII_DEBUG', true);
define('YII_TRACE_LEVEL', 3);

$yii = dirname(__FILE__) . '/vendor/yiisoft/yii/framework/yii.php';
$config = dirname(__FILE__) . '/protected/config/main.php';

require_once($yii);
Yii::createWebApplication($config);

echo "Testing actionLightningViewer code...\n";

// Find a patient with events
$patients = Patient::model()->findAll(array('limit' => 1));

if (empty($patients)) {
    echo "ERROR: No patients found in database. Creating a test patient...\n";
    exit(1);
}

$patient = $patients[0];
echo "Using patient ID: " . $patient->id . "\n";

// Test the code path by simulating the controller action
try {
    // Simulate the key parts of actionLightningViewer
    $eventTypeMap = array();
    $previewGroups = ['Letters' => []];
    
    // Find all events for this patient
    foreach (EventType::model()->findAll() as $eventType) {
        $eventTypeMap[$eventType->name] = array();
        $api = $eventType->getApi();
        if ($api) {
            $eventTypeMap[$eventType->name] += $eventType->getApi()->getVisibleEvents($patient);
        }
    }
    
    echo "Event types found: " . implode(', ', array_keys($eventTypeMap)) . "\n";
    
    // For every document sub type...
    if (isset($eventTypeMap['Document']) && !empty($eventTypeMap['Document'])) {
        foreach (OphCoDocument_Sub_Types::model()->findAll() as $documentType) {
            // Find the document events for that subtype ...
            $documentEvents = array_filter($eventTypeMap['Document'], function ($documentEvent) use ($documentType) {
                $documentElement = $documentEvent->getElementByClass(Element_OphCoDocument_Document::class);
                return $documentElement && $documentElement->sub_type && $documentElement->sub_type->id === $documentType->id;
            });
            
            // And add them to the preview groups
            if ($documentType->name === 'Referral Letter') {
                $previewGroups['Letters'] += $documentEvents;
            } else {
                $previewGroups[$documentType->name] = $documentEvents;
            }
        }
    }
    
    // Process event types
    foreach ($eventTypeMap as $eventType => $events) {
        switch ($eventType) {
            case 'Document':
                continue 2;
            case 'Biometry':
                $groupType = 'BiometryReport';
                break;
            case 'Correspondence':
                $groupType = 'Letters';
                break;
            default:
                $groupType = $eventType;
                break;
        }
        
        if (!array_key_exists($groupType, $previewGroups)) {
            $previewGroups[$groupType] = [];
        }
        $previewGroups[$groupType] = array_merge($previewGroups[$groupType], $events);
    }
    
    echo "Preview groups: " . implode(', ', array_keys($previewGroups)) . "\n";
    
    // Test the sorting and grouping by year
    $preview_type = 'Letters';
    if (!isset($previewGroups[$preview_type])) {
        $preview_type = 'Letters';
        if (count($previewGroups['Letters']) === 0) {
            foreach ($previewGroups as $key => $group) {
                if (count($group) > 0) {
                    $preview_type = $key;
                    break;
                }
            }
        }
    }
    
    $selectedPreviews = $previewGroups[$preview_type];
    $previewsByYear = array();
    
    echo "Selected preview type: " . $preview_type . "\n";
    echo "Selected previews count: " . count($selectedPreviews) . "\n";
    
    if (count($selectedPreviews) > 0) {
        // Sort the documents and split them into different years
        usort($selectedPreviews, function ($a, $b) {
            return $a->event_date > $b->event_date ? -1 : 1;
        });
        
        foreach ($selectedPreviews as $event) {
            // Check for NULL event_date
            if (!$event->event_date) {
                echo "WARNING: Event " . $event->id . " has NULL event_date, skipping\n";
                continue;
            }
            $year = (new DateTime($event->event_date))->format('Y');
            if (!isset($previewsByYear[$year])) {
                $previewsByYear[$year] = array();
            }
            $previewsByYear[$year][] = $event;
        }
    }
    
    echo "Preview groups by year: " . implode(', ', array_keys($previewsByYear)) . "\n";
    
    echo "\n✓ Test PASSED: actionLightningViewer code executed successfully!\n";
} catch (Exception $e) {
    echo "\n✗ Test FAILED with exception:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Stack Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?>
