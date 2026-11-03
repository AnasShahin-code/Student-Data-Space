# Code Explanation

How each feature of the Student Registration System actually works.
Setup instructions are in [README.md](README.md).

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

Every page that touches the database starts with
`require_once 'includes/db.php'`, which gives it a `$pdo` object.

```php
$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);
```

Why each option matters:

- **`ERRMODE_EXCEPTION`** – a broken query throws an exception instead of
  returning `false` silently. Without this, a typo in SQL produces a
  blank page and no clue why.
- **`FETCH_ASSOC`** – rows come back as `$row['name']` rather than
  `$row[0]`, which is far easier to read.
- **`EMULATE_PREPARES => false`** – PDO can *fake* prepared statements by
  escaping values itself. Turning emulation off means MySQL does the real
  thing, keeping the SQL and the data genuinely separate.
- **`charset=utf8mb4`** – so Arabic names and emoji are stored correctly
  rather than as `?????`.

The whole thing sits in a `try / catch`, and the `catch` prints a friendly
message rather than `$e->getMessage()`, which would leak the database
name and credentials onto the page.

---

## 2. User registration and password hashing

**File:** `register.php`

The form posts back to itself. Every field is checked, and `$errors` is an
array of `field name => message`:

```php
if ($username === '') {
    $errors['username'] = 'Please choose a username.';
} elseif (strlen($username) < 3) {
    $errors['username'] = 'Username must be at least 3 characters.';
} elseif (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
    $errors['username'] = 'Username can only contain letters, numbers and underscores.';
}
```

Then a single query checks whether the username or the email is taken:

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

`strcasecmp()` is used because the comparison should ignore case – `Ahmed`
and `ahmed` are the same username.

If `$errors` is empty, the password is hashed and the row inserted:

```php
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('INSERT INTO users (username, email, password) VALUES (?, ?, ?)');
$stmt->execute([$username, $email, $hash]);
```

**What `password_hash()` does.** It runs the password through bcrypt with a
randomly generated salt and produces a 60-character string like:

```
$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy
```

The `$2y$` says which algorithm, `10$` is the cost, and the salt is baked
into the string, so there is no separate salt column. The function is
deliberately slow (a few hundredths of a second), which makes brute-forcing
a stolen database expensive.

It is **one-way**: there is no `password_unhash()`. Hashing the same
password twice gives two different strings, because the salt is random
each time — which is exactly why `password_verify()` exists.

`PASSWORD_DEFAULT` rather than `PASSWORD_BCRYPT` means that when a future
PHP version ships a stronger algorithm, new accounts get it with no code
change. That is also why the `password` column is `VARCHAR(255)` and not
`VARCHAR(60)` — a stronger hash will be longer.

On success the user is redirected to `login.php` with a flash message. The
redirect matters: without it, refreshing the page would resubmit the form.

---

## 3. Login and sessions

**File:** `login.php`

One query handles "username **or** email":

```php
$stmt = $pdo->prepare(
    'SELECT id, username, email, password FROM users
     WHERE username = ? OR email = ?
     LIMIT 1'
);
$stmt->execute([$login, $login]);
$user = $stmt->fetch();
```

The same `$login` value is bound twice, once for each placeholder.

Then the password check:

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

**How `password_verify()` works.** It reads the algorithm, cost and salt
out of the stored hash, hashes the typed password the same way, and
compares the two. The comparison is timing-safe, so an attacker cannot
learn anything from how long the check took.

**Why `session_regenerate_id(true)`.** Without it, the session ID a visitor
had *before* logging in stays valid *after*. If an attacker could set that
ID in someone's browser beforehand, they would inherit the logged-in
session. Regenerating gives a brand new ID at the moment of login, and
`true` deletes the old session file.

The error message is deliberately vague:

```php
$error = 'Wrong username/email or password. Please try again.';
```

Saying "that username does not exist" would turn the login page into a
tool for discovering which accounts exist.

### Logout

