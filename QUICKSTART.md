# 🚀 Quick Start Guide

Get your RFID Attendance System up and running in 10 minutes!

## Prerequisites

✅ XAMPP/WAMP/LAMP installed  
✅ Web browser (Chrome, Firefox, Edge)  
✅ Basic knowledge of PHP and MySQL

## Installation (5 Steps)

### 1️⃣ Clone Repository

```bash
git clone https://github.com/Kowshik08004/ETE-320-project.git
cd ETE-320-project
```

### 2️⃣ Setup Database

1. Start XAMPP/WAMP
2. Open http://localhost/phpmyadmin
3. Create database: `rfidattendance`
4. Import file: `rfidattendance.sql`

### 3️⃣ Configure Connection

```bash
cp connectDB.example.php connectDB.php
```

Edit `connectDB.php` with your MySQL credentials:
```php
$servername = "localhost";
$username = "root";        // Your MySQL username
$password = "";            // Your MySQL password
$dbname = "rfidattendance";
```

### 4️⃣ Deploy Application

Copy project folder to web root:
- **Windows (XAMPP)**: `C:\xampp\htdocs\`
- **Mac**: `/Applications/XAMPP/htdocs/`
- **Linux**: `/var/www/html/`

### 5️⃣ Access Application

Open browser → `http://localhost/ETE-320-project`

**Default Login:**
- Email: `admin@gmail.com`
- Password: `123`

## What's Next?

1. ✅ Change admin password
2. ✅ Add students
3. ✅ Create courses
4. ✅ Setup RFID devices
5. ✅ Start recording attendance!

## Need Help?

- 📖 Read the full [README.md](README.md)
- 🐛 [Report Issues](https://github.com/YOUR-USERNAME/ETE-320-project/issues)
- 💬 Start a [Discussion](https://github.com/YOUR-USERNAME/ETE-320-project/discussions)

## Hardware Setup (Optional)

Got RFID hardware? Follow the [Hardware Setup Guide](README.md#-hardware-setup-optional)

---

**Enjoy using the system! ⭐ Don't forget to star the repo!**
