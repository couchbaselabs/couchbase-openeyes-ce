<?php
/**
 * Generate sample examination data for testing Couchbase sync
 */
class GenerateSampleExaminationsCommand extends CConsoleCommand
{
    public function actionGenerate($count = 5)
    {
        echo "Generating {$count} sample examination events...\n\n";
        
        // Get or create patient
        $patient = Patient::model()->find();
        if (!$patient) {
            echo "ERROR: No patients found in database\n";
            return 1;
        }
        
        echo "Using patient ID: {$patient->id}\n";
        
        // Get episode
        $episode = Episode::model()->findByAttributes(['patient_id' => $patient->id]);
        if (!$episode) {
            echo "ERROR: No episode found for patient\n";
            return 1;
        }
        
        echo "Using episode ID: {$episode->id}\n\n";
        
        // Get event type
        $eventType = EventType::model()->findByAttributes(['class_name' => 'OphCiExamination']);
        if (!$eventType) {
            echo "ERROR: OphCiExamination event type not found\n";
            return 1;
        }
        
        $successCount = 0;
        $errorCount = 0;
        
        for ($i = 1; $i <= $count; $i++) {
            try {
                echo "Creating examination {$i}/{$count}...\n";
                
                // Get institution and site
                $institution = Institution::model()->find();
                $site = Site::model()->find();
                
                if (!$institution || !$site) {
                    echo "  ERROR: Institution or site not found\n";
                    $errorCount++;
                    continue;
                }
                
                // Create event
                $event = new Event();
                $event->event_type_id = $eventType->id;
                $event->episode_id = $episode->id;
                $event->institution_id = $institution->id;
                $event->site_id = $site->id;
                $event->event_date = date('Y-m-d H:i:s', strtotime("-{$i} days"));
                $event->created_user_id = 1;
                $event->last_modified_user_id = 1;
                $event->deleted = 0;
                $event->delete_pending = 0;
                
                if (!$event->save()) {
                    echo "  ERROR creating event: " . print_r($event->errors, true) . "\n";
                    $errorCount++;
                    continue;
                }
                
                echo "  Event created: ID {$event->id}\n";
                
                // Create Visual Acuity element
                if ($this->createVisualAcuity($event->id)) {
                    echo "  ✓ Visual Acuity added\n";
                } else {
                    echo "  ✗ Visual Acuity failed\n";
                }
                
                // Create IOP element
                if ($this->createIntraocularPressure($event->id)) {
                    echo "  ✓ IOP added\n";
                } else {
                    echo "  ✗ IOP failed\n";
                }
                
                // Create Refraction element
                if ($this->createRefraction($event->id)) {
                    echo "  ✓ Refraction added\n";
                } else {
                    echo "  ✗ Refraction failed\n";
                }
                
                // Create Diagnoses element
                if ($this->createDiagnoses($event->id)) {
                    echo "  ✓ Diagnoses added\n";
                } else {
                    echo "  ✗ Diagnoses failed\n";
                }
                
                echo "  Examination {$i} complete!\n\n";
                $successCount++;
                
            } catch (Exception $e) {
                echo "  ERROR: " . $e->getMessage() . "\n\n";
                $errorCount++;
            }
        }
        
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "Generation complete!\n";
        echo "Success: {$successCount}, Errors: {$errorCount}\n";
        echo str_repeat('=', 60) . "\n\n";
        
        echo "Next steps:\n";
        echo "1. Run sync command:\n";
        echo "   php protected/yiic.php couchbasemodulesync sync --module=OphCiExamination --verbose\n\n";
        echo "2. Verify in Couchbase:\n";
        echo "   SELECT * FROM `openeyes`.`clinical`.`examination` LIMIT 5;\n\n";
        
        return 0;
    }
    
