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
class OperativeDeviceMappingController extends ModuleAdminController
{
    public $group = 'Operation note';

    /**
     * To list the operative devices per site per subspeciality
     */
    public function actionList()
    {
        $admin = new AdminListAutocomplete(SiteSubspecialtyOperativeDevice::model(), $this);

        $admin->setListFields(array(
            'id',
            'devices.name',
            'default',
        ));

        $admin->setCustomDeleteURL('/OphTrOperationnote/OperativeDeviceMapping/delete');
        $admin->setCustomSaveURL('/OphTrOperationnote/OperativeDeviceMapping/add');
        $admin->setCustomSetDefaultURL('/OphTrOperationnote/OperativeDeviceMapping/setDefault');
        $admin->setCustomRemoveDefaultURL('/OphTrOperationnote/OperativeDeviceMapping/removeDefault');
        $admin->setModelDisplayName('Operation Note Operative Device Mapping');
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

        // we set default search options
        if ($this->request->getParam('search') == '') {
            $admin->getSearch()->initSearch(array(
                    'filterid' => array(
                        'subspecialty_id' => Firm::model()->findByPk(Yii::app()->session['selected_firm_id'])->serviceSubspecialtyAssignment->subspecialty_id,
                        'site_id' => Yii::app()->session['selected_site_id'],
                    ),
                ));
        }

        $admin->setAutocompleteField(
            array(
                'fieldName' => 'operative_device_id',
                'allowBlankSearch' => 1,
                'jsonURL' => '/OphTrOperationnote/OperativeDeviceMapping/search',
                'placeholder' => 'search for adding operative devices',
            )
        );

        $admin->div_wrapper_class = 'cols-7';
        //$admin->searchAll();
        $admin->listModel();
    }

    /**
     * Delete an operative device association with the site
     * @param $itemId
     */
    public function actionDelete($itemId = null)
    {
        /*
        * We make sure to not allow deleting directly with the URL, user must come from the commondrugs list page
        */
        if (Yii::app()->request->isAjaxRequest) {
            if (!$itemId) {
                echo 'error';
                return;
            }
            if ($leafletSubspecialy = SiteSubspecialtyOperativeDevice::model()->findByPk($itemId)) {
                $leafletSubspecialy->delete();
                echo 'success';
            } else {
                echo 'error';
            }
        } else {
            // Handle non-AJAX requests - redirect to list page
            $this->redirect(array('list'));
        }
    }

    /**
     * To set default values to Operative Device
     * @param $item_id
     */
    public function actionSetDefault($itemId = null)
    {
        /*
        * We make sure to not allow deleting directly with the URL, user must come from the commondrugs list page
        */
        if (Yii::app()->request->isAjaxRequest) {
            if (!$itemId) {
                echo 'error';
                return;
            }
            $currentSSOD = SiteSubspecialtyOperativeDevice::model()->findByPk($itemId);
            if ($currentSSOD) {
                $currentSSOD->default = 1;
                if ($currentSSOD->update()) {
                    echo 'success set default to SSOD';
                } else {
                    echo 'error';
                }
            } else {
                echo 'error';
            }
        } else {
            // Handle non-AJAX requests - redirect to list page
            $this->redirect(array('list'));
        }
    }

    /**
     * To remove default values to Operative Device
     * @param $item_id
     */
    public function actionRemoveDefault($itemId = null)
    {
        /*
        * We make sure to not allow deleting directly with the URL, user must come from the commondrugs list page
        */
        if (Yii::app()->request->isAjaxRequest) {
            if (!$itemId) {
                echo 'error';
                return;
            }
            $currentSSOD = SiteSubspecialtyOperativeDevice::model()->findByPk($itemId);
            if ($currentSSOD) {
                $currentSSOD->default = 0;
                if ($currentSSOD->save()) {
                    echo 'success remove default to SSOD';
                } else {
                    echo 'error';
                }
            } else {
                echo 'error';
            }
        } else {
            // Handle non-AJAX requests - redirect to list page
            $this->redirect(array('list'));
        }
    }

