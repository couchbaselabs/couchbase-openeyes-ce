<?php
/**
 * (C) OpenEyes Foundation, 2020
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (C) 2017, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

class AttachmentDataController extends \AdminController
{
    public $layout = '//layouts/admin';
    public $group = 'API';

    public function accessRules()
    {
        // Allow logged in users - the main authorisation check happens later in verifyActionAccess
        return array_merge(
            array(
                array(
                    'allow',
                    'roles' => array('OprnEditRequestData'),
                )
            ),
            parent::accessRules()
        );
    }

    public function actionEdit($id = null)
    {
        $request = Yii::app()->getRequest();
        $model = AttachmentData::model()->findByPk($id);

        if (!$model) {
            throw new CHttpException(404, 'Request Data not found with id ' . $id);
        }

        if ($request->getPost('AttachmentData')) {
            $model->attributes = $request->getPost('AttachmentData');

            if ($model->save()) {
                $this->redirect(array('/Api/Request/admin/request/index'));
            } else {
                $errors = $model->getErrors();
            }
        }

        // Handle null or empty text_data - use empty object as default
        $decoded_data = null;
        if (!empty($model->text_data)) {
            $decoded_data = json_decode($model->text_data);
        }
        
        // Use empty object as default if decode fails or data is null
        if ($decoded_data === null && empty($model->text_data)) {
            $decoded_data = new \stdClass();
        }
        
        $json_pretty_text_data = json_encode(
            $decoded_data,
            JSON_PRETTY_PRINT
        );

        $this->render('/attachmentData/edit', array(
            'model' => $model,
            'title' => 'Edit Request Data',
            'errors' => isset($errors) ? $errors : null,
            'cancel_uri' => '/Api/Request/admin/request/index',
            'text_data' => $json_pretty_text_data
        ));
    }

    public function actionDownload($id = null)
    {
        // Validate that ID is provided
        if (empty($id)) {
            throw new CHttpException(400, 'Attachment ID is required');
        }

        $model = AttachmentData::model()->findByPk($id);

        if (!$model) {
            throw new CHttpException(404, 'Attachment not found');
        }

        if (empty($model->blob_data)) {
            throw new CHttpException(404, 'Attachment file is empty or not found');
        }

        // Set headers for file download
        header('Content-Type: ' . (!empty($model->mime_type) ? $model->mime_type : 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . (!empty($model->upload_file_name) ? basename($model->upload_file_name) : 'attachment') . '"');
        header('Content-Length: ' . strlen($model->blob_data));
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');

        echo $model->blob_data;
        Yii::app()->end();
    }
}
