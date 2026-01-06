<?php
/**
 * (C) Apperta Foundation, 2022
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2022, Apperta Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */
?>

<div class="cols-7">

    <div class="row divider">
        <h2><?php echo $patient_identifier_type->id ? 'Edit' : 'Add' ?> Patient Identifier Type</h2>
    </div>

    <?php echo $this->renderPartial('_form_errors', array('errors' => $errors)) ?>
    <?php
    $form = $this->beginWidget(
        'BaseEventTypeCActiveForm',
        [
            'id' => 'adminform',
            'enableAjaxValidation' => false,
            'layoutColumns' => array(
                'label' => 2,
                'field' => 5,
            ),
        ]
    ) ?>

    <table class="standard cols-full">
        <colgroup>
            <col class="cols-3">
            <col class="cols-5">
        </colgroup>

        <tbody>
        <tr>
            <td><?php echo $patient_identifier_type->getAttributeLabel('short_title'); ?></td>
            <td>
                <?= \CHtml::activeTextField(
                    $patient_identifier_type,
                    'short_title',
                    [
                        'class' => 'cols-full',
                        'autocomplete' => SettingMetadata::model()->getSetting('html_autocomplete')
                    ]
                ); ?>
            </td>
        </tr>
        <tr>
            <td><?php echo $patient_identifier_type->getAttributeLabel('long_title'); ?></td>
            <td>
                <?= \CHtml::activeTextField(
                    $patient_identifier_type,
                    'long_title',
                    [
                        'class' => 'cols-full',
                        'autocomplete' => SettingMetadata::model()->getSetting('html_autocomplete')
                    ]
                ); ?>
            </td>
        </tr>
        <tr>
            <td><?php echo $patient_identifier_type->getAttributeLabel('usage_type'); ?></td>
            <td>
                <?= \CHtml::activeDropDownList(
                    $patient_identifier_type,
                    'usage_type',
                    [
                        'GLOBAL' => 'GLOBAL',
                        'LOCAL' => 'LOCAL',
                    ],
                    ['class' => 'cols-full']
                ); ?>
            </td>
        </tr>
        <tr>
            <td><?php echo $patient_identifier_type->getAttributeLabel('institution_id'); ?></td>
            <td>
                <?= \CHtml::activeDropDownList(
                    $patient_identifier_type,
                    'institution_id',
                    CHtml::listData(Institution::model()->findAll(), 'id', 'name'),
                    ['class' => 'cols-full', 'prompt' => 'Select an institution']
                ); ?>
            </td>
        </tr>
        <tr>
            <td><?php echo $patient_identifier_type->getAttributeLabel('site_id'); ?></td>
            <td>
                <?= \CHtml::activeDropDownList(
                    $patient_identifier_type,
                    'site_id',
                    CHtml::listData(Site::model()->findAll(), 'id', 'name'),
                    ['class' => 'cols-full', 'prompt' => 'Select a site (optional)']
                ); ?>
            </td>
        </tr>
        <tr>
            <td><?php echo $patient_identifier_type->getAttributeLabel('validate_regex'); ?></td>
            <td>
                <?= \CHtml::activeTextField(
                    $patient_identifier_type,
                    'validate_regex',
                    [
                        'class' => 'cols-full',
                        'autocomplete' => SettingMetadata::model()->getSetting('html_autocomplete')
                    ]
                ); ?>
            </td>
        </tr>
        <tr>
            <td><?php echo $patient_identifier_type->getAttributeLabel('value_display_prefix'); ?></td>
            <td>
                <?= \CHtml::activeTextField(
                    $patient_identifier_type,
                    'value_display_prefix',
                    [
                        'class' => 'cols-full',
                        'autocomplete' => SettingMetadata::model()->getSetting('html_autocomplete')
                    ]
                ); ?>
            </td>
        </tr>
        <tr>
            <td><?php echo $patient_identifier_type->getAttributeLabel('value_display_suffix'); ?></td>
            <td>
                <?= \CHtml::activeTextField(
                    $patient_identifier_type,
                    'value_display_suffix',
                    [
                        'class' => 'cols-full',
                        'autocomplete' => SettingMetadata::model()->getSetting('html_autocomplete')
                    ]
                ); ?>
            </td>
        </tr>
        <tr>
            <td><?php echo $patient_identifier_type->getAttributeLabel('pad'); ?></td>
            <td>
                <?= \CHtml::activeTextField(
                    $patient_identifier_type,
                    'pad',
                    [
                        'class' => 'cols-full',
                        'autocomplete' => SettingMetadata::model()->getSetting('html_autocomplete')
                    ]
                ); ?>
            </td>
        </tr>
        <tr>
            <td><?php echo $patient_identifier_type->getAttributeLabel('spacing_rule'); ?></td>
            <td>
                <?= \CHtml::activeTextField(
                    $patient_identifier_type,
                    'spacing_rule',
                    [
                        'class' => 'cols-full',
                        'autocomplete' => SettingMetadata::model()->getSetting('html_autocomplete')
                    ]
                ); ?>
            </td>
        </tr>
        <tr>
            <td><?php echo $patient_identifier_type->getAttributeLabel('validation_example'); ?></td>
            <td>
                <?= \CHtml::activeTextField(
                    $patient_identifier_type,
                    'validation_example',
                    [
                        'class' => 'cols-full',
                        'autocomplete' => SettingMetadata::model()->getSetting('html_autocomplete')
                    ]
                ); ?>
            </td>
        </tr>
        </tbody>

        <tfoot>
        <tr>
            <td colspan="2">
                <?= \CHtml::submitButton(
                    'Save',
                    [
                        'class' => 'button large',
                        'name' => 'save',
                        'id' => 'et_save'
                    ]
                ); ?>
                <?= \CHtml::submitButton(
                    'Cancel',
                    [
                        'class' => 'button large',
                        'data-uri' => '/admin/patientIdentifierType',
                        'name' => 'cancel',
                        'id' => 'et_cancel'
                    ]
                ); ?>
            </td>
        </tr>
        </tfoot>
    </table>
</div>

<?php $this->endWidget() ?>
