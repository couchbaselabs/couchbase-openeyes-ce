<?php
/**
 * OpenEyes
 *
 * (C) OpenEyes Foundation, 2019
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2019, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */
?>

<div class="box admin">
    <div class="box-header">
        <h1>Create Document Recipients</h1>
    </div>
    
    <div class="box-content">
        <?php
            // Display flash messages
            if (Yii::app()->user->hasFlash('success')):
        ?>
            <div class="alert alert-success">
                <?php echo Yii::app()->user->getFlash('success'); ?>
            </div>
        <?php
            endif;
            if (Yii::app()->user->hasFlash('error')):
        ?>
            <div class="alert alert-danger">
                <?php echo Yii::app()->user->getFlash('error'); ?>
            </div>
        <?php
            endif;
        ?>
        <?php
            $this->renderPartial('/docman/_create', array(
                'row_index' => isset($row_index) ? $row_index : 0,
                'macro_data' => isset($macro_data) ? $macro_data : array(),
                'patient_id' => isset($patient_id) ? $patient_id : null,
                'macro_id' => isset($macro_id) ? $macro_id : 7,
                'element' => isset($element) ? $element : new ElementLetter(),
                'can_send_electronically' => isset($can_send_electronically) ? $can_send_electronically : true,
            ));
        ?>
    </div>
</div>
