<?php
/**
 * OpenEyes
 *
 * (C) OpenEyes Foundation, 2018
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2018, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

class RefMedicationSetAdminController extends BaseAdminController
{
    public function actionList()
    {
        $ref_set_id = Yii::app()->request->getParam('ref_set_id');
        
        if (!$ref_set_id) {
            $this->redirect('/OphDrPrescription/refSetAdmin/list');
            return;
        }
        
        $medSet = MedicationSet::model()->findByPk($ref_set_id);

        if (!$medSet) {
            throw new CHttpException(404, 'Medication set not found.');
        }

        $admin = new Admin(MedicationSetItem::model(), $this);
        $admin->setListFields(array(
            'medication.preferred_term',
        ));

        $admin->getSearch()->addSearchItem('medication.preferred_term');
        $admin->getSearch()->setItemsPerPage(30);
        $crit = new CDbCriteria();
        $crit->order = 'id ASC';
        $crit->condition = 'medication_set_id = '.$medSet->id;
        $admin->getSearch()->setCriteria($crit);

        $admin->setIsSubList(true);
        $admin->setSubListParent(array(
            'medication_set_id' => $medSet->id
        ));

        $admin->setModelDisplayName("Medications in set '{$medSet->name}'");
        $admin->setForceTitleDisplay(true);
        $admin->setForceFormDisplay(true);

        $admin->setListFieldsAction('medEditRedir');

        $admin->listModel();
    }

    public function actionMedEditRedir($id = null)
    {
        if (!$id) {
            $this->redirect('/OphDrPrescription/RefSetAdmin/list');
            return;
        }
        $medSetItem = MedicationSetItem::model()->findByPk($id);
        if (!$medSetItem) {
            throw new CHttpException(404, 'Medication set item not found.');
        }
        $ref_med_id = $medSetItem->medication_id;
        $this->redirect('/OphDrPrescription/RefMedicationAdmin/edit/'.$ref_med_id);
    }

    public function actionEdit($id = null)
    {
        $admin = new Admin(MedicationSetItem::model(), $this);

        // Get medication_set_id from the model if editing, or from parameters if creating
        $ref_set_id = null;
        
        if ($id) {
            // If editing an existing item, load it and get the medication_set_id from the model
            $item = MedicationSetItem::model()->findByPk($id);
            if (!$item) {
                throw new CHttpException(404, 'Medication set item not found.');
            }
            $ref_set_id = $item->medication_set_id;
        } else {
            // If creating a new item, get the medication_set_id from the default parameters
            $default_params = Yii::app()->request->getParam('default');
            if (is_array($default_params) && isset($default_params['medication_set_id'])) {
                $ref_set_id = $default_params['medication_set_id'];
            }
        }
        
        if (!$ref_set_id) {
            throw new CHttpException(400, 'Missing medication set ID.');
        }
        
        $medSet = MedicationSet::model()->findByPk($ref_set_id);

        if (!$medSet) {
            throw new CHttpException(404, 'Medication set not found.');
        }

        $admin->setEditFields(array(
            'medication_id'=> array(
                'widget' => 'RefMedicationLookup',
                'label' => 'Medication',
                'options' => array(
                    'hiddenFieldName' => 'MedicationSetItem[medication_id]',
                    'markupAfter' => '<br/>'
                )
            ),
            'medication_set_id' => 'hidden'
        ));
        $admin->setModelDisplayName("medication to set '{$medSet->name}'");
        if ($id) {
            $admin->setModelId($id);
        }

        $admin->editModel();
    }

    public function actionDelete()
    {
        $postedData = Yii::app()->request->getPost('MedicationSetItem');
        if (!$postedData || !isset($postedData['id'])) {
            throw new CHttpException(400, 'Invalid request. Missing medication set item IDs.');
        }
        
        $ids_to_delete = $postedData['id'];
        if (is_array($ids_to_delete)) {
            foreach ($ids_to_delete as $id) {
                $model = MedicationSetItem::model()->findByPk($id);
                if ($model) {
                    $model->delete();
                }
            }
        }

        exit("1");
    }
}
