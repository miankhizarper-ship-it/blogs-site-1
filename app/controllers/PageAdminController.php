<?php
declare(strict_types=1);

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Helpers\Sanitizer;
use Helpers\Seo;
use Helpers\Slugger;
use Helpers\Validator;
use Models\ActivityLog;
use Models\Page;

/**
 * Admin → Pages manager (Phase 9 #1): edit About/Privacy/Terms/Disclaimer and
 * create custom pages with full SEO fields.
 */
class PageAdminController extends Controller
{
    private Page $pages;

    public function __construct()
    {
        $this->pages = new Page();
    }

    /* GET /admin/pages */
    public function index(): void
    {
        Seo::title('Pages — Admin');
        $this->renderAdmin('pages/index', [
            'title'     => 'Pages',
            'rows'      => $this->pages->all([], 'title ASC'),
            'activeNav' => 'pages',
            'crumbs'    => [['label' => 'Dashboard', 'url' => '/admin'], ['label' => 'Pages']],
        ]);
    }

    /* GET /admin/pages/create | /admin/pages/{id}/edit */
    public function form(?string $id = null): void
    {
        $page = null;
        if ($id !== null) {
            $page = $this->pages->find((int) $id);
            if (!$page) { flash('error', 'Page not found.'); $this->redirect('/admin/pages'); }
        }
        Seo::title(($page ? 'Edit' : 'New') . ' Page — Admin');
        $this->renderAdmin('pages/form', [
            'title'     => $page ? 'Edit: ' . $page['title'] : 'New Page',
            'page'      => $page,
            'activeNav' => 'pages',
            'crumbs'    => [['label' => 'Dashboard', 'url' => '/admin'],
                            ['label' => 'Pages', 'url' => '/admin/pages'],
                            ['label' => $page ? 'Edit' : 'Create']],
        ]);
    }

    /* POST /admin/pages/save */
    public function save(): void
    {
        $id    = (int) ($_POST['id'] ?? 0);
        $isNew = $id === 0;
        $page  = $isNew ? null : $this->pages->find($id);
        if (!$isNew && !$page) { flash('error', 'Page not found.'); $this->redirect('/admin/pages'); }

        $v = new Validator($_POST);
        $v->required('title', 'Title')->minLen('title', 2, 'Title')->maxLen('title', 190, 'Title')
          ->required('content', 'Content')
          ->required('status', 'Status')->in('status', ['draft', 'published'], 'Status');
        if ($v->fails()) {
            flash('error', $v->firstError());
            $this->redirect($isNew ? '/admin/pages/create' : "/admin/pages/$id/edit");
        }

        $slugBase = !empty($_POST['slug']) ? (string) $_POST['slug'] : (string) $_POST['title'];
        $data = [
            'title'            => mb_substr(trim((string) $_POST['title']), 0, 190),
            'slug'             => Slugger::unique('pages', $slugBase, $id),
            'content'          => Sanitizer::html((string) $_POST['content']),
            'status'           => (string) $_POST['status'],
            'meta_title'       => mb_substr(trim((string) ($_POST['meta_title'] ?? '')), 0, 190),
            'meta_description' => mb_substr(trim((string) ($_POST['meta_description'] ?? '')), 0, 320),
            'robots_meta'      => ($_POST['robots_meta'] ?? 'index,follow') === 'noindex,follow' ? 'noindex,follow' : 'index,follow',
            'canonical_url'    => mb_substr(trim((string) ($_POST['canonical_url'] ?? '')), 0, 500),
            'og_image'         => mb_substr(trim((string) ($_POST['og_image'] ?? '')), 0, 255),
            'updated_by'       => Auth::id(),
        ];

        if ($isNew) {
            $id = $this->pages->create($data + ['created_by' => Auth::id()]);
            ActivityLog::log('page.created', "Created page #$id: {$data['title']} (/{$data['slug']})", 'page', $id);
            flash('success', 'Page created.');
        } else {
            $this->pages->update($id, $data);
            ActivityLog::log('page.updated', "Updated page #$id: {$data['title']}", 'page', $id);
            flash('success', 'Page updated.');
        }
        $this->redirect('/admin/pages/' . $id . '/edit');
    }

    /* POST /admin/pages/{id}/delete */
    public function delete(string $id): void
    {
        $page = $this->pages->find((int) $id);
        if (!$page) { flash('error', 'Page not found.'); $this->redirect('/admin/pages'); }
        $this->pages->delete((int) $id);
        ActivityLog::log('page.deleted', "Deleted page #$id: {$page['title']}", 'page', (int) $id);
        flash('success', 'Page deleted.');
        $this->redirect('/admin/pages');
    }

    private function renderAdmin(string $template, array $data): void
    {
        \Core\View::setRoot(BASE_PATH . '/admin/views');
        $this->view($template, $data, 'admin');
    }
}
