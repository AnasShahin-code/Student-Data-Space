<?php
/*
 * add_student.php - Add a new student (the C in CRUD)
 *
 * Two layers of validation:
 *   1. AJAX, while the user is typing  -> validate_student.php
 *   2. PHP, when the form is submitted -> right here
 *
 * The AJAX layer is only there to be helpful. The check below is the
 * one that actually protects the database, because anybody can turn
 * JavaScript off or post straight to this file.
 */

require_once 'includes/functions.php';
require_once 'includes/db.php';

require_login();                 // guests get sent to login.php

$page_title = 'Add Student';

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $old = [
        'name'    => post_text('name'),
        'email'   => post_text('email'),
        'phone'   => post_text('phone'),
        'address' => post_text('address'),
    ];

    // The same function the AJAX endpoint calls
    $errors = validate_student($old, $pdo);

    // The picture is optional, so it is handled separately
    $picture = ['filename' => null, 'error' => null];
    if (empty($errors)) {
        $picture = handle_picture_upload($_FILES['profile_picture'] ?? null);
        if ($picture['error'] !== null) {
            $errors['profile_picture'] = $picture['error'];
        }
    }

    if (empty($errors)) {

        // Prepared statement: the ? placeholders are sent to MySQL
        // separately from the values, so a value like
        //   Robert'); DROP TABLE students;--
        // is stored as a (strange) name and never run as SQL.
        $stmt = $pdo->prepare(
            'INSERT INTO students (name, email, phone, address, profile_picture)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $old['name'],
            $old['email'],
            $old['phone'],
            $old['address'],
            $picture['filename'],
        ]);

        set_flash('success', '"' . $old['name'] . '" was added to the student list.');

        // Redirect after a successful POST so that refreshing the next
        // page does not add the same student twice.
        header('Location: view_students.php');
        exit;
    }
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-person-plus-fill me-2"></i>Add a New Student</h4>
            </div>
            <div class="card-body">

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Please fix the errors highlighted below.
                    </div>
                <?php endif; ?>

                <!--
                    id="studentForm"            -> js/main.js hooks the AJAX validation onto this
                    enctype="multipart/form-data" -> required for the file upload to work
                    novalidate                  -> switch off the browser's own bubbles so
                                                   our messages are the only ones shown
                -->
                <form method="post" action="add_student.php"
                      enctype="multipart/form-data"
                      id="studentForm" novalidate>

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label for="name" class="form-label">
                                Full name <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
                                   id="name" name="name" value="<?= e($old['name']) ?>">
                            <div class="invalid-feedback"><?= e($errors['name'] ?? '') ?></div>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">
                                Email address <span class="text-danger">*</span>
                            </label>
                            <input type="email"
                                   class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                   id="email" name="email" value="<?= e($old['email']) ?>">
                            <div class="invalid-feedback"><?= e($errors['email'] ?? '') ?></div>
                        </div>

                        <div class="col-md-6">
                            <label for="phone" class="form-label">
                                Phone number <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                                   id="phone" name="phone" value="<?= e($old['phone']) ?>"
                                   placeholder="01012345678">
                            <div class="invalid-feedback"><?= e($errors['phone'] ?? '') ?></div>
                        </div>

                        <div class="col-md-6">
                            <label for="profile_picture" class="form-label">
                                Profile picture <span class="text-muted">(optional)</span>
                            </label>
                            <input type="file"
                                   class="form-control <?= isset($errors['profile_picture']) ? 'is-invalid' : '' ?>"
                                   id="profile_picture" name="profile_picture"
                                   accept="image/*">
                            <div class="invalid-feedback"><?= e($errors['profile_picture'] ?? '') ?></div>
                            <div class="form-text">JPG, PNG, GIF or WEBP. Maximum 2 MB.</div>
                        </div>

                        <!-- JavaScript shows the chosen image here before uploading -->
                        <div class="col-12 d-none" id="previewWrapper">
                            <label class="form-label">Preview</label><br>
                            <img src="" alt="Preview" id="picturePreview"
                                 class="img-thumbnail preview-img">
                        </div>

                        <div class="col-12">
                            <label for="address" class="form-label">
                                Address <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>"
                                      id="address" name="address" rows="3"><?= e($old['address']) ?></textarea>
                            <div class="invalid-feedback"><?= e($errors['address'] ?? '') ?></div>
                        </div>
                    </div>

                    <!-- d-flex + flex-column flex-sm-row: the buttons sit side by
                         side normally but stack on a narrow phone screen -->
                    <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="bi bi-save me-1"></i>Save Student
                        </button>
                        <a href="view_students.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
