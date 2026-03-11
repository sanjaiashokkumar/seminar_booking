-- ============================================================
-- SEMINAR HALL BOOKING SYSTEM - DATABASE SCHEMA
-- College Seminar Management Platform
-- ============================================================

CREATE DATABASE IF NOT EXISTS seminar_booking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE seminar_booking;

-- -------------------------------------------------------
-- TABLE: admins
-- Stores admin login credentials
-- -------------------------------------------------------
CREATE TABLE admins (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,          -- bcrypt hash
    full_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(100),
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- TABLE: departments
-- 13 college departments
-- -------------------------------------------------------
CREATE TABLE departments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    code        VARCHAR(20) NOT NULL UNIQUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- TABLE: dept_heads
-- Department Head login accounts (managed by Admin)
-- -------------------------------------------------------
CREATE TABLE dept_heads (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    dept_id     INT NOT NULL,
    username    VARCHAR(50) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,          -- bcrypt hash
    full_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(100),
    phone       VARCHAR(20),
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (dept_id) REFERENCES departments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- TABLE: seminar_halls
-- 5 seminar halls with capacities
-- -------------------------------------------------------
CREATE TABLE seminar_halls (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    code        VARCHAR(20) NOT NULL UNIQUE,
    capacity    INT NOT NULL DEFAULT 100,
    location    VARCHAR(100),
    facilities  TEXT,                           -- JSON list of default facilities
    is_active   TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- TABLE: periods
-- 7 teaching periods with time slots
-- -------------------------------------------------------
CREATE TABLE periods (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    period_no   INT NOT NULL,                   -- 1-7
    label       VARCHAR(30) NOT NULL,           -- e.g. "Period 1"
    start_time  TIME NOT NULL,
    end_time    TIME NOT NULL,
    UNIQUE KEY (period_no)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- TABLE: bookings
-- Main booking records
-- -------------------------------------------------------
CREATE TABLE bookings (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    booking_ref         VARCHAR(20) NOT NULL UNIQUE,    -- e.g. SHB-2024-00001
    dept_head_id        INT NOT NULL,
    dept_id             INT NOT NULL,
    hall_id             INT NOT NULL,
    booking_date        DATE NOT NULL,
    event_name          VARCHAR(200) NOT NULL,
    event_type          VARCHAR(100) NOT NULL,           -- Seminar, Workshop, Conference, etc.
    chief_guest         VARCHAR(200),
    student_coord_name  VARCHAR(100),
    student_coord_phone VARCHAR(20),
    faculty_coord_name  VARCHAR(100),
    faculty_coord_phone VARCHAR(20),
    facilities_needed   TEXT,                            -- JSON: projector, mic, etc.
    special_notes       TEXT,
    expected_attendees  INT DEFAULT 0,
    status              ENUM('draft','pending','approved','rejected','cancelled') DEFAULT 'draft',
    admin_remarks       TEXT,
    is_locked           TINYINT(1) DEFAULT 0,            -- locked after submission
    admin_edited        TINYINT(1) DEFAULT 0,            -- flag for admin edits
    notified_user       TINYINT(1) DEFAULT 0,            -- user seen admin-edit notification
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    submitted_at        TIMESTAMP NULL,
    FOREIGN KEY (dept_head_id) REFERENCES dept_heads(id),
    FOREIGN KEY (dept_id)      REFERENCES departments(id),
    FOREIGN KEY (hall_id)      REFERENCES seminar_halls(id),
    INDEX idx_date (booking_date),
    INDEX idx_status (status),
    INDEX idx_dept (dept_id)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- TABLE: booking_periods
-- Which periods are booked for each booking
-- -------------------------------------------------------
CREATE TABLE booking_periods (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    booking_id  INT NOT NULL,
    period_id   INT NOT NULL,
    UNIQUE KEY (booking_id, period_id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (period_id)  REFERENCES periods(id)
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- TABLE: help_requests
-- Special requests when slots unavailable
-- -------------------------------------------------------
CREATE TABLE help_requests (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    dept_head_id    INT NOT NULL,
    dept_id         INT NOT NULL,
    hall_id         INT,                                -- preferred hall (if any)
    requested_date  DATE NOT NULL,
    periods_needed  VARCHAR(50),                        -- comma-separated period IDs
    event_name      VARCHAR(200) NOT NULL,
    reason          TEXT NOT NULL,
    attachment_path VARCHAR(500),                       -- PDF or image file path
    attachment_name VARCHAR(255),
    status          ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_remarks   TEXT,
    booking_id      INT,                                -- linked booking if approved
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (dept_head_id) REFERENCES dept_heads(id),
    FOREIGN KEY (dept_id)      REFERENCES departments(id),
    FOREIGN KEY (booking_id)   REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------------------------------------
-- TABLE: notifications
-- User notifications for admin-triggered events
-- -------------------------------------------------------
CREATE TABLE notifications (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    dept_head_id    INT NOT NULL,
    booking_id      INT,
    help_request_id INT,
    type            ENUM('booking_approved','booking_rejected','booking_edited',
                         'help_approved','help_rejected','changes_requested') NOT NULL,
    message         TEXT NOT NULL,
    is_read         TINYINT(1) DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dept_head_id) REFERENCES dept_heads(id) ON DELETE CASCADE,
    FOREIGN KEY (booking_id)   REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Admin account (password: admin@123)
INSERT INTO admins (username, password, full_name, email) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@college.edu');

-- 13 Departments
INSERT INTO departments (name, code) VALUES
('Computer Science & Engineering',      'CSE'),
('Electronics & Communication Engg.',   'ECE'),
('Electrical & Electronics Engg.',      'EEE'),
('Mechanical Engineering',              'MECH'),
('Civil Engineering',                   'CIVIL'),
('Information Technology',              'IT'),
('Artificial Intelligence & ML',        'AIML'),
('Biotechnology',                       'BT'),
('Chemical Engineering',                'CHEM'),
('Physics',                             'PHY'),
('Mathematics',                         'MATH'),
('Management Studies',                  'MBA'),
('Master of Computer Applications',     'MCA');

-- 5 Seminar Halls
INSERT INTO seminar_halls (name, code, capacity, location, facilities) VALUES
('Seminar Hall 1 - Main Block',    'SH1', 150, 'Main Block, Ground Floor',  '["Projector","Microphone","AC","Whiteboard","Podium"]'),
('Seminar Hall 2 - Main Block',    'SH2', 120, 'Main Block, First Floor',   '["Projector","Microphone","AC","Whiteboard"]'),
('Seminar Hall 3 - Tech Block',    'SH3', 200, 'Tech Block, Ground Floor',  '["Projector","Microphone","AC","Smartboard","Podium","Video Conferencing"]'),
('Conference Room - Admin Block',  'SH4',  60, 'Admin Block, Second Floor', '["Projector","Microphone","AC","Whiteboard","Podium"]'),
('Seminar Hall 5 - New Block',     'SH5', 180, 'New Block, First Floor',    '["Projector","Microphone","AC","Smartboard","Podium","Stage Lighting"]');

-- 7 Periods (9:00 to 4:30 with breaks)
INSERT INTO periods (period_no, label, start_time, end_time) VALUES
(1, 'Period 1',  '09:00:00', '10:00:00'),
(2, 'Period 2',  '10:00:00', '11:00:00'),
(3, 'Period 3',  '11:00:00', '11:30:00'),
-- BREAK 11:30-11:45
(4, 'Period 4',  '11:45:00', '12:45:00'),
-- LUNCH 1:00-2:00
(5, 'Period 5',  '13:00:00', '14:00:00'),
-- BREAK 14:00-14:15
(6, 'Period 6',  '14:15:00', '15:15:00'),
(7, 'Period 7',  '15:15:00', '16:30:00');

-- Sample dept head accounts (password for all: dept@123)
-- password hash for "dept@123"
INSERT INTO dept_heads (dept_id, username, password, full_name, email, phone) VALUES
(1, 'hod_cse',   '$2y$10$TKh8H1.PchR347WiIBf.UObJ7EQ9y/dDp2K1vPKxkUdIGPEyFsFq2', 'Dr. Rajesh Kumar',    'hod.cse@college.edu',   '9876543210'),
(2, 'hod_ece',   '$2y$10$TKh8H1.PchR347WiIBf.UObJ7EQ9y/dDp2K1vPKxkUdIGPEyFsFq2', 'Dr. Priya Sharma',    'hod.ece@college.edu',   '9876543211'),
(3, 'hod_eee',   '$2y$10$TKh8H1.PchR347WiIBf.UObJ7EQ9y/dDp2K1vPKxkUdIGPEyFsFq2', 'Dr. Anand Mishra',    'hod.eee@college.edu',   '9876543212'),
(4, 'hod_mech',  '$2y$10$TKh8H1.PchR347WiIBf.UObJ7EQ9y/dDp2K1vPKxkUdIGPEyFsFq2', 'Dr. Suresh Patel',    'hod.mech@college.edu',  '9876543213'),
(5, 'hod_civil', '$2y$10$TKh8H1.PchR347WiIBf.UObJ7EQ9y/dDp2K1vPKxkUdIGPEyFsFq2', 'Dr. Meena Nair',      'hod.civil@college.edu', '9876543214');
