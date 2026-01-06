<?php
?>

<div class="oe-full-header flex-layout">
    <div class="title wordcaps">Virus Scanning</div>
</div>
<div class="oe-full-content">
    <div class="cols-9">
        <?php
        // Display flash messages if any
        if (Yii::app()->user->hasFlash('error')) {
            echo '<div class="alert alert-danger" style="margin-bottom: 20px;">';
            echo CHtml::encode(Yii::app()->user->getFlash('error'));
            echo '</div>';
        }
        if (Yii::app()->user->hasFlash('success')) {
            echo '<div class="alert alert-success" style="margin-bottom: 20px;">';
            echo CHtml::encode(Yii::app()->user->getFlash('success'));
            echo '</div>';
        }
        ?>
        <?php
        echo CHtml::form(array('virusScan/scanProtectedFiles'), 'post');
        echo CHtml::submitButton('Scan Files', array('class' => 'green hint'));
        echo CHtml::endForm();
        ?>
    </div>
</div>
