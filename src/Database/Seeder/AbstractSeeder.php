<?php

declare(strict_types=1);

namespace Rider\System\Database\Seeder;

use PDO;

abstract class AbstractSeeder
{
    private PDO $pdo;

    public function boot(PDO $pdo): void
    {
        $this->pdo = $pdo;
    }

    abstract public function run(): void;

    protected function insert(string $table, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $columns = array_keys($rows[0]);
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $allSlots = implode(', ', array_fill(0, count($rows), $placeholders));

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $table,
            implode(', ', $columns),
            $allSlots
        );

        $values = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                $values[] = $value;
            }
        }

        $this->pdo->prepare($sql)->execute($values);
    }
}
