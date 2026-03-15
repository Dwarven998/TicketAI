-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 14, 2026 at 11:04 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ticketai`
--

-- --------------------------------------------------------

--
-- Table structure for table `campuses`
--

CREATE TABLE `campuses` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(10) NOT NULL,
  `location` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `campuses`
--

INSERT INTO `campuses` (`id`, `name`, `code`, `location`, `address`, `phone`, `email`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Bagong Silang Extension Campus', 'BSILANG', 'Bagong Silang', 'Barangay 176, Bagong Silang, Caloocan City', '88132324', NULL, 1, '2026-03-01 14:05:13', '2026-03-01 14:09:47'),
(2, 'Camarin Extension Campus', 'CAMARIN', 'Camarin', '23 Chrysanthemum St, Barangay 174, Caloocan, Metro Manila', NULL, NULL, 1, '2026-03-01 14:05:13', '2026-03-01 14:09:54'),
(3, 'Congressional Extension Campus', 'CONG_EXT', 'Congressional', 'Congressional Rd Ext, Barangay 171, Caloocan', '85242267', NULL, 1, '2026-03-01 14:05:13', '2026-03-01 14:10:00'),
(4, 'Main Campus', 'MAIN', 'Caloocan City', 'Biglang Awa Street, Cor 11th Ave, Caloocan City', '8528-4654', NULL, 1, '2026-03-01 14:05:13', '2026-03-01 14:05:13');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(10) NOT NULL,
  `description` text DEFAULT NULL,
  `head_user_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `code`, `description`, `head_user_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Information Technology', 'IT', 'IT support and technical services', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(2, 'Facilities Management', 'FM', 'Building and facility maintenance', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(3, 'Academic Affairs', 'AA', 'Academic support services (non-grade related)', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(4, 'Student Affairs', 'SA', 'Student support and welfare services', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(5, 'Human Resources', 'HR', 'HR and personnel services', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(6, 'Library Services', 'LIB', 'Library and research support', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(7, 'Security', 'SEC', 'Campus security and safety', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(8, 'Transportation', 'TRANS', 'Campus transportation services', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15');

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `building` varchar(100) DEFAULT NULL,
  `floor` varchar(50) DEFAULT NULL,
  `room` varchar(50) DEFAULT NULL,
  `campus_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `name`, `description`, `building`, `floor`, `room`, `campus_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'CL1 Ground Floor', 'Computer Laboratory 1 - Ground Floor', 'Computer Building', 'Ground Floor', 'CL1', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(2, 'CL1 2nd Floor', 'Computer Laboratory 1 - 2nd Floor', 'Computer Building', '2nd Floor', 'CL1-2F', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(3, 'CL1 3rd Floor', 'Computer Laboratory 1 - 3rd Floor', 'Computer Building', '3rd Floor', 'CL1-3F', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(4, 'CL2 Ground Floor', 'Computer Laboratory 2 - Ground Floor', 'Computer Building', 'Ground Floor', 'CL2', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(5, 'CL2 2nd Floor', 'Computer Laboratory 2 - 2nd Floor', 'Computer Building', '2nd Floor', 'CL2-2F', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(6, 'Library Ground Floor', 'Main Library - Ground Floor', 'Library Building', 'Ground Floor', 'LIB-GF', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(7, 'Library 2nd Floor', 'Main Library - 2nd Floor', 'Library Building', '2nd Floor', 'LIB-2F', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(8, 'Library 3rd Floor', 'Main Library - 3rd Floor', 'Library Building', '3rd Floor', 'LIB-3F', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(9, 'Admin Building 1st Floor', 'Administration Building - 1st Floor', 'Admin Building', '1st Floor', 'ADMIN-1F', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(10, 'Admin Building 2nd Floor', 'Administration Building - 2nd Floor', 'Admin Building', '2nd Floor', 'ADMIN-2F', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(11, 'Cafeteria', 'Student Cafeteria', 'Student Center', 'Ground Floor', 'CAFE', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(12, 'Gymnasium', 'Main Gymnasium', 'Sports Complex', 'Ground Floor', 'GYM', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(13, 'Auditorium', 'Main Auditorium', 'Academic Building', 'Ground Floor', 'AUD', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(14, 'Classroom A101', 'Academic Building Room A101', 'Academic Building', '1st Floor', 'A101', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(15, 'Classroom A201', 'Academic Building Room A201', 'Academic Building', '2nd Floor', 'A201', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(16, 'Classroom A301', 'Academic Building Room A301', 'Academic Building', '3rd Floor', 'A301', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(17, 'NCL1 Ground Floor', 'North Computer Lab 1 - Ground Floor', 'North Computer Building', 'Ground Floor', 'NCL1', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(18, 'NCL1 2nd Floor', 'North Computer Lab 1 - 2nd Floor', 'North Computer Building', '2nd Floor', 'NCL1-2F', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(19, 'NCL2 Ground Floor', 'North Computer Lab 2 - Ground Floor', 'North Computer Building', 'Ground Floor', 'NCL2', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(20, 'North Library', 'North Campus Library', 'North Library Building', 'Ground Floor', 'NLIB', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(21, 'North Admin', 'North Campus Administration', 'North Admin Building', '1st Floor', 'NADMIN', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(22, 'North Cafeteria', 'North Campus Cafeteria', 'North Student Center', 'Ground Floor', 'NCAFE', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(23, 'North Gym', 'North Campus Gymnasium', 'North Sports Complex', 'Ground Floor', 'NGYM', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(24, 'Classroom N101', 'North Academic Building Room N101', 'North Academic Building', '1st Floor', 'N101', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(25, 'Classroom N201', 'North Academic Building Room N201', 'North Academic Building', '2nd Floor', 'N201', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(26, 'CCL1 Ground Floor', 'Congressional Computer Lab 1', 'Congressional IT Building', 'Ground Floor', 'CCL1', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(27, 'CCL2 Ground Floor', 'Congressional Computer Lab 2', 'Congressional IT Building', 'Ground Floor', 'CCL2', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(28, 'Congressional Library', 'Congressional Campus Library', 'Congressional Library Building', 'Ground Floor', 'CLIB', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(29, 'Congressional Admin', 'Congressional Administration', 'Congressional Admin Building', '1st Floor', 'CADMIN', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(30, 'Congressional Cafeteria', 'Congressional Campus Cafeteria', 'Congressional Student Center', 'Ground Floor', 'CCAFE', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(31, 'Classroom C101', 'Congressional Academic Room C101', 'Congressional Academic Building', '1st Floor', 'C101', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(32, 'Classroom C201', 'Congressional Academic Room C201', 'Congressional Academic Building', '2nd Floor', 'C201', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(33, 'Parking Lot A', 'Main Parking Area A', 'Outdoor', 'Ground Level', 'PARK-A', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(34, 'Parking Lot B', 'Main Parking Area B', 'Outdoor', 'Ground Level', 'PARK-B', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(35, 'Campus Grounds', 'General Campus Area', 'Outdoor', 'Ground Level', 'GROUNDS', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(36, 'Student Dormitory', 'Student Housing Area', 'Dormitory Building', 'Various', 'DORM', NULL, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('ticket_created','ticket_updated','ticket_assigned','comment_added','status_changed','system') DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `service_categories`
--

CREATE TABLE `service_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_categories`
--

INSERT INTO `service_categories` (`id`, `name`, `description`, `department_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Computer/Laptop Issues', 'Hardware and software problems with computers and laptops', 1, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(2, 'Network/Internet Problems', 'Connectivity and network access issues', 1, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(3, 'Email/System Access', 'Account access and email related issues', 1, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(4, 'Software Installation/Updates', 'Software installation, updates and licensing', 1, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(5, 'Printer/Scanner Issues', 'Printing, scanning and peripheral device problems', 1, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(6, 'Audio/Visual Equipment', 'Projectors, speakers, microphones and AV equipment', 1, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(7, 'Website/Online Services', 'University website and online platform issues', 1, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(8, 'Classroom/Laboratory Issues', 'Classroom and laboratory maintenance and repairs', 2, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(9, 'Electrical Problems', 'Electrical issues, outlets, and lighting problems', 2, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(10, 'Plumbing/Water Issues', 'Water, plumbing, and restroom facility problems', 2, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(11, 'Air Conditioning/Ventilation', 'HVAC, cooling, heating and air quality issues', 2, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(12, 'Furniture/Equipment Repair', 'Furniture damage and equipment repair requests', 2, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(13, 'Building Maintenance', 'General building maintenance and structural issues', 2, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(14, 'Cleaning/Sanitation', 'Cleaning services and sanitation concerns', 2, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(15, 'Course/Schedule Information', 'Non-grade related course and schedule inquiries', 3, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(16, 'Academic Resources', 'Learning materials, textbooks and academic resources', 3, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(17, 'Examination Support', 'Non-grade related exam scheduling and support', 3, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(18, 'Academic Events', 'Seminars, conferences and academic event support', 3, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(19, 'Research Support', 'Research facilities and academic research assistance', 3, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(20, 'Academic Records', 'Transcript requests and academic documentation', 3, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(21, 'Student Activities/Events', 'Student organizations and campus events support', 4, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(22, 'Counseling/Wellness Services', 'Student counseling and mental health support', 4, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(23, 'Health Services', 'Medical services and health-related concerns', 4, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(24, 'Scholarship/Financial Aid', 'Scholarship information and financial assistance', 4, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(25, 'Student Housing', 'Dormitory and student accommodation issues', 4, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(26, 'Student ID/Cards', 'Student identification and access card issues', 4, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(27, 'Disciplinary/Conduct', 'Student conduct and disciplinary matters', 4, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(28, 'Employment/Job Inquiries', 'Job applications and employment information', 5, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(29, 'Benefits/Compensation', 'Employee benefits and compensation inquiries', 5, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(30, 'Training/Development', 'Staff training and professional development', 5, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(31, 'HR Policies/Procedures', 'Human resources policies and procedure questions', 5, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(32, 'Payroll Issues', 'Salary, payroll and compensation problems', 5, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(33, 'Book/Resource Requests', 'Library material requests and acquisitions', 6, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(34, 'Research Assistance', 'Research support and reference services', 6, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(35, 'Library Access/Systems', 'Library system access and technical issues', 6, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(36, 'Study Spaces/Facilities', 'Study rooms, facilities and equipment in library', 6, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(37, 'Digital Resources', 'Online databases and digital library resources', 6, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(38, 'Library Events/Programs', 'Library workshops, events and educational programs', 6, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(39, 'Lost and Found', 'Lost item reports and found item inquiries', 7, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(40, 'Security/Safety Concerns', 'Campus safety and security incident reports', 7, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(41, 'Access Control', 'Building access, key cards and entry issues', 7, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(42, 'Emergency Response', 'Emergency situations and response procedures', 7, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(43, 'Parking/Traffic', 'Parking violations, permits and traffic issues', 7, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(44, 'Incident Reports', 'Security incidents and violation reports', 7, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(45, 'Shuttle Service', 'Campus shuttle schedules and service issues', 8, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(46, 'Parking Services', 'Parking permits, spaces and related problems', 8, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(47, 'Vehicle Registration', 'Campus vehicle registration and permits', 8, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(48, 'Transportation Events', 'Special event transportation and logistics', 8, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15');

