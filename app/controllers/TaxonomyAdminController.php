<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Helpers\Seo;
use Helpers\Slugger;
use Helpers\Validator;
use Models\ActivityLog;
use Models\Category;
use Models\Tag;

/**
 * Admin → Categories & Tags (Phase 7).
 */
class TaxonomyAdminController extends Controller
{
    /* ============================ CATEGORIES ============================ */

    public function categories(): void
    {
        Seo::title('Categories — Admin');
        $edit = isset($_GET['edit']) ? (new Category())->find((int) $_GET['edit']) : null;
        $this->renderAdmin('taxonomies/categories', [
            'title'     => 'Categories',
            'rows'      => (new Category())->withCounts(true),
            'edit'      => $edit,
            'activeNav' => 'categories',
            'crumbs'    => [['label' => 'Dashboard', 'url' => '/admin'], ['label' => 'Categories']],
        ]);
    }

    public function saveCategory(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $v = new Validator($_POST);
        $v->required('name', 'Name')->minLen('name', 2, 'Name')->maxLen('name', 100, 'Name');
        if ($v->fails()) {
            flash('error', $v->firstError());
            $this->redirect('/admin/categories' . ($id ? '?edit=' . $id : ''));
        }

        $data = [
            'name'        => trim((string) $_POST['name']),
            'slug'        => Slugger::unique('categories', !empty($_POST['slug']) ? (string) $_POST['slug'] : (string) $_POST['name'], $id),
            'description' => mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 500),
            'sort_order'  => (int) ($_POST['sort_order'] ?? 0),
        ];
        if (!empty($_FILES['image']['name'])) {
            try {
                $res = \Helpers\Uploader::image($_FILES['image'], 'categories');
                $data['image'] = '/' . $res['path'];
            } catch (\Throwable $e) {
                flash('error', 'Image: ' . $e->getMessage());
            }
        }

        $cat = new Category();
        if ($id && $cat->find($id)) {
            $cat->update($id, $data);
            ActivityLog::log('category.updated', "Updated category #$id: {$data['name']}", 'category', $id);
            flash('success', 'Category updated.');
        } else {
            $id = $cat->create($data);
            ActivityLog::log('category.created', "Created category #$id: {$data['name']}", 'category', $id);
            flash('success', 'Category created.');
        }
        $this->redirect('/admin/categories');
    }

    public function deleteCategory(string $id): void
    {
        $cat = (new Category())->find((int) $id);
        if (!$cat) { flash('error', 'Not found.'); $this->redirect('/admin/categories'); }
        // Posts keep working with NULL category (FK is SET NULL) — warn in UI via JS confirm.
        (new Category())->delete((int) $id);
        ActivityLog::log('category.deleted', "Deleted category #$id: {$cat['name']}", 'category', (int) $id);
        flash('success', 'Category deleted. Its posts are now uncategorised.');
        $this->redirect('/admin/categories');
    }

    /* ============================== TAGS =============================== */

    public function tags(): void
    {
        Seo::title('Tags — Admin');
        $edit = isset($_GET['edit']) ? (new Tag())->find((int) $_GET['edit']) : null;
        $this->renderAdmin('taxonomies/tags', [
            'title'     => 'Tags',
            'rows'      => (new Tag())->withCounts(),
            'edit'      => $edit,
            'activeNav' => 'tags',
            'crumbs'    => [['label' => 'Dashboard', 'url' => '/admin'], ['label' => 'Tags']],
        ]);
    }

    public function saveTag(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $v = new Validator($_POST);
        $v->required('name', 'Name')->minLen('name', 2, 'Name')->maxLen('name', 80, 'Name');
        if ($v->fails()) {
            flash('error', $v->firstError());
            $this->redirect('/admin/tags' . ($id ? '?edit=' . $id : ''));
        }
        $data = [
            'name' => trim((string) $_POST['name']),
            'slug' => Slugger::unique('tags', !empty($_POST['slug']) ? (string) $_POST['slug'] : (string) $_POST['name'], $id),
        ];
        $tag = new Tag();
        if ($id && $tag->find($id)) {
            $tag->update($id, $data);
            ActivityLog::log('tag.updated', "Updated tag #$id: {$data['name']}", 'tag', $id);
            flash('success', 'Tag updated.');
        } else {
            $id = $tag->create($data);
            ActivityLog::log('tag.created', "Created tag #$id: {$data['name']}", 'tag', $id);
            flash('success', 'Tag created.');
        }
        $this->redirect('/admin/tags');
    }

    public function deleteTag(string $id): void
    {
        $tag = (new Tag())->find((int) $id);
        if (!$tag) { flash('error', 'Not found.'); $this->redirect('/admin/tags'); }
        (new Tag())->delete((int) $id); // blog_tags cascade removes pivots
        ActivityLog::log('tag.deleted', "Deleted tag #$id: {$tag['name']}", 'tag', (int) $id);
        flash('success', 'Tag deleted.');
        $this->redirect('/admin/tags');
    }

    private function renderAdmin(string $template, array $data): void
    {
        \Core\View::setRoot(BASE_PATH . '/admin/views');
        $this->view($template, $data, 'admin');
    }
}
