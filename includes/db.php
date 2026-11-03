<?php
/*
 * db.php
 * ------
 * Opens the connection to MySQL with PDO and gives us back
 * a $pdo object that every other page uses.
 *
 * We use PDO (instead of mysqli) because prepared statements
 * are easier to read, and prepared statements are what stop
 * SQL injection.
 *
 * SETTINGS
 * --------
 * The values below are the XAMPP defaults, so the project runs on a
 * local machine with no changes at all.
 *
 * On a real web host the database name, user and password are
 * different. Rather than editing this file (and risking the live
 * password ending up on GitHub), copy config.example.php to
 * config.php and put the live settings there. config.php is listed
 * in .gitignore, so it never gets committed.
 */

// Start with the local XAMPP defaults
$settings = [
    'host'     => 'localhost',
    'name'     => 'student_registration_system',
    'user'     => 'root',
    'pass'     => '',          // XAMPP's root user has no password
    'port'     => 3306,
];

// If config.php exists (i.e. we are on the live server), let it
// overwrite any of the values above.
$config_file = __DIR__ . '/config.php';
if (is_file($config_file)) {
    $settings = array_merge($settings, require $config_file);
}

define('DB_HOST', $settings['host']);
define('DB_NAME', $settings['name']);
define('DB_USER', $settings['user']);
define('DB_PASS', $settings['pass']);
define('DB_PORT', $settings['port']);

try {
    // charset=utf8mb4 so Arabic names and emojis are stored correctly
    $dsn = 'mysql:host=' . DB_HOST
         . ';port='      . DB_PORT
         . ';dbname='    . DB_NAME
         . ';charset=utf8mb4';

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        // throw an exception when a query fails instead of failing silently
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        // fetch rows as associative arrays ($row['name'] instead of $row[0])
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // use REAL prepared statements, not ones emulated by PDO
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // Never print $e->getMessage() on a live site - it can leak the
    // database name and password. For this assignment we show a
    // friendly message instead.
    die('Could not connect to the database. Is MySQL running? '
        . 'Did you import sql/student_registration_system.sql, and are '
        . 'the settings in includes/db.php (or includes/config.php) correct?');
}
