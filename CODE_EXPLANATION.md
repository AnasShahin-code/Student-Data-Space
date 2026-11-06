# Code Explanation

This document explains how each feature of my Student Registration System works. It goes through the code step by step and gives the reason behind each choice. The setup steps are in [README.md](README.md).

---

## Contents

1. [Database connection](#1-database-connection)
2. [User registration and password hashing](#2-user-registration-and-password-hashing)
3. [Login and sessions](#3-login-and-sessions)
4. [Protecting pages](#4-protecting-pages)
5. [Adding a student](#5-adding-a-student)
6. [AJAX form validation](#6-ajax-form-validation)
7. [File uploads](#7-file-uploads)
8. [Search](#8-search)
9. [Pagination](#9-pagination)
10. [Sorting](#10-sorting)
11. [Updating a student](#11-updating-a-student)
12. [Deleting a student](#12-deleting-a-student)
13. [Responsive design](#13-responsive-design)
14. [Preventing SQL injection and XSS](#14-preventing-sql-injection-and-xss)

---

## 1. Database connection

**File:** `includes/db.php`

Every page that needs the database starts with `require_once 'includes/db.php'`. This file creates a `$pdo` object, and the pages use it to run their queries.

```php
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);
```

Here is what each option does and why I used it:

- **`ERRMODE_EXCEPTION`**: When a query is wrong, PHP throws an exception. Without this option, PHP just returns `false` and says nothing, so a small typo in the SQL gives a blank page with no hint about the problem.
- **`FETCH_ASSOC`**: Each row is returned with column names, so I can write `$row['name']` instead of `$row[0]`. This is much easier to read.
- **`EMULATE_PREPARES => false`**: By default, PDO can pretend to use prepared statements by escaping the values itself. When I turn this off, MySQL runs real prepared statements, so the SQL and the data stay separate.
- **`charset=utf8mb4`**: This makes sure Arabic names and emojis are saved correctly. Without it they can turn into `?????`.

The connection code is inside a `try / catch` block. If the connection fails, the `catch` shows a simple friendly message. It does not print `$e->getMessage()`, because that message could show the database name and login details to anyone who opens the page.

---

## 2. User registration and password hashing

**File:** `register.php`

The registration form sends its data back to the same page. Each field is checked, and the problems are stored in an `$errors` array where the key is the field name and the value is the message.

```php
if ($username === '') {
    $errors['username'] = 'Please choose a username.';
} elseif (strlen($username) < 3) {
    $errors['username'] = 'Username must be at least 3 characters.';
} elseif (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
    $errors['username'] = 'Username can only contain letters, numbers and underscores.';
}
```

After that, one query checks if the username or the email is already used:

```php
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
```

I used `strcasecmp()` because it ignores uppercase and lowercase. This way `Ahmed` and `ahmed` count as the same username.

If there are no errors, the password is hashed and the new user is saved:

```php
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
$stmt->execute([$username, $email, $hash]);
```

**What `password_hash()` does.** It uses the bcrypt algorithm with a random salt, and it returns a 60-character string like this one:

```
$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy
```

The `$2y$` part shows which algorithm was used, and `10$` is the cost. The salt is stored inside the same string, so I do not need a separate column for it. The function is also slow on purpose (it takes a few hundredths of a second). This is not a problem for one user logging in, but it makes it very expensive for an attacker to try millions of guesses if the database is ever stolen.

Hashing is **one-way**. There is no function to turn a hash back into the password. Also, hashing the same password twice gives two different results, because the salt is random each time. This is the reason we need `password_verify()` when the user logs in.

I used `PASSWORD_DEFAULT` instead of `PASSWORD_BCRYPT`. With this choice, if a newer PHP version adds a stronger algorithm, new accounts will use it automatically and I do not need to change the code. This is also why the `password` column is `VARCHAR(255)` and not `VARCHAR(60)`, since a stronger hash may be longer.

After a successful registration, the user is redirected to `login.php` with a flash message. The redirect is important. Without it, refreshing the page would send the form again.

---

## 3. Login and sessions

**File:** `login.php`

The user can log in with either a username or an email, and one query handles both:

```php
$stmt = $pdo->prepare(
    'SELECT id, username, email, password FROM users
     WHERE username = ? OR email = ?
     LIMIT 1'
);
$stmt->execute([$login, $login]);
$user = $stmt->fetch();
```

The same `$login` value is given to both placeholders.

Next, the password is checked:

```php
if ($user && password_verify($password, $user['password'])) {
    session_regenerate_id(true);

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email']    = $user['email'];

    header('Location: index.php');
    exit;
}
```

**How `password_verify()` works.** It reads the algorithm, the cost and the salt from the saved hash. Then it hashes the password the user typed in the same way and compares the two results. The comparison is timing-safe, which means an attacker cannot learn anything by measuring how long the check takes.

**Why I used `session_regenerate_id(true)`.** Without it, the session ID the visitor had *before* logging in would still be valid *after* logging in. An attacker who managed to set that ID in the victim's browser could then use the logged-in session. By creating a new ID at the moment of login, this attack no longer works. The `true` argument also deletes the old session file.

The error message is kept general on purpose:

```php
$error = 'Wrong username/email or password. Please try again.';
```

If the page said "this username does not exist", an attacker could use the login form to find out which accounts are registered.

### Logout

**File:** `logout.php`

Logout needs three steps, and all of them are necessary:

```php
$_SESSION = [];                 // 1. clear the data

if (ini_get('session.use_cookies')) {   // 2. delete the cookie
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], ...);
}

session_destroy();              // 3. delete the session on the server
```

If I only do step 1, the session is empty but still valid. If I only do step 3, the browser still has a cookie that points to a session that no longer exists. Doing all three really logs the user out. Setting the cookie's expiry to `time() - 42000` (a time in the past) tells the browser to remove it.

---

## 4. Protecting pages

**File:** `includes/functions.php`

```php
function require_login()
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}
```

The `exit` is very important. The `header()` function only adds a redirect to the response, and it does **not** stop the script. If I forget `exit`, PHP keeps running and sends the whole protected page after the redirect header. Anyone looking at the raw response could then read it.

Every protected page calls this function at the top, before any output:

```php
require_once 'includes/functions.php';
require_once 'includes/db.php';

require_login();
```

`validate_student.php` also does this check. An AJAX file is just a normal URL that anyone can send requests to. If it were not protected, a stranger could keep sending emails to it and use the "already exists" answer to find out which students are in the database.

---

## 5. Adding a student

**File:** `add_student.php`

```php
$errors = validate_student($old, $pdo);

$picture = ['filename' => null, 'error' => null];
if (empty($errors)) {
    $picture = handle_picture_upload($_FILES['profile_picture'] ?? null);
    if ($picture['error'] !== null) {
        $errors['profile_picture'] = $picture['error'];
    }
}

if (empty($errors)) {
    $stmt = $pdo->prepare(
        'INSERT INTO students (name, email, phone, address, profile_picture)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $old['name'], $old['email'], $old['phone'],
        $old['address'], $picture['filename'],
    ]);

    header('Location: view_students.php');
    exit;
}
```

There are three points to notice here:

- The picture is only processed **after** the text fields are valid. If the form is going to be rejected anyway, there is no reason to save a file on the server.
- The `$old` array keeps what the user typed. If the form is rejected, it comes back with the values still filled in instead of empty.
- The redirect after a successful insert follows the **POST / redirect / GET** pattern. If the page only showed a "saved" message, pressing F5 would send the form again and add the same student twice.

The `validate_student()` function is used in three places: the AJAX file, the add page and the update page. I did this on purpose. If I wrote the rules separately in each place, they could slowly become different, and a user might see "looks good" from the AJAX check and then get an error after pressing submit.

---

## 6. AJAX form validation

**Files:** `js/main.js` and `validate_student.php`

### The idea

AJAX lets the page ask the server a question **without reloading**. In this project the question is "are these values acceptable?", and the server answers in JSON format.

### Client side

`js/main.js` listens to every field. The important part is *when* it sends the request:

```js
let timer = null;
input.addEventListener('input', function () {
    clearTimeout(timer);

    const delay = input.classList.contains('is-invalid') ? 300 : 600;

    timer = setTimeout(function () {
        if (input.value.trim() !== '') {
            validateFields([field]);
        }
    }, delay);
});
```

This technique is called **debouncing**. Each key press cancels the old timer and starts a new one, so the request is only sent when the user stops typing for a moment. A 25-character email therefore sends **one** request and not 25. If a field already shows an error, the delay is shorter, so the red message disappears quickly after the user fixes the problem.

This is the request itself:

```js
const data = new FormData(studentForm);
data.set('fields', fields.join(','));

if (currentRequest) {
    currentRequest.abort();
}
const controller = new AbortController();
currentRequest = controller;

fetch('validate_student.php', {
    method: 'POST',
    body: data,
    signal: controller.signal
})
    .then(response => response.json())
    .then(result => applyResult(result, fields));
```

- `FormData(studentForm)` collects all the form fields in one line.
- `AbortController` cancels an old request that is still running when a new one starts. Without it, a slow old reply could arrive *after* a fast new one and show outdated messages. This problem is called a race condition.
- `fields` tells PHP which fields to report on. That way, fields the user has not reached yet do not all turn red at the same time.

To show the result, I used the validation classes that Bootstrap already has:

```js
if (result.errors && result.errors[field]) {
    input.classList.remove('is-valid');
    input.classList.add('is-invalid');
    feedback.textContent = result.errors[field];
} else {
    input.classList.remove('is-invalid');
    if (input.value.trim() !== '') {
        input.classList.add('is-valid');
    }
    feedback.textContent = '';
}
```

The class `is-invalid` gives the field a red border and shows the `.invalid-feedback` message under it. The class `is-valid` gives a green border. I did not need to write any custom CSS for this.

### Server side

`validate_student.php` sends back JSON instead of HTML:

```php
header('Content-Type: application/json; charset=utf-8');

$errors = validate_student($_POST, $pdo, $ignore_id);

$fields = post_text('fields');
if ($fields !== '') {
    $only = explode(',', $fields);
    $errors = array_intersect_key($errors, array_flip($only));
}

echo json_encode(['valid' => empty($errors), 'errors' => $errors]);
```

The function `array_flip()` changes `['name', 'email']` into `['name' => 0, 'email' => 1]`. After that, `array_intersect_key()` keeps only those keys from `$errors`. This is how the "only report these fields" filter works.

An example of a reply:

```json
{ "valid": false, "errors": { "email": "A student with this email already exists." } }
```

### On submit

When the user presses submit, the handler stops the form, checks all the fields together, and only lets the form go through if the server says it is valid:

```js
studentForm.addEventListener('submit', function (event) {

    if (studentForm.dataset.validated === 'true') {
        return;                 // second pass - let it go
    }

    event.preventDefault();
    // ... fetch, then:
    if (result.valid) {
        studentForm.dataset.validated = 'true';
        studentForm.submit();
    } else {
        const firstBad = studentForm.querySelector('.is-invalid');
        firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstBad.focus();
    }
});
```

The `dataset.validated` flag prevents an infinite loop. Without it, `studentForm.submit()` would call this same handler again.

If the AJAX request fails (for example, the server is down or there is no internet), the `catch` block submits the form in the normal way:

```js
.catch(function (error) {
    studentForm.dataset.validated = 'true';
    studentForm.submit();
});
```

This is safe because **PHP checks everything again anyway**. The AJAX part is only there to help the user, and it is not a security feature. Someone can turn off JavaScript, or send data directly to `add_student.php` with a tool like curl. For this reason, `add_student.php` runs the same `validate_student()` function before saving anything.

---

## 7. File uploads

**File:** `includes/functions.php`, function `handle_picture_upload()`

The form must use the correct encoding. Otherwise `$_FILES` will be empty:

```html
<form method="post" enctype="multipart/form-data">
```

### Step 1: Was a file sent?

```php
if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
    return $result;      // filename = null, error = null
}
```

The picture is optional, so having no file is normal and is not treated as an error.

### Step 2: Check the size

```php
$max_bytes = 2 * 1024 * 1024;         // 2 MB
if ($file['size'] > $max_bytes) {
    $result['error'] = 'The picture must be smaller than 2 MB.';
    return $result;
}
```

### Step 3: Check that it is really an image

This is the most important step:

```php
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
```

I followed two good habits here:

- **I do not trust `$file['type']`.** The browser sends this value, so an attacker can set it to anything. The function `getimagesize()` opens the file and reads its real header bytes. So if someone renames `shell.php` to `photo.jpg` and fakes the MIME type, the file is still rejected.
- **The extension comes from the detected type**, not from the name of the uploaded file. No matter what the file was called, it is saved with the extension that matches what it really is.

### Step 4: Create a new safe file name

```php
$filename = 'student_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
```

This gives a name like `student_1730000000_a3f91b2c.jpg`.

I never reuse the name the user gave. If two students both upload `photo.jpg`, one would overwrite the other. A name like `../../includes/db.php` could also try to leave the uploads folder. Using `time()` together with 4 random bytes makes a name collision almost impossible, even for two uploads in the same second.

### Step 5: Move the file

```php
$upload_dir = __DIR__ . '/../uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
    $result['error'] = 'Could not save the picture on the server.';
    return $result;
}
```

PHP saves each upload in a temporary folder and deletes it when the script ends, so the file must be moved if we want to keep it. I used `move_uploaded_file()` instead of `rename()` because it first checks that the file really came from an HTTP upload. It refuses to move any other file from the server.

`__DIR__` makes the path relative to `functions.php`, so the function works correctly no matter which page calls it.

### Step 6: Only the file name goes in the database

```php
$stmt->execute([..., $picture['filename']]);
```

The database saves `student_1730000000_a3f91b2c.jpg`, and not the image data or the full path. Saving images as BLOBs would make the database very large and slow down the queries. Saving a full path would break if the project is moved to another folder. The pages then simply write:

```html
<img src="uploads/<?= e($student['profile_picture']) ?>">
```

### Step 7: Clean up

The function `delete_picture()` removes the file when a student is deleted, or when their picture is replaced or removed:

```php
$path = __DIR__ . '/../uploads/' . basename($filename);
if (is_file($path)) {
    unlink($path);
}
```

`basename()` removes any folder part from the name. So even if a wrong value like `../includes/db.php` ended up in the database, the code could only delete files inside `uploads/`.

### Step 8: No PHP execution in the uploads folder

`uploads/.htaccess`:

```apache
php_flag engine off
<FilesMatch "\.(php|phtml|php3|php4|php5|phps|cgi|pl|py|asp|aspx|sh)$">
    Require all denied
</FilesMatch>
```

This is a second layer of protection. Even if a dangerous file somehow got into the folder, Apache would refuse to run it.

### Client-side preview

`js/main.js` uses `FileReader` to show the picture before it is uploaded:

```js
const reader = new FileReader();
reader.onload = function (event) {
    previewImg.src = event.target.result;      // a base64 data: URL
    previewWrapper.classList.remove('d-none');
};
reader.readAsDataURL(file);
```

The function `readAsDataURL()` reads the chosen file and returns a string like `data:image/jpeg;base64,...`, which can be used directly as the `src` of an image. Nothing is sent to the server at this point, so the preview happens completely in the browser.

---

## 8. Search

**File:** `view_students.php`

```php
$search = get_text('search');

$where  = '';
$params = [];

if ($search !== '') {
    $where = 'WHERE name LIKE ? OR email LIKE ? OR phone LIKE ?';
    $like  = '%' . $search . '%';
    $params = [$like, $like, $like];
}
```

`LIKE '%ahmed%'` means "contains ahmed anywhere in the text". The three `OR` conditions let one search box look in three columns. For example, typing `0101` finds students by phone number, and typing `gmail` finds them by email.

**The `%` signs are added to the value, not to the SQL.** The query text only has normal `?` placeholders, so the search text is still a bound parameter and cannot change the query. Writing `"WHERE name LIKE '%$search%'"` would create an SQL injection hole.

The variables `$where` and `$params` are used again in both the COUNT query and the SELECT query. This way, the two queries always search for the same thing.

The search form uses `method="get"`:

```html
<form method="get" action="view_students.php">
    <input type="hidden" name="sort" value="...">
    <input type="hidden" name="dir"  value="...">
    <input type="text" name="search" id="searchInput">
```

With GET, the search goes into the URL (`?search=ahmed`). This means the result can be bookmarked or shared, and the back button works as expected. The hidden fields keep the current sorting, so searching does not reset it.

JavaScript sends the form a short time after the user stops typing:

```js
let searchTimer = null;
searchInput.addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () {
        searchInput.form.submit();
    }, 700);
});
```

This is debouncing again, so the page does not reload in the middle of a word. The **Search** button is still there, and it works even when JavaScript is turned off.

---

## 9. Pagination

**File:** `view_students.php`

Pagination starts with two values:

```php
$per_page = 10;
$page     = max(1, (int) get_text('page', '1'));
```

The `(int)` cast changes `"abc"` into `0`, and `max(1, ...)` raises any number below 1 up to 1. So both `?page=-5` and `?page=abc` open page 1. Without this, a negative `OFFSET` would cause a MySQL error.

`get_text()` is a small helper in `functions.php`. Normally reading `$_GET['page']` directly would be fine. However, a URL like `?page[]=1` makes PHP return an **array**, and giving an array to `trim()` or `strtolower()` causes a fatal error in PHP 8. The helper checks the type first and uses the default value if it is not a string:

```php
function get_text($key, $default = '')
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}
```

There is also a similar `post_text()` function for `$_POST`. I did not use it for passwords, because trimming a password would change what the user typed. A space at the end of a password is a valid character.

### Count first

```php
$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM students $where");
$count_stmt->execute($params);
$total_rows = (int) $count_stmt->fetchColumn();

$total_pages = max(1, (int) ceil($total_rows / $per_page));

if ($page > $total_pages) {
    $page = $total_pages;
}
```

I need the total before getting the rows. Without it, I cannot know how many pages there are or when the **Next** button should be disabled. The `ceil()` function rounds up, so 25 students with 10 per page gives 2.5, which becomes 3 pages. The count uses the **same** `$where` and `$params` as the search. Because of this, searching for one name correctly shows 1 page and not the number of pages for the whole table.

Setting `$page` down to `$total_pages` means that `?page=99` shows the last page and not an empty table.

### Then get the rows for the page

```php
$offset = ($page - 1) * $per_page;

$sql = "SELECT id, name, email, phone, address, profile_picture, created_at
        FROM students
        $where
        ORDER BY $sort_column $direction
        LIMIT $per_page OFFSET $offset";
```

`LIMIT` is the number of rows to return, and `OFFSET` is the number of rows to skip:

| Page | OFFSET | Rows shown |
|------|--------|-----------|
| 1 | 0 | 1–10 |
| 2 | 10 | 11–20 |
| 3 | 20 | 21–30 |

Because of this, only 10 rows are sent from MySQL to PHP. Getting all 5,000 students and cutting the list in PHP would work fine with 12 rows, but it would be very slow with 5,000. So I let the database do that work.

I put `$per_page` and `$offset` directly into the query instead of binding them. This is because MySQL does not accept a placeholder in `LIMIT` when `EMULATE_PREPARES` is off. It is still safe because both values are integers created by my own code. `$offset` comes from a calculation with the `(int)` page number, and `$per_page` is a fixed number. No string from the URL reaches them.

### The page buttons

I made two small helper functions to keep the HTML clean. The first one, `page_url()`, builds the query string again with some values changed:

```php
function page_url($overrides = [])
{
    $query = array_merge([
        'search' => get_text('search'),
        'sort'   => get_text('sort', 'created_at'),
        'dir'    => get_text('dir', 'desc'),
        'page'   => max(1, (int) get_text('page', '1')),
    ], $overrides);

    $query = array_filter($query, function ($v) { return $v !== '' && $v !== null; });

    return 'view_students.php?' . http_build_query($query);
}
```

In `array_merge()`, the values in `$overrides` replace the old ones. So `page_url(['page' => 3])` keeps the current search and sorting but changes the page. This is why clicking "Next" does not forget the search.

The Previous and Next buttons get Bootstrap's `disabled` class at the first and last page:

```php
<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
    <a class="page-link" href="<?= e(page_url(['page' => $page - 1])) ?>">Previous</a>
</li>
```

The numbered buttons only show a small range around the current page:

```php
<?php for ($i = 1; $i <= $total_pages; $i++): ?>
    <?php if ($i == 1 || $i == $total_pages || abs($i - $page) <= 2): ?>
        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= e(page_url(['page' => $i])) ?>"><?= $i ?></a>
        </li>
    <?php elseif (abs($i - $page) === 3): ?>
        <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
    <?php endif; ?>
<?php endfor; ?>
```

The first and last pages are always shown, together with two pages on each side of the current one, and `…` fills the gaps. With 50 pages, this gives about 7 buttons instead of 50.

---

## 10. Sorting

**File:** `view_students.php`

A column name **cannot** be bound with a `?` placeholder. If I wrote `ORDER BY ?`, MySQL would sort by a text value and not by a column. So I used a whitelist, which is a list of the only values that are allowed:

```php
$sortable = [
    'name'       => 'name',
    'email'      => 'email',
    'phone'      => 'phone',
    'created_at' => 'created_at',
    'id'         => 'id',
];

$sort_key = get_text('sort', 'created_at');

if (!isset($sortable[$sort_key])) {
    $sort_key = 'created_at';          // anything unexpected falls back
}
$sort_column = $sortable[$sort_key];   // guaranteed to be one of our five
```

The value from the URL is only used as an **array key** and never as SQL. If it is not in the list, the code uses `created_at` instead. So `?sort=password` or `?sort=name;DROP TABLE students` cannot reach the query. Only one of the five exact strings can be used.

The sort direction is handled in the same way, and it only has two possible results:

```php
$direction = (strtolower(get_text('dir', 'desc')) === 'asc') ? 'ASC' : 'DESC';
```

The function `sort_header()` draws each clickable column heading:

```php
function sort_header($label, $key, $current_key, $direction)
{
    $is_active = ($key === $current_key);
    $next_dir  = ($is_active && $direction === 'ASC') ? 'desc' : 'asc';

    $icon = '<i class="bi bi-arrow-down-up text-muted small ms-1"></i>';
    if ($is_active) {
        $icon = $direction === 'ASC'
            ? '<i class="bi bi-sort-up ms-1"></i>'
            : '<i class="bi bi-sort-down ms-1"></i>';
    }

    $url = page_url(['sort' => $key, 'dir' => $next_dir, 'page' => 1]);
    echo '<a href="' . e($url) . '">' . e($label) . $icon . '</a>';
}
```

If the user clicks the column that is already used for sorting, the direction changes. If they click a different column, it starts with ascending order. The icon shows which column is active and in which direction.

I set `'page' => 1` because after changing the order, "page 5 of the old order" has no meaning. So sorting again always goes back to the first page.

---

## 11. Updating a student

**File:** `update_student.php`

This one file does two jobs:

```php
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
```

A `GET` request with `?id=5` shows the form, and a `POST` request saves it. On the first visit the `id` comes from `$_GET`, and when the form is submitted it comes from a hidden field. This keeps the `id` available even if the validation fails and the form is shown again.

Next, the record is loaded and the code checks that it exists:

```php
$stmt = $pdo->prepare('SELECT * FROM students WHERE id = ?');
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('danger', 'That student does not exist (they may have been deleted already).');
    header('Location: view_students.php');
    exit;
}
```

To fill the form, I put the values from the database into the `value` attributes:

```php
$old = [
    'name'    => $student['name'],
    'email'   => $student['email'],
    // ...
];
```

```html
<input type="text" name="name" value="<?= e($old['name']) ?>">
```

If a POST fails validation, `$old` is replaced with what the user typed. This way the form shows the user's changes and not the old database values.

### The `ignore_id` trick

```php
$errors = validate_student($old, $pdo, $id);
```

The third argument is the reason why saving a student without changing the email does not give an error. Inside `validate_student()` the code looks like this:

```php
$sql = 'SELECT id FROM students WHERE email = ?';
$params = [$email];

if ($ignore_id !== null) {
    $sql .= ' AND id != ?';
    $params[] = $ignore_id;
}
```

The duplicate check skips the row of the student being edited. Without `AND id != ?`, every edit would show "a student with this email already exists", and it would be pointing to the same student.

The form also sends the same value to the AJAX file as a hidden field. This keeps the live validation consistent with the server-side check:

```html
<input type="hidden" name="ignore_id" value="<?= (int) $id ?>" id="ignoreId">
```

### Picture: keep, replace or remove

```php
$picture_name   = $student['profile_picture'];     // keep by default
$remove_picture = isset($_POST['remove_picture']);

$upload = handle_picture_upload($_FILES['profile_picture'] ?? null);

if ($upload['error'] !== null) {
    $errors['profile_picture'] = $upload['error'];

} elseif ($upload['filename'] !== null) {
    delete_picture($student['profile_picture']);   // replace
    $picture_name = $upload['filename'];

} elseif ($remove_picture) {
    delete_picture($student['profile_picture']);   // remove
    $picture_name = null;
}
```

One form can lead to three results. The default is to keep the current picture, so if the user only wanted to fix a typo in the phone number, the picture is not lost. The old file is deleted when it is replaced or removed, so the `uploads/` folder does not fill up with unused files.

---

## 12. Deleting a student

**Files:** `view_students.php` (the button) and `delete_student.php`

### A form, not a link

```html
<form method="post" action="delete_student.php" class="js-delete-form d-inline">
    <input type="hidden" name="id" value="<?= (int) $student['id'] ?>">
    <input type="hidden" name="student_name" value="<?= e($student['name']) ?>">
    <button type="submit" class="btn btn-outline-danger">
        <i class="bi bi-trash"></i>
    </button>
</form>
```

A normal link like `<a href="delete_student.php?id=5">` would send a GET request, and a GET request should never change data. Browsers can load links in advance, search engine crawlers follow links, and a feature like "open all links in tabs" could delete the whole table. For this reason, delete uses POST, and the server also checks this:

```php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('danger', 'Students can only be deleted from the student list.');
    header('Location: view_students.php');
    exit;
}
```

### Confirmation

```js
document.querySelectorAll('.js-delete-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        const nameField = form.querySelector('[name="student_name"]');
        const name = nameField ? nameField.value : 'this student';

        if (!window.confirm('Delete "' + name + '"? ...')) {
            event.preventDefault();
        }
    });
});
```

The `confirm()` function returns `false` when the user presses Cancel, and then `preventDefault()` stops the form from being sent. The hidden `student_name` field lets the message show the student's name and not just "are you sure?".

### Then delete

```php
$stmt = $pdo->prepare('SELECT name, profile_picture FROM students WHERE id = ?');
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('warning', 'That student was already deleted.');
    header('Location: view_students.php');
    exit;
}

$stmt = $pdo->prepare('DELETE FROM students WHERE id = ?');
$stmt->execute([$id]);

delete_picture($student['profile_picture']);
```

The row is read **before** it is deleted, for two reasons. The code needs the picture file name to delete the file, and it needs the student's name for the "X was deleted" message. After the row is gone, neither of them is available.

At the end, the user is sent back to the page they came from, with the same search and page number:

```php
$back = 'view_students.php';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $parts = parse_url($_SERVER['HTTP_REFERER']);
    if (!empty($parts['query'])) {
        $back .= '?' . $parts['query'];
    }
}
header('Location: ' . $back);
```

I only reuse the query string from the referrer and never the full URL. The `HTTP_REFERER` value comes from the browser, so it cannot be trusted as a place to redirect to.

---

## 13. Responsive design

**Files:** `includes/header.php`, every page, and `css/style.css`

### The viewport meta tag

```html
<meta name="viewport" content="width=device-width, initial-scale=1">
```

Without this tag, a phone acts like a desktop that is about 980px wide and zooms out, and Bootstrap cannot fix that. I think it is the most important line for mobile devices.

### The grid

Bootstrap's grid has 12 columns. A breakpoint prefix means "from this width and bigger":

```html
<div class="row g-3">
    <div class="col-md-6"> ... name ... </div>
    <div class="col-md-6"> ... email ... </div>
</div>
```

`col-md-6` means half of the width (6 out of 12) from 768px and up. On smaller screens it becomes full width. So the two fields are next to each other on a laptop and one under the other on a phone. The class `g-3` sets the space between them.

The cards on the homepage use a shorter way:

```html
<div class="row row-cols-1 row-cols-md-3 g-4">
```

This gives one card per row on phones and three cards per row from medium screens up.

The forms are also centred and made narrower on big screens:

```html
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-5">
```

The form is full width on a phone, 8/12 on a tablet and 5/12 on a desktop. A login box stretched across a 27-inch monitor would look strange.

### Hiding columns on small screens

The students table has seven columns, which is too many for a phone:

```html
<th class="d-none d-lg-table-cell">Address</th>
<th class="d-none d-md-table-cell">Added</th>
```

`d-none` hides the column on all screens, and then `d-lg-table-cell` shows it again from 992px and up. The Address column disappears first because it is the longest, and then the "Added" column. The photo, name, email, phone and action buttons always stay.

The table is also inside a wrapper, so it scrolls sideways and does not break the page:

```html
<div class="table-responsive">
```

### Collapsing navbar

```html
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#mainNav">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
```

`navbar-expand-lg` means the menu is horizontal from 992px and up, and below that it collapses into the hamburger button. The `data-bs-toggle="collapse"` attribute is handled by Bootstrap's own JavaScript, which is why the bundle is included in `footer.php`.

### Buttons that stack

```html
<div class="d-flex flex-column flex-sm-row gap-2 mt-4">
    <button type="submit" class="btn btn-primary">Save Student</button>
    <a href="view_students.php" class="btn btn-outline-secondary">Cancel</a>
</div>
```

On a narrow phone the buttons are stacked and full width, which makes them easy to tap. From 576px and up they sit side by side.

### My own media query

In `css/style.css` I added one thing that Bootstrap did not cover. On a narrow phone, the page numbers went outside the edge of the screen:

```css
@media (max-width: 575.98px) {
    .page-link {
        padding: 0.3rem 0.55rem;
        font-size: 0.85rem;
    }

    .hero h1 {
        font-size: 1.8rem;
    }
}
```

I used `575.98px` and not `576px` because Bootstrap does the same. It stops just before the next breakpoint, so the two rules never apply together.

---

## 14. Preventing SQL injection and XSS

### SQL injection

Every value that goes into a query is a bound parameter:

```php
$stmt = $pdo->prepare('SELECT * FROM students WHERE email = ?');
$stmt->execute([$email]);
```

The SQL text and the data are sent to MySQL **separately**. MySQL first reads the query and sees a placeholder where the value goes. Then it puts the data in as a value, and it never reads that data as SQL. So if someone types this in the email box:

```
' OR 1=1 --
```

MySQL looks for a student whose email is exactly `' OR 1=1 --`. It finds nobody and moves on.

This is the version that should be avoided, where the string is joined together:

```php
// NEVER do this
$pdo->query("SELECT * FROM students WHERE email = '$email'");
```

In this case the typed text becomes part of the query and can change what it does.

However, placeholders only work for **values**. Column names and `LIMIT` need a different solution, and both are used in `view_students.php`:

- **Column names** (`ORDER BY`): I used a whitelist. The URL value is used as an array key in a list written in the code, so only five exact strings can reach the SQL. ([section 10](#10-sorting))
- **`LIMIT` / `OFFSET`**: These are integers calculated by the code from a page number that was changed with `(int)`. Nothing from the URL arrives as a string. ([section 9](#9-pagination))
- **`LIKE` wildcards**: The `%` characters are added to the *value* and not to the query text. ([section 8](#8-search))

### XSS

Everything that is printed into HTML goes through one helper function:

```php
function e($text)
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}
```

I use it everywhere, for example:

```html
<td class="fw-semibold"><?= e($student['name']) ?></td>
```

The function `htmlspecialchars()` changes `<` to `&lt;`, `>` to `&gt;`, `"` to `&quot;` and `&` to `&amp;`. So if a student is saved with this name:

```html
<script>alert('hacked')</script>
```

the browser receives:

```html
&lt;script&gt;alert(&#039;hacked&#039;)&lt;/script&gt;
```

The browser **shows** this as text and does not run it. Without escaping, the script would run in the browser of every user who opens the list. This attack is called stored XSS.

I used `ENT_QUOTES` so single quotes are also escaped, which is important inside attributes like `value='...'`. The function is short on purpose. A one-letter name is easy to use everywhere, and being consistent is the main point, because one unescaped echo is enough to open the hole again.
