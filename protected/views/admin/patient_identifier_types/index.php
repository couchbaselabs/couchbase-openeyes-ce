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
    <?php if (!$patient_identifier_types) : ?>
    <div class="row divider">
        <div class="alert-box issue"><b>No results found</b></div>
    </div>
    <?php endif; ?>

    <form id="admin_patient_identifier_types">
        <input type="hidden" name="YII_CSRF_TOKEN" value="<?php echo Yii::app()->request->csrfToken ?>"/>
        <table class="standard">
            <thead>
            <tr>
                <th>
                    <input type="checkbox" id="checkall" class="patient_identifier_type"/>
                </th>
                <th>Title</th>
                <th>Long Title</th>
                <th>Usage Type</th>
                <th>Institution</th>
                <th>Site</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($patient_identifier_types as $i => $pit) { ?>
                <tr class="clickable" data-id="<?php echo $pit->id ?>"
                    data-uri="admin/editPatientIdentifierType?patient_identifier_type_id=<?php echo $pit->id ?>">
                    <td><input type="checkbox" name="patient_identifier_types[]" value="<?php echo $pit->id ?>" class="patient_identifier_types"/></td>
                    <td><?php echo CHtml::encode($pit->short_title) ?></td>
                    <td><?php echo CHtml::encode($pit->long_title) ?></td>
                    <td><?php echo CHtml::encode($pit->usage_type) ?></td>
                    <td><?php echo $pit->institution ? CHtml::encode($pit->institution->name) : 'N/A' ?></td>
                    <td><?php echo $pit->site ? CHtml::encode($pit->site->name) : 'N/A' ?></td>
                </tr>
            <?php } ?>
            </tbody>
            <tfoot>
            <tr>
                <td colspan="6">
                    <?= \CHtml::button(
                        'Add',
                        [
                            'class' => 'button large',
                            'name' => 'add_patient_identifier_type',
                            'id' => 'et_add'
                        ]
                    ); ?>
                    <?= \CHtml::button(
                        'Delete',
                        [
                            'class' => 'button large',
                            'name' => 'delete_patient_identifier_types',
                            'data-object' => 'PatientIdentifierTypes',
                            'id' => 'et_delete'
                        ]
                    ); ?>
                </td>
            </tr>
            </tfoot>
        </table>
    </form>

    <div id="confirm_delete_patient_identifier_types"
         title="Confirm delete patient identifier type" style="display: none;">
        <div>
            <div id="delete_patient_identifier_types">
                <div class="alertBox" style="margin-top: 10px; margin-bottom: 15px;">
                    <strong>WARNING: This will remove the patient identifier types from the system.<br/>This action cannot
                        be undone.</strong>
                </div>
                <p>
                    <strong>Are you sure you want to proceed?</strong>
                </p>
                <div class="buttonwrapper" style="margin-top: 15px; margin-bottom: 5px;">
                    <input type="hidden" id="patient_identifier_type_id" value=""/>
                    <button type="submit" class="classy red venti btn_remove_patient_identifier_types"><span
                                class="button-span button-span-red">Remove patient identifier type(s)</span></button>
                    <button type="submit" class="classy green venti btn_cancel_remove_patient_identifier_types"><span
                                class="button-span button-span-green">Cancel</span></button>
                    <img class="loader" src="<?php echo Yii::app()->assetManager->createUrl('img/ajax-loader.gif') ?>"
                         alt="loading..." style="display: none;"/>
                </div>
            </div>
        </div>
    </div>

</div>
<script type="text/javascript">

    $('#et_add_patient_identifier_type').click(function (e) {
        e.preventDefault();
        window.location.href = baseUrl + '/admin/addPatientIdentifierType';
    });

    $('#checkall').click(function (e) {
        $('input[name="patient_identifier_types[]"]').attr('checked', $(this).is(':checked') ? 'checked' : false);
    });

    $('#et_delete_patient_identifier_types').click(function (e) {
        e.preventDefault();

        if ($('input[type="checkbox"][name="patient_identifier_types[]"]:checked').length < 1) {
            alert("Please select the patient identifier types you wish to delete.");
            enableButtons();
            return;
        }

        $.ajax({
            'type': 'POST',
            'url': baseUrl + '/admin/verifyDeletePatientIdentifierTypes',
            'data': $('#admin_patient_identifier_types').serialize() + "&YII_CSRF_TOKEN=" + YII_CSRF_TOKEN,
            'success': function (resp) {
                var mention = ($('input[type="checkbox"][name="patient_identifier_types[]"]:checked').length == 1) ? 'patient identifier type' : 'patient identifier types';

                if (resp == "1") {
                    enableButtons();

                    $('#confirm_delete_patient_identifier_types').attr('title', 'Confirm delete ' + mention);
                    $('#delete_patient_identifier_types').children('div').children('strong').html("WARNING: This will remove the " + mention + " from the system.<br/><br/>This action cannot be undone.");
                    $('button.btn_remove_patient_identifier_types').children('span').text('Remove ' + mention);

                    $('#confirm_delete_patient_identifier_types').dialog({
                        resizable: false,
                        modal: true,
                        width: 560
                    });
                } else {
                    alert("One or more of the selected patient identifier types are in use and so cannot be deleted.");
                    enableButtons();
                }
            }
        });
    });

    $('button.btn_cancel_remove_patient_identifier_types').click(function (e) {
        e.preventDefault();
        $('#confirm_delete_patient_identifier_types').dialog('close');
    });

    handleButton($('button.btn_remove_patient_identifier_types'), function (e) {
        e.preventDefault();

        $.ajax({
            'type': 'POST',
            'url': baseUrl + '/admin/deletePatientIdentifierTypes',
            'data': $('#admin_patient_identifier_types').serialize() + "&YII_CSRF_TOKEN=" + YII_CSRF_TOKEN,
            'success': function (resp) {
                if (resp == "1") {
                    window.location.reload();
                } else {
                    alert("There was an unexpected error deleting the patient identifier types, please try again or contact support for assistance");
                    enableButtons();
                    $('#confirm_delete_patient_identifier_types').dialog('close');
                }
            }
        });
    });
</script>
