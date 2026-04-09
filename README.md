
# 🎓 WUP Portal — Announcement & Events Management System

> A web-based information management portal for **Westleyan University of the Philippines (WUP)**.
> Built with PHP, MySQL, HTML, CSS, and JavaScript.

---

## 📌 Description

WUP Portal is an internal campus portal that allows administrators and teachers to post announcements and manage school events, while students, parents, and staff can view updates in real time. The portal features role-based access control, image uploads, read tracking, and a dynamic dashboard.

---

## ✨ Features

- 🔐 **Secure Login** — Token-based authentication with role-based access (Admin, Teacher, Student, Parent)
- 📢 **Announcements** — Create, edit, pin, archive, and delete announcements with image uploads
- 📅 **Events** — Schedule and manage upcoming school events with date and location
- 👤 **User Management** — Admins can add, edit, deactivate, and delete user accounts
- 🖼️ **Avatar Upload** — Users can upload and update their profile photos
- 📊 **Dashboard** — Live stats showing total, unread, and upcoming events at a glance
- 🔍 **Filtering** — Filter announcements by category, audience, archived status, and keyword search
- 📬 **Read Receipts** — Tracks which users have read each announcement
- 🗂️ **Archive System** — Admins can archive old announcements for record keeping

---

## 🛠️ Technologies Used

| Layer | Technology |
|-------|------------|
| Frontend | HTML5, CSS3, JavaScript (Vanilla) |
| Backend | PHP 8.0+ |
| Database | MySQL 5.7+ |
| Server | Apache (via XAMPP or WAMP) |
| Auth | Token-based session (Bearer Token + PHP Session) |
| Hosting | ByteHost (Live) / XAMPP (Local) |

---

## ⚙️ Installation / Setup Guide

### ✅ Requirements
- [XAMPP](https://www.apachefriends.org/) or [WAMP](https://www.wampserver.com/) installed
- PHP 8.0 or higher
- MySQL 5.7 or higher
- A modern web browser (Chrome, Edge, Firefox)

---

### 🖥️ Local Setup (XAMPP)

**Step 1 — Clone or download the repository**
```
https://github.com/Dee207/wup-portal
```
Or click **Code → Download ZIP** and extract it.

**Step 2 — Move the folder to your server root**
- Copy the project folder to:
  ```
  C:/xampp/htdocs/wup-portal
  ```

**Step 3 — Start Apache and MySQL**
- Open **XAMPP Control Panel**
- Click **Start** on both **Apache** and **MySQL**

**Step 4 — Set up the database**
- Open your browser and go to:
  ```
  http://localhost/phpmyadmin
  ```
- Create a new database named: `wup_db`

**Step 5 — Configure database credentials**
- Open the file: `api/config.php`
- Update the values to match your local MySQL setup:
  ```php
  define('DB_HOST', 'localhost');
  define('DB_NAME', 'wup_db');
  define('DB_USER', 'root');
  define('DB_PASS', '');
  ```

**Step 6 — Run the database installer**
- Visit this URL in your browser (run it only once):
  ```
  http://localhost/wup-portal/api/install.php
  ```
- This will automatically create all tables and seed default data.
- ⚠️ **Delete or rename `install.php` after running it.**

**Step 7 — Open the portal**
```
http://localhost/wup-portal/
```

---

### 🔑 Default Login Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@wup.edu.ph | admin123 |
| Teacher | teacher@wup.edu.ph | teach123 |
| Student | student@wup.edu.ph | stud123 |
| Parent | parent@wup.edu.ph | par123 |

> ⚠️ Change these passwords immediately after your first login in a production environment.

---

## 📁 Project Structure

```
wup-portal/
├── index.php               ← Login page
├── logout.php              ← Session logout handler
├── .htaccess               ← Apache URL and redirect rules
├── api/
│   ├── config.php          ← Database connection & helpers (⚠️ not committed)
│   ├── config.example.php  ← Safe config template for setup
│   ├── auth.php            ← Login / logout / token verify API
│   ├── announcements.php   ← Announcements CRUD API
│   ├── events.php          ← Events CRUD API
│   ├── users.php           ← User management API
│   ├── upload_image.php    ← Announcement image upload API
│   ├── upload_avatar.php   ← User avatar upload API
│   └── install.php         ← One-time database installer
├── pages/
│   └── dashboard.php       ← Main dashboard (admin & student views)
└── assets/
    ├── wup-logo.png
    ├── campus.jpg
    ├── campus-map.jpg
    ├── avatars/            ← User profile pictures (auto-created)
    └── uploads/            ← Announcement images (auto-created)
```

---

## 📸 Screenshots

### Login Page
<img width="1918" height="921" alt="image" src="https://github.com/user-attachments/assets/19ddf3d8-eeff-4f85-984b-02e6b83d792b" />


### Dashboard
<img width="1897" height="866" alt="image" src="https://github.com/user-attachments/assets/5566b047-cc0d-45be-a4ff-a069e88655e2" />


### Announcements
<img width="1902" height="927" alt="image" src="https://github.com/user-attachments/assets/f77c05e0-2e6a-4f6e-8fc7-6e379580e42e" />


---

## 👥 Contributors

| Name | Role |
|------|------|
| David Concepcion   | Lead Developer           |
| Zedrick Buenavidez | Backend Developer|
| Dylan Concepcion   | Frontend & UI            |
| Aaron Magdaleno    | Database & Documentation |

---

## 📄 License

This project was developed as a school requirement for **Bulacan State University**.
For academic use only.
