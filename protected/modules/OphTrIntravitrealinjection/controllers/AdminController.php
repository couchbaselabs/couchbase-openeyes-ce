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
class AdminController extends ModuleAdminController
{
    public $defaultAction = 'ViewAllOphTrIntravitrealinjection_Treatment_Drug';
    public $group = 'Intravitreal injection';
    
    public function init()
    {
        parent::init();
        // Disable CSRF validation for AJAX requests
        Yii::app()->request->enableCsrfValidation = false;
    }

    public function actionViewTreatmentDrugs()
    {
        $model_list = OphTrIntravitrealinjection_Treatment_Drug::model()->findAll(array('order' => 'display_order asc'));
        $this->jsVars['OphTrIntravitrealinjection_sort_url'] = $this->createUrl('sortTreatmentDrugs');

        Audit::add('admin', 'list', null, null, array('module' => 'OphTrIntravitrealinjection', 'model' => 'OphTrIntravitrealinjection_Treatment_Drug'));

        $this->render('list_OphTrIntravitrealinjection_Treatment_Drug', array(
                'model_list' => $model_list,
                'title' => 'Treatment Drugs',
                'model_class' => 'OphTrIntravitrealinjection_Treatment_Drug',
        ));
    }

    public function actionAddTreatmentDrug()
    {
        $model = new OphTrIntravitrealinjection_Treatment_Drug();

        if (isset($_POST['OphTrIntravitrealinjection_Treatment_Drug'])) {
            $model->attributes = $_POST['OphTrIntravitrealinjection_Treatment_Drug'];

            if ($bottom_drug = OphTrIntravitrealinjection_Treatment_Drug::model()->find(array('order' => 'display_order desc'))) {
                $display_order = $bottom_drug->display_order + 1;
            } else {
                $display_order = 1;
            }
            $model->display_order = $display_order;

            if ($model->save()) {
                Audit::add('admin', 'create', $model->id, null, array('module' => 'OphTrIntravitrealinjection', 'model' => 'OphTrIntravitrealinjection_Treatment_Drug'));
                Yii::app()->user->setFlash('success', 'Treatment drug created');

                $this->redirect(array('ViewTreatmentDrugs'));
            }
        }

        $this->render('update', array(
            'model' => $model,
            'title' => 'Add Treatment Drug',
            'cancel_uri' => '/OphTrIntravitrealinjection/admin/viewTreatmentDrugs',
        ));
    }

    public function actionEditTreatmentDrug($id)
    {
        if (!$model = OphTrIntravitrealinjection_Treatment_Drug::model()->findByPk((int) $id)) {
            throw new Exception('Treatment drug not found with id ' . $id);
        }

        if (isset($_POST['OphTrIntravitrealinjection_Treatment_Drug'])) {
            $model->attributes = $_POST['OphTrIntravitrealinjection_Treatment_Drug'];

            if ($model->save()) {
                Audit::add('admin', 'update', $model->id, null, array('module' => 'OphTrIntravitrealinjection', 'model' => 'OphTrIntravitrealinjection_Treatment_Drug'));
                Yii::app()->user->setFlash('success', 'Treatment drug updated');

                $this->redirect(array('ViewTreatmentDrugs'));
            }
        }

        $this->render('update', array(
            'model' => $model,
            'title' => 'Edit Treatment Drug',
            'cancel_uri' => '/OphTrIntravitrealinjection/admin/viewTreatmentDrugs',
        ));
    }

    /*
     * sorts the drugs into the provided order (NOTE does not support a paginated list of drugs)
     */
    public function actionSortTreatmentDrugs()
    {
        if (\Yii::app()->request->isPostRequest) {
            if (!empty($_POST['order'])) {
                foreach ($_POST['order'] as $i => $id) {
                    if ($drug = OphTrIntravitrealinjection_Treatment_Drug::model()->findByPk($id)) {
                        $drug->display_order = $i + 1;
                        if (!$drug->save()) {
                            throw new Exception('Unable to save drug: ' . print_r($drug->getErrors(), true));
                        }
                    }
                }
            }
        } else {
            // For GET requests, display the sortable list
            $model_list = OphTrIntravitrealinjection_Treatment_Drug::model()->findAll(array('order' => 'display_order asc'));
            $this->jsVars['OphTrIntravitrealinjection_sort_url'] = $this->createUrl('sortTreatmentDrugs');

            Audit::add('admin', 'list', null, null, array('module' => 'OphTrIntravitrealinjection', 'model' => 'OphTrIntravitrealinjection_Treatment_Drug'));

            $this->render('sort_OphTrIntravitrealinjection_Treatment_Drug', array(
                'model_list' => $model_list,
                'title' => 'Sort Treatment Drugs',
                'model_class' => 'OphTrIntravitrealinjection_Treatment_Drug',
            ));
        }
    }

