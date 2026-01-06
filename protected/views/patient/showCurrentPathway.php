<?php
/**
 * View for displaying the current pathway
 * @var $pathway Pathway
 */
?>

<div class="box content">
    <div class="box-header">
        <h2>Current Pathway</h2>
    </div>
    <div class="box-content">
        <?php if ($pathway): ?>
            <div class="pathway-display">
                <div class="pathway-section">
                    <h3><?php echo CHtml::encode($pathway->name ?? 'Pathway'); ?></h3>
                    <?php if (isset($pathway->description)): ?>
                        <p><?php echo CHtml::encode($pathway->description); ?></p>
                    <?php endif; ?>
                </div>
                
                <?php if (method_exists($pathway, 'steps') && $pathway->steps): ?>
                    <div class="pathway-steps">
                        <h4>Steps:</h4>
                        <ul>
                            <?php foreach ($pathway->steps as $step): ?>
                                <li><?php echo CHtml::encode($step->name ?? 'Step'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="alert">Pathway not found or no longer available.</p>
        <?php endif; ?>
    </div>
</div>
