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

/**
 * ComplicationController
 * This controller provides the /complication/list route
 */
class ComplicationController extends BaseAdminController
{
    public function actionList()
    {
        $criteria = new CDbCriteria();
        $search = \Yii::app()->request->getPost('search', ['query' => '', 'active' => '']);

        if (Yii::app()->request->isPostRequest) {
            if ($search['query']) {
                $criteria->addCondition('(name = :query OR id = :query)');
                $criteria->params[':query'] = $search['query'];
            }

            if ($search['active'] == 1) {
                $criteria->addCondition('t.active = 1');
            } elseif ($search['active'] != '') {
                $criteria->addCondition('t.active != 1');
            }
        }

        $complication = Complication::model();

        $this->render('/oeadmin/complication/index', [
            'pagination' => $this->initPagination($complication, $criteria),
            'complications' => $complication->findAll($criteria),
            'search' => $search,
        ]);
    }

    public function actionEdit($id = false)
    {
        $errors = [];
        $complication_object = Complication::model()->findByPk($id);

        if (!$complication_object) {
            $complication_object = new Complication();
            if ($id) {
                $complication_object->id = $id;
            }
        }

        if (Yii::app()->request->isPostRequest) {
            $user_data = \Yii::app()->request->getPost('Complication');

            $complication_object->name = $user_data['name'];
            $complication_object->active = $user_data['active'];

            if (!$complication_object->save()) {
                $errors = $complication_object->getErrors();
            } else {
                $this->redirect('/complication/list/');
            }
        }

        $this->render('/oeadmin/complication/edit', array(
            'complication' => $complication_object,
            'errors' => $errors
        ));
    }

    protected function isComplicationDeletable(Complication $complication)
    {
        $check_dependencies = 1;

        $options = [':id' => $complication->id];
        $check_dependencies &= !ProcedureComplication::model()->count('complication_id = :id', $options);

        return $check_dependencies;
    }

    public function actionDelete()
    {
        $complications = \Yii::app()->request->getPost('select', []);

        foreach ($complications as $complication_id) {
            $complication = Complication::model()->findByPk($complication_id);

            if ($this->isComplicationDeletable($complication)) {
                if (!$complication->delete()) {
                    echo 'Could not delete complication with id: ' . $complication_id . '.\n';
                }
            } else {
                echo 'Complication with id ' . $complication_id .' cannot be deleted. Other tables depend on it.\n';
            }
        }
        echo 1;
    }

    public function getId()
    {
        return 'complication';
    }

    public function getLayoutFile($layoutName = null)
    {
        return parent::getLayoutFile('admin');
    }
}
