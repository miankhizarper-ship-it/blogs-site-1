<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\RateLimiter;
use Helpers\Sanitizer;
use Helpers\Seo;
use Helpers\Validator;
use Models\Blog;
use Models\Category;
use Models\Comment;
use Models\Tag;
use Core\Settings;

/**
 * Public single-post page (/blog/{slug}) + AJAX endpoints:
 *   GET  /blog/{slug}
 *   POST /comment          (new comment or reply, JSON)
 *   POST /blog/{id}/like   (increments like counter, JSON)
 *   POST /track-view       (records unique view, JSON)
 */
class BlogController extends Controller
{
    /* ------------------------------------------------------------------
       GET /blog/{slug}
       ------------------------------------------------------------------ */
    public function show(string $slug): void
    {
        $blog = new Blog();
        $post = $blog->findPublishedBySlug($slug);

        if (!$post) {
            (new HomeController())->notFound();
            return;
        }

        $id      = (int) $post['id'];
        $tags    = $blog->tagsFor($id);
        $related = $blog->related($id, $post['category_id'] ? (int) $post['category_id'] : null,
                                  array_map(fn ($t) => (int) $t['id'], $tags), 3);
        $nav     = $blog->siblings($id, $post['published_at'] ?? date('Y-m-d H:i:s'));
        $comments = (new Comment())->approvedTree($id);

        /* -------- SEO meta -------- */
        Seo::title($post['meta_title'] ?: $post['title']);
        Seo::description($post['meta_description'] ?: $post['excerpt']);
        Seo::canonical($post['canonical_url'] ?: url('/blog/' . $post['slug']));
        Seo::image($post['og_image']
            ? url($post['og_image'])
            : ($post['featured_image'] ? url($post['featured_image']) : null));
        Seo::type('article');
        if (($post['robots_meta'] ?? 'index,follow') === 'noindex,follow') {
            Seo::noindex();
        }
        Seo::breadcrumbs([
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Blogs', 'url' => '/blogs'],
            ['label' => $post['category_name'] ?? 'Post', 'url' => '/category/' . ($post['category_slug'] ?? '')],
            ['label' => $post['title'], 'url' => '/blog/' . $post['slug']],
        ]);
        Seo::articleSchema(
            $post,
            $post['author_name'] ?? 'Editorial Team',
            $post['featured_image'] ? url($post['featured_image']) : ''
        );

        $this->view('pages/blog', [
            'navCategories' => array_map(
                fn ($c) => ['name' => $c['name'], 'slug' => $c['slug']],
                (new Category())->withCounts()
            ),
            'post'     => $post,
            'tags'     => $tags,
            'related'  => $related,
            'nav'      => $nav,
            'comments' => $comments,
            'bodyClass' => 'page-blog',
        ]);
    }

    /* ------------------------------------------------------------------
       POST /comment — AJAX, moderated, honeypot, throttled
       ------------------------------------------------------------------ */
    public function comment(): void
    {
        \Core\Csrf::verify();

        if (trim((string) ($_POST['website'] ?? '')) !== '') {   // honeypot
            json_response(['ok' => true, 'message' => 'Thanks! Your comment is awaiting moderation.']);
        }

        if (!RateLimiter::allow('comment', 5, 600)) {
            json_response([
                'ok' => false,
                'message' => 'You are commenting too fast. Please wait '
                           . RateLimiter::retryAfter('comment', 600) . 's.',
            ], 429);
        }

        $v = new Validator($_POST);
        $v->required('blog_id', 'Post')->in('blog_id', ['numeric'], 'Post')
          ->required('author_name', 'Name')->minLen('author_name', 2, 'Name')->maxLen('author_name', 100, 'Name')
          ->required('author_email', 'Email')->email('author_email', 'Email')->maxLen('author_email', 190, 'Email')
          ->required('content', 'Comment')->minLen('content', 3, 'Comment')->maxLen('content', 3000, 'Comment');
        if ($v->fails()) {
            json_response(['ok' => false, 'message' => $v->firstError(), 'errors' => $v->errors()], 422);
        }

        $blogId = (int) $_POST['blog_id'];
        $post   = (new Blog())->find($blogId);
        if (!$post || $post['status'] !== 'published' || $post['deleted_at']) {
            json_response(['ok' => false, 'message' => 'This post does not accept comments.'], 404);
        }

        // Comments disabled site-wide?
        if (Settings::get('comments_enabled', '1') === '0') {
            json_response(['ok' => false, 'message' => 'Comments are currently closed.'], 403);
        }

        $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int) $_POST['parent_id'] : null;
        if ($parentId !== null) {
            $parent = (new Comment())->find($parentId);
            if (!$parent || (int) $parent['blog_id'] !== $blogId) {
                json_response(['ok' => false, 'message' => 'Invalid reply target.'], 422);
            }
        }

