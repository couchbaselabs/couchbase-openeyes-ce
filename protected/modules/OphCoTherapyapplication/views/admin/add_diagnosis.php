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

<div class="cols-3">

<div class="row divider">
    <h2><?php echo $title ?></h2>
</div>

<?php $this->renderPartial('//base/_messages')?>

<?php
$form = $this->beginWidget('BaseEventTypeCActiveForm', array(
    'id' => 'add-diagnosis-form',
    'enableAjaxValidation' => false,
    'action' => Yii::app()->createURL($this->module->getName().'/admin/addDiagnosis'),
));

if ($parent_id) {
    echo CHtml::hiddenField('parent_id', $parent_id);
}
?>

<fieldset class="field-row">
    <legend class="data-group collapsible">
        Add Diagnosis
    </legend>
    <div class="data-group">
        <div class="field-row">
            <div class="label">
                <?php echo CHtml::label('Disorder', 'disorder_id'); ?>
            </div>
            <div class="full-width">
                <?php
                $form->widget('application.widgets.DiagnosisSelection', array(
                    'field' => 'new_disorder_id',
                    'layout' => 'minimal',
                    'default' => false,
                    'callback' => 'OphCoTherapyapplication_AddDiagnosis',
                    'placeholder' => 'type the first few characters to search',
                ));
                ?>
                <?php echo CHtml::hiddenField('disorder_id', '', array('id' => 'disorder_id')); ?>
            </div>
        </div>
    </div>
</fieldset>

<div class="form-actions">
    <?php echo CHtml::submitButton('Add', array('class' => 'button primary large')); ?>
    <?php echo CHtml::link('Cancel', $cancel_uri, array('class' => 'button secondary large')); ?>
</div>

<?php $this->endWidget(); ?>

</div>