-- --------------------------------------------------------

--
-- Table structure for table `service_subcategories`
--

CREATE TABLE `service_subcategories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_subcategories`
--

INSERT INTO `service_subcategories` (`id`, `name`, `description`, `category_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Hardware Malfunction', 'Physical computer/laptop hardware problems', 1, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(2, 'Software Crashes/Errors', 'Application crashes and software errors', 1, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(3, 'Slow Performance', 'Computer running slowly or freezing', 1, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(4, 'Blue Screen/System Crashes', 'System crashes and critical errors', 1, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(5, 'Virus/Malware Issues', 'Computer infected with virus or malware', 1, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(6, 'WiFi Connection Issues', 'Cannot connect to wireless network', 2, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(7, 'Ethernet/Wired Connection', 'Wired network connection problems', 2, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(8, 'Slow Internet Speed', 'Internet connection is slow', 2, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(9, 'Network Access Denied', 'Cannot access network resources or websites', 2, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(10, 'VPN Connection Issues', 'Problems connecting to university VPN', 2, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(11, 'Password Reset', 'Forgot password or account locked', 3, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(12, 'New Account Creation', 'Need new system account access', 3, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(13, 'Permission/Access Issues', 'Cannot access certain systems or files', 3, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(14, 'Email Not Working', 'Email sending/receiving problems', 3, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(15, 'Two-Factor Authentication', 'Issues with 2FA setup or access', 3, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(16, 'New Software Installation', 'Need new software installed', 4, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(17, 'Software Updates', 'Update existing software to newer version', 4, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(18, 'License/Activation Issues', 'Software licensing and activation problems', 4, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(19, 'Software Compatibility', 'Software not compatible with system', 4, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(20, 'Antivirus/Security Software', 'Antivirus and security software issues', 4, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(21, 'Cannot Print', 'Printer not responding or printing', 5, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(22, 'Print Quality Problems', 'Poor print quality, faded or blurry prints', 5, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(23, 'Scanner Not Working', 'Scanner not functioning properly', 5, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(24, 'Paper Jams', 'Printer paper jam issues', 5, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(25, 'Printer Driver Issues', 'Printer driver installation or update problems', 5, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(26, 'Projector Problems', 'Projector not working or displaying properly', 6, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(27, 'Audio System Issues', 'Speakers, microphones not working', 6, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(28, 'Screen/Display Problems', 'Monitor or display screen issues', 6, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(29, 'Cable/Connection Issues', 'HDMI, VGA or other cable connection problems', 6, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(30, 'Remote Control Issues', 'AV equipment remote controls not working', 6, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(31, 'University Website Issues', 'Problems accessing university website', 7, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(32, 'Online Portal Access', 'Cannot access student/staff online portals', 7, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(33, 'Online Learning Platform', 'Issues with LMS or online learning systems', 7, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(34, 'Online Registration', 'Problems with online course registration', 7, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(35, 'Digital Services', 'Other online university services not working', 7, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(36, 'Classroom Equipment', 'Desks, chairs, whiteboards not working', 8, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(37, 'Laboratory Equipment', 'Lab equipment malfunction or damage', 8, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(38, 'Classroom Lighting', 'Lights not working or too dim/bright', 8, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(39, 'Classroom Temperature', 'Room too hot, cold, or poor ventilation', 8, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(40, 'Classroom Cleanliness', 'Dirty or unsanitary classroom conditions', 8, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(41, 'Power Outage', 'No electricity in area', 9, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(42, 'Electrical Outlets', 'Power outlets not working', 9, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(43, 'Light Bulbs/Fixtures', 'Light bulbs burned out or fixtures broken', 9, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(44, 'Electrical Safety Hazards', 'Exposed wires or electrical dangers', 9, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(45, 'Generator Issues', 'Backup generator problems', 9, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(46, 'Leaky Faucets/Pipes', 'Water leaking from taps or pipes', 10, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(47, 'Toilet Problems', 'Toilets not flushing or clogged', 10, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(48, 'Low Water Pressure', 'Weak water flow from taps', 10, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(49, 'Clogged Drains', 'Sinks or floor drains blocked', 10, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(50, 'Water Quality Issues', 'Dirty, discolored, or bad-tasting water', 10, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(51, 'AC Not Cooling', 'Air conditioning not working or not cold', 11, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(52, 'AC Too Cold/Hot', 'Temperature control issues', 11, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(53, 'Poor Air Circulation', 'Stuffy air or poor ventilation', 11, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(54, 'AC Noise Issues', 'Air conditioning making loud noises', 11, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(55, 'Air Quality Problems', 'Bad air quality or strange odors', 11, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(56, 'Broken Chairs/Tables', 'Damaged classroom or office furniture', 12, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(57, 'Door/Window Issues', 'Doors or windows not opening/closing properly', 12, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(58, 'Cabinet/Storage Issues', 'Broken cabinets or storage units', 12, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(59, 'Equipment Installation', 'Need furniture or equipment installed', 12, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(60, 'Equipment Replacement', 'Need damaged equipment replaced', 12, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(61, 'Roof/Ceiling Issues', 'Leaks, cracks, or ceiling problems', 13, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(62, 'Floor/Flooring Problems', 'Damaged, loose, or unsafe flooring', 13, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(63, 'Wall/Paint Issues', 'Cracks, holes, or paint problems on walls', 13, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(64, 'Structural Problems', 'Building structural issues or safety concerns', 13, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(65, 'Exterior Maintenance', 'Building exterior, landscaping, or grounds issues', 13, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(66, 'Regular Cleaning', 'Request for regular cleaning services', 14, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(67, 'Deep Cleaning', 'Need thorough or specialized cleaning', 14, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(68, 'Waste Management', 'Trash collection or disposal issues', 14, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(69, 'Restroom Sanitation', 'Restroom cleaning and sanitation issues', 14, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(70, 'Pest Control', 'Insects, rodents, or pest problems', 14, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(71, 'Course Prerequisites', 'Questions about course requirements', 15, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(72, 'Class Schedule Changes', 'Schedule modifications or conflicts', 15, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(73, 'Course Availability', 'Course offering and availability inquiries', 15, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(74, 'Academic Calendar', 'Questions about academic dates and deadlines', 15, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(75, 'Course Descriptions', 'Information about course content and objectives', 15, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(76, 'Textbook/Materials', 'Required textbooks and learning materials', 16, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(77, 'Library Resources', 'Academic books and research materials', 16, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(78, 'Online Learning Tools', 'Digital learning platforms and tools', 16, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(79, 'Study Materials', 'Additional study resources and materials', 16, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(80, 'Academic Software', 'Specialized academic software access', 16, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(81, 'Exam Scheduling', 'Exam dates and scheduling information', 17, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(82, 'Exam Locations', 'Exam venue and room assignments', 17, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(83, 'Special Accommodations', 'Disability or special needs exam support', 17, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(84, 'Make-up Exams', 'Missed exam rescheduling', 17, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(85, 'Exam Procedures', 'Questions about exam rules and procedures', 17, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(86, 'Seminars/Workshops', 'Academic seminars and workshop information', 18, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(87, 'Conferences', 'Academic conferences and symposiums', 18, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(88, 'Guest Lectures', 'Special lectures and guest speaker events', 18, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(89, 'Academic Competitions', 'Academic contests and competitions', 18, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(90, 'Graduation Events', 'Graduation ceremonies and related events', 18, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(91, 'Research Facilities', 'Access to research labs and facilities', 19, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(92, 'Research Funding', 'Research grants and funding opportunities', 19, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(93, 'Research Ethics', 'Research ethics approval and guidelines', 19, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(94, 'Research Equipment', 'Specialized research equipment access', 19, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(95, 'Publication Support', 'Academic publication and journal support', 19, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(96, 'Transcript Requests', 'Official transcript requests', 20, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(97, 'Grade Reports', 'Academic grade and progress reports', 20, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(98, 'Enrollment Verification', 'Proof of enrollment documentation', 20, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(99, 'Academic Certificates', 'Academic achievement certificates', 20, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(100, 'Record Corrections', 'Corrections to academic records', 20, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(101, 'Student Organizations', 'Student clubs and organization support', 21, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(102, 'Campus Events', 'Campus-wide events and activities', 21, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(103, 'Cultural Activities', 'Cultural events and celebrations', 21, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(104, 'Sports/Recreation', 'Sports events and recreational activities', 21, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(105, 'Leadership Programs', 'Student leadership development programs', 21, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(106, 'Personal Counseling', 'Individual counseling and mental health support', 22, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(107, 'Academic Counseling', 'Academic guidance and career counseling', 22, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(108, 'Crisis Support', 'Emergency mental health and crisis intervention', 22, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(109, 'Group Counseling', 'Group therapy and support sessions', 22, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(110, 'Wellness Programs', 'Mental health and wellness programs', 22, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(111, 'Medical Consultation', 'General medical consultation and checkups', 23, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(112, 'Emergency Medical', 'Medical emergencies and urgent care', 23, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(113, 'Health Insurance', 'Student health insurance inquiries', 23, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(114, 'Vaccination/Immunization', 'Required vaccinations and health clearances', 23, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(115, 'Health Education', 'Health awareness and education programs', 23, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(116, 'Scholarship Applications', 'Scholarship application process and requirements', 24, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(117, 'Financial Aid', 'Student financial assistance programs', 24, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(118, 'Payment Plans', 'Tuition payment plans and options', 24, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(119, 'Work-Study Programs', 'Student work opportunities and programs', 24, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(120, 'Emergency Financial Aid', 'Emergency financial assistance for students', 24, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(121, 'Dormitory Assignment', 'Room assignments and housing applications', 25, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(122, 'Housing Maintenance', 'Dormitory maintenance and repair issues', 25, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(123, 'Roommate Issues', 'Roommate conflicts and room changes', 25, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(124, 'Housing Policies', 'Dormitory rules and housing policies', 25, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(125, 'Move-in/Move-out', 'Housing check-in and check-out procedures', 25, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(126, 'New ID Card', 'New student ID card issuance', 26, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(127, 'Lost/Stolen ID', 'Replacement for lost or stolen ID cards', 26, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(128, 'ID Card Problems', 'ID card not working or damaged', 26, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(129, 'Access Card Issues', 'Building or facility access card problems', 26, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(130, 'ID Photo Update', 'Update photo on student ID card', 26, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(131, 'Code of Conduct', 'Student code of conduct inquiries', 27, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(132, 'Disciplinary Procedures', 'Disciplinary process and procedures', 27, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(133, 'Appeals Process', 'Academic or disciplinary appeals', 27, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(134, 'Behavioral Issues', 'Student behavior concerns and reports', 27, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(135, 'Academic Integrity', 'Academic honesty and integrity matters', 27, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(136, 'Job Applications', 'Employment application process', 28, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(137, 'Job Openings', 'Available positions and job postings', 28, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(138, 'Interview Process', 'Job interview scheduling and procedures', 28, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(139, 'Employment Verification', 'Employment verification and references', 28, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(140, 'Internship Programs', 'Student and graduate internship opportunities', 28, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(141, 'Health Benefits', 'Employee health insurance and benefits', 29, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(142, 'Retirement Plans', 'Employee retirement and pension plans', 29, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(143, 'Leave Policies', 'Vacation, sick leave, and time-off policies', 29, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(144, 'Employee Discounts', 'Staff discounts and perks', 29, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(145, 'Compensation Review', 'Salary review and compensation inquiries', 29, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(146, 'Professional Development', 'Staff training and skill development programs', 30, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(147, 'Orientation Programs', 'New employee orientation and onboarding', 30, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(148, 'Certification Programs', 'Professional certification and continuing education', 30, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(149, 'Workshop/Seminars', 'Staff workshops and training seminars', 30, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(150, 'Performance Reviews', 'Employee performance evaluation process', 30, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(151, 'Employee Handbook', 'Employee policies and handbook inquiries', 31, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(152, 'Workplace Policies', 'Workplace rules and procedure questions', 31, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(153, 'Grievance Procedures', 'Employee complaint and grievance process', 31, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(154, 'Equal Opportunity', 'Equal employment opportunity and diversity', 31, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(155, 'Workplace Safety', 'Employee safety policies and procedures', 31, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(156, 'Salary Problems', 'Payroll errors and salary issues', 32, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(157, 'Tax Withholding', 'Tax deduction and withholding questions', 32, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(158, 'Direct Deposit', 'Payroll direct deposit setup and issues', 32, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(159, 'Overtime Pay', 'Overtime compensation and policies', 32, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(160, 'Pay Stub Issues', 'Payroll statement and documentation problems', 32, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(161, 'Book Reservations', 'Reserve books and library materials', 33, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(162, 'Interlibrary Loans', 'Request materials from other libraries', 33, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(163, 'New Acquisitions', 'Suggest new books and materials for purchase', 33, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(164, 'Book Renewals', 'Extend borrowing period for library materials', 33, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(165, 'Lost/Damaged Books', 'Report lost or damaged library materials', 33, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(166, 'Reference Services', 'Research help and reference questions', 34, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(167, 'Database Access', 'Access to academic databases and journals', 34, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(168, 'Citation Help', 'Assistance with citations and bibliography', 34, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(169, 'Research Strategies', 'Help developing research methodologies', 34, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(170, 'Subject Guides', 'Subject-specific research guides and resources', 34, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(171, 'Library Card Issues', 'Library card problems and renewals', 35, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(172, 'Computer/Internet Access', 'Library computer and internet access', 35, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(173, 'Printing/Copying', 'Library printing and photocopying services', 35, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(174, 'System Login Problems', 'Library system access and login issues', 35, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(175, 'Mobile App Issues', 'Library mobile app problems', 35, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(176, 'Study Room Reservations', 'Reserve group study rooms', 36, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(177, 'Quiet Study Areas', 'Issues with noise in study areas', 36, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(178, 'Equipment Checkout', 'Borrow laptops, calculators, and equipment', 36, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(179, 'Facility Problems', 'Library facility maintenance issues', 36, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(180, 'Accessibility Services', 'Disability access and accommodation', 36, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(181, 'E-book Access', 'Electronic book access and downloading', 37, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(182, 'Online Journals', 'Access to online academic journals', 37, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(183, 'Digital Archives', 'Historical documents and digital collections', 37, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(184, 'Multimedia Resources', 'Videos, audio, and multimedia materials', 37, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(185, 'Software Access', 'Specialized software available in library', 37, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(186, 'Information Literacy', 'Library skills and information literacy training', 38, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(187, 'Workshops', 'Library workshops and training sessions', 38, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(188, 'Book Clubs', 'Library book clubs and reading programs', 38, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(189, 'Author Events', 'Author visits and literary events', 38, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(190, 'Exhibitions', 'Library exhibitions and displays', 38, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(191, 'Lost Items', 'Report lost personal belongings', 39, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(192, 'Found Items', 'Turn in found items or claim found property', 39, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(193, 'Lost ID/Keys', 'Lost identification cards or keys', 39, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(194, 'Lost Electronics', 'Lost phones, laptops, or electronic devices', 39, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(195, 'Lost Documents', 'Lost important documents or papers', 39, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(196, 'Suspicious Activity', 'Report suspicious behavior or activities', 40, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(197, 'Safety Hazards', 'Report safety hazards or dangerous conditions', 40, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(198, 'Theft/Vandalism', 'Report theft, vandalism, or property damage', 40, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(199, 'Personal Safety', 'Personal safety concerns and escort services', 40, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(200, 'Emergency Situations', 'Report emergencies or urgent safety issues', 40, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(201, 'Building Access', 'Problems accessing buildings or facilities', 41, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(202, 'Key Card Issues', 'Access card not working or needs replacement', 41, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(203, 'Lock/Key Problems', 'Broken locks or key issues', 41, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(204, 'After-Hours Access', 'Special access requests for after hours', 41, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(205, 'Visitor Access', 'Guest access and visitor registration', 41, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(206, 'Fire Safety', 'Fire alarms, extinguishers, and fire safety', 42, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(207, 'Medical Emergencies', 'Medical emergency response and first aid', 42, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(208, 'Natural Disasters', 'Weather emergencies and natural disaster response', 42, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(209, 'Evacuation Procedures', 'Emergency evacuation plans and procedures', 42, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(210, 'Emergency Communication', 'Emergency alert systems and communication', 42, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(211, 'Parking Violations', 'Parking tickets and violation appeals', 43, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(212, 'Parking Permits', 'Parking permit applications and renewals', 43, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(213, 'Parking Availability', 'Parking space availability and assignments', 43, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(214, 'Traffic Issues', 'Campus traffic flow and safety concerns', 43, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(215, 'Vehicle Registration', 'Campus vehicle registration requirements', 43, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(216, 'Accident Reports', 'Report accidents and injuries on campus', 44, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(217, 'Property Damage', 'Report damage to university property', 44, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(218, 'Behavioral Incidents', 'Report disruptive or inappropriate behavior', 44, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(219, 'Policy Violations', 'Report violations of university policies', 44, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(220, 'Witness Statements', 'Provide witness information for incidents', 44, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(221, 'Shuttle Schedule', 'Shuttle bus schedules and route information', 45, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(222, 'Shuttle Delays', 'Report shuttle delays or service interruptions', 45, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(223, 'Route Changes', 'Information about route modifications', 45, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(224, 'Shuttle Accessibility', 'Wheelchair accessible shuttle services', 45, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(225, 'Lost Items on Shuttle', 'Items left behind on shuttle buses', 45, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(226, 'Parking Permits', 'Apply for or renew parking permits', 46, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(227, 'Parking Enforcement', 'Parking violation tickets and appeals', 46, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(228, 'Parking Maintenance', 'Parking lot maintenance and repair issues', 46, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(229, 'Reserved Parking', 'Special parking space requests and assignments', 46, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(230, 'Parking Information', 'General parking rules and information', 46, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(231, 'Campus Vehicle Registration', 'Register vehicles for campus access', 47, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(232, 'Registration Renewal', 'Renew vehicle registration permits', 47, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(233, 'Registration Changes', 'Update vehicle registration information', 47, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(234, 'Temporary Permits', 'Short-term vehicle access permits', 47, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(235, 'Registration Problems', 'Issues with vehicle registration process', 47, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(236, 'Event Transportation', 'Special transportation for campus events', 48, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(237, 'Group Transportation', 'Charter bus or group transportation requests', 48, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(238, 'Field Trip Transportation', 'Transportation for academic field trips', 48, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(239, 'Emergency Transportation', 'Emergency or urgent transportation needs', 48, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(240, 'Transportation Coordination', 'Coordinate transportation for large groups', 48, 1, '2026-03-01 07:34:15', '2026-03-01 07:34:15');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `description`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'ServiceLink', 'Website name', '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(2, 'site_description', 'University Service Ticketing System', 'Website description', '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(3, 'max_file_size', '10485760', 'Maximum file upload size in bytes (10MB)', '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(4, 'allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,txt', 'Allowed file extensions', '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(5, 'ticket_auto_close_days', '30', 'Days after which resolved tickets are auto-closed', '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(6, 'email_notifications', '1', 'Enable email notifications', '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(7, 'ai_ticket_routing', '1', 'Enable AI-powered ticket routing', '2026-03-01 07:34:15', '2026-03-01 07:34:15'),
(8, 'maintenance_mode', '0', 'Enable maintenance mode', '2026-03-01 07:34:15', '2026-03-01 07:34:15');

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `ticket_number` varchar(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `recommendations` text DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `subcategory_id` int(11) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `priority` enum('low','medium','high','emergency') DEFAULT 'low',
  `status` enum('pending','in_progress','resolved','unresolved') DEFAULT 'pending',
  `resolution_type` enum('online','onsite') DEFAULT NULL,
  `estimated_days_to_solve` int(11) DEFAULT NULL,
  `requester_id` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `ai_analysis` text DEFAULT NULL,
  `resolution` text DEFAULT NULL,
  `satisfaction_rating` int(11) DEFAULT NULL CHECK (`satisfaction_rating` >= 1 and `satisfaction_rating` <= 5),
  `satisfaction_feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `is_client` tinyint(1) DEFAULT 0,
  `client_name` varchar(255) DEFAULT NULL,
  `client_email` varchar(255) DEFAULT NULL,
  `client_department` varchar(255) DEFAULT NULL,
  `tracking_code` varchar(20) DEFAULT NULL,
  `guest_campus` varchar(255) DEFAULT NULL,
  `guest_room` varchar(100) DEFAULT NULL,
  `guest_contact_info` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `ticket_number`, `title`, `description`, `recommendations`, `category_id`, `subcategory_id`, `location_id`, `priority`, `status`, `resolution_type`, `estimated_days_to_solve`, `requester_id`, `assigned_to`, `department_id`, `ai_analysis`, `resolution`, `satisfaction_rating`, `satisfaction_feedback`, `created_at`, `updated_at`, `resolved_at`, `closed_at`, `is_client`, `client_name`, `client_email`, `client_department`, `tracking_code`, `guest_campus`, `guest_room`, `guest_contact_info`) VALUES
