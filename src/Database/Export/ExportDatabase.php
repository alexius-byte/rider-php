<?php

declare(strict_types=1);

namespace Rider\System\Database\Export;

use PDO;
use Rider\System\Database\Connection;

class ExportDatabase
{
    protected array $exclude = [];
    protected string $savePath;
    private PDO $pdo;

    public function __construct(string $savePath)
    {
        $this->savePath = $savePath;
        $this->pdo = Connection::getInstance();
    }

    public function setExclude(array $exclude): void
    {
        $this->exclude = $exclude;
    }

    public function export(): bool
    {
        $handle = fopen($this->savePath, 'w');

        if ($handle === false) {
            return false;
        }

        $tables = $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        fwrite($handle, "SET NAMES utf8mb4;\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

        foreach ($tables as $table) {
            if (in_array($table, $this->exclude, true)) {
                continue;
            }

            $this->writeTable($handle, $table);
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($handle);

        return true;
    }

    private function writeTable(mixed $handle, string $table): void
    {
        fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");

        $create = $this->pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
        fwrite($handle, $create[1] . ";\n\n");

        $stmt = $this->pdo->prepare("SELECT * FROM `{$table}`");
        $stmt->execute();

        $columns = null;
        $batch = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($columns === null) {
                $columns = '`' . implode('`, `', array_keys($row)) . '`';
            }

            $batch[] = '(' . implode(', ', array_map(fn(mixed $v) => $this->quote($v), $row)) . ')';

            if (count($batch) === 500) {
                $this->flushBatch($handle, $table, $columns, $batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->flushBatch($handle, $table, $columns, $batch);
        }

        fwrite($handle, "\n");
    }

    private function flushBatch(mixed $handle, string $table, string $columns, array $rows): void
    {
        fwrite($handle, "INSERT INTO `{$table}` ({$columns}) VALUES\n");
        fwrite($handle, implode(",\n", $rows) . ";\n");
    }

    private function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return $this->pdo->quote((string)$value);
    }
}
