<?php
declare(strict_types=1);

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Helpers\Seo;
use Models\ActivityLog;
use Models\Blog;
use Models\Category;
use Models\Comment;
use Models\ContactMessage;
use Models\Page;
use Models\Subscriber;
use Models\Tag;

/**
 * Admin dashboard (Phase 5 + stats). All admin controllers render through
 * the admin layout in /admin/views.
 */
class AdminController extends Controller
{
    public function index(): void
    {
        $blogs = new Blog();
        Seo::title('Dashboard — Admin');
        \Core\View::setRoot(BASE_PATH . '/admin/views');
        $this->view('dashboard/index', [
            'title'      => 'Dashboard',
            'stats'      => $blogs->stats() + [
                'comments_pending' => (new Comment())->countByStatus('pending'),
                'messages_unread'  => (new ContactMessage())->unreadCount(),
                'subscribers'      => (new Subscriber())->activeCount(),
                'categories'       => (new Category())->count(),
                'tags'             => (new Tag())->count(),
                'pages'            => (new Page())->count(),
            ],
            'series'     => $blogs->viewsSeries(30),
            'topBlogs'   => $blogs->topBlogs(5),
            'recentPosts'=> $blogs->paginate(['includeAll' => true, 'sort' => 'updated'], 1, 6)['items'],
            'recentComments' => (new Comment())->recent(5),
            'recentActivity' => (new ActivityLog())->paginate(1, 8)['rows'],
            'activeNav'  => 'dashboard',
            'crumbs'     => [['label' => 'Dashboard']],
        ], 'admin');
    }

    /** Shared guard used by routes file. */
    public static function requireAdmin(): void
    {
        if (!Auth::check()) {
            flash('error', 'Please sign in to access the admin panel.');
            header('Location: ' . url('/login'));
            exit;
        }
    }
}
