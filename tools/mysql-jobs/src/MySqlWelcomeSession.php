<?php

declare(strict_types=1);

namespace MySqlWelcomeRecipe;

use PHPThis\Database\Connection;
use RuntimeException;

final class MySqlWelcomeSession
{
    public static function configure(Connection $connection): void
    {
        $connection->executeStatement('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $connection->executeStatement('SET SESSION innodb_lock_wait_timeout = 2');
        $connection->executeStatement('SET SESSION lock_wait_timeout = 2');
        $connection->executeStatement('SET SESSION max_execution_time = 1000');
        $row = $connection->selectOneRow(
            <<<'SQL'
                SELECT VERSION() AS version, @@session.transaction_isolation AS isolation_level,
                    @@global.innodb_flush_log_at_trx_commit AS flush_log,
                    @@global.sync_binlog AS sync_binlog, @@global.log_bin AS log_bin,
                    @@session.binlog_format AS binlog_format, @@global.innodb_doublewrite AS doublewrite
                SQL,
        );
        if ($row === null || ($row['version'] ?? null) !== '8.4.11'
            || ($row['isolation_level'] ?? null) !== 'READ-COMMITTED'
            || ($row['flush_log'] ?? null) !== 1 || ($row['sync_binlog'] ?? null) !== 1
            || ($row['log_bin'] ?? null) !== 1 || ($row['binlog_format'] ?? null) !== 'ROW'
            || ($row['doublewrite'] ?? null) !== 'ON') {
            throw new RuntimeException('The MySQL welcome profile is unavailable.');
        }
    }
}
