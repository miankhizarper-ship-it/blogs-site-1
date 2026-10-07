<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;

/**
 * Temporary Phase-1 controller — replaced with real Home/Blog controllers in Phase 3.
 */
class HomeController extends Controller
{
    public function index(): void
    {
        // Quick router proof-of-life; later phases render the full homepage.
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Hello from the Router — phase 1 OK';
    }

    public function notFound(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(404);
        echo '404 — page not found (custom 404 arrives in phase 3)';
    }
}
