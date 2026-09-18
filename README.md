# TechSpace

A modern technology blog built with PHP and MySQL.

**Live Demo:** https://techspace-blogsite.infinityfreeapp.com/blog-site

---

## Overview

TechSpace is a content platform for technology articles with a public reader site, an admin panel, and an author workspace.

Authors can register, submit posts for review, and manage their own articles. Admins can manage authors, moderate content, publish posts, and control website content and settings.

The interface uses a dark glass-style design with responsive support for desktop and mobile devices.

---

## Features

### Public Site

* Home feed with cover images, authors, dates, and view counts
* Individual article pages with view tracking
* Search by post title or author name
* About and Contact pages with editable content
* Archives by month
* Pagination with customizable posts per page
* Popular posts based on view count
* Responsive design for desktop and mobile

### Admin Panel

* Admin login with session-based authentication
* Automatic session timeout
* Create, edit, delete, and publish blog posts
* Live preview while writing or editing posts
* Author management
* Approve or reject author registrations
* Edit and delete authors
* Author avatar management
* Blog cover image uploads
* Website settings editor
* Live settings preview
* Dashboard analytics
* Post search by title or author
* View statistics

### Author Workspace

* Public author registration
* Author sign-in
* Approval workflow before posting
* Create and edit personal articles
* Articles submitted as pending until approved
* View full article details
* Personal post statistics
* Search personal posts

---

## Tech Stack

| Layer             | Technology                        |
| ----------------- | --------------------------------- |
| Backend           | PHP (Procedural)                  |
| Database          | MySQL / MariaDB                   |
| Frontend          | HTML5, CSS3, JavaScript           |
| Icons             | Font Awesome                      |
| Fonts             | Google Fonts – Plus Jakarta Sans  |
| Local Environment | XAMPP (Apache, MySQL, phpMyAdmin) |

No frameworks such as Laravel, React, or Vue are used. The project is intentionally kept simple for learning purpose.

---

## Project Structure

```text
blog-site/
├── index.php              # Public website
├── db.php                 # Database connection and auto schema setup
├── style.css              # Public website styles
├── favicon.svg
│
├── admin/
│   ├── index.php          # Admin login
│   ├── dashboard.php      # Admin dashboard and management
│   └── admin.css          # Admin styles
│
└── author/
    ├── index.php          # Author login and registration
    └── dashboard.php      # Author workspace
```

Uploaded blog cover images and author avatars are stored within the project files.

---

## Database

The application uses a MySQL/MariaDB database named `db_blog`.

On first setup, `db.php` automatically creates the required database tables and default records when they are missing. Existing data is not intentionally deleted during normal page loads.

### Main Tables

* `admins` – administrator accounts
* `authors` – author accounts, profiles, and approval status
* `blog_posts` – blog articles, authors, views, and publication information
* `site_settings` – editable website content such as slogan, About, Contact, and CTA sections

### Default Admin

For the initial local setup:

* **Username:** `admin`
* **Password:** `admin`

---

## Local Setup

### Requirements

Make sure you have:

* XAMPP
* Apache
* MySQL
* A web browser

### Installation

1. Install [XAMPP](https://www.apachefriends.org/) if it is not already installed.

2. Start **Apache** and **MySQL** from the XAMPP Control Panel.

3. Copy the project folder into:

```text
C:\xampp\htdocs\blog-site
```

4. Open the public website:

http://localhost/blog-site/

5. On the first load, `db.php` will initialize the `db_blog` database and required tables if they do not already exist.

6. Open the Admin Panel:

http://localhost/blog-site/admin/

7. Open the Author Workspace:

http://localhost/blog-site/author/

### phpMyAdmin

You can inspect the database through:

http://localhost/phpmyadmin

The application database is:

```text
db_blog
```

---

## Roles

| Role    | Capabilities                                                 |
| ------- | ------------------------------------------------------------ |
| Visitor | Read posts, search articles, register as an author           |
| Author  | Create and edit own articles after approval                  |
| Admin   | Manage posts, authors, approvals, settings, and site content |

---

## Deployment

A hosted version of TechSpace is available at:

https://techspace-blogsite.infinityfreeapp.com/blog-site

For deployment:

1. Upload the project files to the hosting server.
2. Create a MySQL database and database user through the hosting control panel.
3. Update the database connection details in `db.php`.
4. Make sure the required upload directories are writable.
5. Change the default admin credentials before making the site publicly available.
6. Test the public site, admin panel, author workspace, database operations, and image uploads.

---

## Screenshots

Screenshots can be added to a `screenshots/` folder and referenced here.

Example:

```markdown
![Home](screenshots/home.png)
![Admin Dashboard](screenshots/admin.png)
![Author Dashboard](screenshots/author.png)
```

---

## What I Learned

* Building a complete request lifecycle using PHP and MySQL
* Working with relational database tables and relationships
* Implementing visitor, author, and admin workflows
* Session handling and access control
* Building CRUD functionality
* Implementing content moderation and approval workflows
* Creating responsive interfaces with a consistent design system
* Working with file uploads for blog covers and author avatars
* Implementing search, pagination, archives, and view tracking
* Creating an auto-initializing database setup for easier project installation

---

## Future Improvements

* Password hashing with `password_hash()` and `password_verify()`
* Prepared statements across database queries
* Email verification for author registration
* Improved image storage and file validation
* Automated database backup and restore
* Additional security improvements for production deployment

---

## License

Copyright © 2026 Naima Rahman. All rights reserved.

This project is publicly available for viewing on GitHub, but the source code may not be copied, modified, redistributed, or used in other projects without prior written permission from the author.

---

## Contact

**Naima Rahman**

* **GitHub:** https://github.com/Naima006
* **LinkedIn:** https://linkedin.com/in/naima-rahman-176196308
* **Live Demo:** https://techspace-blogsite.infinityfreeapp.com/blog-site
