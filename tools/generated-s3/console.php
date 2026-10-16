<?php

declare(strict_types=1);

use GeneratedS3Recipe\ExportInvocation;
use GeneratedS3Recipe\ExportLock;
use GeneratedS3Recipe\InventoryExports;
use GeneratedS3Recipe\InventoryS3;
use GeneratedS3Recipe\ReferenceEnvironment;
use GeneratedS3Recipe\SystemExportClock;
use GeneratedS3Recipe\TestToneProducer;
use GeneratedS3Recipe\UnknownExportCommand;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use function GeneratedS3Recipe\exportS3Client;

// Parse the closed command before loading credentials or touching application storage.
require_once __DIR__ . '/src/Values.php';
require_once __DIR__ . '/src/ExportInvocation.php';
try { $invocation = ExportInvocation::fromArguments(array_slice($argv, 1)); }
catch (UnknownExportCommand) { fwrite(STDERR, "{\"error\":\"unknown_command\"}\n"); exit(2); }
catch (InvalidArgumentException) { fwrite(STDERR, "{\"error\":\"invalid_arguments\"}\n"); exit(2); }

$lock = null;
$exitCode = 0;
try {
    require __DIR__ . '/bootstrap.php';
    if (ini_set('memory_limit', '64M') === false) { throw new RuntimeException('Runtime bound unavailable.'); }
    $configuration = match ($invocation->command) {
        'generate', 'generate-test-tone' => ReferenceEnvironment::writer(),
        'verify' => ReferenceEnvironment::reader(),
        'reconcile-one' => ReferenceEnvironment::reconciler(),
        'expire-one' => ReferenceEnvironment::deletion(),
        default => throw new LogicException('Unknown command.'),
    };
    $settings = $configuration->s3;
    $tone = null;
    if ($invocation->command === 'generate-test-tone') {
        $settings->verify(time());
        $tone = TestToneProducer::generate();
    }
    $client = exportS3Client($settings);
    $lock = new ExportLock($settings->root);
    $lock->acquire($invocation->command !== 'verify');
    $database = Connection::connect('sqlite:' . $settings->root->path . '/inventory.sqlite', new QueryBudget(16), new QueryTrace(16));
    $database->executeStatement('PRAGMA synchronous = FULL');
    $database->executeStatement('PRAGMA busy_timeout = 0');
    $operation = new InventoryExports($database, $lock, new InventoryS3($client, $settings->target), new SystemExportClock());
    if ($invocation->command === 'generate') { $operation->generate(); $outcome = 'generated'; }
    elseif ($invocation->command === 'generate-test-tone') {
        $operation->generateTestTone($tone); $outcome = 'generated';
    }
    else {
        $id = $invocation->id;
        if ($id === null) { throw new LogicException('Missing identity.'); }
        if ($invocation->command === 'verify') { $operation->read($id); $outcome = 'verified'; }
        elseif ($invocation->command === 'reconcile-one') { $outcome = $operation->reconcile($id); }
        else { $outcome = $operation->expire($id); }
    }
    fwrite(STDOUT, json_encode(['command' => $invocation->command, 'outcome' => $outcome], JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable) { fwrite(STDERR, "{\"error\":\"command_failed\"}\n"); $exitCode = 1; }
finally { $lock?->release(); }
exit($exitCode);
