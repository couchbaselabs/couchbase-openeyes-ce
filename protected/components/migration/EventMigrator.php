<?php
/**
 * Event migrator with denormalized references
 */

namespace OE\Migration;

class EventMigrator implements TableMigrator
{
    private $adapter;
    
    public function __construct()
    {
        $this->adapter = \OE\Database\DatabaseAdapterFactory::getAdapter(
            \OE\Database\DatabaseAdapterFactory::ADAPTER_COUCHBASE
        );
    }
    
    public function migrate(array $record): void
    {
        $event = \Event::model()->with([
            'eventType',
            'episode',
            'user',
        ])->findByPk($record['id']);
        
        if (!$event) {
            throw new \Exception("Event not found: {$record['id']}");
        }
        
        $doc = [
            '_mysql_id' => $event->id,
            '_type' => 'event',
            '_migrated' => date('c'),
            '_source' => 'mariadb',
            'episode_id' => $event->episode_id,
            'event_type_id' => $event->event_type_id,
            'event_date' => $event->event_date,
            'created_date' => $event->created_date,
            'last_modified_date' => $event->last_modified_date,
            'created_user_id' => $event->created_user_id,
            'last_modified_user_id' => $event->last_modified_user_id,
            'deleted' => (bool)$event->deleted,
            'delete_reason' => $event->delete_reason,
            'institution_id' => $event->institution_id,
            'site_id' => $event->site_id,
        ];
        
        // Denormalized references
        if ($event->eventType) {
            $doc['event_type_name'] = $event->eventType->name;
            $doc['event_type_class'] = $event->eventType->class_name;
        }
        
        if ($event->episode) {
            $doc['patient_id'] = $event->episode->patient_id;
        }
        
        $this->adapter->upsert('event', $event->id, $doc);
    }
    
    public function getCollection(): string
    {
        return 'event';
    }
    
    public function getTable(): string
    {
        return 'event';
    }
}
