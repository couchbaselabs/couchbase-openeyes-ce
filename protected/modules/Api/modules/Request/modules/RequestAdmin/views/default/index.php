<?php
/**
 * (C) Copyright Apperta Foundation 2020
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (C) 2017, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */
?>

<h1>API Request Administration</h1>
<p>Welcome to the API Request Administration interface.</p>

<div class="admin-menu">
    <ul>
        <li><?php echo CHtml::link('Manage Requests', array('request/index')); ?></li>
        <li><?php echo CHtml::link('Request Types', array('requestType/index')); ?></li>
        <li><?php echo CHtml::link('MIME Types', array('mimeType/index')); ?></li>
        <li><?php echo CHtml::link('Attachment Types', array('attachmentType/index')); ?></li>
        <li><?php echo CHtml::link('Request Queues', array('requestQueue/index')); ?></li>
        <li><?php echo CHtml::link('Request Routines', array('requestRoutine/index')); ?></li>
    </ul>
</div>
