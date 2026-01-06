<?php
require_once 'protected/config/main.php';
Yii::app()->db;

$addresses = Yii::app()->db->createCommand()
    ->select('id, contact_id, address1, city')
    ->from('address')
    ->limit(5)
    ->queryAll();

echo "Found addresses:\n";
foreach ($addresses as $address) {
    echo "ID: {$address['id']}, Contact ID: {$address['contact_id']}, Address: {$address['address1']}, City: {$address['city']}\n";
}
