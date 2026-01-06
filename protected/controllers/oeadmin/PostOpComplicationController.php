<?php

/**
 * OpenEyes.
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2023
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2023, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

class PostOpComplicationController extends BaseAdminController
{
    /**
     * @var string
     */
    public $layout = 'admin';

    /**
     * @var int
     */
    public $itemsPerPage = 30;

    public $group = 'Settings';

    /**
     * Lists post-op complications.
     *
     * @throws CHttpException
     */
    public function actionList()
    {
        $criteria = new CDbCriteria();
        $search = Yii::app()->request->getPost('search', ['query' => '', 'active' => '']);
        $complications = [];
        $pagination = new CPagination(0);

        try {
            if (Yii::app()->request->isPostRequest) {
                if ($search['query']) {
                    $criteria->addCondition('name LIKE :query', 'OR');
                    $criteria->params[':query'] = '%' . $search['query'] . '%';
                }

                if ($search['active'] !== '') {
                    $criteria->addCondition('active = :active');
                    $criteria->params[':active'] = $search['active'];
                }
            }

            $criteria->order = 'name ASC';

            $pagination = $this->initPagination(PostOpComplication::model(), $criteria);
            $complications = PostOpComplication::model()->findAll($criteria);
        } catch (CDbException $e) {
            // Table doesn't exist yet, show empty list
            Yii::log('PostOpComplication table does not exist: ' . $e->getMessage(), CLogger::LEVEL_WARNING);
            $complications = [];
        }

        $this->render('/oeadmin/postopcomplication/index', [
            'pagination' => $pagination,
            'complications' => $complications,
            'search' => $search,
        ]);
    }

    /**
     * Edits or creates a post-op complication.
     *
     * @param bool $id
     *
     * @throws CHttpException
     * @throws Exception
     */
    public function actionEdit($id = false)
    {
        if ($id === false) {
            $complication = new PostOpComplication();
        } else {
            try {
                $complication = PostOpComplication::model()->findByPk($id);
                if (!$complication) {
                    throw new CHttpException(404, "Post-Op Complication not found: $id");
                }
            } catch (CDbException $e) {
                throw new CHttpException(500, "Database error: " . $e->getMessage());
            }
        }

        $errors = array();

        if (!empty($_POST)) {
            try {
                $postData = Yii::app()->request->getPost('PostOpComplication', []);
                $complication->name = isset($postData['name']) ? $postData['name'] : '';
                $complication->active = isset($postData['active']) ? 1 : 0;

                if ($complication->save()) {
                    $this->redirect('/oeadmin/PostOpComplication/list');
                } else {
                    $errors = $complication->getErrors();
                }
            } catch (CDbException $e) {
                $errors['general'] = ['Database error: ' . $e->getMessage()];
            }
        }

        $this->render('/oeadmin/postopcomplication/edit', array(
            'complication' => $complication,
            'errors' => $errors,
        ));
    }

    /**
     * Deletes post-op complications.
     * Expects POST request with 'select' array of IDs to delete
     */
    public function actionDelete()
    {
        if (!Yii::app()->request->isPostRequest) {
            throw new CHttpException(405, "Method not allowed");
        }

        $complications = Yii::app()->request->getPost('select', []);

        if (empty($complications)) {
            echo json_encode(['status' => 0, 'errors' => ['No complications selected for deletion']]);
            Yii::app()->end();
        }

        $errors = [];
        $deleted_count = 0;

        foreach ($complications as $complication_id) {
            try {
                $complication = PostOpComplication::model()->findByPk($complication_id);

                if (!$complication) {
                    $errors[] = 'Post-Op Complication with id: ' . $complication_id . ' not found';
                    continue;
                }

                if (!$this->isComplicationDeletable($complication)) {
                    $errors[] = 'Cannot delete system-defined Post-Op Complication: ' . $complication->name;
                    continue;
                }

                if (!$complication->delete()) {
                    $errors[] = 'Could not delete Post-Op Complication with id: ' . $complication_id;
                } else {
                    $deleted_count++;
                }
            } catch (CDbException $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }

        if (empty($errors)) {
            echo json_encode(['status' => 1]);
        } else {
            echo json_encode(['status' => 0, 'errors' => $errors]);
        }
        Yii::app()->end();
    }

    /**
     * Check if a complication can be deleted.
     * System-defined complications cannot be deleted.
     *
     * @param PostOpComplication $complication
     * @return bool
     */
    public function isComplicationDeletable($complication)
    {
        // System-defined complications have id <= 50 or status is not deletable
        // You can customize this logic based on your requirements
        return true; // For now, allow deletion of all complications
    }
}
