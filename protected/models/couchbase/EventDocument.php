<?php
/**
 * Event document model for direct Couchbase operations
 */

class EventDocument extends CouchbaseActiveRecord
{
    public function documentType()
    {
        return 'event';
    }
    
    public function scope()
    {
        return 'core';
    }
    
    public function collectionName()
    {
        return 'event';
    }
    
    public function rules()
    {
        return [
            [['event_type_id', 'event_date'], 'required'],
        ];
    }
    
    public function attributeLabels()
    {
        return [
            'episode_id' => 'Episode',
            'event_type_id' => 'Event Type',
            'event_date' => 'Event Date',
        ];
    }
    
    /**
     * Find events by episode ID
     * @param int $episodeId Episode ID
     * @return array Array of EventDocument
     */
    public static function findByEpisodeId($episodeId)
    {
        return static::findAllByAttributes(
            ['episode_id' => (int)$episodeId],
            ['order' => 'event_date DESC']
        );
    }
    
    /**
     * Find events by event type
     * @param int $eventTypeId Event type ID
     * @param int $limit Maximum results
     * @return array Array of EventDocument
     */
    public static function findByEventType($eventTypeId, $limit = 100)
    {
        return static::findAllByAttributes(
            ['event_type_id' => (int)$eventTypeId],
            ['order' => 'event_date DESC', 'limit' => $limit]
        );
    }
    
    /**
     * Find events for a patient (via episodes)
     * Uses N1QL JOIN for efficiency
     * @param int $patientId Patient ID
     * @param int $limit Maximum results
     * @return array Array of EventDocument
     */
    public static function findByPatientId($patientId, $limit = 100)
    {
        $conn = Yii::app()->couchbase;
        $bucket = $conn->config['bucket'];
        
        $query = "SELECT META(e).id as _key, e.* 
                  FROM `{$bucket}`.`core`.`event` e
                  JOIN `{$bucket}`.`core`.`episode` ep ON e.episode_id = ep._mysql_id
                  WHERE ep.patient_id = \$patientId
                  AND e._type = 'event'
                  ORDER BY e.event_date DESC
                  LIMIT \$limit";
        
        try {
            $results = $conn->query($query, [
                'patientId' => (int)$patientId,
                'limit' => $limit,
            ]);
            
            $models = [];
            foreach ($results as $row) {
                $model = new static();
                $data = is_object($row) ? (array)$row : $row;
                if (isset($data['e'])) {
                    $data = array_merge($data, (array)$data['e']);
                    unset($data['e']);
                }
                $model->setAttributes($data);
                $model->setPrimaryKey(isset($data['_mysql_id']) ? $data['_mysql_id'] : null);
                $model->setIsNewRecord(false);
                $models[] = $model;
            }
            
            return $models;
        } catch (\Exception $e) {
            Yii::log("EventDocument search error: " . $e->getMessage(), CLogger::LEVEL_ERROR);
            return [];
        }
    }
    
    /**
     * Get the episode document
     * @return EpisodeDocument|null
     */
    public function getEpisode()
    {
        return $this->episode_id ? EpisodeDocument::findByPk($this->episode_id) : null;
    }
    
    /**
     * Check if event is deleted
     * @return bool
     */
    public function isDeleted()
    {
        return !empty($this->deleted);
    }
    
    /**
     * Get corresponding MariaDB Event model
     * @return Event|null
     */
    public function getMariaDbModel()
    {
        $pk = $this->getPrimaryKey();
        return $pk ? Event::model()->findByPk($pk) : null;
    }
}
