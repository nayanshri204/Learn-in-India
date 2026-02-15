<?php
session_start();
// Clear intern session
unset($_SESSION['intern_email']);
session_regenerate_id(true);
header('Location: index.php');
exit;
