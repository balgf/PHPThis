<?php

declare(strict_types=1);

use MySqlWelcomeRecipe\ProducerConfiguration;
use MySqlWelcomeRecipe\WorkerConfiguration;
use PHPThis\Verification\EnvironmentAccessProfile;
use PHPThis\Verification\SyntaxProfile;

require __DIR__ . '/process-support.php';
require __DIR__ . '/mysql-jobs/bootstrap.php';
require __DIR__ . '/../verification/SyntaxProfile.php';
require __DIR__ . '/../verification/EnvironmentAccessProfile.php';

function requireMySqlReference(bool $condition): void
{
    if (!$condition) {
        throw new RuntimeException('MySQL reference source/failure control failed.');
    }
}

/** @param list<string> $arguments
 * @return array{exit_code: int, stdout: string, stderr: string}
 */
function runMySqlReferenceControl(string $path, array $arguments = []): array
{
    return runBoundedMaintainerProcess([PHP_BINARY, '-d', 'display_errors=0', '-d', 'log_errors=0', $path, ...$arguments], dirname(__DIR__), [], 5_000, 4096, 4096);
}

$sources = glob(__DIR__ . '/mysql-jobs/src/*.php');
requireMySqlReference(is_array($sources) && $sources !== []);
if (!is_array($sources)) {
    throw new RuntimeException('Reference source inventory is missing.');
}
foreach ($sources as $path) {
    $source = file_get_contents($path);
    requireMySqlReference(is_string($source));
    if (is_string($source)) {
        requireMySqlReference(SyntaxProfile::failures($source, $path) === []);
        $environment = EnvironmentAccessProfile::inspect($source, $path);
        requireMySqlReference($environment['failures'] === []);
        requireMySqlReference(basename($path) === 'ReferenceEnvironment.php' || $environment['reads'] === []);
    }
}
foreach ([false, '', 'mysql:host=example.invalid;dbname=production', 'mysql:host=127.0.0.1;port=65536;dbname=phpthis_jobs;charset=utf8mb4'] as $dsn) {
    try {
        WorkerConfiguration::fromInputs($dsn, str_repeat('a', 64));
        throw new LogicException('Invalid worker configuration was accepted.');
    } catch (InvalidArgumentException) {
        // Reject before Connection construction; no service is used here.
    }
}
try {
    ProducerConfiguration::fromInputs('mysql:host=127.0.0.1;port=3306;dbname=phpthis_jobs;charset=utf8mb4', false);
    throw new LogicException('Missing producer credential was accepted.');
} catch (InvalidArgumentException) {
}
requireMySqlReference(runMySqlReferenceControl(__DIR__ . '/mysql-jobs/console.php', ['jobs:run-one']) === ['exit_code' => 1, 'stdout' => '', 'stderr' => "{\"error\":\"command_failed\"}\n"]);
requireMySqlReference(runMySqlReferenceControl(__DIR__ . '/mysql-jobs/console.php', ['unknown']) === ['exit_code' => 2, 'stdout' => '', 'stderr' => "{\"error\":\"unknown_command\"}\n"]);
requireMySqlReference(runMySqlReferenceControl(__DIR__ . '/mysql-jobs/console.php', ['jobs:run-one', 'extra']) === ['exit_code' => 2, 'stdout' => '', 'stderr' => "{\"error\":\"invalid_arguments\"}\n"]);
requireMySqlReference(runMySqlReferenceControl(__DIR__ . '/mysql-jobs/tools/verify-jobs.php') === ['exit_code' => 1, 'stdout' => '', 'stderr' => "JOBS VERIFY FAIL\n"]);

// Exercise only entrypoint control flow here, never label synthetic modules as
// service evidence. The real runner separately requires all live modules.
$temporary = dirname(__DIR__) . '/tmp/mysql-gate-' . bin2hex(random_bytes(8));
mkdir($temporary . '/tools/verify-jobs', 0700, true);
try {
    copy(__DIR__ . '/mysql-jobs/tools/verify-jobs.php', $temporary . '/tools/verify-jobs.php');
    file_put_contents($temporary . '/bootstrap.php', "<?php\ndeclare(strict_types=1);\n");
    file_put_contents($temporary . '/tools/verify-jobs/publication.php', '<?php function verifyJobsPublication(): array { return []; }');
    file_put_contents($temporary . '/tools/verify-jobs/delivery.php', '<?php function verifyJobsDelivery(): array { return []; }');
    file_put_contents($temporary . '/tools/verify-jobs/operations.php', '<?php function verifyJobsOperations(): array { return []; }');
    requireMySqlReference(runMySqlReferenceControl($temporary . '/tools/verify-jobs.php') === ['exit_code' => 0, 'stdout' => "JOBS VERIFY PASS\n", 'stderr' => '']);
    file_put_contents($temporary . '/tools/verify-jobs/delivery.php', '<?php exit(0);');
    requireMySqlReference(runMySqlReferenceControl($temporary . '/tools/verify-jobs.php') === ['exit_code' => 1, 'stdout' => '', 'stderr' => "JOBS VERIFY FAIL\n"]);
    file_put_contents($temporary . '/tools/verify-jobs/delivery.php', '<?php function verifyJobsDelivery(): array { return ["delivery_failed"]; }');
    requireMySqlReference(runMySqlReferenceControl($temporary . '/tools/verify-jobs.php') === ['exit_code' => 1, 'stdout' => '', 'stderr' => "JOBS VERIFY FAIL\n"]);
    unlink($temporary . '/tools/verify-jobs/delivery.php');
    requireMySqlReference(runMySqlReferenceControl($temporary . '/tools/verify-jobs.php') === ['exit_code' => 1, 'stdout' => '', 'stderr' => "JOBS VERIFY FAIL\n"]);
} finally {
    foreach (['tools/verify-jobs/publication.php', 'tools/verify-jobs/delivery.php', 'tools/verify-jobs/operations.php', 'tools/verify-jobs.php', 'bootstrap.php'] as $relative) {
        if (is_file($temporary . '/' . $relative)) {
            unlink($temporary . '/' . $relative);
        }
    }
    rmdir($temporary . '/tools/verify-jobs');
    rmdir($temporary . '/tools');
    rmdir($temporary);
}
fwrite(STDOUT, "PASS MySQL reference source and fail-closed controls (no service claim)\n");
