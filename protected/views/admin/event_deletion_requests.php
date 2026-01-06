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
 *
 * @var $institutions array
 * @var $selected_institution int
 * @var $events Event[]
 */
?>

<div class="cols-7">
    <?php if (empty($institutions)): ?>
        <div class="alert alert-info">
            <p>No institutions available. Please check your institution access permissions.</p>
        </div>
    <?php else: ?>
    <select id="select-institution">
        <?php foreach ($institutions as $institution) {
            if ($institution['id'] === $selected_institution) {
                echo "<option value=\"{$institution['id']}\" selected>{$institution['name']}</option>";
            } else {
                echo "<option value=\"{$institution['id']}\">{$institution['name']}</option>";
            }
        } ?>
    </select>
    <?php endif; ?>

    <table class="standard">
        <thead>
            <tr>
                <th>Date/time</th>
                <th>User</th>
                <th>Event</th>
                <th>Reason</th>
                <th>Actions</th>
            </tr>
        </thead>

        <tbody>
        <?php if (empty($events)): ?>
            <tr>
                <td colspan="5" class="text-center">No events pending deletion</td>
            </tr>
        <?php else: foreach ($events as $i => $event) {?>
            <tr data-id="<?php echo $event->id?>"
                data-uri="admin/viewDeletionRequest/<?php echo $event->id?>">
                <td>
                    <?php echo $event->NHSDate('last_modified_date')?>
                    <?php echo substr($event->last_modified_date, 11, 5)?>
                </td>
                <td><?php echo $event->usermodified ? $event->usermodified->fullName : 'Unknown'?></td>
                <td>
                    <a href="<?php echo Yii::app()->createUrl('/'.$event->eventType->class_name.'/default/view/'.$event->id)?>">
                        <?php echo $event->eventType ? $event->eventType->name : 'Unknown'?>
                        <?php echo $event->id?></a>
                </td>
                <td><?php echo $event->delete_reason?></td>
                <td>
                    <form method="post" class="deletion-request-form">
                        <input type="hidden" name="event_id" value="<?php echo $event->id?>" />
                        <input type="hidden" name="YII_CSRF_TOKEN"
                               value="<?php echo Yii::app()->request->csrfToken?>" />
                        <?=\CHtml::submitButton(
                            'Approve',
                            [
                                'class' => 'button large',
                                'id' => 'et_approve_' . $event->id,
                                'name' => 'approve'
                            ]
                        );?>
                        <?=\CHtml::submitButton(
                            'Reject',
                            [
                                'class' => 'button large',
                                'id' => 'et_reject_' . $event->id,
                                'name' => 'reject'
                            ]
                        );?>
                    </form>
                </td>
            </tr>
        <?php } endif; ?>
        </tbody>
    </table>
</div>
<script type="text/javascript">
    $(document).ready(function() {
        $('#select-institution').change(function() {
            window.location.href = '/admin/eventDeletionRequests?selected_institution=' + $(this).val();
        });
    });
</script>