    /**
     * To add new operative devices to the site.
     */
    public function actionAdd()
    {
        $model = new SiteSubspecialtyOperativeDevice();
        
        // Handle AJAX requests (for AdminListAutocomplete functionality)
        if (Yii::app()->request->isAjaxRequest) {
            $subspecialtyId = $this->request->getParam('subspecialty_id');
            $siteId = $this->request->getParam('site_id');
            $operativeDeviceId = $this->request->getParam('operative_device_id');
            
            if (!is_numeric($subspecialtyId) || !is_numeric($siteId) || !is_numeric($operativeDeviceId)) {
                echo 'error';
            } else {
                $criteria = new CDbCriteria();
                $criteria->condition = 'operative_device_id=:operative_device_id AND site_id=:site_id AND subspecialty_id=:subspecialty_id';
                $criteria->params = array(
                    ':operative_device_id' => $operativeDeviceId,
                    ':site_id' => $siteId,
                    ':subspecialty_id' => $subspecialtyId,
                );
                $currentSSOD = SiteSubspecialtyOperativeDevice::model()->findall($criteria);
                if (!$currentSSOD) {
                    $newSSOD = new SiteSubspecialtyOperativeDevice();
                    $newSSOD->subspecialty_id = $subspecialtyId;
                    $newSSOD->site_id = $siteId;
                    $newSSOD->operative_device_id = $operativeDeviceId;
                    if ($newSSOD->save()) {
                        echo 'success added to SSAA';
                    } else {
                        echo 'error';
                    }
                } else {
                    echo 'success';
                }
            }
            return;
        }
        
        // Handle form requests (POST and GET)
        $request = Yii::app()->getRequest();
        
        // Set default values from session
        $site_id = Yii::app()->session['selected_site_id'];
        $firm = Firm::model()->findByPk(Yii::app()->session['selected_firm_id']);
        $subspecialty_id = $firm->serviceSubspecialtyAssignment->subspecialty_id;
        
        $model->site_id = $site_id;
        $model->subspecialty_id = $subspecialty_id;
        
        if ($request->getPost('SiteSubspecialtyOperativeDevice')) {
            $model->attributes = $request->getPost('SiteSubspecialtyOperativeDevice');
            if ($model->save()) {
                Yii::app()->user->setFlash('success', 'Operative Device Mapping created successfully');
                $this->redirect(array('list'));
            } else {
                // Log save errors for debugging and display them
                $errors = $model->getErrors();
                Yii::log('Save failed for SiteSubspecialtyOperativeDevice: ' . json_encode($errors), 'error');
                
                // Add error message to view
                foreach ($errors as $field => $messages) {
                    foreach ($messages as $message) {
                        Yii::app()->user->setFlash('error', $message);
                    }
                }
            }
        }
        
        // Render the form view
        $this->render('/admin/add', array(
            'model' => $model,
            'title' => 'Add Operative Device Mapping',
            'cancel_uri' => '/OphTrOperationnote/OperativeDeviceMapping/list',
        ));
    }

    /**
     * To search the operative devices.
     */
    public function actionSearch()
    {
        if (Yii::app()->request->isAjaxRequest) {
            $criteria = new CDbCriteria();
            $params = array();
            if (isset($_GET['term'])) {
                $term = $_GET['term'];
                $criteria->addCondition(
                    array('LOWER(name) LIKE :term'),
                    'OR'
                );
                $params[':term'] = '%' . strtolower(strtr($term, array('%' => '\%'))) . '%';
            }

            $criteria->order = 'name';
            $criteria->select = 'id, name';
            $criteria->params = $params;

            $results = OperativeDevice::model()->active()->findAll($criteria);

            $return = array();
            foreach ($results as $resultRow) {
                $return[] = array(
                    'label' => $resultRow->name,
                    'value' => $resultRow->name,
                    'id' => $resultRow->id,
                );
            }
            $this->renderJSON($return);
        } else {
            // Handle non-AJAX requests - redirect to list page
            $this->redirect(array('list'));
        }
    }

}
