<?php
declare(strict_types=1);

namespace Core;

use Database;
use PDOStatement;

/**
 * Base model — thin PDO wrapper. All methods use prepared statements.
 * Subclasses set protected string $table.
 */
abstract class Model
{
    protected string $table = '';

    protected function db(): \PDO
    {
        return Database::conn();
    }

    protected function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function all(array $where = [], string $orderBy = 'id DESC', int $limit = 0): array
    {
        [$clause, $bind] = $this->buildWhere($where);
        $sql = "SELECT * FROM {$this->table} $clause ORDER BY $orderBy";
        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
        }
        return $this->run($sql, $bind)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $row = $this->run("SELECT * FROM {$this->table} WHERE id = ? LIMIT 1", [$id])->fetch();
        return $row === false ? null : $row;
    }

    public function findBy(string $column, mixed $value): ?array
    {
        $column = $this->safeCol($column);
        $row = $this->run("SELECT * FROM {$this->table} WHERE $column = ? LIMIT 1", [$value])->fetch();
        return $row === false ? null : $row;
    }

    public function create(array $data): int
    {
        $cols = array_keys($data);
        foreach ($cols as $c) {
            $this->safeCol($c); // validate identifiers
        }
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $cols),
            implode(', ', array_fill(0, count($cols), '?'))
        );
        $this->run($sql, array_values($data));
        return (int) $this->db()->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sets = [];
        $vals = [];
        foreach ($data as $col => $val) {
            $this->safeCol($col);
            $sets[] = "$col = ?";
            $vals[] = $val;
        }
        $vals[] = $id;
        return $this->run(
            "UPDATE {$this->table} SET " . implode(', ', $sets) . ' WHERE id = ?',
            $vals
        )->rowCount() >= 0;
    }

    public function delete(int $id): bool
    {
        return $this->run("DELETE FROM {$this->table} WHERE id = ?", [$id])->rowCount() > 0;
    }

    public function count(array $where = []): int
    {
        [$clause, $bind] = $this->buildWhere($where);
        return (int) $this->run("SELECT COUNT(*) AS c FROM {$this->table} $clause", $bind)
            ->fetch()['c'];
    }

    /** Simple equality/IN where builder — values always bound. */
    protected function buildWhere(array $where): array
    {
        $parts = [];
        $bind  = [];
        foreach ($where as $col => $val) {
            $this->safeCol((string) $col);
            if (is_array($val)) {
                $parts[] = "$col IN (" . implode(',', array_fill(0, count($val), '?')) . ')';
                $bind    = array_merge($bind, $val);
            } elseif ($val === null) {
                $parts[] = "$col IS NULL";
            } else {
                $parts[] = "$col = ?";
                $bind[]  = $val;
            }
        }
        return [$parts ? 'WHERE ' . implode(' AND ', $parts) : '', $bind];
    }

    /** Whitelist column names to keep dynamic SQL safe. */
    protected function safeCol(string $col): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col)) {
            throw new \InvalidArgumentException("Illegal column name: $col");
        }
        return $col;
    }
}
