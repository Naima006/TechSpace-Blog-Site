<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['author_id'])) {
    header("Location: index.php");
    exit();
}

$author_id = (int)$_SESSION['author_id'];
$author_name = $_SESSION['author_name'] ?? 'Author';

/* Session timeout 20 min for authors */
$timeout = 1200;
$now = time();
if (isset($_SESSION['author_last_activity']) && ($now - $_SESSION['author_last_activity']) >= $timeout) {
    session_unset();
    session_destroy();
    header("Location: index.php?session_expired=1");
    exit();
}
$_SESSION['author_last_activity'] = $now;

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../index.php");
    exit();
}

function process_image_upload_author($file_array) {
    if (isset($file_array) && $file_array['error'] === UPLOAD_ERR_OK) {
        $original_name = basename($file_array['name']);
        $clean_name = time() . "_" . preg_replace("/[^A-Za-z0-9.\-_]/", "", $original_name);
        $destination_path = __DIR__ . '/../' . $clean_name;
        if (move_uploaded_file($file_array['tmp_name'], $destination_path)) {
            return $clean_name;
        }
    }
    return null;
}

/* Delete own post only */
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM blog_posts WHERE post_id=$id AND author_id=$author_id");
    header("Location: dashboard.php");
    exit();
}

/* Add post (pending by default) */
if (isset($_POST['add_post'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title'] ?? '');
    $content = mysqli_real_escape_string($conn, $_POST['content'] ?? '');
    $cover = process_image_upload_author($_FILES['cover_image_file'] ?? null);
    if (!$cover) { $cover = 'blog1.png'; }
    if ($title !== '' && $content !== '') {
        mysqli_query($conn, "
            INSERT INTO blog_posts (title, cover_image, content, author_id, is_popular, view_count, status)
            VALUES ('$title', '$cover', '$content', $author_id, 0, 0, 'pending')
        ");
    }
    header("Location: dashboard.php");
    exit();
}

/* Update own post */
if (isset($_POST['update_post'])) {
    $id = (int)$_POST['post_id'];
    $title = mysqli_real_escape_string($conn, $_POST['title'] ?? '');
    $content = mysqli_real_escape_string($conn, $_POST['content'] ?? '');
    $new_cover = process_image_upload_author($_FILES['cover_image_file'] ?? null);
    $cover_sql = $new_cover ? "cover_image='$new_cover'," : "";
    mysqli_query($conn, "
        UPDATE blog_posts SET
        title='$title',
        content='$content',
        $cover_sql
        status='pending'
        WHERE post_id=$id AND author_id=$author_id
    ");
    header("Location: dashboard.php");
    exit();
}

$edit_post = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $res = mysqli_query($conn, "SELECT * FROM blog_posts WHERE post_id=$id AND author_id=$author_id");
    $edit_post = mysqli_fetch_assoc($res);
}

$posts = mysqli_query($conn, "
    SELECT * FROM blog_posts WHERE author_id=$author_id ORDER BY post_id DESC
");

$show_form = $edit_post || (isset($_GET['action_mode']) && $_GET['action_mode'] === 'create');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Author Dashboard · TechSpace</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../admin/admin.css">
</head>
<body class="dashboard-body">

<div class="dashboard-container">

    <div class="dashboard-header">
        <div class="panel-identity">
            <i class="fa-solid fa-feather-pointed control-node-icon"></i>
            <div>
                <h2 style="word-spacing: 0.15em; letter-spacing: normal;">Author Workspace</h2>
                <p class="sub-text-node" style="padding-top: 5px;">Welcome, <?php echo htmlspecialchars($author_name); ?>. Manage and publish your articles.</p>
            </div>
        </div>
        <div class="header-nav-actions">
            <a href="../index.php" class="live-site-link"><i class="fa-solid fa-circle-nodes"></i> View Live Site</a>
            <a href="dashboard.php?logout=1" class="logout-btn logout-action-btn"><i class="fa-solid fa-power-off"></i> Logout</a>
        </div>
    </div>

    <?php if ($show_form) { ?>
    <div class="form-card action-focus-card" style="margin-bottom: 24px;">
        <div class="form-card-header">
            <h3>
                <i class="fa-solid <?php echo $edit_post ? 'fa-square-pen' : 'fa-folder-plus'; ?>"></i>
                <?php echo $edit_post ? 'Edit Your Article' : 'Write New Article'; ?>
            </h3>
            <a href="dashboard.php" class="close-workspace-btn"><i class="fa-solid fa-xmark"></i></a>
        </div>

        <div class="post-preview-workspace">
            <form method="POST" enctype="multipart/form-data">
                <?php if ($edit_post) { ?>
                    <input type="hidden" name="post_id" value="<?php echo (int)$edit_post['post_id']; ?>">
                <?php } ?>
                <div class="form-input-grid">
                    <div class="input-group">
                        <label>Title</label>
                        <input type="text" name="title" id="post_title_input" required
                            value="<?php echo htmlspecialchars($edit_post['title'] ?? ''); ?>"
                            placeholder="Article title...">
                    </div>
                    <div class="input-group">
                        <label>Cover Image <?php echo $edit_post ? '(leave blank to keep current)' : ''; ?></label>
                        <input type="file" name="cover_image_file" id="post_cover_input" accept="image/*" <?php echo $edit_post ? '' : 'required'; ?>>
                    </div>
                </div>
                <div class="input-group" style="margin-top: 15px;">
                    <label>Content</label>
                    <textarea name="content" id="post_content_input" rows="10" required placeholder="Write your article..."><?php echo htmlspecialchars($edit_post['content'] ?? ''); ?></textarea>
                </div>
                <p style="font-size:12px; color:var(--text-secondary); margin: 12px 0;">
                    <i class="fa-solid fa-circle-info"></i> New and edited posts are submitted as <strong>pending</strong> and appear on the site after admin approval.
                </p>
                <div class="form-button-cluster">
                    <?php if ($edit_post) { ?>
                        <button type="submit" name="update_post" class="submit-action-btn"><i class="fa-solid fa-database"></i> Update Post</button>
                    <?php } else { ?>
                        <button type="submit" name="add_post" class="submit-action-btn"><i class="fa-solid fa-cloud-arrow-up"></i> Submit for Review</button>
                    <?php } ?>
                    <a href="dashboard.php" class="cancel-action-btn"><i class="fa-solid fa-times"></i> &nbsp Cancel</a>
                </div>
            </form>

            <div class="live-sandbox-preview-frame">
                <div class="sandbox-overlay-badge"><i class="fa-solid fa-wand-magic-sparkles"></i> Live Preview</div>
                <div class="live-post-preview-card">
                    <img class="preview-cover" id="live_cover_preview"
                        src="<?php echo $edit_post ? '../' . htmlspecialchars($edit_post['cover_image']) : 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=800&q=80'; ?>"
                        alt="Cover preview"
                        onerror="this.src='https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=800&q=80'">
                    <div class="preview-body">
                        <div class="preview-title" id="live_title_preview"><?php echo htmlspecialchars($edit_post['title'] ?? 'Your title appears here'); ?></div>
                        <div class="preview-excerpt" id="live_excerpt_preview"><?php
                            $ex = $edit_post['content'] ?? 'Your article excerpt will show here as you type...';
                            echo htmlspecialchars(mb_substr($ex, 0, 180)) . (mb_strlen($ex) > 180 ? '...' : '');
                        ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>

    <div class="posts-card">
        <div class="table-header-action-row">
            <div class="table-title-area">
                <h3 style="display: flex; align-items: center; gap: 15px; margin: 0;">
                    <i class="fa-solid fa-newspaper text-blue-icon"></i>
                    <span>My Articles</span>
                </h3>
                <p class="row-count-tracker" style="padding-top: 5px; margin: 0; padding-left: 35px;">
                    Only your own posts are listed here
                </p>
            </div>
            <div style="display: flex; gap: 12px;">
                <a href="dashboard.php?action_mode=create" class="initialize-creation-btn">
                    <i class="fa-solid fa-plus-circle"></i> Write New Article
                </a>
            </div>
        </div>

        <div class="table-responsive-wrapper">
            <table class="dashboard-data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Views</th>
                        <th style="text-align:right; padding-right:25px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($posts && mysqli_num_rows($posts) > 0) {
                    while ($p = mysqli_fetch_assoc($posts)) {
                        $st = $p['status'] ?? 'published';
                        $badge = $st === 'pending' ? 'status-pending' : 'status-published';
                ?>
                    <tr class="table-data-row">
                        <td class="record-id-badge">#<?php echo (int)$p['post_id']; ?></td>
                        <td><span class="table-row-title"><?php echo htmlspecialchars($p['title']); ?></span></td>
                        <td><span class="status-badge <?php echo $badge; ?>"><?php echo htmlspecialchars($st); ?></span></td>
                        <td><?php echo number_format((int)$p['view_count']); ?></td>
                        <td style="text-align:right; padding-right:20px;">
                            <div class="operation-icon-actions-cluster">
                                <a href="dashboard.php?edit=<?php echo (int)$p['post_id']; ?>" class="op-btn modify-view" title="Edit"><i class="fa-solid fa-pencil"></i></a>
                                <a href="dashboard.php?delete=<?php echo (int)$p['post_id']; ?>"
                                   onclick="return confirm('Delete this article?');" class="op-btn drop-view" title="Delete"><i class="fa-solid fa-trash-can"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php
                    }
                } else {
                ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-dashboard-state">
                                <i class="fa-solid fa-ghost empty-ghost-icon"></i>
                                <p>No articles yet. Write your first post!</p>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
(function () {
    var titleIn = document.getElementById('post_title_input');
    var contentIn = document.getElementById('post_content_input');
    var coverIn = document.getElementById('post_cover_input');
    var liveTitle = document.getElementById('live_title_preview');
    var liveExcerpt = document.getElementById('live_excerpt_preview');
    var liveCover = document.getElementById('live_cover_preview');
    if (!titleIn) return;

    titleIn.addEventListener('input', function () {
        liveTitle.textContent = this.value || 'Your title appears here';
    });
    contentIn.addEventListener('input', function () {
        var t = this.value || 'Your article excerpt will show here as you type...';
        liveExcerpt.textContent = t.length > 180 ? t.substring(0, 180) + '...' : t;
    });
    if (coverIn) {
        coverIn.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) { liveCover.src = e.target.result; };
                reader.readAsDataURL(this.files[0]);
            }
        });
    }
})();
</script>

</body>
</html>
