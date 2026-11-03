# Deploying this project online (free)

How to get this site onto GitHub and then onto a free web host.

---

## Contents

1. [Why not Vercel](#1-why-not-vercel)
2. [What to use instead](#2-what-to-use-instead)
3. [Step 1 — put the project on GitHub](#step-1--put-the-project-on-github)
4. [Step 2 — create the hosting account](#step-2--create-the-hosting-account)
5. [Step 3 — create the database](#step-3--create-the-database)
6. [Step 4 — import the tables](#step-4--import-the-tables)
7. [Step 5 — upload the files](#step-5--upload-the-files)
8. [Step 6 — point the site at the live database](#step-6--point-the-site-at-the-live-database)
9. [Step 7 — test the live site](#step-7--test-the-live-site)
10. [Updating the site later](#updating-the-site-later)
11. [Troubleshooting](#troubleshooting)

---

## 1. Why not Vercel

Vercel is excellent, but it is built for JavaScript and static sites, and
this project is PHP + MySQL with file uploads. Three things break:

| What this project needs | What Vercel gives |
|---|---|
| **PHP** | No official PHP runtime. PHP is only available through `vercel-php`, a community runtime maintained by one person outside Vercel. |
| **A MySQL database** | None. Vercel hosts no MySQL, so you would have to rent one elsewhere and connect to it. |
| **To save uploaded pictures into `/uploads`** | The function filesystem is **read-only** (only `/tmp` is writable, and it is wiped between requests). `move_uploaded_file()` into `uploads/` fails, so the profile picture feature — a graded requirement — stops working. |

There is also a fourth, quieter problem: PHP sessions are stored as files
on disk by default, so logging in would not reliably stay logged in
across serverless invocations.

You *can* force it to work using Vercel's container-based functions with
FrankenPHP, plus an external MySQL, plus rewriting uploads to use an
external storage service. But that means changing code you are being
marked on, to work around a platform that was never meant for it. For an
assignment, that is a bad trade.

**Rule of thumb:** Vercel/Netlify are for JavaScript and static sites.
PHP + MySQL wants classic shared hosting.

---

## 2. What to use instead

**Recommended: [InfinityFree](https://www.infinityfree.com/)** — genuinely
free, no credit card, no ads on your site, and it runs this project with
**zero code changes**.

| | |
|---|---|
| PHP | 8.4 (with a version selector) |
| Database | MySQL 8.0 / MariaDB 11.4, up to 400 databases |
| Disk | 5 GB |
| Bandwidth | Unlimited |
| SSL | Free certificate (https) |
| Domain | Free subdomain, or bring your own |
| Credit card | Not required |

Equally fine alternatives if you dislike it: **ByetHost**, **AwardSpace**,
**GoogieHost**, **HelioHost**. They all work the same way — the steps
below barely change.

**Be aware** this is free shared hosting, so expect fair-use limits (daily
hit caps, a cap on total number of files, a maximum size per uploaded
file, and suspension if your site spikes CPU). For an assignment demo
none of that matters.

---

## Step 1 — put the project on GitHub

### 1.1 Check the cleanup

Already done for you, but worth understanding:

- `.remember/` (a folder written by an AI coding tool) is **deleted**, and
  listed in `.gitignore` so it can never be pushed. If you still see it,
  that is the tool recreating it in the background — it is ignored, so it
  will not reach GitHub either way.
- `.gitignore` also blocks `includes/config.php`, which will hold your
  real database password, and blocks pictures uploaded while using the
  site (but keeps the four `sample_*.png` the SQL file refers to).

### 1.2 Open the folder in VS Code

`File → Open Folder…` and pick the `Web_2_assign` folder.

### 1.3 Create the repository

In VS Code, open the **Source Control** panel (`Ctrl+Shift+G`) and click
**Publish to GitHub** — it will create the repo and push in one go.

Or from the terminal (`Ctrl+ö` / `Ctrl+\``):

```bash
git init
```

```bash
git add .
```

```bash
git commit -m "Student Registration System - PHP, MySQL, Bootstrap 5"
```

Then create an empty repo on github.com (**no** README, **no** .gitignore —
you already have one), and connect it:

```bash
git branch -M main
```

```bash
git remote add origin https://github.com/YOUR-USERNAME/YOUR-REPO.git
```

```bash
git push -u origin main
```

### 1.4 Check what you pushed

Open the repo on github.com and confirm:

- ✅ all the `.php` files, `css/`, `js/`, `sql/`, `screenshots/`
- ✅ `uploads/.htaccess` and the four `sample_*.png`
- ❌ **no** `.remember/` folder
- ❌ **no** `includes/config.php`

---

## Step 2 — create the hosting account

1. Go to <https://www.infinityfree.com/> and click **Sign Up**.
2. Confirm your email address.
3. In the client area click **Create Account**.
4. Choose a free subdomain, e.g. `student-records.rf.gd`
   (or `.infinityfreeapp.com`, `.great-site.net` …).
5. Set an account password when asked and **write it down** — it is also
   your database and FTP password.

Account creation takes a few minutes. Wait until the status is active.

> It can take up to 72 hours for a brand new free subdomain to start
> resolving worldwide, though it is usually minutes. If the address does
> not load at first, that is why — do the rest of the steps anyway.

---

## Step 3 — create the database

1. Open the **Control Panel** for your account.
2. Click **MySQL Databases**.
3. Type a short name, e.g. `srs`, and click **Create Database**.

The panel now shows four values. **Copy all four** — you need them twice:

| Shown as | Looks like |
|---|---|
| Database name | `if0_12345678_srs` |
| Database user | `if0_12345678` |
| Database host | `sql123.infinityfree.com` |
| Password | your account password |

> Notice the host is **not** `localhost`. That is the single most common
> reason a project works locally and fails on a host.

---

## Step 4 — import the tables

1. In the control panel click **phpMyAdmin** next to your database.
2. In the left sidebar, click your database name (`if0_12345678_srs`) so
   it is selected. **Do not skip this.**
3. Click the **Import** tab.
4. Choose the file **`sql/tables_only.sql`**.
5. Scroll down and click **Import**.

> ### Use `tables_only.sql`, not the other one
>
> `sql/student_registration_system.sql` starts with `CREATE DATABASE`.
> On shared hosting you are not allowed to create databases — the host
> made one for you — so that file fails with *"Access denied … CREATE
> DATABASE"*. `sql/tables_only.sql` is the same thing with those two
> lines removed, for exactly this situation.

You should end up with two tables, `students` and `users`, and 12 sample
students.

---

## Step 5 — upload the files

Everything goes **inside the `htdocs` folder**, not next to it. Files
outside `htdocs` are not served.

### Option A — File Manager (no extra software)

1. Control panel → **Online File Manager**.
2. Open the **`htdocs`** folder and delete the default placeholder
   `index2.html` if it is there.
3. On your computer, select the project's *contents* (not the folder
   itself) and make a `.zip`.
4. Upload the zip, then use the file manager's **Extract** option.

### Option B — FTP with FileZilla (better for updates)

1. Install [FileZilla](https://filezilla-project.org/).
2. Control panel → **FTP Accounts** for your host, username and password.
3. Connect, open **`htdocs`** on the right, drag the project contents in.

### What to upload

Upload everything **except** these, which are only useful locally:

- `screenshots/` (just report images, not part of the site)
- `README.md`, `CODE_EXPLANATION.md`, `DEPLOYMENT.md`
- `.gitignore`
- `sql/` (you already imported it through phpMyAdmin)

Uploading them does no harm, it is just dead weight.

**Make sure `uploads/` and `uploads/.htaccess` are uploaded.** FTP clients
often hide dotfiles — in FileZilla turn on
*Server → Force showing hidden files*. Without that `.htaccess`, the
folder loses its protection against someone uploading a script.

---

## Step 6 — point the site at the live database

The project reads `includes/config.php` if it exists, and falls back to
the XAMPP defaults if it does not. That is how the same code runs in both
places without edits.

1. On your computer, copy `includes/config.example.php` and rename the
   copy to `includes/config.php`.
2. Fill in the four values from Step 3:

```php
<?php
return [
    'host' => 'sql123.infinityfree.com',
    'name' => 'if0_12345678_srs',
    'user' => 'if0_12345678',
    'pass' => 'your-account-password',
    'port' => 3306,
];
```

3. Upload just that one file into `htdocs/includes/`.

`config.php` is in `.gitignore`, so your live password stays off GitHub.
Your local XAMPP copy keeps working because you never created a
`config.php` there.

---

## Step 7 — test the live site

Open `https://your-subdomain.rf.gd/` and walk through it:

- [ ] Homepage loads with styling (Bootstrap comes from a CDN)
- [ ] **Register** an account — this is your live login
- [ ] **Login** works
- [ ] **View Students** shows 12 students, 2 pages, with pictures on the first four
- [ ] **Search** for `Sara`
- [ ] Click a column heading to **sort**
- [ ] **Add Student** — watch the AJAX validation go red and green
- [ ] Add a student **with a picture**, and check the picture appears in the list
- [ ] **Edit** and **Delete** a student
- [ ] Open it on your phone

The upload test is the one most likely to fail on a free host, so do not
skip it.

---

## Updating the site later

GitHub and the host are separate — pushing to GitHub does **not** update
the live site. After changing something:

```bash
git add . && git commit -m "describe the change" && git push
```

Then re-upload the changed files over FTP. Only the files you changed.

---

## Troubleshooting

| Symptom | Cause and fix |
|---|---|
| **"Could not connect to the database"** | `includes/config.php` is missing, in the wrong folder, or has a typo. Remember `host` is `sqlXXX.infinityfree.com`, **not** `localhost`. |
| **Access denied … CREATE DATABASE** on import | You imported the wrong SQL file. Use `sql/tables_only.sql` and select your database in phpMyAdmin first. |
| **Page shows PHP source code** | PHP is off or the file was uploaded outside `htdocs`. |
| **500 Internal Server Error on the whole site** | Almost always `.htaccess`. The one in `uploads/` is already written to be portable; if you added your own, remove it and retry. |
| **500 error only when opening an image** | An `.htaccess` incompatibility in `uploads/`. Replace it with the one from this repo. |
| **No styling at all** | Bootstrap loads from a CDN — check your internet, and look in the browser console (F12) for blocked requests. |
| **Uploads fail or picture does not appear** | Set the `uploads` folder permissions to `755` in the file manager. Also check the file is under 2 MB and under the host's own per-file limit. |
| **AJAX validation does nothing** | Open the console (F12). A 401 means the session expired — log in again. A 404 means `validate_student.php` did not upload. |
| **Site worked, now "account suspended"** | Free hosts suspend on resource spikes or policy hits. Read the email they send; it is usually lifted by itself or by a support ticket. |
| **Subdomain does not resolve** | New free subdomains can take a few hours to propagate. Try it on mobile data to rule out your own DNS cache. |

---

## Sources

- [Vercel — supported languages / community runtimes](https://vercel.com/docs/concepts/functions/serverless-functions/supported-languages)
- [Vercel — running Laravel PHP with Docker](https://vercel.com/kb/guide/laravel-php-with-docker)
- [InfinityFree](https://www.infinityfree.com/)
