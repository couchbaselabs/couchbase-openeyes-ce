<?php
/* @var $this DisorderController */
/* @var $model Disorder */

$this->breadcrumbs=array(
    'Disorders'=>array('index'),
    $model->id,
);

?>
<?php
echo CHtml::link(
    'Back to Disorder list',
    array('disorder/index'),
    array('class' => 'button small')
) . ' ';
?>

<h1>View Disorder #<?php echo CHtml::encode($model->id); ?></h1>

<table class="standard highlight-rows">
    <tbody>
    <tr>
        <td>ID: </td>
        <td>
            <?= CHtml::link(CHtml::encode($model->id), array('view', 'id'=>$model->id)); ?>
        </td>
    </tr>
    <tr>
        <td>
            Fully Specified Name:
        </td>
        <td>
            <?= CHtml::encode($model->fully_specified_name); ?>
        </td>
    </tr>
    <tr>
        <td>
            Term:
        </td>
        <td>
            <?= CHtml::encode($model->term); ?>
        </td>
    </tr>
    <tr>
        <td>
            Specialty:
        </td>
        <td>
            <?php
            $specialty = isset($model->specialty_id) ? Specialty::model()->findByPk($model->specialty_id) : null;
            echo $specialty ? CHtml::encode($specialty->name) : '';
            ?>
        </td>
    </tr>
    <tr>
        <td>
            Active:
        </td>
        <td>
            <?= $model->active?'True': 'False'; ?>
        </td>
    </tr>
    </tbody>
</table>