<?php
/**
 * TechSpace live search API
 * Returns JSON suggestions for published posts (title / author).
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/db.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if (mb_strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$safe = mysqli_real_escape_string($conn, $q);
$sql = "
    SELECT blog_posts.post_id, blog_posts.title, blog_posts.cover_image,
           blog_posts.view_count, authors.author_name, authors.avatar_url
    FROM blog_posts
    JOIN authors ON blog_posts.author_id = authors.author_id
    WHERE blog_posts.status = 'published'
      AND (blog_posts.title LIKE '%$safe%' OR authors.author_name LIKE '%$safe%')
    ORDER BY blog_posts.view_count DESC, blog_posts.published_date DESC
    LIMIT 8
";
$res = mysqli_query($conn, $sql);
$results = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $results[] = [
            'id'     => (int) $row['post_id'],
            'title'  => $row['title'],
            'author' => $row['author_name'],
            'views'  => (int) $row['view_count'],
            'cover'  => $row['cover_image'],
            'avatar' => $row['avatar_url'],
            'url'    => 'index.php?post=' . (int) $row['post_id'],
        ];
    }
}
echo json_encode(['results' => $results]);
