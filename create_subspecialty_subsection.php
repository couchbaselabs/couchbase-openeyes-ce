<?php
// Direct subspecialty subsection creation for testing
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set the YII_DEBUG and YII_TRACE_LEVEL 
defined('YII_DEBUG') or define('YII_DEBUG',true);
defined('YII_TRACE_LEVEL') or define('YII_TRACE_LEVEL',3);

// This will include index.php which will invoke Yii
require dirname(__FILE__).'/index.php';

try {
    
    // Get the Glaucoma subspecialty (ID 3 from our earlier testing)
    $subspecialty = Subspecialty::model()->findByPk(3);
    if (!$subspecialty) {
        throw new Exception("Glaucoma subspecialty (ID 3) not found!");
    }

    // Create subspecialty subsection
    $subsection = new SubspecialtySubsection();
    $subsection->subspecialty_id = $subspecialty->id;
    $subsection->name = "Test Subsection for Testing";

    if ($subsection->save()) {
        $response = [
            'success' => true,
            'subsection_id' => $subsection->id,
            'subspecialty_id' => $subspecialty->id,
            'subspecialty_name' => $subspecialty->name,
            'subsection_name' => $subsection->name,
            'message' => "Subspecialty subsection created successfully!",
            'edit_url' => "/subspecialtySubsections/edit?id=" . $subsection->id . "&subspecialty_id=" . $subspecialty->id
        ];
    } else {
        $response = [
            'success' => false,
            'errors' => $subsection->errors,
            'message' => "Error creating subspecialty subsection"
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit;
}
?>
