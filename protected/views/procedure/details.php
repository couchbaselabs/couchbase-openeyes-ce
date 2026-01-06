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
<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="alert alert-warning" role="alert">
                <h4 class="alert-heading"><?php echo isset($error) ? CHtml::encode($error) : 'Information'; ?></h4>
                <?php if (isset($instructions)): ?>
                    <p><?php echo CHtml::encode($instructions); ?></p>
                <?php endif; ?>
                
                <hr>
                <p><strong>About this endpoint:</strong></p>
                <ul>
                    <li>This endpoint is primarily designed for AJAX requests</li>
                    <li>It returns procedure details in a table row format</li>
                    <li>Required parameter: <code>name</code> - the procedure name to look up</li>
                    <li>Optional parameters: <code>durations</code>, <code>identifier</code></li>
                </ul>
                
                <p><strong>Example Usage:</strong></p>
                <code>/procedure/details?name=Retinopexia</code>
            </div>
        </div>
    </div>
</div>
