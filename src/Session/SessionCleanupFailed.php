<?php

declare(strict_types=1);

namespace PHPThis\Session;

final class SessionCleanupFailed extends \RuntimeException
{
    public function __construct(public readonly \Throwable $primaryFailure, public readonly \Throwable $cleanupFailure) {
        parent::__construct('Session cleanup failed after a primary failure.');
    }
}
