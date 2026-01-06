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
 * This is the model class for rules that apply workflows.
 * Uses Couchbase as the primary database.
 *
 * @property integer $id
 * @property integer $workflow_id
 * @property integer $firm_id
 * @property integer $subspecialty_id
 * @property integer $episode_status_id
 * @property OphCiExamination_Workflow $workflow
 */
class OphCiExamination_Workflow_Rule extends \BaseActiveRecordVersioned
{
    use HasFactory;
    use \OE\Models\Traits\CouchbaseModelBridge;

    protected $_workflow = null;

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
        return 'ophciexamination_workflow_rule';
    }

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return [
            ['subspecialty_id, firm_id, episode_status_id, workflow_id', 'safe'],
            ['id', 'safe', 'on' => 'search'],
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
     * Get the workflow for this rule via N1QL
     */
    public function getWorkflow()
    {
        if ($this->_workflow === null && $this->workflow_id) {
            $this->_workflow = OphCiExamination_Workflow::model()->findByPk($this->workflow_id);
        }
        return $this->_workflow;
    }

    /**
     * Set workflow relation
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
            $n1ql = "SELECT r.* FROM `openeyes`.`reference`.`ophciexamination_workflow_rule` r WHERE r.id = \$id LIMIT 1";
            $rows = $restClient->query($n1ql, ['id' => (int)$pk]);
            
            if (empty($rows)) {
                return null;
            }
            
            return $this->populateFromRow($rows[0]);
        } catch (\Exception $e) {
            \Yii::log("WorkflowRule findByPk failed: " . $e->getMessage(), \CLogger::LEVEL_ERROR);
            return null;
        }
    }

    /**
     * Find single record using N1QL
     */
    public function find($condition = '', $params = [])
    {
        $results = $this->findAllN1ql($condition, $params, 1);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Find all records using N1QL
     */
    public function findAll($condition = '', $params = [])
    {
        return $this->findAllN1ql($condition, $params);
    }

    /**
     * Internal N1QL findAll implementation
     */
    protected function findAllN1ql($condition = '', $params = [], $limit = null)
    {
        try {
            $restClient = \Yii::app()->couchbaseRest;
            $n1ql = "SELECT r.* FROM `openeyes`.`reference`.`ophciexamination_workflow_rule` r";
            
            $whereClause = $this->buildWhereClause($condition, $params);
            if ($whereClause) {
                $n1ql .= " WHERE " . $whereClause;
            }
            
            $n1ql .= " ORDER BY r.firm_id DESC, r.episode_status_id DESC, r.subspecialty_id DESC";
            
            if ($limit) {
                $n1ql .= " LIMIT " . (int)$limit;
            }
            
            $rows = $restClient->query($n1ql, $params);
            
            $models = [];
            foreach ($rows as $row) {
                $models[] = $this->populateFromRow($row);
            }
            return $models;
        } catch (\Exception $e) {
            \Yii::log("WorkflowRule findAll failed: " . $e->getMessage(), \CLogger::LEVEL_ERROR);
            return [];
        }
    }

    /**
     * Build WHERE clause from condition
     */
    protected function buildWhereClause($condition, &$params)
    {
        if (empty($condition)) {
            return '';
        }
        
        if (is_string($condition)) {
            // Convert MySQL-style placeholders to N1QL
            $condition = preg_replace('/:(\w+)/', '\$$1', $condition);
            $condition = preg_replace('/\bt\./', 'r.', $condition);
            return $condition;
        }
        
        return '';
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
     * Find the best matching workflow using N1QL.
     *
     * @param int|\Firm $firm_id
     * @param int $status_id
     * @return OphCiExamination_Workflow
     * @throws \CException
     */
    public function findWorkflowCascading($firm_id, $status_id)
    {
        $firm = $firm_id instanceof \Firm ? $firm_id : \Firm::model()->findByPk($firm_id);
        if (!$firm) {
            return $this->getDefaultWorkflow();
        }
        
        $subspecialty_id = ($firm->serviceSubspecialtyAssignment) 
            ? $firm->serviceSubspecialtyAssignment->subspecialty_id 
            : null;
        $institution_id = $firm->institution_id;

        try {
            $restClient = \Yii::app()->couchbaseRest;
            
            // Query workflow rules with JOIN to workflows
            $n1ql = "SELECT r.*, w.id as wf_id, w.name as wf_name, w.institution_id as wf_institution_id
                     FROM `openeyes`.`reference`.`ophciexamination_workflow_rule` r
                     JOIN `openeyes`.`reference`.`ophciexamination_workflow` w ON r.workflow_id = w.id
                     WHERE (r.firm_id = \$firmId OR r.firm_id IS MISSING OR r.firm_id IS NULL)
                     AND (w.institution_id = \$institutionId OR w.institution_id IS MISSING OR w.institution_id IS NULL)
                     ORDER BY r.firm_id DESC, r.episode_status_id DESC, r.subspecialty_id DESC";
            
            $rows = $restClient->query($n1ql, [
                'firmId' => (int)$firm->id,
                'institutionId' => (int)$institution_id
            ]);
            
            if (empty($rows)) {
                return $this->getDefaultWorkflow();
            }
            
            // Find the best matching rule
            foreach ($rows as $row) {
                $ruleSubspecialtyId = $row['subspecialty_id'] ?? null;
                $ruleEpisodeStatusId = $row['episode_status_id'] ?? null;
                
                // Episode and subspecialty must match or be null
                $episodeMatch = ($ruleEpisodeStatusId == $status_id || !$ruleEpisodeStatusId);
                $subspecialtyMatch = ($ruleSubspecialtyId == $subspecialty_id || !$ruleSubspecialtyId);
                
                if (!$episodeMatch || !$subspecialtyMatch) {
                    continue;
                }
                
                // Get the workflow ID and try to load it properly
                $workflowId = $row['wf_id'] ?? $row['workflow_id'];
                
                // Try to load full workflow using findByPk first
                $workflow = OphCiExamination_Workflow::model()->findByPk($workflowId);
                
                if (!$workflow) {
                    // Fallback: Build workflow manually from row data
                    $workflow = new OphCiExamination_Workflow();
                    $workflow->setIsNewRecord(false);
                    $workflow->id = $workflowId;
                    $workflow->name = $row['wf_name'] ?? 'Workflow';
                    $workflow->institution_id = $row['wf_institution_id'] ?? null;
                }
                
                \Yii::log("findWorkflowCascading returning workflow: id={$workflow->id}, name={$workflow->name}", \CLogger::LEVEL_INFO);
                return $workflow;
            }
            
            return $this->getDefaultWorkflow();
            
        } catch (\Exception $e) {
            \Yii::log("findWorkflowCascading failed: " . $e->getMessage(), \CLogger::LEVEL_ERROR);
            return $this->getDefaultWorkflow();
        }
    }

    /**
     * Get or create a default workflow when none found
     */
    protected function getDefaultWorkflow()
    {
        \Yii::log("getDefaultWorkflow called - no matching workflow rule found", \CLogger::LEVEL_WARNING);
        
        // Try to find any workflow
        try {
            $restClient = \Yii::app()->couchbaseRest;
            $n1ql = "SELECT w.* FROM `openeyes`.`reference`.`ophciexamination_workflow` w LIMIT 1";
            $rows = $restClient->query($n1ql, []);
            
            if (!empty($rows)) {
                $workflowId = $rows[0]['id'] ?? null;
                \Yii::log("Found default workflow id: {$workflowId}", \CLogger::LEVEL_INFO);
                
                // Use findByPk for proper loading
                if ($workflowId) {
                    $workflow = OphCiExamination_Workflow::model()->findByPk($workflowId);
                    if ($workflow) {
                        return $workflow;
                    }
                }
                
                // Fallback to manual creation
                $workflow = new OphCiExamination_Workflow();
                $workflow->setIsNewRecord(false);
                foreach ($rows[0] as $key => $value) {
                    if (strpos($key, '_') === 0) continue;
                    if ($workflow->hasAttribute($key)) {
                        $workflow->$key = $value;
                    }
                }
                return $workflow;
            }
        } catch (\Exception $e) {
            \Yii::log("getDefaultWorkflow query failed: " . $e->getMessage(), \CLogger::LEVEL_WARNING);
        }
        
        \Yii::log("No workflows found in database, creating synthetic default", \CLogger::LEVEL_WARNING);
        
        // Build a synthetic default workflow with a default step
        $wf = new OphCiExamination_Workflow();
        $wf->id = 0;
        $wf->name = 'Default Workflow';
        $wf->setIsNewRecord(false);
        
        return $wf;
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return array(
            'id' => 'ID',
            'subspecialty_id' => 'Subspecialty',
            'firm_id' => \Firm::contextLabel(),
            'episode_status_id' => 'Episode status',
            'workflow_id' => 'Workflow',
        );
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
            \Yii::log("WorkflowRule save failed: " . $e->getMessage(), \CLogger::LEVEL_ERROR);
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
            \Yii::log("WorkflowRule delete failed: " . $e->getMessage(), \CLogger::LEVEL_ERROR);
            return false;
        }
    }

    public function findWorkflowSteps($institution_id, $episode_status_id)
    {
        $firms = \Firm::model()->findAll('institution_id = :institution_id', array(':institution_id' => $institution_id));
        $workflowSteps = [];

        foreach ($firms as $firm) {
            $workflow = self::model()->findWorkflowCascading($firm, $episode_status_id);
            $workflowSteps[$firm->id] = $workflow ? $workflow->active_steps : null;
        }

        return $workflowSteps;
    }
}
