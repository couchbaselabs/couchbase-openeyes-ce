<?php
/**
 * Form for Site Subspecialty Operative Device
 */
?>

<div class="element-fields full-width">
    <h3><?php echo $title; ?></h3>

    <?php echo $form->hiddenField($model, 'site_id'); ?>
    <?php echo $form->hiddenField($model, 'subspecialty_id'); ?>

    <div class="field-row">
        <label for="SiteSubspecialtyOperativeDevice_operative_device_id">Operative Device:</label>
        <div class="field">
            <?php
            $operativeDevices = OperativeDevice::model()->active()->findAll(array('order' => 'name ASC'));
            $deviceOptions = array();
            foreach ($operativeDevices as $device) {
                $deviceOptions[$device->id] = $device->name;
            }
            echo $form->dropDownList($model, 'operative_device_id', $deviceOptions, array(
                'empty' => '-- Select an operative device --',
                'class' => 'field-width-small',
            ));
            ?>
        </div>
    </div>

    <div class="field-row">
        <label for="SiteSubspecialtyOperativeDevice_default">Set as Default:</label>
        <div class="field">
            <?php echo $form->checkBox($model, 'default', array('value' => 1)); ?>
        </div>
    </div>
</div>
