<?php
/**
 * Trait for examination element models to support Couchbase embedding
 * Elements using this trait can be embedded within ExaminationDocument
 */

namespace OEModule\OphCiExamination\models\traits;

trait CouchbaseElementBridge
{
    /**
     * Convert element to embeddable array for Couchbase document
     * Override in specific elements for custom handling
     * @return array
     */
    public function toCouchbaseEmbedded()
    {
        $data = [];
        
        // Get all scalar attributes (skip foreign keys and internal fields)
        $skipFields = ['id', 'event_id', 'created_user_id', 'created_date', 
                       'last_modified_user_id', 'last_modified_date'];
        
        foreach ($this->attributes as $attr => $value) {
            if (in_array($attr, $skipFields)) {
                continue;
            }
            $data[$attr] = $value;
        }
        
        // Handle sided elements (left/right eye data)
        if ($this instanceof \SplitEventTypeElement) {
            $data['_sided'] = [
                'has_left' => $this->hasLeft(),
                'has_right' => $this->hasRight(),
            ];
        }
        
        // Handle related data for embedding
        $data = array_merge($data, $this->getEmbeddedRelations());
        
        return $data;
    }
    
    /**
     * Get related data to embed in document
     * Override in elements that have relations to embed
     * @return array
     */
    protected function getEmbeddedRelations()
    {
        $data = [];
        
        // Auto-embed HAS_MANY relations marked with 'embed' => true
        $relations = $this->relations();
        foreach ($relations as $name => $config) {
            // Check if relation is marked for embedding
            if (!isset($config['embed']) || !$config['embed']) {
                continue;
            }
            
            if ($config[0] === \CActiveRecord::HAS_MANY) {
                $items = $this->$name;
                if (!empty($items)) {
                    $data[$name] = array_map(function($item) {
                        return $this->relatedItemToArray($item);
                    }, $items);
                }
            } elseif ($config[0] === \CActiveRecord::BELONGS_TO || $config[0] === \CActiveRecord::HAS_ONE) {
                $item = $this->$name;
                if ($item !== null) {
                    $data[$name] = $this->relatedItemToArray($item);
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Convert related item to array for embedding
     * @param \CActiveRecord $item
     * @return array
     */
    protected function relatedItemToArray($item)
    {
        $arr = [];
        $skipFields = ['id', 'element_id', 'event_id', 'created_user_id', 
                       'created_date', 'last_modified_user_id', 'last_modified_date'];
        
        foreach ($item->attributes as $attr => $value) {
            if (!in_array($attr, $skipFields)) {
                $arr[$attr] = $value;
            }
        }
        
        // Resolve lookup references (e.g., method_id -> method.name)
        if (method_exists($item, 'getEmbeddedLookups')) {
            $arr = array_merge($arr, $item->getEmbeddedLookups());
        }
        
        return $arr;
    }
    
    /**
     * Check if this element should be embedded or referenced
     * Large elements (images, drawings >10KB) should be referenced
     * @return bool
     */
    public function shouldBeEmbedded()
    {
        // Default to embed unless element is large
        $largeElements = [
            'Element_OphCiExamination_Fundus',  // Contains large image data
            'Element_OphCiExamination_OCT',     // Contains image data
        ];
        
        return !in_array(get_class($this), $largeElements);
    }
}
