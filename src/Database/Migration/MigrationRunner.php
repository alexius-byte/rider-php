<?php

declare(strict_types=1);

namespace Rider\System\Database\Migration;

use PDO;

class MigrationRunner
{
    public function __construct(
        private readonly PDO    $pdo,
        private readonly string $migrationsPath,
        private readonly string $seedersPath,
    )
    {
        $this->createMigrationsTable();
    }

    public function run(): void
    {
        $pending = $this->getPendingMigrations();

        if (empty($pending)) {
            echo "Nothing to migrate.\n";
            return;
        }

        $batch = $this->getNextBatch();

        foreach ($pending as $name) {
            $this->executeMigration($name, 'up');
            $this->pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)")->execute([$name, $batch]);
            echo "Migrated:    {$name}\n";
        }
    }

    public function rollback(): void
    {
        $batch = $this->getLastBatch();

        if ($batch === 0) {
            echo "Nothing to rollback.\n";
            return;
        }

        $migrations = $this->getMigrationsByBatch($batch);

        foreach (array_reverse($migrations) as $name) {
            $this->executeMigration($name, 'down');
            $this->pdo->prepare("DELETE FROM migrations WHERE migration = ?")->execute([$name]);
            echo "Rolled back: {$name}\n";
        }
    }

    public function status(): void
    {
        $files = glob($this->migrationsPath . '/*.php') ?: [];
        $ran = $this->getRanMigrationsWithBatch();

        if (empty($files)) {
            echo "No migration files found.\n";
            return;
        }

        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (isset($ran[$name])) {
                echo "[Ran]     {$name} (batch {$ran[$name]})\n";
            } else {
                echo "[Pending] {$name}\n";
            }
        }
    }

    public function make(string $name): void
    {
        $timestamp = date('Y_m_d_His');
        $className = $this->toClassName($name);
        $filename = "{$timestamp}_{$name}.php";
        $path = $this->migrationsPath . '/' . $filename;

        file_put_contents($path, $this->migrationStub($className));
        echo "Created: {$path}\n";
    }

    public function makeSeeder(string $name): void
    {
        $path = $this->seedersPath . '/' . $name . '.php';

        file_put_contents($path, $this->seederStub($name));
        echo "Created: {$path}\n";
    }

    public function seed(?string $seeder = null): void
    {
        if ($seeder !== null) {
            $this->executeSeeder($seeder);
            return;
        }

        $files = glob($this->seedersPath . '/*.php') ?: [];

        if (empty($files)) {
            echo "No seeders found.\n";
            return;
        }

        foreach ($files as $file) {
            $this->executeSeeder(basename($file, '.php'));
        }
    }

    private function executeMigration(string $name, string $direction): void
    {
        require_once $this->migrationsPath . '/' . $name . '.php';

        $className = $this->toClassName(preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $name));
        $migration = new $className();
        $migration->boot($this->pdo);
        $migration->{$direction}();
    }

    private function executeSeeder(string $className): void
    {
        require_once $this->seedersPath . '/' . $className . '.php';

        $seeder = new $className();
        $seeder->boot($this->pdo);
        $seeder->run();
        echo "Seeded:      {$className}\n";
    }

    private function createMigrationsTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id           INT AUTO_INCREMENT PRIMARY KEY,
                migration    VARCHAR(255) NOT NULL,
                batch        INT NOT NULL,
                executed_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    private function getPendingMigrations(): array
    {
        $files = glob($this->migrationsPath . '/*.php') ?: [];
        $ran = $this->getRanMigrations();

        $pending = [];
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (!in_array($name, $ran, true)) {
                $pending[] = $name;
            }
        }

        sort($pending);
        return $pending;
    }

    private function getRanMigrations(): array
    {
        return $this->pdo->query("SELECT migration FROM migrations ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getRanMigrationsWithBatch(): array
    {
        $rows = $this->pdo->query("SELECT migration, batch FROM migrations")->fetchAll(PDO::FETCH_OBJ);
        $result = [];
        foreach ($rows as $row) {
            $result[$row->migration] = $row->batch;
        }
        return $result;
    }

    private function getMigrationsByBatch(int $batch): array
    {
        $stmt = $this->pdo->prepare("SELECT migration FROM migrations WHERE batch = ? ORDER BY id");
        $stmt->execute([$batch]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getNextBatch(): int
    {
        return (int)$this->pdo->query("SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations")->fetchColumn();
    }

    private function getLastBatch(): int
    {
        return (int)$this->pdo->query("SELECT COALESCE(MAX(batch), 0) FROM migrations")->fetchColumn();
    }

    private function toClassName(string $name): string
    {
        return str_replace('_', '', ucwords($name, '_'));
    }

    private function migrationStub(string $className): string
    {
        $template = <<<'PHP'
    <?php

    declare(strict_types=1);

    use Rider\System\Database\Migration\AbstractMigration;

    class %s extends AbstractMigration
    {
        public function up(): void
        {
            $this->execute("");
        }

        public function down(): void
        {
            $this->execute("");
        }
    }
    PHP;

        return sprintf(ltrim($template), $className);
    }

    private function seederStub(string $className): string
    {
        $template = <<<'PHP'
    <?php

    declare(strict_types=1);

    use Rider\System\Database\Seeder\AbstractSeeder;

    class %s extends AbstractSeeder
    {
        public function run(): void
        {
            $this->insert('table', [
                //
            ]);
        }
    }
    PHP;

        return sprintf(ltrim($template), $className);
    }
}
