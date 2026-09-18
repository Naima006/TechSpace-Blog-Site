<?php
include("db.php");

$page = isset($_GET['page']) ? $_GET['page'] : 'home';
$search = "";
$selected_post = null;
$month = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$year  = isset($_GET['year']) ? (int)$_GET['year'] : 0;

$settings_query = mysqli_query($conn, "SELECT * FROM site_settings");
$site = [];
while ($set_row = mysqli_fetch_assoc($settings_query)) {
    $site[$set_row['setting_key']] = $set_row['setting_value'];
}

/** SINGLE POST VIEW COUNT TRACKER MECHANISM */
if (isset($_GET['post'])) {
    $post_id = (int)$_GET['post'];

    // Increment view count column on every individual single-view payload request
    mysqli_query($conn, "UPDATE blog_posts SET view_count = view_count + 1 WHERE post_id = $post_id");

    $single_query = "
        SELECT *
        FROM blog_posts
        JOIN authors
        ON blog_posts.author_id = authors.author_id
        WHERE post_id = $post_id
          AND blog_posts.status = 'published'
    ";

    $single_result = mysqli_query($conn, $single_query);

    if (mysqli_num_rows($single_result) > 0) {
        $selected_post = mysqli_fetch_assoc($single_result);
    }
}

/** 📄 DYNAMIC PAGINATION LAYERING */
// Check if the user selected a custom limit, otherwise default to 3 posts per page
$limit = isset($_GET['per_page']) ? max(1, (int)$_GET['per_page']) : 3;

$current_page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = ($current_page - 1) * $limit;

$count_where = "WHERE blog_posts.status = 'published'";
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_clean = mysqli_real_escape_string($conn, trim($_GET['search']));
    $count_where .= " AND (blog_posts.title LIKE '%$search_clean%' OR authors.author_name LIKE '%$search_clean%')";
} elseif ($month && $year) {
    $count_where .= " AND MONTH(published_date) = $month AND YEAR(published_date) = $year";
}

$total_query = mysqli_query($conn, "SELECT COUNT(*) AS total FROM blog_posts JOIN authors ON blog_posts.author_id = authors.author_id $count_where");
$total_data = mysqli_fetch_assoc($total_query);
$total_pages = ceil($total_data['total'] / $limit);

// Search by Title OR Author Name
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, trim($_GET['search']));
    $query = "
        SELECT *
        FROM blog_posts
        JOIN authors ON blog_posts.author_id = authors.author_id
        WHERE blog_posts.status = 'published'
          AND (blog_posts.title LIKE '%$search%' 
           OR authors.author_name LIKE '%$search%')
        ORDER BY view_count DESC, published_date DESC
        LIMIT $limit OFFSET $offset
    ";
} elseif ($month && $year) {
    $query = "
        SELECT *
        FROM blog_posts
        JOIN authors ON blog_posts.author_id = authors.author_id
        WHERE blog_posts.status = 'published'
        AND MONTH(published_date) = $month
        AND YEAR(published_date) = $year
        ORDER BY view_count DESC, published_date DESC
        LIMIT $limit OFFSET $offset
    ";
} else {
    $query = "
        SELECT *
        FROM blog_posts
        JOIN authors ON blog_posts.author_id = authors.author_id
        WHERE blog_posts.status = 'published'
        ORDER BY view_count DESC, published_date DESC
        LIMIT $limit OFFSET $offset
    ";
}

$result = mysqli_query($conn, $query);

/** SIDEBAR: ONLY CHOOSE ARTICLES THAT HAVE EXCEEDED 500 VIEWS */
$popular = mysqli_query(
    $conn,
    "SELECT * FROM blog_posts WHERE status = 'published' AND view_count > 500 ORDER BY view_count DESC LIMIT 5"
);

