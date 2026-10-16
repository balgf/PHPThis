<?php

declare(strict_types=1);

use MetadataS3Recipe\DocumentInvocation;
use MetadataS3Recipe\DocumentLock;
use MetadataS3Recipe\Documents;
use MetadataS3Recipe\DocumentS3;
use MetadataS3Recipe\ReferenceEnvironment;
use MetadataS3Recipe\SystemDocumentClock;
use MetadataS3Recipe\UnknownDocumentCommand;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use function MetadataS3Recipe\documentS3Client;

// Only an authorized local operator supplies the owner; this is not an HTTP authority source.
require_once __DIR__ . '/src/DocumentInput.php';
require_once __DIR__ . '/src/Values.php';
require_once __DIR__ . '/src/DocumentInvocation.php';
try { $invocation = DocumentInvocation::fromArguments(array_slice($argv, 1)); }
catch (UnknownDocumentCommand) { fwrite(STDERR, "{\"error\":\"unknown_command\"}\n"); exit(2); }
catch (InvalidArgumentException) { fwrite(STDERR, "{\"error\":\"invalid_arguments\"}\n"); exit(2); }

$lock = null;
$exitCode = 0;
try {
    require __DIR__ . '/bootstrap.php';
    if (ini_set('memory_limit', '64M') === false) { throw new RuntimeException('Runtime bound unavailable.'); }
    $configuration = match ($invocation->command) {
        'reconcile-one' => ReferenceEnvironment::reconciler(),
        'expire-one' => ReferenceEnvironment::deletion(),
        default => throw new LogicException('Unknown command.'),
    };
    $settings = $configuration->s3;
    $client = documentS3Client($settings);
    $lock = new DocumentLock($settings->root);
    $lock->acquire(true);
    $database = Connection::connect('sqlite:' . $settings->root->path . '/documents.sqlite', new QueryBudget(16), new QueryTrace(16));
    $database->executeStatement('PRAGMA busy_timeout = 0');
    $database->executeStatement('PRAGMA synchronous = FULL');
    $documents = new Documents($database, $lock, new DocumentS3($client, $settings->target), new SystemDocumentClock());
    $outcome = $invocation->command === 'reconcile-one'
        ? $documents->reconcile($invocation->id, $invocation->owner)
        : $documents->expire($invocation->id, $invocation->owner);
    fwrite(STDOUT, json_encode(['command' => $invocation->command, 'outcome' => $outcome], JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable) { fwrite(STDERR, "{\"error\":\"command_failed\"}\n"); $exitCode = 1; }
finally { $lock?->release(); }
exit($exitCode);
