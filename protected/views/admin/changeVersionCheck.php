<?php
/**
 * (C) OpenEyes Foundation, 2019
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
?>

<div class="admin-index-header">
    <h1>Version Check Settings</h1>
</div>

<?php
$form = $this->beginWidget('BaseEventTypeCActiveForm', array(
    'id' => 'changeVersionCheckForm',
    'method' => 'post',
    'action' => $this->createUrl('changeVersionCheck'),
));
?>

<div class="row">
    <div class="column">
        <fieldset>
            <legend>Auto Version Check</legend>
            <div class="row">
                <div class="column">
                    <label for="version_check_enabled">
                        <?php echo CHtml::radioButtonList('value', $setting_value, array(1 => 'Enabled', 0 => 'Disabled')); ?>
                    </label>
                </div>
            </div>
        </fieldset>
    </div>
</div>

<div class="row">
    <div class="column">
        <?php echo CHtml::submitButton('Save', array('class' => 'green hint', 'id' => 'et_save')); ?>
        <?php echo CHtml::link('Cancel', $this->createUrl('/admin'), array('class' => 'blue hint')); ?>
    </div>
</div>

<?php $this->endWidget(); ?>
