<?php
// Quick script to generate a bcrypt hash
$password = 'admin';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo $hash;
?>
