<?php
/**
 * OpenEyes.
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2017
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2011-2017, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */
?>

<div class="box admin">
    <h1>Add Risk to Patient</h1>

    <?php
    $form = $this->beginWidget('BaseEventTypeCActiveForm', array(
        'id' => 'add-risk-form',
        'action' => $this->createUrl('addRisk'),
        'method' => 'POST',
        'enableAjaxValidation' => false,
        'layoutColumns' => array(
            'label' => 2,
            'field' => 5,
        ),
    )) ?>

    <fieldset class="rows">
        <legend>Risk Details</legend>

        <!-- Patient Selection -->
        <div class="row">
            <label for="patient_id">Patient:</label>
            <input type="text" id="patient_search" name="patient_search" placeholder="Search patient by name or ID" class="search" autocomplete="off" />
            <input type="hidden" id="patient_id" name="patient_id" value="" />
            <div id="patient_suggestions" class="suggestions-box" style="display:none;"></div>
        </div>

        <!-- Risk Selection -->
        <div class="row">
            <label for="risk_id">Risk:</label>
            <select id="risk_id" name="risk_id" class="field">
                <option value="">Loading risks...</option>
            </select>
        </div>

        <!-- Other Risk (if applicable) -->
        <div class="row" id="other-wrapper" style="display:none;">
            <label for="other">Other Risk Description:</label>
            <input type="text" id="other" name="other" class="field" placeholder="Describe the other risk" />
        </div>

        <!-- Comments -->
        <div class="row">
            <label for="comments">Comments:</label>
            <textarea id="comments" name="comments" class="field" rows="4" placeholder="Add any relevant comments"></textarea>
        </div>

        <!-- No Risks Option -->
        <div class="row">
            <label for="no_risks">Patient has no risks:</label>
            <input type="checkbox" id="no_risks" name="no_risks" value="1" />
        </div>

    </fieldset>

    <div class="actions">
        <button type="submit" class="clipped-button blue" id="et_save">Add Risk</button>
        <a href="<?php echo $this->createUrl('/patient/search'); ?>" class="clipped-button">Cancel</a>
    </div>

    <?php $this->endWidget() ?>
</div>

<script type="text/javascript">
$(document).ready(function() {
    // Show/hide "Other" field based on risk selection
    $('#risk_id').on('change', function() {
        var $selected = $(this).find('option:selected');
        if ($selected.data('other')) {
            $('#other-wrapper').show();
        } else {
            $('#other-wrapper').hide();
            $('#other').val('');
        }
    });

    // Patient search autocomplete
    var patientSearchTimeout;
    $('#patient_search').on('input', function() {
        clearTimeout(patientSearchTimeout);
        var searchTerm = $(this).val();
        
        if (searchTerm.length < 2) {
            $('#patient_suggestions').hide();
            return;
        }

        patientSearchTimeout = setTimeout(function() {
            $.ajax({
                url: '<?php echo $this->createUrl('/patient/ajaxSearch'); ?>',
                type: 'GET',
                data: { term: searchTerm },
                success: function(response) {
                    try {
                        var data = JSON.parse(response);
                        var suggestionsHtml = '';
                        
                        if (data && data.length > 0) {
                            data.forEach(function(patient) {
                                var label = patient.first_name + ' ' + patient.last_name + 
                                    ' (' + patient.dob + ', ' + patient.gender + ')';
                                suggestionsHtml += '<div class="suggestion-item" data-id="' + patient.id + '">' +
                                    label + 
                                '</div>';
                            });
                            $('#patient_suggestions').html(suggestionsHtml).show();
                        } else {
                            $('#patient_suggestions').hide();
                        }
                    } catch(e) {
                        console.error('Error parsing patient search response:', e);
                        $('#patient_suggestions').hide();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Patient search error:', error);
                    $('#patient_suggestions').hide();
                }
            });
        }, 300);
    });

    // Handle suggestion click
    $(document).on('click', '#patient_suggestions .suggestion-item', function() {
        var patientId = $(this).data('id');
        var patientLabel = $(this).text();
        $('#patient_search').val(patientLabel);
        $('#patient_id').val(patientId);
        $('#patient_suggestions').hide();
    });

    // Form validation
    $('#add-risk-form').on('submit', function(e) {
        var patientId = $('#patient_id').val();
        var riskId = $('#risk_id').val();
        var noRisks = $('#no_risks').is(':checked');

        if (!patientId) {
            e.preventDefault();
            alert('Please select a patient');
            return false;
        }

        if (!noRisks && !riskId) {
            e.preventDefault();
            alert('Please select a risk');
            return false;
        }

        return true;
    });
});
</script>

<style>
.suggestions-box {
    border: 1px solid #ccc;
    border-top: none;
    max-height: 200px;
    overflow-y: auto;
    position: absolute;
    background: white;
    z-index: 1000;
    width: 300px;
}

.suggestion-item {
    padding: 8px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
}

.suggestion-item:hover {
    background-color: #f5f5f5;
}
</style>
