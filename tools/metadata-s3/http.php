<?php

declare(strict_types=1);

use App\Observability\ErrorLogRequestSummarySink;
use MetadataS3Recipe\DenyDocumentUploads;
use MetadataS3Recipe\DocumentLock;
use MetadataS3Recipe\Documents;
use MetadataS3Recipe\DocumentS3;
use MetadataS3Recipe\LockedDocumentReceiver;
use MetadataS3Recipe\DenyPacketUploads;
use MetadataS3Recipe\LockedPacketReceiver;
use MetadataS3Recipe\Packets;
use MetadataS3Recipe\PacketS3;
use MetadataS3Recipe\ReferenceEnvironment;
use MetadataS3Recipe\SystemDocumentClock;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use function MetadataS3Recipe\documentCoordinator;
use function MetadataS3Recipe\documentS3Client;

require __DIR__ . '/bootstrap.php';
$settings = ReferenceEnvironment::writer()->s3;
$client = documentS3Client($settings);
$settings->root->verify();
$budget = new QueryBudget(16);
$trace = new QueryTrace(16);
$database = Connection::connect('sqlite:' . $settings->root->path . '/documents.sqlite', $budget, $trace);
$lock = new DocumentLock($settings->root);
$documents = new Documents($database, $lock, new DocumentS3($client, $settings->target), new SystemDocumentClock());
$packetBudget = new QueryBudget(32);
$packetTrace = new QueryTrace(32);
$packetDatabase = Connection::connect('sqlite:' . $settings->root->path . '/documents.sqlite', $packetBudget, $packetTrace);
$packets = new Packets($packetDatabase, $lock, new PacketS3($client, $settings->target), new SystemDocumentClock());
return documentCoordinator(new DenyDocumentUploads(), new LockedDocumentReceiver($documents, $lock), $budget, $trace, new ErrorLogRequestSummarySink(),
    new DenyPacketUploads(), new LockedPacketReceiver($packets, $lock), $packetBudget, $packetTrace);
