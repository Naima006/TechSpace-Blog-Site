<?php
include("db.php");

$page = isset($_GET['page']) ? $_GET['page'] : 'home';
$search = "";
$selected_post = null;
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$year  = isset($_GET['year']) ? (int)$_GET['year'] : 0;

if (isset($_GET['post'])) {

    $post_id = (int)$_GET['post'];

    $single_query = "
        SELECT *
        FROM blog_posts
        JOIN authors
        ON blog_posts.author_id = authors.author_id
        WHERE post_id = $post_id
    ";

    $single_result = mysqli_query($conn, $single_query);

    if (mysqli_num_rows($single_result) > 0) {
        $selected_post = mysqli_fetch_assoc($single_result);
    }
}

if (isset($_GET['search']) && !empty($_GET['search'])) {

    $search = mysqli_real_escape_string($conn, $_GET['search']);

    $query = "
        SELECT *
        FROM blog_posts
        JOIN authors
        ON blog_posts.author_id = authors.author_id
        WHERE title LIKE '%$search%'
        ORDER BY published_date DESC
    ";

} elseif ($month && $year) {

    $query = "
        SELECT *
        FROM blog_posts
        JOIN authors
        ON blog_posts.author_id = authors.author_id
        WHERE MONTH(published_date) = $month
        AND YEAR(published_date) = $year
        ORDER BY published_date DESC
    ";

} else {

    $query = "
        SELECT *
        FROM blog_posts
        JOIN authors
        ON blog_posts.author_id = authors.author_id
        ORDER BY published_date DESC
    ";
}

$result = mysqli_query($conn, $query);

