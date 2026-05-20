<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Rider\System\Database\Connection;
use Rider\System\Database\Migration\MigrationRunner;
use Rider\System\Env\Dotenv;

Dotenv::load(__DIR__ . '/.env');

$runner = new MigrationRunner(
    Connection::getInstance(),
    __DIR__ . '/database/migrations',
    __DIR__ . '/database/seeders',
);

$command = $argv[1] ?? 'run';
$argument = $argv[2] ?? null;

try {
    match (true) {
        $command === 'rollback' => $runner->rollback(),
        $command === 'status' => $runner->status(),
        $command === 'seed' => $runner->seed($argument),
        $command === 'make:seeder' => $runner->makeSeeder($argument ?? ''),
        $command === 'make' => $runner->make($argument ?? ''),
        default => $runner->run(),
    };
} catch (Throwable $e) {
    echo "Error: {$e->getMessage()}\n";
    exit(1);
}