(0, 'TK260006', 'Guest Request: Love joy hope', 'Love joy hope', NULL, 9, NULL, NULL, 'medium', '', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, NULL, '2026-03-14 08:57:51', '2026-03-14 08:57:51', NULL, NULL, 1, 'Jake Cyrus', 'JakeCyrus@gmail.com', 'Computer Science', 'SL-GST-2026-0006', NULL, NULL, NULL),
(1, 'TK20263900', 'Hotdog', 'Hotdog', NULL, 31, 154, 33, 'medium', 'resolved', NULL, NULL, 38, NULL, 5, 'Ticket submitted by staff. AI analysis pending for priority and routing.', NULL, NULL, NULL, '2026-03-01 08:23:02', '2026-03-01 11:55:00', '2026-03-01 11:55:00', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'TK20268256', 'Aircon', 'Aircon', 'Try restarting the affected device or application\nCheck for any error messages and note them down\nVerify that all cables and connections are secure', 11, 54, 14, 'medium', 'resolved', 'online', 5, 44, NULL, 2, 'Ticket submitted by staff. AI analysis pending for priority and routing.', NULL, NULL, NULL, '2026-03-01 14:38:52', '2026-03-01 14:39:25', '2026-03-01 14:39:25', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'TK20261660', 'Toilet', 'URGENT', 'Try restarting the affected device or application\nCheck for any error messages and note them down\nVerify that all cables and connections are secure', 10, 47, 31, 'high', '', 'online', 5, 46, 43, 2, 'Ticket submitted by staff. AI analysis pending for priority and routing.', NULL, NULL, NULL, '2026-03-01 16:29:37', '2026-03-01 17:38:39', '2026-03-01 16:53:28', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'TK20263023', 'Fix Computer', 'computer on cl 1 is not working', 'Restart the computer to clear temporary issues\nCheck if there are any error messages displayed\nClose unnecessary applications and browser tabs\nCheck available disk space (should have at least 10-15% free)', 1, 1, 3, 'high', '', 'onsite', 5, 51, 49, 1, 'Ticket submitted by staff. AI analysis pending for priority and routing.', NULL, NULL, NULL, '2026-03-06 06:49:14', '2026-03-06 06:52:53', '2026-03-06 06:52:09', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 'TK20266834', 'Aircon not working', 'aircon is not working', 'Try restarting the affected device or application\nCheck for any error messages and note them down\nVerify that all cables and connections are secure', 11, 52, 3, 'medium', 'pending', 'online', 5, 51, 49, 2, 'Ticket submitted by staff. AI analysis pending for priority and routing.', NULL, NULL, NULL, '2026-03-06 06:54:52', '2026-03-08 13:34:38', '2026-03-08 11:23:43', NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'TK20266747', 'Love Joy HOPE', 'Test', NULL, 37, NULL, NULL, 'medium', '', NULL, NULL, 49, 47, NULL, NULL, NULL, NULL, NULL, '2026-03-08 13:44:35', '2026-03-08 13:44:35', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 'TK20263141', 'Pagmamahal ni Jake', 'Sheesh', 'Try restarting the affected device or application\nCheck for any error messages and note them down\nVerify that all cables and connections are secure', 20, 99, 11, 'medium', '', 'online', 5, 51, NULL, 3, 'Ticket submitted by staff. AI analysis pending for priority and routing.', NULL, NULL, NULL, '2026-03-08 13:59:38', '2026-03-08 13:59:38', NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 'TK260001', 'Guest Request: Love joy hope', 'Love joy hope', NULL, 3, NULL, NULL, 'medium', '', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, '2026-03-09 04:27:20', '2026-03-09 04:27:20', NULL, NULL, 1, 'Jake Cyruz Pogi', 'algian.aquillo@gmail.com', 'Computer Science', 'SL-GST-2026-0001', NULL, NULL, NULL),
(10, 'TK260002', 'Guest Request: Jake badboy', 'Jake badboy', NULL, 37, NULL, NULL, 'medium', '', NULL, NULL, NULL, NULL, 6, NULL, NULL, NULL, NULL, '2026-03-09 04:31:23', '2026-03-09 04:31:23', NULL, NULL, 1, 'Jake Cyrus', 'JakeCyrus@gmail.com', 'Computer Science', 'SL-GST-2026-0002', NULL, NULL, NULL),
(11, 'TK260003', 'Guest Request: Jake going there', 'Jake going there', NULL, 27, NULL, NULL, 'medium', '', NULL, NULL, NULL, NULL, 4, NULL, NULL, NULL, NULL, '2026-03-09 05:05:45', '2026-03-09 05:05:45', NULL, NULL, 1, 'Jake Cyrus', 'JakeCyruss@gmail.com', 'Computer Science', 'SL-GST-2026-0003', '1', 'Lab 202', 'Love joy hope'),
(12, 'TK260004', 'Guest Request: Hmmmm', 'Hmmmm', NULL, 11, NULL, NULL, 'medium', '', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, NULL, '2026-03-09 05:16:32', '2026-03-09 05:16:32', NULL, NULL, 1, 'Jake Cyruss', 'JakeCyrusss@gmail.com', 'Computer Science', 'SL-GST-2026-0004', '', 'Lab 202', 'Love joy hope'),
(13, 'TK260005', 'Guest Request: Test', 'Test', NULL, 8, NULL, NULL, 'medium', '', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, NULL, '2026-03-09 05:17:31', '2026-03-09 05:17:31', NULL, NULL, 1, 'Emmanuel O. Echavez', 'algian.aquillo@gmail.com', 'Biology', 'SL-GST-2026-0005', '2', 'Lab 202', 'Test');

