-- Migration: Add resolution_type and estimated_days_to_solve to tickets table
-- Run this SQL to update your database

ALTER TABLE tickets 
ADD COLUMN resolution_type ENUM('online', 'onsite') NULL AFTER status,
ADD COLUMN estimated_days_to_solve INT NULL AFTER resolution_type,
ADD COLUMN recommendations TEXT NULL AFTER description;

-- Add index for resolution_type
CREATE INDEX idx_resolution_type ON tickets(resolution_type);
