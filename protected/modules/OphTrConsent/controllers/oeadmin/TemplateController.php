<?php

/**
 * OpenEyes
 *
 * (C) OpenEyes Foundation, 2021
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2021, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

class TemplateController extends BaseAdminController
{
    public $items_per_page = 60;
    public $group = 'Templates';

    public function actionList()
    {
        Audit::add(
            'admin',
            'list',
            null,
            false,
            array('module' => 'OphTrConsent',
            'model' => 'Template')
        );
        $query = \Yii::app()->request->getQuery('searchQuery');
        $subspecialty = \Yii::app()->request->getQuery('subspecialty');
        $institution = \Yii::app()->request->getQuery('institution');
        $site = \Yii::app()->request->getQuery('site');
        $criteria = new \CDbCriteria();
        $criteria->order = 'name';
        if ($query) {
            if (is_numeric($query)) {
                $criteria->addCondition('id = :id');
                $criteria->params[':id'] = $query;
            } else {
                $criteria->addSearchCondition('lower(name)', strtolower($query), true, 'OR');
            }
        }

        if ($institution) {
            if ($institution == "None") {
                $criteria->addCondition('institution_id IS NULL');
            } else {
                $criteria->compare('institution_id', $institution);
            }
        }

        if ($site) {
            if ($site == "None") {
                $criteria->addCondition('site_id IS NULL');
            } else {
                $criteria->compare('site_id', $site);
            }
        }

        if ($subspecialty) {
            if ($subspecialty == "None") {
                $criteria->addCondition('subspecialty_id IS NULL');
            } else {
                $criteria->compare('subspecialty_id', $subspecialty);
            }
        }

        $this->render('/oeadmin/templates/list_template', array(
            'pagination' => $this->initPagination(OphTrConsent_Template::model(), $criteria),
            'model_list' => OphTrConsent_Template::model()->findAll($criteria),
            'title' => 'Manage Template',
            'model_class' => 'Template',
            'query' => $query
        ));
    }

    public function actionEdit()
    {
        $request = Yii::app()->getRequest();
        $model = OphTrConsent_Template::model()->findByPk((int)$request->getParam('id'));
        if (!$model) {
            throw new Exception('Template not found with id ' . $request->getParam('id'));
        }
        if ($request->getPost('OphTrConsent_Template')) {
            $templateAtt = $request->getPost('OphTrConsent_Template');
            
            // Extract only the model attributes (exclude nested arrays like procedures)
            $modelAttributes = array();
            foreach ($templateAtt as $key => $value) {
                if (!is_array($value) || in_array($key, array('institution_id', 'site_id', 'subspecialty_id', 'type_id'))) {
                    $modelAttributes[$key] = $value;
                }
            }
            
            $model->attributes = $modelAttributes;
            
            if (!$model->validate()) {
                $errors = $model->getErrors();
            } else {
                // Use direct SQL UPDATE to bypass ORM issues with isSqlAvailable() checks
                $updates = $modelAttributes;
                unset($updates['id']); // Don't update the primary key
                
                $updatePairs = array();
                $params = array();
                foreach ($updates as $column => $value) {
                    $paramName = ':' . $column;
                    $updatePairs[] = "$column = $paramName";
                    $params[$paramName] = $value;
                }
                $params[':id'] = $model->id;
                
                $sql = 'UPDATE ' . $model->tableName() . ' SET ' . implode(', ', $updatePairs) . ' WHERE id = :id';
                
                try {
                    $command = Yii::app()->db->createCommand($sql);
                    $command->execute($params);
                    
                    Audit::add('admin', 'update', serialize($model->attributes), false, array('model' => 'Template', 'id' => $model->id));
                    Yii::app()->user->setFlash('success', 'Template saved');
                    if (!array_key_exists('firms', $templateAtt) || !is_array($templateAtt['firms'])) {
                        $templateAtt['firms'] = array();
                    }
                    if (array_key_exists('procedures', $templateAtt) && is_array($templateAtt['procedures'])) {
                        $model->saveProcedures($templateAtt['procedures']);
                    }
                    $this->redirect(array('List'));
                } catch (Exception $e) {
                    Yii::log('Failed to save template: ' . $e->getMessage(), CLogger::LEVEL_ERROR);
                    Yii::app()->user->setFlash('error', 'Failed to save template: ' . $e->getMessage());
                }
            }
        }

        $this->render('/oeadmin/templates/edit', array(
            'model' => $model,
            'title' => 'Edit Template',
            'errors' => isset($errors) ? $errors : null,
            'cancel_uri' => '/OphTrConsent/oeadmin/Template/list',
        ));
    }

    public function actionAdd()
    {
        $model = new OphTrConsent_Template();
        $request = Yii::app()->getRequest();
        if ($request->getPost('OphTrConsent_Template')) {
            $model->attributes = $request->getPost('OphTrConsent_Template');
            $templateAtt = $request->getPost('OphTrConsent_Template');

            if (!$model->validate()) {
                $errors = $model->getErrors();
            } else {
                if ($model->save()) {
                    Audit::add('admin', 'create', serialize($model->attributes), false, array('model' => 'Template'));
                    Yii::app()->user->setFlash('success', 'Template created');
                    if (!array_key_exists('firms', $templateAtt) || !is_array($templateAtt['firms'])) {
                        $templateAtt['firms'] = array();
                    }
                    if (array_key_exists('procedures', $templateAtt) && is_array($templateAtt['procedures'])) {
                        $model->saveProcedures($templateAtt['procedures']);
                    }
                    $this->redirect(array('List'));
                } else {
                    $errors = $model->getErrors();
                }
            }
        }
        $this->render('/oeadmin/templates/edit', array(
            'model' => $model,
            'title' => 'Add Template',
            'cancel_uri' => '/OphTrConsent/oeadmin/Template/list',
            'errors' => isset($errors) ? $errors : null,
        ));
    }

    public function actionDelete($id = null)
    {
        $result = [];
        $result['status'] = 1;
        $result['errors'] = [];

        // Handle direct ID parameter (e.g., /delete/1 or /delete?id=1)
        if ($id === null && !empty($_POST['templates'])) {
            // Handle POST data with templates array (bulk delete from list page)
            foreach (OphTrConsent_Template::model()->findAllByPk($_POST['templates']) as $consent_template) {
                $this->deleteTemplate($consent_template, $result);
            }
        } elseif ($id !== null) {
            // Handle direct ID parameter
            $consent_template = OphTrConsent_Template::model()->findByPk((int)$id);
            if (!$consent_template) {
                $result['status'] = 0;
                $result['errors'][] = "Template not found with id " . $id;
            } else {
                $this->deleteTemplate($consent_template, $result);
            }
        } else {
            // Neither ID parameter nor POST data provided
            $result['status'] = 1; // Still return success for backward compatibility
        }

        $this->renderJSON($result);
    }

    /**
     * Helper function to delete a template and its related procedures
     * @param OphTrConsent_Template $consent_template
     * @param array &$result Reference to result array to track errors
     */
    private function deleteTemplate($consent_template, &$result)
    {
        try {
            // Store the ID in a local variable to avoid overloaded property issues
            $templateId = (int)$consent_template->id;
            
            \Yii::log("Deleting template ID: " . $templateId, \CLogger::LEVEL_INFO, 'application');
            
            // First, delete all related template procedures using direct SQL
            $db = Yii::app()->db;
            $command = $db->createCommand('DELETE FROM ophtrconsent_template_procedure WHERE template_id = :template_id');
            $command->bindParam(':template_id', $templateId, PDO::PARAM_INT);
            $affectedRows = $command->execute();
            
            \Yii::log("Deleted $affectedRows template procedures for template ID $templateId", \CLogger::LEVEL_INFO, 'application');
            
            // Audit the deletion of procedures (execute() returns number of rows affected or 0)
            if ($affectedRows > 0) {
                Audit::add('admin-templateprocedure', 'delete', "Deleted $affectedRows procedure assignments for template " . $templateId);
            }
            
            // Now delete the template itself using direct SQL
            $command = $db->createCommand('DELETE FROM ophtrconsent_template WHERE id = :id');
            $command->bindParam(':id', $templateId, PDO::PARAM_INT);
            $affectedRows = $command->execute();
            
            \Yii::log("DELETE FROM ophtrconsent_template WHERE id = $templateId returned $affectedRows rows", \CLogger::LEVEL_INFO, 'application');
            
            // execute() returns number of rows affected, should be >= 1 for successful deletion
            if ($affectedRows > 0) {
                Audit::add('admin-template', 'delete', $consent_template);
            } else {
                // No rows were deleted - either template doesn't exist or already deleted
                $result['status'] = 0;
                $result['errors'][] = "Template with ID " . $templateId . " not found or already deleted (0 rows affected)";
            }
        } catch (Exception $e) {
            $result['status'] = 0;
            $result['errors'][] = "Template deletion error: " . $e->getMessage();
            \Yii::log("Template deletion error: " . $e->getMessage(), \CLogger::LEVEL_ERROR, 'application');
        }
    }
}
