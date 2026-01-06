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
                <div class="alert-box info">
                    <p>To view and manage your profile, <a href="<?php echo $this->createUrl('/site/login'); ?>">please log in</a>.</p>
                </div>
                <table class="standard">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 20px;">
                                No user profiles available. Please log in to manage your profile.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
