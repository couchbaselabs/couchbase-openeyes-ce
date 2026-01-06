<?php
/**
 * OpenEyes
 *
 * (C) OpenEyes Foundation, 2019
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2019, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */
$errorMessages = array(
    'badreferer' => 'ERROR: Bad referer, you are not allowed to open this page directly! Use the administration menu for managing anaesthetic agent defaults.',
    'recordmissing' => 'ERROR: The requested record does not exist in the database!',
    'notajaxcall' => 'ERROR: This page cannot be accessed directly, please use the Manage Anaesthetic Agent Defaults list to add new records!',
);
?>
<div class="large-12 column">
    <div class="alert-box with-icon warning" id="flash-anaesthetic_agent_defaults">
        <?php echo htmlspecialchars($errorMessages[$errormessage]) ?>
    </div>
</div>
