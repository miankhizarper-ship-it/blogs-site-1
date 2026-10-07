<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\RateLimiter;
use Helpers\Validator;
use Models\Subscriber;

/**
 * POST /subscribe — AJAX newsletter signup (JSON response).
 * CSRF + honeypot + rate limiting, idempotent upsert.
 */
class SubscribeController extends Controller
{
    public function store(): void
    {
        \Core\Csrf::verify();

        // Honeypot field: bots fill it, humans never see it.
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            json_response(['ok' => true, 'message' => 'Subscribed!']);
        }

        if (!RateLimiter::allow('subscribe', 5, 300)) {
            json_response(['ok' => false, 'message' => 'Too many attempts — please try again later.'], 429);
        }

        $v = new Validator($_POST);
        $v->required('email', 'Email')->email('email', 'Email')->maxLen('email', 190, 'Email');
        if ($v->fails()) {
            json_response(['ok' => false, 'message' => $v->firstError()], 422);
        }

        $sub = new Subscriber();
        $msg = $sub->subscribe(trim((string) $_POST['email']));
        json_response(['ok' => true, 'message' => $msg]);
    }
}
