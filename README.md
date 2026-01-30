# 🎓 RFID-Based Attendance Management System

[![PHP](https://img.shields.io/badge/PHP-7.4+-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Arduino](https://img.shields.io/badge/Arduino-Compatible-00979D?style=flat&logo=arduino&logoColor=white)](https://www.arduino.cc/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

A comprehensive web-based attendance management system using RFID technology for automated student attendance tracking. Built for educational institutions to streamline attendance recording, monitoring, and reporting.

## ✨ Features

### 🔐 Admin Features
- **User Management**: Add, edit, and delete student records
- **Course Management**: Create and manage courses, sections, and batches
- **Device Management**: Configure and monitor RFID readers
- **Room & Batch Assignment**: Link rooms with devices and student batches
- **Session Management**: Create manual or automatic class sessions
- **Attendance Reports**: 
  - View real-time attendance
  - Generate attendance summaries
  - Export to Excel, CSV, and PDF formats
- **Weekly Routines**: Auto-generate class schedules
- **User Activity Logs**: Track system usage and changes

### 📊 Attendance Features
- Automatic attendance recording via RFID
- Real-time attendance monitoring
- Session-based attendance tracking
- Attendance percentage calculation
- Course-wise attendance reports
- Auto-close expired sessions

### 🔧 Technical Features
- Responsive web interface
- Secure authentication system
- Database-driven architecture
- AJAX-powered real-time updates
- Export functionality (Excel, CSV, PDF)
- Automated session cleanup

## 🏗️ System Architecture

```
┌─────────────┐      ┌──────────────┐      ┌─────────────┐
│   RFID      │─────▶│   Arduino    │─────▶│  Web Server │
│   Tags      │      │   Reader     │      │   (PHP)     │
└─────────────┘      └──────────────┘      └──────┬──────┘
                                                   │
                                                   ▼
                                            ┌─────────────┐
                                            │   MySQL     │
                                            │  Database   │
                                            └─────────────┘
```

![System Architecture Diagram](screenshots/system-architecture.png)

## 📋 Prerequisites

Before you begin, ensure you have the following installed:

- **Web Server**: Apache (XAMPP, WAMP, or LAMP)
- **PHP**: Version 7.4 or higher
- **MySQL**: Version 5.7 or higher
- **Arduino IDE**: For programming the RFID reader (if using hardware)
- **RFID Reader**: RC522 or compatible
- **Modern Web Browser**: Chrome, Firefox, Edge, or Safari

## 🚀 Installation

### Step 1: Clone the Repository

```bash
git clone https://github.com/Kowshik08004/ETE-320-project.git
cd ETE-320-project
```

### Step 2: Setup Database

1. Start your MySQL server (via XAMPP/WAMP)
2. Open phpMyAdmin (http://localhost/phpmyadmin)
3. Create a new database named `rfidattendance`
4. Import the database schema:
   - Click on the `rfidattendance` database
   - Go to the **Import** tab
   - Select `rfidattendance.sql` file
   - Click **Go**

### Step 3: Configure Database Connection

1. Open `connectDB.php` in a text editor
2. Update the database credentials:

```php
$servername = "localhost";
$username = "root";        // Your MySQL username
$password = "";            // Your MySQL password
$dbname = "rfidattendance";
```

### Step 4: Deploy Application

1. Copy the project folder to your web server's root directory:
   - **XAMPP**: `C:\xampp\htdocs\`
   - **WAMP**: `C:\wamp64\www\`
   - **LAMP**: `/var/www/html/`

2. Ensure proper file permissions (Linux/Mac):
```bash
sudo chmod -R 755 /var/www/html/ETE-320-project
```

### Step 5: Access the Application

1. Open your web browser
2. Navigate to: `http://localhost/ETE-320-project`
3. Login with default credentials:
   - **Email**: `admin@gmail.com`
   - **Password**: `123` (or check the SQL dump for the hashed password)

> ⚠️ **Important**: Change the default admin password immediately after first login!

## 🔌 Hardware Setup (Optional)

If you're using the physical RFID system:

### Components Needed
- Arduino Uno/Nano
- RC522 RFID Reader Module
- RFID Cards/Tags
- Connecting Wires
- USB Cable

### Wiring Diagram

| RC522 Pin | Arduino Pin |
|-----------|-------------|
| SDA       | D10         |
| SCK       | D13         |
| MOSI      | D11         |
| MISO      | D12         |
| IRQ       | Not Connected |
| GND       | GND         |
| RST       | D9          |
| 3.3V      | 3.3V        |

![RC522 Wiring Table](screenshots/rc522-wiring-table.png)

### Upload Arduino Code

1. Open Arduino IDE
2. Install required library: **MFRC522** (Tools → Manage Libraries → Search "MFRC522")
3. Open `RFID/RFID.ino`
4. Select your board and port
5. Upload the sketch

## 📖 Usage Guide

### For Administrators

1. **Add Students**
   - Navigate to "Manage Users"
   - Click "Add New Student"
   - Enter student details and RFID card number
   - Save

2. **Create Course Sessions**
   - Go to "Course Sessions"
   - Select course, room, and time
   - Create session (manual or automatic)

3. **View Attendance**
   - Navigate to "Attendance View"
   - Select course and date range
   - View or export reports

4. **Setup Weekly Routines**
   - Go to "Weekly Routines"
   - Configure class schedules
   - Enable auto-generation

### For Students

- Simply tap your RFID card on the reader when entering class
- Attendance is automatically recorded
- No manual login required

## 📁 Project Structure

```
ETE-320-project/
│
├── RFID/                          # Arduino code for RFID reader
│   ├── RFID.ino
│   └── rfid_attendance/           # KiCad PCB design files
│
├── js/                            # JavaScript files
│   ├── jquery-2.2.3.min.js
│   ├── bootstrap.js
│   └── *.js                       # Custom scripts
│
├── icons/                         # UI icons
│
├── css/                           # Stylesheets
│
├── *.php                          # PHP application files
│   ├── index.php                  # Main dashboard
│   ├── login.php                  # Authentication
│   ├── connectDB.php              # Database connection
│   ├── ManageUsers.php            # User management
│   ├── ManageCourses.php          # Course management
│   ├── attendance_view.php        # Attendance reports
│   └── ...                        # Other modules
│
├── rfidattendance.sql             # Database schema
├── composer.json                   # PHP dependencies
└── README.md                      # This file
```

## 🔒 Security Features

- ✅ Session-based authentication
- ✅ Admin guard middleware
- ✅ Password hashing (bcrypt)
- ✅ SQL injection prevention
- ✅ XSS protection
- ✅ CSRF token validation (recommended to implement)

## 🐛 Troubleshooting

### Common Issues

**Database Connection Failed**
- Check MySQL service is running
- Verify credentials in `connectDB.php`
- Ensure database `rfidattendance` exists

**RFID Not Reading**
- Check Arduino connections
- Verify COM port in Arduino IDE
- Test RFID cards are not damaged
- Ensure MFRC522 library is installed

**Attendance Not Recording**
- Verify student RFID is registered in database
- Check active session exists for the course
- Ensure device is linked to correct room

**Export Not Working**
- Check PHP extensions: `php_zip`, `php_excel`
- Verify write permissions on export directory
- Check PHP memory limit in `php.ini`

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 Development Roadmap

- [ ] Add SMS/Email notifications for absent students
- [ ] Implement facial recognition as alternative to RFID
- [ ] Create mobile app for students
- [ ] Add biometric authentication
- [ ] Real-time analytics dashboard
- [ ] Multi-language support
- [ ] RESTful API for third-party integrations

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👥 Authors

- **Kowshik Chowdhury** - [GitHub](https://github.com/Kowshik08004)

## 🙏 Acknowledgments

- CUET ETE-320 Course Project
- MFRC522 Library developers
- Bootstrap framework
- All contributors and testers

## 📧 Contact

For questions or support, please contact:
- **Email**: u2108004@student.cuet.ac.bd
- **Project Link**: [https://github.com/Kowshik08004/ETE-320-project](https://github.com/Kowshik08004/ETE-320-project)

---

<div align="center">

⭐ Star this repo if you find it helpful!

</div>
