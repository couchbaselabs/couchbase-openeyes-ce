<?php
/**
 * (C) OpenEyes Foundation, 2018
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2019, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

class AllergyController extends BaseController
{
    public function accessRules()
    {
        return array(
            array(
                'allow',
                'roles' => array('OprnViewClinical'),
            ),
        );
    }

    public function actionAutocomplete()
    {
        if (Yii::app()->request->isAjaxRequest) {
            $criteria = new CDbCriteria();
            $params = array();
            if (isset($_GET['term']) && $name = $_GET['term']) {
                $criteria->addCondition('LOWER(name) LIKE :name COLLATE utf8_general_ci');
                $params[':name'] = '%' . strtolower(strtr($name, array('%' => '\%'))) . '%';
            }
            $criteria->order = 'name';
            // Limit results
            $criteria->limit = '200';
            $criteria->params = $params;

            $return = array();
            try {
                $allergies = Allergy::model()->active()->findAll($criteria);
                foreach ($allergies as $allergy) {
                    $return[] = $this->allergyStructure($allergy);
                }
            } catch (CDbException $e) {
                // Handle case where table doesn't exist (database not initialized or Couchbase-only mode)
                // Log the error but return empty results gracefully
                Yii::log('Allergy table not accessible: ' . $e->getMessage(), CLogger::LEVEL_WARNING, 'application');
                // Return empty array instead of throwing exception
                $return = array();
            }
            $this->renderJSON($return);
        }
    }

    protected function allergyStructure($allergy)
    {
        return [
            'label' => $allergy->name,
            'id' => $allergy->id,
        ];
    }

}
