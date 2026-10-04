<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PDO;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->ensureTestingDatabaseExists();

        parent::setUp();
    }

    protected function ensureTestingDatabaseExists(): void
    {
        static $databaseReady = false;
        $testingEnv = $this->readTestingEnvFile();

        $connection = getenv('DB_CONNECTION') ?: ($testingEnv['DB_CONNECTION'] ?? null);

        if ($databaseReady || $connection !== 'mysql') {
            return;
        }

        $host = getenv('DB_HOST') ?: ($testingEnv['DB_HOST'] ?? '127.0.0.1');
        $port = getenv('DB_PORT') ?: ($testingEnv['DB_PORT'] ?? '3306');
        $database = getenv('DB_DATABASE') ?: ($testingEnv['DB_DATABASE'] ?? 'sarunis_testing');
        $username = getenv('DB_USERNAME') ?: ($testingEnv['DB_USERNAME'] ?? 'root');
        $password = getenv('DB_PASSWORD') ?: ($testingEnv['DB_PASSWORD'] ?? '');

        $pdo = new PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $pdo->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            str_replace('`', '``', $database),
        ));

        $databaseReady = true;
    }

    /**
     * @return array<string, string>
     */
    protected function readTestingEnvFile(): array
    {
        $path = dirname(__DIR__).'/.env.testing';

        if (! is_file($path)) {
            return [];
        }

        $values = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if ($line === false || str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim($value, " \t\n\r\0\x0B\"");
        }

        return $values;
    }
}
