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
 * This is the model class for table "ophciexamination_workflow".
 * Uses Couchbase as the primary database.
 *
 * @property int $id
 * @property string $name
 * @property int $institution_id
 * @property \Institution $institution
 * @property OphCiExamination_ElementSet[] $steps
 * @property OphCiExamination_ElementSet $first_step
 */
class OphCiExamination_Workflow extends \BaseActiveRecordVersioned
{
    use \OwnedByReferenceData;
    use \OE\Models\Traits\CouchbaseModelBridge;
    use HasFactory;

    protected $_steps = null;
    protected $_first_step = null;
    protected $_active_steps = null;

    public function couchbaseScope()
    {
        return 'reference';
    }

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return 'ophciexamination_workflow';
    }

    public function defaultScope()
    {
        return [];
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['name', 'required'],
            ['id, name, institution_id', 'safe', 'on' => 'search'],
        ];
    }

    /**
     * @return array relational rules - kept for compatibility but relations loaded via N1QL
     */
    public function relations()
    {
        return [
            'institution' => [self::BELONGS_TO, \Institution::class, 'institution_id'],
        ];
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'institution_id' => 'Institution',
        ];
    }

    /**
     * Find workflow by primary key using N1QL
     */
    public function findByPk($pk, $condition = '', $params = [])
    {
        try {
            $restClient = \Yii::app()->couchbaseRest;
            $n1ql = "SELECT w.* FROM `openeyes`.`reference`.`ophciexamination_workflow` w WHERE w.id = \$id LIMIT 1";
            $rows = $restClient->query($n1ql, ['id' => (int)$pk]);
            
            if (empty($rows)) {
                return null;
            }
            
            return $this->populateFromRow($rows[0]);
        } catch (\Exception $e) {
            \Yii::log("Workflow findByPk failed: " . $e->getMessage(), \CLogger::LEVEL_ERROR);
            return null;
        }
    }

    /**
     * Find all workflows using N1QL
     */
    public function findAll($condition = '', $params = [])
    {
        try {
            $restClient = \Yii::app()->couchbaseRest;
            $n1ql = "SELECT w.* FROM `openeyes`.`reference`.`ophciexamination_workflow` w ORDER BY w.name";
            $rows = $restClient->query($n1ql, []);
            
            $models = [];
            foreach ($rows as $row) {
                $models[] = $this->populateFromRow($row);
            }
            return $models;
        } catch (\Exception $e) {
            \Yii::log("Workflow findAll failed: " . $e->getMessage(), \CLogger::LEVEL_ERROR);
            return [];
        }
    }

    /**
     * Populate model from database row
     */
    protected function populateFromRow($row)
    {
        $model = new self();
        $model->setIsNewRecord(false);
        foreach ($row as $key => $value) {
            if (strpos($key, '_') === 0) continue;
            if ($model->hasAttribute($key)) {
                $model->$key = $value;
            }
        }
        return $model;
    }

    /**
     * Get steps for this workflow via N1QL
     */
    public function getSteps()
    {
        if ($this->_steps === null) {
            $this->_steps = $this->loadSteps();
        }
        return $this->_steps;
    }

    /**
     * Get active steps for this workflow
     */
    public function getActive_steps()
    {
        if ($this->_active_steps === null) {
            $this->_active_steps = $this->loadSteps(true);
        }
        return $this->_active_steps;
    }

    /**
     * Get first active step for this workflow
     */
    public function getFirst_step()
    {
        if ($this->_first_step === null) {
            $steps = $this->getActive_steps();
            $this->_first_step = !empty($steps) ? $steps[0] : null;
        }
        return $this->_first_step;
    }

    /**
     * Load steps from Couchbase
     */
    protected function loadSteps($activeOnly = false)
    {
        if (!$this->id) {
            \Yii::log("loadSteps called with no workflow id", \CLogger::LEVEL_WARNING);
            return $this->createDefaultStep();
        }
        
        try {
            $restClient = \Yii::app()->couchbaseRest;
            
            // Try multiple query approaches as the data might be stored differently
            $queries = [
                // Try with integer workflow_id
                "SELECT s.* FROM `openeyes`.`reference`.`ophciexamination_element_set` s WHERE s.workflow_id = \$workflowId",
                // Try with string comparison
                "SELECT s.* FROM `openeyes`.`reference`.`ophciexamination_element_set` s WHERE TO_STRING(s.workflow_id) = \$workflowIdStr",
            ];
            
            $rows = [];
            foreach ($queries as $baseQuery) {
                $n1ql = $baseQuery;
                if ($activeOnly) {
                    $n1ql .= " AND (s.is_active = true OR s.is_active = 1 OR s.is_active IS MISSING)";
                }
                $n1ql .= " ORDER BY s.position, s.id";
                
                $params = [
                    'workflowId' => (int)$this->id,
                    'workflowIdStr' => (string)$this->id
                ];
                
                $rows = $restClient->query($n1ql, $params);
                
                if (!empty($rows)) {
                    break;
                }
            }
            
            // If still no steps found, try to get ALL element sets and filter
            if (empty($rows)) {
                \Yii::log("No steps found for workflow {$this->id} ({$this->name}), trying fallback query", \CLogger::LEVEL_WARNING);
                $n1ql = "SELECT s.* FROM `openeyes`.`reference`.`ophciexamination_element_set` s LIMIT 100";
                $allRows = $restClient->query($n1ql, []);
                
                foreach ($allRows as $row) {
                    $rowWorkflowId = $row['workflow_id'] ?? null;
                    if ($rowWorkflowId == $this->id || (string)$rowWorkflowId === (string)$this->id) {
                        if (!$activeOnly || ($row['is_active'] ?? true)) {
                            $rows[] = $row;
                        }
                    }
                }
            }
            
            if (empty($rows)) {
                \Yii::log("No element_set documents found for workflow {$this->id} ({$this->name})", \CLogger::LEVEL_WARNING);
                return $this->createDefaultStep();
            }
            
            $steps = [];
            foreach ($rows as $row) {
                $step = new OphCiExamination_ElementSet();
                $step->setIsNewRecord(false);
                foreach ($row as $key => $value) {
                    if (strpos($key, '_') === 0) continue;
                    if ($step->hasAttribute($key)) {
                        $step->$key = $value;
                    }
                }
                $steps[] = $step;
            }
            
            // Sort by position
            usort($steps, function($a, $b) {
                return ($a->position ?? 0) - ($b->position ?? 0);
            });
            
            return $steps;
        } catch (\Exception $e) {
            \Yii::log("Failed to load workflow steps: " . $e->getMessage(), \CLogger::LEVEL_ERROR);
            return $this->createDefaultStep();
        }
    }

    /**
     * Create a default step when none exists
     */
    protected function createDefaultStep()
    {
        $step = new OphCiExamination_ElementSet();
        $step->id = 0;
        $step->name = 'Default';
        $step->workflow_id = $this->id;
        $step->position = 1;
        $step->is_active = 1;
        $step->setIsNewRecord(false);
        return [$step];
    }

    /**
     * First step (set) in this workflow.
     *
     * @return OphCiExamination_ElementSet
     * @throws \SystemException
     */
    public function getFirstStep()
    {
        $firstStep = $this->getFirst_step();
        if (!$firstStep) {
            throw new \SystemException("Incomplete workflow '$this->name' has no steps configured");
        }
        return $firstStep;
    }

    /**
     * Save to Couchbase
     */
    public function save($runValidation = true, $attributes = null, $allow_overriding = false)
    {
        if ($runValidation && !$this->validate($attributes)) {
            return false;
        }
        
        if (!$this->beforeSave()) {
            return false;
        }
        
        $isNewRecord = $this->getIsNewRecord();
        $now = date('Y-m-d H:i:s');
        
        if ($isNewRecord && !$this->id) {
            $this->id = (int)floor(microtime(true) * 1000);
        }
        if ($isNewRecord) {
            $this->created_date = $now;
            $this->created_user_id = \Yii::app()->user->id ?? 1;
        }
        $this->last_modified_date = $now;
        $this->last_modified_user_id = \Yii::app()->user->id ?? 1;
        
        try {
            $restClient = \Yii::app()->couchbaseRest;
            $doc = $this->toCouchbaseDocument();
            $docId = $this->tableName() . '::' . $this->id;
            $restClient->upsert('reference', $this->tableName(), $docId, $doc);
            
            $this->setIsNewRecord(false);
            $this->afterSave();
            return true;
        } catch (\Exception $e) {
            \Yii::log("Workflow save failed: " . $e->getMessage(), \CLogger::LEVEL_ERROR);
            return false;
        }
    }

    /**
     * Delete from Couchbase
     */
    public function delete()
    {
        if (!$this->beforeDelete()) {
            return false;
        }
        
        try {
            $restClient = \Yii::app()->couchbaseRest;
            $docId = $this->tableName() . '::' . $this->id;
            $restClient->remove('reference', $this->tableName(), $docId);
            $this->afterDelete();
            return true;
        } catch (\Exception $e) {
            \Yii::log("Workflow delete failed: " . $e->getMessage(), \CLogger::LEVEL_ERROR);
            return false;
        }
    }

    /**
     * Check if institution can be changed
     */
    public function canChangeInstitution(): bool
    {
        try {
            $restClient = \Yii::app()->couchbaseRest;
            $n1ql = "SELECT RAW COUNT(*) FROM `openeyes`.`reference`.`ophciexamination_workflow_rule` r 
                     WHERE r.workflow_id = \$workflowId";
            $rows = $restClient->query($n1ql, ['workflowId' => (int)$this->id]);
            return empty($rows) || $rows[0] === 0;
        } catch (\Exception $e) {
            return true;
        }
    }

    protected function getSupportedLevelMask(): int
    {
        return \ReferenceData::LEVEL_INSTALLATION |
            \ReferenceData::LEVEL_INSTITUTION;
    }
}
