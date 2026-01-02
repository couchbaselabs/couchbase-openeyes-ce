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

namespace services;

use OE\Reports\CouchbaseEpisodeReport;

/**
 * Episode Service with Couchbase support
 */
class EpisodeService extends DatabaseAgnosticService
{
    protected $collection = 'episode';
    
    /**
     * Get episodes for patient
     * @param string $patientId Patient ID
     * @return array Episodes
     */
    public function getForPatient($patientId)
    {
        return $this->executeWithFallback(
            function() use ($patientId) {
                if (!class_exists('EpisodeDocument')) {
                    return [];
                }
                $episodes = \EpisodeDocument::findByPatientId($patientId);
                return array_map(function($ep) { return $ep->getAttributes(); }, $episodes);
            },
            function() use ($patientId) {
                $episodes = \Episode::model()->findAllByAttributes(
                    ['patient_id' => $patientId],
                    ['order' => 'start_date DESC']
                );
                return $this->normalizeResults($episodes);
            }
        );
    }
    
    /**
     * Create episode
     * @param array $data Episode data
     * @return array Created episode
     * @throws ValidationFailure
     */
    public function createEpisode(array $data)
    {
        $episode = new \Episode();
        $episode->attributes = $data;
        
        if (!$episode->save()) {
            throw new ValidationFailure('Episode validation failed', $episode->getErrors());
        }
        
        return $this->normalizeResult($episode);
    }
    
    /**
     * Read episode by ID
     * @param string $id Episode ID
     * @return array|null Episode data or null
     */
    public function readEpisode($id)
    {
        return $this->executeWithFallback(
            function() use ($id) {
                if (!class_exists('EpisodeDocument')) {
                    return null;
                }
                $doc = \EpisodeDocument::findByPk($id);
                return $doc ? $doc->getAttributes() : null;
            },
            function() use ($id) {
                $episode = \Episode::model()->findByPk($id);
                return $this->normalizeResult($episode);
            }
        );
    }
    
    /**
     * Update episode
     * @param string $id Episode ID
     * @param array $data Updated data
     * @return array Updated episode
     * @throws NotFound
     * @throws ValidationFailure
     */
    public function updateEpisode($id, array $data)
    {
        $episode = \Episode::model()->findByPk($id);
        if (!$episode) {
            throw new NotFound("Episode not found: {$id}");
        }
        
        $episode->attributes = $data;
        if (!$episode->save()) {
            throw new ValidationFailure('Episode update failed', $episode->getErrors());
        }
        
        return $this->normalizeResult($episode);
    }
    
    /**
     * Get events for episode
     * @param string $episodeId Episode ID
     * @return array Events
     */
    public function getEvents($episodeId)
    {
        return $this->executeWithFallback(
            function() use ($episodeId) {
                if (!class_exists('EventDocument')) {
                    return [];
                }
                $events = \EventDocument::findByEpisodeId($episodeId);
                return array_map(function($ev) { return $ev->getAttributes(); }, $events);
            },
            function() use ($episodeId) {
                $events = \Event::model()->findAllByAttributes(
                    ['episode_id' => $episodeId],
                    ['order' => 'event_date DESC']
                );
                return $this->normalizeResults($events);
            }
        );
    }
    
    /**
     * Get episode statistics for reporting
     * @param array $params Statistics parameters
     * @return array Statistics
     */
    public function getStatistics(array $params = [])
    {
        if ($this->isUsingCouchbase()) {
            return $this->getStatisticsCouchbase($params);
        }
        return $this->getStatisticsMariaDB($params);
    }
    
    /**
     * Get statistics from Couchbase
     */
    private function getStatisticsCouchbase(array $params)
    {
        if (!class_exists('OE\\Reports\\CouchbaseEpisodeReport')) {
            return $this->getStatisticsMariaDB($params);
        }
        
        $report = new CouchbaseEpisodeReport();
        return $report->countBySubspecialty(
            $params['start_date'] ?? date('Y-01-01'),
            $params['end_date'] ?? date('Y-m-d'),
            $params['institution_id'] ?? null
        );
    }
    
    /**
     * Get statistics from MariaDB
     */
    private function getStatisticsMariaDB(array $params)
    {
        $sql = "SELECT subspecialty_id, COUNT(*) as count 
                FROM episode 
                WHERE start_date BETWEEN :start AND :end";
        $sqlParams = [
            ':start' => $params['start_date'] ?? date('Y-01-01'),
            ':end' => $params['end_date'] ?? date('Y-m-d'),
        ];
        
        if (!empty($params['institution_id'])) {
            $sql .= " AND institution_id = :institution";
            $sqlParams[':institution'] = $params['institution_id'];
        }
        
        $sql .= " GROUP BY subspecialty_id ORDER BY count DESC";
        
        $command = \Yii::app()->cbdb->createCommand($sql);
        foreach ($sqlParams as $key => $value) {
            $command->bindValue($key, $value);
        }
        
        return $command->queryAll();
    }
    
    /**
     * Find episodes by firm
     * @param string $firmId Firm ID
     * @param int $limit Maximum results
     * @return array Episodes
     */
    public function findByFirm($firmId, $limit = 100)
    {
        return $this->executeWithFallback(
            function() use ($firmId, $limit) {
                if (!class_exists('EpisodeDocument')) {
                    return [];
                }
                $episodes = \EpisodeDocument::findByFirmId($firmId, $limit);
                return array_map(function($ep) { return $ep->getAttributes(); }, $episodes);
            },
            function() use ($firmId, $limit) {
                $criteria = new \CDbCriteria();
                $criteria->compare('firm_id', $firmId);
                $criteria->order = 'start_date DESC';
                $criteria->limit = $limit;
                
                $episodes = \Episode::model()->findAll($criteria);
                return $this->normalizeResults($episodes);
            }
        );
    }
    
    /**
     * Check if episode is open
     * @param string $id Episode ID
     * @return bool True if open
     */
    public function isOpen($id)
    {
        $episode = $this->readEpisode($id);
        return $episode && empty($episode['end_date']);
    }
    
    /**
     * Close episode
     * @param string $id Episode ID
     * @param string|null $endDate End date (defaults to today)
     * @return array Updated episode
     * @throws NotFound
     */
    public function close($id, $endDate = null)
    {
        $episode = \Episode::model()->findByPk($id);
        if (!$episode) {
            throw new NotFound("Episode not found: {$id}");
        }
        
        $episode->end_date = $endDate ?? date('Y-m-d');
        $episode->save(false);
        
        return $this->normalizeResult($episode);
    }
}
