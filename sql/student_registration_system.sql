-- ============================================================
-- Student Registration System - Database Setup
-- Web 2 Assignment
--
-- How to use this file:
--   1. Open phpMyAdmin (http://localhost/phpmyadmin)
--   2. Click the "Import" tab
--   3. Choose this file and click "Import"
--
-- Or from the command line:
--   mysql -u root -p < student_registration_system.sql
-- ============================================================

-- Create the database (only if it does not already exist)
CREATE DATABASE IF NOT EXISTS student_registration_system
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_general_ci;

USE student_registration_system;


-- ------------------------------------------------------------
-- Table: users
-- Stores the accounts that can log into the system.
-- The password column holds the HASH made by password_hash(),
-- never the plain password. The hash is always 60 characters
-- for bcrypt but we use 255 so a stronger algorithm still fits.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(100) NOT NULL UNIQUE,
    email       VARCHAR(100) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);


-- ------------------------------------------------------------
-- Table: students
-- One row per student record.
-- profile_picture keeps only the file NAME, the real image
-- lives inside the /uploads folder on the server.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS students (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(100) NOT NULL,
    phone           VARCHAR(15)  NOT NULL,
    address         TEXT,
    profile_picture VARCHAR(255) DEFAULT NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
);


-- ------------------------------------------------------------
-- Sample data
-- A few students so the table, the search box and the
-- pagination buttons have something to show straight away.
-- (12 rows, so with 10 per page we get 2 pages.)
--
-- The first four point at the sample_*.png files that ship in the
-- /uploads folder, so the profile pictures work straight after an
-- import. The rest have no picture, which shows the coloured
-- "first initial" circle the site falls back to.
--
-- There is no sample user on purpose: create your own account
-- on the Register page so the password gets hashed properly.
-- ------------------------------------------------------------
INSERT INTO students (name, email, phone, address, profile_picture) VALUES
('Ahmed Hassan',    'ahmed.hassan@example.com',   '01012345678', '15 Tahrir Street, Cairo',               'sample_ahmed.png'),
('Sara Mahmoud',    'sara.mahmoud@example.com',   '01198765432', '7 Nile Corniche, Giza',                 'sample_sara.png'),
('Omar Khaled',     'omar.khaled@example.com',    '01234567890', '22 Abbas El Akkad, Nasr City',          'sample_omar.png'),
('Nour Ibrahim',    'nour.ibrahim@example.com',   '01555667788', '3 Gamal Abdel Nasser Road, Alexandria', 'sample_nour.png'),
('Youssef Ali',     'youssef.ali@example.com',    '01099887766', '41 El Horreya Avenue, Alexandria',      NULL),
('Mariam Fouad',    'mariam.fouad@example.com',   '01122334455', '9 El Mansoura Street, Mansoura',        NULL),
('Karim Adel',      'karim.adel@example.com',     '01233445566', '18 Port Said Street, Ismailia',         NULL),
('Habiba Tarek',    'habiba.tarek@example.com',   '01566778899', '5 El Geish Road, Tanta',                NULL),
('Mostafa Samir',   'mostafa.samir@example.com',  '01044556677', '27 Ramses Street, Cairo',               NULL),
('Laila Hossam',    'laila.hossam@example.com',   '01177889900', '12 El Haram Street, Giza',              NULL),
('Amr Sayed',       'amr.sayed@example.com',      '01255667788', '33 Salah Salem Street, Assiut',         NULL),
('Jana Wael',       'jana.wael@example.com',      '01588990011', '6 El Nasr Street, Suez',                NULL);
