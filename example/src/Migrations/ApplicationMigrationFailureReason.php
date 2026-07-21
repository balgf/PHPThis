<?php

declare(strict_types=1);

namespace Example\Migrations;

enum ApplicationMigrationFailureReason: string
{
    case Busy = 'busy';
    case ChecksumDrift = 'checksum_drift';
    case HistoryInvalid = 'history_invalid';
    case LedgerUnavailable = 'ledger_unavailable';
    case ApplyFailed = 'apply_failed';
    case LockFailed = 'lock_failed';
}
