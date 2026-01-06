<?php
/**
 * (C) OpenEyes Foundation, 2018
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
?>
<div class="box admin">
  <div class="cols-8 column">
    <h2>Document sub types</h2>
  </div>
  <div class="cols-4 column end">
        <?=\CHtml::htmlButton('Add sub type', array('class' => 'button large addSubType'))?>
  </div>
  <?php if (isset($document_sub_types) && !empty($document_sub_types)): ?>
    <table class="standard sortable">
      <thead>
        <tr>
          <th>Name</th>
          <th>Display Order</th>
          <th>Active</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($document_sub_types as $subType): ?>
          <tr>
            <td><?= CHtml::encode($subType->name) ?></td>
            <td><?= CHtml::encode($subType->display_order) ?></td>
            <td><?= CHtml::encode($subType->is_active ? 'Yes' : 'No') ?></td>
            <td>
              <?= CHtml::link('Edit', array('update', 'id' => $subType->id)) ?>
              | <?= CHtml::link('Delete', array('delete', 'id' => $subType->id), array('confirm' => 'Are you sure?')) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="empty-message">No document sub types configured yet.</div>
  <?php endif; ?>
</div>