--
-- Triggers `tickets`
--
DELIMITER $$
CREATE TRIGGER `log_ticket_status_change` AFTER UPDATE ON `tickets` FOR EACH ROW BEGIN
    IF NEW.status != OLD.status THEN
        INSERT INTO ticket_status_history (ticket_id, old_status, new_status, changed_by, created_at)
        VALUES (NEW.id, OLD.status, NEW.status, COALESCE(NEW.assigned_to, 1), NOW());
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_ticket_resolved_time` BEFORE UPDATE ON `tickets` FOR EACH ROW BEGIN
    IF NEW.status = 'resolved' AND OLD.status != 'resolved' THEN
        SET NEW.resolved_at = NOW();
    END IF;
    
    IF NEW.status = 'closed' AND OLD.status != 'closed' THEN
        SET NEW.closed_at = NOW();
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_attachments`
--

CREATE TABLE `ticket_attachments` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `attachment_type` enum('image','video','document','other') DEFAULT 'other',
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ticket_attachments`
--

INSERT INTO `ticket_attachments` (`id`, `ticket_id`, `filename`, `original_filename`, `file_path`, `file_size`, `mime_type`, `attachment_type`, `uploaded_by`, `created_at`) VALUES
(1, 1, '69a3f766dc03c_af650e27a2e647d7.png', '2023_Predator_Wallpaper_Created in Neon_3840x2160.png', '../uploads/tickets/69a3f766dc03c_af650e27a2e647d7.png', 6779992, 'image/png', 'image', 38, '2026-03-01 08:23:02'),
(2, 5, '69aa78ea2afc9_fbebf95b1ca885d5.png', 'Untitled design (7).png', '../uploads/tickets/69aa78ea2afc9_fbebf95b1ca885d5.png', 557747, 'image/png', 'image', 51, '2026-03-06 06:49:14'),
(3, 6, '69ad5c3fb8b92_0b35396f61516ce0.pdf', 'Module 4 - Lab Activities_AQUILLO.pdf', 'uploads/proof_of_work/69ad5c3fb8b92_0b35396f61516ce0.pdf', 2378636, 'application/pdf', 'document', 1, '2026-03-08 11:23:43'),
(4, 8, '69ad80ca06bad_ebe0f4a45d49b1ea.png', '_finallyfree__charice_pempengco_is_now_jake_zyrus_coming_out_1498049520.png', '../uploads/tickets/69ad80ca06bad_ebe0f4a45d49b1ea.png', 51515, 'image/jpeg', 'image', 51, '2026-03-08 13:59:38');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_comments`
--

