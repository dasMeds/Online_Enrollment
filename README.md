# Online Enrollment System

A legacy PHP-based online enrollment portal for a college or school. The system allows students to register, view their profile, and submit enrollment forms, while administrators can manage accounts, subjects, and course-related records.

This project was built around a MySQL database and classic PHP scripts, with separate sections for admin and student access.

## Project Overview

The application includes two primary user roles:

- Admin: manages users and academic records
- Student: logs in, views profile info, and enrolls in subjects

The app is designed around a local web server environment using Apache/PHP and MySQL.

## Main Features

### Student Features
- User login
- Student dashboard
- View personal profile
- Change password
- Select subjects for enrollment
- Generate and submit an enrollment form
- Review semester and course information

### Admin Features
- Admin login
- Dashboard homepage for administrators
- Manage users and student accounts
- Add new subjects
- Add and manage course-related sections
- View reports and account information

## Tech Stack

- PHP (legacy procedural style)
- MySQL
- HTML/CSS
- JavaScript/jQuery (used in some pages)

## Project Structure

```text
Online_Enrollment/
├── Admin/
│   ├── addnewsched.php
│   ├── addnewsection.php
│   ├── addnewsubj.php
│   ├── addnewuser.php
│   ├── adminindex.php
│   ├── adminedit.php
│   ├── deletecurrsubj.php
│   ├── deletecurruser.php
│   ├── logcheck.php
│   ├── style.css
│   ├── subjectreg.php
│   ├── userreg.php
│   ├── css/
│   ├── includes/
│   └── js/
├── student/
│   ├── changepass.php
│   ├── enroll.php
│   ├── logcheck.php
│   ├── showsubs.php
│   ├── studentindex.php
│   ├── studentprofile.php
│   ├── style.css
│   ├── includes/
│   └── js/
├── styles/
├── conn.php
├── index.php
├── logo.jpg
├── online_enroll (2).sql
└── README.md
```

## Database

The application uses a MySQL database named `online_enroll`.

The SQL dump file `online_enroll (2).sql` contains the schema and sample data. Key tables include:

- `usertbl`
- `studenttbl`
- `subjecttbl`
- `student_subject`
- `sections_tbl`
- `sched_tbl`
- `courses_tbl`

## Default Accounts

The system includes sample account data in the SQL script:

- Admin username: `admins`
- Admin password: `admins`
- Student username: `dennis`
- Student password: `123456`

## Setup Instructions

1. Install Apache + PHP + MySQL.
2. Start the MySQL service.
3. Create a database named `online_enroll`.
4. Import the SQL dump file:
   ```bash
   mysql -u root -p online_enroll < "online_enroll (2).sql"
   ```
5. Place the project folder in your web server root, such as:
   - `htdocs` for XAMPP
   - `www` for WAMP
6. Open the project in a browser:
   ```text
   http://localhost/Online_Enrollment/
   ```
7. Log in with one of the sample accounts above.

## Database Connection

The database connection is defined in `conn.php` and uses a local MySQL setup:

```php
mysql_connect("localhost","root","");
mysql_select_db("online_enroll");
```

Because this is a legacy app, it relies on older deprecated MySQL APIs (`mysql_connect`, `mysql_query`, etc.).

## Notes

- This project is a school/demo system and is not production-grade.
- The app uses hardcoded database credentials and legacy SQL functions.
- It was designed for older PHP/MySQL environments.
- For a modern upgrade, the code should be migrated to `mysqli` or `PDO` and protected against SQL injection and session issues.

## Typical Workflow

1. User opens the login page from `index.php`.
2. The login script checks `usertbl` for the username and password.
3. Based on `user_type`, the user is redirected to either the admin area or the student area.
4. Students can view profile data and select subjects for enrollment.
5. Selected subjects are stored in `student_subject` and can be submitted as an enrollment form.

## License

This project is for educational use and appears to be a class assignment or demo application.

## Summary

This repository is a compact enrollment management system for a college environment, built with classic PHP and MySQL, and structured around admin and student workflows. It is useful as a learning project or a starting point for a modern enrollment portal.
