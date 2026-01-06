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

<table class="document-table" id="document-table">
    <thead>
        <tr>
            <th>Document</th>
            <th>Recipient</th>
            <th>Output</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($data)): ?>
            <?php if (is_array($data) && count($data) > 0): ?>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <td><?php echo isset($row['document']) ? $row['document'] : '-'; ?></td>
                        <td><?php echo isset($row['recipient']) ? $row['recipient'] : '-'; ?></td>
                        <td><?php echo isset($row['output']) ? $row['output'] : '-'; ?></td>
                        <td><?php echo isset($row['status']) ? $row['status'] : '-'; ?></td>
                        <td>
                            <?php if (isset($row['correspondence_mode']) && !$row['correspondence_mode']): ?>
                                <button class="small btn">Edit</button>
                                <button class="small btn">Delete</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" style="text-align: center;">No documents available</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
