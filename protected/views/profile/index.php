<?php
/**
 * OpenEyes Profile Index View - Public Access
 *
 * This view is displayed for unauthenticated users accessing the profile index page
 */
$this->pageTitle = 'User Profile';
?>

<div class="box patient-info oe-full-height ">
    <div class="box-content oe-full-height">
        <div class="oe-full-height">
            <div class="patient-info-bar flex-layout">
                <div class="patient-info-bar-title">
                    <h1 class="patient-name">User Profiles</h1>
                </div>
            </div>
            <div class="oe-full-content oe-scrollable">
                <p>User profile information is available to authenticated users only.</p>
                <p>Please <a href="<?php echo $this->createUrl('/site/login'); ?>">log in</a> to view your profile.</p>
            </div>
        </div>
    </div>
</div>
