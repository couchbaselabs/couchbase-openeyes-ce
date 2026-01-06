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

<?php $this->renderPartial('//base/_messages') ?>

<div class="cols-5">
    <div class="sortable">
        <ul class="sortable-list">
            <?php foreach ($model_list as $model) { ?>
                <li class="sortable-item" data-attr-id="<?php echo $model->id ?>">
                    <div class="sortable-item-content">
                        <span class="sortable-handle">⋮⋮</span>
                        <span class="sortable-text"><?php echo $model->name ?></span>
                        <?php if ($model->active) { ?>
                            <i class="oe-i tick small"></i>
                        <?php } else { ?>
                            <i class="oe-i remove small"></i>
                        <?php } ?>
                    </div>
                </li>
            <?php } ?>
        </ul>
    </div>
    <div style="margin-top: 20px;">
        <a href="<?php echo $this->createUrl('viewTreatmentDrugs') ?>" class="button secondary">
            Back to List
        </a>
    </div>
</div>

<style>
.sortable {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sortable ul.sortable-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sortable li.sortable-item {
    background: #f5f5f5;
    border: 1px solid #e0e0e0;
    border-radius: 3px;
    padding: 10px;
    margin-bottom: 10px;
    cursor: move;
    user-select: none;
}

.sortable li.sortable-item:hover {
    background: #ebebeb;
    border-color: #d0d0d0;
}

.sortable li.sortable-item.ui-sortable-helper {
    opacity: 0.8;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}

.sortable-item-content {
    display: flex;
    align-items: center;
    gap: 10px;
}

.sortable-handle {
    font-weight: bold;
    color: #999;
    cursor: grab;
}

.sortable-text {
    flex: 1;
}
</style>
