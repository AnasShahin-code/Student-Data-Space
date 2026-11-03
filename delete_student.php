<?php
/*
 * delete_student.php - Remove a student (the D in CRUD)
 *
 * There is no HTML on this page. It deletes the row and then sends the
 * browser back to the list with a message.
 *
 * Two safety nets before anything is deleted:
 *   1. the request must be a POST (a GET link could be followed by a
 *      browser prefetch or a crawler all on its own)
 *   2. JavaScript shows a confirmation dialog first - see js/main.js
 */

require_once 'includes/functions.php';
require_once 'includes/db.php';

require_login();

// --- 1. Only accept POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('danger', 'Students can only be deleted from the student list.');
    header('Location: view_students.php');
    exit;
}

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', 'No student was selected for deletion.');
    header('Location: view_students.php');
    exit;
}

// --- 2. Read the row first ---
// We need the file name so we can delete the picture too, and the name
// so the confirmation message can say who was removed.
$stmt = $pdo->prepare('SELECT name, profile_picture FROM students WHERE id = ?');
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('warning', 'That student was already deleted.');
    header('Location: view_students.php');
    exit;
}

// --- 3. Delete the database row ---
$stmt = $pdo->prepare('DELETE FROM students WHERE id = ?');
$stmt->execute([$id]);

// --- 4. Delete the uploaded picture as well ---
// Without this the image would stay on the server forever with nothing
// pointing at it.
delete_picture($student['profile_picture']);

set_flash('success', '"' . $student['name'] . '" was deleted.');

// Send the user back to the page they came from, keeping their search
// and page number if we can work them out from the referring URL.
$back = 'view_students.php';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $parts = parse_url($_SERVER['HTTP_REFERER']);
    if (!empty($parts['query'])) {
        $back .= '?' . $parts['query'];
    }
}

header('Location: ' . $back);
exit;
