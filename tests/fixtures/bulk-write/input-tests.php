<?php

declare(strict_types=1);

use BulkWriteRecipe\LedgerBatch;
use BulkWriteRecipe\LedgerEntry;

require_once __DIR__ . '/LedgerEntry.php';
require_once __DIR__ . '/LedgerBatch.php';

$entry = ['entry_id' => '9223372036854775807', 'quantity' => '-9223372036854775808', 'amount' => '-99999999999999999999999999.9999', 'memo' => "Unicode 雪 😀 and 'SQL'; --\n"];
$batch = LedgerBatch::fromJson(json_encode(['entries' => [$entry]], JSON_THROW_ON_ERROR));
if (json_decode($batch->encodedRows, true, 8, JSON_THROW_ON_ERROR) !== [$entry] || $batch->rowCount !== 1) {
    throw new RuntimeException('Canonical exact values changed before SQL.');
}
if (LedgerBatch::fromJson('{"entries":[]}')->rowCount !== 0) {
    throw new RuntimeException('Empty batch must be explicit.');
}
$rows = [];
for ($index = 1; $index <= 64; ++$index) {
    $rows[] = array_replace($entry, ['entry_id' => (string) $index, 'memo' => null]);
}
if (LedgerBatch::fromJson(json_encode(['entries' => $rows], JSON_THROW_ON_ERROR))->rowCount !== 64) {
    throw new RuntimeException('Maximum batch was rejected.');
}
$invalid = ['{', 'null', '[]', '{}', '{"entries":{}}', '{"entries":[],"unknown":true}', '{"entries":[null]}', str_repeat(' ', 65_537)];
$invalid[] = json_encode(['entries' => [...$rows, $entry]], JSON_THROW_ON_ERROR);
$invalid[] = json_encode(['entries' => [$entry, $entry]], JSON_THROW_ON_ERROR);
foreach (['entry_id', 'quantity', 'amount', 'memo'] as $key) {
    $missing = $entry;
    unset($missing[$key]);
    $invalid[] = json_encode(['entries' => [$missing]], JSON_THROW_ON_ERROR);
}
$invalid[] = json_encode(['entries' => [$entry + ['unknown' => true]]], JSON_THROW_ON_ERROR);
foreach ([
    'entry_id' => ['', '0', '-1', '+1', '01', '1e2', '9223372036854775808', 1, 1.0, null, true, []],
    'quantity' => ['-0', '00', '-01', ' 1', '1 ', '+1', '1.0', '1e2', '9223372036854775808', '-9223372036854775809', 1, null, false],
    'amount' => ['1', '1.0', '1.00000', '01.0000', '+1.0000', '-0.0000', '1e4', 'NaN', 'Infinity', ' 1.0000', '1.0000 ', '100000000000000000000000000.0000', 1.25, 1, null],
    'memo' => [str_repeat('x', 129), "zero\0byte", 1, [], true],
] as $key => $values) {
    foreach ($values as $value) {
        $invalid[] = json_encode(['entries' => [array_replace($entry, [$key => $value])]], JSON_THROW_ON_ERROR);
    }
}
foreach ($invalid as $json) {
    try {
        LedgerBatch::fromJson($json);
    } catch (InvalidArgumentException $failure) {
        if (str_contains($failure->getMessage(), '922337') || str_contains($failure->getMessage(), 'Unicode')) {
            throw new RuntimeException('Rejected input entered a parser diagnostic.');
        }
        continue;
    }
    throw new RuntimeException('An invalid ledger batch was accepted.');
}
try {
    LedgerBatch::fromJson('{"entries":[{"entry_id":"1","quantity":"0","amount":"0.0000","memo":"' . "\xff" . '"}]}');
    throw new RuntimeException('Malformed UTF-8 was accepted.');
} catch (InvalidArgumentException) {
}
$entry['memo'] = null;
$projection = LedgerEntry::fromDatabaseRow($entry);
if ($projection->entryId !== $entry['entry_id'] || $projection->quantity !== $entry['quantity'] || $projection->amount !== $entry['amount'] || $projection->memo !== null) {
    throw new RuntimeException('Projection changed exact SQL values.');
}
// Encoding control bytes can exceed the encoded budget even within raw input and row limits.
$large = [];
for ($index = 1; $index <= 64; ++$index) {
    $large[] = array_replace($entry, ['entry_id' => (string) $index, 'memo' => str_repeat("\x01", 128)]);
}
try {
    LedgerBatch::fromJson(json_encode(['entries' => $large], JSON_THROW_ON_ERROR));
    throw new RuntimeException('Encoded byte overflow was accepted.');
} catch (InvalidArgumentException) {
}

$small = '{"entries":[]}';
LedgerBatch::fromJson(str_pad($small, 65_536));
try {
    LedgerBatch::fromJson(str_pad($small, 65_537));
    throw new RuntimeException('Raw input byte overflow was accepted.');
} catch (InvalidArgumentException) {
}
$exactRows = [];
for ($index = 1; $index <= 64; ++$index) {
    $exactRows[] = ['entry_id' => (string) $index, 'quantity' => '0', 'amount' => '0.0000', 'memo' => str_repeat("\x01", 70)];
}
$remaining = 32_768 - strlen(json_encode($exactRows, JSON_THROW_ON_ERROR));
foreach ($exactRows as $index => $row) {
    $add = min(128 - strlen($row['memo']), $remaining);
    $exactRows[$index]['memo'] .= str_repeat('a', $add);
    $remaining -= $add;
}
if ($remaining !== 0) {
    throw new RuntimeException('Encoded-bound fixture cannot reach the exact limit.');
}
$exactBatch = LedgerBatch::fromJson(json_encode(['entries' => $exactRows], JSON_THROW_ON_ERROR));
if (strlen($exactBatch->encodedRows) !== 32_768) {
    throw new RuntimeException('Exact encoded byte limit was not exercised.');
}
$exactRows[63]['memo'] .= 'a';
try {
    LedgerBatch::fromJson(json_encode(['entries' => $exactRows], JSON_THROW_ON_ERROR));
    throw new RuntimeException('One encoded byte of excess was accepted.');
} catch (InvalidArgumentException) {
}
LedgerEntry::fromValues('1', '0', '0.0000', str_repeat('é', 64));
LedgerEntry::fromValues('1', '0', '0.0000', '');
try {
    LedgerBatch::fromJson(json_encode(['entries' => [array_replace($entry, ['amount' => 'bad']), array_replace($entry, ['memo' => 1])]], JSON_THROW_ON_ERROR));
    throw new RuntimeException('Malformed later row was accepted.');
} catch (InvalidArgumentException $failure) {
    if ($failure->getMessage() !== 'Ledger entry structure is invalid.') {
        throw new RuntimeException('A value failure preceded a later structural failure.');
    }
}
foreach ([['entry_id' => 1], ['amount' => '1.23456'], ['memo' => "\xff"], ['unknown' => 'value']] as $change) {
    try {
        LedgerEntry::fromDatabaseRow(array_replace($entry, $change));
    } catch (UnexpectedValueException) {
        continue;
    }
    throw new RuntimeException('Malformed SQL projection was accepted.');
}

fwrite(STDOUT, "PASS bulk-write exact input and projection boundaries\n");
