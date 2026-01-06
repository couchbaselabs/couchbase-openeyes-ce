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
?>

<div class="box admin">
    <div class="box-title">
        <h2>Add Workflow Step</h2>
    </div>
    <div class="box-content">
        <form method="POST" action="<?php echo Yii::app()->createUrl('OphCiExamination/admin/addworkflowStep'); ?>">
            <fieldset class="row full">
                <legend class="cols-full">Select a workflow to add a step to:</legend>
                
                <div class="cols-full row">
                    <label for="workflow_id" class="cols-2">Workflow:</label>
                    <div class="cols-4">
                        <?php echo CHtml::dropDownList(
                            'workflow_id',
                            '',
                            CHtml::listData($workflows, 'id', 'name'),
                            array('empty' => '-- Select a workflow --', 'required' => 'required')
                        ); ?>
                    </div>
                </div>
            </fieldset>
            
            <div class="row full">
                <div class="cols-full">
                    <button type="submit" class="button large">Add step</button>
                    <button type="button" class="button large" onclick="window.location='/admin/index'; return false;">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>
