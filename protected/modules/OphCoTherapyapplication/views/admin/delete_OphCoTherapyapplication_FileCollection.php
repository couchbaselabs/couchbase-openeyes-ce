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

<div class="row divider cols-7">
    <h2>Delete <?php echo $title ?></h2>
</div>

<div class="row divider">
    <div class="alert-box alert with-icon">
        <strong>WARNING: This will permanently delete the file collection and any associated files.</strong>
    </div>
</div>

<div class="cols-7">
    <table class="standard cols-full">
        <colgroup>
            <col class="cols-3">
            <col class="cols-4">
        </colgroup>
        <tbody>
            <tr>
                <td><strong><?= $model->getAttributeLabel('institution') ?></strong></td>
                <td><?= $model->institution ? $model->institution->name : 'N/A' ?></td>
            </tr>
            <tr>
                <td><strong><?= $model->getAttributeLabel('name') ?></strong></td>
                <td><?= CHtml::encode($model->name) ?></td>
            </tr>
            <tr>
                <td><strong><?= $model->getAttributeLabel('summary') ?></strong></td>
                <td><?= CHtml::encode($model->summary) ?></td>
            </tr>
            <?php if ($model->files && count($model->files) > 0): ?>
            <tr>
                <td><strong>Files</strong></td>
                <td>
                    <ul>
                    <?php foreach ($model->files as $file): ?>
                        <li><?= CHtml::encode($file->name) ?></li>
                    <?php endforeach; ?>
                    </ul>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="row divider">
    <p>Are you sure you want to delete this file collection?</p>
</div>

<?php
$form = $this->beginWidget('BaseEventTypeCActiveForm', array(
    'id' => 'deleteform',
    'method' => 'post',
    'enableAjaxValidation' => false,
)) ?>

<div class="row">
    <div class="button-group">
        <button type="submit" class="button warning" name="delete_confirm">Delete File Collection</button>
        <a href="<?php echo Yii::app()->createUrl('/OphCoTherapyapplication/admin/viewFileCollections') ?>" class="button secondary">Cancel</a>
    </div>
</div>

<input type="hidden" name="file_collections[]" value="<?php echo $model->id ?>">

<?php $this->endWidget() ?>
