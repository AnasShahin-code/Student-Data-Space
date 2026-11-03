<?php
/*
 * config.example.php
 * ------------------
 * A TEMPLATE for the live database settings. This file is safe to
 * commit because it contains no real password.
 *
 * HOW TO USE IT ON A WEB HOST
 *   1. Copy this file and rename the copy to  config.php
 *   2. Put your real hosting database details in the copy
 *   3. Upload config.php to the server (it is in .gitignore, so it
 *      will never be pushed to GitHub)
 *
 * You do NOT need this file to run the project locally on XAMPP -
 * db.php already has the right defaults for that.
 *
 * Example of what a free host (InfinityFree) gives you:
 *   host => 'sql123.infinityfree.com'
 *   name => 'if0_12345678_srs'
 *   user => 'if0_12345678'
 *   pass => 'the password you chose when creating the database'
 */

return [
    'host' => 'your-database-host',
    'name' => 'your-database-name',
    'user' => 'your-database-user',
    'pass' => 'your-database-password',
    'port' => 3306,
];
