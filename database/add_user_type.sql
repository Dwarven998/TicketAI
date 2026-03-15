-- Add user_type column for staff (MIS, Labtech, Utility, Maintenance)
-- Run this once on existing databases. If the column already exists, you will get an error; safe to ignore.
ALTER TABLE users ADD COLUMN user_type VARCHAR(50) NULL COMMENT 'For staff: MIS, Labtech, Utility, Maintenance' AFTER role;
