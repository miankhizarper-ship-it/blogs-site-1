<?php
declare(strict_types=1);

namespace Controllers;

use Core\Auth;
use Core\Controller;
use Helpers\Seo;
use Models\ActivityLog;

/**
 * Admin → Tools (Phase 9 #5): activity log viewer, database backup/export,
 * cache clear, phpinfo-style diagnostics.
 */
class ToolsAdminController extends Controller
{
    /* GET /admin/tools */
    public function index(): void
    {
        Seo::title('Tools — Admin');
        $backups = glob(BASE_PATH . '/storage/backups/*.sql.gz') ?: [];
        rsort($backups);
        $this->renderAdmin('tools/index', [
            'title'     => 'Tools & Maintenance',
            'backups'   => array_map(fn ($f) => [
                'name'  => basename($f),
                'size'  => round(filesize($f) / 1024, 1) . ' KB',
                'mtime' => date('Y-m-d H:i', filemtime($f)),
            ], array_slice($backups, 0, 20)),
            'php'       => PHP_VERSION,
            'db'        => $this->dbSize(),
            'uploads'   => $this->dirSize(BASE_PATH . '/public/uploads'),
            'activeNav' => 'tools',
            'crumbs'    => [['label' => 'Dashboard', 'url' => '/admin'], ['label' => 'Tools']],
        ]);
    }

    /* GET /admin/activity */
    public function activity(): void
    {
        Seo::title('Activity Log — Admin');
        $log = new ActivityLog();
        $this->renderAdmin('tools/activity', [
            'title'     => 'Activity Log',
            'result'    => $log->paginate(max(1, (int) ($_GET['page'] ?? 1)), 30, trim((string) ($_GET['q'] ?? ''))),
            'q'         => trim((string) ($_GET['q'] ?? '')),
            'activeNav' => 'activity',
            'crumbs'    => [['label' => 'Dashboard', 'url' => '/admin'], ['label' => 'Activity Log']],
        ]);
    }

    /* POST /admin/tools/backup — mysqldump → gzip into storage/backups, then stream download */
    public function backup(): void
    {
        $cfg  = require BASE_PATH . '/config/database.php';
        $dir  = BASE_PATH . '/storage/backups';
        if (!is_dir($dir)) { mkdir($dir, 0775, true); }
        $file = "$dir/backup-" . date('Ymd-His') . '.sql.gz';

        $env = '';
        foreach ([['MYSQL_PWD', $cfg['password']]] as [$k, $v]) { $env .= "$k='$v' "; }
        $cmd = sprintf(
            '%s mysqldump --single-transaction --quick --add-drop-table -h %s -P %s -u %s %s 2>/dev/null | gzip > %s',
            $env,
            escapeshellarg($cfg['host']),
            escapeshellarg((string) $cfg['port']),
            escapeshellarg($cfg['username']),
            escapeshellarg($cfg['database']),
            escapeshellarg($file)
        );
        exec($cmd, $o, $code);

        if ($code !== 0 || !is_file($file) || filesize($file) < 20) {
            // Fallback: pure-PHP dump (no exec available on many shared hosts).
            $this->phpBackup($file);
        }

        ActivityLog::log('tools.backup', 'Database backup created: ' . basename($file));

        if (PHP_SAPI === 'cli') {
            echo "Backup written to $file\n";
            return;
        }
        // Stream it to the browser as a download.
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    /** Portable PHP dump: DDL + INSERTs, gzipped. */
    private function phpBackup(string $file): void
    {
        $pdo = \Database::conn();
        $gz  = gzopen($file, 'wb9');
        $tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(\PDO::FETCH_COLUMN);
        gzwrite($gz, "-- Khizar Blog backup " . date('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        foreach ($tables as $t) {
            $create = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(\PDO::FETCH_NUM)[1];
            gzwrite($gz, "DROP TABLE IF EXISTS `$t`;\n$create;\n\n");
            $rows = $pdo->query("SELECT * FROM `$t`")->fetchAll(\PDO::FETCH_ASSOC);
            foreach (array_chunk($rows, 200) as $chunk) {
                $vals = [];
                foreach ($chunk as $r) {
                    $cols = '`' . implode('`,`', array_keys($r)) . '`';
                    $ph   = '(' . implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($r))) . ')';
                    $vals[] = $ph;
                }
                gzwrite($gz, "INSERT INTO `$t` ($cols) VALUES\n" . implode(",\n", $vals) . ";\n");
            }
            gzwrite($gz, "\n");
        }
        gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);
    }

    /* GET /admin/tools/download/{name} */
    public function download(string $name): void
    {
        $safe = basename($name); // strip traversal
        $file = BASE_PATH . '/storage/backups/' . $safe;
        if (!str_ends_with($safe, '.sql.gz') || !is_file($file)) {
            http_response_code(404); exit('Not found');
        }
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $safe . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    /* POST /admin/tools/delete-backup */
    public function deleteBackup(): void
    {
        $safe = basename((string) ($_POST['name'] ?? ''));
        $file = BASE_PATH . '/storage/backups/' . $safe;
        if (str_ends_with($safe, '.sql.gz') && is_file($file)) {
            unlink($file);
            ActivityLog::log('tools.backup_deleted', "Removed backup $safe");
            flash('success', 'Backup deleted.');
        } else {
            flash('error', 'Backup not found.');
        }
        $this->redirect('/admin/tools');
    }

    /* POST /admin/tools/clear-cache */
    public function clearCache(): void
    {
        $pat = BASE_PATH . '/storage/cache/*';
        $n = 0;
        foreach (glob($pat) ?: [] as $f) {
            if (is_file($f) && basename($f) !== '.gitignore') { unlink($f); $n++; }
        }
        ActivityLog::log('tools.cache_cleared', "Cleared $n cache files");
        flash('success', "Cache cleared ($n files).");
        $this->redirect('/admin/tools');
    }

    private function dbSize(): string
    {
        try {
            $cfg  = require BASE_PATH . '/config/database.php';
            $v = \Database::run(
                'SELECT ROUND(SUM(data_length + index_length)/1024/1024, 2) AS mb
                 FROM information_schema.tables WHERE table_schema = ?',
                [$cfg['database']]
            )->fetch();
            return ($v['mb'] ?? 0) . ' MB';
        } catch (\Throwable $e) {
            return '?';
        }
    }

    private function dirSize(string $dir): string
    {
        if (!is_dir($dir)) return '0 KB';
        $bytes = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) { $bytes += $f->getSize(); }
        return round($bytes / 1024, 1) . ' KB';
    }

    private function renderAdmin(string $template, array $data): void
    {
        \Core\View::setRoot(BASE_PATH . '/admin/views');
        $this->view($template, $data, 'admin');
    }
}