**File:** `logout.php`

Three steps, all of them needed:

```php
$_SESSION = [];                 // 1. clear the data

if (ini_get('session.use_cookies')) {   // 2. delete the cookie
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], ...);
}

session_destroy();              // 3. delete the session on the server
```

Step 1 alone leaves an empty-but-valid session. Step 3 alone leaves the
cookie pointing at a session that no longer exists. Doing all three is
what actually logs someone out. Setting the cookie's expiry to
`time() - 42000` (a time in the past) is how you tell a browser to throw a
cookie away.

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

The `exit` is essential. `header()` only queues a redirect header — it does
**not** stop the script. Without `exit`, PHP would carry on and send the
whole protected page body after the redirect header, and anyone watching
the raw response could read it.

Every protected page calls it near the top, before any output:

```php
require_once 'includes/functions.php';
require_once 'includes/db.php';

require_login();
```

`validate_student.php` checks too, because an AJAX endpoint is just a URL
anyone can request. Without the check, a stranger could post emails at it
and use the "already exists" answer to find out which students are in the
database.

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

Three points worth noting:

- The picture is only handled **after** the text fields pass. There is no
  reason to move a file onto the server if the form is going to be
  rejected anyway.
- `$old` keeps whatever the user typed, so a rejected form comes back
  filled in instead of blank.
- The redirect after a successful insert is the **POST / redirect / GET**
  pattern. If the page just printed "saved", pressing F5 would resubmit
  the form and add the student twice.

The same `validate_student()` function is called by the AJAX endpoint, the
add page and the update page. That is deliberate — writing the rules twice
would let them drift apart, and the user would see "looks good" from the
AJAX check and then an error after submitting.

---

## 6. AJAX form validation

**Files:** `js/main.js` + `validate_student.php`

### The idea

AJAX means asking the server a question **without reloading the page**.
Here the question is "are these values acceptable?" and the answer comes
back as JSON.

### Client side

`js/main.js` listens on each field. The interesting part is *when* it asks:

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

This is **debouncing**. Every keystroke cancels the pending timer and
starts a new one, so the request only fires once the user pauses. Typing a
25-character email sends **one** request instead of 25. A field that is
already showing an error gets a shorter delay, so the red message clears
quickly once it is fixed.

The request itself:

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

- `FormData(studentForm)` grabs every field in the form in one line.
- `AbortController` cancels a request that is still in flight when a newer
  one starts. Without it, a slow earlier reply could land *after* a fast
  later one and paint stale messages (a race condition).
- `fields` tells PHP which fields to report on, so empty fields the user
  has not reached yet stay quiet instead of all turning red at once.

Painting the answer uses Bootstrap's own validation classes:

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

`is-invalid` makes the border red and reveals the `.invalid-feedback` div
underneath; `is-valid` makes it green. No custom CSS needed.

### Server side

`validate_student.php` returns JSON, not HTML:

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

`array_flip()` turns `['name', 'email']` into `['name' => 0, 'email' => 1]`,
and `array_intersect_key()` then keeps only those keys from `$errors`.
That is how the "only report on these fields" filter works.

A reply looks like:

```json
{ "valid": false, "errors": { "email": "A student with this email already exists." } }
```

### On submit

The submit handler blocks the form, checks every field at once, and only
lets it through if the server says yes:

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

The `dataset.validated` flag stops an infinite loop: `studentForm.submit()`
would otherwise trigger this same handler again.

And if the AJAX call itself fails (server down, no network) the `catch`
submits the form normally:

```js
.catch(function (error) {
    studentForm.dataset.validated = 'true';
    studentForm.submit();
});
```

This is safe because **PHP validates everything again anyway**. The AJAX
layer exists to be helpful, not to be a security control. Anyone can turn
JavaScript off, or post straight to `add_student.php` with curl — which is
why `add_student.php` runs the identical `validate_student()` call before
it writes anything.

---

## 7. File uploads

