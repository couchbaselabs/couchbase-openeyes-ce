<?php
/**
 * View for finding duplicate patients by identifier
 */
?>

<div class="box admin patient">
    <div class="box-header">
        <h2>Find Duplicate Patients by Identifier</h2>
    </div>
    <div class="box-content">
        <p>Searching for patients with identifier value: <strong><?php echo CHtml::encode($identifier_value); ?></strong></p>
        
        <?php if (empty($duplicates)): ?>
            <p>No duplicate patients found with this identifier.</p>
        <?php else: ?>
            <table class="standard">
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Name</th>
                        <th>DOB</th>
                        <th>NHS Number</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($duplicates as $patient): ?>
                        <tr>
                            <td><?php echo $patient->id; ?></td>
                            <td><?php echo CHtml::encode($patient->first_name . ' ' . $patient->last_name); ?></td>
                            <td><?php echo $patient->getNHSDate('dob'); ?></td>
                            <td><?php echo CHtml::encode($patient->getNhs()); ?></td>
                            <td>
                                <a href="<?php echo Yii::app()->createUrl('/patient/view/' . $patient->id); ?>" class="button small">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
