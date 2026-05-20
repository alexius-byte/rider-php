<?php

declare(strict_types=1);

namespace Rider\System\Database;

use PDO;

class TruncateTables
{
    private array $tables = [];
    private PDO   $pdo;

    public function __construct()
    {
        $this->pdo = Connection::getInstance();
    }

    public function addTable(string $table): void
    {
        $this->tables[] = $table;
    }

    public function addTables(array $tables): void
    {
        $this->tables = array_merge($this->tables, $tables);
    }

    public function truncate(): bool
    {
        foreach ($this->tables as $table) {
            if ($this->pdo->exec("TRUNCATE TABLE `{$table}`") === false) {
                return false;
            }
        }

        return true;
    }

    public function truncateAll(array $exclude = []): bool
    {
        $tables = $this->pdo
            ->query('SHOW TABLES')
            ->fetchAll(PDO::FETCH_COLUMN);

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tables as $table) {
            if (in_array($table, $exclude, true)) {
                continue;
            }

            if ($this->pdo->exec("TRUNCATE TABLE `{$table}`") === false) {
                $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                return false;
            }
        }

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        return true;
    }
}