**File:** `includes/functions.php`, function `handle_picture_upload()`

The form needs the right encoding, or `$_FILES` arrives empty:

```html
<form method="post" enctype="multipart/form-data">
```

### Step 1 – was a file even sent?

```php
if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
    return $result;      // filename = null, error = null
}
```

The picture is optional, so "no file" is a normal outcome, not an error.

### Step 2 – size

```php
$max_bytes = 2 * 1024 * 1024;         // 2 MB
if ($file['size'] > $max_bytes) {
    $result['error'] = 'The picture must be smaller than 2 MB.';
    return $result;
}
```

### Step 3 – is it really an image?

This is the important one:

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

Two habits worth keeping:

- **`$file['type']` is not checked.** That value is sent by the browser and
  can be set to anything. `getimagesize()` opens the file and reads its
  actual header bytes, so `shell.php` renamed to `photo.jpg` with a faked
  MIME type is rejected here.
- **The extension comes from the detected type**, not from the uploaded
  file name. Whatever the file was called, it is saved with an extension
  matching what it actually is.

### Step 4 – a new, safe file name

```php
$filename = 'student_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
```

Giving `student_1730000000_a3f91b2c.jpg`.

The user's own file name is never reused. Two students both uploading
`photo.jpg` would overwrite each other, and a crafted name like
`../../includes/db.php` could try to escape the folder. `time()` plus
4 random bytes makes collisions effectively impossible even for two
uploads in the same second.

### Step 5 – move it

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

PHP puts every upload in a temporary folder and deletes it when the script
ends, so it has to be moved to be kept. `move_uploaded_file()` is used
rather than `rename()` because it first verifies the path really was an
HTTP upload — it refuses to move an arbitrary server file.

`__DIR__` makes the path relative to `functions.php` itself, so the
function works no matter which page called it.

### Step 6 – only the name goes in the database

```php
$stmt->execute([..., $picture['filename']]);
```

The database stores `student_1730000000_a3f91b2c.jpg`, not the image data
and not a full path. Storing images as BLOBs would bloat the database and
slow every query; storing an absolute path would break the moment the
project moved folders. Pages then just write:

```html
<img src="uploads/<?= e($student['profile_picture']) ?>">
```

### Step 7 – clean up

`delete_picture()` removes the file when a student is deleted, or when
their picture is replaced or removed:

```php
$path = __DIR__ . '/../uploads/' . basename($filename);
if (is_file($path)) {
    unlink($path);
}
```

`basename()` strips any directory part, so even a corrupted database value
like `../includes/db.php` can only ever delete something inside `uploads/`.

### Step 8 – no PHP execution in the uploads folder

`uploads/.htaccess`:

```apache
php_flag engine off
<FilesMatch "\.(php|phtml|php3|php4|php5|phps|cgi|pl|py|asp|aspx|sh)$">
    Require all denied
</FilesMatch>
```

A second line of defence. Even if something executable did get in, Apache
would refuse to run it.

### Client-side preview

`js/main.js` uses `FileReader` to show the image before it is uploaded:

```js
const reader = new FileReader();
reader.onload = function (event) {
    previewImg.src = event.target.result;      // a base64 data: URL
    previewWrapper.classList.remove('d-none');
};
reader.readAsDataURL(file);
```

`readAsDataURL()` reads the file the user picked and gives back a
`data:image/jpeg;base64,...` string that can go straight into `src`.
Nothing is sent to the server at this point — the preview is entirely local.

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

`LIKE '%ahmed%'` means "contains ahmed anywhere". The three `OR`s mean one
box searches all three columns, so typing `0101` finds students by phone
and typing `gmail` finds them by email.

**The `%` signs go in the value, not in the SQL.** The query text still
contains plain `?` placeholders, so the search term remains a bound
parameter and cannot alter the query. Writing
`"WHERE name LIKE '%$search%'"` would be an SQL injection hole.

`$where` and `$params` are then reused by both the COUNT query and the
SELECT, so the two can never disagree about what is being searched.

