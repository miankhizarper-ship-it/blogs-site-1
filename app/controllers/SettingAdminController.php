<?php
declare(strict_types=1);

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Core\Settings;
use Helpers\Seo;
use Helpers\Validator;
use Models\ActivityLog;

/**
 * Admin → Settings (Phase 9 #4): site identity, logo/favicon, social links,
 * SMTP, default SEO, analytics/ads snippets, posts-per-page, comment moderation,
 * maintenance mode. Admin-only.
 */
class SettingAdminController extends Controller
{
    /** Whitelist: key => [type, maxLen]. type: text|bool|int|html|image */
    private const FIELDS = [
        // Identity
        'site_name'          => ['text', 100],
        'tagline'            => ['text', 200],
        'description'        => ['text', 500],
        'contact_email'      => ['text', 190],
        'logo'               => ['image', 255],
        'favicon'            => ['image', 255],
        // Social
        'social_facebook'    => ['text', 255],
        'social_twitter'     => ['text', 255],
        'social_instagram'   => ['text', 255],
        'social_youtube'     => ['text', 255],
        'social_linkedin'    => ['text', 255],
        'social_github'      => ['text', 255],
        'social_whatsapp'    => ['text', 255],
        // Reading
        'posts_per_page'     => ['int', 3],
        'excerpt_length'     => ['int', 4],
        // Comments
        'comments_enabled'   => ['bool', 1],
        'comment_moderation' => ['bool', 1],
        // Maintenance
        'maintenance_mode'   => ['bool', 1],
        'maintenance_message'=> ['text', 1000],
        // Email / SMTP
        'mail_enabled'       => ['bool', 1],
        'smtp_host'          => ['text', 190],
        'smtp_port'          => ['int', 5],
        'smtp_secure'        => ['text', 10],
        'smtp_user'          => ['text', 190],
        'smtp_pass'          => ['text', 190],
        'mail_from'          => ['text', 190],
        'mail_from_name'     => ['text', 190],
        'email_notify_contact'      => ['bool', 1],
        'email_notify_new_comment'  => ['bool', 1],
        // Default SEO
        'meta_title_default' => ['text', 190],
        'meta_description_default' => ['text', 320],
        'meta_keywords_default'    => ['text', 500],
        'og_site_name'       => ['text', 100],
        'og_default_image'   => ['image', 255],
        'twitter_card_type'  => ['text', 30],
        'robots_txt'         => ['text', 2000],
        'google_verify'      => ['text', 200],
        // Snippets
        'analytics_id'       => ['text', 100],
        'head_snippet'       => ['html', 8000],
        'body_snippet'       => ['html', 8000],
    ];

    public function __construct()
    {
        if (!Auth::isAdmin()) {
            http_response_code(403);
            exit('403 — Only administrators can change settings.');
        }
    }

    /* GET /admin/settings?tab=general */
    public function index(): void
    {
        $tabs = [
            'general'  => 'General',
            'branding' => 'Branding',
            'seo'      => 'SEO',
            'email'    => 'Email / SMTP',
            'reading'  => 'Discussion & Reading',
            'scripts'  => 'Analytics & Ads',
        ];
        $tab = (string) ($_GET['tab'] ?? 'general');
        if (!isset($tabs[$tab])) { $tab = 'general'; }

        Seo::title('Settings — Admin');
        \Core\View::setRoot(BASE_PATH . '/admin/views');
        $this->view('settings/index', [
            'title'     => 'Settings',
            'tabs'      => $tabs,
            'tab'       => $tab,
            's'         => Settings::all(),
            'activeNav' => 'settings',
            'crumbs'    => [['label' => 'Dashboard', 'url' => '/admin'], ['label' => 'Settings']],
        ], 'admin');
    }

    /* POST /admin/settings/save */
    public function save(): void
    {
        $changed = [];
        foreach (self::FIELDS as $key => [$type, $max]) {
            if ($type === 'image') {
                continue; // handled via uploads below
            }
            if ($type === 'bool') {
                $val = empty($_POST[$key]) ? '0' : '1';
            } elseif ($type === 'int') {
                $val = (string) max(0, (int) ($_POST[$key] ?? 0));
            } elseif ($type === 'html') {
                // Allow raw HTML snippets (admin-trusted), strip scripts tags from <script> injection of event handlers only via CSP later.
                $val = mb_substr((string) ($_POST[$key] ?? ''), 0, $max);
            } else {
                $val = mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $max);
            }
            if (Settings::get($key, null) !== $val) {
                Settings::put($key, $val);
                $changed[] = $key;
            }
        }

        // Logo / favicon / og-default image uploads
        foreach (['logo' => 'branding', 'favicon' => 'branding', 'og_default_image' => 'branding'] as $field => $folder) {
            if (!empty($_FILES[$field]['name'])) {
                try {
                    $res = \Helpers\Uploader::image($_FILES[$field], $folder, 1_048_576);
                    Settings::put($field, '/' . $res['path']);
                    $changed[] = $field;
                } catch (\Throwable $e) {
                    flash('error', strtoupper($field) . ': ' . $e->getMessage());
                }
            }
        }

        if (!empty($_POST['remove_logo']))     { Settings::put('logo', '');     $changed[] = 'logo'; }
        if (!empty($_POST['remove_favicon']))  { Settings::put('favicon', '');  $changed[] = 'favicon'; }

        ActivityLog::log('settings.updated', 'Settings changed: ' . implode(', ', $changed ?: ['(none)']), 'setting');
        flash('success', 'Settings saved.');
        $this->redirect('/admin/settings?tab=' . urlencode((string) ($_POST['tab'] ?? 'general')));
    }

    /* POST /admin/settings/test-mail — sends a test email through PHP mail or configured SMTP-ish path */
    public function testMail(): void
    {
        $v = new Validator($_POST);
        $v->required('test_to', 'Recipient')->email('test_to', 'Recipient');
        if ($v->fails()) {
            flash('error', $v->firstError());
            $this->redirect('/admin/settings?tab=email');
        }
        $to   = trim((string) $_POST['test_to']);
        $from = Settings::get('mail_from', 'noreply@localhost');
        $name = Settings::get('mail_from_name', Settings::get('site_name', 'Blog'));
        $ok   = @mail($to, 'Test email from ' . $name,
            "This is a test message from your blog at " . base_url() . "\n\nIf you received this, PHP mail() transport works.\nSMTP settings are stored and used by the Mailer helper.",
            "From: $name <$from>");
        ActivityLog::log('settings.test_mail', "Test email to $to: " . ($ok ? 'sent' : 'FAILED'), 'setting');
        flash($ok ? 'success' : 'error', $ok ? "Test mail sent to $to." : 'Sending failed — check server mail logs / SMTP config.');
        $this->redirect('/admin/settings?tab=email');
    }
}
