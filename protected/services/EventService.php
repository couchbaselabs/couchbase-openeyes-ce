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

/**
 * Event Service with Couchbase support
 */
class EventService extends DatabaseAgnosticService
{
    protected $collection = 'event';
    
    /**
     * Read event by ID
     * @param string $id Event ID
     * @return array|null Event data or null
     */
    public function readEvent($id)
    {
        return $this->executeWithFallback(
            function() use ($id) {
                if (!class_exists('EventDocument')) {
                    return null;
                }
                $doc = \EventDocument::findByPk($id);
                return $doc ? $doc->getAttributes() : null;
            },
            function() use ($id) {
                $event = \Event::model()->findByPk($id);
                return $this->normalizeResult($event);
            }
        );
    }
    
    /**
     * Get events for episode
     * @param string $episodeId Episode ID
     * @return array Events
     */
    public function getForEpisode($episodeId)
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
     * Get events for patient
     * @param string $patientId Patient ID
     * @param int $limit Maximum results
     * @return array Events
     */
    public function getForPatient($patientId, $limit = 100)
    {
        return $this->executeWithFallback(
            function() use ($patientId, $limit) {
                if (!class_exists('EventDocument')) {
                    return [];
                }
                $events = \EventDocument::findByPatientId($patientId, $limit);
                return array_map(function($ev) { return $ev->getAttributes(); }, $events);
            },
            function() use ($patientId, $limit) {
                $criteria = new \CDbCriteria();
                $criteria->with = ['episode'];
                $criteria->addCondition('episode.patient_id = :patientId');
                $criteria->params[':patientId'] = $patientId;
                $criteria->order = 't.event_date DESC';
                $criteria->limit = $limit;
                
                $events = \Event::model()->findAll($criteria);
                return $this->normalizeResults($events);
            }
        );
    }
    
    /**
     * Get events by type
     * @param int $eventTypeId Event type ID
     * @param array $options Query options
     * @return array Events
     */
    public function getByType($eventTypeId, array $options = [])
    {
        return $this->executeWithFallback(
            function() use ($eventTypeId, $options) {
                if (!class_exists('EventDocument')) {
                    return [];
                }
                $events = \EventDocument::findByEventType($eventTypeId, $options['limit'] ?? 100);
                return array_map(function($ev) { return $ev->getAttributes(); }, $events);
            },
            function() use ($eventTypeId, $options) {
                $criteria = new \CDbCriteria();
                $criteria->compare('event_type_id', $eventTypeId);
                $criteria->order = 'event_date DESC';
                $criteria->limit = $options['limit'] ?? 100;
                
                if (!empty($options['start_date'])) {
                    $criteria->addCondition('event_date >= :startDate');
                    $criteria->params[':startDate'] = $options['start_date'];
                }
                
                if (!empty($options['end_date'])) {
                    $criteria->addCondition('event_date <= :endDate');
                    $criteria->params[':endDate'] = $options['end_date'];
                }
                
                $events = \Event::model()->findAll($criteria);
                return $this->normalizeResults($events);
            }
        );
    }
    
    /**
     * Delete event (soft delete)
     * @param string $id Event ID
     * @param string $reason Delete reason
     * @return bool Success
     * @throws NotFound
     */
    public function deleteEvent($id, $reason = '')
    {
        $event = \Event::model()->findByPk($id);
        if (!$event) {
            throw new NotFound("Event not found: {$id}");
        }
        
        $event->deleted = 1;
        $event->delete_reason = $reason;
        return $event->save(false);
    }
    
    /**
     * Get event count by type for date range
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Counts by event type
     */
    public function getCountByType($startDate, $endDate)
    {
        $sql = "SELECT event_type_id, COUNT(*) as count 
                FROM event 
                WHERE event_date BETWEEN :start AND :end 
                AND deleted = 0
                GROUP BY event_type_id
                ORDER BY count DESC";
        
        $command = \Yii::app()->cbdb->createCommand($sql);
        $command->bindValue(':start', $startDate);
        $command->bindValue(':end', $endDate);
        
        return $command->queryAll();
    }
}
