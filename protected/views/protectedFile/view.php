<div class="container">
    <div class="content-header">
        <h1>Protected File Details</h1>
    </div>
    
    <div class="content-body">
        <?php if (isset($file) && $file): ?>
            <table class="table table-striped">
                <tbody>
                    <tr>
                        <th>File ID:</th>
                        <td><?php echo htmlspecialchars($file->id); ?></td>
                    </tr>
                    <tr>
                        <th>File Name:</th>
                        <td><?php echo htmlspecialchars($file->name); ?></td>
                    </tr>
                    <?php if (!empty($file->title)): ?>
                    <tr>
                        <th>Title:</th>
                        <td><?php echo htmlspecialchars($file->title); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($file->description)): ?>
                    <tr>
                        <th>Description:</th>
                        <td><?php echo htmlspecialchars($file->description); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>MIME Type:</th>
                        <td><?php echo htmlspecialchars($file->mimetype); ?></td>
                    </tr>
                    <tr>
                        <th>File Size:</th>
                        <td><?php echo number_format($file->size) . ' bytes'; ?></td>
                    </tr>
                    <tr>
                        <th>UID:</th>
                        <td><?php echo htmlspecialchars($file->uid); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <div class="form-actions">
                <a href="<?php echo Yii::app()->createUrl('protectedFile/download', array('id' => $file->id)); ?>" class="btn btn-primary">
                    Download File
                </a>
                <a href="<?php echo Yii::app()->createUrl('site/index'); ?>" class="btn btn-secondary">
                    Back to Home
                </a>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                File not found or no file data available.
            </div>
        <?php endif; ?>
    </div>
</div>
