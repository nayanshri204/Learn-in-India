<?php
// Quick script to generate password hash for admin
// Run this: php generate_admin_hash.php
$password = 'YOUR_ADMIN_PASSWORD_HERE'; // Change this to your desired admin password
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Password: $password\n";
echo "Hash: $hash\n";
echo "\nUse this hash in database_schema.sql\n";

?>