/* Archive: last 6 calendar months including the current month (newest first) */
$archive_months = [];
for ($i = 0; $i < 6; $i++) {
    $ts = strtotime("first day of -{$i} month");
    $archive_months[] = [
        'month' => (int) date('n', $ts),
        'year'  => (int) date('Y', $ts),
        'label' => date('F Y', $ts),
    ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TechSpace</title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
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
        <p><?php echo htmlspecialchars($site['site_slogan'] ?? 'Your guide to the digital age'); ?></p>
    </header>

    <nav>
        <ul>
            <li><a href="index.php?page=home" class="<?php echo ($page === 'home' && !isset($_GET['post']) && empty($search) && !$month) ? 'active' : ''; ?>"><i class="fa-solid rel-icon fa-house"></i> Home</a></li>
            <li><a href="index.php?page=about" class="<?php echo ($page === 'about') ? 'active' : ''; ?>"><i class="fa-solid rel-icon fa-circle-info"></i> About</a></li>
            <li><a href="index.php?page=contact" class="<?php echo ($page === 'contact') ? 'active' : ''; ?>"><i class="fa-solid rel-icon fa-envelope"></i> Contact</a></li>
        </ul>
        <div class="nav-right-cluster">
            <form method="GET" action="index.php" class="nav-search-form">
                <div class="search-box">
                    <input
                        type="text"
                        name="search"
                        placeholder="Search..."
                        value="<?php echo htmlspecialchars($search); ?>"
                        aria-label="Search articles">
                    <i class="fa-solid fa-magnifying-glass search-icon" aria-hidden="true"></i>
                </div>
            </form>
            <div class="nav-auth-actions">
                <a href="author/index.php" class="nav-auth-btn" title="Author Login"><i class="fa-solid fa-right-to-bracket"></i> <span>Sign In</span></a>
                <a href="author/index.php?mode=register" class="nav-auth-btn nav-auth-primary" title="Become an Author"><i class="fa-solid fa-user-plus"></i> <span>Register</span></a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="main-content">

        <?php if ($page === 'about') { ?>

            <div class="page-card">
                <h2><?php echo htmlspecialchars($site['about_heading'] ?? 'About TechSpace'); ?></h2>
                <p><?php echo nl2br(htmlspecialchars($site['about_text'] ?? '')); ?></p>
            </div>

        <?php } elseif ($page === 'contact') { ?>

            <div class="page-card">
                <h2><?php echo htmlspecialchars($site['contact_heading'] ?? 'Contact Us'); ?></h2>
                <p><?php echo htmlspecialchars($site['contact_text'] ?? 'Have questions or suggestions?'); ?><br><br></p>

                <div class="contact-info">
                    <div class="contact-item">
                        <i class="fa-solid fa-envelope" style="color: var(--accent-blue);"></i>
                        <a href="mailto:<?php echo htmlspecialchars($site['contact_email'] ?? 'support@techspace.com'); ?>" 
                        style="color: inherit; text-decoration: none; transition: color 0.2s;" 
                        onmouseover="this.style.color='var(--accent-blue)'" 
                        onmouseout="this.style.color='inherit'">
                            <span><?php echo htmlspecialchars($site['contact_email'] ?? 'support@techspace.com'); ?></span>
                        </a>
                    </div>

                    <div class="contact-item">
                        <i class="fa-solid fa-phone"></i>
                        <span><?php echo htmlspecialchars($site['contact_phone'] ?? '+880 1234-567890'); ?></span>
                    </div>

                    <div class="contact-item">
                        <i class="fa-solid fa-location-dot"></i>
                        <span><?php echo htmlspecialchars($site['contact_location'] ?? 'Sylhet, Bangladesh'); ?></span>
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
                    <div class="author" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                        <div style="display: flex; gap: 12px; align-items: center;">
                            <img src="<?php echo $selected_post['avatar_url']; ?>" alt="Author Avatar">
                            <div>
                                <h4><?php echo $selected_post['author_name']; ?></h4>
                                <p class="date"><?php echo date("M d, Y", strtotime($selected_post['published_date'])); ?></p>
                            </div>
                        </div>
                        <div style="color: var(--text-secondary); font-size: 13px; font-weight: 500;">
                            <i class="fa-solid fa-eye" style="margin-right: 5px; color: var(--accent-blue);"></i> <?php echo number_format($selected_post['view_count']); ?> views
                        </div>
                    </div>

                    <h2><?php echo $selected_post['title']; ?></h2>
                    <p class="full-content"><?php echo nl2br($selected_post['content']); ?></p>
                    <br>
                    <a href="index.php" class="show-more-btn">← Back </a>
                </div>
            </div>

        <?php } else { ?>

            <?php
            if (mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
            ?>
                    <a href="index.php?post=<?php echo $row['post_id']; ?>" class="blog-card" style="display: block; text-decoration: none; color: inherit; cursor: pointer;">
                        <div class="cover-wrapper">
                            <img class="cover" src="<?php echo $row['cover_image']; ?>" alt="Blog Cover">
                        </div>

                        <div class="blog-content">
                            <div class="author" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <img src="<?php echo $row['avatar_url']; ?>" alt="Author Avatar">
                                    <div>
                                        <h4><?php echo $row['author_name']; ?></h4>
                                        <p class="date"><?php echo date("M d, Y", strtotime($row['published_date'])); ?></p>
                                    </div>
                                </div>
                                <div style="color: #9ca3af; font-size: 12px;">
                                    <i class="fa-regular fa-eye"></i> <?php echo $row['view_count']; ?>
                                </div>
                            </div>

                            <h2><?php echo $row['title']; ?></h2>
                            <p class="excerpt">
                                <?php
                                echo substr($row['content'], 0, 180);
                                if (strlen($row['content']) > 180) { echo "..."; }
                                ?>
                            </p>
                        </div>
                    </a>
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

        <?php if (!$selected_post) { ?>
            <style type="text/css">
                .glass-pagination-dock * {
                    box-sizing: border-box;
                }
                .glass-pagination-dock .show-more-btn:hover {
                    transform: none !important;
                    border-color: rgba(96, 165, 250, 0.4) !important;
                    background: rgba(96, 165, 250, 0.12) !important;
                    box-shadow: none !important;
                }
                .custom-glass-dropdown {
                    position: relative;
                    display: inline-flex;
                    align-items: center;
                }
                .dropdown-pill-trigger {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    padding: 8px 14px;
                    background: rgba(255, 255, 255, 0.04);
                    border: 1px solid rgba(255, 255, 255, 0.06);
                    border-radius: 20px;
                    font-size: 13px;
                    color: #fff;
                    cursor: pointer;
                    height: 36px;
                    box-sizing: border-box;
                    transition: all 0.2s ease;
                }
                .dropdown-pill-trigger:hover {
                    background: rgba(255, 255, 255, 0.08);
                    border-color: rgba(96, 165, 250, 0.4);
                }
                /* Hidden Dropdown Stack Menu */
                .custom-glass-dropdown .dropdown-glass-menu {
                    position: absolute !important;
                    bottom: calc(100% + 10px) !important; 
                    right: 0 !important;
                    width: 120px !important;
                    background: #0f1422 !important; 
                    border: 1px solid rgba(255, 255, 255, 0.12) !important;
                    border-radius: 12px !important;
                    padding: 6px !important;
                    display: flex !important;
                    flex-direction: column !important;
                    gap: 4px !important;
                    backdrop-filter: blur(20px) !important;
                    -webkit-backdrop-filter: blur(20px) !important;
                    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.6) !important;
                    opacity: 0 !important;
                    visibility: hidden !important;
                    transform: translateY(10px) !important;
                    pointer-events: none !important;
                    transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s ease !important;
                    z-index: 9999 !important;
                    margin: 0 !important;
                }
                /* JavaScript Trigger Toggle State */
                .custom-glass-dropdown.active .dropdown-glass-menu {
                    opacity: 1 !important;
                    visibility: visible !important;
                    transform: translateY(0) !important;
                    pointer-events: auto !important;
                }
                /* Absolute Reset For Menu Buttons */
                .custom-glass-dropdown .dropdown-glass-menu button.dropdown-glass-item {
                    background: transparent !important;
                    border: none !important;
                    border-radius: 8px !important;
                    padding: 8px 12px !important;
                    color: rgba(255, 255, 255, 0.7) !important;
                    font-size: 13px !important;
                    text-align: left !important;
                    cursor: pointer !important;
                    width: 100% !important;
                    display: block !important;
                    font-family: inherit !important;
                    font-weight: 500 !important;
                    box-shadow: none !important;
                    transform: none !important;
                    margin: 0 !important;
                    height: auto !important;
                    line-height: normal !important;
                    transition: all 0.2s ease !important;
                }
                .custom-glass-dropdown .dropdown-glass-menu button.dropdown-glass-item:hover {
                    background: rgba(255, 255, 255, 0.08) !important;
                    color: #fff !important;
                }
                .custom-glass-dropdown .dropdown-glass-menu button.dropdown-glass-item.active-density {
                    background: rgba(96, 165, 250, 0.15) !important;
                    color: #60a5fa !important;
                    font-weight: 600 !important;
                }
            </style>

            <div style="margin-top: 40px; display: flex; justify-content: center; width: 100%;">
                <div class="glass-pagination-dock" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 30px; backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.2); box-sizing: border-box;">
                    
                    <?php if ($total_pages > 1) { 
                        $url_params = $_GET;
                        
                        if ($current_page > 1) { 
                            $url_params['p'] = $current_page - 1;
                            echo '<a href="index.php?' . http_build_query($url_params) . '" class="show-more-btn" style="margin: 0; padding: 8px 16px; border-radius: 20px; font-size: 13px; display: inline-flex; align-items: center; height: 36px; box-sizing: border-box; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06);">&larr; Prev</a>';
                        }

                        for ($i = 1; $i <= $total_pages; $i++) {
                            $url_params['p'] = $i;
                            if ($i === $current_page) {
                                echo '<span class="show-more-btn" style="margin: 0; padding: 8px 14px; border-radius: 50%; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; height: 36px; width: 36px; box-sizing: border-box; background: var(--accent-blue); color: #0b0f19; font-weight: 700; border: 1px solid var(--accent-blue);">' . $i . '</span>';
                            } else {
                                echo '<a href="index.php?' . http_build_query($url_params) . '" class="show-more-btn" style="margin: 0; padding: 8px 14px; border-radius: 50%; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; height: 36px; width: 36px; box-sizing: border-box; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06);">' . $i . '</a>';
                            }
                        }

                        if ($current_page < $total_pages) { 
                            $url_params['p'] = $current_page + 1;
                            echo '<a href="index.php?' . http_build_query($url_params) . '" class="show-more-btn" style="margin: 0; padding: 8px 16px; border-radius: 20px; font-size: 13px; display: inline-flex; align-items: center; height: 36px; box-sizing: border-box; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06);">Next &rarr;</a>';
                        }
                    } ?>

                    <?php if ($total_pages > 1) { ?>
                        <div style="width: 1px; height: 20px; background: rgba(255, 255, 255, 0.15); margin: 0 4px;"></div>
                    <?php } ?>

                    <?php 
                    $clean_get_params = $_GET;
                    unset($clean_get_params['per_page']); 
                    $clean_get_params['p'] = 1; 
                    $base_redirect_url = "index.php?" . http_build_query($clean_get_params);
                    ?>
                    
                    <div class="custom-glass-dropdown" id="paginationDropdownNode">
                        <div class="dropdown-pill-trigger" onclick="toggleGlassMenu(event);">
                            <span><?php echo $limit; ?> / page</span>
                            <i class="fa-solid fa-chevron-down" style="font-size: 11px; color: rgba(255,255,255,0.4);"></i>
                        </div>
                        <div class="dropdown-glass-menu">
                            <button type="button" class="dropdown-glass-item <?php if($limit == 3) echo 'active-density'; ?>" onclick="triggerDensityShift(event, 3);">3 / page</button>
                            <button type="button" class="dropdown-glass-item <?php if($limit == 5) echo 'active-density'; ?>" onclick="triggerDensityShift(event, 5);">5 / page</button>
                            <button type="button" class="dropdown-glass-item <?php if($limit == 10) echo 'active-density'; ?>" onclick="triggerDensityShift(event, 10);">10 / page</button>
                            <button type="button" class="dropdown-glass-item <?php if($limit == 20) echo 'active-density'; ?>" onclick="triggerDensityShift(event, 20);">20 / page</button>
                        </div>
                    </div>

                </div>
            </div>
        <?php } ?>

        </div> 
        
        <div class="sidebar">
            <div class="sidebar-widget">
                <h3><i class="fa-solid fa-fire text-accent"></i> Popular &nbsp Blogs</h3>
                <ul class="popular-list">
                    <?php while ($p = mysqli_fetch_assoc($popular)) { ?>
                        <li>
                            <a href="index.php?post=<?php echo $p['post_id']; ?>">
                                <i class="fa-solid fa-bolt list-bullet"></i>
                                <span style="padding-right: 15px; display: inline-block; max-width: 80%; word-break: break-word;">
                                    <?php echo $p['title']; ?>
                                </span>
                                <small style="float: right; color: var(--text-secondary); font-size: 11px; padding-top: 2px;">
                                    (<?php echo $p['view_count']; ?>)
                                </small>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            </div>

            <div class="sidebar-widget">
                <h3><i class="fa-solid fa-box-archive text-accent"></i> Archive</h3>
                <ul class="archive-list">
                    <?php foreach ($archive_months as $am) { ?>
                    <li>
                        <a href="index.php?month=<?php echo (int)$am['month']; ?>&year=<?php echo (int)$am['year']; ?>"
                           class="<?php echo ($month === (int)$am['month'] && $year === (int)$am['year']) ? 'active' : ''; ?>">
                            <i class="fa-regular fa-calendar-days list-bullet"></i>
                            <?php echo htmlspecialchars($am['label']); ?>
                        </a>
                    </li>
                    <?php } ?>
                </ul>
            </div>
        </div>
    </div>

    <section class="cta-section">
        <div class="cta-card">
            <h2><?php echo htmlspecialchars($site['cta_heading'] ?? 'Stay Updated With The Latest Technology Trends'); ?></h2>
            <p><?php echo htmlspecialchars($site['cta_text'] ?? 'Explore insightful articles on Artificial Intelligence, Cybersecurity, Software Development, and emerging technologies shaping the future.'); ?></p>
            <div class="cta-buttons">
                <a href="index.php" class="cta-primary"><i class="fa-solid fa-book-open"></i> Explore Blogs</a>
                <a href="#footer-socials" class="cta-secondary"><i class="fa-solid fa-share-nodes"></i> Follow Us</a>
            </div>
        </div>
    </section>

    <footer>
        <div class="footer-grid">
            <p class="copyright">&copy; 2026 <span class="text-glow">TechSpace</span></p>
            <div class="social-block">
                <span class="social-title">Connect</span>
                <div class="social-links" id="footer-socials">
                    <a href="https://www.facebook.com" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://twitter.com" title="Twitter"><i class="fab fa-x-twitter"></i></a>
                    <a href="https://www.linkedin.com/in/naima-rahman-176196308/" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <script type="text/javascript">
    function toggleGlassMenu(event) {
        event.stopPropagation();
        const dropdownNode = document.getElementById("paginationDropdownNode");
        if (dropdownNode) {
            dropdownNode.classList.toggle('active');
        }
    }

    function triggerDensityShift(event, customLimit) {
        event.stopPropagation();
        localStorage.setItem('paginationScrollPos', window.scrollY);
        window.location.href = '<?php echo $base_redirect_url; ?>&per_page=' + customLimit;
    }

    document.addEventListener("DOMContentLoaded", function() {
        // 1. SYSTEM SCROLL HEIGHT RESTORATION
        const savedScrollPosition = localStorage.getItem('paginationScrollPos');
        if (savedScrollPosition !== null) {
            window.scrollTo(0, parseInt(savedScrollPosition));
            localStorage.removeItem('paginationScrollPos');
        }

        // 2. BACKDROP GLOBAL OUTSIDE CLICKS MENU CLOSER
        window.addEventListener("click", function() {
            const dropdownNode = document.getElementById("paginationDropdownNode");
            if (dropdownNode) {
                dropdownNode.classList.remove("active");
            }
        });

        // 3. RELIABLE BACKDROP DISMISS LISTENER VIA PHP CONDITIONAL DETECTOR
        const isSinglePostActive = <?php echo $selected_post ? 'true' : 'false'; ?>;
        
        if (isSinglePostActive) {
            const bodyCanvas = document.body;
            bodyCanvas.style.cursor = "pointer";
            
            bodyCanvas.addEventListener("click", function(event) {
                const clickTarget = event.target;
                
                // Track structural layout boundaries safely across nested blocks
                const clickedOnCard = clickTarget.closest('.blog-card');
                const clickedOnSidebar = clickTarget.closest('.sidebar');
                const clickedOnHeader = clickTarget.closest('header');
                const clickedOnNav = clickTarget.closest('nav');
                
                // If clicked outside all structural items, return to index listings
                if (!clickedOnCard && !clickedOnSidebar && !clickedOnHeader && !clickedOnNav) {
                    window.location.href = "index.php";
                }
            });
        }
    });
    </script>



</body>
</html>