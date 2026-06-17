<?php
session_start();
require_once __DIR__ . '/../db.php';

/** 🔒 SECURE SESSION MANAGEMENT TIMEOUT LAYER */
if (isset($_SESSION['admin_id'])) {
    $current_timestamp = time();
    $timeout_duration = 600; // (10min * 60)

    if (isset($_SESSION['last_activity'])) {
        $seconds_idle = $current_timestamp - $_SESSION['last_activity'];
        if ($seconds_idle >= $timeout_duration) {
            session_unset();
            session_destroy();
            header("Location: index.php?session_expired=1");
            exit();
        }
    }
    $_SESSION['last_activity'] = $current_timestamp;
} else {
    header("Location: index.php");
    exit();
}

/** Logout & Secure Clear Action */
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

/** DELETE POST */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM blog_posts WHERE post_id=$id");
    header("Location: dashboard.php");
    exit();
}

/** DELETE AUTHOR DIRECTLY */
if (isset($_GET['delete_author'])) {
    $auth_id = (int)$_GET['delete_author'];
    $check_posts = mysqli_query($conn, "SELECT post_id FROM blog_posts WHERE author_id=$auth_id");
    if (mysqli_num_rows($check_posts) == 0) {
        mysqli_query($conn, "DELETE FROM authors WHERE author_id=$auth_id");
    }
    header("Location: dashboard.php?action_mode=authors_panel");
    exit();
}

/** HELPER FUNCTION FOR LOCAL FILE UPLOADS */
function process_image_upload($file_array) {
    if (isset($file_array) && $file_array['error'] === UPLOAD_ERR_OK) {
        $source_path = $file_array['tmp_name'];
        $original_name = basename($file_array['name']);
        $clean_name = time() . "_" . preg_replace("/[^A-Za-z0-9.\-_]/", "", $original_name);
        
        $target_directory = __DIR__ . '/../';
        $destination_path = $target_directory . $clean_name;

        if (move_uploaded_file($source_path, $destination_path)) {
            return $clean_name;
        }
    }
    return null;
}

/** ADD AUTHOR DIRECTLY */
if (isset($_POST['add_author'])) {
    $auth_name = mysqli_real_escape_string($conn, $_POST['author_name']);
    $auth_avatar = process_image_upload($_FILES['author_avatar_file']);
    if (!$auth_avatar) { $auth_avatar = 'avatar1.png'; }
    
    if (!empty($auth_name)) {
        mysqli_query($conn, "INSERT INTO authors (author_name, avatar_url) VALUES ('$auth_name', '$auth_avatar')");
    }
    header("Location: dashboard.php?action_mode=authors_panel");
    exit();
}

/** UPDATE AUTHOR PROPERTIES DIRECTLY */
if (isset($_POST['update_author'])) {
    $auth_id = (int)$_POST['author_id'];
    $auth_name = mysqli_real_escape_string($conn, $_POST['author_name']);
    
    $new_avatar = process_image_upload($_FILES['author_avatar_file']);
    $avatar_update_sql = $new_avatar ? ", avatar_url='$new_avatar'" : "";
    
    if (!empty($auth_name)) {
        mysqli_query($conn, "UPDATE authors SET author_name='$auth_name' $avatar_update_sql WHERE author_id=$auth_id");
    }
    header("Location: dashboard.php?action_mode=authors_panel");
    exit();
}

/** UPDATE DYNAMIC WEBSITE SETTINGS LAYER */
if (isset($_POST['save_settings'])) {
    foreach ($_POST['settings'] as $key => $value) {
        $safe_key = mysqli_real_escape_string($conn, $key);
        $safe_value = mysqli_real_escape_string($conn, $value);
        mysqli_query($conn, "INSERT INTO site_settings (setting_key, setting_value) VALUES ('$safe_key', '$safe_value') ON DUPLICATE KEY UPDATE setting_value='$safe_value'");
    }
    header("Location: dashboard.php");
    exit();
}

