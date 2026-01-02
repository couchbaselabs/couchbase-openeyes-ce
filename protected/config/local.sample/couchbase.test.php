<?php
/**
 * OpenEyes - Couchbase Test Configuration
 *
 * (C) OpenEyes Foundation, 2025
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (C) 2025, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

$couchbase_test_host = getenv('COUCHBASE_TEST_HOST') ?: 'localhost';
$couchbase_test_port = getenv('COUCHBASE_TEST_PORT') ?: '8091';
$couchbase_test_bucket = getenv('COUCHBASE_TEST_BUCKET') ?: 'openeyes_test';
$couchbase_test_user = getenv('COUCHBASE_TEST_USER') ?: 'Administrator';
$couchbase_test_pass = getenv('COUCHBASE_TEST_PASS') ?: 'password';

return array(
    'components' => array(
        'couchbase' => array(
            'class' => 'CouchbaseConnection',
            'connectionString' => "couchbase://{$couchbase_test_host}:{$couchbase_test_port}",
            'bucket' => $couchbase_test_bucket,
            'username' => $couchbase_test_user,
            'password' => $couchbase_test_pass,
        ),
    ),
    'params' => array(
        'couchbase_enabled' => true,
        'enable_dual_write' => true,
        'enable_couchbase_read' => true,
        'couchbase_test_mode' => true,
    ),
);
