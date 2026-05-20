<?php

declare(strict_types=1);

namespace Rider\System\Database;

use PDO;
use PDOException;
use RuntimeException;

class Connection
{
    private static PDO|null $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if (($_ENV['PDO_PERSIST'] ?? false) === true) {
            $options[PDO::ATTR_PERSISTENT] = true;
        }

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',
            $_ENV['MYSQL_HOST'] ?? '127.0.0.1',
            $_ENV['MYSQL_DATABASE'] ?? ''
        );

        try {
            self::$instance = new PDO($dsn,
                (string)($_ENV['MYSQL_USERNAME'] ?? ''),
                (string)($_ENV['MYSQL_PASSWORD'] ?? ''),
                $options
            );
        } catch (PDOException) {
            throw new RuntimeException('Database connection failed');
        }

        return self::$instance;
    }
}