-- =====================================================
-- FST Club Hub - initial database (based on SDS section 3)
-- Import with phpMyAdmin (Import tab).
-- WARNING: re-importing deletes the old tables and their data.
-- =====================================================
--
-- ASSUMPTIONS TO REVIEW AS A GROUP (not confirmed by the SRS/SDS):
-- A1. Table names are lowercase plural versions of the SDS classes:
--     UserData -> users, ClubData -> clubs, MembershipData -> memberships,
--     Events -> events, Announcements -> announcements.
-- A2. users has email and password_hash (needed for login; the SDS only lists userId, name, role).
-- A3. The role values are Student, Representative, Administrator.
-- A4. A club's leader is stored in clubs.leader_id (one user can lead many clubs).
-- A5. clubs has contact_phone, meeting_info, rejection_reason and accepting_members.
--     These come from US-03, US-07 and US-08 but are not in the SDS class diagram.
-- A6. Field lengths follow the SRS (club name 60, description 500, event title 100,
--     announcement title 80, message 1000). Event end time is optional.
-- A7. UNIQUE (student_id, club_id) allows one request per student per club, ever.
--     This also stops a student re-applying after being declined. Is that what we want?
-- A8. Club categories (Academic, Technology, Sports, Arts, Service) are only examples.
-- A9. Sample passwords are placeholders. Replace them with PHP password_hash() values
--     when authentication is built.
-- A10. The SDS says 'Pending, Approved, Rejected, Inactive' for clubs while the SRS
--      constraints mention 'verified'. We used the SDS wording.

CREATE DATABASE IF NOT EXISTS fst_club_hub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fst_club_hub;

-- Drop tables in reverse order (children first) so re-importing works.
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS memberships;
DROP TABLE IF EXISTS clubs;
DROP TABLE IF EXISTS users;

-- ===== UserData =====
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Student', 'Representative', 'Administrator') NOT NULL DEFAULT 'Student'
);

-- ===== ClubData =====
-- The UNIQUE name is not case-sensitive with this collation ('Chess Club' = 'chess club').
CREATE TABLE clubs (
    club_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL UNIQUE,
    category VARCHAR(50) NOT NULL,
    description VARCHAR(500) NOT NULL,
    contact_email VARCHAR(100) NOT NULL,
    contact_phone VARCHAR(20) NULL,
    meeting_info VARCHAR(200) NULL,
    status ENUM('Pending', 'Approved', 'Rejected', 'Inactive') NOT NULL DEFAULT 'Pending',
    rejection_reason VARCHAR(255) NULL,
    accepting_members BOOLEAN NOT NULL DEFAULT TRUE,
    leader_id INT NOT NULL,
    registration_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (leader_id) REFERENCES users(user_id)
);

-- ===== MembershipData =====
CREATE TABLE memberships (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    club_id INT NOT NULL,
    status ENUM('Pending', 'Accepted', 'Declined') NOT NULL DEFAULT 'Pending',
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (student_id, club_id),
    FOREIGN KEY (student_id) REFERENCES users(user_id),
    FOREIGN KEY (club_id) REFERENCES clubs(club_id)
);

-- ===== Events =====
CREATE TABLE events (
    event_id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NULL,
    venue VARCHAR(100) NOT NULL,
    status ENUM('Draft', 'Published') NOT NULL DEFAULT 'Draft',
    FOREIGN KEY (club_id) REFERENCES clubs(club_id)
);

-- ===== Announcements =====
CREATE TABLE announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    title VARCHAR(80) NOT NULL,
    message VARCHAR(1000) NOT NULL,
    posted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (club_id) REFERENCES clubs(club_id)
);

-- =====================================================
-- SAMPLE DATA FOR TESTING (the IDs match the fixed IDs in index.php)
-- =====================================================
INSERT INTO users (user_id, name, email, password_hash, role) VALUES
(1, 'Sample Admin', 'admin@example.com', 'placeholder_hash', 'Administrator'),
(2, 'Sample Representative', 'rep@example.com', 'placeholder_hash', 'Representative'),
(3, 'Sample Student', 'student@example.com', 'placeholder_hash', 'Student');

INSERT INTO clubs (club_id, name, category, description, contact_email, status, leader_id) VALUES
(1, 'Computing Society', 'Technology', 'A club for students interested in programming and technology.', 'computing@example.com', 'Approved', 2),
(2, 'Chess Club', 'Academic', 'Weekly chess games and tournaments.', 'chess@example.com', 'Pending', 2);

INSERT INTO memberships (student_id, club_id, status) VALUES
(3, 1, 'Accepted');

INSERT INTO events (club_id, title, description, start_time, venue, status) VALUES
(1, 'Intro to Python Workshop', 'A beginner-friendly workshop.', DATE_ADD(NOW(), INTERVAL 7 DAY), 'FST Computer Lab', 'Published'),
(1, 'Planning Meeting (draft)', 'Draft events must not be visible to students.', DATE_ADD(NOW(), INTERVAL 10 DAY), 'FST Room 5', 'Draft');

INSERT INTO announcements (club_id, title, message) VALUES
(1, 'Welcome!', 'Welcome to the Computing Society. Our first meeting is next week.');

-- =====================================================
-- Search filter on clubs that have been approved and lists them
CREATE INDEX idx_clubs_status ON clubs(status);
SELECT name FROM clubs WHERE status = 'Approved';