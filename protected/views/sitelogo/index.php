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

<h1>Site Logos</h1>

<?php if (!$logos || count($logos) == 0): ?>
    <p>No site logos found.</p>
<?php else: ?>
    <table class="fancy" id="sitelogos">
        <thead>
            <tr>
                <th>ID</th>
                <th>Primary Logo</th>
                <th>Secondary Logo</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logos as $logo): ?>
                <tr>
                    <td><?php echo CHtml::encode($logo->id); ?></td>
                    <td>
                        <?php if ($logo->primary_logo): ?>
                            <a href="<?php echo $this->createUrl('sitelogo/view', array('id' => $logo->id)); ?>" target="_blank">View Primary Logo</a>
                        <?php else: ?>
                            <span class="fade">No primary logo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($logo->secondary_logo): ?>
                            <a href="<?php echo $this->createUrl('sitelogo/view', array('id' => $logo->id, 'secondary_logo' => 1)); ?>" target="_blank">View Secondary Logo</a>
                        <?php else: ?>
                            <span class="fade">No secondary logo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?php echo $this->createUrl('sitelogo/view', array('id' => $logo->id)); ?>">Primary</a> |
                        <a href="<?php echo $this->createUrl('sitelogo/view', array('id' => $logo->id, 'secondary_logo' => 1)); ?>">Secondary</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
