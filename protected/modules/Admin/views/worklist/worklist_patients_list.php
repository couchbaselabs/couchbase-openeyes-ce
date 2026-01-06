<?php
/**
 * OpenEyes.
 *
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

<div class="admin box">
    <h2>Worklist Patients</h2>
    <?php if (!empty($worklists)) { ?>
        <table class="generic-admin standard">
            <thead>
            <tr>
                <th>Name</th>
                <th>Date Range</th>
                <th>Number of Patients</th>
                <th>Type</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($worklists as $worklist) { ?>
                <tr>
                    <td><?= CHtml::encode($worklist->name) ?></td>
                    <td><?= (!empty($worklist->start) ? $worklist->start : 'N/A') . ' - ' . (!empty($worklist->end) ? $worklist->end : 'N/A') ?></td>
                    <td><?= count($worklist->worklist_patients) ?></td>
                    <td><?= $worklist->worklist_definition ? 'Definition-based' : 'Manual' ?></td>
                    <td>
                        <?php if ($worklist->worklist_definition) { ?>
                            <a href="/Admin/worklist/worklistPatients/<?= $worklist->id ?>">View Patients</a>
                        <?php } else { ?>
                            <span class="disabled">N/A (Manual)</span>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    <?php } else { ?>
        <div class="alert-box info">No worklists have been created yet. <a href="/worklist/manualAdd">Create a manual worklist</a> or <a href="/Admin/worklist/definitions">create a worklist definition</a>.</div>
    <?php } ?>
</div>
