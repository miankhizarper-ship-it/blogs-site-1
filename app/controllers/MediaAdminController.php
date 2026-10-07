<?php
declare(strict_types=1);

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Helpers\Seo;
use Helpers\Uploader;
use Models\ActivityLog;
use Models\Medium;

/**
 * Admin → Media library (Phase 9): drag-drop upload, validation, thumbnails,
 * delete, copy URL, and a picker usable inside the blog editor.
 */
class MediaAdminController extends Controller
{
    private Medium $media;

    public function __construct()
    {
        $this->media = new Medium();
    }

    /* GET /admin/media */
    public function index(): void
    {
        Seo::title('Media — Admin');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $q    = trim((string) ($_GET['q'] ?? ''));
        $this->renderAdmin('media/index', [
            'title'     => 'Media Library',
            'result'    => $this->media->paginate($page, 24, $q),
            'q'         => $q,
            'activeNav' => 'media',
            'crumbs'    => [['label' => 'Dashboard', 'url' => '/admin'], ['label' => 'Media']],
        ]);
    }

    /* POST /admin/media/upload — one file per request (multi handled client-side) */
    public function upload(): void
    {
        if (!isset($_FILES['file'])) {
            json_response(['ok' => false, 'message' => 'No file received.'], 422);
        }
        try {
            $res = Uploader::image($_FILES['file'], 'media');
        } catch (\Throwable $e) {
            json_response(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $id = $this->media->record([
            'file_name'     => basename($res['path']),
            'original_name' => mb_substr(basename((string) $_FILES['file']['name']), 0, 255),
            'file_path'     => '/' . $res['path'],
            'mime_type'     => $res['mime'],
            'file_size'     => (int) $_FILES['file']['size'],
            'width'         => $res['width'],
            'height'        => $res['height'],
            'alt_text'      => mb_substr(pathinfo(basename((string) $_FILES['file']['name']), PATHINFO_FILENAME), 0, 255),
        ], Auth::id());

        ActivityLog::log('media.uploaded', 'Uploaded ' . basename($res['path']), 'media', $id);
        json_response([
            'ok'   => true,
            'item' => [
                'id'    => $id,
                'url'   => url('/' . $res['path']),
                'thumb' => url('/' . ($res['webp'] ?? $res['path'])),
                'name'  => htmlspecialchars(basename((string) $_FILES['file']['name'])),
                'size'  => round(((int) $_FILES['file']['size']) / 1024, 1) . ' KB',
                'dims'  => $res['width'] . '×' . $res['height'],
            ],
        ]);
    }

    /* POST /admin/media/{id}/delete */
    public function delete(string $id): void
    {
        $row = $this->media->find((int) $id);
        if (!$row) {
            flash('error', 'File not found.');
            $this->redirect('/admin/media');
        }
        // Editors/admins can delete anything; authors only their own uploads.
        if (Auth::role() === 'author' && (int) $row['uploaded_by'] !== (int) Auth::id()) {
            http_response_code(403); exit('403');
        }
        Uploader::delete(ltrim((string) parse_url($row['file_path'], PHP_URL_PATH), '/'));
        $this->media->delete((int) $id);
        ActivityLog::log('media.deleted', 'Deleted media #' . $id . ' ' . $row['file_name'], 'media', (int) $id);
        flash('success', 'File deleted.');
        $this->redirect('/admin/media');
    }

    /* POST /admin/media/{id} — update alt text (AJAX JSON from picker modal) */
    public function updateAlt(): void
    {
        $id  = (int) ($_POST['id'] ?? 0);
        $alt = mb_substr(trim((string) ($_POST['alt_text'] ?? '')), 0, 255);
        $this->media->update($id, ['alt_text' => $alt]);
        json_response(['ok' => true]);
    }

    /* GET /admin/media/picker?field=featured_image — iframe-friendly grid for the editor */
    public function picker(): void
    {
        Seo::title('Media Picker');
        Seo::noindex();
        $field = preg_replace('/[^a-z_-]/i', '', (string) ($_GET['field'] ?? 'image'));
        $page  = max(1, (int) ($_GET['page'] ?? 1));
        \Core\View::setRoot(BASE_PATH . '/admin/views');
        $this->view('media/picker', [
            'result' => $this->media->paginate($page, 24, trim((string) ($_GET['q'] ?? ''))),
            'q'      => trim((string) ($_GET['q'] ?? '')),
            'field'  => $field,
        ], null);
    }

    private function renderAdmin(string $template, array $data): void
    {
        \Core\View::setRoot(BASE_PATH . '/admin/views');
        $this->view($template, $data, 'admin');
    }
}
