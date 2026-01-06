<?php

/**
 * (C) OpenEyes Foundation, 2019
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (C) 2019, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */
class DisorderController extends BaseAdminController
{
    public $items_per_page = 60;
    public $group = 'Disorders';

    public function actionList()
    {
        Audit::add(
            'admin',
            'list',
            null,
            false,
            array('module' => 'OphTrOperationnote',
            'model' => 'Disorder')
        );
        $query = \Yii::app()->request->getQuery('searchQuery');
        $specialty = \Yii::app()->request->getQuery('specialty');
        
        // Query Couchbase directly since MariaDB is no longer available
        $cbRest = \Yii::app()->couchbaseRest;
        $n1ql = "SELECT disorder.* FROM `openeyes`.`reference`.`disorder` AS disorder WHERE 1=1";
        $params = array();
        
        if ($query) {
            if (is_numeric($query)) {
                $n1ql .= " AND disorder.id = \$queryId";
                $params['queryId'] = (int)$query;
            } else {
                $searchTerm = '%' . strtolower($query) . '%';
                $n1ql .= " AND (LOWER(disorder.fully_specified_name) LIKE \$searchTerm OR LOWER(disorder.term) LIKE \$searchTerm OR LOWER(disorder.aliases) LIKE \$searchTerm)";
                $params['searchTerm'] = $searchTerm;
            }
        }
        
        if ($specialty) {
            if ($specialty == "None") {
                $n1ql .= " AND disorder.specialty_id IS NULL";
            } else {
                $n1ql .= " AND disorder.specialty_id = \$specialtyId";
                $params['specialtyId'] = (int)$specialty;
            }
        }
        
        $n1ql .= " ORDER BY disorder.fully_specified_name";
        
        // Execute query
        $result = $cbRest->query($n1ql, $params);
        
        // Convert result to Disorder model instances for display
        $model_list = array();
        if ($result) {
            foreach ($result as $row) {
                $disorder = new Disorder();
                $disorder->setAttributes($row['disorder'] ?? $row, false);
                $model_list[] = $disorder;
            }
        }

        $this->render('/list_disorder', array(
            'pagination' => null, // Pagination disabled for Couchbase queries
            'model_list' => $model_list,
            'title' => 'Manage Disorder',
            'model_class' => 'Disorder',
            'query' => $query
        ));
    }

    public function actionEdit()
    {
        $request = Yii::app()->getRequest();
        $id = (int)$request->getParam('id');
        
        // Query Couchbase directly to find the disorder
        $cbRest = \Yii::app()->couchbaseRest;
        $n1ql = "SELECT disorder.* FROM `openeyes`.`reference`.`disorder` AS disorder WHERE disorder.id = \$disorderId";
        $result = $cbRest->query($n1ql, array('disorderId' => $id));
        $result = isset($result[0]) ? $result[0] : null;
        
        if (!$result) {
            throw new Exception('Disorder not found with id ' . $id);
        }
        
        $model = new Disorder();
        $model->setAttributes($result['disorder'] ?? $result, false);
        if ($request->getPost('Disorder')) {
            $model->attributes = $request->getPost('Disorder');
            if (!$model->validate()) {
                $errors = $model->getErrors();
            } else {
                if ($model->save()) {
                    Yii::app()->user->setFlash('success', 'Disorder saved');
                    $this->redirect(array('List'));
                } else {
                    $errors = $model->getErrors();
                }
            }
        }

        $this->render('/edit', array(
            'model' => $model,
            'title' => 'Edit Disorder',
            'errors' => isset($errors) ? $errors : null,
            'cancel_uri' => '/Admin/disorder/list',
        ));
    }

    public function actionAdd()
    {
        $model = new Disorder();
        $request = Yii::app()->getRequest();
        if ($request->getPost('Disorder')) {
            $model->attributes = $request->getPost('Disorder');

            if ($model->save()) {
                Audit::add('admin', 'create', serialize($model->attributes), false, array('model' => 'Disorder'));
                Yii::app()->user->setFlash('success', 'Disorder created');
                $this->redirect(array('List'));
            } else {
                $errors = $model->getErrors();
            }
        }
        $this->render('/edit', array(
            'model' => $model,
            'title' => 'Add Disorder',
            'cancel_uri' => '/Admin/Disorder/list',
            'errors' => isset($errors) ? $errors : null,
        ));
    }

    public function actionDelete()
    {
        $result = [];
        $result['status'] = 1;
        $result['errors'] = "";

        if (!empty($_POST['disorders'])) {
            foreach (Disorder::model()->findAllByPk($_POST['disorders']) as $disorder) {
                try {
                    if (!$disorder->delete()) {
                        $result['status'] = 0;
                        $result['errors'][]= $disorder->getErrors();
                    } else {
                        Audit::add('admin-disorder', 'delete', $disorder);
                    }
                } catch (Exception $e) {
                    $result['status'] = 0;
                    $result['errors'][]= "Disorder: " . $disorder->term . " is in use";
                }
            }
        }

        $this->renderJSON($result);
    }
}
