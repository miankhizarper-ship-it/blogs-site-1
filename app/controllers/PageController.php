<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\RateLimiter;
use Helpers\Sanitizer;
use Helpers\Seo;
use Helpers\Validator;
use Models\ContactMessage;
use Models\Page;
use Core\Settings;

/**
 * Phase 5 — static pages (DB-driven, admin-editable) + AJAX contact form.
 *
 * Routes:
 *   GET  /about /contact /privacy-policy /terms /disclaimer /cookie-policy
 *   POST /contact            (AJAX, JSON response)
 */
class PageController extends Controller
{
    /** Slugs served straight from the `pages` table. */
    private const DB_PAGES = ['about', 'privacy-policy', 'terms', 'disclaimer', 'cookie-policy'];

    /* ------------------------------------------------------------------
       GET /{page-slug}
       ------------------------------------------------------------------ */
    public function show(string $slug): void
    {
        $slug = slugify($slug);
        $page = (new Page())->findBySlug($slug);

        if (!$page) {
            // Contact page is rendered by its own view but still lives in `pages`
            // once created via admin; fall through to 404 when absent.
            (new HomeController())->notFound();
            return;
        }

        Seo::title($page['meta_title'] ?: $page['title']);
        Seo::description($page['meta_description'] ?: strip_tags((string) $page['content']));
        Seo::canonical(url('/' . $page['slug']));
        Seo::breadcrumbs([
            ['label' => 'Home', 'url' => '/'],
            ['label' => $page['title'], 'url' => '/' . $page['slug']],
        ]);

        // Contact page gets the form injected below the editorial content.
        if ($slug === 'contact') {
            $this->view('pages/contact', [
                'navCategories' => [],
                'page'          => $page,
                'body'          => Sanitizer::html((string) $page['content']),
                'email'         => Settings::get('contact_email', Settings::get('admin_email', '')),
            ]);
            return;
        }

        $this->view('pages/plain', [
            'heading' => $page['title'],
            'body'    => Sanitizer::html((string) $page['content']), // already sanitized on save; re-sanitize defensively
            'crumbs'  => [['label' => 'Home', 'url' => '/'], ['label' => $page['title'], 'url' => '/' . $page['slug']]],
        ]);
    }

    /* ------------------------------------------------------------------
       POST /contact — AJAX submit (JSON), honeypot + throttle + CSRF
       ------------------------------------------------------------------ */
    public function store(): void
    {
        \Core\Csrf::verify(); // 419 on failure

        // Honeypot: silently accept bot submissions without storing them.
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            json_response(['ok' => true, 'message' => 'Thanks — your message has been sent!']);
        }

        // Rate limit: max 3 messages per IP per 10 minutes.
        if (!RateLimiter::allow('contact', 3, 600)) {
            json_response([
                'ok'      => false,
                'message' => 'Too many messages — please try again in '
                           . RateLimiter::retryAfter('contact', 600) . ' seconds.',
            ], 429);
        }

        $v = new Validator($_POST);
        $v->required('name', 'Name')->minLen('name', 2, 'Name')
          ->required('email', 'Email')->email('email', 'Email')->maxLen('email', 190, 'Email')
          ->required('message', 'Message')->minLen('message', 10, 'Message')->maxLen('message', 5000, 'Message');

        if ($v->fails()) {
            json_response(['ok' => false, 'message' => $v->firstError(), 'errors' => $v->errors()], 422);
        }

        $data = [
            'name'    => trim((string) $_POST['name']),
            'email'   => trim((string) $_POST['email']),
            'subject' => trim((string) ($_POST['subject'] ?? '')),
            'message' => trim((string) $_POST['message']),
            'ip'      => client_ip(),
        ];

        try {
            (new ContactMessage())->log($data);
        } catch (\Throwable $e) {
            error_log('[contact] ' . $e->getMessage());
            json_response(['ok' => false, 'message' => 'Something went wrong. Please try again later.'], 500);
        }

        // Optional best-effort email notification to the site admin.
        $to = Settings::get('contact_email') ?: Settings::get('admin_email');
        if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $safe  = fn (string $s): string => str_replace(["\r", "\n"], '', $s);
            $body  = "New contact message from {$data['name']} <{$data['email']}>\n\n"
                   . ($data['subject'] ? "Subject: {$data['subject']}\n\n" : '')
                   . $data['message'];
            @mail($safe($to), $safe('[' . Settings::get('site_name', 'Blog') . '] New contact message'), $body,
                  'From: ' . $safe(Settings::get('mail_from', 'noreply@localhost')));
        }

        json_response(['ok' => true, 'message' => 'Thanks — your message has been sent! We usually reply within 48 hours.']);
    }
}
