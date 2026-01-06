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

/**
 * @var $unavailable_reasons OphTrOperationbooking_Operation_Session_UnavailableReason[]
 * @var $errors string[]
 * @var $success_message string|null
 */
?>

<div class="row divider">
    <h2>Add Session Unavailable Reasons to Current Institution</h2>
</div>

<?php if (!empty($errors)) : ?>
    <div class="row divider">
        <div class="alert-box issue">
            <ul>
                <?php foreach ($errors as $error) : ?>
                    <li><?= CHtml::encode(is_array($error) ? json_encode($error) : $error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($success_message)) : ?>
    <div class="row divider">
        <div class="alert-box success">
            <?= CHtml::encode($success_message) ?>
        </div>
    </div>
<?php endif; ?>

<div class="row divider cols-9">
    <form id="add_institution_mapping_form" method="post" action="/OphTrOperationbooking/admin/addInstitutionMapping">
        <input type="hidden" name="YII_CSRF_TOKEN" value="<?= Yii::app()->request->csrfToken ?>"/>
        
        <fieldset>
            <legend>Select Session Unavailable Reasons to Add</legend>
            
            <?php if (empty($unavailable_reasons)) : ?>
                <p>No session unavailable reasons available.</p>
            <?php else : ?>
                <table class="standard">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectall" onchange="toggleAll(this)"/></th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Display Order</th>
                            <th>Enabled</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unavailable_reasons as $reason) : ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="select[]" value="<?= CHtml::encode($reason->id) ?>" 
                                           class="unavailable-reason-checkbox"/>
                                </td>
                                <td><?= CHtml::encode($reason->id) ?></td>
                                <td><?= CHtml::encode($reason->name) ?></td>
                                <td><?= CHtml::encode($reason->display_order) ?></td>
                                <td><?= $reason->enabled ? 'Yes' : 'No' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </fieldset>
        
        <div class="row divider">
            <button type="submit" class="button blue large">Add Selected to Institution</button>
            <a href="/OphTrOperationbooking/admin/viewSessionUnavailableReasons" class="button secondary large">Cancel</a>
        </div>
    </form>
</div>

<script>
function toggleAll(checkbox) {
    const checkboxes = document.querySelectorAll('.unavailable-reason-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}
</script>
