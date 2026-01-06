<?php
/**
 * (C) Copyright Apperta Foundation 2021
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (C) 2021, Apperta Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */
?>

<?php $this->pageTitle = 'CVI Print Test'; ?>

<div class="box content">
    <div class="box-header">
        <h1>CVI Print Test</h1>
    </div>

    <div class="box-content">
        <p>Test form for CVI template printing functionality.</p>

        <?php if (!empty($pdfLink)): ?>
            <div class="alert alert-success">
                PDF Generated Successfully: <?php echo $pdfLink; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($imageSrc)): ?>
            <div class="test-image">
                <h3>Test Signature Image:</h3>
                <?php echo $imageSrc; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="patient_name">Patient Name:</label>
                <input type="text" id="patient_name" name="patient_name" value="Test Patient" />
            </div>

            <div class="form-group">
                <label for="address">Address:</label>
                <textarea id="address" name="address">123 Test Street, Test City</textarea>
            </div>

            <div class="form-group">
                <label for="postcode">Postcode:</label>
                <input type="text" id="postcode" name="postcode" value="TC1 1TC" />
            </div>

            <div class="form-group">
                <button type="submit" name="test_print" value="1" class="btn btn-primary">Generate PDF</button>
            </div>
        </form>
    </div>
</div>

<style>
    .test-image {
        margin-top: 20px;
        padding: 15px;
        border: 1px solid #ccc;
        background-color: #f9f9f9;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 5px;
        border: 1px solid #ccc;
    }

    .btn {
        padding: 8px 15px;
        cursor: pointer;
        border: none;
        border-radius: 4px;
    }

    .btn-primary {
        background-color: #007bff;
        color: white;
    }

    .btn-primary:hover {
        background-color: #0056b3;
    }

    .alert {
        padding: 12px;
        margin-bottom: 15px;
        border: 1px solid transparent;
        border-radius: 4px;
    }

    .alert-success {
        color: #155724;
        background-color: #d4edda;
        border-color: #c3e6cb;
    }
</style>