The form uses `method="get"`:

```html
<form method="get" action="view_students.php">
    <input type="hidden" name="sort" value="...">
    <input type="hidden" name="dir"  value="...">
    <input type="text" name="search" id="searchInput">
```

GET puts the search in the URL (`?search=ahmed`), which makes a result
bookmarkable and shareable, and means the back button behaves properly.
The hidden fields carry the current sort along, so searching does not
silently reset it.

JavaScript submits the form shortly after the user stops typing:

```js
let searchTimer = null;
searchInput.addEventListener('input', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function () {
        searchInput.form.submit();
    }, 700);
});
```

Debounced again, so the page does not reload mid-word. The **Search**
button is still there and still works with JavaScript off.

---

## 9. Pagination

**File:** `view_students.php`

Three numbers drive everything:

```php
$per_page = 10;
$page     = max(1, (int) get_text('page', '1'));
```

`(int)` turns `"abc"` into `0`, and `max(1, ...)` lifts anything below 1 up
to 1 — so `?page=-5` and `?page=abc` both land on page 1 rather than
producing a negative `OFFSET` and a MySQL error.

`get_text()` is a small helper in `functions.php`. Reading `$_GET['page']`
directly would normally be fine, but a URL like `?page[]=1` makes PHP hand
back an **array**, and passing an array to `trim()` or `strtolower()` is a
fatal error in PHP 8. The helper checks the type and falls back to the
default:

```php
function get_text($key, $default = '')
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}
```

There is a matching `post_text()` for `$_POST`. Passwords deliberately do
**not** go through it, because trimming a password would silently change
what the user typed — a trailing space is a legitimate character.

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

The total is needed before the rows, because without it there is no way to
know how many pages exist or whether **Next** should be greyed out.
`ceil()` rounds up: 25 students at 10 per page is 2.5 → 3 pages. Note the
count uses the **same** `$where` and `$params`, so searching for one name
correctly reports 1 page rather than the whole table's worth.

Clamping `$page` down to `$total_pages` means `?page=99` shows the last
page instead of an empty table.

### Then the slice

```php
$offset = ($page - 1) * $per_page;

$sql = "SELECT id, name, email, phone, address, profile_picture, created_at
        FROM students
        $where
        ORDER BY $sort_column $direction
        LIMIT $per_page OFFSET $offset";
```

`LIMIT` is how many rows, `OFFSET` is how many to skip:

| Page | OFFSET | Rows shown |
|------|--------|-----------|
| 1 | 0 | 1–10 |
| 2 | 10 | 11–20 |
| 3 | 20 | 21–30 |

This is why only 10 rows ever travel from MySQL to PHP. Fetching all 5,000
students and slicing in PHP would work with 12 rows and fall over with
5,000 — the database does the work here instead.

`$per_page` and `$offset` are interpolated rather than bound because MySQL
will not accept a placeholder in `LIMIT` when `EMULATE_PREPARES` is off.
They are safe because both are integers the code produced itself: `$offset`
comes from arithmetic on the `(int)`-cast `$page`, and `$per_page` is a
literal. Nothing from the URL reaches them as a string.

### The buttons

Two small helpers keep the HTML readable. `page_url()` rebuilds the query
string with some values changed:

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

`array_merge()` lets the overrides win, so `page_url(['page' => 3])` keeps
the current search and sort but changes the page. This is what stops
clicking "Next" from forgetting what you searched for.

Previous and Next get Bootstrap's `disabled` class at the ends:

```php
<li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
    <a class="page-link" href="<?= e(page_url(['page' => $page - 1])) ?>">Previous</a>
</li>
```

And the numbered buttons only show a window around the current page:

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

Always the first and last page, plus two either side of the current one,
with `…` in the gaps. With 50 pages that is about 7 buttons instead of 50.

---

## 10. Sorting

**File:** `view_students.php`

