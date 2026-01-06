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

?>
<div class="hotlist-items">
    <h2>My Hotlist</h2>
    
    <div class="hotlist-section">
        <h3>Open Items</h3>
        <?php if (!empty($open_items)): ?>
            <ul class="hotlist-list">
                <?php foreach ($open_items as $item): ?>
                    <li class="hotlist-item">
                        <strong>Patient ID:</strong> <?php echo CHtml::encode($item->patient_id); ?><br/>
                        <strong>Comment:</strong> <?php echo CHtml::encode($item->user_comment); ?><br/>
                        <strong>Last Modified:</strong> <?php echo CHtml::encode($item->last_modified_date); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No open hotlist items.</p>
        <?php endif; ?>
    </div>
    
    <div class="hotlist-section">
        <h3>Closed Items</h3>
        <?php if (!empty($closed_items)): ?>
            <ul class="hotlist-list">
                <?php foreach ($closed_items as $item): ?>
                    <li class="hotlist-item">
                        <strong>Patient ID:</strong> <?php echo CHtml::encode($item->patient_id); ?><br/>
                        <strong>Comment:</strong> <?php echo CHtml::encode($item->user_comment); ?><br/>
                        <strong>Last Modified:</strong> <?php echo CHtml::encode($item->last_modified_date); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No closed hotlist items.</p>
        <?php endif; ?>
    </div>
</div>