CREATE TABLE `ticket_comments` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `is_internal` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ticket_comments`
--

INSERT INTO `ticket_comments` (`id`, `ticket_id`, `user_id`, `comment`, `is_internal`, `created_at`, `updated_at`) VALUES
(1, 6, 49, 'tapos napo', 0, '2026-03-06 07:01:28', '2026-03-06 07:01:28'),
(2, 6, 51, 'san prof', 0, '2026-03-06 07:02:49', '2026-03-06 07:02:49'),
(3, 6, 1, '1:43 am ahaha', 1, '2026-03-06 17:43:35', '2026-03-06 17:43:35'),
(4, 6, 51, 'hahaha', 0, '2026-03-06 18:46:49', '2026-03-06 18:46:49'),
(5, 6, 1, 'Kumain kana ba', 0, '2026-03-08 11:59:36', '2026-03-08 11:59:36'),
(6, 6, 49, 'Shet tae pa more', 0, '2026-03-08 13:37:44', '2026-03-08 13:37:44');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_history`
--

CREATE TABLE `ticket_history` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `change_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_status_history`
--

CREATE TABLE `ticket_status_history` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `old_status` enum('pending','in_progress','resolved','unresolved') DEFAULT NULL,
  `new_status` enum('pending','in_progress','resolved','unresolved') NOT NULL,
  `changed_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ticket_status_history`
