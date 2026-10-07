<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Auth;
use Helpers\Sanitizer;
use Helpers\Seo;
use Helpers\Slugger;
use Helpers\Validator;
use Models\ActivityLog;
use Models\Blog;
use Models\Category;
use Models\Tag;
use Models\User;

/**
 * Admin → Blogs module (Phases 6–7): list, editor, trash, ranking, featured.
 */
class BlogAdminController extends Controller
{
    private Blog $blogs;

    public function __construct()
    {
        $this->blogs = new Blog();
    }

    /** Authors may only see their own posts. */
    private function scope(array $filters): array
    {
        if (Auth::role() === 'author') {
            $filters['author_id'] = (int) Auth::id();
        }
        return $filters;
    }

    /* GET /admin/blogs */
    public function index(): void
    {
        $status = (string) ($_GET['status'] ?? '');
        if (!in_array($status, ['', 'published', 'draft', 'scheduled', 'trashed'], true)) {
            $status = '';
        }
        $filters = $this->scope([
            'q'          => trim((string) ($_GET['q'] ?? '')),
            'category'   => (string) ($_GET['category'] ?? ''),
            'featured'   => !empty($_GET['featured']) ? 1 : '',
            'sort'       => in_array((string) ($_GET['sort'] ?? ''), ['latest','oldest','popular','title','updated'], true)
                            ? (string) $_GET['sort'] : 'updated',
            'includeAll' => true,
            'status'     => $status ?: null,
        ]);
        $result = $this->blogs->paginate($filters, max(1, (int) ($_GET['page'] ?? 1)), 15);

        Seo::title('Blogs — Admin');
        $this->renderAdmin('blogs/index', [
            'title'     => 'Blogs',
            'result'    => $result,
            'filters'   => $filters + ['status' => $status],
            'cats'      => (new Category())->withCounts(true),
            'activeNav' => 'blogs',
            'crumbs'    => [['label' => 'Dashboard', 'url' => '/admin'], ['label' => 'Blogs']],
        ]);
    }

    /* GET /admin/blogs/create | /admin/blogs/{id}/edit */
    public function form(?string $id = null): void
    {
        $blog = null;
        if ($id !== null) {
            $blog = $this->blogs->findAny((int) $id);
            if (!$blog) {
                flash('error', 'Blog not found.');
                $this->redirect('/admin/blogs');
            }
            if (Auth::role() === 'author' && (int) $blog['author_id'] !== (int) Auth::id()) {
                http_response_code(403);
                exit('403 — You can only edit your own posts.');
            }
        }

        Seo::title(($blog ? 'Edit' : 'New') . ' Blog — Admin');
        $this->renderAdmin('blogs/form', [
            'title'     => $blog ? 'Edit: ' . $blog['title'] : 'New Blog',
            'blog'      => $blog,
            'tagIds'    => $blog ? (new Tag())->tagIdsFor((int) $blog['id']) : [],
            'cats'      => (new Category())->withCounts(true),
            'tags'      => (new Tag())->all([], 'name ASC'),
            'authors'   => Auth::isAdmin() ? (new User())->adminsAndEditors() : [],
            'activeNav' => 'blogs',
            'crumbs'    => [['label' => 'Dashboard', 'url' => '/admin'],
                            ['label' => 'Blogs', 'url' => '/admin/blogs'],
                            ['label' => $blog ? 'Edit' : 'Create']],
        ]);
    }

