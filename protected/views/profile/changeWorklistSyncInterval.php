<?php
/**
 * OpenEyes.
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2012
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2011-2012, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */
?>

<section class="box profile-section">
	<header class="box-header">
		<h2>Worklist Sync Intervals</h2>
	</header>
	<section class="box-content">
		<?php 
		// Get worklist sync settings for the user
		$syncSettings = SettingUser::model()->findAll('user_id = ? AND `key` LIKE ?', array($user->id, 'worklist_sync%'));
		?>
		
		<?php if (!empty($syncSettings)): ?>
			<table class="standard">
				<thead>
					<tr>
						<th>Setting</th>
						<th>Sync Interval (seconds)</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($syncSettings as $setting): ?>
						<tr>
							<td><?php echo CHtml::encode($setting->key); ?></td>
							<td><?php echo CHtml::encode($setting->value); ?></td>
							<td>
								<a href="#" class="edit-sync-setting" data-setting-id="<?php echo $setting->id; ?>" data-key="<?php echo CHtml::encode($setting->key); ?>" data-value="<?php echo CHtml::encode($setting->value); ?>">Edit</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else: ?>
			<p>No worklist sync interval settings configured.</p>
		<?php endif; ?>
	</section>
</section>

<div id="sync-interval-modal" class="modal" style="display: none;">
	<div class="modal-content">
		<h3>Edit Sync Interval</h3>
		<form id="sync-interval-form">
			<input type="hidden" id="sync-key" name="key" />
			<div class="form-group">
				<label for="sync-value">Sync Interval (seconds):</label>
				<input type="number" id="sync-value" name="sync_interval" min="1" required />
			</div>
			<div class="modal-buttons">
				<button type="submit" class="button small">Save</button>
				<button type="button" class="button small cancel">Cancel</button>
			</div>
		</form>
	</div>
</div>

<script>
$(document).ready(function() {
	$('.edit-sync-setting').click(function(e) {
		e.preventDefault();
		$('#sync-key').val($(this).data('key'));
		$('#sync-value').val($(this).data('value'));
		$('#sync-interval-modal').show();
	});

	$('#sync-interval-form').submit(function(e) {
		e.preventDefault();
		
		var key = $('#sync-key').val();
		var sync_interval = $('#sync-value').val();
		
		$.ajax({
			url: '<?php echo Yii::app()->createUrl('/profile/changeWorklistSyncInterval'); ?>',
			type: 'POST',
			data: {
				key: key,
				sync_interval: sync_interval
			},
			dataType: 'json',
			success: function(response) {
				if (response.status === 'success') {
					alert('Sync interval updated successfully');
					location.reload();
				} else {
					alert('Error: ' + response.message);
				}
			},
			error: function() {
				alert('An error occurred while updating the sync interval');
			}
		});
	});

	$('.modal-buttons .cancel').click(function() {
		$('#sync-interval-modal').hide();
	});
});
</script>

<style>
.modal {
	position: fixed;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background-color: rgba(0, 0, 0, 0.5);
	display: none;
	z-index: 1000;
}

.modal-content {
	background-color: white;
	margin: 15% auto;
	padding: 20px;
	border: 1px solid #888;
	width: 300px;
}

.modal-buttons {
	margin-top: 20px;
	text-align: right;
}

.modal-buttons button {
	margin-left: 10px;
}
</style>
