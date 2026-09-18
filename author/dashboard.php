<?php
session_start();
require_once __DIR__ . '/../db.php';

if (!isset($_SESSION['author_id'])) {
    header("Location: index.php");
    exit();
}

$author_id = (int)$_SESSION['author_id'];
$author_name = $_SESSION['author_name'] ?? 'Author';

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

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM blog_posts WHERE post_id=$id AND author_id=$author_id");
    header("Location: dashboard.php");
    exit();
}

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

$view_post = null;
if (isset($_GET['view'])) {
    $id = (int)$_GET['view'];
    $res = mysqli_query($conn, "
        SELECT blog_posts.*, authors.author_name, authors.avatar_url
        FROM blog_posts
        JOIN authors ON blog_posts.author_id = authors.author_id
        WHERE blog_posts.post_id=$id AND blog_posts.author_id=$author_id
    ");
    $view_post = mysqli_fetch_assoc($res);
}

$search_q = isset($_GET['q']) ? trim($_GET['q']) : '';
$posts_sql = "SELECT * FROM blog_posts WHERE author_id=$author_id";
if ($search_q !== '') {
    $sq = mysqli_real_escape_string($conn, $search_q);
    $posts_sql .= " AND title LIKE '%$sq%'";
}
$posts_sql .= " ORDER BY post_id DESC";
$posts = mysqli_query($conn, $posts_sql);
$total_rows = $posts ? mysqli_num_rows($posts) : 0;

/* Author analytics */
$a_total = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM blog_posts WHERE author_id=$author_id"))['c'] ?? 0);
$a_pub = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM blog_posts WHERE author_id=$author_id AND status='published'"))['c'] ?? 0);
$a_pending = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM blog_posts WHERE author_id=$author_id AND status='pending'"))['c'] ?? 0);
$a_views = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(view_count),0) AS c FROM blog_posts WHERE author_id=$author_id"))['c'] ?? 0);

$auth_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT avatar_url FROM authors WHERE author_id=$author_id"));
$my_avatar = $auth_row['avatar_url'] ?? 'avatar1.png';

$show_form = $edit_post || (isset($_GET['action_mode']) && $_GET['action_mode'] === 'create');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Author Workspace · TechSpace</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/tinymce@7.6.0/tinymce.min.js" referrerpolicy="origin"></script>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="../admin/admin.css">
</head>
<body class="dashboard-body">

