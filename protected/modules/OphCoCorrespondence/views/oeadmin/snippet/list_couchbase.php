<?php
/**
 * Custom list view for Letter Snippets when running in Couchbase-primary mode.
 * This avoids the JOINs required by the standard Admin list view.
 */

/* @var $this SnippetController */
/* @var $models array of LetterString models */
/* @var $institutions array */
/* @var $sites array */
?>

<?php $this->renderPartial('//base/_messages'); ?>

<div class="cols-full">
    <h2>Letter String</h2>
    
    <form id="snippet-search" method="GET" action="">
        <table class="standard">
            <tbody>
                <tr>
                    <td>
                        <?= CHtml::dropDownList('institution_id', $selectedInstitution, $institutions, array(
                            'empty' => '-- Select Institution --',
                        )); ?>
                    </td>
                    <td>
                        <?= CHtml::dropDownList('site_id', $selectedSite, $sites, array(
                            'empty' => 'All sites',
                        )); ?>
                    </td>
                    <td>
                        <?= CHtml::textField('name', $searchName, array('placeholder' => 'Name')); ?>
                    </td>
                    <td>
                        <button class="button large" type="submit">Search</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </form>

    <form id="generic-admin-list">
        <input type="hidden" name="YII_CSRF_TOKEN" value="<?php echo Yii::app()->request->csrfToken ?>"/>
        
        <table class="standard">
            <thead>
                <tr>
                    <th><input type="checkbox" name="selectall" id="selectall"/></th>
                    <th>Display Order</th>
                    <th>Id</th>
                    <th>Sites</th>
                    <th>Name</th>
                    <th>Body</th>
                    <th>Element Type Name</th>
                    <th>Event Type Name</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($models)): ?>
                <tr>
                    <td colspan="8">No snippets found.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($models as $model): ?>
                <tr class="clickable" data-id="<?= $model->id ?>" data-uri="OphCoCorrespondence/oeadmin/snippet/edit/<?= $model->id ?>">
                    <td>
                        <input type="checkbox" name="LetterString[id][]" value="<?= $model->id ?>"/>
                    </td>
                    <td>&uarr;&darr;<input type="hidden" name="LetterString[display_order][]" value="<?= $model->id ?>"></td>
                    <td><?= CHtml::encode($model->id) ?></td>
                    <td>
                        <?php 
                        if (isset($model->sites) && is_array($model->sites)) {
                            $siteNames = array();
                            foreach ($model->sites as $site) {
                                $siteNames[] = is_object($site) ? $site->name : $site;
                            }
                            echo CHtml::encode(implode(', ', $siteNames));
                        }
                        ?>
                    </td>
                    <td><?= CHtml::encode($model->name) ?></td>
                    <td><?= mb_substr(CHtml::encode(strip_tags($model->body)), 0, 100) ?>...</td>
                    <td><?= CHtml::encode($model->element_type) ?></td>
                    <td><?= CHtml::encode($model->event_type) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot class="pagination-container">
                <tr>
                    <td colspan="8">
                        <button class="button large" name="add" id="et_add" type="button" data-uri="/OphCoCorrespondence/oeadmin/snippet/edit">Add to <?= CHtml::encode($currentInstitutionName) ?></button>
                        <button class="button large" name="delete" id="et_delete" type="button" data-uri="/OphCoCorrespondence/oeadmin/snippet/delete">Delete</button>
                    </td>
                </tr>
            </tfoot>
        </table>
    </form>
</div>

<script>
$(document).ready(function() {
    // Handle row clicks
    $('tbody tr.clickable').on('click', function(e) {
        if (!$(e.target).is('input[type="checkbox"]')) {
            window.location.href = '/' + $(this).data('uri');
        }
    });
    
    // Handle add button
    $('#et_add').on('click', function() {
        window.location.href = $(this).data('uri');
    });
    
    // Handle delete button
    $('#et_delete').on('click', function() {
        var ids = [];
        $('input[name="LetterString[id][]"]:checked').each(function() {
            ids.push($(this).val());
        });
        if (ids.length > 0 && confirm('Are you sure you want to delete the selected items?')) {
            window.location.href = $(this).data('uri') + '?id=' + ids.join(',');
        }
    });
    
    // Select all
    $('#selectall').on('click', function() {
        $('input[name="LetterString[id][]"]').prop('checked', $(this).prop('checked'));
    });
});
</script>
