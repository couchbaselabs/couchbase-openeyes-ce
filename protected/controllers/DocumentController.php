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
 * Wrapper controller that delegates to the oeadmin DocumentController
 * This allows the /document/list URL to work as specified
 */
class DocumentController extends BaseAdminController
{
    /**
     * @var string
     */
    public $layout = 'admin';

    /**
     * Implements the same functionality as oeadmin DocumentController::actionList
     * @throws CHttpException
     */
    public function actionList()
    {
        if (!$this->checkAccess('admin')) {
            throw new CHttpException(403, 'Only a system admin is permitted to change these settings.');
        }

        try {
            $model = OphCoDocument_Sub_Types::model();
            if (!$model) {
                throw new CHttpException(500, 'Unable to load document sub types model.');
            }

            $document_sub_types = $model->findAll();
            if ($document_sub_types === null) {
                $document_sub_types = array();
            }

            $this->render('//admin/document_sub_types', array('document_sub_types' => $document_sub_types));
        } catch (Exception $e) {
            if ($e instanceof CHttpException) {
                throw $e;
            }
            throw new CHttpException(500, 'Error loading document sub types: ' . $e->getMessage());
        }
    }
}
