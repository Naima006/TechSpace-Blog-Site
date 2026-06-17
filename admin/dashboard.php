<?php
session_start();
require_once __DIR__ . '/../db.php';

/** Simple auth check */
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

/** Logout & Secure Clear Action - Updated to target the admin sign-in form */
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

/** HELPER FUNCTION TO CHOREOGRAPH LOCAL FILE UPLOADS */
function process_image_upload($file_array) {
    if (isset($file_array) && $file_array['error'] === UPLOAD_ERR_OK) {
        $source_path = $file_array['tmp_name'];
        $original_name = basename($file_array['name']);
        
        // Clean filename to match standard naming conventions
        $clean_name = time() . "_" . preg_replace("/[^A-Za-z0-9.\-_]/", "", $original_name);
        
        // Root destination path (C:/xampp/htdocs/blog-site/)
        $target_directory = __DIR__ . '/../';
        $destination_path = $target_directory . $clean_name;

        if (move_uploaded_file($source_path, $destination_path)) {
            return $clean_name; // Return string route path to write to MySQL columns
        }
    }
    return null;
}

/** ADD AUTHOR DIRECTLY */
if (isset($_POST['add_author'])) {
    $auth_name = mysqli_real_escape_string($conn, $_POST['author_name']);
    
    // Process profile picture file stream upload
    $auth_avatar = process_image_upload($_FILES['author_avatar_file']);
    if (!$auth_avatar) {
        $auth_avatar = 'avatar1.png'; // Failover fallback asset default node
    }
    
    if (!empty($auth_name)) {
        mysqli_query($conn, "INSERT INTO authors (author_name, avatar_url) VALUES ('$auth_name', '$auth_avatar')");
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

    // Handle incoming file stream architectures cleanly
    $cover = process_image_upload($_FILES['cover_image_file']);
    $post_avatar = process_image_upload($_FILES['post_author_avatar_file']);

    // Failover fallback check if no new upload stream is supplied
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

    // Process new file input binaries
    $new_cover = process_image_upload($_FILES['cover_image_file']);
    $new_avatar = process_image_upload($_FILES['post_author_avatar_file']);

    // Base fallback selection mapping
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

$authors_query = mysqli_query($conn, "SELECT * FROM authors");
$authors = [];
while ($auth_row = mysqli_fetch_assoc($authors_query)) {
    $authors[] = $auth_row;
}

// Store total rows to compute dynamic visual serials down standard iterations
$total_rows = mysqli_num_rows($posts);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="dashboard-body">

<div class="dashboard-container">

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

    <?php if ($edit_post || isset($_GET['action_mode'])) { ?>
        
        <?php if (isset($_GET['action_mode']) && $_GET['action_mode'] === 'author') { ?>
            <div class="form-card action-focus-card">
                <div class="form-card-header">
                    <h3>
                        <i class="fa-solid fa-user-plus text-blue-icon"></i> Introduce New Verified Platform Author
                    </h3>
                    <a href="dashboard.php" class="close-workspace-btn"><i class="fa-solid fa-xmark"></i></a>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-input-grid">
                        <div class="input-group">
                            <label>Author Display Name</label>
                            <input type="text" name="author_name" placeholder="e.g., Sarah Jenkins" required>
                        </div>
                        <div class="input-group">
                            <label>Author Avatar Picture Upload</label>
                            <input type="file" name="author_avatar_file" accept="image/*" required>
                        </div>
                    </div>
                    <div class="form-button-cluster">
                        <button type="submit" name="add_author" class="submit-action-btn"><i class="fa-solid fa-user-check"></i> Register New Author</button>
                        <a href="dashboard.php" class="cancel-action-btn">Cancel Workspace</a>
                    </div>
                </form>
            </div>
        <?php } else { ?>
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
                                <option value="">Map structural author token...</option>
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

                    <div class="form-input-grid" style="margin-top: 15px; grid-template-columns: 1fr;">
                        <div class="input-group checkbox-align" style="padding-top: 0; justify-content: flex-start;">
                            <label class="checkbox context-checkbox">
                                <input type="checkbox" name="is_popular" class="popular-toggle-box"
                                    <?php if ($edit_post && $edit_post['is_popular']) echo "checked"; ?>>
                                <span>Mark as Popular</span>
                            </label>
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
        <div class="posts-card">
            <div class="table-header-action-row">
                <div class="table-title-area">
                    <h3><i class="fa-solid fa-database text-blue-icon"></i> Content Management</h3>
                    <p class="row-count-tracker">Showing <?php echo $total_rows; ?> posts</p>
                </div>
                <div style="display: flex; gap: 12px;">
                    <?php if (!isset($_GET['action_mode']) && !$edit_post) { ?>
                        <a href="dashboard.php?action_mode=author" class="initialize-creation-btn" style="background: rgba(167, 139, 250, 0.1); color: var(--accent-purple); border-color: rgba(167, 139, 250, 0.25);">
                            <i class="fa-solid fa-user-plus"></i> Add New Author
                        </a>
                        <a href="dashboard.php?action_mode=create" class="initialize-creation-btn">
                            <i class="fa-solid fa-plus-circle"></i> Add New Post
                        </a>
                    <?php } ?>
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
                                        <?php if ($p['is_popular']) { ?>
                                            <span class="popular-status-pill"><i class="fa-solid fa-fire-flame-curved"></i> Popular</span>
                                        <?php } else { ?>
                                            <span class="standard-dash-pill">—</span>
                                        <?php } ?>
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

</div>

</body>
</html>