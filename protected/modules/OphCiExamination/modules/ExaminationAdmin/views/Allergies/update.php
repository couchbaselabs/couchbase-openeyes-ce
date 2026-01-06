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
 * @copyright Copyright (C) 2019, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

?>
<div class="row divider cols-5">
    <h2>Edit Allergy</h2>
</div>
<?php $this->renderPartial('//base/_messages') ?>
<div class="cols-6">
    <form id="admin_Allergies_update" method="post" action="/OphCiExamination/admin/Allergies/update">
        <input type="hidden" name="YII_CSRF_TOKEN" value="<?= Yii::app()->request->csrfToken ?>"/>
        <input type="hidden" name="page" value="1">
        <table class="standard generic-admin">
            <thead>
                <tr>
                    <th>Field</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Name</td>
                    <td>
                        <?= CHtml::textField("OphCiExamination_Allergy[0][name]", $model->name); ?>
                        <?= CHtml::hiddenField("OphCiExamination_Allergy[0][id]", $model->id); ?>
                    </td>
                </tr>
                <tr>
                    <td>Allergic to Medication Set</td>
                    <td>
                        <?= CHtml::dropDownList("OphCiExamination_Allergy[0][medication_set_id]", $model->medication_set_id, CHtml::listData($medication_set_list_options, 'id', 'name'), ["empty" => "- Please Select -"]); ?>
                    </td>
                </tr>
                <tr>
                    <td>Active</td>
                    <td>
                        <?= CHtml::checkBox("OphCiExamination_Allergy[0][active]", $model->active); ?>
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">
                        <?= \CHtml::button(
                            'Back',
                            [
                                'class' => 'button large',
                                'type' => 'button',
                                'onclick' => "window.location.href='/OphCiExamination/admin/Allergies/index';",
                            ]
                        ); ?>
                        <?= \CHtml::button(
                            'Save',
                            [
                                'class' => 'button large',
                                'type' => 'submit',
                                'name' => 'save',
                                'id' => 'et_save'
                            ]
                        ); ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </form>
</div>