    /* POST /admin/blogs/save */
    public function save(): void
    {
        $id    = (int) ($_POST['id'] ?? 0);
        $isNew = $id === 0;
        $blog  = $isNew ? null : $this->blogs->find($id);

        if (!$isNew && !$blog) {
            flash('error', 'Blog not found.'); $this->redirect('/admin/blogs');
        }
        if (!$isNew && Auth::role() === 'author' && (int) $blog['author_id'] !== (int) Auth::id()) {
            http_response_code(403); exit('403');
        }

        $v = new Validator($_POST);
        $v->required('title', 'Title')->minLen('title', 3, 'Title')->maxLen('title', 255, 'Title')
          ->required('content', 'Content')
          ->required('status', 'Status')->in('status', ['draft', 'published', 'scheduled'], 'Status');
        if ($v->fails()) {
            flash('error', $v->firstError());
            $this->redirect($isNew ? '/admin/blogs/create' : "/admin/blogs/$id/edit");
        }

        $status = (string) $_POST['status'];
        $publishedAt = $blog['published_at'] ?? null;
        if ($status === 'published' && !$publishedAt) {
            $publishedAt = date('Y-m-d H:i:s');
        }
        if ($status === 'scheduled') {
            $publishedAt = !empty($_POST['published_at'])
                ? date('Y-m-d H:i:s', strtotime((string) $_POST['published_at']))
                : date('Y-m-d H:i:s', strtotime('+1 day'));
        }

        // Author role always owns its posts; admins can pick the author.
        $authorId = (int) ($blog['author_id'] ?? Auth::id());
        if (Auth::isAdmin() && !empty($_POST['author_id'])) {
            $authorId = (int) $_POST['author_id'];
        }

        $slugBase = !empty($_POST['slug']) ? slugify((string) $_POST['slug']) : slugify((string) $_POST['title']);
        $data = [
            'title'            => mb_substr(trim((string) $_POST['title']), 0, 255),
            'slug'             => Slugger::unique('blogs', $slugBase, $id),
            'excerpt'          => mb_substr(trim((string) ($_POST['excerpt'] ?? '')), 0, 500),
            'content'          => Sanitizer::html((string) $_POST['content']),
            'status'           => $status,
            'category_id'      => (int) ($_POST['category_id'] ?? 0) ?: null,
            'author_id'        => $authorId,
            'published_at'     => $publishedAt,
            'reading_time'     => reading_time((string) $_POST['content']),
            'featured_image'   => $this->handleImage($blog['featured_image'] ?? null),
            // SEO panel
            'meta_title'       => mb_substr(trim((string) ($_POST['meta_title'] ?? '')), 0, 190),
            'meta_description' => mb_substr(trim((string) ($_POST['meta_description'] ?? '')), 0, 320),
            'focus_keyword'    => mb_substr(trim((string) ($_POST['focus_keyword'] ?? '')), 0, 190),
            'canonical_url'    => mb_substr(trim((string) ($_POST['canonical_url'] ?? '')), 0, 500),
            'og_title'         => mb_substr(trim((string) ($_POST['og_title'] ?? '')), 0, 190),
            'og_description'   => mb_substr(trim((string) ($_POST['og_description'] ?? '')), 0, 320),
            'robots_meta'      => ($_POST['robots_meta'] ?? 'index,follow') === 'noindex,follow' ? 'noindex,follow' : 'index,follow',
        ];
        // OG image via media picker or upload handled with featured image field
        if (!empty($_POST['og_image'])) {
            $data['og_image'] = mb_substr(trim((string) $_POST['og_image']), 0, 255);
        }

        try {
            \Database::conn()->beginTransaction();
            if ($isNew) {
                $id = $this->blogs->create($data);
            } else {
                $this->blogs->update($id, $data);
            }
            (new Tag())->syncForBlog($id, array_map('intval', (array) ($_POST['tags'] ?? [])));
            \Database::conn()->commit();
        } catch (\Throwable $e) {
            \Database::conn()->rollBack();
            error_log('[blog-save] ' . $e->getMessage());
            flash('error', 'Could not save the blog. Please try again.');
            $this->redirect($isNew ? '/admin/blogs/create' : "/admin/blogs/$id/edit");
        }

        ActivityLog::log($isNew ? 'blog.created' : 'blog.updated',
            ($isNew ? 'Created' : 'Updated') . " blog #{$id}: {$data['title']}", 'blog', $id);
        flash('success', $isNew ? 'Blog created.' : 'Blog updated.');
        $this->redirect('/admin/blogs/' . $id . '/edit');
    }

    /* POST /admin/blogs/{id}/delete — soft delete to trash */
    public function delete(string $id): void
    {
        $blog = $this->blogs->find((int) $id);
        if (!$blog) { flash('error', 'Not found.'); $this->redirect('/admin/blogs'); }
        if (Auth::role() === 'author' && (int) $blog['author_id'] !== (int) Auth::id()) {
            http_response_code(403); exit('403');
        }
        $this->blogs->softDelete((int) $id);
        ActivityLog::log('blog.trashed', "Moved blog #{$id} to trash: {$blog['title']}", 'blog', (int) $id);
        flash('success', 'Moved to trash.');
        $this->redirect('/admin/blogs?status=' . urlencode((string) ($_GET['status'] ?? '')));
    }

    /* POST /admin/blogs/{id}/restore */
    public function restore(string $id): void
    {
        $this->blogs->restore((int) $id);
        ActivityLog::log('blog.restored', "Restored blog #{$id} from trash", 'blog', (int) $id);
        flash('success', 'Blog restored as draft.');
        $this->redirect('/admin/blogs?status=trashed');
    }

    /* POST /admin/blogs/{id}/duplicate */
    public function duplicate(string $id): void
    {
        $newId = $this->blogs->duplicate((int) $id);
        if ($newId) {
            ActivityLog::log('blog.duplicated', "Duplicated blog #{$id} → #{$newId}", 'blog', $newId);
            flash('success', 'Duplicated as a new draft.');
            $this->redirect('/admin/blogs/' . $newId . '/edit');
        }
        flash('error', 'Could not duplicate.');
        $this->redirect('/admin/blogs');
    }

