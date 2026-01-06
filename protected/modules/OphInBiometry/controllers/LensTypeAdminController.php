<?php

/**
 * Created by PhpStorm.
 * User: veta
 * Date: 06/05/15
 * Time: 11:30.
 */
class LensTypeAdminController extends BaseAdminController
{
    /**
     * @var int
     */
    public $itemsPerPage = 100;

    public $group = 'Biometry';

    /**
     * Lists lens types.
     *
     * @throws CHttpException
     */
    public function actionList()
    {
        $criteria = new CDbCriteria();
        $search = \Yii::app()->request->getPost('search', ['query' => '', 'active' => '']);

        if (Yii::app()->request->isPostRequest) {
            if ($search['query']) {
                $criteria->addCondition('name = :query', 'OR');
                $criteria->addCondition('id = :query', 'OR');
                $criteria->addCondition('display_name = :query', 'OR');
                $criteria->addCondition('description = :query', 'OR');
                $criteria->addCondition('acon = :query', 'OR');
                $criteria->params[':query'] = $search['query'];
            }

            if ($search['active'] == 1) {
                $criteria->addCondition('t.active = 1');
            } elseif ($search['active'] != '') {
                $criteria->addCondition('t.active != 1');
            }
        }

        $lensType_lens = OphInBiometry_LensType_Lens::model();

        $this->render('/admin/index', array(
            'pagination' => $this->initPagination($lensType_lens, $criteria),
            'lensType_lens' => $lensType_lens->findAll($criteria),
            'search' => $search,
        ));
    }

    /**
     * Edits or adds a lens type.
     *
     * @param bool|int $id
     *
     * @throws CHttpException
     */
    public function actionEdit($id = false)
    {
        $errors = [];
        $lensType_object = OphInBiometry_LensType_Lens::model()->findByPk($id);

        if (!$lensType_object) {
            $lensType_object = new OphInBiometry_LensType_Lens();
            if ($id) {
                $lensType_object->id = $id;
            }
        }

        if (Yii::app()->request->isPostRequest) {
            $user_data = \Yii::app()->request->getPost('OphInBiometry_LensType_Lens');
            $lensType_object->attributes = $user_data;

            if (!$lensType_object->save()) {
                $errors = $lensType_object->getErrors();
            } else {
                $this->redirect('/OphInBiometry/lensTypeAdmin/list');
            }
        }

        $this->render('/admin/edit', array(
            'lensType_lens' => $lensType_object,
            'errors' => $errors
        ));
    }

    /**
     * Deletes rows for the model.
     */
    public function actionDelete()
    {
        if (Yii::app()->request->isPostRequest) {
            $ids = Yii::app()->request->getPost('select');
            $saved = true;

            $transaction = Yii::app()->cbdb->beginTransaction();

            $lens_type_list = OphInBiometry_LensType_Lens::model()->findAllByPk($ids);

            try {
                foreach ($lens_type_list as $model) {
                    if (!$model->deleteMappings(ReferenceData::LEVEL_INSTITUTION)) {
                        $saved = false;
                    }

                    if (!$model->delete()) {
                        $saved = false;
                    }
                }
            } catch (Exception $e) {
                $saved = false;
            }

            if ($saved) {
                $transaction->commit();
                echo 1;
            } else {
                $transaction->rollback();
                echo 'error';
            }
        }
    }

    /**
     * Adds institution mappings for selected lens types.
     * Supports both GET (to display form) and POST (to process form) requests.
     *
     * @throws Exception
     */
    public function actionAddInstitutionMapping()
    {
        $errors = array();
        $lensTypes = OphInBiometry_LensType_Lens::model()->findAll();
        $success_message = null;
        
        if (Yii::app()->request->isPostRequest) {
            $ids = Yii::app()->request->getPost('select');
            
            if (empty($ids)) {
                $errors[] = 'Please select at least one lens type';
            } else {
                $transaction = Yii::app()->cbdb->beginTransaction();
                $institution_id = Institution::model()->getCurrent()->id;
                $lens_types = OphInBiometry_LensType_Lens::model()->findAllByPk($ids);
                
                try {
                    foreach ($lens_types as $lens_type) {
                        $lens_type->createMapping(ReferenceData::LEVEL_INSTITUTION, $institution_id);
                    }
                    $transaction->commit();
                    $success_message = 'Institution mapping added successfully for ' . count($lens_types) . ' lens type(s)';
                    $this->redirect('/OphInBiometry/lensTypeAdmin/list');
                } catch (Exception $e) {
                    $transaction->rollback();
                    $errors[] = 'Error: ' . $e->getMessage();
                }
            }
        }

        $this->render('/admin/addInstitutionMapping', array(
            'lensTypes' => $lensTypes,
            'errors' => $errors,
            'success_message' => $success_message,
        ));
    }

    /**
     * @throws Exception
     */
    public function actionDeleteInstitutionMapping()
    {
        $ids = Yii::app()->request->getPost('select');
        $transaction = Yii::app()->cbdb->beginTransaction();
        $errors = array();
        $institution_id = Institution::model()->getCurrent()->id;
        $lens_types = OphInBiometry_LensType_Lens::model()->findAllByPk($ids);
        try {
            foreach ($lens_types as $lens_type) {
                $lens_type->deleteMapping(ReferenceData::LEVEL_INSTITUTION, $institution_id);
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }

        if (!empty($errors)) {
            $transaction->rollback();
        } else {
            $transaction->commit();
        }
        $this->redirect('/OphInBiometry/lensTypeAdmin/list');
    }
}
