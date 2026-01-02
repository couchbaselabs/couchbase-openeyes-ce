<?php
/**
 * Episode migrator with embedded firm and diagnosis data
 */

namespace OE\Migration;

class EpisodeMigrator implements TableMigrator
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
        $episode = \Episode::model()->with([
            'firm',
            'patient',
            'diagnosis',
        ])->findByPk($record['id']);
        
        if (!$episode) {
            throw new \Exception("Episode not found: {$record['id']}");
        }
        
        $doc = [
            '_mysql_id' => $episode->id,
            '_type' => 'episode',
            '_migrated' => date('c'),
            '_source' => 'mariadb',
            'patient_id' => $episode->patient_id,
            'firm_id' => $episode->firm_id,
            'start_date' => $episode->start_date,
            'end_date' => $episode->end_date,
            'disorder_id' => $episode->disorder_id,
            'eye_id' => $episode->eye_id,
            'created_date' => $episode->created_date,
            'last_modified_date' => $episode->last_modified_date,
            'deleted' => (bool)$episode->deleted,
        ];
        
        // Embed firm/subspecialty denormalized data
        if ($episode->firm) {
            $doc['firm_name'] = $episode->firm->name;
        }
        
        // Embed principal diagnosis
        if (isset($episode->diagnosis) && $episode->diagnosis) {
            $doc['principal_diagnosis'] = [
                'id' => $episode->diagnosis->id,
                'term' => $episode->diagnosis->term,
            ];
        }
        
        $this->adapter->upsert('episode', $episode->id, $doc);
    }
    
    public function getCollection(): string
    {
        return 'episode';
    }
    
    public function getTable(): string
    {
        return 'episode';
    }
}
