<?php

declare(strict_types=1);

use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryBudgetExceeded;
use PHPThis\Database\QueryTrace;

require dirname(__DIR__) . '/autoload.php';

$drivers = configuredDatabaseDrivers();
$availableDrivers = PDO::getAvailableDrivers();

foreach ($drivers as $driver) {
    if (!in_array($driver, $availableDrivers, true)) {
        throw new RuntimeException("Configured PDO driver is unavailable: {$driver}.");
    }

    certifyDatabaseDriver($driver, databaseDriverConfiguration($driver));
}

fwrite(STDOUT, 'PASS database transport: ' . implode(', ', $drivers) . "\n");

/** @return non-empty-list<'sqlite'|'mysql'|'pgsql'> */
function configuredDatabaseDrivers(): array
{
    $configured = getenv('PHPTHIS_DATABASE_TEST_DRIVERS');

    if (!is_string($configured) || trim($configured) === '') {
        return ['sqlite'];
    }

    /** @var array<'sqlite'|'mysql'|'pgsql', true> $drivers */
    $drivers = [];

    foreach (explode(',', $configured) as $candidate) {
        $driver = trim($candidate);

        if (!in_array($driver, ['sqlite', 'mysql', 'pgsql'], true)) {
            throw new RuntimeException("Unsupported database transport test driver: {$driver}.");
        }

        $drivers[$driver] = true;
    }

    return array_keys($drivers);
}

/** @return array{dsn: non-empty-string, username: ?string, password: ?string, cleanup_file: ?string} */
function databaseDriverConfiguration(string $driver): array
{
    return match ($driver) {
        'sqlite' => sqliteDatabaseDriverConfiguration(),
        'mysql' => [
            'dsn' => requiredDatabaseEnvironment('PHPTHIS_MYSQL_DSN'),
            'username' => optionalDatabaseEnvironment('PHPTHIS_MYSQL_USERNAME'),
            'password' => optionalDatabaseEnvironment('PHPTHIS_MYSQL_PASSWORD'),
            'cleanup_file' => null,
        ],
        'pgsql' => [
            'dsn' => requiredDatabaseEnvironment('PHPTHIS_PGSQL_DSN'),
            'username' => optionalDatabaseEnvironment('PHPTHIS_PGSQL_USERNAME'),
            'password' => optionalDatabaseEnvironment('PHPTHIS_PGSQL_PASSWORD'),
            'cleanup_file' => null,
        ],
        default => throw new RuntimeException("Unsupported database driver configuration: {$driver}."),
    };
}

/**
 * @param 'sqlite'|'mysql'|'pgsql' $driver
 * @param array{dsn: non-empty-string, username: ?string, password: ?string, cleanup_file: ?string} $configuration
 */
