<?php
/**
 * Sign CVI with PIN
 */
?>

<div class="box">
    <div class="box-content">
        <h2>Sign CVI</h2>
        <p>Please enter your PIN to sign this CVI.</p>
        
        <?php $form = $this->beginWidget('CActiveForm', array(
            'id' => 'sign-cvi-form',
            'method' => 'GET',
            'action' => array('/'.$this->module->name.'/default/signCVI', 'id' => $event_id),
        )); ?>
        
        <div class="form-group">
            <label for="signature_pin">PIN:</label>
            <input type="password" id="signature_pin" name="signature_pin" required placeholder="Enter your PIN" />
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Sign CVI</button>
            <a href="<?php echo $this->createUrl('/'.$this->module->name.'/default/view/', array('id' => $event_id)); ?>" class="btn btn-secondary">Cancel</a>
        </div>
        
        <?php $this->endWidget(); ?>
    </div>
</div>
