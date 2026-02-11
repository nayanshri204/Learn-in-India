<?php
// Quick script to generate password hash for admin
// Run this: php generate_admin_hash.php
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Password: $password\n";
echo "Hash: $hash\n";
echo "\nUse this hash in database_schema.sql\n";

?>