function certifyDatabaseDriver(string $driver, array $configuration): void
{
    if (!str_starts_with($configuration['dsn'], $driver . ':')) {
        throw new RuntimeException("Configured {$driver} DSN does not select the {$driver} PDO driver.");
    }

    $budget = new QueryBudget(9);
    $trace = new QueryTrace(7);
    $connection = Connection::connect(
        $configuration['dsn'],
        $budget,
        $trace,
        $configuration['username'],
        $configuration['password'],
    );
    $table = databaseCertificationTableName();
    $missingTable = $table . '_missing';
    $tableCreated = false;
    $insertSql = <<<SQL
        INSERT INTO {$table} (id, label, enabled, note)
        VALUES (:id, :label, :enabled, :note)
        SQL;

    try {
        $connection->executeStatement(
            <<<SQL
                CREATE TABLE {$table} (
                    id INTEGER NOT NULL PRIMARY KEY,
                    label VARCHAR(64) NOT NULL,
                    enabled BOOLEAN NOT NULL,
                    note VARCHAR(64) NULL
                )
                SQL,
        );
        $tableCreated = true;

        $connection->beginTransaction();

        try {
            requireDatabaseCertification(
                $connection->executeStatement(
                    $insertSql,
                    ['id' => 1, 'label' => 'committed', 'enabled' => true, 'note' => null],
                ) === 1,
                "{$driver} insert must affect one row.",
            );
            $connection->commit();
        } finally {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
        }

        $row = $connection->selectOneRow(
            "SELECT id, label FROM {$table} WHERE id = :id AND enabled = :enabled AND note IS NULL",
            ['id' => 1, 'enabled' => true],
        );
        $identifier = $row['id'] ?? null;
        $label = $row['label'] ?? null;

        requireDatabaseCertification(
            ($identifier === 1 || $identifier === '1') && $label === 'committed',
            "{$driver} named bindings or associative fetch behavior changed.",
        );

        $connection->beginTransaction();

        try {
            requireDatabaseCertification(
                $connection->executeStatement(
                    $insertSql,
                    ['id' => 2, 'label' => 'rolled-back', 'enabled' => false, 'note' => 'temporary'],
                ) === 1,
                "{$driver} transactional insert must affect one row.",
            );
        } finally {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
        }

        requireDatabaseCertification(
            $connection->selectOneRow(
                "SELECT id FROM {$table} WHERE id = :id",
                ['id' => 2],
            ) === null,
            "{$driver} rollback must remove the uncommitted row.",
        );

        $observerTrace = new QueryTrace(2);
        $observer = Connection::connect(
            $configuration['dsn'],
            new QueryBudget(2),
            $observerTrace,
            $configuration['username'],
            $configuration['password'],
        );
        requireDatabaseCertification(
            $observer->selectOneRow(
                "SELECT id FROM {$table} WHERE id = :committed_id",
                ['committed_id' => 1],
            ) !== null
            && $observer->selectOneRow(
                "SELECT id FROM {$table} WHERE id = :rolled_back_id",
                ['rolled_back_id' => 2],
            ) === null,
            "{$driver} second connection did not observe committed and rolled-back work correctly.",
        );
        requireDatabaseCertification(
            $observerTrace->snapshot()['statements'] === 2,
            "{$driver} second connection trace must remain independent.",
        );

        requireDatabaseCertification(
            $connection->executeStatement(
                $insertSql,
                ['id' => 3, 'label' => 'second-row', 'enabled' => true, 'note' => null],
            ) === 1,
            "{$driver} second committed insert must affect one row.",
        );
        $rows = $connection->selectAllRows("SELECT id, label FROM {$table} ORDER BY id");
        requireDatabaseCertification(
            count($rows) === 2
            && databaseIdentifierMatches($rows[0]['id'] ?? null, 1)
            && ($rows[0]['label'] ?? null) === 'committed'
            && databaseIdentifierMatches($rows[1]['id'] ?? null, 3)
            && ($rows[1]['label'] ?? null) === 'second-row',
            "{$driver} ordered associative collection fetch changed.",
        );
        requireDatabaseCertification(
            $connection->executeStatement(
                "DELETE FROM {$table} WHERE id = :id",
                ['id' => 3],
            ) === 1,
            "{$driver} single-row delete must affect one row.",
        );

        $databaseFailed = false;

        try {
            $connection->selectOneRow("SELECT id FROM {$missingTable}");
        } catch (PDOException) {
            $databaseFailed = true;
        }

        requireDatabaseCertification($databaseFailed, "{$driver} database failure was not rethrown.");

        $budgetRejected = false;

        try {
            $connection->selectOneRow(
                "SELECT id FROM {$table} WHERE id = :id AND enabled = :enabled",
                ['id' => 1, 'enabled' => true],
            );
        } catch (QueryBudgetExceeded) {
            $budgetRejected = true;
        }

        $snapshot = $trace->snapshot();
        requireDatabaseCertification($budgetRejected, "{$driver} query budget did not reject statement ten.");
        requireDatabaseCertification($budget->used() === 9, "{$driver} query budget count changed.");
        requireDatabaseCertification($snapshot['statements'] === 9, "{$driver} query trace count changed.");
        requireDatabaseCertification($snapshot['failures'] === 1, "{$driver} query trace failure count changed.");
        requireDatabaseCertification(
            $snapshot['tracked_fingerprints'] === 7
            && $snapshot['repeated_fingerprints'] === 1
            && $snapshot['maximum_executions_per_fingerprint'] === 3
            && !$snapshot['truncated'],
            "{$driver} query trace fingerprint shape changed.",
        );
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        try {
            $cleanup = Connection::connect(
                $configuration['dsn'],
                new QueryBudget(1),
                new QueryTrace(1),
                $configuration['username'],
                $configuration['password'],
            );

            if ($tableCreated) {
                $cleanup->executeStatement("DROP TABLE {$table}");
            }

            unset($cleanup, $observer, $connection);
        } finally {
            $cleanupFile = $configuration['cleanup_file'];

            if (is_string($cleanupFile) && is_file($cleanupFile) && !unlink($cleanupFile)) {
                throw new RuntimeException("Unable to remove {$driver} transport fixture.");
            }
        }
    }
}

/** @return non-empty-string */
function databaseCertificationTableName(): string
{
    return 'phpthis_transport_' . bin2hex(random_bytes(8));
}

/** @return array{dsn: non-empty-string, username: null, password: null, cleanup_file: non-empty-string} */
function sqliteDatabaseDriverConfiguration(): array
{
    $path = tempnam(sys_get_temp_dir(), 'phpthis-pdo-');

    if (!is_string($path)) {
        throw new RuntimeException('Unable to create the SQLite transport fixture.');
    }

    return [
        'dsn' => 'sqlite:' . $path,
        'username' => null,
        'password' => null,
        'cleanup_file' => $path,
    ];
}

function databaseIdentifierMatches(mixed $value, int $expected): bool
{
    return $value === $expected || $value === (string) $expected;
}

/** @return non-empty-string */
function requiredDatabaseEnvironment(string $name): string
{
    $value = getenv($name);

    if (!is_string($value) || $value === '') {
        throw new RuntimeException("Required database transport environment variable is missing: {$name}.");
    }

    return $value;
}

function optionalDatabaseEnvironment(string $name): ?string
{
    $value = getenv($name);

    return is_string($value) && $value !== '' ? $value : null;
}

function requireDatabaseCertification(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
