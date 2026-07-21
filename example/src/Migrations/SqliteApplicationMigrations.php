<?php

declare(strict_types=1);

namespace Example\Migrations;

use PDO;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use Throwable;

final readonly class SqliteApplicationMigrations
{
    private const int QUERY_LIMIT = 21;

    private const string USER_SCHEMA_IDENTIFIER = '0001_create_user_schema';
    private const string JOB_SCHEMA_IDENTIFIER = '0002_create_job_schema';
    private const string PREPARE_DOCUMENT_IDENTIFIER = '0003_prepare_document_schema';
    private const string DOCUMENT_CATEGORY_IDENTIFIER = '0004_add_document_category';
    private const string DOCUMENT_SORT_RANK_IDENTIFIER = '0005_add_document_sort_rank';
    private const string DOCUMENT_ACCESS_IDENTIFIER = '0006_create_document_access_schema';

    private const string CREATE_USERS_SQL = <<<'SQL'
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE
        )
        SQL;

    private const string CREATE_USER_EVENTS_SQL = <<<'SQL'
        CREATE TABLE user_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            event_type TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users (id)
        )
        SQL;

    private const string CREATE_USER_EVENTS_INDEX_SQL =
        'CREATE INDEX user_events_user_id_idx ON user_events (user_id)';

    private const string CREATE_APPLICATION_JOBS_SQL = <<<'SQL'
        CREATE TABLE application_jobs (
            job_id TEXT PRIMARY KEY
                CHECK (
                    length(job_id) = 32
                    AND job_id NOT GLOB '*[^0-9a-f]*'
                ),
            envelope_json TEXT NOT NULL
                CHECK (length(CAST(envelope_json AS BLOB)) BETWEEN 2 AND 2048),
            status TEXT NOT NULL
                CHECK (status IN ('available', 'leased', 'succeeded', 'dead')),
            available_at INTEGER NOT NULL CHECK (available_at >= 0),
            attempts_started INTEGER NOT NULL DEFAULT 0
                CHECK (attempts_started >= 0),
            max_attempts INTEGER NOT NULL CHECK (max_attempts = 3),
            lease_token TEXT
                CHECK (
                    lease_token IS NULL
                    OR (
                        length(lease_token) = 32
                        AND lease_token NOT GLOB '*[^0-9a-f]*'
                    )
                ),
            lease_expires_at INTEGER
                CHECK (lease_expires_at IS NULL OR lease_expires_at >= 0),
            last_failure_code TEXT
                CHECK (
                    last_failure_code IS NULL
                    OR last_failure_code IN (
                        'handler_failure',
                        'invalid_envelope',
                        'lease_expired',
                        'lease_expired_after_final_attempt'
                    )
                ),
            created_at INTEGER NOT NULL CHECK (created_at >= 0),
            updated_at INTEGER NOT NULL CHECK (updated_at >= 0),
            completed_at INTEGER CHECK (completed_at IS NULL OR completed_at >= 0),
            dead_at INTEGER CHECK (dead_at IS NULL OR dead_at >= 0),
            CHECK (attempts_started <= max_attempts),
            CHECK (
                (
                    status = 'available'
                    AND attempts_started < max_attempts
                    AND lease_token IS NULL
                    AND lease_expires_at IS NULL
                    AND completed_at IS NULL
                    AND dead_at IS NULL
                )
                OR (
                    status = 'leased'
                    AND attempts_started BETWEEN 1 AND max_attempts
                    AND lease_token IS NOT NULL
                    AND lease_expires_at IS NOT NULL
                    AND completed_at IS NULL
                    AND dead_at IS NULL
                )
                OR (
                    status = 'succeeded'
                    AND attempts_started BETWEEN 1 AND max_attempts
                    AND lease_token IS NULL
                    AND lease_expires_at IS NULL
                    AND completed_at IS NOT NULL
                    AND dead_at IS NULL
                )
                OR (
                    status = 'dead'
                    AND attempts_started BETWEEN 1 AND max_attempts
                    AND lease_token IS NULL
                    AND lease_expires_at IS NULL
                    AND completed_at IS NULL
                    AND dead_at IS NOT NULL
                    AND last_failure_code IS NOT NULL
                )
            )
        ) STRICT
        SQL;

    private const string CREATE_APPLICATION_JOBS_AVAILABLE_INDEX_SQL = <<<'SQL'
        CREATE INDEX application_jobs_available_due_idx
        ON application_jobs (available_at, created_at, job_id)
        WHERE status = 'available'
        SQL;

    private const string CREATE_APPLICATION_JOBS_LEASE_INDEX_SQL = <<<'SQL'
        CREATE INDEX application_jobs_expired_lease_idx
        ON application_jobs (lease_expires_at, created_at, job_id)
        WHERE status = 'leased'
        SQL;

    private const string CREATE_WELCOME_DELIVERIES_SQL = <<<'SQL'
        CREATE TABLE welcome_deliveries (
            idempotency_key TEXT PRIMARY KEY
                CHECK (
                    length(idempotency_key) = 64
                    AND idempotency_key NOT GLOB '*[^0-9a-f]*'
                ),
            job_id TEXT NOT NULL
                CHECK (
                    length(job_id) = 32
                    AND job_id NOT GLOB '*[^0-9a-f]*'
                ),
            recipient_email TEXT NOT NULL
                CHECK (length(CAST(recipient_email AS BLOB)) BETWEEN 3 AND 254),
            created_at INTEGER NOT NULL CHECK (created_at >= 0)
        ) STRICT
        SQL;

    private const string CREATE_DOCUMENTS_SQL = <<<'SQL'
        CREATE TABLE documents (
            account_id INTEGER NOT NULL,
            document_key TEXT NOT NULL,
            title TEXT NOT NULL,
            PRIMARY KEY (account_id, document_key)
        )
        SQL;

    private const string ADD_DOCUMENT_CATEGORY_SQL =
        "ALTER TABLE documents ADD COLUMN category TEXT NOT NULL DEFAULT 'general'";
    private const string ADD_DOCUMENT_SORT_RANK_SQL =
        'ALTER TABLE documents ADD COLUMN sort_rank INTEGER NOT NULL DEFAULT 0';

    private const string CREATE_DOCUMENT_INDEX_SQL = <<<'SQL'
        CREATE INDEX documents_account_rank_key_idx
        ON documents (account_id, sort_rank, document_key COLLATE BINARY)
        SQL;

    private const string CREATE_ACCOUNT_MEMBERSHIPS_SQL = <<<'SQL'
        CREATE TABLE account_memberships (
            principal_id INTEGER NOT NULL,
            account_id INTEGER NOT NULL,
            PRIMARY KEY (principal_id, account_id)
        )
        SQL;

    public function __construct(
        private string $databasePath,
        private LocalMigrationLock $lock,
    ) {
    }

    /**
     * @return list<array{position: int, identifier: non-empty-string, checksum: non-empty-string}>
     */
    public static function manifest(): array
    {
        return [
            [
                'position' => 1,
                'identifier' => self::USER_SCHEMA_IDENTIFIER,
                'checksum' => self::userSchemaChecksum(),
            ],
            [
                'position' => 2,
                'identifier' => self::JOB_SCHEMA_IDENTIFIER,
                'checksum' => self::jobSchemaChecksum(),
            ],
            [
                'position' => 3,
                'identifier' => self::PREPARE_DOCUMENT_IDENTIFIER,
                'checksum' => self::prepareDocumentChecksum(),
            ],
            [
                'position' => 4,
                'identifier' => self::DOCUMENT_CATEGORY_IDENTIFIER,
                'checksum' => self::documentCategoryChecksum(),
            ],
            [
                'position' => 5,
                'identifier' => self::DOCUMENT_SORT_RANK_IDENTIFIER,
                'checksum' => self::documentSortRankChecksum(),
            ],
            [
                'position' => 6,
                'identifier' => self::DOCUMENT_ACCESS_IDENTIFIER,
                'checksum' => self::documentAccessChecksum(),
            ],
        ];
    }

    public function run(): ApplicationMigrationOutcome
    {
        try {
            $acquired = $this->lock->acquire();
        } catch (Throwable $exception) {
            throw new ApplicationMigrationFailed(
                ApplicationMigrationFailureReason::LockFailed,
                previous: $exception,
            );
        }

        if (!$acquired) {
            throw new ApplicationMigrationFailed(ApplicationMigrationFailureReason::Busy);
        }

        try {
            try {
                $connection = Connection::connect(
                    'sqlite:' . $this->databasePath,
                    new QueryBudget(self::QUERY_LIMIT),
                    new QueryTrace(self::QUERY_LIMIT),
                    options: [PDO::ATTR_TIMEOUT => 5],
                );
                $ledger = new SqliteMigrationLedger($connection);
                $history = $ledger->loadHistory(self::manifest());
            } catch (ApplicationMigrationFailed $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                throw new ApplicationMigrationFailed(
                    ApplicationMigrationFailureReason::LedgerUnavailable,
                    previous: $exception,
                );
            }

            $applied = false;

            if (!$history->contains(1)) {
                $this->applyUserSchema($connection, $ledger);
                $applied = true;
            }

            if (!$history->contains(2)) {
                $this->applyJobSchema($connection, $ledger);
                $applied = true;
            }

            if (!$history->contains(3)) {
                $this->prepareDocumentSchema($connection, $ledger);
                $applied = true;
            }

            if (!$history->contains(4)) {
                $this->addDocumentCategory($connection, $ledger);
                $applied = true;
            }

            if (!$history->contains(5)) {
                $this->addDocumentSortRank($connection, $ledger);
                $applied = true;
            }

            if (!$history->contains(6)) {
                $this->createDocumentAccessSchema($connection, $ledger);
                $applied = true;
            }

            return $applied
                ? ApplicationMigrationOutcome::Applied
                : ApplicationMigrationOutcome::UpToDate;
        } finally {
            try {
                $this->lock->release();
            } catch (Throwable $exception) {
                throw new ApplicationMigrationFailed(
                    ApplicationMigrationFailureReason::LockFailed,
                    previous: $exception,
                );
            }
        }
    }

    private function applyUserSchema(
        Connection $connection,
        SqliteMigrationLedger $ledger,
    ): void {
        try {
            $connection->beginTransaction();
            $connection->executeStatement(self::CREATE_USERS_SQL);
            $connection->executeStatement(self::CREATE_USER_EVENTS_SQL);
            $connection->executeStatement(self::CREATE_USER_EVENTS_INDEX_SQL);
            $ledger->record(1, self::USER_SCHEMA_IDENTIFIER, self::userSchemaChecksum());
            $connection->commit();
        } catch (Throwable $exception) {
            throw new ApplicationMigrationFailed(
                ApplicationMigrationFailureReason::ApplyFailed,
                self::USER_SCHEMA_IDENTIFIER,
                $exception,
            );
        } finally {
            if ($connection->inTransaction()) {
                try {
                    $connection->rollBack();
                } catch (Throwable $rollbackFailure) {
                    throw new ApplicationMigrationFailed(
                        ApplicationMigrationFailureReason::ApplyFailed,
                        self::USER_SCHEMA_IDENTIFIER,
                        $rollbackFailure,
                    );
                }
            }
        }
    }

    private function applyJobSchema(
        Connection $connection,
        SqliteMigrationLedger $ledger,
    ): void {
        try {
            $connection->beginTransaction();
            $connection->executeStatement(self::CREATE_APPLICATION_JOBS_SQL);
            $connection->executeStatement(self::CREATE_APPLICATION_JOBS_AVAILABLE_INDEX_SQL);
            $connection->executeStatement(self::CREATE_APPLICATION_JOBS_LEASE_INDEX_SQL);
            $connection->executeStatement(self::CREATE_WELCOME_DELIVERIES_SQL);
            $ledger->record(2, self::JOB_SCHEMA_IDENTIFIER, self::jobSchemaChecksum());
            $connection->commit();
        } catch (Throwable $exception) {
            throw new ApplicationMigrationFailed(
                ApplicationMigrationFailureReason::ApplyFailed,
                self::JOB_SCHEMA_IDENTIFIER,
                $exception,
            );
        } finally {
            if ($connection->inTransaction()) {
                try {
                    $connection->rollBack();
                } catch (Throwable $rollbackFailure) {
                    throw new ApplicationMigrationFailed(
                        ApplicationMigrationFailureReason::ApplyFailed,
                        self::JOB_SCHEMA_IDENTIFIER,
                        $rollbackFailure,
                    );
                }
            }
        }
    }

    private function prepareDocumentSchema(
        Connection $connection,
        SqliteMigrationLedger $ledger,
    ): void {
        try {
            $connection->beginTransaction();
            $connection->executeStatement(self::CREATE_DOCUMENTS_SQL);
            $ledger->record(
                3,
                self::PREPARE_DOCUMENT_IDENTIFIER,
                self::prepareDocumentChecksum(),
            );
            $connection->commit();
        } catch (Throwable $exception) {
            throw new ApplicationMigrationFailed(
                ApplicationMigrationFailureReason::ApplyFailed,
                self::PREPARE_DOCUMENT_IDENTIFIER,
                $exception,
            );
        } finally {
            if ($connection->inTransaction()) {
                try {
                    $connection->rollBack();
                } catch (Throwable $rollbackFailure) {
                    throw new ApplicationMigrationFailed(
                        ApplicationMigrationFailureReason::ApplyFailed,
                        self::PREPARE_DOCUMENT_IDENTIFIER,
                        $rollbackFailure,
                    );
                }
            }
        }
    }

    private function addDocumentCategory(
        Connection $connection,
        SqliteMigrationLedger $ledger,
    ): void {
        try {
            $connection->beginTransaction();
            $connection->executeStatement(self::ADD_DOCUMENT_CATEGORY_SQL);

            $ledger->record(
                4,
                self::DOCUMENT_CATEGORY_IDENTIFIER,
                self::documentCategoryChecksum(),
            );
            $connection->commit();
        } catch (Throwable $exception) {
            throw new ApplicationMigrationFailed(
                ApplicationMigrationFailureReason::ApplyFailed,
                self::DOCUMENT_CATEGORY_IDENTIFIER,
                $exception,
            );
        } finally {
            if ($connection->inTransaction()) {
                try {
                    $connection->rollBack();
                } catch (Throwable $rollbackFailure) {
                    throw new ApplicationMigrationFailed(
                        ApplicationMigrationFailureReason::ApplyFailed,
                        self::DOCUMENT_CATEGORY_IDENTIFIER,
                        $rollbackFailure,
                    );
                }
            }
        }
    }

    private function addDocumentSortRank(
        Connection $connection,
        SqliteMigrationLedger $ledger,
    ): void {
        try {
            $connection->beginTransaction();
            $connection->executeStatement(self::ADD_DOCUMENT_SORT_RANK_SQL);

            $ledger->record(
                5,
                self::DOCUMENT_SORT_RANK_IDENTIFIER,
                self::documentSortRankChecksum(),
            );
            $connection->commit();
        } catch (Throwable $exception) {
            throw new ApplicationMigrationFailed(
                ApplicationMigrationFailureReason::ApplyFailed,
                self::DOCUMENT_SORT_RANK_IDENTIFIER,
                $exception,
            );
        } finally {
            if ($connection->inTransaction()) {
                try {
                    $connection->rollBack();
                } catch (Throwable $rollbackFailure) {
                    throw new ApplicationMigrationFailed(
                        ApplicationMigrationFailureReason::ApplyFailed,
                        self::DOCUMENT_SORT_RANK_IDENTIFIER,
                        $rollbackFailure,
                    );
                }
            }
        }
    }

    private function createDocumentAccessSchema(
        Connection $connection,
        SqliteMigrationLedger $ledger,
    ): void {
        try {
            $connection->beginTransaction();
            $connection->executeStatement(self::CREATE_DOCUMENT_INDEX_SQL);
            $connection->executeStatement(self::CREATE_ACCOUNT_MEMBERSHIPS_SQL);
            $ledger->record(
                6,
                self::DOCUMENT_ACCESS_IDENTIFIER,
                self::documentAccessChecksum(),
            );
            $connection->commit();
        } catch (Throwable $exception) {
            throw new ApplicationMigrationFailed(
                ApplicationMigrationFailureReason::ApplyFailed,
                self::DOCUMENT_ACCESS_IDENTIFIER,
                $exception,
            );
        } finally {
            if ($connection->inTransaction()) {
                try {
                    $connection->rollBack();
                } catch (Throwable $rollbackFailure) {
                    throw new ApplicationMigrationFailed(
                        ApplicationMigrationFailureReason::ApplyFailed,
                        self::DOCUMENT_ACCESS_IDENTIFIER,
                        $rollbackFailure,
                    );
                }
            }
        }
    }

    /** @return non-empty-string */
    private static function userSchemaChecksum(): string
    {
        return self::checksum(
            self::USER_SCHEMA_IDENTIFIER . "\0"
                . self::CREATE_USERS_SQL . "\0"
                . self::CREATE_USER_EVENTS_SQL . "\0"
                . self::CREATE_USER_EVENTS_INDEX_SQL,
        );
    }

    /** @return non-empty-string */
    private static function jobSchemaChecksum(): string
    {
        return self::checksum(
            self::JOB_SCHEMA_IDENTIFIER . "\0"
                . self::CREATE_APPLICATION_JOBS_SQL . "\0"
                . self::CREATE_APPLICATION_JOBS_AVAILABLE_INDEX_SQL . "\0"
                . self::CREATE_APPLICATION_JOBS_LEASE_INDEX_SQL . "\0"
                . self::CREATE_WELCOME_DELIVERIES_SQL,
        );
    }

    /** @return non-empty-string */
    private static function prepareDocumentChecksum(): string
    {
        return self::checksum(
            self::PREPARE_DOCUMENT_IDENTIFIER . "\0"
                . self::CREATE_DOCUMENTS_SQL,
        );
    }

    /** @return non-empty-string */
    private static function documentCategoryChecksum(): string
    {
        return self::checksum(
            self::DOCUMENT_CATEGORY_IDENTIFIER . "\0"
                . self::ADD_DOCUMENT_CATEGORY_SQL,
        );
    }

    /** @return non-empty-string */
    private static function documentSortRankChecksum(): string
    {
        return self::checksum(
            self::DOCUMENT_SORT_RANK_IDENTIFIER . "\0"
                . self::ADD_DOCUMENT_SORT_RANK_SQL,
        );
    }

    /** @return non-empty-string */
    private static function documentAccessChecksum(): string
    {
        return self::checksum(
            self::DOCUMENT_ACCESS_IDENTIFIER . "\0"
                . self::CREATE_DOCUMENT_INDEX_SQL . "\0"
                . self::CREATE_ACCOUNT_MEMBERSHIPS_SQL,
        );
    }

    /** @return non-empty-string */
    private static function checksum(string $material): string
    {
        return hash('sha256', $material);
    }
}
