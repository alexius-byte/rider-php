<?php

declare(strict_types=1);

namespace Rider\System\Database\Migration;

use PDO;

abstract class AbstractMigration
{
    private PDO $pdo;

    public function boot(PDO $pdo): void
    {
        $this->pdo = $pdo;
    }

    abstract public function up(): void;

    abstract public function down(): void;

    protected function execute(string $sql): void
    {
        $this->pdo->exec($sql);
    }
}
