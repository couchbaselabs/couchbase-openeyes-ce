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

<div class="box admin">
    <h2>DNA Extraction Search</h2>

    <table class="standard">
        <?php $form=$this->beginWidget('BaseEventTypeCActiveForm', array(
            'action' => Yii::app()->createUrl('/OphInDnaextraction/search/DnaExtractions'),
            'method' => 'GET',
            'id' => 'generic-search-form',
            'focus' => '#search',
            'enableAjaxValidation' => false,
        )); ?>
        <input type="hidden" value="search" name="search">
        <tr class="standard">
            <td>
                <?=\CHtml::textField('extraction_id', @$_GET['extraction_id'], array('placeholder' => 'Extraction Id'))?>
            </td>
            <td>
                <?=\CHtml::textField('hos_num', @$_GET['hos_num'], array('placeholder' => 'Hospital Number'))?>
            </td>
            <td>
                <?=\CHtml::textField('first_name', @$_GET['first_name'], array('placeholder' => 'First Name'))?>
            </td>
            <td>
                <?=\CHtml::textField('last_name', @$_GET['last_name'], array('placeholder' => 'Last Name'))?>
            </td>
        </tr>
        <tr>
            <td>
                <?php $this->widget('zii.widgets.jui.CJuiDatePicker', array(
                    'name' => 'date-from',
                    'id' => 'date-from',
                    'options' => array(
                        'showAnim' => 'fold',
                        'dateFormat' => Helper::NHS_DATE_FORMAT_JS,
                    ),
                    'htmlOptions'=>array(
                        'placeholder' => 'Extracted Date From'
                    ),
                    'value' => @$_GET['date-from'],
                ))?>
            </td>
            <td>
                <?php $this->widget('zii.widgets.jui.CJuiDatePicker', array(
                    'name' => 'date-to',
                    'id' => 'date-to',
                    'options' => array(
                        'showAnim' => 'fold',
                        'dateFormat' => Helper::NHS_DATE_FORMAT_JS,
                    ),
                    'htmlOptions'=>array(
                        'placeholder' => 'Extracted Date To'
                    ),
                    'value' => @$_GET['date-to'],
                ))?>
            </td>
            <td>
                <?=\CHtml::textField('volume', @$_GET['volume'], array('placeholder' => 'Volume'))?>
            </td>
            <td>
                <?=\CHtml::textField('comment', @$_GET['comment'], array('placeholder' => 'Comments'))?>
            </td>
            <td>
                <div>
                    <div class="submit-row text-right" style="margin-left:auto">
                        <?=\CHtml::submitButton('Search', ['class' => 'button small primary event-action blue hint', 'id' => 'search_dna_extraction', 'form' => 'generic-search-form']); ?>
                    </div>
                </div>
            </td>
        </tr>
        <?php $this->endWidget(); ?>
    </table>

    <h2>DNA Extractions</h2>

    <form id="extraction_result">
        <input type="hidden" id="select_all" value="0" />

        <?php if (count($results) <1) {?>
            <div class="alert-box no_results">
                <span class="column_no_results">
                    <?php if (@$_GET['search']) {?>
                        No results found for current criteria.
                    <?php } else {?>
                        Please enter criteria to search for extractions.
                    <?php }?>
                </span>
            </div>
        <?php }?>

        <?php if (!empty($results)) {?>
            <table class="standard">
                <thead>
                    <tr>
                        <th><?=\CHtml::link('Extraction Id', $this->getUri(array('sortby' => 'extraction_id')))?></th>
                        <th><?=\CHtml::link('Hospital No', $this->getUri(array('sortby' => 'hos_num')))?></th>
                        <th><?=\CHtml::link('Patient Name', $this->getUri(array('sortby' => 'patient_name')))?></th>
                        <th><?=\CHtml::link('Extracted Date', $this->getUri(array('sortby' => 'extracted_date')))?></th>
                        <th><?=\CHtml::link('Volume', $this->getUri(array('sortby' => 'volume')))?></th>
                        <th><?=\CHtml::link('Comment', $this->getUri(array('sortby' => 'comment')))?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php

                    foreach ($results as $result) {?>
                        <tr class="clickable" data-uri="<?php echo Yii::app()->createUrl('/OphInDnaextraction/default/view/'.$result['id'])?>">
                            <td><?php echo $result['extraction_id']?></td>
                            <td><?php echo $result['hos_num']?></td>
                            <td><?php echo strtoupper($result['last_name'])?>, <?php echo $result['first_name']?></td>
                            <td>
                                <?php
                                    if (!empty($result['extracted_date'])) {
                                        $date = new DateTime($result['extracted_date']);
                                        echo $date->format('d M Y');
                                    }
                                ?>
                            </td>
                            <td><?php echo $result['volume']?></td>
                            <td><?php echo $result['comments']?></td>
                        </tr>
                    <?php }?>
                </tbody>
                <tfoot class="pagination-container">
                <tr>
                    <td colspan="3">
                        <?php $to = ($pagination->getItemCount() < $pagination->limit) ? $pagination->getItemCount() : ($pagination->limit * ($pagination->getCurrentPage()+1)); ?>
                        Showing <?php echo $pagination->offset + 1; ?> to <?php echo $to; ?> of <?php echo $pagination->getItemCount(); ?>
                    </td>
                    <td colspan="3">
                        <?php
                        $this->widget('CLinkPager', array(
                            'currentPage' => $pagination->getCurrentPage(),
                            'itemCount' => $pagination->getItemCount(),
                            'pageSize' => $pagination->getPageSize(),
                            'maxButtonCount' => 10,
                            'header'=> '',
                            'htmlOptions'=>array('class'=>'pagination right'),
                            'selectedPageCssClass' => 'current'
                        ));
                        ?>
                    </td>
                </tr>
                </tfoot>
            </table>
        <?php }?>
    </form>
</div>
