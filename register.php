<?php
/*
 * register.php - Create a new user account
 *
 * The form posts back to this same file. We check everything on the
 * server, and if there are no errors we hash the password with
 * password_hash() and insert the row.
 */

require_once 'includes/functions.php';
require_once 'includes/db.php';

$page_title = 'Register';

// Someone who is already logged in has no reason to be here
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$errors = [];
// Keep what the user typed so we can put it back in the form if
// something was wrong - nobody likes retyping everything.
$old = ['username' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // post_text() instead of plain $_POST so a crafted request that
    // sends an array instead of a string cannot crash the page
    $username = post_text('username');
    $email    = post_text('email');
    // Passwords are NOT trimmed - a space can be a real character in
    // a password, so removing it would silently change what the user typed
    $password = is_string($_POST['password'] ?? '') ? $_POST['password'] : '';
    $confirm  = is_string($_POST['confirm_password'] ?? '') ? $_POST['confirm_password'] : '';

    $old['username'] = $username;
    $old['email']    = $email;

    // --- Username ---
    if ($username === '') {
        $errors['username'] = 'Please choose a username.';
    } elseif (strlen($username) < 3) {
        $errors['username'] = 'Username must be at least 3 characters.';
    } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
        $errors['username'] = 'Username can only contain letters, numbers and underscores.';
    }

    // --- Email ---
    if ($email === '') {
        $errors['email'] = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That does not look like a valid email address.';
    }

    // --- Password ---
    if ($password === '') {
        $errors['password'] = 'Please choose a password.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    }

    // --- Password confirmation ---
    if ($confirm === '') {
        $errors['confirm_password'] = 'Please confirm your password.';
    } elseif ($password !== $confirm) {
        $errors['confirm_password'] = 'The two passwords do not match.';
    }

    // --- Is the username or email already taken? ---
    // Only worth asking the database if the fields themselves are valid.
    if (!isset($errors['username']) || !isset($errors['email'])) {
        $stmt = $pdo->prepare('SELECT username, email FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$username, $email]);

        foreach ($stmt->fetchAll() as $row) {
            if (strcasecmp($row['username'], $username) === 0) {
                $errors['username'] = 'That username is already taken.';
            }
            if (strcasecmp($row['email'], $email) === 0) {
                $errors['email'] = 'An account with this email already exists.';
            }
        }
    }

    // --- Everything was fine, so create the account ---
    if (empty($errors)) {

        // password_hash() adds a random salt itself, so we do not need
        // to store one. PASSWORD_DEFAULT is bcrypt today and will
        // automatically become something stronger in future PHP versions.
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, password) VALUES (?, ?, ?)'
        );
        $stmt->execute([$username, $email, $hash]);

        set_flash('success', 'Your account was created. You can log in now.');
        header('Location: login.php');
        exit;
    }
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <!-- col-md-8 col-lg-5: narrow on big screens, full width on phones -->
    <div class="col-md-8 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0"><i class="bi bi-person-plus me-2"></i>Create an Account</h4>
            </div>
            <div class="card-body">

                <form method="post" action="register.php" novalidate>

                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text"
                               class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>"
                               id="username" name="username"
                               value="<?= e($old['username']) ?>">
                        <?php if (isset($errors['username'])): ?>
                            <div class="invalid-feedback"><?= e($errors['username']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email"
                               class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                               id="email" name="email"
                               value="<?= e($old['email']) ?>">
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback"><?= e($errors['email']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password"
                               class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                               id="password" name="password">
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback"><?= e($errors['password']) ?></div>
                        <?php else: ?>
                            <div class="form-text">At least 6 characters.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm password</label>
                        <input type="password"
                               class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>"
                               id="confirm_password" name="confirm_password">
                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="invalid-feedback"><?= e($errors['confirm_password']) ?></div>
                        <?php endif; ?>
                        <!-- JavaScript writes a live "passwords match" message here -->
                        <div id="passwordMatchNote" class="form-text"></div>
                    </div>

                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-check-lg me-1"></i>Register
                    </button>
                </form>

                <hr>
                <p class="text-center mb-0 small">
                    Already registered? <a href="login.php">Log in here</a>.
                </p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