    /* POST /admin/blogs/bulk — action=publish|draft|trash|feature|unfeature */
    public function bulk(): void
    {
        $ids    = array_map('intval', (array) ($_POST['ids'] ?? []));
        $action = (string) ($_POST['bulk_action'] ?? '');
        if (!$ids || !in_array($action, ['publish', 'draft', 'trash', 'feature', 'unfeature'], true)) {
            flash('error', 'Nothing selected or invalid action.');
            $this->redirect('/admin/blogs');
        }

        switch ($action) {
            case 'trash':
                foreach ($ids as $i) { $this->blogs->softDelete($i); }
                break;
            case 'feature':
            case 'unfeature':
                foreach ($ids as $i) { $this->blogs->toggleFeatured($i, $action === 'feature'); }
                break;
            default:
                $this->blogs->bulkStatus($ids, $action);
        }
        ActivityLog::log('blog.bulk', strtoupper($action) . ' on blogs: ' . implode(',', $ids), 'blog');
        flash('success', count($ids) . " blog(s) — $action done.");
        $this->redirect('/admin/blogs');
    }

    /* GET /admin/ranking — drag & drop trending order */
    public function ranking(): void
    {
        Seo::title('Ranking — Admin');
        $this->renderAdmin('blogs/ranking', [
            'title'     => 'Trending Ranking',
            'rows'      => $this->blogs->rankedList(),
            'activeNav' => 'ranking',
            'crumbs'    => [['label' => 'Dashboard', 'url' => '/admin'], ['label' => 'Ranking']],
        ]);
    }

    /* POST /admin/ranking/save — ordered ids (AJAX JSON) */
    public function saveRanking(): void
    {
        $order = json_decode((string) file_get_contents('php://input'), true)['order'] ?? [];
        if (!is_array($order) || !$order) {
            json_response(['ok' => false, 'message' => 'No data'], 422);
        }
        $this->blogs->reorderRanks(array_map('intval', $order));
        ActivityLog::log('blog.ranking', 'Re-sorted trending ranking (' . count($order) . ' posts)', 'blog');
        json_response(['ok' => true, 'message' => 'Ranking saved.']);
    }

    /* POST /admin/ranking/set — numeric rank for one blog */
    public function setRank(): void
    {
        $id   = (int) ($_POST['blog_id'] ?? 0);
        $rank = max(0, (int) ($_POST['rank'] ?? 0));
        $this->blogs->setRank($id, $rank);
        ActivityLog::log('blog.rank', "Blog #$id rank set to $rank", 'blog', $id);
        json_response(['ok' => true]);
    }

    /* GET /admin/featured — homepage slider manager */
    public function featured(): void
    {
        Seo::title('Featured — Admin');
        $this->renderAdmin('blogs/featured', [
            'title'     => 'Featured Blogs',
            'rows'      => $this->blogs->featuredList(),
            'candidates'=> $this->blogs->paginate(['includeAll' => true, 'status' => 'published', 'featured' => ''], 1, 100),
            'activeNav' => 'featured',
            'crumbs'    => [['label' => 'Dashboard', 'url' => '/admin'], ['label' => 'Featured']],
        ]);
    }

    /* POST /admin/featured/toggle */
    public function toggleFeatured(): void
    {
        $id = (int) ($_POST['blog_id'] ?? 0);
        $on = !empty($_POST['featured']);
        $this->blogs->toggleFeatured($id, $on);
        ActivityLog::log('blog.featured', ($on ? 'Featured' : 'Unfeatured') . " blog #$id", 'blog', $id);
        flash('success', $on ? 'Added to homepage slider.' : 'Removed from slider.');
        $this->redirect('/admin/featured');
    }

    /* POST /admin/featured/order — ordered ids */
    public function saveFeaturedOrder(): void
    {
        $order = json_decode((string) file_get_contents('php://input'), true)['order'] ?? [];
        foreach (array_map('intval', (array) $order) as $i => $id) {
            $this->blogs->update($id, ['featured_order' => $i + 1]);
        }
        ActivityLog::log('blog.featured_order', 'Re-ordered featured slider', 'blog');
        json_response(['ok' => true, 'message' => 'Slider order saved.']);
    }

    /* ------------------------------------------------------------------ */

    /** Upload/keep/remove featured image. Returns relative path or null. */
    private function handleImage(?string $current): ?string
    {
        if (($_POST['remove_image'] ?? '') === '1') {
            if ($current) {
                \Helpers\Uploader::delete(ltrim((string) parse_url($current, PHP_URL_PATH), '/'));
            }
            return null;
        }
        if (!empty($_FILES['featured_image']['name'])) {
            try {
                $res = \Helpers\Uploader::image($_FILES['featured_image'], 'blogs');
                return '/' . $res['relative_path'];
            } catch (\Throwable $e) {
                flash('error', 'Image: ' . $e->getMessage());
            }
        }
        return $current;
    }

    /** Render with admin layout (views root switched to /admin/views). */
    private function renderAdmin(string $template, array $data): void
    {
        \Core\View::setRoot(BASE_PATH . '/admin/views');
        $this->view($template, $data, 'admin');
    }
}
