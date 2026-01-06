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

class RoutesAdminController extends BaseAdminController
{
    public $group = 'Drugs';

    public function actionList()
    {
        $admin = new Admin(MedicationRoute::model(), $this);
        $admin->getSearch()->getCriteria()->addColumnCondition(['deleted_date' => null]);
        $admin->setListFields(array(
            'id',
            'term',
            'source_type',
            'source_subtype',
            'getHasLateralityIcon',
            'getIsActiveIcon'
        ));

        $admin->setCustomAddURL('/OphDrPrescription/routesAdmin/add');
        $admin->getSearch()->addSearchItem('term');
        $admin->getSearch()->addSearchItem('source_type');
        $admin->getSearch()->addSearchItem('source_subtype');

        $admin->setModelDisplayName('Medication Routes');

        $admin->listModel();
    }

    public function actionEdit($id)
    {
        $admin = new Admin(MedicationRoute::model(), $this);
        $admin->setModelId($id);
        $admin->setEditFields(array(
            'term' => 'Term',
            'source_type' => 'Source Type',
            'source_subtype' => 'Source Subtype',
            'has_laterality' => 'checkbox',
            'is_active' => 'checkbox'
        ));

        $admin->editModel();
    }

    public function actionAdd()
    {
        $admin = new Admin(MedicationRoute::model(), $this);
        $admin->setEditFields(array(
            'term' => 'Term',
            'source_type' => 'Source Type',
            'source_subtype' => 'Source Subtype',
            'has_laterality' => 'checkbox'
        ));

        $admin->editModel();
    }

    public function actionDelete($id = null)
    {
        // Handle both POST requests from list page and GET requests from URL
        $post = Yii::app()->request->getPost('MedicationRoute');
        
        // Get IDs either from POST data or URL parameter
        if ($post && isset($post['id'])) {
            $attribute_ids_array = $post['id'];
        } elseif ($id !== null) {
            $attribute_ids_array = [$id];
        } else {
            // No ID provided
            echo 0;
            return;
        }

        $result = 1;
        foreach ($attribute_ids_array as $key => $id) {
            try {
                $route = MedicationRoute::model()->findByPk($id);
                if ($route) {
                    // Set deleted_date for soft delete
                    $route->deleted_date = date('Y-m-d H:i:s');
                    
                    // Disable validation on save to avoid issues with has_laterality validation
                    if ($route->save(false)) {
                        Yii::log('Successfully deleted route ' . $id . ' (soft delete)', CLogger::LEVEL_INFO);
                    } else {
                        Yii::log('Failed to save deleted_date for route ' . $id, CLogger::LEVEL_ERROR);
                        $result = 0;
                    }
                } else {
                    Yii::log('Route not found with ID: ' . $id, CLogger::LEVEL_WARNING);
                    $result = 0;
                }
            } catch (Exception $e) {
                Yii::log('Exception while deleting route ' . $id . ': ' . $e->getMessage(), CLogger::LEVEL_ERROR);
                $result = 0;
            }
        }
        echo $result;
    }
}
