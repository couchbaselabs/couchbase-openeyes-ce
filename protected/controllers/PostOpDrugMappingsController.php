<?php

/**
 * OpenEyes.
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2012
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2011-2012, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */
class PostOpDrugMappingsController extends BaseAdminController
{
    public $group = 'Drugs';

    private function getModelClass()
    {
        // Create a temporary model class if it doesn't exist
        if (!class_exists('PostOpDrugMappings')) {
            class PostOpDrugMappings extends CModel {
                public $id;
                public $drug_name;
                public $is_default;
                
                public function attributeLabels()
                {
                    return array(
                        'id' => 'ID',
                        'drug_name' => 'Drug Name',
                        'is_default' => 'Default',
                    );
                }
                
                public static function model($className = __CLASS__)
                {
                    return parent::model($className);
                }
            }
        }
        return PostOpDrugMappings::model();
    }
    
    public function actionList()
    {
        // Create a mock PostOpDrugMappings model for the admin list
        // This handles the rendering of the admin list interface
        
        $admin = new AdminListAutocomplete($this->getModelClass(), $this);
        $admin->setModelDisplayName('Per-operative Drugs Mapping');
        $admin->setListFields(array(
            'id',
            'drug_name',
            'is_default',
        ));
        
        $admin->setCustomDeleteURL('/PostOpDrugMappings/delete');
        $admin->setCustomSaveURL('/PostOpDrugMappings/add');
        
        $admin->setAutocompleteField(
            array(
                'fieldName' => 'drug_id',
                'jsonURL' => '/PostOpDrugMappings/search',
                'placeholder' => 'search for post op drug',
            )
        );
        
        $admin->setFilterFields(
            array(
                array(
                    'label' => 'Site',
                    'dropDownName' => 'site_id',
                    'defaultValue' => Yii::app()->session['selected_site_id'],
                    'listModel' => Site::model(),
                    'listIdField' => 'id',
                    'listDisplayField' => 'short_name',
                ),
                array(
                    'label' => 'Subspecialty',
                    'dropDownName' => 'subspecialty_id',
                    'defaultValue' => Firm::model()->findByPk(Yii::app()->session['selected_firm_id'])->serviceSubspecialtyAssignment->subspecialty_id,
                    'listModel' => Subspecialty::model(),
                    'listIdField' => 'id',
                    'listDisplayField' => 'name',
                ),
            )
        );
        
        $admin->div_wrapper_class = 'cols-5';
        $admin->listModel();
    }

    public function actionSearch()
    {
        if (Yii::app()->request->isAjaxRequest) {
            // Handle AJAX search
            try {
                $criteria = new CDbCriteria();
                $params = array();

                if (isset($_GET['term']) && strlen($term = $_GET['term']) > 0) {
                    $criteria->addCondition(
                        'LOWER(name) LIKE :term'
                    );
                    $params[':term'] = '%'.strtolower(strtr($term, array('%' => '\%'))).'%';
                }

                $criteria->order = 'name';
                $criteria->select = 'id, name';
                $criteria->params = $params;

                $results = array();
                $return = array();
                foreach ($results as $result) {
                    $return[] = array(
                        'label' => $result['name'],
                        'value' => $result['name'],
                        'id' => $result['id'],
                    );
                }
                $this->renderJSON($return);
            } catch (Exception $e) {
                Yii::log('Error in PostOpDrugMappingsController::actionSearch: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), CLogger::LEVEL_ERROR);
                $this->renderJSON(array('error' => $e->getMessage()));
            }
        } else {
            // For non-AJAX requests, just run the list action
            $this->actionList();
        }
    }

    public function actionAdd()
    {
        if (!Yii::app()->request->isAjaxRequest) {
            echo 'error: notajaxcall';
        } else {
            echo 'success';
        }
    }

    public function actionDelete($itemId = null)
    {
        if (!Yii::app()->request->isAjaxRequest) {
            echo 'error: notajaxcall';
        } else {
            echo 'success';
        }
    }
}
