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

namespace services\fhir;

use services\PatientService;
use services\NotFound;

/**
 * FHIR Patient Resource Service
 */
class FhirPatientService extends BaseFhirService
{
    protected $collection = 'patient';
    
    /** @var PatientService */
    private $patientService;
    
    public function __construct()
    {
        parent::__construct();
        $this->patientService = new PatientService();
    }
    
    /**
     * Get patient as FHIR Patient resource
     * @param string $id Patient ID
     * @return array FHIR Patient resource
     * @throws NotFound
     */
    public function getPatient($id)
    {
        $patient = $this->patientService->readPatient($id);
        
        if (!$patient) {
            throw new NotFound("Patient not found: {$id}");
        }
        
        return $this->toFhirResource($patient);
    }
    
    /**
     * Search patients returning FHIR Bundle
     * @param array $params Search parameters
     * @return array FHIR Bundle
     */
    public function searchPatients(array $params)
    {
        // Use the existing FHIR search from PatientService
        $results = $this->patientService->search($params);
        
        // If results are already FHIR resources, return bundle
        if (!empty($results) && isset($results[0]) && is_object($results[0])) {
            // Already FHIR resources from parent class
            return $this->buildBundle('searchset', $results);
        }
        
        // Convert array results to FHIR
        $resources = array_map([$this, 'toFhirResource'], $results);
        return $this->buildBundle('searchset', $resources);
    }
    
    /**
     * Convert patient data to FHIR Patient resource
     * @param array $patient Internal patient data
     * @return array FHIR Patient resource
     */
    protected function toFhirResource(array $patient): array
    {
        $resource = [
            'resourceType' => 'Patient',
            'id' => (string)($patient['id'] ?? $patient['_mysql_id'] ?? ''),
            'meta' => $this->buildMeta(
                $patient['_version'] ?? '1',
                $patient['_modified'] ?? null
            ),
            'identifier' => array_values(array_filter([
                $this->buildIdentifier(
                    'http://hospital.example.org/patients',
                    $patient['hos_num'] ?? null
                ),
                $this->buildIdentifier(
                    'https://fhir.nhs.uk/Id/nhs-number',
                    $patient['nhs_num'] ?? null
                ),
            ])),
            'name' => [
                [
                    'use' => 'official',
                    'family' => $this->extractName($patient, 'last_name'),
                    'given' => array_filter([$this->extractName($patient, 'first_name')]),
                ],
            ],
            'gender' => $this->mapGender($patient['gender'] ?? 'U'),
            'birthDate' => $patient['dob'] ?? null,
        ];
        
        // Add deceased information
        if (!empty($patient['date_of_death'])) {
            $resource['deceasedDateTime'] = $patient['date_of_death'];
        } elseif (!empty($patient['is_deceased'])) {
            $resource['deceasedBoolean'] = true;
        }
        
        // Add addresses if embedded
        if (!empty($patient['addresses']) && is_array($patient['addresses'])) {
            $resource['address'] = array_map([$this, 'mapAddress'], $patient['addresses']);
        }
        
        // Add telecom
        $telecom = [];
        $phone = $patient['contact']['primary_phone'] ?? $patient['primary_phone'] ?? null;
        $email = $patient['contact']['email'] ?? $patient['email'] ?? null;
        
        if (!empty($phone)) {
            $telecom[] = [
                'system' => 'phone',
                'value' => $phone,
                'use' => 'home',
            ];
        }
        if (!empty($email)) {
            $telecom[] = [
                'system' => 'email',
                'value' => $email,
            ];
        }
        if (!empty($telecom)) {
            $resource['telecom'] = $telecom;
        }
        
        return $resource;
    }
    
    /**
     * Convert FHIR Patient resource to internal format
     * @param array $resource FHIR Patient resource
     * @return array Internal patient data
     */
    protected function fromFhirResource(array $resource): array
    {
        $data = [];
        
        // Extract identifiers
        foreach ($resource['identifier'] ?? [] as $identifier) {
            $system = $identifier['system'] ?? '';
            $value = $identifier['value'] ?? null;
            
            if (strpos($system, 'nhs-number') !== false) {
                $data['nhs_num'] = $value;
            } elseif (strpos($system, 'patients') !== false) {
                $data['hos_num'] = $value;
            }
        }
        
        // Extract name
        if (!empty($resource['name'][0])) {
            $name = $resource['name'][0];
            $data['last_name'] = $name['family'] ?? null;
            $data['first_name'] = $name['given'][0] ?? null;
        }
        
        // Map gender
        $data['gender'] = $this->reverseMapGender($resource['gender'] ?? 'unknown');
        
        // Birth date
        $data['dob'] = $resource['birthDate'] ?? null;
        
        // Deceased
        if (isset($resource['deceasedDateTime'])) {
            $data['date_of_death'] = $resource['deceasedDateTime'];
        } elseif (!empty($resource['deceasedBoolean'])) {
            $data['is_deceased'] = true;
        }
        
        return $data;
    }
    
    /**
     * Extract name from patient data
     */
    private function extractName(array $patient, string $field)
    {
        if (isset($patient['contact'][$field])) {
            return $patient['contact'][$field];
        }
        return $patient[$field] ?? null;
    }
    
    /**
     * Map internal gender to FHIR
     */
    private function mapGender($gender): string
    {
        $map = ['M' => 'male', 'F' => 'female', 'U' => 'unknown'];
        return $map[$gender] ?? 'unknown';
    }
    
    /**
     * Map FHIR gender to internal
     */
    private function reverseMapGender($gender): string
    {
        $map = ['male' => 'M', 'female' => 'F', 'unknown' => 'U', 'other' => 'U'];
        return $map[$gender] ?? 'U';
    }
    
    /**
     * Map internal address to FHIR
     */
    private function mapAddress(array $address): array
    {
        return [
            'use' => ($address['is_primary'] ?? false) ? 'home' : 'temp',
            'type' => 'physical',
            'line' => array_values(array_filter([
                $address['address1'] ?? null,
                $address['address2'] ?? null,
            ])),
            'city' => $address['city'] ?? null,
            'state' => $address['county'] ?? null,
            'postalCode' => $address['postcode'] ?? null,
            'country' => $address['country'] ?? null,
        ];
    }
}
