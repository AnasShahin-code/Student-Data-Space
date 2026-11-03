<?php
/*
 * header.php
 * ----------
 * The top of every page: the <head>, the Bootstrap links and the
 * navigation bar. Each page sets $page_title before including this.
 *
 * Doing it this way means the navbar only exists in ONE file, so
 * adding a new menu link changes every page at once.
 */

require_once __DIR__ . '/functions.php';

$page_title = $page_title ?? 'Student Registration System';

// Used to highlight the link of the page we are currently on
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- This meta tag is what makes Bootstrap's responsive grid work on phones -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?> | Student Registration System</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (for the small icons on the buttons) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Our own styles - loaded last so it can override Bootstrap -->
    <link href="css/style.css" rel="stylesheet">
</head>
<body>

<!-- ===== Navigation bar ===== -->
<!-- navbar-expand-lg = collapse into a hamburger menu below 992px -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">

        <a class="navbar-brand fw-bold" href="index.php">
            <i class="bi bi-mortarboard-fill me-1"></i>
            Student Registration
        </a>

        <!-- The hamburger button, only visible on small screens -->
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page === 'index.php' ? 'active' : '' ?>"
                       href="index.php">Home</a>
                </li>

                <?php if (is_logged_in()): ?>
                    <!-- These two links only make sense when logged in -->
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'view_students.php' ? 'active' : '' ?>"
                           href="view_students.php">View Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'add_student.php' ? 'active' : '' ?>"
                           href="add_student.php">Add Student</a>
                    </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav">
                <?php if (is_logged_in()): ?>
                    <li class="nav-item">
                        <span class="nav-link text-white-50">
                            <i class="bi bi-person-circle me-1"></i>
                            <?= e($_SESSION['username']) ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right me-1"></i>Logout
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'login.php' ? 'active' : '' ?>"
                           href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'register.php' ? 'active' : '' ?>"
                           href="register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- ===== Page content starts here ===== -->
<main class="container py-4">
    <?php show_flash(); ?>
