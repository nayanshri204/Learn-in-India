<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Learn in India</title>
     <link rel="stylesheet" href="/Learn-in-India/assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="/Learn-in-India/index.php">Learn in India</a>
        <nav class="nav">
            <a href="/Learn-in-India/index.php">Home</a>
            <a href="/Learn-in-India/courses.php">Courses</a>
            <a href="/Learn-in-India/features.php">Features</a>

            <?php if (!empty($_SESSION['admin_id'])): ?>
                <a href="/Learn-in-India/admin/admin_dashboard.php">Admin Panel</a>
                <a href="/Learn-in-India/admin/admin_logout.php">Admin Logout</a>
            <?php elseif (!empty($_SESSION['intern_email'])): ?>
                <a href="/Learn-in-India/dashboard.php">Dashboard</a>
                <a href="/Learn-in-India/student/student_exams.php">Exams</a>
                <a href="/Learn-in-India/student/profile.php">Profile</a>
            <?php else: ?>
                <a href="/Learn-in-India/auth/registration.php">Register</a>
                <a href="/Learn-in-India/auth/login.php">Login</a>
                <a href="/Learn-in-India/admin/admin_login.php" style="color: #ff6b6b;">Admin</a>
            <?php endif; ?>
        </nav>
        
        <form class="nav-search" method="get" action="/Learn-in-India/courses.php">
            <input name="q" type="search" placeholder="Search courses...">
            <button type="submit">Search</button>
        </form>
    </div>
</header>
<main class="container">