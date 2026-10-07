<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Helpers\Seo;
use Models\ActivityLog;
use Models\ContactMessage;
use Models\Subscriber;

/**
 * Admin → Contact inbox + newsletter subscribers (Phase 8).
 */
class InboxAdminController extends Controller
{
    public function messages(): void
    {
        Seo::title('Inbox — Admin');
        $m = new ContactMessage();
        $this->renderAdmin('inbox/messages', [
            'title'     => 'Contact Inbox',
            'rows'      => $m->run(
                'SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 200'
            )->fetchAll(),
            'unread'    => $m->unreadCount(),
            'activeNav' => 'inbox',
            'crumbs'    => [['label' => 'Dashboard', 'url' => '/admin'], ['label' => 'Inbox']],
        ]);
    }

    public function read(string $id): void
    {
        $m = new ContactMessage();
        $msg = $m->find((int) $id);
        if (!$msg) { flash('error', 'Message not found.'); $this->redirect('/admin/inbox'); }
        $m->update((int) $id, ['is_read' => 1]);
        Seo::title('Message — Admin');
        $this->renderAdmin('inbox/read', [
            'title'     => 'Message from ' . $msg['name'],
            'msg'       => $msg,
            'activeNav' => 'inbox',
            'crumbs'    => [['label' => 'Dashboard', 'url' => '/admin'],
                            ['label' => 'Inbox', 'url' => '/admin/inbox'],
                            ['label' => $msg['name']]],
        ]);
    }

    public function deleteMessage(string $id): void
    {
        (new ContactMessage())->delete((int) $id);
        ActivityLog::log('contact.deleted', "Deleted contact message #$id", 'contact', (int) $id);
        flash('success', 'Message deleted.');
        $this->redirect('/admin/inbox');
    }

    public function subscribers(): void
    {
        Seo::title('Subscribers — Admin');
        $s = new Subscriber();
        $this->renderAdmin('inbox/subscribers', [
            'title'     => 'Newsletter Subscribers',
            'rows'      => $s->run('SELECT * FROM subscribers ORDER BY created_at DESC LIMIT 500')->fetchAll(),
            'counts'    => [
                'total'   => (int) $s->run('SELECT COUNT(*) FROM subscribers')->fetchColumn(),
                'active'  => (int) $s->run('SELECT COUNT(*) FROM subscribers WHERE is_active=1')->fetchColumn(),
            ],
            'activeNav' => 'subscribers',
            'crumbs'    => [['label' => 'Dashboard', 'url' => '/admin'], ['label' => 'Subscribers']],
        ]);
    }

    /** POST /admin/subscribers/export — CSV download of active emails. */
    public function exportSubscribers(): void
    {
        $rows = (new Subscriber())->run(
            'SELECT email, is_active, created_at FROM subscribers ORDER BY created_at DESC'
        )->fetchAll();
        ActivityLog::log('subscribers.exported', 'Exported ' . count($rows) . ' subscribers to CSV', 'subscriber');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="subscribers-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['email', 'status', 'subscribed_at']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['email'], $r['is_active'] ? 'active' : 'unsubscribed', $r['created_at']]);
        }
        fclose($out);
        exit;
    }

    public function toggleSubscriber(string $id): void
    {
        $s = new Subscriber();
        $row = $s->find((int) $id);
        if ($row) {
            $s->update((int) $id, ['is_active' => $row['is_active'] ? 0 : 1]);
            ActivityLog::log('subscriber.toggled', ($row['is_active'] ? 'Deactivated' : 'Activated') . " subscriber #$id", 'subscriber', (int) $id);
        }
        flash('success', 'Subscriber updated.');
        $this->redirect('/admin/subscribers');
    }

    private function renderAdmin(string $template, array $data): void
    {
        \Core\View::setRoot(BASE_PATH . '/admin/views');
        $this->view($template, $data, 'admin');
    }
}
