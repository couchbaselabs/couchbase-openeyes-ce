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

use services\DatabaseAgnosticService;

/**
 * Base class for FHIR services
 */
abstract class BaseFhirService extends DatabaseAgnosticService
{
    /** @var string FHIR version */
    protected $fhirVersion = '4.0.1';
    
    /**
     * Convert internal data to FHIR resource
     * @param array $data Internal data
     * @return array FHIR resource
     */
    abstract protected function toFhirResource(array $data): array;
    
    /**
     * Convert FHIR resource to internal format
     * @param array $resource FHIR resource
     * @return array Internal data
     */
    abstract protected function fromFhirResource(array $resource): array;
    
    /**
     * Build FHIR identifier
     * @param string $system Identifier system URI
     * @param string|null $value Identifier value
     * @return array|null Identifier or null if no value
     */
    protected function buildIdentifier(string $system, $value)
    {
        if (empty($value)) {
            return null;
        }
        
        return [
            'system' => $system,
            'value' => (string)$value,
        ];
    }
    
    /**
     * Build FHIR reference
     * @param string $resourceType Resource type
     * @param string $id Resource ID
     * @return array Reference object
     */
    protected function buildReference(string $resourceType, $id): array
    {
        return [
            'reference' => "{$resourceType}/" . (string)$id,
        ];
    }
    
    /**
     * Build FHIR Bundle
     * @param string $type Bundle type (searchset, collection, etc.)
     * @param array $resources Array of resources
     * @return array FHIR Bundle
     */
    protected function buildBundle(string $type, array $resources): array
    {
        return [
            'resourceType' => 'Bundle',
            'type' => $type,
            'total' => count($resources),
            'entry' => array_map(function($resource) {
                return [
                    'resource' => $resource,
                ];
            }, $resources),
        ];
    }
    
    /**
     * Build FHIR meta element
     * @param string|null $versionId Version ID
     * @param string|null $lastUpdated Last updated timestamp
     * @return array Meta element
     */
    protected function buildMeta($versionId = null, $lastUpdated = null): array
    {
        return [
            'versionId' => $versionId ? (string)$versionId : '1',
            'lastUpdated' => $lastUpdated ?? date('c'),
        ];
    }
    
    /**
     * Build FHIR coding element
     * @param string $system Coding system URI
     * @param string $code Code value
     * @param string|null $display Display text
     * @return array Coding element
     */
    protected function buildCoding(string $system, string $code, $display = null): array
    {
        $coding = [
            'system' => $system,
            'code' => $code,
        ];
        
        if ($display !== null) {
            $coding['display'] = $display;
        }
        
        return $coding;
    }
    
    /**
     * Build FHIR codeable concept
     * @param array $codings Array of coding elements
     * @param string|null $text Text description
     * @return array CodeableConcept element
     */
    protected function buildCodeableConcept(array $codings, $text = null): array
    {
        $concept = ['coding' => $codings];
        
        if ($text !== null) {
            $concept['text'] = $text;
        }
        
        return $concept;
    }
}
