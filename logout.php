<?php
/*
 * logout.php - End the session
 *
 * Three steps, and all three matter:
 *   1. empty the $_SESSION array
 *   2. delete the session cookie in the browser
 *   3. destroy the session file on the server
 */

require_once 'includes/functions.php';

// 1. Throw away everything we stored about this user
$_SESSION = [];

// 2. Tell the browser to delete the session cookie by re-sending it
//    with a date in the past.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 3. Destroy the session on the server side
session_destroy();

// Start a fresh session just to carry the goodbye message over
session_start();
set_flash('info', 'You have been logged out. See you next time!');

header('Location: login.php');
exit;
