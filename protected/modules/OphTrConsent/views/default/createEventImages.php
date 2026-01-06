<?php
/**
 * OpenEyes.
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2013
 */
?>

<?php
$this->breadcrumbs=array(
    'OphTrConsent' => array('default/index'),
    'Create Event Images',
);
?>

<div class="box admin">
    <div class="box-content">
        <h2>Create Event Images</h2>
        
        <p>Generate PDF preview images for a consent event.</p>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="<?php echo Yii::app()->createUrl('OphTrConsent/default/createEventImages'); ?>">
            <?php echo CHtml::hiddenField(Yii::app()->request->csrfTokenName, Yii::app()->request->csrfToken); ?>
            <fieldset class="group full-width">
                <legend>Event Details</legend>
                
                <div class="field-row row">
                    <div class="large-3 column">
                        <label for="booking_event_id">Booking Event ID:</label>
                    </div>
                    <div class="large-9 column">
                        <input type="text" id="booking_event_id" name="booking_event_id" 
                               value="<?php echo isset($booking_event_id) ? htmlspecialchars($booking_event_id) : ''; ?>" 
                               placeholder="Enter booking event ID" required />
                        <p class="help-block">The ID of the booking event for which to create PDF preview images.</p>
                    </div>
                </div>
            </fieldset>
            
            <div class="row">
                <div class="large-9 large-offset-3 column">
                    <button type="submit" class="button green">Create Images</button>
                    <?php echo CHtml::link('Cancel', array('/Admin/default/index'), array('class' => 'button')); ?>
                </div>
            </div>
        </form>
    </div>
</div>
