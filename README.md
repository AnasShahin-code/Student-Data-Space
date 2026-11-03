# Student Registration System

A dynamic, responsive student registration system built with **HTML, CSS,
JavaScript, Bootstrap 5, PHP and MySQL**.

Logged-in users can add, view, search, update and delete student records,
upload a profile picture for each student, and browse the list page by page.

---

## Table of contents

1. [Features](#1-features)
2. [Files in this project](#2-files-in-this-project)
3. [What you need installed](#3-what-you-need-installed)
4. [Setting up the database](#4-setting-up-the-database)
5. [Running the website](#5-running-the-website)
6. [How to use the website](#6-how-to-use-the-website)
7. [Troubleshooting](#7-troubleshooting)
8. [Security notes](#8-security-notes)
9. [Screenshots](#9-screenshots)

---

## 1. Features

### User authentication
- Register an account with a username, email and password
- Log in with **either** the username **or** the email address
- Logout that properly destroys the session
- Passwords stored with `password_hash()` and checked with `password_verify()`
- Pages for managing students are protected by a session check

### CRUD on student records
- **Create** – add a student with name, email, phone, address and an
  optional profile picture
- **Read** – a table of all students with search, sorting and pagination
- **Update** – edit any student, change or remove their picture
- **Delete** – with a JavaScript confirmation prompt first

### Advanced features
- **File uploads** – profile pictures saved into `/uploads`, only the file
  name goes into the database
- **Search** – one box searches name, email and phone together
- **Pagination** – 10 students per page with Previous / Next buttons
- **AJAX validation** – the add and update forms are validated while you
  type, without the page reloading
- **Responsive design** – Bootstrap 5 grid, works on phone and desktop
- **Column sorting** – click any column heading to sort by it

---

## 2. Files in this project

```
Web_2_assign/
│
├── index.php                 Homepage
├── register.php              Create a user account
├── login.php                 Log in
├── logout.php                Log out and destroy the session
│
├── add_student.php           Add a student  (CREATE)
├── view_students.php         List students  (READ + search + sort + pages)
├── update_student.php        Edit a student (UPDATE)
├── delete_student.php        Delete a student (DELETE)
├── validate_student.php      AJAX endpoint – returns JSON, not HTML
│
├── includes/
│   ├── db.php                PDO connection to MySQL
│   ├── config.example.php    Template for live hosting credentials
│   ├── functions.php         Shared helpers (login check, validation, uploads)
│   ├── header.php            <head> + navigation bar
│   └── footer.php            Footer + script tags
│
├── css/
│   └── style.css             Our styles on top of Bootstrap
│
├── js/
│   └── main.js               AJAX validation, image preview, confirmations
│
├── sql/
│   ├── student_registration_system.sql    Database + tables + sample data (use locally)
│   └── tables_only.sql                    Tables + sample data, no CREATE DATABASE
│                                          (use on shared hosting)
│
├── uploads/                  Uploaded profile pictures live here
│   └── .htaccess             Stops Apache executing anything in this folder
│
├── screenshots/              Screenshots for the report
│
├── .gitignore                Keeps secrets and uploads out of Git
├── README.md                 This file
├── CODE_EXPLANATION.md       How each feature works, with code
└── DEPLOYMENT.md             Putting it on GitHub and hosting it online
```

### A note on `.html` vs `.php`

The assignment sheet lists `index.html`, `login.html` and `register.html`.
These are `.php` files here instead, because all three need PHP to work:

| Page | Why it must be PHP |
|------|--------------------|
| `index.php` | Shows different links depending on whether you are logged in, and reads the student count from the database |
| `login.php` | Has to receive the form, check the password and start the session |
| `register.php` | Has to insert the new user and show validation errors |

A plain `.html` file is never processed by PHP, so none of that would run.
The HTML inside them is exactly the HTML the assignment asks for.

---

## 3. What you need installed

**XAMPP** (or WAMP / MAMP / Laragon) – it bundles everything:

| Component | Minimum version |
|-----------|-----------------|
| Apache    | 2.4 |
| PHP       | 7.4 (8.x recommended) |
| MySQL / MariaDB | 5.7 / 10.4 |

Download XAMPP from <https://www.apachefriends.org/download.html> and
install it with the default options (Apache + MySQL + phpMyAdmin all ticked).

Bootstrap and Bootstrap Icons are loaded from a CDN, so **you need an
internet connection the first time you open the site** for the styling to
appear. Nothing else needs installing.

---

## 4. Setting up the database

### Step 1 – start MySQL

Open the **XAMPP Control Panel** and click **Start** next to both
**Apache** and **MySQL**. Both should turn green.

### Step 2 – import the SQL file

**Option A: phpMyAdmin (easiest)**

1. Go to <http://localhost/phpmyadmin>
2. Click the **Import** tab at the top
3. Click **Choose File** and pick `sql/student_registration_system.sql`
4. Scroll down and click **Import**

You should see *"Import has been successfully finished"*. In the left
sidebar you will now have a `student_registration_system` database with
two tables: `users` and `students`.

**Option B: command line**

```bash
cd "path/to/Web_2_assign"
"C:\xampp\mysql\bin\mysql" -u root -p < sql/student_registration_system.sql
```

(Press Enter at the password prompt – XAMPP's `root` has no password by
default.)

### Step 3 – check the connection settings

Open `includes/db.php`. The defaults match a fresh XAMPP install:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'student_registration_system');
define('DB_USER', 'root');
define('DB_PASS', '');          // empty by default in XAMPP
```

**If you set a root password in MySQL, put it in `DB_PASS`.** That is the
only line you are likely to need to change.

---

## 5. Running the website

### Step 1 – put the project where Apache can see it

Copy the whole `Web_2_assign` folder into XAMPP's web root:

```
C:\xampp\htdocs\Web_2_assign
```

(On Mac that is `/Applications/XAMPP/htdocs/`, on Linux
`/opt/lampp/htdocs/`.)

### Step 2 – open it in the browser

<http://localhost/Web_2_assign/>

That is it. You should see the blue homepage banner.

### Alternative: PHP's built-in server (no Apache needed)

Useful for quickly testing, but you still need MySQL running:

```bash
cd "path/to/Web_2_assign"
"C:\xampp\php\php.exe" -S localhost:8000
```

Then open <http://localhost:8000>.

> Note: the built-in server ignores `.htaccess`, so use Apache for the
> real thing.

### Make sure uploads work

The `uploads/` folder must be writable by the web server. On Windows it
already is. On Mac or Linux:

```bash
chmod 775 uploads
```

---

## 6. How to use the website

### Create an account

1. Click **Register** in the navigation bar
2. Fill in a username (letters, numbers and underscores), an email and a
   password of at least 6 characters
3. Type the password again in **Confirm password** – a live green note
   tells you when the two match
4. Click **Register**

Your password is hashed before it is stored. Nobody, including you, can
read it back out of the database.

> The SQL file deliberately contains **no sample user**. A pre-made
> password hash in a text file would be a bad habit, so create your own
> account instead.

### Log in

1. Click **Login**
2. Enter **either** your username **or** your email address, plus your
   password
3. Click **Login**

If the details are wrong you get one general error message. It does not
say which part was wrong, so the page cannot be used to discover which
usernames exist.

### Add a student

1. Click **Add Student**
2. Fill in name, email, phone and address (all four are required)
3. Optionally choose a profile picture – you will see a preview straight
   away, before anything is uploaded
4. Click **Save Student**

As you fill the form in, each field is checked by AJAX the moment you
leave it: valid fields get a green border, problems get a red border and
a message underneath. **The page never reloads while this happens.**

Things that are checked:
- name – required, 3 to 100 characters
- email – required, must be a real email format, must not already belong
  to another student
- phone – required, 7 to 15 digits
- address – required, at least 5 characters
- picture – JPG / PNG / GIF / WEBP only, maximum 2 MB

### View, search and sort students

Click **View Students**.

- **Search** – type in the search box. It looks through name, email and
  phone at the same time, and submits itself shortly after you stop
  typing. The **Search** button also works if you prefer clicking (and
  still works with JavaScript turned off).
- **Sort** – click the **Name**, **Email**, **Phone** or **Added**
  heading. Click the same heading again to flip between A→Z and Z→A. A
  small arrow shows which column is sorting.
- **Pages** – 10 students per page. Use **Previous**, **Next** or the
  numbered buttons. Your search and sort are kept as you move between
  pages, and the URL holds all three, so a particular view can be
  bookmarked.

### Update a student

1. On the student list, click the blue **pencil** button on their row
2. The form opens already filled in with their current details
3. Change whatever you need
4. To swap the picture, choose a new file. To get rid of it entirely,
   tick **Remove the current picture**. Leave both alone to keep it.
5. Click **Save Changes**

The old image file is deleted from the server when you replace or remove
it, so the `uploads` folder does not fill up with unused files.

### Delete a student

1. On the student list, click the red **bin** button on their row
2. A dialog asks you to confirm, naming the student
3. Click **OK** to delete, or **Cancel** to stop

Deleting also removes their profile picture from the server. There is no
undo.

### Log out

Click **Logout** in the navigation bar. The session is emptied, the
session cookie is deleted and the session is destroyed on the server.

---

## 7. Troubleshooting

| Problem | What to do |
|---------|-----------|
| **"Could not connect to the database"** | MySQL is not running. Open the XAMPP Control Panel and start it. If it is running, check `DB_PASS` in `includes/db.php`. |
| **"Unknown database student_registration_system"** | You have not imported the SQL file yet. See [section 4](#4-setting-up-the-database). |
| **The page shows raw PHP code** | You opened the file directly (`file:///...`) instead of through the server. Use `http://localhost/Web_2_assign/`. |
| **Everything is unstyled, no colours** | Bootstrap comes from a CDN, so you need internet access. Check the browser console (F12) for blocked requests. |
| **Port 80 is already in use** | Skype or IIS is usually the culprit. In XAMPP click **Config → Apache (httpd.conf)**, change `Listen 80` to `Listen 8080`, restart Apache, and use `http://localhost:8080/Web_2_assign/`. |
| **Port 3306 is already in use** | Another MySQL is running. Stop that service, or change MySQL's port in XAMPP and set the same port in `db.php` (`'mysql:host=localhost;port=3307;...'`). |
| **Pictures do not upload** | Check that the `uploads/` folder exists and is writable, and that the file is under 2 MB. PHP's own `upload_max_filesize` in `php.ini` must also be at least 2M (the default is 2M or 40M, so this is rarely the issue). |
| **AJAX validation does nothing** | Open the browser console (F12). If you see a 401, your session expired – log in again. |
| **Uploaded images do not display** | Make sure you are viewing the site through the server, and that the file really is in `uploads/`. |

---

## 8. Security notes

These are the specific practices used in the code:

| Risk | How it is handled | Where |
|------|-------------------|-------|
| SQL injection | Every query uses PDO prepared statements with `?` placeholders. Column names for sorting come from a whitelist array, never straight from the URL. | all `*.php` |
| Plain-text passwords | `password_hash()` on register, `password_verify()` on login. The hash is one-way. | `register.php`, `login.php` |
| XSS (script injection) | Every value printed into HTML goes through the `e()` helper, which calls `htmlspecialchars()`. | everywhere |
| Unauthorised access | `require_login()` at the top of every page that touches student data, including the AJAX endpoint. | `add_student.php`, `view_students.php`, `update_student.php`, `delete_student.php`, `validate_student.php` |
| Malicious file uploads | `getimagesize()` verifies the file really is an image, the extension is taken from the detected type rather than the file name, and the saved name is generated by us. | `functions.php` |
| PHP running inside `uploads/` | `uploads/.htaccess` turns the PHP engine off for that folder. | `uploads/.htaccess` |
| Session fixation | `session_regenerate_id(true)` right after a successful login. | `login.php` |
| Accidental deletion | Delete is a POST form, not a GET link, plus a JavaScript confirmation. | `view_students.php`, `delete_student.php` |
| Duplicate submission | Redirect after every successful POST (the "POST / redirect / GET" pattern). | `add_student.php`, `update_student.php` |
| Username enumeration | The login error never says which half was wrong. | `login.php` |

---

## 9. Screenshots

Screenshots of each page are in the `screenshots/` folder:

| File | Shows |
|------|-------|
| `01-homepage.png` | Homepage, not logged in |
| `02-register.png` | Registration form |
| `03-login.png` | Login form |
| `04-homepage-logged-in.png` | Homepage with the student count and shortcuts |
| `05-view-students.png` | Student list with pagination |
| `06-add-student.png` | Add Student form |
| `07-ajax-validation.png` | AJAX validation showing red and green fields |
| `08-search.png` | Search results |
| `09-update-student.png` | Update form, pre-filled |
| `10-delete-button.png` | The student list showing the edit and delete buttons |
| `11-mobile-view.png` | The site on a phone-sized screen |

> **One screenshot to take yourself:** the delete confirmation box is drawn
> by the browser itself (`window.confirm()`), not by the page, so it does
> not appear in an automated screenshot. To capture it, open the student
> list, click a red bin button, and press **Win + Shift + S** while the
> dialog is on screen. Save it as `screenshots/10-delete-confirm.png`.

---

For a walkthrough of **how** each feature works, with the relevant code,
see **[CODE_EXPLANATION.md](CODE_EXPLANATION.md)**.

To put the project on GitHub and host it online for free, see
**[DEPLOYMENT.md](DEPLOYMENT.md)**.
