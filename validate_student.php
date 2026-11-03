<?php
/*
 * validate_student.php - the AJAX validation endpoint
 *
 * js/main.js posts the form fields here while the user types.
 * This file does NOT return HTML - it returns JSON, like this:
 *
 *   { "valid": false, "errors": { "email": "That is not a valid email." } }
 *   { "valid": true,  "errors": {} }
 *
 * The JavaScript reads that and paints the red/green boxes.
 *
 * Note that it calls exactly the same validate_student() function that
 * add_student.php and update_student.php call. That is on purpose: if
 * we wrote the rules twice they would eventually disagree with each
 * other, and the user would see "looks good" and then get an error
 * after submitting.
 */

require_once 'includes/functions.php';
require_once 'includes/db.php';

// Tell the browser this is JSON, not a web page
header('Content-Type: application/json; charset=utf-8');

// Even an AJAX call has to be logged in - otherwise anyone could use
// this endpoint to find out which student emails are in the database.
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['valid' => false, 'errors' => ['_' => 'Your session has expired. Please log in again.']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);   // 405 = Method Not Allowed
    echo json_encode(['valid' => false, 'errors' => ['_' => 'POST requests only.']]);
    exit;
}

// When the user is EDITING a student, the form sends ignore_id so that
// their own email address does not count as a duplicate.
$ignore_id = isset($_POST['ignore_id']) && (int) $_POST['ignore_id'] > 0
    ? (int) $_POST['ignore_id']
    : null;

$errors = validate_student($_POST, $pdo, $ignore_id);

// The user may only have filled in one field so far. We do not want to
// shout about the empty ones they have not reached yet, so if the
// request names specific fields we only report on those.
$fields = post_text('fields');
if ($fields !== '') {
    $only = explode(',', $fields);
    $errors = array_intersect_key($errors, array_flip($only));
}

// (object) so that an empty list comes out as {} and not [].
// json_encode() turns an empty PHP array into [], because it cannot tell
// an empty list from an empty map - and our JavaScript expects a map.
echo json_encode([
    'valid'  => empty($errors),
    'errors' => (object) $errors,
]);