--

INSERT INTO `ticket_status_history` (`id`, `ticket_id`, `old_status`, `new_status`, `changed_by`, `notes`, `created_at`) VALUES
(1, 1, '', '', 1, 'Admin Override: ', '2026-03-01 09:03:50'),
(2, 1, '', '', 1, 'Admin Override: ', '2026-03-01 09:03:54'),
(3, 1, '', '', 1, 'Admin Override: ', '2026-03-01 09:13:04'),
(4, 1, '', '', 1, 'Admin Override: ', '2026-03-01 09:17:19'),
(5, 1, '', '', 1, 'Admin Override: ', '2026-03-01 09:18:22'),
(6, 1, '', '', 1, 'Admin Override: Secret', '2026-03-01 09:18:32'),
(8, 1, '', 'resolved', 1, 'Admin Override: ', '2026-03-01 11:55:00'),
(13, 3, '', 'resolved', 1, 'Admin Override: ', '2026-03-01 14:39:25'),
(14, 4, '', '', 1, 'Admin Override: ', '2026-03-01 16:32:14'),
(15, 4, '', 'resolved', 43, NULL, '2026-03-01 16:53:28'),
(16, 4, '', 'resolved', 43, '', '2026-03-01 16:53:28'),
(17, 4, 'resolved', '', 43, NULL, '2026-03-01 17:38:39'),
(18, 4, 'resolved', '', 43, '', '2026-03-01 17:38:39'),
(19, 5, '', '', 49, NULL, '2026-03-06 06:50:27'),
(20, 5, '', '', 1, 'Admin Override: ', '2026-03-06 06:50:27'),
(21, 5, '', 'resolved', 49, NULL, '2026-03-06 06:52:09'),
(22, 5, '', 'resolved', 49, '', '2026-03-06 06:52:09'),
(23, 5, 'resolved', '', 49, NULL, '2026-03-06 06:52:53'),
(24, 5, 'resolved', '', 49, 'reopen', '2026-03-06 06:52:53'),
(25, 6, '', 'in_progress', 49, NULL, '2026-03-06 06:59:45'),
(26, 6, '', 'in_progress', 1, 'Admin Override: ', '2026-03-06 06:59:45'),
(27, 6, 'in_progress', 'resolved', 49, NULL, '2026-03-06 07:02:12'),
(28, 6, 'in_progress', 'resolved', 49, '', '2026-03-06 07:02:12'),
(29, 6, 'resolved', '', 49, NULL, '2026-03-06 17:46:03'),
(30, 6, 'resolved', '', 1, 'Admin Override: ', '2026-03-06 17:46:03'),
(31, 6, '', '', 1, 'Admin Override: ', '2026-03-06 18:17:49'),
(32, 6, '', 'resolved', 49, NULL, '2026-03-08 11:23:43'),
(33, 6, '', 'resolved', 1, 'Admin Override: ', '2026-03-08 11:23:43'),
(34, 6, 'resolved', '', 49, NULL, '2026-03-08 12:31:05'),
(35, 6, 'resolved', '', 1, 'Admin Override: ', '2026-03-08 12:31:05'),
(36, 6, '', '', 49, NULL, '2026-03-08 12:48:25'),
(37, 6, '', '', 49, '', '2026-03-08 12:48:25'),
(38, 6, '', '', 49, '', '2026-03-08 12:48:30'),
(39, 6, '', 'pending', 49, NULL, '2026-03-08 13:34:38'),
(40, 6, '', 'pending', 49, '', '2026-03-08 13:34:38'),
(41, 7, '', '', 49, 'Ticket created and directly assigned by admin', '2026-03-08 13:44:35');

