-- Sample Data for ServiceLink Database
-- Run this after the main schema.sql if you want to add sample users and department heads
-- Password for all sample users: password123

USE servicelink_db;

-- Insert department heads and staff (password: password123)
INSERT INTO users (user_number, first_name, last_name, email, password_hash, role, department_id, campus_id, year_level, is_active, email_verified) VALUES
-- IT Department Head and Staff
('DEPT001', 'John', 'Smith', 'john.smith@servicelink.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'department_admin', 1, 1, 'Staff', TRUE, TRUE),
('USER001', 'Alice', 'Johnson', 'alice.johnson@servicelink.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 1, 1, 'Staff', TRUE, TRUE),
('USER002', 'Bob', 'Wilson', 'bob.wilson@servicelink.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 1, 2, 'Staff', TRUE, TRUE),

-- Facilities Management Head and Staff
('DEPT002', 'Maria', 'Garcia', 'maria.garcia@servicelink.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'department_admin', 2, 2, 'Staff', TRUE, TRUE),
('USER003', 'David', 'Brown', 'david.brown@servicelink.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 2, 3, 'Staff', TRUE, TRUE),

-- Academic Affairs Head
('DEPT003', 'Sarah', 'Davis', 'sarah.davis@servicelink.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'department_admin', 3, 1, 'Faculty', TRUE, TRUE),
('USER004', 'Michael', 'Miller', 'michael.miller@servicelink.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 3, 2, 'Faculty', TRUE, TRUE),

-- Student Affairs Head
('DEPT004', 'Jennifer', 'Taylor', 'jennifer.taylor@servicelink.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'department_admin', 4, 3, 'Staff', TRUE, TRUE),
('USER005', 'Robert', 'Anderson', 'robert.anderson@servicelink.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 4, 1, 'Staff', TRUE, TRUE),

-- HR Head
('DEPT005', 'Lisa', 'Thomas', 'lisa.thomas@servicelink.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'department_admin', 5, 2, 'Staff', TRUE, TRUE),

-- Library Services Head
('DEPT006', 'James', 'Jackson', 'james.jackson@servicelink.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'department_admin', 6, 3, 'Faculty', TRUE, TRUE),
('USER006', 'Emily', 'White', 'emily.white@servicelink.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff', 6, 1, 'Staff', TRUE, TRUE),

-- Security Head
('DEPT007', 'William', 'Harris', 'william.harris@servicelink.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'department_admin', 7, 2, 'Staff', TRUE, TRUE),

-- Transportation Head
('DEPT008', 'Patricia', 'Martin', 'patricia.martin@servicelink.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'department_admin', 8, 3, 'Staff', TRUE, TRUE),

-- Sample regular users
('STU001', 'Emma', 'Rodriguez', 'emma.rodriguez@student.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', NULL, 1, '3rd Year', TRUE, TRUE),
('STU002', 'Daniel', 'Lee', 'daniel.lee@student.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', NULL, 2, '2nd Year', TRUE, TRUE),
('STU003', 'Sophia', 'Clark', 'sophia.clark@student.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', NULL, 3, '4th Year', TRUE, TRUE),
('STU004', 'Christopher', 'Lewis', 'christopher.lewis@student.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', NULL, 1, '1st Year', TRUE, TRUE),
('STU005', 'Ashley', 'Walker', 'ashley.walker@student.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', NULL, 2, '4th Year', TRUE, TRUE);

-- Update departments to assign heads (run this after inserting users)
UPDATE departments SET head_user_id = (SELECT id FROM users WHERE user_number = 'DEPT001') WHERE code = 'IT';
UPDATE departments SET head_user_id = (SELECT id FROM users WHERE user_number = 'DEPT002') WHERE code = 'FM';
UPDATE departments SET head_user_id = (SELECT id FROM users WHERE user_number = 'DEPT003') WHERE code = 'AA';
UPDATE departments SET head_user_id = (SELECT id FROM users WHERE user_number = 'DEPT004') WHERE code = 'SA';
UPDATE departments SET head_user_id = (SELECT id FROM users WHERE user_number = 'DEPT005') WHERE code = 'HR';
UPDATE departments SET head_user_id = (SELECT id FROM users WHERE user_number = 'DEPT006') WHERE code = 'LIB';
UPDATE departments SET head_user_id = (SELECT id FROM users WHERE user_number = 'DEPT007') WHERE code = 'SEC';
UPDATE departments SET head_user_id = (SELECT id FROM users WHERE user_number = 'DEPT008') WHERE code = 'TRANS';

-- Insert some sample tickets for testing
INSERT INTO tickets (ticket_number, title, description, category_id, priority, status, requester_id, department_id, created_at) VALUES
('TK20240001', 'Computer not starting', 'My computer won\'t turn on after the power outage yesterday', 1, 'high', 'new', (SELECT id FROM users WHERE user_number = 'STU001'), 1, NOW() - INTERVAL 2 DAY),
('TK20240002', 'WiFi connection issues', 'Cannot connect to campus WiFi in the library', 2, 'medium', 'in_progress', (SELECT id FROM users WHERE user_number = 'STU002'), 1, NOW() - INTERVAL 1 DAY),
('TK20240003', 'Classroom projector not working', 'Projector in Room 201 is not displaying anything', 6, 'high', 'new', (SELECT id FROM users WHERE user_number = 'STU003'), 2, NOW() - INTERVAL 3 HOUR),
('TK20240004', 'Lost student ID card', 'I lost my student ID card and need a replacement', 19, 'low', 'resolved', (SELECT id FROM users WHERE user_number = 'STU004'), 7, NOW() - INTERVAL 5 DAY),
('TK20240005', 'Parking permit request', 'Need to apply for a student parking permit', 21, 'low', 'closed', (SELECT id FROM users WHERE user_number = 'STU005'), 8, NOW() - INTERVAL 7 DAY);

-- Assign some tickets to staff
UPDATE tickets SET assigned_to = (SELECT id FROM users WHERE user_number = 'USER001') WHERE ticket_number = 'TK20240001';
UPDATE tickets SET assigned_to = (SELECT id FROM users WHERE user_number = 'USER002') WHERE ticket_number = 'TK20240002';
UPDATE tickets SET assigned_to = (SELECT id FROM users WHERE user_number = 'USER003') WHERE ticket_number = 'TK20240003';

-- Add some ticket comments
INSERT INTO ticket_comments (ticket_id, user_id, comment, is_internal, created_at) VALUES
((SELECT id FROM tickets WHERE ticket_number = 'TK20240001'), (SELECT id FROM users WHERE user_number = 'USER001'), 'I will check the computer hardware first thing tomorrow morning.', FALSE, NOW() - INTERVAL 1 DAY),
((SELECT id FROM tickets WHERE ticket_number = 'TK20240002'), (SELECT id FROM users WHERE user_number = 'USER002'), 'Please try forgetting and reconnecting to the WiFi network. If that doesn\'t work, I\'ll check the access point in the library.', FALSE, NOW() - INTERVAL 12 HOUR),
((SELECT id FROM tickets WHERE ticket_number = 'TK20240003'), (SELECT id FROM users WHERE user_number = 'STU003'), 'The issue is still persisting. The projector shows a blue screen but no content.', FALSE, NOW() - INTERVAL 2 HOUR);

-- Add some notifications
INSERT INTO notifications (user_id, ticket_id, title, message, type, created_at) VALUES
((SELECT id FROM users WHERE user_number = 'STU001'), (SELECT id FROM tickets WHERE ticket_number = 'TK20240001'), 'Ticket Assigned', 'Your ticket has been assigned to Alice Johnson from IT Department.', 'ticket_assigned', NOW() - INTERVAL 1 DAY),
((SELECT id FROM users WHERE user_number = 'STU002'), (SELECT id FROM tickets WHERE ticket_number = 'TK20240002'), 'New Comment', 'Bob Wilson has added a comment to your ticket.', 'comment_added', NOW() - INTERVAL 12 HOUR),
((SELECT id FROM users WHERE user_number = 'STU004'), (SELECT id FROM tickets WHERE ticket_number = 'TK20240004'), 'Ticket Resolved', 'Your lost ID card ticket has been resolved. Please visit the Security office to collect your new card.', 'status_changed', NOW() - INTERVAL 4 DAY);