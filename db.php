<?php
/**
 * TechSpace - Database Connection + Auto Setup
 * --------------------------------------------
 * Connects to MySQL and ensures the database, tables, and
 * essential default records exist. Existing data is never
 * overwritten or deleted.
 *
 * Tables required by the current codebase:
 *   - admins        (admin_id, username, password)
 *   - authors       (author_id, author_name, avatar_url)
 *   - blog_posts    (post_id, title, cover_image, content,
 *                    published_date, is_popular, author_id, view_count)
 *   - site_settings (setting_key, setting_value)
 */

$servername = "localhost";
$username   = "root";
$password   = "";
$database   = "db_blog";

// 1. Connect to MySQL server (without selecting a database yet)
$conn = @mysqli_connect($servername, $username, $password);

if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}

// 2. Create the database if it does not exist
mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// 3. Select the database
if (!mysqli_select_db($conn, $database)) {
    die("Could not select database `$database`: " . mysqli_error($conn));
}

// 4. Create tables if they do not exist (IF NOT EXISTS = safe for existing data)

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS `admins` (
        `admin_id` INT AUTO_INCREMENT PRIMARY KEY,
        `username` VARCHAR(100) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS `authors` (
        `author_id` INT AUTO_INCREMENT PRIMARY KEY,
        `author_name` VARCHAR(150) NOT NULL,
        `avatar_url` VARCHAR(255) DEFAULT 'avatar1.png'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS `blog_posts` (
        `post_id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `cover_image` VARCHAR(255) DEFAULT 'blog1.png',
        `content` TEXT NOT NULL,
        `published_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `is_popular` TINYINT(1) DEFAULT 0,
        `author_id` INT NOT NULL,
        `view_count` INT UNSIGNED DEFAULT 0,
        INDEX (`author_id`),
        INDEX (`published_date`),
        INDEX (`view_count`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS `site_settings` (
        `setting_key` VARCHAR(100) NOT NULL PRIMARY KEY,
        `setting_value` TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 5. Seed default admin only if no admins exist
$admin_check = mysqli_query($conn, "SELECT admin_id FROM admins LIMIT 1");
if ($admin_check && mysqli_num_rows($admin_check) === 0) {
    // Default credentials (kept simple for internship project):
    // Username: admin
    // Password: admin321
    mysqli_query($conn, "
        INSERT INTO admins (username, password)
        VALUES ('admin', 'admin321')
    ");
}

// 6. Seed default authors only if none exist
$author_check = mysqli_query($conn, "SELECT author_id FROM authors LIMIT 1");
if ($author_check && mysqli_num_rows($author_check) === 0) {
    mysqli_query($conn, "
        INSERT INTO authors (author_name, avatar_url) VALUES
        ('John Doe', 'avatar1.png'),
        ('Jane Doe', 'avatar2.png'),
        ('Jack Doe', 'avatar3.png')
    ");
}

// 7. Seed default site settings only for missing keys
//    (uses INSERT IGNORE so existing values are never overwritten)
$default_settings = [
    'site_slogan'      => 'Your guide to the digital age',
    'about_heading'    => 'About TechSpace',
    'about_text'       => "TechSpace is a modern technology blog dedicated to sharing insights on Artificial Intelligence, Cybersecurity, Software Development, and emerging tech trends.\n\nBuilt during a Software Development Internship at IT Lab Solutions Ltd., Sylhet.",
    'contact_heading'  => 'Contact Us',
    'contact_text'     => 'Have questions or suggestions? We would love to hear from you.',
    'contact_email'    => 'support@techspace.com',
    'contact_phone'    => '+880 1234-567890',
    'contact_location' => 'Sylhet, Bangladesh',
    'cta_heading'      => 'Stay Updated With The Latest Technology Trends',
    'cta_text'         => 'Explore insightful articles on Artificial Intelligence, Cybersecurity, Software Development, and emerging technologies shaping the future.'
];

foreach ($default_settings as $key => $value) {
    $safe_key   = mysqli_real_escape_string($conn, $key);
    $safe_value = mysqli_real_escape_string($conn, $value);
    // Only insert if the key does not already exist
    mysqli_query($conn, "
        INSERT IGNORE INTO site_settings (setting_key, setting_value)
        VALUES ('$safe_key', '$safe_value')
    ");
}

// 8. Optional: seed one sample post only when the posts table is completely empty
//    (so a fresh install is not blank; existing posts are left untouched)
$post_check = mysqli_query($conn, "SELECT post_id FROM blog_posts LIMIT 1");
if ($post_check && mysqli_num_rows($post_check) === 0) {
    // Pick the first author (John Doe) if available
    $first_author = mysqli_query($conn, "SELECT author_id FROM authors ORDER BY author_id ASC LIMIT 1");
    if ($first_author && $row = mysqli_fetch_assoc($first_author)) {
        $aid = (int)$row['author_id'];
        $sample_title   = mysqli_real_escape_string($conn, 'Welcome to TechSpace');
        $sample_content = mysqli_real_escape_string($conn,
            "Welcome to TechSpace — your guide to the digital age.\n\n" .
            "This is a sample post created automatically on first setup. " .
            "You can edit or delete it from the Admin Dashboard, and add your own articles anytime.\n\n" .
            "Features already working on this site:\n" .
            "• Dynamic blog posts with cover images\n" .
            "• Author profiles and avatars\n" .
            "• Search by title or author name\n" .
            "• Pagination and archives\n" .
            "• View counters and popular posts\n" .
            "• Editable site settings from the admin panel\n\n" .
            "Happy coding!"
        );
        mysqli_query($conn, "
            INSERT INTO blog_posts (title, cover_image, content, author_id, is_popular, view_count)
            VALUES ('$sample_title', 'blog1.png', '$sample_content', $aid, 0, 0)
        ");
    }
}

?>