$popular = mysqli_query(
    $conn,
    "SELECT * FROM blog_posts WHERE is_popular = 1 LIMIT 5"
);
$archives = mysqli_query(
    $conn,
    "SELECT
        post_id,
        title,
        DATE_FORMAT(published_date, '%M %Y') AS archive_month
        FROM blog_posts
        ORDER BY published_date DESC"
);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechSpace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <div class="neon-glow glow-1"></div>
    <div class="neon-glow glow-2"></div>

    <header>
        <div class="logo-container">
            <i class="fa-solid fa-microchip logo-icon"></i>
            <h1><a href="index.php?page=home">TechSpace</a></h1>
        </div>
        <p>Your guide to the digital age</p>
    </header>

    <nav>
        <ul>
            <li><a href="index.php?page=home" class="active"><i class="fa-solid rel-icon fa-house"></i> Home</a></li>
            <li><a href="index.php?page=about"><i class="fa-solid rel-icon fa-circle-info"></i> About</a></li>
            <li><a href="index.php?page=contact"><i class="fa-solid rel-icon fa-envelope"></i> Contact</a></li>
        </ul>
        <form method="GET" action="index.php">
            <div class="search-box">
                <input
                    type="text"
                    name="search"
                    placeholder="Search premium articles..."
                    value="<?php echo htmlspecialchars($search); ?>">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
            </div>
        </form>
    </nav>

    <div class="container">
        <div class="main-content">

        <?php if ($page === 'about') { ?>

            <div class="page-card">
                <h2>About TechSpace</h2>
                <p>
                    TechSpace is a modern tech blog platform designed to share insights about Artificial Intelligence,
                    software engineering, cybersecurity, and emerging technologies. Our goal is to make complex technology topics simple, readable, and engaging for everyone.
                </p>
            </div>

        <?php } elseif ($page === 'contact') { ?>

            <div class="page-card">
                <h2>Contact Us</h2>

                <p>
                    Have questions or suggestions?<br><br>
                </p>

                <div class="contact-info">
                    <div class="contact-item">
                        <i class="fa-solid fa-envelope"></i>
                        <span>support@techspace.com</span>
                    </div>

                    <div class="contact-item">
                        <i class="fa-solid fa-phone"></i>
                        <span>+880 1234-567890</span>
                    </div>

                    <div class="contact-item">
                        <i class="fa-solid fa-location-dot"></i>
                        <span>Sylhet, Bangladesh</span>
                    </div>
                </div>
            </div>

        <?php } elseif ($selected_post) { ?>

            <div class="blog-card">
                <div class="cover-wrapper">
                    <img
                        class="cover"
                        src="<?php echo $selected_post['cover_image']; ?>"
                        alt="Blog Cover">
                </div>

                <div class="blog-content">
                    <div class="author">
                        <img
                            src="<?php echo $selected_post['avatar_url']; ?>"
                            alt="Author Avatar">
                        <div>
                            <h4><?php echo $selected_post['author_name']; ?></h4>
                            <p class="date">
                                <?php echo date("M d, Y", strtotime($selected_post['published_date'])); ?>
                            </p>
                        </div>
                    </div>

                    <h2><?php echo $selected_post['title']; ?></h2>

                    <p class="full-content">
                        <?php echo nl2br($selected_post['content']); ?>
                    </p>

                    <br>

                    <a href="index.php" class="show-more-btn">
                        ← Back To All Posts
                    </a>
                </div>
            </div>

        <?php } else { ?>

            <?php
            if (mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
            ?>

                    <div class="blog-card">
                        <div class="cover-wrapper">
                            <img
                                class="cover"
                                src="<?php echo $row['cover_image']; ?>"
                                alt="Blog Cover">
                        </div>

                        <div class="blog-content">
                            <div class="author">
                                <img
                                    src="<?php echo $row['avatar_url']; ?>"
                                    alt="Author Avatar">
                                <div>
                                    <h4><?php echo $row['author_name']; ?></h4>
                                    <p class="date">
                                        <?php echo date("M d, Y", strtotime($row['published_date'])); ?>
                                    </p>
                                </div>
                            </div>

                            <h2><?php echo $row['title']; ?></h2>

                            <p class="excerpt">
                                <?php
                                echo substr($row['content'], 0, 180);

                                if (strlen($row['content']) > 180) {
                                    echo "...";
                                }
                                ?>
                            </p>

                            <a
                                href="index.php?post=<?php echo $row['post_id']; ?>"
                                class="show-more-btn">
                                Show More
                            </a>
                        </div>
                    </div>

            <?php
                }
            } else {
                echo "
                <div class='no-results'>
                    <i class='fa-solid fa-ghost'></i>
                    <p>No matching tech nodes found in database.</p>
                </div>";
            }
            ?>

        <?php } ?>

        </div>

        <div class="sidebar">
            <div class="sidebar-widget">
                <h3><i class="fa-solid fa-fire text-accent"></i> Popular Blogs</h3>
                <ul class="popular-list">
                    <?php while ($p = mysqli_fetch_assoc($popular)) { ?>
                        <li>
                            <a href="index.php?post=<?php echo $p['post_id']; ?>">
                                <i class="fa-solid fa-bolt list-bullet"></i>
                                <span><?php echo $p['title']; ?></span>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            </div>

            <div class="sidebar-widget">
                <h3><i class="fa-solid fa-box-archive text-accent"></i> Archive</h3>
                <ul class="archive-list">

                    <li>
                        <a href="index.php?month=4&year=2026">
                            <i class="fa-regular fa-calendar-days list-bullet"></i> April 2026
                        </a>
                    </li>

                    <li>
                        <a href="index.php?month=3&year=2026">
                            <i class="fa-regular fa-calendar-days list-bullet"></i> March 2026
                        </a>
                    </li>

                    <li>
                        <a href="index.php?month=2&year=2026">
                            <i class="fa-regular fa-calendar-days list-bullet"></i> February 2026
                        </a>
                    </li>

                    <li>
                        <a href="index.php?month=1&year=2026">
                            <i class="fa-regular fa-calendar-days list-bullet"></i> January 2026
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <section class="cta-section">
        <div class="cta-card">
            <h2>Stay Updated With The Latest Technology Trends</h2>

            <p>
                Explore insightful articles on Artificial Intelligence,
                Cybersecurity, Software Development,
                and emerging technologies shaping the future.
            </p>

            <div class="cta-buttons">
                <a href="index.php" class="cta-primary">
                    <i class="fa-solid fa-book-open"></i>
                    Explore Blogs
                </a>

                <a href="#footer-socials" class="cta-secondary">
                    <i class="fa-solid fa-share-nodes"></i>
                    Follow Us
                </a>
            </div>
        </div>
    </section>

    <footer>
        <div class="footer-grid">
            <p class="copyright">
                &copy; 2026 <span class="text-glow">TechSpace</span>
            </p>
            <div class="social-block">
                <span class="social-title">Connect</span>
                <div class="social-links" id="footer-socials">
                    <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" title="Twitter"><i class="fab fa-x-twitter"></i></a>
                    <a href="#" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
        </div>
    </footer>

</body>

</html>