<?php
if (session_status() === PHP_SESSION_NONE) session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Learn in India</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="index.php">Learn in India</a>
        <nav class="nav">
            <a href="index.php">Home</a>
            <a href="courses.php">Courses</a>
            <a href="features.php">Features</a>
            <?php if (!empty($_SESSION['admin_id'])): ?>
                <a href="admin_dashboard.php">Admin Panel</a>
                <a href="admin_logout.php">Admin Logout</a>
            <?php elseif (!empty($_SESSION['intern_email'])): ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="student_exams.php">Exams</a>
                <a href="profile.php">Profile</a>
            <?php else: ?>
                <a href="registration.php">Register</a>
                <a href="login.php">Login</a>
            <?php endif; ?>
            <?php if (empty($_SESSION['admin_id']) && empty($_SESSION['intern_email'])): ?>
                <a href="admin_login.php" style="color: #ff6b6b;">Admin</a>
            <?php endif; ?>
        </nav>
        <form class="nav-search" method="get" action="courses.php">
            <input name="q" type="search" placeholder="Search courses..." aria-label="Search courses">
            <button type="submit">Search</button>
        </form>
    </div>
</header>
<main class="container">