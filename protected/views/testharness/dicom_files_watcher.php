<?php
/**
 * Test Harness - DICOM Files Watcher Test View
 */
?>
<div class="container">
    <h1>DICOM Files Watcher Test Harness</h1>
    <p>This is a test page for DICOM files watcher functionality.</p>
    
    <?php if ($msg): ?>
        <div class="alert alert-<?php echo ($msg === 1) ? 'success' : 'warning'; ?>">
            <strong>Message:</strong> <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>
    
    <h3>Available Files:</h3>
    <?php if (!empty($dirlist)): ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Last Modified</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dirlist as $file): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($file['name']); ?></td>
                        <td><?php echo htmlspecialchars($file['type']); ?></td>
                        <td><?php echo $file['size']; ?> bytes</td>
                        <td><?php echo date('Y-m-d H:i:s', $file['lastmod']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="alert alert-info">No files found.</p>
    <?php endif; ?>
</div>
