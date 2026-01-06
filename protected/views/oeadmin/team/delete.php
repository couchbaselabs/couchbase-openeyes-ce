<?php
$form = $this->beginWidget(
    'BaseEventTypeCActiveForm',
    [
        'id' => 'team-delete-form',
        'enableAjaxValidation' => false,
        'action' => Yii::app()->createUrl('oeadmin/team/delete/' . $team->id),
        'method' => 'post'
    ]
);
?>
<div class="row divider">
    <div class="alert-box warning">
        <b>Warning:</b> You are about to deactivate the team "<?php echo CHtml::encode($team->name); ?>".
        This action cannot be easily reversed.
    </div>
</div>

<table class="standard">
    <thead>
        <tr>
            <th>Property</th>
            <th>Value</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Team Name</strong></td>
            <td><?php echo CHtml::encode($team->name); ?></td>
        </tr>
        <tr>
            <td><strong>Team Email</strong></td>
            <td><?php echo $team->contact ? CHtml::encode($team->contact->email) : '<em>None</em>'; ?></td>
        </tr>
        <tr>
            <td><strong>Institution</strong></td>
            <td><?php echo $team->institution ? CHtml::encode($team->institution->name) : '<em>None</em>'; ?></td>
        </tr>
        <tr>
            <td><strong>Active</strong></td>
            <td><?php echo $team->active ? '<i class="oe-i tick small"></i>' : '<i class="oe-i remove small"></i>'; ?></td>
        </tr>
    </tbody>
</table>

<div class="row divider">
    <input
        type="hidden"
        name="Team[0]"
        value="<?php echo $team->id ?>"
    />
    <input
        type="hidden"
        name="YII_CSRF_TOKEN"
        value="<?php echo Yii::app()->request->csrfToken ?>"
    />

    <?php echo CHtml::submitButton(
        'Deactivate Team',
        [
            'class' => 'button large red',
            'data-test' => 'confirm-delete-button'
        ]
    ); ?>

    <?php echo CHtml::linkButton(
        'Cancel',
        [
            'href' => $cancel_url,
            'class' => 'button large'
        ]
    ); ?>
</div>

<?php $this->endWidget(); ?>
