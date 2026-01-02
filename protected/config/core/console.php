<?php
/**
 * OpenEyes.
 *
 * (C) Apperta Foundation, 2020
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @link http://www.openeyes.org.uk
 *
 * @author OpenEyes <info@apperta.org>
 * @copyright Copyright (c) 2020, Apperta Foundation
 * @license http://www.gnu.org/licenses/agpl-3.0.html The GNU Affero General Public License V3.0
 */

// MariaDB has been REMOVED - Couchbase is now the only database
// These dummy values are kept for backward compatibility with Yii framework
$db = array(
    'host' => 'localhost',
    'port' => '3306',
    'dbname' => 'openeyes',
    'username' => 'openeyes',
    'password' => 'openeyes',
);
$db_test = array(
    'host' => 'localhost',
    'port' => '3306',
    'dbname' => 'openeyes_test',
    'username' => 'openeyes',
    'password' => 'openeyes',
);

$config = array(
    'name' => 'OpenEyes Console',
    'import' => array(
            'application.components.*',
            'application.modules.OphCoCorrespondence.components.*',
            'system.cli.commands.*',
    ),
    'commandMap' => array(
        'migrate' => array(
            'class' => 'application.commands.OEMigrateCommand',
            'migrationPath' => 'application.migrations',
            'migrationTable' => 'tbl_migration',
            'connectionID' => 'db',
        ),
    ),
    'components' => array(
        // DEPRECATED: MariaDB removed - uses static schema only
        'db' => array(
            'class' => "OEDbConnection",
            'connectionString' => '', // No connection - MariaDB removed
            'username' => '',
            'password' => '',
            'autoConnect' => false,
        ),
        // DEPRECATED: Test database - MariaDB removed
        'testdb' => array(
            'class' => "OEDbConnection",
            'connectionString' => '', // No connection - MariaDB removed
            'username' => '',
            'password' => '',
            'autoConnect' => false,
        ),
        'couchbase' => array(
            'class' => 'application.components.CouchbaseConnection',
            'config' => require(__DIR__ . '/../couchbase.php'),
        ),
        'mailer' => array(
            // Setting the mailer mode to null will suppress email
            //'mode' => null
            // Mail can be diverted by setting the divert array
            //'divert' => array('foo@example.org', 'bar@example.org')
        ),
    ),
);

if (preg_match('/\/protected\/modules\/deploy\/yiic$/', @$_SERVER['SCRIPT_FILENAME']) || preg_match('/\/protected\/modules\/deploy$/', @$_SERVER['PWD'])) {
    $config['commandMap']['migrate']['class'] = 'MigrateCommand';
    $config['commandMap']['migrate']['migrationPath'] = 'application.modules.deploy.migrations';
    $config['commandMap']['migrate']['migrationTable'] = 'tbl_migration_deploy';
}

//Module commands
$modulesDir = __DIR__.'/../../modules/';
$modules = opendir($modulesDir);
if ($modules) {
    while (false !== ($filename = readdir($modules))) {
        if (!in_array($filename, array('.', '..'), true) && is_dir($modulesDir.$filename)) {
            $module = opendir($modulesDir.$filename);
            while (false !== ($moduleSub = readdir($module))) {
                if ($moduleSub === 'commands' && is_dir($modulesDir.$filename.'/'.$moduleSub)) {
                    $commands = scandir($modulesDir.$filename.'/'.$moduleSub);
                    foreach ($commands as $command) {
                        if (strpos($command, 'Command.php')) {
                            $commandName = substr($command, 0, strpos($command, 'Command.php'));
                            $config['commandMap'][strtolower($commandName)] = array('class' => 'application.modules.'.$filename.'.commands.'.$commandName.'Command');
                        }
                    }
                }
            }
        }
    }
}

// Merge with local console config if it exists
$localConfig = __DIR__ . '/../local/console.php';
if (file_exists($localConfig)) {
    $localConsoleConfig = include($localConfig);
    // Manually merge arrays (can't use CMap::mergeArray as Yii not loaded yet)
    foreach ($localConsoleConfig as $key => $value) {
        if (isset($config[$key]) && is_array($config[$key]) && is_array($value)) {
            $config[$key] = array_replace_recursive($config[$key], $value);
        } else {
            $config[$key] = $value;
        }
    }
}

return $config;
