<?php
/**
 * (C) OpenEyes Foundation, 2025
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (C) 2025, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

/**
 * Verify service layer functionality
 * 
 * Usage:
 *   yiic serviceverify all       - Verify all services
 *   yiic serviceverify patient   - Verify patient service
 *   yiic serviceverify fhir      - Verify FHIR services
 */
class ServiceVerifyCommand extends CConsoleCommand
{
    private $passed = 0;
    private $failed = 0;
    
    public function getHelp()
    {
        return <<<EOD
USAGE
  yiic serviceverify <action>

ACTIONS
  all       - Verify all services
  patient   - Verify PatientService
  episode   - Verify EpisodeService
  event     - Verify EventService
  fhir      - Verify FHIR services
  
EXAMPLES
  yiic serviceverify all
  yiic serviceverify patient
EOD;
    }
    
    public function actionAll()
    {
        echo "=== Service Layer Verification ===\n\n";
        
        $this->verifyPatientService();
        echo "\n";
        $this->verifyEpisodeService();
        echo "\n";
        $this->verifyEventService();
        echo "\n";
        $this->verifyFhirServices();
        
        echo "\n" . str_repeat("=", 40) . "\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        
        return $this->failed > 0 ? 1 : 0;
    }
    
    public function actionPatient()
    {
        $this->verifyPatientService();
        $this->printSummary();
    }
    
    public function actionEpisode()
    {
        $this->verifyEpisodeService();
        $this->printSummary();
    }
    
    public function actionEvent()
    {
        $this->verifyEventService();
        $this->printSummary();
    }
    
    public function actionFhir()
    {
        $this->verifyFhirServices();
        $this->printSummary();
    }
    
    private function verifyPatientService()
    {
        echo "PatientService:\n";
        
        try {
            $service = new \services\PatientService();
            $this->check("  Instantiation", true);
        } catch (Exception $e) {
            $this->check("  Instantiation", false, $e->getMessage());
            return;
        }
        
        // Read with valid patient
        $patient = Patient::model()->find();
        if ($patient) {
            try {
                $result = $service->readPatient($patient->id);
                $this->check("  readPatient(existing)", $result !== null && is_array($result));
            } catch (Exception $e) {
                $this->check("  readPatient(existing)", false, $e->getMessage());
            }
        }
        
        // Read with invalid ID
        try {
            $result = $service->readPatient('999999999');
            $this->check("  readPatient(invalid)", $result === null);
        } catch (Exception $e) {
            $this->check("  readPatient(invalid)", false, $e->getMessage());
        }
        
        // findByHosNum
        try {
            $result = $service->findByHosNum('NONEXISTENT');
            $this->check("  findByHosNum()", $result === null);
        } catch (Exception $e) {
            $this->check("  findByHosNum()", false, $e->getMessage());
        }
        
        // getEpisodes
        if ($patient) {
            try {
                $episodes = $service->getEpisodes($patient->id);
                $this->check("  getEpisodes()", is_array($episodes));
            } catch (Exception $e) {
                $this->check("  getEpisodes()", false, $e->getMessage());
            }
        }
    }
    
    private function verifyEpisodeService()
    {
        echo "EpisodeService:\n";
        
        try {
            $service = new \services\EpisodeService();
            $this->check("  Instantiation", true);
        } catch (Exception $e) {
            $this->check("  Instantiation", false, $e->getMessage());
            return;
        }
        
        $patient = Patient::model()->find();
        
        if ($patient) {
            try {
                $episodes = $service->getForPatient($patient->id);
                $this->check("  getForPatient()", is_array($episodes));
            } catch (Exception $e) {
                $this->check("  getForPatient()", false, $e->getMessage());
            }
        }
        
        // Statistics
        try {
            $stats = $service->getStatistics([
                'start_date' => date('Y-01-01'),
                'end_date' => date('Y-m-d'),
            ]);
            $this->check("  getStatistics()", is_array($stats));
        } catch (Exception $e) {
            $this->check("  getStatistics()", false, $e->getMessage());
        }
        
        // Read invalid
        try {
            $result = $service->readEpisode('999999999');
            $this->check("  readEpisode(invalid)", $result === null);
        } catch (Exception $e) {
            $this->check("  readEpisode(invalid)", false, $e->getMessage());
        }
    }
    
    private function verifyEventService()
    {
        echo "EventService:\n";
        
        try {
            $service = new \services\EventService();
            $this->check("  Instantiation", true);
        } catch (Exception $e) {
            $this->check("  Instantiation", false, $e->getMessage());
            return;
        }
        
        $patient = Patient::model()->find();
        
        if ($patient) {
            try {
                $events = $service->getForPatient($patient->id, 10);
                $this->check("  getForPatient()", is_array($events));
            } catch (Exception $e) {
                $this->check("  getForPatient()", false, $e->getMessage());
            }
        }
        
        // Read invalid
        try {
            $result = $service->readEvent('999999999');
            $this->check("  readEvent(invalid)", $result === null);
        } catch (Exception $e) {
            $this->check("  readEvent(invalid)", false, $e->getMessage());
        }
    }
    
    private function verifyFhirServices()
    {
        echo "FHIR Services:\n";
        
        // FhirPatientService
        try {
            $service = new \services\fhir\FhirPatientService();
            $this->check("  FhirPatientService instantiation", true);
        } catch (Exception $e) {
            $this->check("  FhirPatientService instantiation", false, $e->getMessage());
            return;
        }
        
        // Search returns bundle
        try {
            $service = new \services\fhir\FhirPatientService();
            $result = $service->searchPatients([]);
            $this->check("  searchPatients() returns array", is_array($result));
            
            if (is_array($result)) {
                $this->check("  searchPatients() has resourceType", 
                    isset($result['resourceType']));
            }
        } catch (Exception $e) {
            $this->check("  searchPatients()", false, $e->getMessage());
        }
    }
    
    private function check($label, $passed, $error = '')
    {
        if ($passed) {
            echo "{$label}: \033[32mPASS\033[0m\n";
            $this->passed++;
        } else {
            echo "{$label}: \033[31mFAIL\033[0m";
            if ($error) {
                echo " ({$error})";
            }
            echo "\n";
            $this->failed++;
        }
    }
    
    private function printSummary()
    {
        echo "\n" . str_repeat("-", 40) . "\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
    }
}