-- --------------------------------------------------------

--
-- Stand-in structure for view `ticket_summary`
-- (See below for the actual view)
--
CREATE TABLE `ticket_summary` (
`id` int(11)
,`ticket_number` varchar(20)
,`title` varchar(255)
,`status` enum('pending','in_progress','resolved','unresolved')
,`priority` enum('low','medium','high','emergency')
,`created_at` timestamp
,`resolved_at` timestamp
,`closed_at` timestamp
,`requester_name` varchar(201)
,`requester_email` varchar(255)
,`requester_department` int(11)
,`requester_campus` int(11)
,`campus_name` varchar(100)
,`department_name` varchar(255)
,`category_name` varchar(255)
,`subcategory_name` varchar(255)
,`location_name` varchar(255)
,`location_description` varchar(500)
,`assigned_staff_name` varchar(201)
,`resolution_time_hours` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `user_number` varchar(20) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `role` enum('admin','department_admin','staff','user') DEFAULT 'user',
  `user_type` varchar(50) DEFAULT NULL COMMENT 'For staff: MIS, Labtech, Utility, Maintenance',
  `department_id` int(11) DEFAULT NULL,
  `campus_id` int(11) DEFAULT NULL,
  `year_level` enum('1st Year','2nd Year','3rd Year','4th Year','5th Year','Graduate','Faculty','Staff') DEFAULT '1st Year',
  `profile_picture` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `email_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `user_number`, `first_name`, `last_name`, `email`, `phone_number`, `password_hash`, `google_id`, `role`, `user_type`, `department_id`, `campus_id`, `year_level`, `profile_picture`, `is_active`, `email_verified`, `created_at`, `updated_at`) VALUES
(1, 'SYSADM', 'System', 'Administrator', 'admin@servicelink.com', '', '$2a$12$RuAvghRfjlBs.aVcL9sMNOkXON25SkqieHx74h7PmY1NkiS.wwm.O', NULL, 'admin', NULL, 1, NULL, 'Staff', 'uploads/profiles/profile_1_1772740204.jpg', 1, 1, '2026-03-01 07:34:15', '2026-03-14 09:11:57'),
(38, '2029-129', 'Pogi', 'Ako', 'pogiako123@gmail.com', '', '$2y$10$3pHSvfebA4UBS9KjumXNEeC26y0RpvyKtpX8KPcAZ6eXz8n7pmqlO', NULL, 'staff', NULL, NULL, NULL, '1st Year', 'uploads/profiles/69a42d499bc36_984947bf57371dd3.jpg', 1, 0, '2026-03-01 07:39:30', '2026-03-01 17:22:39'),
(43, '2029-120', 'Abdallah', 'Yu', 'abdallahjibs@gmail.com', '09706344405', '$2y$10$TBjCIiUgVqXKsSX57rA2lub0JtNx2YyfdmLQJ6OU51Vth1RP4HFM.', NULL, 'department_admin', NULL, NULL, 1, '1st Year', 'uploads/profiles/69a476fd0a217_34e0a652ab1d3f00.jpg', 1, 0, '2026-03-01 14:24:40', '2026-03-01 18:25:47'),
(44, '2039-868', 'Jake', 'Zyrus', 'Jakebaho@gmail.com', NULL, '$2y$10$Jnz3XbDzAmiG2Y3N0k4qY.Vo0ruoKZBQSnxZhjWS5gVevsHatrhEa', NULL, 'staff', 'Labtech', NULL, 2, '1st Year', NULL, 1, 0, '2026-03-01 14:36:13', '2026-03-01 14:36:41'),
(45, NULL, 'Ella', 'Banana', 'Ella@gmail.com', NULL, '$2y$10$dpM.2Y.ZYfmL/7LonU32Wu9jFaBjgW1Yn2fdUYMg20jvXLKzGE0e2', NULL, 'department_admin', NULL, NULL, 2, '1st Year', NULL, 1, 0, '2026-03-01 15:42:58', '2026-03-01 16:13:07'),
(46, '2012-130', 'Bernadet', 'Shet', 'Bernadet@gmail.com', NULL, '$2y$10$PFoI6nNqHL0BnTueoNPmLui71z7Xpdcc8amFMP.RfWwnuB4x/XYUO', NULL, 'staff', 'Labtech', NULL, 1, '1st Year', NULL, 1, 0, '2026-03-01 16:24:33', '2026-03-01 17:57:06'),
(47, '2028-939', 'Jonathan', 'Canoy', 'Jonathan@gmail.com', NULL, '$2y$10$B/hzgvy4J.361S.Qotl4fOKAK5RR5/9P25qd5EE/KV3uedBslRFT.', NULL, 'staff', 'Labtech', NULL, 1, '1st Year', NULL, 1, 0, '2026-03-01 17:40:33', '2026-03-01 18:04:50'),
(49, 'SL-SADM-001', 'Admin', 'South', 'south_admin@gmail.com', NULL, '$2y$10$91zycsEHFL1YhBasT8Kv3eAU2rhWLRVvTIhiw2g3nm7rkZ0UDQ2NK', NULL, 'department_admin', NULL, NULL, 4, '1st Year', NULL, 1, 0, '2026-03-06 05:21:27', '2026-03-08 12:55:51'),
(51, 'SL-SLAB-001', 'Lab', 'Tech', 'labtechticket@gmail.com', NULL, '$2y$10$1VJEfK7Eu4Lsd58LJwgEX.bd2U295lowDZjvy0IZe6Ad1k6Svp40C', NULL, 'staff', 'Labtech', NULL, 4, '1st Year', NULL, 1, 0, '2026-03-06 05:36:29', '2026-03-14 10:01:16'),
(52, 'SL-SMIS-002', 'MIS', 'Office', 'misoffice@servicelink.com', NULL, '$2y$10$ElpfxuKSTVZu9EaWL0ZHJuqKpVwmVFVFSYr/VmUGGURNgD7uiigTG', NULL, 'staff', 'MIS', NULL, 4, '1st Year', NULL, 1, 0, '2026-03-06 07:17:09', '2026-03-06 07:17:09');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `ticket_summary`
--
DROP TABLE IF EXISTS `ticket_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `ticket_summary`  AS SELECT `t`.`id` AS `id`, `t`.`ticket_number` AS `ticket_number`, `t`.`title` AS `title`, `t`.`status` AS `status`, `t`.`priority` AS `priority`, `t`.`created_at` AS `created_at`, `t`.`resolved_at` AS `resolved_at`, `t`.`closed_at` AS `closed_at`, concat(`u`.`first_name`,' ',`u`.`last_name`) AS `requester_name`, `u`.`email` AS `requester_email`, `u`.`department_id` AS `requester_department`, `u`.`campus_id` AS `requester_campus`, `c`.`name` AS `campus_name`, `d`.`name` AS `department_name`, `sc`.`name` AS `category_name`, `ssc`.`name` AS `subcategory_name`, `l`.`name` AS `location_name`, `l`.`description` AS `location_description`, concat(`staff`.`first_name`,' ',`staff`.`last_name`) AS `assigned_staff_name`, timestampdiff(HOUR,`t`.`created_at`,coalesce(`t`.`resolved_at`,current_timestamp())) AS `resolution_time_hours` FROM (((((((`tickets` `t` left join `users` `u` on(`t`.`requester_id` = `u`.`id`)) left join `users` `staff` on(`t`.`assigned_to` = `staff`.`id`)) left join `campuses` `c` on(`u`.`campus_id` = `c`.`id`)) left join `departments` `d` on(`t`.`department_id` = `d`.`id`)) left join `service_categories` `sc` on(`t`.`category_id` = `sc`.`id`)) left join `service_subcategories` `ssc` on(`t`.`subcategory_id` = `ssc`.`id`)) left join `locations` `l` on(`t`.`location_id` = `l`.`id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `campuses`
--
ALTER TABLE `campuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_head` (`head_user_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_campus` (`campus_id`),
  ADD KEY `idx_building` (`building`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_ticket` (`ticket_id`),
  ADD KEY `idx_read` (`is_read`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_notifications_composite` (`user_id`,`is_read`,`created_at`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `service_categories`
--
ALTER TABLE `service_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_department` (`department_id`);

--
-- Indexes for table `service_subcategories`
--
ALTER TABLE `service_subcategories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_key` (`setting_key`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_ticket_number` (`ticket_number`),
  ADD KEY `idx_requester` (`requester_id`),
  ADD KEY `idx_assigned` (`assigned_to`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_department` (`department_id`),
  ADD KEY `idx_subcategory` (`subcategory_id`),
  ADD KEY `idx_location` (`location_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_tickets_composite` (`status`,`priority`,`created_at`),
  ADD KEY `idx_tickets_subcategory` (`subcategory_id`),
  ADD KEY `idx_tickets_location` (`location_id`),
  ADD KEY `idx_resolution_type` (`resolution_type`);

--
-- Indexes for table `ticket_attachments`
--
ALTER TABLE `ticket_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket` (`ticket_id`),
  ADD KEY `idx_uploader` (`uploaded_by`),
  ADD KEY `idx_attachment_type` (`attachment_type`),
  ADD KEY `idx_attachments_type` (`attachment_type`);

--
-- Indexes for table `ticket_comments`
--
ALTER TABLE `ticket_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket` (`ticket_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_comments_composite` (`ticket_id`,`created_at`);

--
-- Indexes for table `ticket_history`
--
ALTER TABLE `ticket_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `changed_by` (`changed_by`),
  ADD KEY `idx_ticket_history_ticket` (`ticket_id`),
  ADD KEY `idx_ticket_history_date` (`created_at`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
