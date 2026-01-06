<?php
/**
 * OpenEyes.
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2014
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2011-2014, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */
?>

<?php 
$this->renderPartial('//elements/form_errors', array('errors' => $errors, 'bottom' => false)); ?>
<form>
    <input type="hidden" name="YII_CSRF_TOKEN" value="<?= Yii::app()->request->csrfToken ?>" />
    <?php if ($parent) {?>
        <input type="hidden" name="parent_id" value="<?=$parent->id?>" />
    <?php }?>

    <div>

    <table>
        <colgroup></colgroup>
        <tbody>
        <tr>
            <th>Name:</th>
            <td><?=\CHtml::textField('name', $queue->name, ['class' => 'cols-full']); ?></td>
        </tr>
        <tr>
            <th>Description:</th>
            <td><?=\CHtml::textArea('description', $queue->description, ['class' => 'cols-full']); ?></td>
        </tr>
        <tr>
            <th>Action Label:</th>
            <td><?=\CHtml::textField('action_label', $queue->action_label, ['class' => 'cols-full']); ?></td>
        </tr>
        <tr>
            <th>Report Definition:</th>
            <td><?=\CHtml::textArea('report_definition', $queue->report_definition, ['class' => 'cols-full autosize']); ?></td>
        </tr>
        <tr>
            <th>Assignment Fields:</th>
            <td><?=\CHtml::textArea('assignment_fields', $queue->assignment_fields, ['class' => 'cols-full autosize', 'rows' => 5]); ?></td>
        </tr>
        <tr>
            <th>Event types:</th>
            <th>
                <?php
                $this->widget('application.widgets.MultiSelectList', array(
                    'element' => $queue,
                    'field' => 'event_types',
                    'relation' => 'event_type_assignments',
                    'relation_id_field' => 'event_type_id',
                    'options' => EventType::model()->getActiveList(),
                    'default_options' => array(),
                    'htmlOptions' => array(
                        'label' => null,
                        'empty' => '- Select -',
                        'nowrapper' => true,
                        'class' => 'cols-full'
                    ),
                    'hidden' => false,
                    'inline' => false,
                    'noSelectionsMessage' => 'None',
                    'showRemoveAllLink' => false,
                    'layoutColumns' => array(
                        'label' => 3,
                        'field' => 8,
                    ),
                    'sortable' => true,
                ))?>
            </th>
        </tr>

        </tbody>
    </table>

    </div>
    
    <div class="form-actions">
        <button type="submit" class="button green" id="et_save_queue">Save Queue</button>
        <a href="/PatientTicketing/admin" class="button">Cancel</a>
    </div>
</form>

<script>
    // Handle jQuery initialization for renderPartial context
    function initializeAutosize() {
        if (typeof $ !== 'undefined' && typeof autosize !== 'undefined') {
            setTimeout(() => autosize($('.autosize')), 0);
        } else if (typeof autosize !== 'undefined') {
            // Fallback if jQuery is not available
            const autosizeElements = document.querySelectorAll('.autosize');
            if (autosizeElements.length > 0) {
                setTimeout(() => autosize(autosizeElements), 0);
            }
        }
    }
    
    // Initialize when DOM is ready
    if (typeof $ !== 'undefined') {
        $(document).ready(initializeAutosize);
    } else {
        document.addEventListener('DOMContentLoaded', initializeAutosize);
    }
    
    // Also try to initialize immediately in case DOM is already loaded
    if (document.readyState === 'interactive' || document.readyState === 'complete') {
        setTimeout(initializeAutosize, 0);
    }
    
    // Handle form submission
    $(document).ready(function() {
        $('form').on('submit', function(e) {
            e.preventDefault();
            
            var form = $(this);
            var submitBtn = form.find('#et_save_queue');
            var originalText = submitBtn.text();
            
            $.ajax({
                url: form.attr('action') || window.location.href,
                type: 'POST',
                data: form.serialize(),
                dataType: 'json',
                beforeSend: function() {
                    submitBtn.prop('disabled', true).text('Saving...');
                },
                success: function(resp) {
                    if (resp.success) {
                        // Redirect to list page
                        window.location.href = '/PatientTicketing/admin';
                    } else {
                        // Replace form with errors
                        if (resp.form) {
                            form.closest('main').html(resp.form);
                        }
                        submitBtn.prop('disabled', false).text(originalText);
                    }
                },
                error: function(jqXHR, status, error) {
                    alert('There was a problem saving the queue: ' + error);
                    submitBtn.prop('disabled', false).text(originalText);
                }
            });
            return false;
        });
    });
</script>