        $moderate = Settings::bool('comment_moderation');
        $model = new Comment();
        $newId = $model->create([
            'blog_id'      => $blogId,
            'parent_id'    => $parentId,
            'author_name'  => mb_substr(trim((string) $_POST['author_name']), 0, 100),
            'author_email' => mb_strtolower(trim((string) $_POST['author_email'])),
            'content'      => mb_substr(Sanitizer::text((string) $_POST['content']), 0, 3000),
            'status'       => $moderate ? 'pending' : 'approved',
            'is_admin_reply' => 0,
            'ip_address'   => client_ip(),
        ]);

        // Notify admin about the pending comment (best-effort).
        if ($moderate && Settings::bool('email_notify_new_comment')) {
            $to = Settings::get('contact_email') ?: Settings::get('admin_email');
            if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                @mail($to, 'New comment awaiting moderation',
                    "On post: {$post['title']}\nFrom: {$_POST['author_name']} <{$_POST['author_email']}>\n\n{$_POST['content']}",
                    'From: ' . Settings::get('mail_from', 'noreply@localhost'));
            }
        }

        json_response([
            'ok'      => true,
            'message' => $moderate
                ? 'Thanks! Your comment is awaiting moderation.'
                : 'Your comment has been published.',
            'id'      => $newId,
            'status'  => $moderate ? 'pending' : 'approved',
        ]);
    }

    /* ------------------------------------------------------------------
       POST /blog/{id}/like — simple counter bump (client also stores in localStorage)
       ------------------------------------------------------------------ */
    public function like(string $id): void
    {
        \Core\Csrf::verify();
        $blog = new Blog();
        $post = $blog->find((int) $id);
        if (!$post || $post['status'] !== 'published') {
            json_response(['ok' => false, 'message' => 'Not found'], 404);
        }
        if (!RateLimiter::allow('like-' . (int) $id . '-' . client_ip(), 10, 3600)) {
            json_response(['ok' => false, 'message' => 'Slow down!'], 429);
        }
        $delta = (($_POST['action'] ?? 'like') === 'unlike') ? -1 : 1;
        $blog->incrementLikes((int) $id, $delta);
        $fresh = $blog->find((int) $id);
        json_response(['ok' => true, 'likes' => (int) $fresh['likes']]);
    }

    /**
     * Track a unique view once per blog per browser per day.
     * Uses a signed cookie so we do not double-count refreshes.
     */
    public static function trackView(int $blogId): void
    {
        $key = 'viewed_' . $blogId;
        $today = date('Y-m-d');
        if (isset($_COOKIE[$key]) && $_COOKIE[$key] === $today) {
            return; // already counted today for this visitor
        }

        $pdo = \Database::conn();
        $hash = hash('sha256', client_ip() . '|' . (($_SERVER['HTTP_USER_AGENT'] ?? '') . ''));
        // unique per blog+day+visitor fingerprint
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM blog_views WHERE blog_id = ? AND viewed_on = ? AND ip_hash = ? LIMIT 1'
        );
        $stmt->execute([$blogId, $today, substr($hash, 0, 32)]);
        if ((int) $stmt->fetchColumn() === 0) {
            $ins = $pdo->prepare('INSERT INTO blog_views (blog_id, viewed_on, ip_hash, user_agent) VALUES (?, ?, ?, ?)');
            $ins->execute([$blogId, $today, substr($hash, 0, 32), mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
            (new Blog())->incrementViews($blogId);
        }

        setcookie($key, $today, [
            'expires'  => time() + 86400,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);
    }
}
