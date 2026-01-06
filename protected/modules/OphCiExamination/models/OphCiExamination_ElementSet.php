<?php
/**
 * OpenEyes.
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2011-2013, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

namespace OEModule\OphCiExamination\models;

use OE\factories\models\traits\HasFactory;

/**
 * This is the model class for table "ophciexamination_element_set".
 * Uses Couchbase as the primary database.
 *
 * @property int $id
 * @property string $name
 * @property OphCiExamination_Workflow $workflow
 * @property int $position
 * @property OphCiExamination_ElementSetItem[] $items
 */
class OphCiExamination_ElementSet extends \BaseActiveRecordVersioned
{
    use HasFactory;
    use \OE\Models\Traits\CouchbaseModelBridge;

    // Public properties for Couchbase
    public $id;
    public $workflow_id;
    public $name;
    public $position;
    public $is_active = 1;
    public $display_order_edited = 0;
    
    protected $_items = null;
    protected $_visibleItems = null;
    protected $_workflow = null;
    protected $_created;
    protected $_isNewRecord = true;

    public function couchbaseScope(): string
    {
        return 'reference';
    }

    public function couchbaseCollection(): string
    {
        return $this->tableName();
    }

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return 'ophciexamination_element_set';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['name', 'required'],
            ['id, name, display_order_edited', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return array relational rules - kept for compatibility
     */
    public function relations()
    {
        return [];
    }

    /**
     * Get workflow for this element set
     */
    public function getWorkflow()
    {
        if ($this->_workflow === null && $this->workflow_id) {
            $this->_workflow = OphCiExamination_Workflow::model()->findByPk($this->workflow_id);
        }
        return $this->_workflow;
    }

    /**
     * Set workflow
     */
    public function setWorkflow($workflow)
    {
        $this->_workflow = $workflow;
        if ($workflow) {
            $this->workflow_id = $workflow->id;
        }
    }

    /**
     * Find by primary key using N1QL
     */
    public function findByPk($pk, $condition = '', $params = [])
    {
        try {
            $restClient = \Yii::app()->couchbaseRest;
            $n1ql = "SELECT s.* FROM `openeyes`.`reference`.`ophciexamination_element_set` s WHERE s.id = \$id LIMIT 1";
            $rows = $restClient->query($n1ql, ['id' => (int)$pk]);
            
            if (empty($rows)) {
                return null;
            }
            
            return $this->populateFromRow($rows[0]);
        } catch (\Exception $e) {
            \Yii::log("ElementSet findByPk failed: " . $e->getMessage(), \CLogger::LEVEL_ERROR);
            return null;
        }
    }

    /**
     * Find single record - supports array config like ActiveRecord
     */
    public function find($condition = '', $params = [])
    {
        // Handle array format from ActiveRecord style: find(['condition' => ..., 'params' => ..., 'order' => ...])
        $order = null;
        if (is_array($condition)) {
            $config = $condition;
            $condition = $config['condition'] ?? '';
            $params = $config['params'] ?? [];
            $order = $config['order'] ?? null;
        }
        
        $results = $this->findAllN1ql($condition, $params, 1, $order);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Find all records
     */
    public function findAll($condition = '', $params = [])
    {
        $order = null;
        if (is_array($condition)) {
            $config = $condition;
            $condition = $config['condition'] ?? '';
            $params = $config['params'] ?? [];
            $order = $config['order'] ?? null;
        }
        return $this->findAllN1ql($condition, $params, null, $order);
    }

    /**
     * Internal N1QL findAll
     */
    protected function findAllN1ql($condition = '', $params = [], $limit = null, $order = null)
    {
        try {
            $restClient = \Yii::app()->couchbaseRest;
            $n1ql = "SELECT s.* FROM `openeyes`.`reference`.`ophciexamination_element_set` s";
            
            // Convert SQL-style params (:param) to N1QL style ($param)
            $n1qlParams = [];
            if (!empty($params)) {
                foreach ($params as $key => $value) {
                    $cleanKey = ltrim($key, ':');
                    $n1qlParams[$cleanKey] = $value;
                }
            }
            
            if (!empty($condition)) {
                if (is_string($condition)) {
                    // Convert :param to $param for N1QL
                    $condition = preg_replace('/:(\w+)/', '\$$1', $condition);
                    $n1ql .= " WHERE " . $condition;
                }
            }
            
            // Handle ORDER BY
            if ($order) {
                // Convert SQL order to N1QL (add table alias)
                $order = preg_replace('/(\w+)\s+(asc|desc)/i', 's.$1 $2', $order);
                $n1ql .= " ORDER BY " . $order;
            } else {
                $n1ql .= " ORDER BY s.position, s.id";
            }
            
            if ($limit) {
                $n1ql .= " LIMIT " . (int)$limit;
            }
            
            $rows = $restClient->query($n1ql, $n1qlParams);
            
            $models = [];
            foreach ($rows as $row) {
                $models[] = $this->populateFromRow($row);
            }
            return $models;
        } catch (\Exception $e) {
            \Yii::log("ElementSet findAll failed: " . $e->getMessage(), \CLogger::LEVEL_ERROR);
            return [];
        }
    }
    
    /**
     * Save the model to Couchbase
     */
    public function save($runValidation = true, $attributes = null, $allow_overriding = false)
    {
        if ($runValidation && !$this->validate($attributes)) {
            return false;
        }
        
        try {
            $restClient = \Yii::app()->couchbaseRest;
            $isNew = $this->getIsNewRecord();
            
            // Generate new ID if needed
            if ($isNew && empty($this->id)) {
                $this->id = $this->generateNewId();
            }
            
            $docKey = "ophciexamination_element_set::{$this->id}";
            $doc = $this->toDocument();
            
            $n1ql = "UPSERT INTO `openeyes`.`reference`.`ophciexamination_element_set` (KEY, VALUE) VALUES (\$key, \$doc)";
            $restClient->query($n1ql, ['key' => $docKey, 'doc' => $doc]);
            
            $this->setIsNewRecord(false);
            return true;
        } catch (\Exception $e) {
            \Yii::log("ElementSet save failed: " . $e->getMessage(), \CLogger::LEVEL_ERROR);
            $this->addError('id', $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete the model from Couchbase
     */
    public function delete()
    {
        try {
            $restClient = \Yii::app()->couchbaseRest;
            $docKey = "ophciexamination_element_set::{$this->id}";
            
            $n1ql = "DELETE FROM `openeyes`.`reference`.`ophciexamination_element_set` USE KEYS [\$key]";
            $restClient->query($n1ql, ['key' => $docKey]);
            
            return true;
        } catch (\Exception $e) {
            \Yii::log("ElementSet delete failed: " . $e->getMessage(), \CLogger::LEVEL_ERROR);
            return false;
        }
    }
    
    /**
     * Generate a new unique ID
     */
    protected function generateNewId()
    {
        try {
            $restClient = \Yii::app()->couchbaseRest;
            $n1ql = "SELECT RAW MAX(s.id) FROM `openeyes`.`reference`.`ophciexamination_element_set` s";
            $rows = $restClient->query($n1ql, []);
            $maxId = !empty($rows) && $rows[0] !== null ? (int)$rows[0] : 0;
            return $maxId + 1;
        } catch (\Exception $e) {
            return time(); // Fallback to timestamp
        }
    }
    
    /**
     * Convert model to document for Couchbase
     */
    protected function toDocument()
    {
        return [
            'id' => (int)$this->id,
            'workflow_id' => (int)$this->workflow_id,
            'name' => $this->name,
            'position' => (int)$this->position,
            'is_active' => isset($this->is_active) ? (int)$this->is_active : 1,
            'display_order_edited' => isset($this->display_order_edited) ? (int)$this->display_order_edited : 0,
            '_type' => 'ophciexamination_element_set',
            '_mysql_id' => (int)$this->id,
            '_created' => $this->getIsNewRecord() ? date('c') : ($this->_created ?? date('c')),
            '_modified' => date('c'),
        ];
    }

    /**
     * Populate model from row
     */
    protected function populateFromRow($row)
    {
        $model = new self();
        $model->_isNewRecord = false;
        foreach ($row as $key => $value) {
            if (strpos($key, '_') === 0) {
                // Store metadata
                if ($key === '_created') {
                    $model->_created = $value;
                }
                continue;
            }
            if (property_exists($model, $key)) {
                $model->$key = $value;
            }
        }
        return $model;
    }
    
    /**
     * Check if this is a new record
     */
    public function getIsNewRecord()
    {
        return $this->_isNewRecord;
    }
    
    /**
     * Set new record flag
     */
    public function setIsNewRecord($value)
    {
        $this->_isNewRecord = $value;
    }
    
    /**
     * Check if model has attribute
     */
    public function hasAttribute($name)
    {
        return property_exists($this, $name);
    }

    /**
     * Get next step via N1QL
     */
    public function getNextStep()
    {
        try {
            $restClient = \Yii::app()->couchbaseRest;
            $n1ql = "SELECT s.* FROM `openeyes`.`reference`.`ophciexamination_element_set` s 
                     WHERE s.workflow_id = \$workflowId 
                     AND s.position >= \$position 
                     AND s.id != \$id 
                     AND (s.is_active = true OR s.is_active = 1)
                     ORDER BY s.position, s.id
                     LIMIT 1";
            
            $rows = $restClient->query($n1ql, [
                'workflowId' => (int)$this->workflow_id,
                'position' => (int)$this->position,
                'id' => (int)$this->id
            ]);
            
            if (empty($rows)) {
                return null;
            }
            
            return $this->populateFromRow($rows[0]);
        } catch (\Exception $e) {
            \Yii::log("getNextStep failed: " . $e->getMessage(), \CLogger::LEVEL_ERROR);
            return null;
        }
    }

    /**
     * Get items for this set via N1QL
     */
    public function getItems()
    {
        if ($this->_items === null) {
            $this->_items = $this->loadItems();
        }
        return $this->_items;
    }

    /**
     * Get visible items for this set
     */
    public function getVisibleItems()
    {
        if ($this->_visibleItems === null) {
            $this->_visibleItems = $this->loadItems(true);
        }
        return $this->_visibleItems;
    }

    /**
     * Load items from Couchbase
     */
    protected function loadItems($visibleOnly = false)
    {
        if (!$this->id) {
            return [];
        }
        
        try {
            $restClient = \Yii::app()->couchbaseRest;
            $n1ql = "SELECT i.*, et.id as et_id, et.name as et_name, et.class_name as et_class_name, 
                            et.display_order as et_display_order, et.event_type_id as et_event_type_id
                     FROM `openeyes`.`reference`.`ophciexamination_element_set_item` i
                     LEFT JOIN `openeyes`.`reference`.`element_type` et ON i.element_type_id = et.id
                     WHERE i.set_id = \$setId";
            
            if ($visibleOnly) {
                $n1ql .= " AND (i.is_hidden = false OR i.is_hidden = 0 OR i.is_hidden IS MISSING)";
            }
            
            $n1ql .= " ORDER BY i.display_order, et.display_order";
            
            $rows = $restClient->query($n1ql, ['setId' => (int)$this->id]);
            
            $items = [];
            foreach ($rows as $row) {
                $item = new OphCiExamination_ElementSetItem();
                $item->setIsNewRecord(false);
                $item->id = $row['id'] ?? null;
                $item->set_id = $row['set_id'] ?? null;
                $item->element_type_id = $row['element_type_id'] ?? null;
                $item->display_order = $row['display_order'] ?? null;
                $item->is_hidden = $row['is_hidden'] ?? false;
                $item->is_mandatory = $row['is_mandatory'] ?? false;
                
                // Create element_type relation
                if (isset($row['et_id'])) {
                    $et = new \ElementType();
                    $et->setIsNewRecord(false);
                    $et->id = $row['et_id'];
                    $et->name = $row['et_name'] ?? '';
                    $et->class_name = $row['et_class_name'] ?? '';
                    $et->display_order = $row['et_display_order'] ?? 0;
                    $et->event_type_id = $row['et_event_type_id'] ?? null;
                    $item->setElementType($et);
                }
                
                $items[] = $item;
            }
            return $items;
        } catch (\Exception $e) {
            \Yii::log("loadItems failed: " . $e->getMessage(), \CLogger::LEVEL_ERROR);
            return [];
        }
    }

    /**
     * Get an array of ElementTypes corresponding with the items in this set.
     *
     * @return \ElementType[]
     */
    public function getDefaultElementTypes($action = 'edit')
    {
        $element_types = [];
        $maximum_worklist_display_order = $this->getWorkFlowMaximumDisplayOrder();
        $visibleItems = $this->getVisibleItems();

        foreach ($visibleItems as $item) {
            $elementType = $item->getElementType();
            if (!$elementType) continue;
            
            if ($item->display_order) {
                $element_types[$item->display_order] = $elementType;
            } else {
                $element_types[$maximum_worklist_display_order + ($elementType->display_order ?? 0)] = $elementType;
            }
        }

        ksort($element_types);
        return $element_types;
    }

    /**
     * Returns the given elements set specific display order if it exists.
     */
    public function getSetElementOrder($element)
    {
        $visibleItems = $this->getVisibleItems();
        $elementElementType = $element->getElementType();
        
        if (!$elementElementType) {
            return null;
        }
        
        foreach ($visibleItems as $item) {
            $elementType = $item->getElementType();
            if ($elementType && $elementElementType->class_name == $elementType->class_name && $item->display_order) {
                return $item->display_order;
            }
        }
        return null;
    }

    public function getWorkFlowMaximumDisplayOrder()
    {
        $maximum_display_order = 0;
        $visibleItems = $this->getVisibleItems();
        foreach ($visibleItems as $item) {
            if ($item->display_order && $item->display_order > $maximum_display_order) {
                $maximum_display_order = $item->display_order;
            }
        }
        return $maximum_display_order;
    }

    /**
     * Get mandatory element types for this set
     */
    public function getMandatoryElementTypes()
    {
        $items = $this->getItems();
        $mandatoryTypes = [];
        
        foreach ($items as $item) {
            if ($item->is_mandatory) {
                $elementType = $item->getElementType();
                if ($elementType) {
                    $mandatoryTypes[] = $elementType;
                }
            }
        }
        
        return $mandatoryTypes;
    }

    /**
     * Get hidden element types for this set
     */
    public function getHiddenElementTypes()
    {
        $items = $this->getItems();
        $hiddenTypes = [];
        
        foreach ($items as $item) {
            if ($item->is_hidden) {
                $elementType = $item->getElementType();
                if ($elementType) {
                    $hiddenTypes[] = $elementType;
                }
            }
        }
        
        return $hiddenTypes;
    }

    /**
     * Check if deletable
     */
    public function isDeletable($step_id = null)
    {
        if (!$step_id) {
            $step_id = $this->id;
        }

        try {
            $restClient = \Yii::app()->couchbaseRest;
            $n1ql = "SELECT RAW COUNT(*) FROM `openeyes`.`core`.`ophciexamination_event_elementset_assignment` a 
                     WHERE a.step_id = \$stepId";
            $rows = $restClient->query($n1ql, ['stepId' => (int)$step_id]);
            return empty($rows) || $rows[0] === 0;
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
        ];
    }
}
