<?php
session_start();
// Clear admin session
unset($_SESSION['admin_id']);
unset($_SESSION['admin_email']);
session_regenerate_id(true);
header('Location: admin_login.php');
exit;