<div class="dashboard-container">

    <div class="dashboard-header">
        <div class="panel-identity">
            <i class="fa-solid fa-feather-pointed control-node-icon"></i>
            <div>
                <h2>Author Workspace</h2>
                <p class="sub-text-node">Welcome, <?php echo htmlspecialchars($author_name); ?>! Manage and publish your articles.</p>
            </div>
        </div>
        <div class="header-nav-actions">
            <a href="../index.php" class="live-site-link"><i class="fa-solid fa-circle-nodes"></i> View Live Site</a>
            <a href="dashboard.php?logout=1" class="logout-btn logout-action-btn"><i class="fa-solid fa-power-off"></i> Logout</a>
        </div>
    </div>

    <div class="analytics-strip">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fa-solid fa-newspaper"></i></div>
            <div class="stat-meta">
                <div class="stat-value"><?php echo $a_total; ?></div>
                <div class="stat-label">My Articles</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
            <div class="stat-meta">
                <div class="stat-value"><?php echo $a_pub; ?></div>
                <div class="stat-label">Published</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amber"><i class="fa-solid fa-clock"></i></div>
            <div class="stat-meta">
                <div class="stat-value"><?php echo $a_pending; ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fa-solid fa-eye"></i></div>
            <div class="stat-meta">
                <div class="stat-value"><?php echo number_format($a_views); ?></div>
                <div class="stat-label">Total Views</div>
            </div>
        </div>
    </div>

    <?php if ($view_post) { ?>
    <div class="form-card action-focus-card" style="margin-bottom: 24px;">
        <div class="form-card-header">
            <h3><i class="fa-solid fa-eye"></i> View Article</h3>
            <a href="dashboard.php" class="close-workspace-btn"><i class="fa-solid fa-xmark"></i></a>
        </div>
        <div class="live-blog-preview-card" style="max-width: 720px; margin: 0 auto;">
            <div class="cover-wrapper" style="height: 240px;">
                <img src="../<?php echo htmlspecialchars($view_post['cover_image']); ?>" alt="Cover"
                    onerror="this.src='https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=800&q=80'">
            </div>
            <div class="blog-content">
                <div class="author-row">
                    <div class="author-left">
                        <img src="../<?php echo htmlspecialchars($view_post['avatar_url'] ?? $my_avatar); ?>" alt="Author"
                            onerror="this.src='https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=80&q=80'">
                        <div>
                            <h4><?php echo htmlspecialchars($view_post['author_name'] ?? $author_name); ?></h4>
                            <p class="date"><?php echo date('M d, Y', strtotime($view_post['published_date'])); ?></p>
                        </div>
                    </div>
                    <div class="views-chip"><i class="fa-solid fa-eye"></i> <?php echo number_format((int)$view_post['view_count']); ?> views</div>
                </div>
                <h2 class="preview-title"><?php echo htmlspecialchars($view_post['title']); ?></h2>
                <div class="preview-body-text post-html-body" style="max-height: none;"><?php
                    $vc = $view_post['content'];
                    if (preg_match('/<(p|br|strong|b|em|i|u|ul|ol|li|a|h[1-6])\b/i', $vc)) {
                        echo strip_tags($vc, '<p><br><br/><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><blockquote>');
                    } else {
                        echo nl2br(htmlspecialchars($vc));
                    }
                ?></div>
                <p style="margin-top:14px;"><span class="status-badge <?php echo ($view_post['status'] ?? '') === 'pending' ? 'status-pending' : 'status-published'; ?>"><?php echo htmlspecialchars($view_post['status'] ?? 'published'); ?></span></p>
            </div>
        </div>
        <div class="form-button-cluster" style="margin-top: 16px;">
            <a href="dashboard.php?edit=<?php echo (int)$view_post['post_id']; ?>" class="submit-action-btn"><i class="fa-solid fa-pencil"></i> Edit</a>
            <a href="dashboard.php" class="cancel-action-btn"><i class="fa-solid fa-arrow-left"></i> Back</a>
        </div>
    </div>
    <?php } ?>

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
                    <textarea name="content" id="post_content_input" rows="10" placeholder="Write your article..."><?php echo htmlspecialchars($edit_post['content'] ?? ''); ?></textarea>
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
                    <a href="dashboard.php" class="cancel-action-btn"><i class="fa-solid fa-times"></i> Cancel</a>
                </div>
            </form>

            <div class="live-sandbox-preview-frame">
                <div class="sandbox-overlay-badge"><i class="fa-solid fa-wand-magic-sparkles"></i> Live Preview</div>
                <div class="live-blog-preview-card">
                    <div class="cover-wrapper">
                        <img id="live_cover_preview"
                            src="<?php echo $edit_post ? '../' . htmlspecialchars($edit_post['cover_image']) : 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=800&q=80'; ?>"
                            alt="Cover preview"
                            onerror="this.src='https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?auto=format&fit=crop&w=800&q=80'">
                    </div>
                    <div class="blog-content">
                        <div class="author-row">
                            <div class="author-left">
                                <img src="../<?php echo htmlspecialchars($my_avatar); ?>" alt="You"
                                    onerror="this.src='https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=80&q=80'">
                                <div>
                                    <h4><?php echo htmlspecialchars($author_name); ?></h4>
                                    <p class="date"><?php echo $edit_post ? date('M d, Y', strtotime($edit_post['published_date'])) : date('M d, Y'); ?></p>
                                </div>
                            </div>
                            <div class="views-chip"><i class="fa-solid fa-eye"></i> <?php echo $edit_post ? number_format((int)$edit_post['view_count']) : '0'; ?> views</div>
                        </div>
                        <h2 class="preview-title" id="live_title_preview"><?php echo htmlspecialchars($edit_post['title'] ?? 'Your title appears here'); ?></h2>
                        <div class="preview-body-text post-html-body" id="live_excerpt_preview"><?php
                            $ex = $edit_post['content'] ?? 'Your full article content will appear here as you type.';
                            if (preg_match('/<(p|br|strong|b|em|i|u|ul|ol|li|a|h[1-6])\b/i', $ex)) {
                                echo strip_tags($ex, '<p><br><br/><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><blockquote>');
                            } else {
                                echo nl2br(htmlspecialchars($ex));
                            }
                        ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>

    <?php if (!$view_post) { ?>
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
            <div style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
                <form method="GET" action="dashboard.php" class="portal-search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" placeholder="Search my articles..." value="<?php echo htmlspecialchars($search_q); ?>">
                </form>
                <a href="dashboard.php?action_mode=create" class="initialize-creation-btn">
                    <i class="fa-solid fa-plus-circle"></i> Write New Article
                </a>
            </div>
        </div>

        <div class="table-responsive-wrapper">
            <table class="dashboard-data-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Views</th>
                        <th style="text-align:right; padding-right:25px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($posts && $total_rows > 0) {
                    $serial = $total_rows;
                    while ($p = mysqli_fetch_assoc($posts)) {
                        $st = $p['status'] ?? 'published';
                        $badge = $st === 'pending' ? 'status-pending' : 'status-published';
                ?>
                    <tr class="table-data-row">
                        <td class="record-id-badge"><?php echo $serial; ?></td>
                        <td><span class="table-row-title"><?php echo htmlspecialchars($p['title']); ?></span></td>
                        <td><span class="status-badge <?php echo $badge; ?>"><?php echo htmlspecialchars($st); ?></span></td>
                        <td><?php echo number_format((int)$p['view_count']); ?></td>
                        <td style="text-align:right; padding-right:20px;">
                            <div class="operation-icon-actions-cluster">
                                <a href="dashboard.php?view=<?php echo (int)$p['post_id']; ?>" class="op-btn read-view" title="View"><i class="fa-solid fa-eye"></i></a>
                                <a href="dashboard.php?edit=<?php echo (int)$p['post_id']; ?>" class="op-btn modify-view" title="Edit"><i class="fa-solid fa-pencil"></i></a>
                                <a href="dashboard.php?delete=<?php echo (int)$p['post_id']; ?>"
                                   onclick="return confirm('Delete this article?');" class="op-btn drop-view" title="Delete"><i class="fa-solid fa-trash-can"></i></a>
                            </div>
                        </td>
                    </tr>
                <?php
                        $serial--;
                    }
                } else {
                ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-dashboard-state">
                                <i class="fa-solid fa-ghost empty-ghost-icon"></i>
                                <p><?php echo $search_q !== '' ? 'No articles match your search.' : 'No articles yet. Write your first post!'; ?></p>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php } ?>

    <footer class="portal-footer">
        <p class="copyright">&copy; 2026 <span class="text-glow">&nbspTechSpace</span> · Author Workspace</p>
        <div class="social-links">
            <a href="https://www.facebook.com" title="Facebook"><i class="fab fa-facebook-f"></i></a>
            <a href="https://twitter.com" title="Twitter"><i class="fab fa-x-twitter"></i></a>
            <a href="https://www.linkedin.com/in/naima-rahman-176196308/" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
        </div>
    </footer>

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

    function updateContentPreview(html) {
        if (!liveExcerpt) return;
        var fallback = 'Your full article content will appear here as you type.';
        if (!html || html === '' || html === '<p></p>' || html === '<p><br></p>') {
            liveExcerpt.textContent = fallback;
        } else {
            liveExcerpt.innerHTML = html;
        }
    }

    if (contentIn && typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: '#post_content_input',
            height: 280,
            menubar: false,
            branding: false,
            promotion: false,
            statusbar: false,
            elementpath: false,
            plugins: 'lists link autoresize',
            toolbar: 'undo redo | styles | bold italic underline | bullist numlist | link | removeformat',
            skin: 'oxide-dark',
            content_css: 'dark',
            placeholder: 'Start writing your blog here...',
            content_style: 'body { font-family: Plus Jakarta Sans, sans-serif; font-size: 14px; color: #e5e7eb; background-color: #0b0f19; } body[data-mce-placeholder]:before { color: #6b7280 !important; }',
            setup: function (editor) {
                editor.on('init change keyup SetContent', function () {
                    editor.save();
                    updateContentPreview(editor.getContent());
                });
                var form = editor.getElement().form;
                if (form) {
                    form.addEventListener('submit', function (e) {
                        editor.save();
                        var c = editor.getContent({ format: 'text' }).trim();
                        if (!c) { e.preventDefault(); alert('Please write some article content.'); }
                    });
                }
            }
        });
    } else if (contentIn && liveExcerpt) {
        contentIn.addEventListener('input', function () {
            liveExcerpt.innerText = this.value || 'Your full article content will appear here as you type.';
        });
    }

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