    public function actionDeleteTreatmentDrugs()
    {
        if (!Yii::app()->request->isPostRequest) {
            throw new CHttpException(400, 'Invalid request method.');
        }

        $treatment_drugs = Yii::app()->request->getPost('treatment_drugs', []);
        
        if (empty($treatment_drugs)) {
            echo 0;
            return;
        }

        $result = 1;

        foreach (OphTrIntravitrealinjection_Treatment_Drug::model()->findAllByPk($treatment_drugs) as $drug) {
            if (!$drug->delete()) {
                $result = 0;
            }
        }

        echo $result;
    }

    public function actionManageIOPLoweringDrugs()
    {
        $this->genericAdmin('Edit IOP Lowering Drugs', 'OphTrIntravitrealinjection_IOPLoweringDrug', ['div_wrapper_class' => 'cols-5']);
    }

    public function actionManageSkinDrugs()
    {
        $this->genericAdmin('Edit Skin cleansing drugs', 'OphTrIntravitrealinjection_SkinDrug', ['div_wrapper_class' => 'cols-5']);
    }

    public function actionManageAntisepticDrugs()
    {
        $this->genericAdmin('Edit Antiseptic drugs', 'OphTrIntravitrealinjection_AntiSepticDrug', ['div_wrapper_class' => 'cols-5']);
    }

    public function actionInjectionUsers()
    {
        $injection_users = OphTrIntravitrealinjection_InjectionUser::model()->with(array('user'))->findAll(array('order' => 'user.last_name, user.first_name'));
        $user_ids = CHtml::listData($injection_users, 'id', 'user_id');

        $criteria = new CDbCriteria();
        $criteria->order = 'first_name asc, last_name asc';

        if (!empty($user_ids)) {
            $criteria->addNotInCondition('id', $user_ids);
        }

        $user_list = User::model()->findAll($criteria);

        $this->render('injection_users', array(
            'injection_users' => $injection_users,
            'user_list' => $user_list,
        ));
    }

    public function actionAddInjectionUser()
    {
        if (!Yii::app()->request->isPostRequest) {
            throw new CHttpException(400, 'Invalid request method');
        }

        if (!$user = User::model()->findByPk(@$_POST['user_id'])) {
            throw new Exception('User not found: ' . @$_POST['user_id']);
        }

        $injection_user = new OphTrIntravitrealinjection_InjectionUser();
        $injection_user->user_id = $user->id;

        // Try to save and catch any exceptions
        if (!$injection_user->save()) {
            // Model validation failed
            throw new Exception('Unable to save injection user. Errors: ' . json_encode($injection_user->errors) . ', Attributes: ' . json_encode($injection_user->attributes));
        }

        echo '1';
    }

    public function actionDeleteInjectionUsers()
    {
        if (!Yii::app()->request->isPostRequest) {
            throw new CHttpException(400, 'Invalid request method.');
        }

        $injection_users_ids = Yii::app()->request->getPost('injection_users', []);
        $success = 1;
        
        if (empty($injection_users_ids)) {
            echo $success;
            return;
        }

        try {
            foreach ($injection_users_ids as $injection_user_id) {
                try {
                    $model = OphTrIntravitrealinjection_InjectionUser::model()->findByPk($injection_user_id);
                    if (!$model) {
                        Yii::log('Injection user not found with id: ' . $injection_user_id, CLogger::LEVEL_WARNING);
                        continue;
                    }
                    
                    // Disable Couchbase sync for deletion to avoid sync errors
                    if (method_exists($model, 'disableCouchbaseSync')) {
                        $model->disableCouchbaseSync();
                    }
                    
                    if (!$model->delete()) {
                        Yii::log('Failed to delete injection user with id: ' . $injection_user_id, CLogger::LEVEL_ERROR);
                        $success = 0;
                    }
                } catch (Exception $e) {
                    Yii::log('Error deleting injection user ' . $injection_user_id . ': ' . $e->getMessage(), CLogger::LEVEL_ERROR);
                    // Log error but continue processing other users
                    $success = 0;
                }
            }
        } catch (Exception $e) {
            Yii::log('Unexpected error in actionDeleteInjectionUsers: ' . $e->getMessage(), CLogger::LEVEL_ERROR);
            $success = 0;
        }

        echo $success;
    }
}
