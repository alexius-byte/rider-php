<?php

declare(strict_types=1);

namespace Rider\System\Database;

use PDO;
use DateTime;
use Throwable;

abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected bool $timestamps = true;

    private PDO $pdo;
    private ?string $orderByColumn = null;
    private OrderDirection $orderByDir = OrderDirection::DESC;

    public function __construct()
    {
        $this->pdo = Connection::getInstance();
    }

    public function findById(int|string $id): object|null
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    public function orderBy(string $column, OrderDirection $direction = OrderDirection::DESC): static
    {
        $this->orderByColumn = $column;
        $this->orderByDir = $direction;

        return $this;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table}" . $this->buildOrderBy());
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findBy(string $column, mixed $value, bool $all = true): array|object|null
    {
        $order = $all ? $this->buildOrderBy() : '';
        $this->orderByColumn = null;

        $sql = "SELECT * FROM {$this->table} WHERE {$column} = ?" . ($all ? $order : ' LIMIT 1');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$value]);

        if (!$all) {
            $result = $stmt->fetch();
            return $result === false ? null : $result;
        }

        return $stmt->fetchAll();
    }

    public function search(string $column, string $value): array
    {
        $order = $this->buildOrderBy();
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE {$column} LIKE ?" . $order
        );
        $stmt->execute(["%{$value}%"]);

        return $stmt->fetchAll();
    }

    public function between(string $column, mixed $min, mixed $max, bool $all = false): array|object|null
    {
        $order = $all ? $this->buildOrderBy() : '';
        $sql = "SELECT * FROM {$this->table} WHERE {$column} BETWEEN ? AND ?" . ($all ? $order : ' LIMIT 1');
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$min, $max]);

        if (!$all) {
            $result = $stmt->fetch();
            return $result === false ? null : $result;
        }

        return $stmt->fetchAll();
    }

    public function first(string $column, mixed $value): object|null
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE {$column} = ? LIMIT 1"
        );
        $stmt->execute([$value]);
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    public function count(): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table}");
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    public function create(array $data): int|string
    {
        $externalId = $data[$this->primaryKey] ?? null;
        $data = $this->filter($data);

        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
        }

        $columns = implode(', ', array_map(fn(string $c) => "`{$c}`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})"
        );
        $stmt->execute(array_values($data));

        return $externalId ?? $this->pdo->lastInsertId();
    }

    public function upsert(array $data): void
    {
        $primaryValue = $data[$this->primaryKey] ?? null;
        $data = $this->filter($data);

        if ($primaryValue !== null) {
            $data = [$this->primaryKey => $primaryValue] + $data;
        }

        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
        }

        $incrementals = [];
        $insertData = [];

        foreach ($data as $key => $value) {
            if (is_string($value) && preg_match('/^([+-])(\d+(?:\.\d+)?)$/', $value, $m)) {
                $amount = str_contains($m[2], '.') ? (float) $m[2] : (int) $m[2];
                $incrementals[$key] = ['op' => $m[1], 'amount' => $amount];
                $insertData[$key] = $amount;
            } else {
                $insertData[$key] = $value;
            }
        }

        $columns = implode(', ', array_map(fn(string $c) => "`{$c}`", array_keys($insertData)));
        $placeholders = implode(', ', array_fill(0, count($insertData), '?'));

        $skipOnUpdate = [$this->primaryKey, 'created_at'];
        $updates = [];

        foreach (array_keys($insertData) as $col) {
            if (in_array($col, $skipOnUpdate, true)) {
                continue;
            }

            if (isset($incrementals[$col])) {
                $updates[] = "`{$col}` = `{$col}` {$incrementals[$col]['op']} {$incrementals[$col]['amount']}";
            } else {
                $updates[] = "`{$col}` = VALUES(`{$col}`)";
            }
        }

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE " . implode(', ', $updates);

        $this->pdo->prepare($sql)->execute(array_values($insertData));
    }

    public function update(object $entity): bool
    {
        $data = (array)$entity;
        $id = $data[$this->primaryKey] ?? null;

        if ($id === null) {
            return false;
        }

        unset($data[$this->primaryKey]);

        $data = $this->filter($data);

        if ($this->timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $set = implode(', ', array_map(fn(string $col) => "`{$col}` = ?", array_keys($data)));
        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table} SET {$set} WHERE {$this->primaryKey} = ?"
        );
        $stmt->execute([...array_values($data), $id]);

        return $stmt->rowCount() > 0;
    }

    public function random(): object|null
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} ORDER BY RAND() LIMIT 1"
        );
        $stmt->execute();
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    public function truncate(): void
    {
        $this->pdo->exec("TRUNCATE TABLE {$this->table}");
    }

    public function deleteOlderThan(int $amount, string $column, TimeUnit $unit = TimeUnit::DAYS): int
    {
        $cutoff = (new DateTime("-{$amount} {$unit->value}"))->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE {$column} <= ?");
        $stmt->execute([$cutoff]);
        return $stmt->rowCount();
    }

    public function delete(int|string $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function query(string $sql, mixed $params = []): array
    {
        $param = is_array($params) ? $params : [$params];
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($param);
        return $stmt->fetchAll();
    }

    public function execute(string $sql, array $params = []): bool
    {
        return $this->pdo->prepare($sql)->execute($params);
    }

    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $fn($this);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function buildOrderBy(): string
    {
        $column = $this->orderByColumn ?? $this->primaryKey;
        $clause = " ORDER BY `{$column}` {$this->orderByDir->value}";
        $this->orderByColumn = null;
        $this->orderByDir = OrderDirection::DESC;

        return $clause;
    }

    private function filter(array $data): array
    {
        if ($this->fillable === []) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }
}