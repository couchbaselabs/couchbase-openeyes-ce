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
 * @var $lensTypes OphInBiometry_LensType_Lens[]
 * @var $errors string[]
 */
?>

<div class="row divider">
    <h2>Add Lens Type to Current Institution</h2>
</div>

<?php if (!empty($errors)) : ?>
    <div class="row divider">
        <div class="alert-box issue">
            <ul>
                <?php foreach ($errors as $error) : ?>
                    <li><?= CHtml::encode($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="row divider cols-9">
    <form id="add_institution_mapping_form" method="post" action="/OphInBiometry/lensTypeAdmin/addInstitutionMapping">
        <input type="hidden" name="YII_CSRF_TOKEN" value="<?= Yii::app()->request->csrfToken ?>"/>
        
        <fieldset>
            <legend>Select Lens Types to Add</legend>
            
            <?php if (empty($lensTypes)) : ?>
                <p>No lens types available.</p>
            <?php else : ?>
                <table class="standard">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectall" onchange="toggleAll(this)"/></th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Display Name</th>
                            <th>Description</th>
                            <th>Position</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lensTypes as $lens) : ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="select[]" value="<?= CHtml::encode($lens->id) ?>" 
                                           class="lens-type-checkbox"/>
                                </td>
                                <td><?= CHtml::encode($lens->id) ?></td>
                                <td><?= CHtml::encode($lens->name) ?></td>
                                <td><?= CHtml::encode($lens->display_name) ?></td>
                                <td><?= CHtml::encode($lens->description) ?></td>
                                <td><?= CHtml::encode($lens->position->name ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </fieldset>
        
        <div class="row divider">
            <button type="submit" class="button blue large">Add Selected to Institution</button>
            <a href="/OphInBiometry/lensTypeAdmin/list" class="button secondary large">Cancel</a>
        </div>
    </form>
</div>

<script>
function toggleAll(checkbox) {
    const checkboxes = document.querySelectorAll('.lens-type-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}
</script>
