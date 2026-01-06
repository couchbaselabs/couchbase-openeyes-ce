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

<div class="box content">
    <div class="header">
        <h1>Internal Referral Document List</h1>
    </div>

    <div class="content-main">
        <div class="alert alert-info">
            <p>Total referral documents: <strong><?php echo $total_count; ?></strong></p>
        </div>

        <?php if ($total_count > 0): ?>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>File Name</th>
                        <th>File Type</th>
                        <th>File Size</th>
                        <th>Created Date</th>
                        <th>Last Modified</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($referrals as $referral): ?>
                        <tr>
                            <td><?php echo CHtml::encode($referral->patient_id); ?></td>
                            <td><?php echo CHtml::encode($referral->file_name); ?></td>
                            <td><?php echo CHtml::encode($referral->file_type); ?></td>
                            <td><?php echo CHtml::encode($referral->file_size); ?> bytes</td>
                            <td><?php echo CHtml::encode($referral->created_date); ?></td>
                            <td><?php echo CHtml::encode($referral->last_modified_date); ?></td>
                            <td>
                                <a href="#" class="btn btn-sm btn-info">View</a>
                                <a href="#" class="btn btn-sm btn-danger">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-warning">
                <p>No internal referral documents found.</p>
                <p>
                    <?php echo CHtml::link('Add a new allergy', array('patient/addAllergy'), array('class' => 'btn btn-primary')); ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="form-actions">
            <a href="<?php echo Yii::app()->createUrl('site/index'); ?>" class="btn btn-default">Back</a>
        </div>
    </div>
</div>