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

/** @var MedicationSet $medication_set */
/** @var CActiveForm $form */

$sites = CHtml::listData(Site::model()->findAll("deleted_date IS NULL"), "id", "name");
$subspecialties = CHtml::listData(Subspecialty::model()->findAll("deleted_date IS NULL"), "id", "name");
$usageCodes = CHtml::listData(MedicationUsageCode::model()->findAll("deleted_date IS NULL"), "id", "usage_code");

$medicationSetRules = $medication_set->medicationSetRules;
?>
<script id="rule_row_template" type="x-tmpl-mustache">
    <tr data-key="{{ key }}">
        <td>
            <input type="hidden" name="MedicationSet[medicationSetRules][id][]" value="-1" />
            <?php echo CHtml::dropDownList(
                'MedicationSet[medicationSetRules][site_id][]',
                '{{ site_id }}',
                $sites,
                array('empty' => '-- None --', 'class' => 'cols-full')
            ) ?>
        </td>
        <td>
            <?php echo CHtml::dropDownList(
                'MedicationSet[medicationSetRules][subspecialty_id][]',
                '{{ subspecialty_id }}',
                $subspecialties,
                array('empty' => '-- None --', 'class' => 'cols-full')
            ) ?>
        </td>
        <td>
            <?php echo CHtml::dropDownList(
                'MedicationSet[medicationSetRules][usage_code_id][]',
                '{{ usage_code_id }}',
                $usageCodes,
                array('empty' => '-- None --', 'class' => 'cols-full')
            ) ?>
        </td>
        <td>
            <a href="javascript:void(0);" class="js-delete-rule"><i class="oe-i trash"></i></a>
        </td>
    </tr>
</script>
<script type="text/javascript">
    $(function () {
        $(document).on("click", ".js-delete-rule", function (e) {
            $(e.target).closest("tr").remove();
        });
    });
</script>
<h3>Medication set rules</h3>
<table class="standard" id="medication_set_rule_tbl">
    <thead>
    <tr>
        <th width="30%">Site</th>
        <th width="30%">Subspecialty</th>
        <th width="30%">Usage Code</th>
        <th width="10%">Action</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($medicationSetRules as $rowkey => $rule) : ?>
        <?php
        $id = is_null($rule->id) ? -1 : $rule->id;
        ?>
        <tr data-key="<?= $rowkey ?>">
            <td>
                <input type="hidden" name="MedicationSet[medicationSetRules][id][]" value="<?= $id ?>"/>
                <?php echo CHtml::dropDownList(
                    'MedicationSet[medicationSetRules][site_id][]',
                    $rule->site_id,
                    $sites,
                    array('empty' => '-- None --', 'class' => 'cols-full')
                ) ?>
            </td>
            <td>
                <?php echo CHtml::dropDownList(
                    'MedicationSet[medicationSetRules][subspecialty_id][]',
                    $rule->subspecialty_id,
                    $subspecialties,
                    array('empty' => '-- None --', 'class' => 'cols-full')
                ) ?>
            </td>
            <td>
                <?php echo CHtml::dropDownList(
                    'MedicationSet[medicationSetRules][usage_code_id][]',
                    $rule->usage_code_id,
                    $usageCodes,
                    array('empty' => '-- None --', 'class' => 'cols-full')
                ) ?>
            </td>
            <td>
                <a href="javascript:void(0);" class="js-delete-rule"><i class="oe-i trash"></i></a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot class="pagination-container">
    <tr>
        <td colspan="4">
            <div class="flex-layout flex-right">
                <button class="button hint green js-add-rule" type="button"><i class="oe-i plus pro-theme"></i></button>
                <script type="text/javascript">
                    new OpenEyes.UI.AdderDialog({
                        openButton: $('.js-add-rule'),
                        itemSets: [
                            new OpenEyes.UI.AdderDialog.ItemSet(<?= CJSON::encode(CHtml::listData(Site::model()->findAll("deleted_date IS NULL"), "id", array('name'))) ?>, {
                                'id': 'site_id',
                                'multiSelect': false,
                                header: "Site"
                            }),
                            new OpenEyes.UI.AdderDialog.ItemSet(<?= CJSON::encode(CHtml::listData(Subspecialty::model()->findAll("deleted_date IS NULL"), "id", array('name'))) ?>, {
                                'id': 'subspecialty_id',
                                'multiSelect': false,
                                header: "Subspecialty"
                            }),
                            new OpenEyes.UI.AdderDialog.ItemSet(<?= CJSON::encode(CHtml::listData(MedicationUsageCode::model()->findAll("deleted_date IS NULL"), "id", array('usage_code'))) ?>, {
                                'id': 'usage_code_id',
                                'multiSelect': false,
                                header: "Usage Code"
                            })
                        ],
                        onReturn: function (adderDialog, selectedItems) {
                            var selObj = {};

                            $.each(selectedItems, function (i, e) {
                                selObj[e.itemSet.options.id] = {
                                    id: e.id,
                                    label: e.label
                                };
                            });

                            var lastkey = $("#medication_set_rule_tbl > tbody > tr:last").attr("data-key");
                            if (isNaN(lastkey)) {
                                lastkey = 0;
                            }
                            var key = parseInt(lastkey) + 1;
                            var template = $('#rule_row_template').html();
                            Mustache.parse(template);

                            selObj.key = key;

                            var rendered = Mustache.render(template, selObj);

                            $("#medication_set_rule_tbl > tbody").append(rendered);
                            return true;
                        },
                        enableCustomSearchEntries: false,
                    });
                </script>
            </div>
        </td>
    </tr>
    </tfoot>
</table>
