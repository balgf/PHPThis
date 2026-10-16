<?php

declare(strict_types=1);

// Test-only fresh-process composition with synthetic SDK state. No alternate production command.
require dirname(__DIR__) . '/bootstrap.php';
require __DIR__ . '/Fixture.php';
require __DIR__ . '/PacketFixture.php';
require __DIR__ . '/packet-loopback.php';

use MetadataS3Recipe\DocumentInvocation;
use MetadataS3Recipe\DocumentLock;
use MetadataS3Recipe\DocumentRoot;
use MetadataS3Recipe\PacketId;
use MetadataS3Recipe\Packets;
use MetadataS3Recipe\PacketS3;
use MetadataS3Recipe\S3Target;
use MetadataS3Recipe\SystemDocumentClock;
use MetadataS3Recipe\Tests\PacketPeer;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;

$lock = null;
try {
    $invocation = DocumentInvocation::fromArguments(array_slice($argv, 1));
    if (!$invocation->id instanceof PacketId) { throw new LogicException('Packet command required.'); }
    $root = getcwd();
    if (!is_string($root)) { throw new LogicException('Fixture root unavailable.'); }
    $raw = file_get_contents($root . '/plan.json'); $stored = file_get_contents($root . '/packet-remote.json');
    if (!is_string($raw) || !is_string($stored)) { throw new LogicException('Fixture state unavailable.'); }
    $plan = json_decode($raw, true, 4, JSON_THROW_ON_ERROR);
    if (!is_array($plan) || !is_string($plan['fault'] ?? null) || !is_string($plan['crash_on'] ?? '')) { throw new LogicException('Fixture plan unavailable.'); }
    $peer = new PacketPeer(S3Target::fromInputs('fixture-bucket', 'us-east-1', '000000000000',
        'arn:aws:kms:us-east-1:000000000000:key/00000000-0000-0000-0000-000000000001'));
    $peer->restore($stored); $peer->fault = $plan['fault'];
    $peer->save = static function () use ($root, $peer): void { file_put_contents($root . '/packet-remote.json', $peer->export()); };
    $peer->before = static function (Aws\CommandInterface $command) use ($root, $peer, $plan): void {
        $key = $command['Key'];
        if (!is_string($key)) { return; }
        $role = str_ends_with($key, '/primary') ? 'primary' : 'supporting';
        if (($plan['crash_on'] ?? '') === $command->getName() . ':' . $role) {
            file_put_contents($root . '/worker-receipt.json', json_encode(['calls' => $peer->calls, 'outcome' => 'terminated'], JSON_THROW_ON_ERROR));
            // Deliberately bypass PHP finally blocks, after the operation has persisted its claim.
            exit(99);
        }
    };
    $lock = new DocumentLock(DocumentRoot::fromInput($root)); $lock->acquire(true);
    $trace = new QueryTrace(32);
    $client = $peer->client();
    if (array_key_exists('loopback_port', $plan)) {
        if (!is_int($plan['loopback_port'])) { throw new LogicException('Invalid loopback plan.'); }
        $client = packetLoopbackClient($plan['loopback_port']);
    }
    $operation = new Packets(Connection::connect('sqlite:' . $root . '/documents.sqlite', new QueryBudget(32), $trace), $lock,
        new PacketS3($client, $peer->target, $invocation->command === 'purge-packet' ? 8 : 4), new SystemDocumentClock());
    $outcome = $invocation->command === 'reconcile-packet'
        ? $operation->reconcile($invocation->id, $invocation->owner) : $operation->purge($invocation->id, $invocation->owner);
    file_put_contents($root . '/worker-receipt.json', json_encode(['calls' => $peer->calls, 'statements' => $trace->snapshot()['statements'], 'outcome' => $outcome], JSON_THROW_ON_ERROR));
    fwrite(STDOUT, json_encode(['outcome' => $outcome], JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable) { fwrite(STDERR, "packet_worker_failed\n"); exit(1); }
finally { $lock?->release(); }
