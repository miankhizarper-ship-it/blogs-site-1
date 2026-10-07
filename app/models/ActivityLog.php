<?php
declare(strict_types=1);

namespace Models;

use Core\Model;

/** Audit trail of every admin action. */
class ActivityLog extends Model
{
    protected string $table = 'activity_logs';

    /** Alias — several controllers call ActivityLog::record(...). */
    public static function record(string $action, string $description, ?string $modelType = null, ?int $modelId = null): void
    {
        self::log($action, $description, $modelType, $modelId);
    }

    /** Fire-and-forget log entry (never throws to the caller UI). */
    public static function log(string $action, string $description, ?string $modelType = null, ?int $modelId = null): void
    {
        try {
            $m = new self();
            $m->create([
                'user_id'    => \Core\Auth::id(),
                'action'     => $action,
                'description' => mb_substr($description, 0, 500),
                'model_type' => $modelType,
                'model_id'   => $modelId,
                'ip_address' => client_ip(),
            ]);
        } catch (\Throwable $e) {
            error_log('activity log failed: ' . $e->getMessage());
        }
    }

    public function paginate(int $page, int $perPage, string $q = ''): array
    {
        $where = '';
        $bind = [];
        if ($q !== '') {
            $where = 'WHERE a.action LIKE ? OR a.description LIKE ? OR u.name LIKE ?';
            $like = "%$q%";
            $bind = [$like, $like, $like];
        }
        $base = "FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id $where";
        $total = (int) $this->run("SELECT COUNT(*) $base", $bind)->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);
        $rows = $this->run(
            "SELECT a.*, u.name AS user_name $base ORDER BY a.created_at DESC LIMIT $perPage OFFSET $offset",
            $bind
        )->fetchAll();
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage,
                'pages' => (int) ceil($total / max(1, $perPage))];
    }
}