Column names **cannot** be bound with a `?` placeholder — `ORDER BY ?`
would sort by a literal string, not a column. So a whitelist is used:

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

The value from the URL is used as an **array key**, never as SQL. Anything
that is not in the list falls back to `created_at`, so `?sort=password` or
`?sort=name;DROP TABLE students` cannot reach the query — only one of those
five exact strings ever can.

The direction is handled the same way, with only two possible outcomes:

```php
$direction = (strtolower(get_text('dir', 'desc')) === 'asc') ? 'ASC' : 'DESC';
```

`sort_header()` draws each clickable heading:

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

Clicking the column you are already sorting by flips the direction;
clicking a different one starts ascending. The icon shows which column is
active and which way.

`'page' => 1` matters: after re-sorting, "page 5 of the old order" is
meaningless, so re-sorting returns to the first page.

---

## 11. Updating a student

**File:** `update_student.php`

One file, two jobs:

```php
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
```

A `GET` with `?id=5` loads the form; a `POST` saves it. The `id` comes from
`$_GET` on the first visit and from a hidden field on submit, so it
survives a failed validation round.

The record is loaded and its existence checked:

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

Pre-filling the form is just echoing the row into the `value` attributes:

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

On a failed POST, `$old` is overwritten with what the user typed, so the
form shows their edits rather than reverting to the database values.

### The `ignore_id` trick

```php
$errors = validate_student($old, $pdo, $id);
```

That third argument is why saving a student without changing their email
does not fail. Inside `validate_student()`:

```php
$sql = 'SELECT id FROM students WHERE email = ?';
$params = [$email];

if ($ignore_id !== null) {
    $sql .= ' AND id != ?';
    $params[] = $ignore_id;
}
```

The duplicate check skips the student's own row. Without `AND id != ?`,
every single edit would report "a student with this email already exists" —
pointing at the student being edited.

The form sends the same value to the AJAX endpoint as a hidden field, so
the live validation agrees with the server-side check:

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

Three outcomes from one form. Starting from "keep what we have" means
leaving the file input alone is the safe default — a user who only wanted
to fix a typo in the phone number does not lose the picture. The old file
is deleted in the replace and remove cases so `uploads/` does not
accumulate orphans.

---

## 12. Deleting a student

**Files:** `view_students.php` (the button) + `delete_student.php`

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

A plain `<a href="delete_student.php?id=5">` would be a GET request, and a
GET must never change data. Browsers prefetch links, crawlers follow them,
and "open all in tabs" would wipe the table. So delete is a POST, and the
server insists on it:

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

`confirm()` returns `false` on Cancel, and `preventDefault()` then stops
the form being sent. The hidden `student_name` field is what lets the
dialog name the student rather than saying "are you sure?".

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

The row is read **before** it is deleted, for two reasons: the picture's
file name is needed to delete the file, and the student's name is needed
for the "X was deleted" message. Once the row is gone, both are
unavailable.

Finally, the user goes back to the page they came from, search and page
number intact:

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

Only the query string from the referrer is reused, never the whole URL —
`HTTP_REFERER` is sent by the browser and cannot be trusted as a redirect
target.

---

## 13. Responsive design

**Files:** `includes/header.php`, every page, `css/style.css`

### The viewport meta tag

```html
<meta name="viewport" content="width=device-width, initial-scale=1">
```

Without this, a phone pretends to be a ~980px-wide desktop and zooms out,
and no amount of Bootstrap helps. It is the single most important line for
mobile.

### The grid

Bootstrap's grid is 12 columns, and the breakpoint prefix says *from this
width upwards*:

```html
<div class="row g-3">
    <div class="col-md-6"> ... name ... </div>
    <div class="col-md-6"> ... email ... </div>
</div>
```

`col-md-6` = half width (6 of 12) from 768px up. Below that it falls back
to full width, so the two fields sit side by side on a laptop and stack on
a phone. `g-3` is the gap between them.

The homepage cards use the shorthand:

