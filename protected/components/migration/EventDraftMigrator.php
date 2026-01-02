<?php
/**
 * Event draft migrator
 */

namespace OE\Migration;

use OE\Database\DatabaseAdapterFactory;

class EventDraftMigrator implements TableMigrator
{
    private $adapter;

    public function __construct()
    {
        $this->adapter = DatabaseAdapterFactory::getAdapter(
            DatabaseAdapterFactory::ADAPTER_COUCHBASE
        );
    }

    public function migrate(array $record): void
    {
        $draft = \EventDraft::model()->with(['episode', 'eventType'])->findByPk($record['id']);

        if (!$draft) {
            throw new \Exception("EventDraft not found: {$record['id']}");
        }

        $doc = [
            '_mysql_id' => $draft->id,
            '_type' => 'event_draft',
            '_migrated' => date('c'),
            '_source' => 'mariadb',
            'institution_id' => $draft->institution_id,
            'site_id' => $draft->site_id,
            'episode_id' => $draft->episode_id,
            'event_type_id' => $draft->event_type_id,
            'event_id' => $draft->event_id,
            'is_auto_save' => (bool)$draft->is_auto_save,
            'originating_url' => $draft->originating_url,
            'event_action' => $draft->event_action,
            'data' => $draft->data,
        ];

        if ($draft->episode) {
            $doc['patient_id'] = $draft->episode->patient_id;
        }

        if (\property_exists($draft, 'last_modified_user_id')) {
            $doc['last_modified_user_id'] = $draft->last_modified_user_id;
        }
        if (\property_exists($draft, 'last_modified_date')) {
            $doc['last_modified_date'] = $draft->last_modified_date;
        }
        if (\property_exists($draft, 'created_user_id')) {
            $doc['created_user_id'] = $draft->created_user_id;
        }
        if (\property_exists($draft, 'created_date')) {
            $doc['created_date'] = $draft->created_date;
        }

        $this->adapter->upsert('event_draft', $draft->id, $doc);
    }

    public function getCollection(): string
    {
        return 'event_draft';
    }

    public function getTable(): string
    {
        return 'event_draft';
    }
}
