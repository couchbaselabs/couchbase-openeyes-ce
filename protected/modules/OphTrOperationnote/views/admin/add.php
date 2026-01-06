<?php
/**
 * View for adding a new operative device mapping
 */
?>

<?php
$form = $this->beginWidget('BaseEventTypeCActiveForm', array(
    'id' => 'adminform',
    'enableAjaxValidation' => false,
    'htmlOptions' => array(
        'enctype' => 'multipart/form-data',
    ),
    'layoutColumns' => array(
        'label' => 2,
        'field' => 5,
    ),
)) ?>

<?php echo $form->errorSummary($model) ?>

<?php 
// Display flash messages
if (Yii::app()->user->hasFlash('error')) {
    echo '<div class="alert-box alert">' . Yii::app()->user->getFlash('error') . '</div>';
}
if (Yii::app()->user->hasFlash('success')) {
    echo '<div class="alert-box success">' . Yii::app()->user->getFlash('success') . '</div>';
}
?>

<?php
$this->renderPartial('/admin/form_SiteSubspecialtyOperativeDevice', array(
    'model' => $model,
    'form' => $form,
    'title' => $title,
)) ?>

<?php echo $form->formActions(array('cancel-uri' => isset($cancel_uri) ? $cancel_uri : "")) ?>

<?php $this->endWidget();
