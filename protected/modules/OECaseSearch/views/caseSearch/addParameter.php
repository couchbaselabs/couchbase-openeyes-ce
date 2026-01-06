<?php
/**
 * @var $this CaseSearchController
 * @var $paramList array List of available parameter types
 */
$this->pageTitle = 'Add Search Parameter - Advanced Search';
?>
<div class="oe-full-header flex-layout">
    <div class="title wordcaps">Add Search Parameter</div>
</div>
<div class="oe-full-content subgrid">
    <main class="oe-full-main">
        <div class="cols-12">
            <form id="add-parameter-form" method="post" action="<?php echo $this->createUrl('caseSearch/addParameter'); ?>">
                <div class="row field-row">
                    <label for="parameter-type">Select Parameter Type:</label>
                    <select id="parameter-type" name="parameter_type" required>
                        <option value="">-- Select a parameter --</option>
                        <?php foreach ($paramList as $param): ?>
                            <option value="<?php echo CHtml::encode($param['id']); ?>">
                                <?php echo CHtml::encode($param['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="parameter-details" style="display: none;">
                    <div class="row field-row">
                        <label for="parameter-operation">Operation:</label>
                        <select id="parameter-operation" name="parameter_operation" required>
                            <option value="">-- Select operation --</option>
                        </select>
                    </div>

                    <div class="row field-row">
                        <label for="parameter-value">Value:</label>
                        <input type="text" id="parameter-value" name="parameter_value" />
                    </div>

                    <div class="row">
                        <button type="submit" class="button green">Add Parameter</button>
                        <a href="<?php echo $this->createUrl('caseSearch/index'); ?>" class="button">Cancel</a>
                    </div>
                </div>

                <input type="hidden" name="YII_CSRF_TOKEN" value="<?php echo Yii::app()->request->csrfToken ?>"/>
            </form>
        </div>
    </main>
</div>

<script type="text/javascript">
$(document).ready(function () {
    const parameterOptions = <?php echo CJSON::encode(array_combine(
        array_map(function($p) { return $p['id']; }, $paramList),
        array_map(function($p) { return $p; }, $paramList)
    )); ?>;

    $('#parameter-type').on('change', function () {
        const selectedType = $(this).val();
        if (selectedType) {
            const paramInfo = parameterOptions[selectedType];
            // Fetch the options for this parameter type
            $.ajax({
                url: '<?php echo $this->createUrl('caseSearch/getOptions'); ?>',
                data: { type: selectedType },
                type: 'GET',
                dataType: 'json',
                success: function (options) {
                    const $operationSelect = $('#parameter-operation');
                    $operationSelect.empty();
                    $operationSelect.append($('<option>').val('').text('-- Select operation --'));
                    
                    if (options.operations) {
                        options.operations.forEach(function (op) {
                            $operationSelect.append($('<option>').val(op.id).text(op.label));
                        });
                    }
                    
                    $('#parameter-details').show();
                },
                error: function () {
                    alert('Error loading parameter options');
                }
            });
        } else {
            $('#parameter-details').hide();
        }
    });

    $('#add-parameter-form').on('submit', function (e) {
        e.preventDefault();
        
        const parameterType = $('#parameter-type').val();
        const operation = $('#parameter-operation').val();
        const value = $('#parameter-value').val();

        if (!parameterType || !operation) {
            alert('Please select both parameter type and operation');
            return false;
        }

        // Create the parameter object
        const parameter = {
            id: 1,
            type: parameterType,
            operation: operation,
            value: value || null
        };

        // Add the parameter via AJAX to validate it
        $.ajax({
            url: '<?php echo $this->createUrl('caseSearch/addParameter'); ?>',
            data: { parameter: parameter },
            type: 'GET',
            success: function (response) {
                // Parameter is valid - now we need to submit it to the search
                // Build a form and submit it with the parameter
                const form = $('<form>').attr({
                    method: 'POST',
                    action: '<?php echo $this->createUrl('caseSearch/index'); ?>'
                });
                
                // Add parameter fields to the form using the same naming convention as the search form
                const paramClass = parameterType;  // e.g., 'PatientAgeParameter'
                form.append($('<input>').attr({
                    type: 'hidden',
                    name: paramClass + '[1][id]',
                    value: 1
                }));
                form.append($('<input>').attr({
                    type: 'hidden',
                    name: paramClass + '[1][type]',
                    value: paramClass
                }));
                form.append($('<input>').attr({
                    type: 'hidden',
                    name: paramClass + '[1][operation]',
                    value: operation
                }));
                form.append($('<input>').attr({
                    type: 'hidden',
                    name: paramClass + '[1][value]',
                    value: value || ''
                }));
                form.append($('<input>').attr({
                    type: 'hidden',
                    name: 'YII_CSRF_TOKEN',
                    value: '<?php echo Yii::app()->request->csrfToken ?>'
                }));
                
                // Submit the form
                $('body').append(form);
                form.submit();
            },
            error: function (xhr) {
                alert('Error adding parameter: ' + xhr.responseText);
            }
        });
    });
});
</script>
