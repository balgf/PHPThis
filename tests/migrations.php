<?php

declare(strict_types=1);

use Example\ApplicationComposition;
use Example\ApplicationDatabasePath;
use Example\Migrations\ApplicationMigrationFailed;
use Example\Migrations\ApplicationMigrationFailureReason;
use Example\Migrations\MigrationHistory;
use Example\Migrations\SqliteApplicationMigrations;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

/** @return array<string, Closure(): void> */
function migrationTests(): array
{
    return [
        'database migrate applies an ordered inspectable ledger and reruns as a no-op' => static function (): void {
            $databasePath = freshMigrationDatabasePath('ordered-ledger');

            if (is_file($databasePath)) {
                throw new RuntimeException('The empty migration proof must begin without a database file.');
            }

            $first = runApplicationConsole([
                'database:migrate',
                '--database=' . $databasePath,
            ]);
            $second = runApplicationConsole([
                'database:migrate',
                '--database=' . $databasePath,
            ]);
            $connection = Connection::connect(
                'sqlite:' . $databasePath,
                new QueryBudget(2),
                new QueryTrace(2),
            );
            $history = $connection->selectAllRows(
                <<<'SQL'
                    SELECT
                        application_migrations.position,
                        application_migrations.migration_id,
                        application_migrations.checksum_sha256,
                        application_migrations.applied_at_epoch
                    FROM application_migrations
                    ORDER BY application_migrations.position ASC
                    LIMIT 8
                    SQL,
            );
            $tableCount = $connection->selectOneRow(
                <<<'SQL'
                    SELECT COUNT(*) AS table_count
                    FROM sqlite_master
                    WHERE sqlite_master.type = :object_type
                      AND sqlite_master.name IN (
                          :ledger_table,
                          :users_table,
                          :events_table,
                          :jobs_table,
                          :deliveries_table,
                          :documents_table,
                          :memberships_table,
                          :account_users_table
                      )
                    SQL,
                [
                    'object_type' => 'table',
                    'ledger_table' => 'application_migrations',
                    'users_table' => 'users',
                    'events_table' => 'user_events',
                    'jobs_table' => 'application_jobs',
                    'deliveries_table' => 'welcome_deliveries',
                    'documents_table' => 'documents',
                    'memberships_table' => 'account_memberships',
                    'account_users_table' => 'account_users',
                ],
            );
            $manifest = SqliteApplicationMigrations::manifest();

            if (
                $first !== successfulConsoleResult('database:migrate', 'applied')
                || $second !== successfulConsoleResult('database:migrate', 'up_to_date')
                || count($history) !== count($manifest)
                || $tableCount !== ['table_count' => 8]
            ) {
                throw new RuntimeException('The explicit migration command must apply once and then be a no-op.');
            }

            foreach ($history as $index => $row) {
                $expected = $manifest[$index] ?? null;

                if (
                    !is_array($expected)
                    || array_keys($row) !== [
                        'position',
                        'migration_id',
                        'checksum_sha256',
                        'applied_at_epoch',
                    ]
                    || $row['position'] !== $expected['position']
                    || $row['migration_id'] !== $expected['identifier']
                    || $row['checksum_sha256'] !== $expected['checksum']
                    || !is_int($row['applied_at_epoch'])
                    || $row['applied_at_epoch'] < 0
                ) {
                    throw new RuntimeException('The migration ledger must expose the exact manifest prefix.');
                }
            }

            $permissions = fileperms($databasePath . '.migration.lock');

            if (!is_int($permissions) || ($permissions & 0777) !== 0600) {
                throw new RuntimeException('The application-private migration lock must use mode 0600.');
            }
        },

        'database migrate adds account users without conflating principal identities' => static function (): void {
            $databasePath = freshMigrationDatabasePath('account-users-forward-step');
            $initial = runApplicationConsole([
                'database:migrate',
                '--database=' . $databasePath,
            ]);
            $connection = Connection::connect(
                'sqlite:' . $databasePath,
                new QueryBudget(4),
                new QueryTrace(4),
            );
            $connection->executeStatement('DROP TABLE account_users');
            $connection->executeStatement(
                'DELETE FROM application_migrations WHERE position = :position',
                ['position' => 7],
            );
            $connection->executeStatement(
                'INSERT INTO users (id, name, email) VALUES (:id, :name, :email)',
                ['id' => 7, 'name' => 'Identity Boundary', 'email' => 'identity@example.com'],
            );
            $connection->executeStatement(
                <<<'SQL'
                    INSERT INTO account_memberships (principal_id, account_id)
                    VALUES (:principal_id, :account_id)
                    SQL,
                ['principal_id' => 7, 'account_id' => 42],
            );
            $upgraded = runApplicationConsole([
                'database:migrate',
                '--database=' . $databasePath,
            ]);
            $state = Connection::connect(
                'sqlite:' . $databasePath,
                new QueryBudget(1),
                new QueryTrace(1),
            )->selectOneRow(
                <<<'SQL'
                    SELECT
                        (SELECT COUNT(*) FROM application_migrations) AS migration_count,
                        (SELECT COUNT(*) FROM account_memberships) AS principal_membership_count,
                        (SELECT COUNT(*) FROM account_users) AS account_user_count
                    SQL,
            );

            if (
                $initial !== successfulConsoleResult('database:migrate', 'applied')
                || $upgraded !== successfulConsoleResult('database:migrate', 'applied')
                || $state !== [
                    'migration_count' => 7,
                    'principal_membership_count' => 1,
                    'account_user_count' => 0,
                ]
            ) {
                throw new RuntimeException(
                    'The forward migration must not infer user ownership from principal ids.',
                );
            }
        },

        'database migrate rejects checksum drift before pending migration work' => static function (): void {
            $databasePath = freshMigrationDatabasePath('checksum-drift');
            $initial = runApplicationConsole([
                'database:migrate',
                '--database=' . $databasePath,
            ]);
            $manifest = SqliteApplicationMigrations::manifest();
            $third = $manifest[2];
            $driftChecksum = $third['checksum'] === str_repeat('0', 64)
                ? str_repeat('1', 64)
                : str_repeat('0', 64);
            $connection = Connection::connect(
                'sqlite:' . $databasePath,
                new QueryBudget(4),
                new QueryTrace(4),
            );
            $connection->executeStatement(
                'DELETE FROM application_migrations WHERE position >= :first_pending_position',
                ['first_pending_position' => 4],
            );
            $connection->executeStatement(
                <<<'SQL'
                    UPDATE application_migrations
                    SET checksum_sha256 = :checksum_sha256
                    WHERE position = :position
                    SQL,
                ['checksum_sha256' => $driftChecksum, 'position' => 3],
            );
            $result = runApplicationConsole([
                'database:migrate',
                '--database=' . $databasePath,
            ]);
            $ledgerCount = $connection->selectOneRow(
                'SELECT COUNT(*) AS migration_count FROM application_migrations',
            );
            $indexCount = $connection->selectOneRow(
                <<<'SQL'
                    SELECT COUNT(*) AS index_count
                    FROM sqlite_master
                    WHERE sqlite_master.type = :object_type
                      AND sqlite_master.name = :object_name
                    SQL,
                [
                    'object_type' => 'index',
                    'object_name' => 'documents_account_rank_key_idx',
                ],
            );
            $expectedError = migrationFailureResult(
                'checksum_drift',
                $third['identifier'],
            );

            if (
                $initial !== successfulConsoleResult('database:migrate', 'applied')
                || $result !== $expectedError
                || $ledgerCount !== ['migration_count' => 3]
                || $indexCount !== ['index_count' => 1]
                || str_contains($result['stderr'], $databasePath)
            ) {
                throw new RuntimeException('Checksum drift must stop before every pending manifest step.');
            }
        },

        'database migrate rejects non-prefix ledger history' => static function (): void {
            $databasePath = freshMigrationDatabasePath('history-invalid');
            $initial = runApplicationConsole([
                'database:migrate',
                '--database=' . $databasePath,
            ]);
            $connection = Connection::connect(
                'sqlite:' . $databasePath,
                new QueryBudget(2),
                new QueryTrace(2),
            );
            $connection->executeStatement(
                'DELETE FROM application_migrations WHERE position = :position',
                ['position' => 1],
            );
            $result = runApplicationConsole([
                'database:migrate',
                '--database=' . $databasePath,
            ]);
            $ledgerCount = $connection->selectOneRow(
                'SELECT COUNT(*) AS migration_count FROM application_migrations',
            );

            if (
                $initial !== successfulConsoleResult('database:migrate', 'applied')
                || $result !== migrationFailureResult('history_invalid', null)
                || $ledgerCount !== ['migration_count' => 6]
                || str_contains($result['stderr'], $databasePath)
            ) {
                throw new RuntimeException('A gapped migration history must fail without repair or inference.');
            }
        },

        'database migrate rejects an incompatible preexisting ledger schema' => static function (): void {
            $databasePath = freshMigrationDatabasePath('ledger-schema-invalid');
            $connection = Connection::connect(
                'sqlite:' . $databasePath,
                new QueryBudget(3),
                new QueryTrace(3),
            );
            $connection->executeStatement(
                <<<'SQL'
                    CREATE TABLE application_migrations (
                        position INTEGER,
                        migration_id TEXT,
                        checksum_sha256 TEXT,
                        applied_at_epoch INTEGER
                    )
                    SQL,
            );
            $connection->executeStatement(
                <<<'SQL'
                    INSERT INTO application_migrations (
                        position,
                        migration_id,
                        checksum_sha256,
                        applied_at_epoch
                    ) VALUES (
                        :position,
                        :migration_id,
                        :checksum_sha256,
                        :applied_at_epoch
                    )
                    SQL,
                [
                    'position' => 1,
                    'migration_id' => '0001_create_user_schema',
                    'checksum_sha256' => str_repeat('0', 64),
                    'applied_at_epoch' => 0,
                ],
            );
            $result = runApplicationConsole([
                'database:migrate',
                '--database=' . $databasePath,
            ]);
            $userTable = $connection->selectOneRow(
                <<<'SQL'
                    SELECT COUNT(*) AS table_count
                    FROM sqlite_master
                    WHERE sqlite_master.type = :object_type
                      AND sqlite_master.name = :table_name
                    SQL,
                ['object_type' => 'table', 'table_name' => 'users'],
            );

            if (
                $result !== migrationFailureResult('history_invalid', null)
                || $userTable !== ['table_count' => 0]
                || str_contains($result['stderr'], $databasePath)
            ) {
                throw new RuntimeException('An incompatible ledger schema must fail before migration work.');
            }
        },

        'database migrate reports exact redacted ledger and lock failures' => static function (): void {
            $missingParentPath = freshMigrationDatabasePath('ledger-unavailable')
                . '.missing-' . bin2hex(random_bytes(8))
                . '/application.sqlite';
            $ledgerFailure = runApplicationConsole([
                'database:migrate',
                '--database=' . $missingParentPath,
            ]);
            $databasePath = freshMigrationDatabasePath('lock-failed');
            $lockPath = $databasePath . '.migration.lock';

            if (!mkdir($lockPath)) {
                throw new RuntimeException('Unable to create an unavailable migration lock path.');
            }

            try {
                $lockFailure = runApplicationConsole([
                    'database:migrate',
                    '--database=' . $databasePath,
                ]);
            } finally {
                if (!rmdir($lockPath)) {
                    throw new RuntimeException('Unable to remove the unavailable migration lock path.');
                }
            }

            if (
                $ledgerFailure !== migrationFailureResult('ledger_unavailable', null)
                || $lockFailure !== migrationFailureResult('lock_failed', null)
                || str_contains($ledgerFailure['stderr'], $missingParentPath)
                || str_contains($lockFailure['stderr'], $databasePath)
                || is_file($databasePath)
            ) {
                throw new RuntimeException('Migration infrastructure failures must be finite and redacted.');
            }
        },

        'migration history rejects malformed and oversized database snapshots' => static function (): void {
            $manifest = SqliteApplicationMigrations::manifest();
            $malformedRejected = false;
            $unknownRejected = false;
            $oversizedRejected = false;

            try {
                MigrationHistory::fromDatabaseRows(
                    [[
                        'position' => '1',
                        'migration_id' => $manifest[0]['identifier'],
                        'checksum_sha256' => $manifest[0]['checksum'],
                    ]],
                    $manifest,
                );
            } catch (ApplicationMigrationFailed $exception) {
                $malformedRejected = $exception->reason
                    === ApplicationMigrationFailureReason::HistoryInvalid;
            }

            try {
                MigrationHistory::fromDatabaseRows(
                    [[
                        'position' => 1,
                        'migration_id' => 'unknown_migration',
                        'checksum_sha256' => $manifest[0]['checksum'],
                    ]],
                    $manifest,
                );
            } catch (ApplicationMigrationFailed $exception) {
                $unknownRejected = $exception->reason
                    === ApplicationMigrationFailureReason::HistoryInvalid;
            }

            try {
                MigrationHistory::fromDatabaseRows(
                    array_fill(
                        0,
                        513,
                        [
                            'position' => 1,
                            'migration_id' => $manifest[0]['identifier'],
                            'checksum_sha256' => $manifest[0]['checksum'],
                        ],
                    ),
                    array_fill(0, 513, $manifest[0]),
                );
            } catch (ApplicationMigrationFailed $exception) {
                $oversizedRejected = $exception->reason
                    === ApplicationMigrationFailureReason::HistoryInvalid;
            }

            if (!$malformedRejected || !$unknownRejected || !$oversizedRejected) {
                throw new RuntimeException('Migration history must remain typed and bounded to 512 rows.');
            }
        },

        'database migrate preserves earlier commits across a later migration failure' => static function (): void {
            $databasePath = freshMigrationDatabasePath('partial-failure');
            $blocker = Connection::connect(
                'sqlite:' . $databasePath,
                new QueryBudget(1),
                new QueryTrace(1),
            );
            $blocker->executeStatement(
                'CREATE TABLE welcome_deliveries (conflict INTEGER NOT NULL)',
            );
            $failed = runApplicationConsole([
                'database:migrate',
                '--database=' . $databasePath,
            ]);
            $verification = Connection::connect(
                'sqlite:' . $databasePath,
                new QueryBudget(4),
                new QueryTrace(4),
            );
            $afterFailureLedger = $verification->selectAllRows(
                <<<'SQL'
                    SELECT
                        application_migrations.position,
                        application_migrations.migration_id
                    FROM application_migrations
                    ORDER BY application_migrations.position ASC
                    LIMIT 8
                    SQL,
            );
            $userTables = $verification->selectOneRow(
                <<<'SQL'
                    SELECT COUNT(*) AS table_count
                    FROM sqlite_master
                    WHERE sqlite_master.type = :object_type
                      AND sqlite_master.name IN (:users_table, :events_table)
                    SQL,
                [
                    'object_type' => 'table',
                    'users_table' => 'users',
                    'events_table' => 'user_events',
                ],
            );
            $jobSchemaObjects = $verification->selectOneRow(
                <<<'SQL'
                    SELECT
                        SUM(
                            CASE WHEN sqlite_master.name = :jobs_table THEN 1 ELSE 0 END
                        ) AS jobs_table_count,
                        SUM(
                            CASE
                                WHEN sqlite_master.name IN (
                                    :available_index,
                                    :lease_index
                                ) THEN 1
                                ELSE 0
                            END
                        ) AS jobs_index_count,
                        SUM(
                            CASE WHEN sqlite_master.name = :deliveries_table THEN 1 ELSE 0 END
                        ) AS deliveries_table_count
                    FROM sqlite_master
                    SQL,
                [
                    'jobs_table' => 'application_jobs',
                    'available_index' => 'application_jobs_available_due_idx',
                    'lease_index' => 'application_jobs_expired_lease_idx',
                    'deliveries_table' => 'welcome_deliveries',
                ],
            );
            $verification->executeStatement('DROP TABLE welcome_deliveries');
            $recovered = runApplicationConsole([
                'database:migrate',
                '--database=' . $databasePath,
            ]);
            $final = runApplicationConsole([
                'database:migrate',
                '--database=' . $databasePath,
            ]);

            if (
                $failed !== migrationFailureResult('apply_failed', '0002_create_job_schema')
                || $afterFailureLedger !== [[
                    'position' => 1,
                    'migration_id' => '0001_create_user_schema',
                ]]
                || $userTables !== ['table_count' => 2]
                || $jobSchemaObjects !== [
                    'jobs_table_count' => 0,
                    'jobs_index_count' => 0,
                    'deliveries_table_count' => 1,
                ]
                || $recovered !== successfulConsoleResult('database:migrate', 'applied')
                || $final !== successfulConsoleResult('database:migrate', 'up_to_date')
            ) {
                throw new RuntimeException('A failed migration must roll back its earlier DDL and permit repair.');
            }
        },

        'database migrate refuses to infer a baseline for an unledgered existing schema' => static function (): void {
            $databasePath = freshMigrationDatabasePath('unledgered-schema');
            $existing = Connection::connect(
                'sqlite:' . $databasePath,
                new QueryBudget(3),
                new QueryTrace(3),
            );
            $existing->executeStatement(
                <<<'SQL'
                    CREATE TABLE users (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        name TEXT NOT NULL,
                        email TEXT NOT NULL UNIQUE
                    )
                    SQL,
            );
            $existing->executeStatement(
                <<<'SQL'
                    INSERT INTO users (name, email)
                    VALUES (:name, :email)
                    SQL,
                ['name' => 'Preserved User', 'email' => 'preserved@example.com'],
            );
            $result = runApplicationConsole([
                'database:migrate',
                '--database=' . $databasePath,
            ]);
            $state = $existing->selectOneRow(
                <<<'SQL'
                    SELECT
                        (SELECT COUNT(*) FROM application_migrations) AS migration_count,
                        (SELECT COUNT(*) FROM users) AS user_count
                    SQL,
            );

            if (
                $result !== migrationFailureResult('apply_failed', '0001_create_user_schema')
                || $state !== ['migration_count' => 0, 'user_count' => 1]
            ) {
                throw new RuntimeException('An unledgered schema must not be inferred as applied history.');
            }
        },

        'database migrate fails fast under a subprocess-held migration lock' => static function (): void {
            $databasePath = freshMigrationDatabasePath('lock-contention');
            $contended = runMigrationWithSubprocessLock($databasePath);
            $afterRelease = runApplicationConsole([
                'database:migrate',
                '--database=' . $databasePath,
            ]);

            if (
                $contended['result'] !== migrationFailureResult('busy', null)
                || $contended['duration_us'] > 1_000_000
                || $contended['database_exists']
                || $afterRelease !== successfulConsoleResult('database:migrate', 'applied')
            ) {
                throw new RuntimeException('A concurrent local runner must fail quickly before database work.');
            }
        },

        'HTTP composition never creates or applies a missing database schema' => static function (): void {
            $databasePath = freshMigrationDatabasePath('http-does-not-migrate');
            $composition = new ApplicationComposition(
                ApplicationDatabasePath::fromString($databasePath),
            );
            $failed = false;

            try {
                $composition->http();
            } catch (RuntimeException) {
                $failed = true;
            }

            if (
                !$failed
                || is_file($databasePath)
                || is_file($databasePath . '.migration.lock')
            ) {
                throw new RuntimeException('HTTP startup must not create the database or migration ledger.');
            }
        },
    ];
}

