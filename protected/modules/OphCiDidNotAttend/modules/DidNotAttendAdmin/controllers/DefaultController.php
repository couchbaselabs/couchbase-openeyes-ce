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

namespace OEModule\OphCiDidNotAttend\modules\DidNotAttendAdmin\controllers;

class DefaultController extends \ModuleAdminController
{
    public $layout = null;

    protected function beforeAction($action)
    {
        // For submodules, we need to construct the asset path correctly
        $this->modulePath = \Yii::getPathOfAlias('OphCiDidNotAttend') . '/modules/DidNotAttendAdmin/assets';
        $this->assetPath = \Yii::app()->assetManager->publish($this->modulePath, true, -1);

        if (file_exists($this->modulePath . '/js/admin.js')) {
            $url = \Yii::app()->createUrl($this->assetPath . '/js/admin.js');
            \Yii::app()->clientScript->registerScriptFile($url);
        }

        return \BaseController::beforeAction($action);
    }

    public function actionIndex()
    {
        $this->layout= 'application.views.layouts.admin';
        $this->render('index');
    }
}
