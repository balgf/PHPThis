<?php

declare(strict_types=1);

/** @return list<non-empty-string> */
function runJobsVerificationModules(): array
{
    set_error_handler(
        static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException('Jobs verification runtime warning.', 0, $severity);
        },
    );

    try {
        require dirname(__DIR__) . '/bootstrap.php';
        require __DIR__ . '/verify-jobs/publication.php';
        require __DIR__ . '/verify-jobs/delivery.php';
        require __DIR__ . '/verify-jobs/operations.php';

        $publicationFailures = verifyJobsPublication();
        $deliveryFailures = verifyJobsDelivery();
        $operationsFailures = verifyJobsOperations();

        return [
            ...$publicationFailures,
            ...$deliveryFailures,
            ...$operationsFailures,
        ];
    } finally {
        restore_error_handler();
    }
}

(static function (): void {
    $jobsVerificationState = new class {
        public bool $completed = false;
    };

    register_shutdown_function(
        static function () use ($jobsVerificationState): void {
            if ($jobsVerificationState->completed) {
                return;
            }

            fwrite(STDERR, "JOBS VERIFY FAIL\n");
            exit(1);
        },
    );

    try {
        $failures = runJobsVerificationModules();
    } catch (Throwable) {
        $jobsVerificationState->completed = true;
        fwrite(STDERR, "JOBS VERIFY FAIL\n");
        exit(1);
    }

    if ($failures !== []) {
        $jobsVerificationState->completed = true;
        fwrite(STDERR, "JOBS VERIFY FAIL\n");
        exit(1);
    }

    $passBytes = "JOBS VERIFY PASS\n";
    $written = fwrite(STDOUT, $passBytes);

    if ($written !== strlen($passBytes)) {
        exit(1);
    }

    $jobsVerificationState->completed = true;
})();
