<?php
// Test script to trigger the disorder autoComplete action directly

// Set up Yii environment
$yii = dirname(__FILE__) . '/protected/yii.php';
$config = dirname(__FILE__) . '/protected/config/main.php';

require_once($yii);

// Create the app
Yii::createWebApplication($config);

// Set up a test session with admin user
Yii::app()->session->start();
Yii::app()->user->login(User::model()->findByAttributes(array('username' => 'admin')));

// Simulate AJAX request
Yii::app()->request->enableCsrfValidation = false;
$_GET['term'] = 'test';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

// Create controller and run action
$controller = new DisorderController('disorder');
$controller->actionAutoComplete();
?>
