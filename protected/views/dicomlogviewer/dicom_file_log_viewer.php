<div class="admin box">
    <h1 class="badge admin">DICOM Log Viewer</h1>
    <form id="dicom_file_watcher">
        <input type="hidden" name="YII_CSRF_TOKEN" value="<?php echo Yii::app()->request->csrfToken ?>"/>
        <table class="standard">
            <thead>
            <tr>
                <th>ID</th>
                <th>Date Time</th>
                <th>File Name</th>
                <th>Status</th>
                <th>Process Name</th>
            </tr>
            </thead>
            <tbody>
            <?php
            foreach ($data as $key => $val) {
                $filename = isset($val['filename']) ? $val['filename'] : 'N/A';
                echo '<tr data-id="'.$val['id'].'" filename="'.$filename.'" status="'.$val['status'].'" >
                    <td id="id">'.$val['id'].'</td>
                    <td id="event_date_time">'.$val['event_date_time'].'</td>
                    <td id="filename"><a>'.$filename.'</a></td>
                    <td id="status">'.$val['status'].'</td>
                    <td id="process_name">'.$val['process_name'].'</td>
                </tr>';
            }
            if (empty($data)) {
                echo '<tr><td colspan="5" style="text-align: center;">No DICOM files found.</td></tr>';
            }
            ?>
            </tbody>
        </table>
    </form>
</div>