    private function createVisualAcuity($eventId)
    {
        try {
            $va = new Element_OphCiExamination_VisualAcuity();
            $va->event_id = $eventId;
            $va->eye_id = 3; // Both eyes
            
            // Get method and unit IDs
            $method = Yii::app()->db->createCommand()
                ->select('id')
                ->from('ophciexamination_visualacuity_method')
                ->queryScalar();
            
            $unit = Yii::app()->db->createCommand()
                ->select('id')
                ->from('ophciexamination_visualacuity_unit')
                ->queryScalar();
            
            if (!$method || !$unit) {
                return false;
            }
            
            if ($va->save()) {
                // Create left eye reading
                $leftReading = new OphCiExamination_VisualAcuity_Reading();
                $leftReading->element_id = $va->id;
                $leftReading->side = 0; // Left
                $leftReading->value = rand(20, 85); // Random VA value
                $leftReading->method_id = $method;
                $leftReading->unit_id = $unit;
                $leftReading->save();
                
                // Create right eye reading
                $rightReading = new OphCiExamination_VisualAcuity_Reading();
                $rightReading->element_id = $va->id;
                $rightReading->side = 1; // Right
                $rightReading->value = rand(20, 85); // Random VA value
                $rightReading->method_id = $method;
                $rightReading->unit_id = $unit;
                $rightReading->save();
                
                return true;
            }
            return false;
        } catch (Exception $e) {
            echo "    VA Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    private function createIntraocularPressure($eventId)
    {
        try {
            $iop = new Element_OphCiExamination_IntraocularPressure();
            $iop->event_id = $eventId;
            $iop->eye_id = 3; // Both eyes
            
            // Get instrument ID
            $instrument = Yii::app()->db->createCommand()
                ->select('id')
                ->from('ophciexamination_instrument')
                ->where('name LIKE :name', [':name' => '%Goldmann%'])
                ->queryScalar();
            
            if (!$instrument) {
                $instrument = 1; // Default
            }
            
            if ($iop->save()) {
                // Create left eye reading
                $leftReading = new OphCiExamination_IntraocularPressure_Value();
                $leftReading->element_id = $iop->id;
                $leftReading->eye_id = 1; // Left
                $leftReading->reading = rand(12, 21); // Normal IOP range
                $leftReading->instrument_id = $instrument;
                $leftReading->save();
                
                // Create right eye reading
                $rightReading = new OphCiExamination_IntraocularPressure_Value();
                $rightReading->element_id = $iop->id;
                $rightReading->eye_id = 2; // Right
                $rightReading->reading = rand(12, 21); // Normal IOP range
                $rightReading->instrument_id = $instrument;
                $rightReading->save();
                
                return true;
            }
            return false;
        } catch (Exception $e) {
            echo "    IOP Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    private function createRefraction($eventId)
    {
        try {
            $refraction = new Element_OphCiExamination_Refraction();
            $refraction->event_id = $eventId;
            $refraction->eye_id = 3; // Both eyes
            
            // Get refraction type
            $type = Yii::app()->db->createCommand()
                ->select('id')
                ->from('ophciexamination_refraction_type')
                ->queryScalar();
            
            if (!$type) {
                $type = 1;
            }
            
            if ($refraction->save()) {
                // Create left eye reading
                $leftReading = new OphCiExamination_Refraction_Reading();
                $leftReading->element_id = $refraction->id;
                $leftReading->eye_id = 1; // Left
                $leftReading->sphere = rand(-500, 500) / 100; // -5.00 to +5.00
                $leftReading->cylinder = rand(-300, 0) / 100; // -3.00 to 0.00
                $leftReading->axis = rand(0, 180);
                $leftReading->type_id = $type;
                $leftReading->save();
                
                // Create right eye reading
                $rightReading = new OphCiExamination_Refraction_Reading();
                $rightReading->element_id = $refraction->id;
                $rightReading->eye_id = 2; // Right
                $rightReading->sphere = rand(-500, 500) / 100;
                $rightReading->cylinder = rand(-300, 0) / 100;
                $rightReading->axis = rand(0, 180);
                $rightReading->type_id = $type;
                $rightReading->save();
                
                return true;
            }
            return false;
        } catch (Exception $e) {
            echo "    Refraction Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    private function createDiagnoses($eventId)
    {
        try {
            $diagnoses = new Element_OphCiExamination_Diagnoses();
            $diagnoses->event_id = $eventId;
            $diagnoses->eye_id = 3; // Both eyes
            
            if ($diagnoses->save()) {
                // Get a common disorder (e.g., cataract)
                $disorder = Yii::app()->db->createCommand()
                    ->select('id')
                    ->from('disorder')
                    ->where('term LIKE :term', [':term' => '%cataract%'])
                    ->limit(1)
                    ->queryScalar();
                
                if (!$disorder) {
                    // Just use first disorder if cataract not found
                    $disorder = Yii::app()->db->createCommand()
                        ->select('id')
                        ->from('disorder')
                        ->limit(1)
                        ->queryScalar();
                }
                
                if ($disorder) {
                    $diagnosis = new OphCiExamination_Diagnosis();
                    $diagnosis->element_id = $diagnoses->id;
                    $diagnosis->disorder_id = $disorder;
                    $diagnosis->eye_id = 3; // Both eyes
                    $diagnosis->principal = 1;
                    $diagnosis->save();
                }
                
                return true;
            }
            return false;
        } catch (Exception $e) {
            echo "    Diagnoses Error: " . $e->getMessage() . "\n";
            return false;
        }
    }
}
