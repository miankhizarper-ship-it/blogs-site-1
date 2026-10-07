<?php
declare(strict_types=1);

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Helpers\Seo;
use Helpers\Slugger;
use Helpers\Validator;
use Models\ActivityLog;
use Models\User;

/**
 * Admin → Users & roles (Phase 9 #3). Admin-only module.
 */
class UserAdminController extends Controller
{
    private User $users;

    public function __construct()
    {
        // Only admins manage users.
        if (!Auth::isAdmin()) {
            http_response_code(403);
            exit('403 — Only administrators can manage users.');
        }
        $this->users = new User();
    }

    public function index(): void
    {
        Seo::title('Users — Admin');
        $this->renderAdmin('users/index', [
            'title'     => 'Users & Roles',
            'result'    => $this->users->paginate(max(1, (int) ($_GET['page'] ?? 1)), 15, trim((string) ($_GET['q'] ?? ''))),
            'q'         => trim((string) ($_GET['q'] ?? '')),
            'activeNav' => 'users',
            'crumbs'    => [['label' => 'Dashboard', 'url' => '/admin'], ['label' => 'Users']],
        ]);
    }

    public function form(?string $id = null): void
    {
        $user = $id !== null ? $this->users->find((int) $id) : null;
        if ($id !== null && !$user) { flash('error', 'User not found.'); $this->redirect('/admin/users'); }
        Seo::title(($user ? 'Edit' : 'New') . ' User — Admin');
        $this->renderAdmin('users/form', [
            'title'     => $user ? 'Edit: ' . $user['name'] : 'New User',
            'user'      => $user,
            'activeNav' => 'users',
            'crumbs'    => [['label' => 'Dashboard', 'url' => '/admin'],
                            ['label' => 'Users', 'url' => '/admin/users'],
                            ['label' => $user ? 'Edit' : 'Create']],
        ]);
    }

    public function save(): void
    {
        $id    = (int) ($_POST['id'] ?? 0);
        $isNew = $id === 0;
        $user  = $isNew ? null : $this->users->find($id);
        if (!$isNew && !$user) { flash('error', 'User not found.'); $this->redirect('/admin/users'); }

        $v = new Validator($_POST);
        $v->required('name', 'Name')->minLen('name', 2, 'Name')->maxLen('name', 100, 'Name')
          ->required('email', 'Email')->email('email', 'Email')
          ->required('role', 'Role')->in('role', ['admin', 'editor', 'author'], 'Role');
        if ($isNew) {
            $v->required('password', 'Password')->strongPassword('password');
        } elseif (!empty($_POST['password'])) {
            $v->strongPassword('password');
        }
        if ($v->fails()) {
            flash('error', $v->firstError());
            $this->redirect($isNew ? '/admin/users/create' : "/admin/users/$id/edit");
        }

        // Unique email check
        $email = mb_strtolower(trim((string) $_POST['email']));
        $dup   = $this->users->findBy('email', $email);
        if ($dup && (int) $dup['id'] !== $id) {
            flash('error', 'That email is already in use.');
            $this->redirect($isNew ? '/admin/users/create' : "/admin/users/$id/edit");
        }

        $data = [
            'name'      => trim((string) $_POST['name']),
            'email'     => $email,
            'role'      => (string) $_POST['role'],
            'bio'       => mb_substr(trim((string) ($_POST['bio'] ?? '')), 0, 500),
            'is_active' => empty($_POST['is_active']) ? 0 : 1,
        ];
        if (!empty($_POST['password'])) {
            $data['password_hash'] = password_hash((string) $_POST['password'], PASSWORD_DEFAULT);
        }

        // Avatar upload via media folder
        if (!empty($_FILES['avatar']['name'])) {
            try {
                $res = \Helpers\Uploader::image($_FILES['avatar'], 'avatars');
                $data['avatar'] = '/' . $res['path'];
            } catch (\Throwable $e) {
                flash('error', 'Avatar: ' . $e->getMessage());
            }
        }

        if ($isNew) {
            $data['slug'] = Slugger::unique('users', (string) $_POST['name']);
            $id = $this->users->create($data);
            ActivityLog::log('user.created', "Created user #$id: {$data['email']} ({$data['role']})", 'user', $id);
            flash('success', 'User created.');
        } else {
            // Never let an admin demote/deactivate themselves by accident.
            if ($id === (int) Auth::id() && ($data['role'] !== 'admin' || !$data['is_active'])) {
                flash('error', 'You cannot remove your own admin role or deactivate yourself.');
                $this->redirect("/admin/users/$id/edit");
            }
            unset($data['slug']); // keep existing slug unless name change intentionally re-slugged
            $this->users->update($id, $data);
            ActivityLog::log('user.updated', "Updated user #$id: {$data['email']} ({$data['role']})", 'user', $id);
            flash('success', 'User updated.');
        }
        $this->redirect('/admin/users');
    }

    public function delete(string $id): void
    {
        $id = (int) $id;
        if ($id === (int) Auth::id()) {
            flash('error', 'You cannot delete your own account.');
            $this->redirect('/admin/users');
        }
        $u = $this->users->find($id);
        if (!$u) { flash('error', 'User not found.'); $this->redirect('/admin/users'); }
        // Posts keep author_id but the row disappears — reassign to first admin instead.
        $fallback = $this->users->run("SELECT id FROM users WHERE id <> ? AND role='admin' AND is_active=1 ORDER BY id LIMIT 1", [$id])->fetchColumn();
        if ($fallback) {
            \Database::run('UPDATE blogs SET author_id = ? WHERE author_id = ?', [(int) $fallback, $id]);
            \Database::run('UPDATE comments SET user_id = ? WHERE user_id = ?', [(int) $fallback, $id]);
        }
        $this->users->delete($id);
        ActivityLog::log('user.deleted', "Deleted user #$id: {$u['email']}", 'user', $id);
        flash('success', 'User deleted (their posts reassigned).');
        $this->redirect('/admin/users');
    }

    private function renderAdmin(string $template, array $data): void
    {
        \Core\View::setRoot(BASE_PATH . '/admin/views');
        $this->view($template, $data, 'admin');
    }
}
