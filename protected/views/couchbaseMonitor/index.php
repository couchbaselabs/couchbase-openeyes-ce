<?php
/**
 * Couchbase Monitoring Dashboard View
 * 
 * Real-time monitoring of Couchbase cutover status, health, performance, and sync.
 * 
 * Phase 16: Production Cutover
 * 
 * @var array $cutover Cutover status
 * @var array $health Health status
 * @var array $performance Performance metrics
 * @var array $errors Recent errors
 * @var array $sync Sync status
 * @var int $timestamp Page load timestamp
 */

$this->pageTitle = 'Couchbase Migration Monitor';
$this->breadcrumbs = [
    'Admin' => ['/admin'],
    'Couchbase Monitor',
];
?>

<style>
.monitor-card {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
    background: white;
}

.monitor-card h3 {
    margin-top: 0;
    border-bottom: 2px solid #ddd;
    padding-bottom: 10px;
}

.status-indicator {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 8px;
}

.status-success { background-color: #5cb85c; }
.status-warning { background-color: #f0ad4e; }
.status-danger { background-color: #d9534f; }
.status-unknown { background-color: #999; }

.metric-row {
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.metric-row:last-child {
    border-bottom: none;
}

.metric-label {
    font-weight: bold;
    display: inline-block;
    width: 200px;
}

.metric-value {
    display: inline-block;
}

.traffic-slider {
    width: 100%;
    margin: 20px 0;
}

.emergency-button {
    margin: 10px 0;
}

.phase-badge {
    font-size: 1.2em;
    padding: 10px 15px;
    border-radius: 4px;
}

.auto-refresh {
    font-size: 12px;
    color: #999;
    float: right;
}

.error-log {
    max-height: 300px;
    overflow-y: auto;
    font-family: monospace;
    font-size: 12px;
    background: #f5f5f5;
    padding: 10px;
    border-radius: 4px;
}

.error-log-entry {
    padding: 4px 0;
    border-bottom: 1px solid #ddd;
}

table.sync-table {
    width: 100%;
    border-collapse: collapse;
}

table.sync-table th,
table.sync-table td {
    padding: 8px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

table.sync-table th {
    background-color: #f5f5f5;
    font-weight: bold;
}
</style>

<div class="row">
    <div class="col-12">
        <h1>
            Couchbase Migration Monitor
            <span class="auto-refresh" id="auto-refresh-indicator">
                Auto-refresh: <span id="countdown">30</span>s
            </span>
        </h1>
        
        <?php if (Yii::app()->user->hasFlash('success')): ?>
            <div class="alert alert-success">
                <?php echo Yii::app()->user->getFlash('success'); ?>
            </div>
        <?php endif; ?>
        
        <?php if (Yii::app()->user->hasFlash('error')): ?>
            <div class="alert alert-danger">
                <?php echo Yii::app()->user->getFlash('error'); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Cutover Status -->
<div class="row">
    <div class="col-12">
        <div class="monitor-card">
            <h3>Cutover Status</h3>
            
            <?php if (isset($cutover['error'])): ?>
                <div class="alert alert-danger">
                    Error: <?php echo CHtml::encode($cutover['error']); ?>
                </div>
            <?php else: ?>
                
                <!-- Phase Badge -->
                <div style="text-align: center; margin-bottom: 20px;">
                    <span class="phase-badge alert alert-<?php echo $cutover['emergency_disable'] ? 'danger' : 'info'; ?>">
                        <?php echo CHtml::encode($cutover['phase_name']); ?>
                    </span>
                </div>
                
                <!-- Emergency Status -->
                <?php if ($cutover['emergency_disable']): ?>
                    <div class="alert alert-danger">
                        <strong>⚠ EMERGENCY DISABLE ACTIVE</strong><br>
                        Reason: <?php echo CHtml::encode($cutover['emergency_reason']); ?><br>
                        <?php if ($cutover['emergency_timestamp']): ?>
                            Time: <?php echo date('Y-m-d H:i:s', $cutover['emergency_timestamp']); ?>
                        <?php endif; ?>
                        <br><br>
                        <?php echo CHtml::beginForm(['clearEmergency'], 'post'); ?>
                            <?php echo CHtml::submitButton('Clear Emergency Disable', ['class' => 'btn btn-warning', 'onclick' => 'return confirm("Are you sure you want to clear emergency disable?");']); ?>
                        <?php echo CHtml::endForm(); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Configuration -->
                <div class="metric-row">
                    <span class="metric-label">Enabled:</span>
                    <span class="metric-value">
                        <span class="status-indicator status-<?php echo $cutover['enabled'] ? 'success' : 'danger'; ?>"></span>
                        <?php echo $cutover['enabled'] ? 'YES' : 'NO'; ?>
                    </span>
                </div>
                
                <div class="metric-row">
                    <span class="metric-label">Read Source:</span>
                    <span class="metric-value"><?php echo CHtml::encode($cutover['read_source']); ?></span>
                </div>
                
                <div class="metric-row">
                    <span class="metric-label">Write Mode:</span>
                    <span class="metric-value"><?php echo CHtml::encode($cutover['write_mode']); ?></span>
                </div>
                
                <div class="metric-row">
                    <span class="metric-label">Couchbase Read Traffic:</span>
                    <span class="metric-value">
                        <strong><?php echo $cutover['percentage']; ?>%</strong>
                        <small>(<?php echo (100 - $cutover['percentage']); ?>% MariaDB)</small>
                    </span>
                </div>
                
                <div class="metric-row">
                    <span class="metric-label">Fallback Enabled:</span>
                    <span class="metric-value">
                        <span class="status-indicator status-<?php echo $cutover['fallback_enabled'] ? 'success' : 'warning'; ?>"></span>
                        <?php echo $cutover['fallback_enabled'] ? 'YES' : 'NO'; ?>
                    </span>
                </div>
                
                <!-- Traffic Control -->
                <?php if (!$cutover['emergency_disable']): ?>
                    <hr>
                    <h4>Traffic Control</h4>
                    <?php echo CHtml::beginForm(['setTraffic'], 'post', ['id' => 'traffic-form']); ?>
                        <div class="form-group">
                            <label for="percentage">Couchbase Read Traffic Percentage:</label>
                            <input type="range" class="traffic-slider" id="percentage" name="percentage" 
                                   min="0" max="100" step="10" value="<?php echo $cutover['percentage']; ?>"
                                   oninput="document.getElementById('percentage-value').textContent = this.value + '%'">
                            <div style="text-align: center; margin-top: 10px;">
                                <strong id="percentage-value"><?php echo $cutover['percentage']; ?>%</strong>
                            </div>
                        </div>
                        <div style="text-align: center;">
                            <?php echo CHtml::submitButton('Update Traffic Percentage', [
                                'class' => 'btn btn-primary',
                                'onclick' => 'return confirm("Are you sure you want to update the traffic percentage?");'
                            ]); ?>
                        </div>
                    <?php echo CHtml::endForm(); ?>
                <?php endif; ?>
                
                <!-- Emergency Controls -->
                <hr>
                <h4>Emergency Controls</h4>
                <?php if (!$cutover['emergency_disable']): ?>
                    <?php echo CHtml::beginForm(['emergencyDisable'], 'post', ['id' => 'emergency-form']); ?>
                        <div class="form-group">
                            <label for="reason">Reason for emergency disable:</label>
                            <input type="text" class="form-control" id="reason" name="reason" 
                                   placeholder="e.g., High error rate detected" required>
                        </div>
                        <?php echo CHtml::submitButton('EMERGENCY DISABLE', [
                            'class' => 'btn btn-danger emergency-button',
                            'onclick' => 'return confirm("WARNING: This will immediately route ALL traffic to MariaDB. Continue?");'
                        ]); ?>
                    <?php echo CHtml::endForm(); ?>
                <?php endif; ?>
                
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Health Status -->
<div class="row">
    <div class="col-md-6">
        <div class="monitor-card">
            <h3>Database Health</h3>
            
            <!-- MariaDB Health -->
            <h4>MariaDB</h4>
            <div class="metric-row">
                <span class="metric-label">Status:</span>
                <span class="metric-value">
                    <span class="status-indicator status-<?php echo $health['mariadb']['status_class'] ?? 'unknown'; ?>"></span>
                    <?php echo CHtml::encode($health['mariadb']['status']); ?>
                </span>
            </div>
            <?php if (isset($health['mariadb']['latency'])): ?>
                <div class="metric-row">
                    <span class="metric-label">Latency:</span>
                    <span class="metric-value"><?php echo $health['mariadb']['latency']; ?>ms</span>
                </div>
            <?php endif; ?>
            <?php if (isset($health['mariadb']['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo CHtml::encode($health['mariadb']['error']); ?>
                </div>
            <?php endif; ?>
            
            <hr>
            
            <!-- Couchbase Health -->
            <h4>Couchbase</h4>
            <div class="metric-row">
                <span class="metric-label">Status:</span>
                <span class="metric-value">
                    <span class="status-indicator status-<?php echo $health['couchbase']['status_class'] ?? 'unknown'; ?>"></span>
                    <?php echo CHtml::encode($health['couchbase']['status']); ?>
                </span>
            </div>
            <?php if (isset($health['couchbase']['latency'])): ?>
                <div class="metric-row">
                    <span class="metric-label">Latency:</span>
                    <span class="metric-value"><?php echo $health['couchbase']['latency']; ?>ms</span>
                </div>
            <?php endif; ?>
            <?php if (isset($health['couchbase']['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo CHtml::encode($health['couchbase']['error']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Performance Metrics -->
    <div class="col-md-6">
        <div class="monitor-card">
            <h3>Performance Metrics</h3>
            
            <?php if (!$performance['available']): ?>
                <p><?php echo CHtml::encode($performance['message'] ?? $performance['error'] ?? 'Not available'); ?></p>
            <?php else: ?>
                <?php if (isset($performance['health'])): ?>
                    <div class="alert alert-<?php 
                        echo $performance['health']['status'] === 'healthy' ? 'success' : 
                             ($performance['health']['status'] === 'degraded' ? 'warning' : 'danger'); 
                    ?>">
                        <strong>Status:</strong> <?php echo CHtml::encode($performance['health']['status']); ?>
                        <?php if (isset($performance['health']['p95'])): ?>
                            (p95: <?php echo round($performance['health']['p95'], 2); ?>ms)
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($performance['summary']) && !empty($performance['summary'])): ?>
                    <table class="sync-table">
                        <thead>
                            <tr>
                                <th>Operation</th>
                                <th>Count</th>
                                <th>Avg (ms)</th>
                                <th>Max (ms)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($performance['summary'], 0, 10) as $operation => $stats): ?>
                                <tr>
                                    <td><?php echo CHtml::encode($operation); ?></td>
                                    <td><?php echo number_format($stats['count']); ?></td>
                                    <td><?php echo round($stats['avg_ms'], 2); ?></td>
                                    <td><?php echo round($stats['max_ms'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No performance data available yet.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Sync Status -->
<div class="row">
    <div class="col-12">
        <div class="monitor-card">
            <h3>Data Sync Status</h3>
            
            <?php if (isset($sync['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo CHtml::encode($sync['error']); ?>
                </div>
            <?php else: ?>
                <table class="sync-table">
                    <thead>
                        <tr>
                            <th>Table</th>
                            <th>MariaDB</th>
                            <th>Couchbase</th>
                            <th>Difference</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sync as $table => $data): ?>
                            <tr>
                                <td><strong><?php echo CHtml::encode($table); ?></strong></td>
                                <?php if (isset($data['error'])): ?>
                                    <td colspan="4">
                                        <span class="text-danger">
                                            <?php echo CHtml::encode($data['error']); ?>
                                        </span>
                                    </td>
                                <?php else: ?>
                                    <td><?php echo number_format($data['mariadb']); ?></td>
                                    <td><?php echo number_format($data['couchbase']); ?></td>
                                    <td>
                                        <?php if ($data['diff'] !== 0): ?>
                                            <span class="text-<?php echo $data['status_class']; ?>">
                                                <?php echo $data['diff'] > 0 ? '+' : ''; ?><?php echo number_format($data['diff']); ?>
                                                (<?php echo $data['diff_percent']; ?>%)
                                            </span>
                                        <?php else: ?>
                                            <span class="text-success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-indicator status-<?php echo $data['status_class']; ?>"></span>
                                        <?php echo $data['synced'] ? 'Synced' : 'Out of sync'; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Errors -->
<div class="row">
    <div class="col-12">
        <div class="monitor-card">
            <h3>Recent Errors (Last 10)</h3>
            
            <?php if (empty($errors)): ?>
                <p class="text-success">No recent errors.</p>
            <?php else: ?>
                <div class="error-log">
                    <?php foreach ($errors as $error): ?>
                        <div class="error-log-entry">
                            <?php echo CHtml::encode($error); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Footer Info -->
<div class="row">
    <div class="col-12">
        <p class="text-muted text-center">
            Last updated: <?php echo date('Y-m-d H:i:s', $timestamp); ?> |
            <a href="<?php echo $this->createUrl('metrics'); ?>" target="_blank">JSON API</a> |
            <a href="<?php echo $this->createUrl('index'); ?>">Refresh Now</a>
        </p>
    </div>
</div>

<!-- Auto-refresh script -->
<script>
(function() {
    var countdown = 30;
    var countdownElement = document.getElementById('countdown');
    
    setInterval(function() {
        countdown--;
        if (countdown <= 0) {
            location.reload();
        }
        if (countdownElement) {
            countdownElement.textContent = countdown;
        }
    }, 1000);
})();
</script>
