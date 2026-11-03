<?php
/*
 * login.php - Log an existing user in
 *
 * The login box accepts EITHER the username OR the email address,
 * so the SQL looks for both in one query.
 */

require_once 'includes/functions.php';
require_once 'includes/db.php';

$page_title = 'Login';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';                 // one general message for the whole form
$old_login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $login    = post_text('login');
    // Not trimmed - see the note in register.php
    $password = is_string($_POST['password'] ?? '') ? $_POST['password'] : '';
    $old_login = $login;

    if ($login === '' || $password === '') {
        $error = 'Please fill in both fields.';
    } else {

        $stmt = $pdo->prepare(
            'SELECT id, username, email, password FROM users
             WHERE username = ? OR email = ?
             LIMIT 1'
        );
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        // password_verify() takes the password the user typed and the
        // hash from the database and works out whether they match.
        // We can never turn the hash back into the password.
        if ($user && password_verify($password, $user['password'])) {

            // Stop session fixation: give the logged-in user a brand new
            // session id so an id that was known before login is useless.
            session_regenerate_id(true);

            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email']    = $user['email'];

            set_flash('success', 'You are logged in. Welcome, ' . $user['username'] . '!');
            header('Location: index.php');
            exit;
        }

        // Deliberately vague: we do not say WHICH part was wrong,
        // otherwise someone could use this page to find out which
        // usernames exist.
        $error = 'Wrong username/email or password. Please try again.';
    }
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-box-arrow-in-right me-2"></i>Login</h4>
            </div>
            <div class="card-body">

                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="login.php" novalidate>

                    <div class="mb-3">
                        <label for="login" class="form-label">Username or email</label>
                        <input type="text" class="form-control" id="login" name="login"
                               value="<?= e($old_login) ?>" autofocus>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password">
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Login
                    </button>
                </form>

                <hr>
                <p class="text-center mb-0 small">
                    No account yet? <a href="register.php">Register here</a>.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