function freshMigrationDatabasePath(string $name): string
{
    $directory = dirname(__DIR__) . '/tmp/application-tests/migrations';

    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the migration test directory.');
    }

    $resolvedDirectory = realpath($directory);

    if (!is_string($resolvedDirectory)) {
        throw new RuntimeException('Unable to resolve the migration test directory.');
    }

    $databasePath = $resolvedDirectory . '/' . $name . '.sqlite';

    foreach (
        [
            $databasePath,
            $databasePath . '-shm',
            $databasePath . '-wal',
            $databasePath . '.migration.lock',
            $databasePath . '.schedule.lock',
        ] as $path
    ) {
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Unable to reset a migration test artifact.');
        }
    }

    return $databasePath;
}

/** @return array{exit_code: int, stdout: string, stderr: string} */
function migrationFailureResult(string $reason, ?string $migration): array
{
    return [
        'exit_code' => 1,
        'stdout' => '',
        'stderr' => json_encode(
            [
                'error' => 'migration_failed',
                'reason' => $reason,
                'migration' => $migration,
            ],
            JSON_THROW_ON_ERROR,
        ) . "\n",
    ];
}

/** @return array{result: array{exit_code: int, stdout: string, stderr: string}, duration_us: int, database_exists: bool} */
function runMigrationWithSubprocessLock(string $databasePath): array
{
    $process = proc_open(
        [PHP_BINARY, __DIR__ . '/cli-migration-lock-holder.php', $databasePath],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        dirname(__DIR__),
        null,
        ['bypass_shell' => true],
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start the migration lock-holder process.');
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $deadline = hrtime(true) + 5_000_000_000;

    while (!str_contains($stdout, "READY\n") && hrtime(true) < $deadline) {
        $stdoutChunk = stream_get_contents($pipes[1]);
        $stderrChunk = stream_get_contents($pipes[2]);

        if (is_string($stdoutChunk)) {
            $stdout .= $stdoutChunk;
        }

        if (is_string($stderrChunk)) {
            $stderr .= $stderrChunk;
        }

        if (!str_contains($stdout, "READY\n")) {
            usleep(10_000);
        }
    }

    if (!str_contains($stdout, "READY\n")) {
        proc_terminate($process);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        throw new RuntimeException('Migration lock holder did not become ready: ' . $stderr);
    }

    try {
        $startedAt = hrtime(true);
        $result = runApplicationConsole([
            'database:migrate',
            '--database=' . $databasePath,
        ]);
        $durationUs = max(0, intdiv(hrtime(true) - $startedAt, 1_000));
        $databaseExists = is_file($databasePath);
    } finally {
        proc_terminate($process);
        $remainingStdout = stream_get_contents($pipes[1]);
        $remainingStderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if (is_string($remainingStdout)) {
            $stdout .= $remainingStdout;
        }

        if (is_string($remainingStderr)) {
            $stderr .= $remainingStderr;
        }
    }

    if ($stdout !== "READY\n" || $stderr !== '') {
        throw new RuntimeException('Migration lock holder emitted unexpected output.');
    }

    return [
        'result' => $result,
        'duration_us' => $durationUs,
        'database_exists' => $databaseExists,
    ];
}
