<?php
/*
 * update_student.php - Edit an existing student (the U in CRUD)
 *
 * The page is used twice:
 *   GET  ?id=5  -> load student 5 and show the form filled in
 *   POST        -> check the new values and save them
 */

require_once 'includes/functions.php';
require_once 'includes/db.php';

require_login();

$page_title = 'Update Student';

// ------------------------------------------------------------------
// Which student are we editing?
// (int) turns "5" into 5 and anything odd like "abc" into 0.
// ------------------------------------------------------------------
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'No student was selected.');
    header('Location: view_students.php');
    exit;
}

// Load the current record
$stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('danger', 'That student does not exist (they may have been deleted already).');
    header('Location: view_students.php');
    exit;
}

$errors = [];

// What the form should show. On a GET this is the database row;
// on a failed POST it becomes what the user just typed.
$old = [
    'name'    => $student['name'],
    'email'   => $student['email'],
    'phone'   => $student['phone'],
    'address' => $student['address'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $old = [
        'name'    => post_text('name'),
        'email'   => post_text('email'),
        'phone'   => post_text('phone'),
        'address' => post_text('address'),
    ];

    // We pass $id as the third argument so the duplicate-email check
    // skips this student's own row. Otherwise saving without changing
    // the email would say "that email already exists".
    $errors = validate_student($old, $pdo, $id);

    // Start by assuming we keep the picture we already have
    $picture_name = $student['profile_picture'];
    $remove_picture = isset($_POST['remove_picture']);

    if (empty($errors)) {

        $upload = handle_picture_upload($_FILES['profile_picture'] ?? null);

        if ($upload['error'] !== null) {
            $errors['profile_picture'] = $upload['error'];

        } elseif ($upload['filename'] !== null) {
            // A new picture came in: use it and delete the old file so
            // the uploads folder does not fill up with orphans.
            delete_picture($student['profile_picture']);
            $picture_name = $upload['filename'];

        } elseif ($remove_picture) {
            // No new file, but the "remove" box was ticked
            delete_picture($student['profile_picture']);
            $picture_name = null;
        }
    }

    if (empty($errors)) {

        $stmt = $pdo->prepare(
            'UPDATE students
                SET name = ?, email = ?, phone = ?, address = ?, profile_picture = ?
              WHERE id = ?'
        );
        $stmt->execute([
            $old['name'],
            $old['email'],
            $old['phone'],
            $old['address'],
            $picture_name,
            $id,
        ]);

        set_flash('success', '"' . $old['name'] . '" was updated.');
        header('Location: view_students.php');
        exit;
    }

    // Validation failed, so re-read the picture name for the preview
    $student['profile_picture'] = $picture_name;
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-warning">
                <h4 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Update Student</h4>
            </div>
            <div class="card-body">

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Please fix the errors highlighted below.
                    </div>
                <?php endif; ?>

                <!-- Same id="studentForm" as the add page, so the AJAX
                     validation in js/main.js works here too without
                     writing it a second time. -->
                <form method="post" action="update_student.php"
                      enctype="multipart/form-data"
                      id="studentForm" novalidate>

                    <!-- Which row to update. Sent as a hidden field so it
                         survives even if the form fails validation. -->
                    <input type="hidden" name="id" value="<?= (int) $id ?>">
                    <!-- Tells validate_student.php to ignore this student's
                         own email address in the duplicate check -->
                    <input type="hidden" name="ignore_id" value="<?= (int) $id ?>" id="ignoreId">

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
                                   id="phone" name="phone" value="<?= e($old['phone']) ?>">
                            <div class="invalid-feedback"><?= e($errors['phone'] ?? '') ?></div>
                        </div>

                        <div class="col-md-6">
                            <label for="profile_picture" class="form-label">
                                Change profile picture <span class="text-muted">(optional)</span>
                            </label>
                            <input type="file"
                                   class="form-control <?= isset($errors['profile_picture']) ? 'is-invalid' : '' ?>"
                                   id="profile_picture" name="profile_picture" accept="image/*">
                            <div class="invalid-feedback"><?= e($errors['profile_picture'] ?? '') ?></div>
                            <div class="form-text">
                                Leave this empty to keep the current picture.
                            </div>
                        </div>

                        <!-- Current picture (if there is one) -->
                        <?php if (!empty($student['profile_picture'])): ?>
                            <div class="col-12">
                                <label class="form-label">Current picture</label><br>
                                <img src="uploads/<?= e($student['profile_picture']) ?>"
                                     alt="<?= e($old['name']) ?>"
                                     class="img-thumbnail preview-img mb-2">

                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                           id="remove_picture" name="remove_picture" value="1">
                                    <label class="form-check-label" for="remove_picture">
                                        Remove the current picture
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Preview of a newly chosen file -->
                        <div class="col-12 d-none" id="previewWrapper">
                            <label class="form-label">New picture preview</label><br>
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

                    <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                        <button type="submit" class="btn btn-warning" id="submitBtn">
                            <i class="bi bi-save me-1"></i>Save Changes
                        </button>
                        <a href="view_students.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
