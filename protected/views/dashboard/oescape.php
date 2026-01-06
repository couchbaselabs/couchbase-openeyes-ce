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

<div class="oescape-dashboard">
    <!-- Patient Info -->
    <div class="patient-info">
        <h2>OEscape - Patient <?php echo htmlspecialchars($this->patient->id); ?></h2>
        <p><?php echo htmlspecialchars($this->patient->getFullName()); ?></p>
    </div>

    <!-- IOP Chart -->
    <div class="chart-container">
        <div id="iopchart" style="height: 400px;"></div>
    </div>

    <!-- VA/MD Chart -->
    <div class="chart-container">
        <div id="vachart" style="height: 400px;"></div>
    </div>

    <!-- Visual Field Images -->
    <div class="images-container">
        <h3>Visual Fields</h3>
        <div class="vf-images">
            <div>
                <h4>Left Eye</h4>
                <div id="vfgreyscale_left" class="vf-image-display"></div>
            </div>
            <div>
                <h4>Right Eye</h4>
                <div id="vfgreyscale_right" class="vf-image-display"></div>
            </div>
        </div>
    </div>

    <!-- Visual Field Color Plot -->
    <div class="colorplot-container">
        <h3>Visual Field Color Plot</h3>
        <div class="colorplot-images">
            <div>
                <h4>Left Eye</h4>
                <div id="vfcolorplot_left" class="colorplot-display"></div>
            </div>
            <div>
                <h4>Right Eye</h4>
                <div id="vfcolorplot_right" class="colorplot-display"></div>
            </div>
        </div>
    </div>

    <!-- Regression Chart -->
    <div class="chart-container">
        <div id="regression_chart" style="height: 400px; display: none;"></div>
    </div>

    <!-- OCT Images -->
    <div class="images-container">
        <h3>OCT Images</h3>
        <div id="oct_images" class="oct-image-display"></div>
    </div>

    <!-- Hidden caches for image management -->
    <div style="display: none;">
        <div id="vfgreyscale_left_cache"></div>
        <div id="vfgreyscale_right_cache"></div>
        <div id="oct_images_cache"></div>
    </div>

    <!-- Kowa image containers -->
    <div style="display: none;">
        <div id="kowa_left"></div>
        <div id="kowa_right"></div>
    </div>
</div>

<style>
.oescape-dashboard {
    padding: 20px;
}

.patient-info {
    margin-bottom: 20px;
    padding: 10px;
    background-color: #f5f5f5;
    border-radius: 4px;
}

.chart-container {
    margin: 20px 0;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: #fff;
}

.images-container {
    margin: 20px 0;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: #fff;
}

.vf-images {
    display: flex;
    gap: 20px;
    margin-top: 10px;
}

.vf-images > div {
    flex: 1;
}

.vf-image-display {
    height: 300px;
    border: 1px solid #eee;
    overflow: auto;
}

.colorplot-container {
    margin: 20px 0;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: #fff;
}

.colorplot-images {
    display: flex;
    gap: 20px;
    margin-top: 10px;
}

.colorplot-images > div {
    flex: 1;
}

.colorplot-display {
    height: 300px;
    border: 1px solid #eee;
    overflow: auto;
}

.oct-image-display {
    height: 400px;
    border: 1px solid #eee;
    margin-top: 10px;
    overflow: auto;
}
</style>
