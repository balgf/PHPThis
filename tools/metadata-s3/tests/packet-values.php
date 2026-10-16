<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use MetadataS3Recipe\DocumentUnavailable;
use MetadataS3Recipe\InvalidMetadata;
use MetadataS3Recipe\PacketInput;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\MultipartForm;
use PHPThis\Http\MultipartLimits;
use PHPThis\Http\RequestBodyTooLarge;

/** @return array{name: string, type: string, tmp_name: string, size: int, error: int} */
function packetUpload(int $error = UPLOAD_ERR_OK, int $bytes = 1): array
{
    return ['name' => 'hostile.svg', 'type' => 'text/html', 'tmp_name' => $error === UPLOAD_ERR_NO_FILE ? '' : '/synthetic/upload',
        'size' => $error === UPLOAD_ERR_NO_FILE ? 0 : $bytes, 'error' => $error];
}

/**
 * @param array<string, mixed> $fields
 * @param array<string, mixed> $files
 */
function packetForm(array $fields, array $files): MultipartForm
{
    return MultipartForm::fromPhp($fields, $files, 196608, new MultipartLimits(196608, 65536, 131072, 1024, 1195));
}

$fields = ['label' => ' label ', 'request_id' => str_repeat('a', 32)];
$files = ['primary' => packetUpload(), 'supporting' => packetUpload()];
$input = PacketInput::fromMultipart(packetForm($fields, $files));
if ($input->metadata->label !== ' label ' || $input->metadata->note !== null || $input->requestId !== str_repeat('a', 32)) {
    throw new LogicException('Packet exact values lost.');
}
$reversed = PacketInput::fromMultipart(packetForm($fields + ['note' => ''], array_reverse($files, true)));
if ($reversed->metadata->note !== '' || $reversed->primary->reportedSizeBytes !== 1) { throw new LogicException('Role order or empty note changed.'); }
$cases = [
    [$fields + ['unknown' => 'secret'], $files, InvalidRequest::class],
    [['label' => '', 'request_id' => 'bad', 'unknown' => 'secret'], $files, InvalidRequest::class],
    [['label' => 'ok'], $files, InvalidRequest::class],
    [$fields, ['primary' => packetUpload()], InvalidRequest::class],
    [$fields, $files + ['third' => packetUpload()], InvalidRequest::class],
    [['label' => '', 'request_id' => str_repeat('a', 32)], $files, InvalidMetadata::class],
    [['label' => 'ok', 'request_id' => str_repeat('A', 32)], $files, InvalidMetadata::class],
    [['label' => 'ok', 'request_id' => str_repeat('a', 31)], $files, InvalidMetadata::class],
    [['label' => 'ok', 'request_id' => str_repeat('a', 33)], $files, InvalidMetadata::class],
    [$fields, ['primary' => packetUpload(bytes: 0), 'supporting' => packetUpload()], InvalidMetadata::class],
    [$fields, ['primary' => packetUpload(), 'supporting' => packetUpload(bytes: 0)], InvalidMetadata::class],
];
foreach ([UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE, UPLOAD_ERR_PARTIAL, UPLOAD_ERR_NO_FILE,
    UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION] as $error) {
    $expected = match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => RequestBodyTooLarge::class,
        UPLOAD_ERR_PARTIAL, UPLOAD_ERR_NO_FILE => InvalidRequest::class,
        default => DocumentUnavailable::class,
    };
    $cases[] = [$fields, ['primary' => packetUpload($error), 'supporting' => packetUpload()], $expected];
    // A later upload error must precede an earlier file's value failure.
    $cases[] = [$fields, ['primary' => packetUpload(bytes: 0), 'supporting' => packetUpload($error)], $expected];
}
foreach ($cases as [$candidateFields, $candidateFiles, $expected]) {
    try { PacketInput::fromMultipart(packetForm($candidateFields, $candidateFiles)); }
    catch (Throwable $failure) {
        if ($failure::class === $expected) { continue; }
        throw new LogicException('Unexpected packet failure class.', previous: $failure);
    }
    throw new LogicException('Invalid packet accepted.');
}
fwrite(STDOUT, "PASS packet values: 27 cases\n");
