<?php
/*
 * functions.php
 * -------------
 * Small helpers that we kept needing on more than one page,
 * so we put them all in one file and include it everywhere.
 */

// Start the session only if it has not been started yet.
// (Calling session_start() twice gives a PHP notice.)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Is somebody logged in right now?
 */
function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

/**
 * Put this at the top of any page that should only be visible
 * to a logged-in user. If nobody is logged in we send them to
 * the login page and stop the rest of the page from running.
 */
function require_login()
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;   // important! without exit the page below would still run
    }
}

/**
 * Read a text value out of $_GET.
 *
 * Why not just use $_GET['search'] directly? Because a URL like
 *   view_students.php?search[]=hello
 * makes PHP hand us an ARRAY instead of a string, and passing an array
 * to trim() or strtolower() is a fatal error in PHP 8. So we check the
 * type first and fall back to the default if it is not a string.
 */
function get_text($key, $default = '')
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/**
 * The same thing for $_POST.
 */
function post_text($key, $default = '')
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

/**
 * Escape text before printing it inside HTML.
 * This is what protects us from XSS: if a student's name was
 * saved as <script>alert(1)</script> we want to SHOW that text,
 * not run it.
 */
function e($text)
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Save a one-time message (like "Student added!") in the session
 * so it survives the redirect to the next page.
 */
function set_flash($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Print the flash message (if there is one) as a Bootstrap alert
 * and then delete it, so it only ever shows once.
 */
function show_flash()
{
    if (!isset($_SESSION['flash'])) {
        return;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    echo '<div class="alert alert-' . e($flash['type']) . ' alert-dismissible fade show" role="alert">'
       . e($flash['message'])
       . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
       . '</div>';
}

/**
 * Validate the student form fields.
 * Returns an array of errors: field name => error message.
 * An empty array means everything was fine.
 *
 * This same function is used by:
 *   - validate_student.php  (the AJAX validation)
 *   - add_student.php       (the real save)
 *   - update_student.php    (the real save)
 * so the rules can never drift apart between client and server.
 *
 * $pdo and $ignore_id are only needed for the duplicate-email check.
 * $ignore_id is the id of the student we are editing, so a student
 * does not clash with their own email address.
 */
function validate_student($data, $pdo = null, $ignore_id = null)
{
    $errors = [];

    // --- Name ---
    $name = trim($data['name'] ?? '');
    if ($name === '') {
        $errors['name'] = 'Please enter the student name.';
    } elseif (mb_strlen($name) < 3) {
        $errors['name'] = 'Name must be at least 3 characters.';
    } elseif (mb_strlen($name) > 100) {
        $errors['name'] = 'Name cannot be longer than 100 characters.';
    }

    // --- Email ---
    $email = trim($data['email'] ?? '');
    if ($email === '') {
        $errors['email'] = 'Please enter an email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That does not look like a valid email address.';
    } elseif ($pdo !== null) {
        // Check the email is not already used by a DIFFERENT student
        $sql = 'SELECT id FROM students WHERE email = ?';
        $params = [$email];

        if ($ignore_id !== null) {
            $sql .= ' AND id != ?';
            $params[] = $ignore_id;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($stmt->fetch()) {
            $errors['email'] = 'A student with this email already exists.';
        }
    }

    // --- Phone ---
    // Digits only, 7 to 15 of them. We allow spaces and + while
    // typing but strip them before checking.
    $phone = trim($data['phone'] ?? '');
    $phone_digits = preg_replace('/[\s\-\+\(\)]/', '', $phone);
    if ($phone === '') {
        $errors['phone'] = 'Please enter a phone number.';
    } elseif (!preg_match('/^[0-9]{7,15}$/', $phone_digits)) {
        $errors['phone'] = 'Phone must be 7 to 15 digits.';
    }

    // --- Address ---
    $address = trim($data['address'] ?? '');
    if ($address === '') {
        $errors['address'] = 'Please enter an address.';
    } elseif (mb_strlen($address) < 5) {
        $errors['address'] = 'Address seems too short.';
    }

    return $errors;
}

/**
 * Handle the profile picture upload.
 *
 * Returns an array: [ 'filename' => ..., 'error' => ... ]
 * - filename is null when the user did not pick a file at all
 *   (the picture is optional, so that is not an error)
 * - error is null when everything worked
 */
function handle_picture_upload($file)
{
    $result = ['filename' => null, 'error' => null];

    // UPLOAD_ERR_NO_FILE means the user simply left the field empty
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $result;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'Something went wrong while uploading the picture.';
        return $result;
    }

    // --- Size check: max 2 MB ---
    $max_bytes = 2 * 1024 * 1024;
    if ($file['size'] > $max_bytes) {
        $result['error'] = 'The picture must be smaller than 2 MB.';
        return $result;
    }

    // --- Type check ---
    // We do NOT trust $file['type'] because the browser sends it and
    // it can be faked. getimagesize() actually looks inside the file,
    // so a .php file renamed to .jpg will be rejected here.
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        $result['error'] = 'That file is not a real image.';
        return $result;
    }

    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];

    if (!isset($allowed[$info[2]])) {
        $result['error'] = 'Only JPG, PNG, GIF or WEBP images are allowed.';
        return $result;
    }

    $extension = $allowed[$info[2]];

    // --- Build a safe, unique file name ---
    // We never reuse the name the user sent. Two students uploading
    // "photo.jpg" would overwrite each other, and a crafted name could
    // try to escape the uploads folder.
    $filename = 'student_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;

    $upload_dir = __DIR__ . '/../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
        $result['error'] = 'Could not save the picture on the server.';
        return $result;
    }

    $result['filename'] = $filename;
    return $result;
}

/**
 * Delete a profile picture from the uploads folder.
 * Used when a student is deleted, or when their picture is replaced.
 */
function delete_picture($filename)
{
    if (empty($filename)) {
        return;
    }

    // basename() makes sure a value like "../includes/db.php" that
    // somehow got into the database cannot delete another file.
    $path = __DIR__ . '/../uploads/' . basename($filename);

    if (is_file($path)) {
        unlink($path);
    }
}
