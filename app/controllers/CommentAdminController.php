<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Helpers\Seo;
use Models\ActivityLog;
use Models\Blog;
use Models\Comment;

/**
 * Admin → Comments moderation queue (Phase 8): approve / unapprove / spam / delete,
 * admin replies, filters, bulk actions.
 */
class CommentAdminController extends Controller
{
    private Comment $comments;

    public function __construct()
    {
        $this->comments = new Comment();
    }

    /* GET /admin/comments */
    public function index(): void
    {
        $status = (string) ($_GET['status'] ?? '');
        if (!in_array($status, ['', 'pending', 'approved', 'spam'], true)) {
            $status = '';
        }
        $result = $this->comments->paginate(
            ['status' => $status ?: null, 'q' => trim((string) ($_GET['q'] ?? ''))],
            max(1, (int) ($_GET['page'] ?? 1)),
            20
        );

        Seo::title('Comments — Admin');
        $this->renderAdmin('comments/index', [
            'title'     => 'Comments',
            'result'    => $result,
            'status'    => $status,
            'q'         => trim((string) ($_GET['q'] ?? '')),
            'counts'    => [
                'all'      => $this->comments->countByStatus(),
                'pending'  => $this->comments->countByStatus('pending'),
                'approved' => $this->comments->countByStatus('approved'),
                'spam'     => $this->comments->countByStatus('spam'),
            ],
            'activeNav' => 'comments',
            'crumbs'    => [['label' => 'Dashboard', 'url' => '/admin'], ['label' => 'Comments']],
        ]);
    }

    /* POST /admin/comments/{id}/status?to=approved|pending|spam */
    public function setStatus(string $id): void
    {
        $to = (string) ($_GET['to'] ?? $_POST['to'] ?? 'approved');
        if (!in_array($to, ['approved', 'pending', 'spam'], true)) {
            flash('error', 'Invalid status.'); $this->redirect('/admin/comments');
        }
        $c = $this->comments->find((int) $id);
        if (!$c) { flash('error', 'Comment not found.'); $this->redirect('/admin/comments'); }
        $this->comments->setStatus((int) $id, $to);
        ActivityLog::log('comment.' . $to, "Comment #$id marked $to on post #{$c['blog_id']}", 'comment', (int) $id);
        flash('success', 'Comment marked ' . $to . '.');
        $this->redirect((string) ($_POST['return'] ?? '/admin/comments'));
    }

    /* POST /admin/comments/{id}/delete */
    public function delete(string $id): void
    {
        $this->comments->removeWithReplies((int) $id);
        ActivityLog::log('comment.deleted', "Deleted comment #$id (and its replies)", 'comment', (int) $id);
        flash('success', 'Comment and its replies deleted.');
        $this->redirect((string) ($_POST['return'] ?? '/admin/comments'));
    }

    /* POST /admin/comments/{id}/reply — admin reply to a visitor comment */
    public function reply(string $id): void
    {
        $parent = $this->comments->find((int) $id);
        if (!$parent) { flash('error', 'Comment not found.'); $this->redirect('/admin/comments'); }

        $content = trim((string) ($_POST['content'] ?? ''));
        if ($content === '' || mb_strlen($content) > 3000) {
            flash('error', 'Reply must be 1–3000 characters.');
            $this->redirect('/admin/comments');
        }

        $newId = $this->comments->create([
            'blog_id'        => (int) $parent['blog_id'],
            'parent_id'      => (int) $parent['id'],
            'user_id'        => \Core\Auth::id(),
            'author_name'    => \Core\Auth::user()['name'] ?? 'Admin',
            'author_email'   => \Core\Auth::user()['email'] ?? '',
            'content'        => $content,
            'status'         => 'approved',
            'is_admin_reply' => 1,
        ]);
        ActivityLog::log('comment.reply', "Admin replied to comment #$id", 'comment', $newId);
        flash('success', 'Reply published.');
        $this->redirect('/admin/comments');
    }

    /* POST /admin/comments/bulk — action=approve|spam|delete */
    public function bulk(): void
    {
        $ids    = array_map('intval', (array) ($_POST['ids'] ?? []));
        $action = (string) ($_POST['bulk_action'] ?? '');
        if (!$ids || !in_array($action, ['approve', 'pending', 'spam', 'delete'], true)) {
            flash('error', 'Nothing selected or invalid action.');
            $this->redirect('/admin/comments');
        }
        foreach ($ids as $i) {
            if ($i <= 0) continue;
            if ($action === 'delete') {
                $this->comments->removeWithReplies($i);
            } else {
                $this->comments->setStatus($i, $action);
            }
        }
        ActivityLog::log('comment.bulk', strtoupper($action) . ' on comments: ' . implode(',', $ids), 'comment');
        flash('success', count($ids) . " comment(s) — $action done.");
        $this->redirect('/admin/comments');
    }

    private function renderAdmin(string $template, array $data): void
    {
        \Core\View::setRoot(BASE_PATH . '/admin/views');
        $this->view($template, $data, 'admin');
    }
}
