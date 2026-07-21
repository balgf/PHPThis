<?php

declare(strict_types=1);

namespace Example\Migrations;

use RuntimeException;
use Throwable;

final class ApplicationMigrationFailed extends RuntimeException
{
    public function __construct(
        public readonly ApplicationMigrationFailureReason $reason,
        public readonly ?string $migrationIdentifier = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct('Application migration failed.', 0, $previous);
    }

    public function stderrLine(): string
    {
        return json_encode(
            [
                'error' => 'migration_failed',
                'reason' => $this->reason->value,
                'migration' => $this->migrationIdentifier,
            ],
            JSON_THROW_ON_ERROR,
        ) . "\n";
    }
}
