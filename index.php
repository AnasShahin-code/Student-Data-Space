<?php
/*
 * index.php - Homepage
 *
 * The assignment sheet calls this index.html, but we need PHP here
 * to check whether the user is logged in (so we can show "Add Student"
 * instead of "Register"), and to read the student count from the
 * database. A .html file cannot do either.
 */

require_once 'includes/functions.php';

$page_title = 'Home';

// Show a couple of numbers on the homepage. We only bother asking the
// database when someone is actually logged in.
$student_count = null;
if (is_logged_in()) {
    require_once 'includes/db.php';
    $student_count = (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
}

include 'includes/header.php';
?>

<!-- ===== Welcome banner ===== -->
<div class="p-4 p-md-5 mb-4 rounded-3 hero text-white">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <h1 class="display-5 fw-bold">Student Registration System</h1>
            <p class="lead mb-0">
                A simple place to keep your student records: add new students,
                search through them, update their details and remove the ones
                you no longer need.
            </p>
        </div>
        <div class="col-lg-4 text-center d-none d-lg-block">
            <i class="bi bi-mortarboard-fill hero-icon"></i>
        </div>
    </div>
</div>

<?php if (is_logged_in()): ?>

    <!-- ===== Logged in: show the stats and the shortcuts ===== -->
    <div class="alert alert-success">
        <i class="bi bi-check-circle-fill me-1"></i>
        Welcome back, <strong><?= e($_SESSION['username']) ?></strong>!
        There <?= $student_count === 1 ? 'is' : 'are' ?>
        <strong><?= $student_count ?></strong>
        student<?= $student_count === 1 ? '' : 's' ?> in the system right now.
    </div>

    <!-- row-cols-* is Bootstrap's grid: 1 card per row on phones,
         3 per row from medium screens upwards -->
    <div class="row row-cols-1 row-cols-md-3 g-4">
        <div class="col">
            <div class="card h-100 text-center shadow-sm">
                <div class="card-body">
                    <i class="bi bi-person-plus-fill card-icon text-primary"></i>
                    <h5 class="card-title mt-3">Add a Student</h5>
                    <p class="card-text text-muted">
                        Fill in the student's details and optionally upload a
                        profile picture.
                    </p>
                    <a href="add_student.php" class="btn btn-primary">Add Student</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 text-center shadow-sm">
                <div class="card-body">
                    <i class="bi bi-people-fill card-icon text-success"></i>
                    <h5 class="card-title mt-3">View Students</h5>
                    <p class="card-text text-muted">
                        Browse every record with searching, sorting and
                        10 students per page.
                    </p>
                    <a href="view_students.php" class="btn btn-success">View Students</a>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card h-100 text-center shadow-sm">
                <div class="card-body">
                    <i class="bi bi-search card-icon text-info"></i>
                    <h5 class="card-title mt-3">Search</h5>
                    <p class="card-text text-muted">
                        Look a student up by their name, email address or
                        phone number.
                    </p>
                    <a href="view_students.php#searchInput" class="btn btn-info">Search Records</a>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>

    <!-- ===== Not logged in: ask them to log in or register ===== -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-box-arrow-in-right card-icon text-primary"></i>
                    <h5 class="card-title mt-3">Already have an account?</h5>
                    <p class="card-text text-muted">
                        Log in with your username or email address to manage
                        the student records.
                    </p>
                    <a href="login.php" class="btn btn-primary">Login</a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-person-plus card-icon text-success"></i>
                    <h5 class="card-title mt-3">New here?</h5>
                    <p class="card-text text-muted">
                        Create an account in a few seconds. Your password is
                        stored as a secure hash, never as plain text.
                    </p>
                    <a href="register.php" class="btn btn-success">Register</a>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-secondary mt-4 mb-0">
        <i class="bi bi-info-circle-fill me-1"></i>
        You need to be logged in before you can add, view, edit or delete
        student records.
    </div>

<?php endif; ?>

<?php include 'includes/footer.php'; ?>
