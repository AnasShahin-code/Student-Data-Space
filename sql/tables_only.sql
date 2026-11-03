-- ============================================================
-- Student Registration System - tables only
--
-- USE THIS FILE ON A WEB HOST (InfinityFree, ByetHost, cPanel...).
--
-- On shared hosting you are NOT allowed to run CREATE DATABASE -
-- the host creates the database for you in the control panel and
-- gives it a fixed name like  if0_12345678_srs.  Importing the
-- other file there fails with "Access denied ... CREATE DATABASE".
--
-- So this file is the same as student_registration_system.sql with
-- the CREATE DATABASE and USE lines removed. Pick your database in
-- phpMyAdmin first, then import this.
--
-- For running the project locally on XAMPP, use
-- student_registration_system.sql instead - it creates the database
-- for you.
-- ============================================================


-- ------------------------------------------------------------
-- Table: users
-- Stores the accounts that can log into the system.
-- The password column holds the HASH made by password_hash(),
-- never the plain password.
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
-- Sample data (12 students, so there are 2 pages of 10)
-- The first four use the sample_*.png files in /uploads.
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
