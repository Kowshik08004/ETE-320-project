# Documentation

## System Documentation

### Database Schema

The system uses a MySQL database with the following main tables:

#### Core Tables

**admin**
- Stores administrator credentials
- Fields: id, admin_name, admin_email, admin_pwd (hashed)

**students**
- Student information and RFID card mappings
- Fields: id, name, card_uid, batch, department, etc.

**courses**
- Course information
- Fields: id, course_code, course_name, department, etc.

**attendance**
- Attendance records
- Fields: id, student_id, course_id, session_id, timestamp, status

**sessions**
- Class session management
- Fields: id, course_id, room_id, start_time, end_time, status

**devices**
- RFID reader device information
- Fields: id, device_uid, room_id, status

**rooms**
- Room/classroom information
- Fields: id, room_name, building, capacity

### API Endpoints

(Document your API endpoints here if applicable)

### System Workflows

#### Attendance Recording Flow

1. Student scans RFID card
2. Arduino sends card UID to server
3. Server validates card against database
4. System checks for active session in room
5. Attendance record is created
6. Confirmation sent back to device

#### Session Management

1. Admin creates course session (manual or automatic)
2. System validates room availability
3. Session is marked as active
4. RFID readers in the room become active
5. Auto-close mechanism runs for expired sessions

### Configuration

Key configuration files:
- `connectDB.php` - Database connection
- `dev_config.php` - Development settings
- `composer.json` - PHP dependencies

### Maintenance

#### Regular Tasks
- Run `cleanup_sessions.php` to remove old sessions
- Run `auto_close_sessions.php` to close expired sessions
- Backup database regularly
- Monitor device connectivity

#### Database Migrations

Migration files included:
- `migrate_add_course_session_routines.sql`
- `migrate_add_mobile_to_students.sql`
- `migrate_attendance_units.php`

### Troubleshooting Guide

See README.md for common issues and solutions.
