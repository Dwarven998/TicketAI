# ServiceLink Database Setup

This directory contains the database schema and sample data for the ServiceLink University Ticketing System.

## Files

- **`schema.sql`** - Complete database schema with tables, relationships, and sample data
- **`sample_data.sql`** - Additional sample data (optional, for testing)

## Setup Instructions

### Option 1: Complete Setup (Recommended)
Run the main schema file which includes everything:
```sql
mysql -u root -p < schema.sql
```

### Option 2: Schema Only + Sample Data Later
If you want to set up the schema first and add sample data later:
```sql
-- First, create the basic schema (you'll need to modify schema.sql to exclude sample data)
mysql -u root -p < schema.sql

-- Then add sample data
mysql -u root -p servicelink_db < sample_data.sql
```

## Default Users

The system comes with these default accounts:

### Admin Account
- **Email:** admin@servicelink.com
- **Password:** admin123
- **Role:** System Administrator

### Department Heads
- **IT Department:** john.smith@servicelink.com (password123)
- **Facilities:** maria.garcia@servicelink.com (password123)
- **Academic Affairs:** sarah.davis@servicelink.com (password123)
- **Student Affairs:** jennifer.taylor@servicelink.com (password123)
- **HR:** lisa.thomas@servicelink.com (password123)
- **Library:** james.jackson@servicelink.com (password123)
- **Security:** william.harris@servicelink.com (password123)
- **Transportation:** patricia.martin@servicelink.com (password123)

### Staff Members
- **IT Staff:** alice.johnson@servicelink.com, bob.wilson@servicelink.com
- **Facilities Staff:** david.brown@servicelink.com
- **Academic Staff:** michael.miller@servicelink.com
- **Student Affairs Staff:** robert.anderson@servicelink.com
- **Library Staff:** emily.white@servicelink.com

### Sample Students
- emma.rodriguez@student.edu
- daniel.lee@student.edu
- sophia.clark@student.edu
- christopher.lewis@student.edu
- ashley.walker@student.edu

All sample users have the password: **password123**

## Database Structure

### Key Tables
- **users** - All system users (admin, department heads, staff, students)
- **departments** - University departments with assigned heads
- **service_categories** - Ticket categories organized by department
- **tickets** - Service request tickets
- **ticket_comments** - Communication on tickets
- **notifications** - System notifications

### Relationships
- Departments have heads (users with department_admin role)
- Users belong to departments (except regular students)
- Tickets are assigned to departments and staff members
- Categories belong to specific departments

## Features Included

- **Role-based access control** (admin, department_admin, staff, user)
- **Department management** with assigned heads
- **Ticket routing** by department and category
- **Comment system** for ticket communication
- **Notification system** for updates
- **Audit trails** with status history
- **File attachment support**
- **Reporting views** for analytics

## Security Notes

- All passwords are hashed using PHP's password_hash()
- Session management with security settings
- SQL injection protection with prepared statements
- Role-based access control throughout the system

## Customization

You can modify the departments, categories, and sample users in the SQL files to match your institution's structure before running the setup.