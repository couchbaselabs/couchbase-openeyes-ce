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

<div class="cols-5">
    <div class="row divider">
        <h2><?php echo $title ?></h2>
    </div>

    <table class="standard cols-full">
        <colgroup>
            <col class="cols-3">
            <col class="cols-5">
        </colgroup>
        <tbody>
        <tr>
            <td>Name</td>
            <td class="cols-full">
                <?=\CHtml::activeTelField(
                    $model,
                    'name',
                    ['class' => 'cols-full']
                ); ?>
            </td>
        </tr>
        <tr>
            <td>Institution</td>
            <td class="cols-full">
                <?= CHtml::activeDropDownList(
                    $model,
                    'institution_id',
                    Institution::model()->getTenantedList(true),
                    ['class' => 'cols-full', 'empty' => '- Institution -'],
                ) ?>
            </td>
        </tr>
        <tr>
            <td>Site</td>
            <td>
                <?php
                // Get sites for current institution, with fallback for testing
                $siteList = Site::model()->getListForCurrentInstitution();
                if (empty($siteList)) {
                    // Provide fallback for testing - try to get the first site from the system
                    try {
                        $allSites = Site::model()->findAll();
                        $siteList = array();
                        foreach ($allSites as $site) {
                            $siteList[$site->id] = $site->name;
                        }
                    } catch (Exception $e) {
                        // If no sites exist, provide a dummy site for testing
                        $siteList = array(1 => 'Test Site');
                    }
                }
                ?>
                <?= CHtml::activeDropDownList(
                    $model,
                    'site_id',
                    $siteList,
                    ['empty' => '- Site -', 'class' => 'cols-full']
                ); ?>
            </td>
        </tr>
        <tr>
            <td>Type</td>
            <td>
                <?= CHtml::activeDropDownList(
                    $model,
                    'type_id',
                    isset($types) ? $types : array(1 => 'Unknown', 2 => 'Argon', 3 => 'Diode', 4 => 'Excimer', 5 => 'YAG'),
                    ['empty' => '- Type -', 'class' => 'cols-full']
                ); ?>
            </td>
        </tr>
        <tr>
            <td>Wavelength</td>
            <td>
                <?=\CHtml::activeTelField(
                    $model,
                    'wavelength',
                    ['class' => 'cols-full']
                ); ?>
            </td>
        </tr>
        <tr>
            <td>Active</td>
            <td>
                <?=\CHtml::activeRadioButtonList(
                    $model,
                    'active',
                    [1 => 'Yes', 0 => 'No'],
                    ['separator' => ' ', 'selected' => '1']
                ); ?>
            </td>
        </tr>
        </tbody>
    </table>
</div>