```html
<div class="row row-cols-1 row-cols-md-3 g-4">
```

One card per row on phones, three per row from medium up.

And forms are centred and narrowed on big screens:

```html
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-5">
```

Full width on a phone, 8/12 on a tablet, 5/12 on a desktop — a login box
stretched across a 27" monitor looks wrong.

### Hiding columns on small screens

The students table has seven columns, which is too many for a phone:

```html
<th class="d-none d-lg-table-cell">Address</th>
<th class="d-none d-md-table-cell">Added</th>
```

`d-none` hides it everywhere, then `d-lg-table-cell` brings it back from
992px up. Address disappears first (it is the longest), then "Added".
Photo, name, email, phone and the action buttons always stay.

The table is also wrapped so it scrolls sideways rather than breaking the
page:

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

`navbar-expand-lg` means "lay out horizontally from 992px up, otherwise
collapse into the hamburger". `data-bs-toggle="collapse"` is handled by
Bootstrap's own JavaScript — hence the bundle in `footer.php`.

### Buttons that stack

```html
<div class="d-flex flex-column flex-sm-row gap-2 mt-4">
    <button type="submit" class="btn btn-primary">Save Student</button>
    <a href="view_students.php" class="btn btn-outline-secondary">Cancel</a>
</div>
```

Stacked vertically (and full width, easy to tap) on a narrow phone, side
by side from 576px up.

### Our own media query

`css/style.css` adds the one thing Bootstrap did not cover — the page
numbers were running off the edge of a narrow phone:

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

`575.98px` rather than `576px` matches Bootstrap's own convention of
stopping just short of the next breakpoint, so the two never both apply.

---

## 14. Preventing SQL injection and XSS

### SQL injection

Every value that reaches a query is a bound parameter:

```php
$stmt = $pdo->prepare('SELECT * FROM students WHERE email = ?');
$stmt->execute([$email]);
```

The SQL text and the data travel to MySQL **separately**. MySQL parses the
query first, sees a placeholder where a value goes, and then substitutes
the data as a value. It is never re-parsed as SQL. So if someone types:

```
' OR 1=1 --
```

into the email box, MySQL looks for a student whose email address is
literally `' OR 1=1 --`, finds nobody, and moves on.

The string-concatenation version is what to avoid:

```php
// NEVER do this
$pdo->query("SELECT * FROM students WHERE email = '$email'");
```

There, the typed text becomes part of the query and changes its meaning.

Placeholders only work for **values**, though. Column names and `LIMIT`
need different handling, and both appear in `view_students.php`:

- **Column names** (`ORDER BY`) — whitelist. The URL value is used as an
  array key into a list written in the code, so only five exact strings
  can ever reach the SQL. ([section 10](#10-sorting))
- **`LIMIT` / `OFFSET`** — integers the code computed itself, from a
  `(int)`-cast page number. Nothing from the URL arrives as a string.
  ([section 9](#9-pagination))
- **`LIKE` wildcards** — the `%` characters are concatenated onto the
  *value*, not into the query text. ([section 8](#8-search))

### XSS

Everything printed into HTML goes through one helper:

```php
function e($text)
{
    return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
}
```

Used everywhere:

```html
<td class="fw-semibold"><?= e($student['name']) ?></td>
```

`htmlspecialchars()` turns `<` into `&lt;`, `>` into `&gt;`, `"` into
`&quot;` and `&` into `&amp;`. So a student saved as:

```html
<script>alert('hacked')</script>
```

reaches the browser as:

```html
&lt;script&gt;alert(&#039;hacked&#039;)&lt;/script&gt;
```

The browser **displays** that text instead of running it. Without
escaping, the script would execute in the browser of every user who viewed
the list — which is what stored XSS is.

`ENT_QUOTES` is passed so single quotes are escaped too, which matters
inside attributes like `value='...'`. The function is short on purpose: a
one-letter name makes it easy to use consistently, and consistency is the
whole point — one unescaped echo is enough to reopen the hole.