/** ADD POST */
if (isset($_POST['add_post'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $author_id = (int)$_POST['author_id'];
    $is_popular = isset($_POST['is_popular']) ? 1 : 0;

    $cover = process_image_upload($_FILES['cover_image_file']);
    $post_avatar = process_image_upload($_FILES['post_author_avatar_file']);

    if (!$cover) { $cover = 'blog1.png'; }

    mysqli_query($conn,
        "INSERT INTO blog_posts 
        (title, cover_image, content, author_id, is_popular) 
        VALUES 
        ('$title','$cover','$content',$author_id,$is_popular)"
    );

    if ($post_avatar) {
        mysqli_query($conn, "UPDATE authors SET avatar_url='$post_avatar' WHERE author_id=$author_id");
    }

    header("Location: dashboard.php");
    exit();
}

/** EDIT POST (load state context) */
$edit_post = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $res = mysqli_query($conn, "
        SELECT blog_posts.*, authors.avatar_url 
        FROM blog_posts 
        JOIN authors ON blog_posts.author_id = authors.author_id 
        WHERE post_id=$id
    ");
    $edit_post = mysqli_fetch_assoc($res);
}

/** EDIT AUTHOR CONTEXT FETCH */
$edit_author_ctx = null;
if (isset($_GET['edit_author'])) {
    $id = (int)$_GET['edit_author'];
    $res = mysqli_query($conn, "SELECT * FROM authors WHERE author_id=$id");
    $edit_author_ctx = mysqli_fetch_assoc($res);
}

/** INSPECT/VIEW POST ENGINE LOOP */
$view_post = null;
if (isset($_GET['view'])) {
    $id = (int)$_GET['view'];
    $res = mysqli_query($conn, "
        SELECT blog_posts.*, authors.author_name, authors.avatar_url 
        FROM blog_posts 
        JOIN authors ON blog_posts.author_id = authors.author_id 
        WHERE post_id=$id
    ");
    $view_post = mysqli_fetch_assoc($res);
}

/** UPDATE POST */
if (isset($_POST['update_post'])) {
    $id = (int)$_POST['post_id'];
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $author_id = (int)$_POST['author_id'];
    $is_popular = isset($_POST['is_popular']) ? 1 : 0;

    $new_cover = process_image_upload($_FILES['cover_image_file']);
    $new_avatar = process_image_upload($_FILES['post_author_avatar_file']);

    $cover_condition_update = $new_cover ? "cover_image='$new_cover'," : "";

    mysqli_query($conn,
        "UPDATE blog_posts SET 
        title='$title', 
        content='$content', 
        $cover_condition_update
        author_id=$author_id, 
        is_popular=$is_popular 
        WHERE post_id=$id"
    );

    if ($new_avatar) {
        mysqli_query($conn, "UPDATE authors SET avatar_url='$new_avatar' WHERE author_id=$author_id");
    }

    header("Location: dashboard.php");
    exit();
}

/** DATA QUERIES */
$posts = mysqli_query($conn,
    "SELECT blog_posts.*, authors.author_name 
     FROM blog_posts 
     JOIN authors ON blog_posts.author_id = authors.author_id 
     ORDER BY post_id DESC"
);

$authors_query = mysqli_query($conn, "SELECT * FROM authors ORDER BY author_id DESC");
$authors = [];
while ($auth_row = mysqli_fetch_assoc($authors_query)) {
    $authors[] = $auth_row;
}

// Fetch all dynamic settings rows into a mapping dictionary array map
$settings_query = mysqli_query($conn, "SELECT * FROM site_settings");
$site = [];
while ($set_row = mysqli_fetch_assoc($settings_query)) {
    $site[$set_row['setting_key']] = $set_row['setting_value'];
}

$total_rows = mysqli_num_rows($posts);
$is_overlay_active = ($view_post || $edit_post || $edit_author_ctx || (isset($_GET['action_mode']) && $_GET['action_mode'] !== 'settings')) ? 'true' : 'false';
$dismiss_target_url = (isset($_GET['action_mode']) && ($_GET['action_mode'] === 'author' || $edit_author_ctx)) ? 'dashboard.php?action_mode=authors_panel' : 'dashboard.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="../style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="dashboard-body" id="dashboardViewportBackdrop">

<div class="dashboard-container" id="dashboardContentMatrix">

    <div class="dashboard-header">
        <div class="panel-identity">
            <i class="fa-solid fa-square-terminal control-node-icon"></i>
            <div>
                <h2>Admin Panel</h2>
                <p class="sub-text-node">Manage your content and settings</p>
            </div>
        </div>
        <div class="header-nav-actions">
            <a href="../index.php" class="live-site-link">
                <i class="fa-solid fa-circle-nodes"></i> View Live Site
            </a>
            <a class="logout-btn" href="dashboard.php?logout=1">
                <i class="fa-solid fa-power-off"></i> Logout
            </a>
        </div>
    </div>

    <?php if ($view_post) { ?>
        <div class="inspect-view-card">
            <div class="inspect-cover-wrapper">
                <img src="../<?php echo htmlspecialchars($view_post['cover_image']); ?>" alt="Cover Stream" onerror="this.src='https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=1200&q=80'">
            </div>

            <div class="inspect-main-content-area">
                <div class="inspect-author-row">
                    <img src="../<?php echo htmlspecialchars($view_post['avatar_url'] ?? 'avatar1.png'); ?>" alt="Avatar" onerror="this.src='https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=80&q=80'" class="inspect-avatar">
                    <div class="inspect-author-info">
                        <h4><?php echo htmlspecialchars($view_post['author_name']); ?></h4>
                        <p><?php echo date("M d, Y", strtotime($view_post['published_date'])); ?></p>
                    </div>
                </div>

                <h1 class="inspect-article-title"><?php echo htmlspecialchars($view_post['title']); ?></h1>
                <div class="inspect-article-body"><?php echo htmlspecialchars($view_post['content']); ?></div>

                <div class="inspect-action-footer-toolbar">
                    <a href="dashboard.php" class="toolbar-btn back-btn">
                        <i class="fa-solid fa-arrow-left"></i> Back
                    </a>
                    <div class="right-side-ops">
                        <a href="dashboard.php?edit=<?php echo $view_post['post_id']; ?>" class="toolbar-btn edit-node-btn">
                            <i class="fa-solid fa-pen-to-square"></i> Edit Post
                        </a>
                        <a href="dashboard.php?delete=<?php echo $view_post['post_id']; ?>" onclick="return confirm('Drop records permanently from this table row?')" class="toolbar-btn delete-node-btn">
                            <i class="fa-solid fa-trash-can"></i> Delete Post
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

    <?php if ($edit_post || $edit_author_ctx || isset($_GET['action_mode'])) { ?>
        
        <?php if ((isset($_GET['action_mode']) && $_GET['action_mode'] === 'author') || $edit_author_ctx) { ?>
            <div class="form-card action-focus-card">
                <div class="form-card-header">
                    <h3>
                        <i class="fa-solid <?php echo $edit_author_ctx ? 'fa-user-pen' : 'fa-user-plus'; ?> text-blue-icon"></i> 
                        <?php echo $edit_author_ctx ? "Modify Existing Author Properties" : "Introduce New Verified Platform Author"; ?>
                    </h3>
                    <a href="dashboard.php?action_mode=authors_panel" class="close-workspace-btn"><i class="fa-solid fa-xmark"></i></a>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <?php if ($edit_author_ctx) { ?>
                        <input type="hidden" name="author_id" value="<?php echo $edit_author_ctx['author_id']; ?>">
                    <?php } ?>
                    <div class="form-input-grid">
                        <div class="input-group">
                            <label>Author Name</label>
                            <input type="text" name="author_name" placeholder="e.g., Sarah Jenkins" value="<?php echo htmlspecialchars($edit_author_ctx['author_name'] ?? ''); ?>" required>
                        </div>
                        <div class="input-group">
                            <label>Author Avatar Picture Upload <?php echo $edit_author_ctx ? '(Optional Edit)' : ''; ?></label>
                            <input type="file" name="author_avatar_file" accept="image/*" <?php echo $edit_author_ctx ? '' : 'required'; ?>>
                        </div>
                    </div>
                    <div class="form-button-cluster">
                        <?php if ($edit_author_ctx) { ?>
                            <button type="submit" name="update_author" class="submit-action-btn"><i class="fa-solid fa-user-check"></i> Save Author Changes</button>
                        <?php } else { ?>
                            <button type="submit" name="add_author" class="submit-action-btn"><i class="fa-solid fa-user-check"></i> Register New Author</button>
                        <?php } ?>
                        <a href="dashboard.php?action_mode=authors_panel" class="cancel-action-btn"><i class="fa-solid fa-times"></i> Cancel Workspace</a>
                    </div>
                </form>
            </div>

        <?php } elseif (isset($_GET['action_mode']) && $_GET['action_mode'] === 'settings') { ?>
            <div class="live-builder-split-grid">
                
                <div class="form-card" style="margin: 0; animation: unset;">
                    <div class="form-card-header" style="margin-bottom: 15px; padding-bottom: 10px;">
                        <h3><i class="fa-solid fa-sliders-up text-blue-icon"></i> Style Control Panel</h3>
                        <a href="dashboard.php" class="close-workspace-btn"><i class="fa-solid fa-xmark"></i></a>
                    </div>
                    
                    <form method="POST" action="dashboard.php" style="gap: 12px;">
                        <div class="builder-scroll-zone">
                            <div class="input-group">
                                <label>Header Website Tagline</label>
                                <input type="text" id="input_site_slogan" name="settings[site_slogan]" value="<?php echo htmlspecialchars($site['site_slogan'] ?? ''); ?>">
                            </div>
                            <div class="input-group">
                                <label>About View Title Header</label>
                                <input type="text" id="input_about_heading" name="settings[about_heading]" value="<?php echo htmlspecialchars($site['about_heading'] ?? ''); ?>">
                            </div>
                            <div class="input-group">
                                <label>About Content Description</label>
                                <textarea id="input_about_text" name="settings[about_text]"><?php echo htmlspecialchars($site['about_text'] ?? ''); ?></textarea>
                            </div>
                            <div class="input-group">
                                <label>Contact View Title Header</label>
                                <input type="text" id="input_contact_heading" name="settings[contact_heading]" value="<?php echo htmlspecialchars($site['contact_heading'] ?? ''); ?>">
                            </div>
                            <div class="input-group">
                                <label>Support Email Address</label>
                                <input type="text" id="input_contact_email" name="settings[contact_email]" value="<?php echo htmlspecialchars($site['contact_email'] ?? ''); ?>">
                            </div>
                            <div class="input-group">
                                <label>Support Helpdesk Phone</label>
                                <input type="text" id="input_contact_phone" name="settings[contact_phone]" value="<?php echo htmlspecialchars($site['contact_phone'] ?? ''); ?>">
                            </div>
                            <div class="input-group">
                                <label>Contact Location</label>
                                <input type="text" id="input_contact_location" name="settings[contact_location]" value="<?php echo htmlspecialchars($site['contact_location'] ?? ''); ?>">
                            </div>
                            <div class="input-group">
                                <label>CTA Heading</label>
                                <input type="text" id="input_cta_heading" name="settings[cta_heading]" value="<?php echo htmlspecialchars($site['cta_heading'] ?? ''); ?>">
                            </div>
                            <div class="input-group">
                                <label>CTA Description Text</label>
                                <textarea id="input_cta_text" name="settings[cta_text]"><?php echo htmlspecialchars($site['cta_text'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="form-button-cluster" style="margin-top: 15px; border-top: 1px solid var(--glass-border); padding-top: 15px;">
                            <button type="submit" name="save_settings" class="submit-action-btn" style="min-width: unset; width: 100%;"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
                            <a href="dashboard.php" class="cancel-action-btn" style="width: 100%; text-align: center;"><i class="fa-solid fa-times"></i> Dismiss</a>
                        </div>
                    </form>
                </div>

                <div class="live-sandbox-preview-frame">
                    <div class="sandbox-overlay-badge"><i class="fa-solid fa-wand-magic-sparkles"></i> Active Sandbox Live Preview Canvas</div>
                    
                    <div class="sandbox-scale-wrapper">
                        <header style="margin-bottom: 20px;">
                            <div class="logo-container">
                                <i class="fa-solid fa-microchip logo-icon"></i>
                                <h1 style="font-size: 24px; color: white; display: inline; margin-left: 8px;">TechSpace</h1>
                            </div>
                            <p id="live_site_slogan" style="color: var(--text-secondary); font-size: 13px; margin-top: 4px;"><?php echo htmlspecialchars($site['site_slogan'] ?? ''); ?></p>
                        </header>

                        <div class="page-card" style="margin-bottom: 20px; padding: 20px;">
                            <h2 id="live_about_heading" style="font-size: 18px; margin-bottom: 10px; color: white;"><?php echo htmlspecialchars($site['about_heading'] ?? ''); ?></h2>
                            <p id="live_about_text" style="font-size: 12px; line-height: 1.6; color: var(--text-secondary);"><?php echo htmlspecialchars($site['about_text'] ?? ''); ?></p>
                        </div>

                        <div class="page-card" style="margin-bottom: 20px; padding: 20px;">
                            <h2 id="live_contact_heading" style="font-size: 18px; margin-bottom: 10px; color: white;"><?php echo htmlspecialchars($site['contact_heading'] ?? ''); ?></h2>
                            <div class="contact-info" style="gap: 8px; margin-top: 10px; font-size: 12px;">
                                <div class="contact-item"><i class="fa-solid fa-envelope" style="color: var(--accent-blue);"></i> <span id="live_contact_email"><?php echo htmlspecialchars($site['contact_email'] ?? ''); ?></span></div>
                                <div class="contact-item"><i class="fa-solid fa-phone" style="color: var(--accent-blue);"></i> <span id="live_contact_phone"><?php echo htmlspecialchars($site['contact_phone'] ?? ''); ?></span></div>
                                <div class="contact-item"><i class="fa-solid fa-location-dot" style="color: var(--accent-blue);"></i> <span id="live_contact_location"><?php echo htmlspecialchars($site['contact_location'] ?? ''); ?></span></div>
                            </div>
                        </div>

                        <section class="cta-section" style="padding: 0; margin-top: 10px;">
                            <div class="cta-card" style="padding: 20px; border-radius: 14px;">
                                <h2 id="live_cta_heading" style="font-size: 16px; margin-bottom: 8px; color: white; text-align: center; font-weight: 700;"><?php echo htmlspecialchars($site['cta_heading'] ?? ''); ?></h2>
                                <p id="live_cta_text" style="font-size: 11px; text-align: center; color: rgba(255,255,255,0.7); max-width: 100%;"><?php echo htmlspecialchars($site['cta_text'] ?? ''); ?></p>
                            </div>
                        </section>
                    </div>
                </div>

            </div>

        <?php } elseif ($edit_post || (isset($_GET['action_mode']) && $_GET['action_mode'] === 'create')) { ?>
            <div class="form-card action-focus-card">
                <div class="form-card-header">
                    <h3>
                        <i class="fa-solid <?php echo $edit_post ? 'fa-square-pen' : 'fa-folder-plus'; ?>"></i>
                        <?php echo $edit_post ? "Modify Node Block: ID #" . $edit_post['post_id'] : "Initialize New Blog Entry Node"; ?>
                    </h3>
                    <a href="dashboard.php" class="close-workspace-btn"><i class="fa-solid fa-xmark"></i></a>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <?php if ($edit_post) { ?>
                        <input type="hidden" name="post_id" value="<?php echo $edit_post['post_id']; ?>">
                    <?php } ?>

                    <div class="form-input-grid">
                        <div class="input-group">
                            <label>Blog Post Title</label>
                            <input type="text" name="title" placeholder="Define database title record..."
                                value="<?php echo htmlspecialchars($edit_post['title'] ?? ''); ?>" required>
                        </div>

                        <div class="input-group">
                            <label>Cover Image Upload <?php echo $edit_post ? '(Leave blank to retain current)' : ''; ?></label>
                            <input type="file" name="cover_image_file" accept="image/*" <?php echo $edit_post ? '' : 'required'; ?>>
                        </div>

                        <div class="input-group">
                            <label>Assigned Author</label>
                            <select name="author_id" required>
                                <option value="">Author List</option>
                                <?php foreach ($authors as $a) { ?>
                                    <option value="<?php echo $a['author_id']; ?>"
                                        <?php if ($edit_post && $edit_post['author_id'] == $a['author_id']) echo "selected"; ?>>
                                        <?php echo htmlspecialchars($a['author_name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="input-group">
                            <label>Modify Author Avatar (Optional)</label>
                            <input type="file" name="post_author_avatar_file" accept="image/*">
                        </div>
                    </div>


                    <div class="input-group" style="margin-top: 15px;">
                        <label>Content Body</label>
                        <textarea name="content" placeholder="Compile text array elements here..." required><?php echo htmlspecialchars($edit_post['content'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-button-cluster">
                        <?php if ($edit_post) { ?>
                            <button type="submit" name="update_post" class="submit-action-btn"><i class="fa-solid fa-database"></i> Update</button>
                        <?php } else { ?>
                            <button type="submit" name="add_post" class="submit-action-btn"><i class="fa-solid fa-cloud-arrow-up"></i> Add Post</button>
                        <?php } ?>
                        <a href="dashboard.php" class="cancel-action-btn"><i class="fa-solid fa-times"></i> Cancel Workspace</a>
                    </div>
                </form>
            </div>
        <?php } ?>
    <?php } ?>

    <?php if (!isset($_GET['view'])) { ?>
        
        <?php if (isset($_GET['action_mode']) && ($_GET['action_mode'] === 'authors_panel' || isset($_GET['edit_author']))) { ?>
            <div class="posts-card">
                <div class="table-header-action-row">
                    <div class="table-title-area">
                        <h3><i class="fa-solid fa-users text-blue-icon"></i> Author Management Panel</h3>
                        <p class="row-count-tracker">Review active system content creators</p>
                    </div>
                    <div style="display: flex; gap: 12px;">
                        <a href="dashboard.php" class="initialize-creation-btn" style="background: rgba(96, 165, 250, 0.1); color: var(--accent-blue); border-color: rgba(96, 165, 250, 0.25);">
                            <i class="fa-solid fa-arrow-left"></i> Back
                        </a>
                        <a href="dashboard.php?action_mode=author" class="initialize-creation-btn" style="background: rgba(167, 139, 250, 0.1); color: var(--accent-purple); border-color: rgba(167, 139, 250, 0.25);">
                            <i class="fa-solid fa-user-plus"></i> Register New Author Entry
                        </a>
                    </div>
                </div>

                <div class="table-responsive-wrapper">
                    <table class="dashboard-data-table">
                        <thead>
                            <tr>
                                <th width="15%">Author ID</th>
                                <th width="45%">Author Identity</th>
                                <th width="25%">Avatar Image Asset</th>
                                <th width="15%" style="text-align: right; padding-right: 25px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($authors) > 0) { 
                                foreach ($authors as $auth) {
                            ?>
                                    <tr class="table-data-row">
                                        <td class="record-id-badge">#AUTH-<?php echo $auth['author_id']; ?></td>
                                        <td><span class="table-row-title"><?php echo htmlspecialchars($auth['author_name']); ?></span></td>
                                        <td>
                                            <div class="table-image-preview-node">
                                                <div class="mini-thumbnail-frame" style="border-radius: 50%; width: 38px;">
                                                    <img src="../<?php echo htmlspecialchars($auth['avatar_url']); ?>" alt="Avatar" onerror="this.src='https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=80&q=80'">
                                                </div>
                                                <span class="image-file-string"><?php echo htmlspecialchars($auth['avatar_url']); ?></span>
                                            </div>
                                        </td>
                                        <td style="text-align: right; padding-right: 20px;">
                                            <div class="operation-icon-actions-cluster">
                                                <a href="dashboard.php?edit_author=<?php echo $auth['author_id']; ?>" class="op-btn modify-view" title="Edit Properties">
                                                    <i class="fa-solid fa-pencil"></i>
                                                </a>
                                                <a href="dashboard.php?delete_author=<?php echo $auth['author_id']; ?>" 
                                                   onclick="return confirm('Purge this author record permanently from table databases? Note: Authors linked to blog posts cannot be deleted.')" class="op-btn drop-view" title="Purge Record">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                            <?php 
                                } 
                            } else { ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-dashboard-state">
                                            <i class="fa-solid fa-users-slash empty-ghost-icon"></i>
                                            <p>No verified writers located in database table.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        
        <?php } elseif (!isset($_GET['edit_author']) && !isset($_GET['edit']) && (!isset($_GET['action_mode']) || $_GET['action_mode'] === 'authors_panel')) { ?>
            
            <div class="posts-card">
                <div class="table-header-action-row">
                    <div class="table-title-area">
                        <h3><i class="fa-solid fa-database text-blue-icon"></i> Content Management</h3>
                        <p class="row-count-tracker">Showing <?php echo $total_rows; ?> posts</p>
                    </div>
                    <div style="display: flex; gap: 12px;">
                        <a href="dashboard.php?action_mode=settings" class="initialize-creation-btn" style="background: rgba(56, 189, 248, 0.1); color: #38bdf8; border-color: rgba(56, 189, 248, 0.25);">
                            <i class="fa-solid fa-sliders-up"></i> Site Settings Panel
                        </a>
                        <a href="dashboard.php?action_mode=authors_panel" class="initialize-creation-btn" style="background: rgba(167, 139, 250, 0.1); color: var(--accent-purple); border-color: rgba(167, 139, 250, 0.25);">
                            <i class="fa-solid fa-users-gear"></i> Author Management
                        </a>
                        <a href="dashboard.php?action_mode=create" class="initialize-creation-btn">
                            <i class="fa-solid fa-plus-circle"></i> Add New Post
                        </a>
                    </div>
                </div>

                <div class="table-responsive-wrapper">
                    <table class="dashboard-data-table">
                        <thead>
                            <tr>
                                <th width="10%">Post No</th>
                                <th width="35%">Post Title</th>
                                <th width="22%">Cover Image</th>
                                <th width="13%">Status</th>
                                <th width="20%" style="text-align: right; padding-right: 25px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($total_rows > 0) { 
                                $serial_num = $total_rows;
                                while ($p = mysqli_fetch_assoc($posts)) { 
                            ?>
                                    <tr class="table-data-row">
                                        <td class="record-id-badge">#<?php echo $serial_num; ?></td>
                                        <td>
                                            <div class="identity-meta-container">
                                                <span class="table-row-title"><?php echo htmlspecialchars($p['title']); ?></span>
                                                <span class="table-row-subtext"><i class="fa-solid fa-feather-pointed"></i> <?php echo htmlspecialchars($p['author_name']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="table-image-preview-node">
                                                <div class="mini-thumbnail-frame">
                                                    <img src="../<?php echo htmlspecialchars($p['cover_image']); ?>" alt="Cover Stream" onerror="this.src='https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=80&q=80'">
                                                </div>
                                                <span class="image-file-string"><?php echo htmlspecialchars($p['cover_image']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                                <span style="font-size: 13px; color: #e5e7eb; font-weight: 500;">
                                                    <i class="fa-regular fa-eye" style="color: var(--accent-blue); margin-right: 4px;"></i> 
                                                    <?php echo number_format($p['view_count']); ?> views
                                                </span>
                                                <?php if ($p['view_count'] > 500) { ?>
                                                    <span class="popular-status-pill" style="width: max-content; padding: 2px 8px; font-size: 11px;">
                                                        <i class="fa-solid fa-fire-flame-curved"></i> Popular
                                                    </span>
                                                <?php } ?>
                                            </div>
                                        </td>
                                        <td style="text-align: right; padding-right: 20px;">
                                            <div class="operation-icon-actions-cluster">
                                                <a href="dashboard.php?view=<?php echo $p['post_id']; ?>" class="op-btn read-view" title="Inspect Live View">
                                                    <i class="fa-solid fa-eye"></i>
                                                </a>
                                                <a href="dashboard.php?edit=<?php echo $p['post_id']; ?>" class="op-btn modify-view" title="Edit Properties">
                                                    <i class="fa-solid fa-pencil"></i>
                                                </a>
                                                <a href="dashboard.php?delete=<?php echo $p['post_id']; ?>" 
                                                onclick="return confirm('Drop records permanently from this table row?')" class="op-btn drop-view" title="Purge Record">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php 
                                    $serial_num--;
                                } 
                            } else { ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-dashboard-state">
                                            <i class="fa-solid fa-box-open empty-ghost-icon"></i>
                                            <p>Database structure holds no matching entry points.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php } ?>
    <?php } ?>

</div>

<script type="text/javascript">
document.addEventListener("DOMContentLoaded", function() {
    const overlayConditionActive = <?php echo $is_overlay_active; ?>;
    
    // --- DISMISSAL DETECTION MODULE ---
    if (overlayConditionActive) {
        const viewportBackdrop = document.getElementById("dashboardViewportBackdrop");
        
        viewportBackdrop.style.cursor = "pointer";
        viewportBackdrop.addEventListener("click", function(event) {
            // Find closest parent matrix blocks to see if the click fell into empty gaps
            const insideWorkspaceForm = event.target.closest('.form-card');
            const insideInspectViewCard = event.target.closest('.inspect-view-card');
            const insideHeader = event.target.closest('.dashboard-header');
            
            if (!insideWorkspaceForm && !insideInspectViewCard && !insideHeader) {
                window.location.href = "<?php echo $dismiss_target_url; ?>";
            }
        });
    }

    // --- 🔮 LIVE BUILDER ---
    const bindings = [
        { inputId: 'input_site_slogan', targetId: 'live_site_slogan' },
        { inputId: 'input_about_heading', targetId: 'live_about_heading' },
        { inputId: 'input_about_text', targetId: 'live_about_text' },
        { inputId: 'input_contact_heading', targetId: 'live_contact_heading' },
        { inputId: 'input_contact_email', targetId: 'live_contact_email' },
        { inputId: 'input_contact_phone', targetId: 'live_contact_phone' },
        { inputId: 'input_contact_location', targetId: 'live_contact_location' },
        { inputId: 'input_cta_heading', targetId: 'live_cta_heading' },
        { inputId: 'input_cta_text', targetId: 'live_cta_text' }
    ];

    bindings.forEach(binding => {
        const inputField = document.getElementById(binding.inputId);
        const previewElement = document.getElementById(binding.targetId);
        
        if (inputField && previewElement) {
            inputField.addEventListener('input', function() {
                previewElement.innerText = this.value;
            });
        }
    });
});
</script>

</body>
</html>