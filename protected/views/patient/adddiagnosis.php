<?php
/**
 * OpenEyes.
 *
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
?>
<section class="full-width">
    <div class="box content">
        <h2>Add Patient Diagnosis</h2>

        <?php
        $form = $this->beginWidget('CActiveForm', array(
            'id' => 'add-patient-diagnosis-form',
            'enableAjaxValidation' => false,
            'action' => array('patient/adddiagnosis'),
            'method' => 'post',
            'htmlOptions' => array(
                'class' => 'form',
                'enctype' => 'multipart/form-data',
            ),
        ));
        ?>

        <fieldset class="data-group">
            <legend><strong>Add Patient Diagnosis</strong></legend>

            <!-- Patient ID field -->
            <div class="row data-row">
                <label for="patient_id" class="required">Patient ID:</label>
                <input type="text" id="patient_id" name="patient_id" class="field" value="" />
            </div>

            <!-- Diagnosis Selection -->
            <div class="row data-row">
                <label for="disorder_select" class="required">Disorder:</label>
                <select id="disorder_select" name="disorder_id" class="field">
                    <option value="">-- Select --</option>
                    <?php 
                    $ophDisorders = Disorder::model()->findAll(array('order' => 'term ASC'));
                    foreach ($ophDisorders as $disorder) {
                        echo '<option value="' . $disorder->id . '">' . CHtml::encode($disorder->term) . '</option>';
                    }
                    ?>
                </select>
            </div>

            <!-- Eye field -->
            <div class="row data-row">
                <label class="required">Eye:</label>
                <?php foreach (Eye::model()->findAll(array('order' => 'display_order')) as $i => $eye) {?>
                    <label class="inline">
                        <input type="radio" name="diagnosis_eye" class="diagnosis_eye" value="<?= $eye->id?>"<?php if ($i == 0) {
                            ?> checked="checked"<?php
                                                  }?> /> <?= $eye->name?>
                    </label>
                <?php }?>
            </div>

            <!-- Date diagnosed -->
            <div class="row data-row">
                <label>Date Diagnosed (optional):</label>
                <select name="fuzzy_day" class="field">
                    <option value="">Day</option>
                    <?php for ($i = 1; $i <= 31; $i++) { ?>
                        <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>"><?php echo $i; ?></option>
                    <?php } ?>
                </select>
                <select name="fuzzy_month" class="field">
                    <option value="">Month</option>
                    <?php 
                    $months = array('January', 'February', 'March', 'April', 'May', 'June', 
                                  'July', 'August', 'September', 'October', 'November', 'December');
                    foreach ($months as $idx => $month) { ?>
                        <option value="<?php echo str_pad($idx + 1, 2, '0', STR_PAD_LEFT); ?>"><?php echo $month; ?></option>
                    <?php } ?>
                </select>
                <select name="fuzzy_year" class="field">
                    <option value="">Year</option>
                    <?php for ($i = date('Y'); $i >= 1900; $i--) { ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                    <?php } ?>
                </select>
            </div>

            <div class="buttons">
                <button type="submit" class="secondary small">
                    Save
                </button>
                <a href="<?= $this->createUrl('patient/search') ?>" class="button warning small">
                    Cancel
                </a>
            </div>

        </fieldset>
        <?php $this->endWidget()?>
    </div>
</section>

<style>
.row.data-row {
    margin-bottom: 15px;
}

.row.data-row label {
    display: inline-block;
    width: 200px;
    vertical-align: top;
}

.row.data-row .field {
    display: inline-block;
    width: calc(100% - 220px);
}

.row.data-row label.inline {
    display: inline;
    width: auto;
    margin-right: 15px;
}

.row.data-row label.required::after {
    content: ' *';
    color: red;
}

.buttons {
    margin-top: 20px;
    text-align: left;
}

.buttons button,
.buttons a {
    margin-right: 10px;
}
</style>
