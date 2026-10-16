<?php

declare(strict_types=1);

namespace MetadataS3Recipe;

use PHPThis\Http\RequestUpload;

/** Owns the same native upload handle from verification through the single Put. */
final class VerifiedUpload
{
    /** @var resource|null */
    private mixed $stream;

    /** @param resource $stream */
    private function __construct(
        mixed $stream, public readonly int $bytes,
        public readonly string $sha256, public readonly string $checksum,
    ) { $this->stream = $stream; }

    public static function open(RequestUpload $upload): self
    {
        if (!is_uploaded_file($upload->temporaryPath)) { throw new DocumentUnavailable('Document provenance unavailable.'); }
        $stream = @fopen($upload->temporaryPath, 'rb');
        if ($stream === false) { throw new DocumentUnavailable('Document bytes unavailable.'); }
        try {
            $stat = fstat($stream);
            if (!is_array($stat) || ($stat['mode'] & 0170000) !== 0100000
                || $stat['size'] !== $upload->reportedSizeBytes || $stat['size'] < 1 || $stat['size'] > 65_536) {
                throw new DocumentUnavailable('Document size unavailable.');
            }
            $hash = hash_init('sha256');
            $read = hash_update_stream($hash, $stream, 65_537);
            if ($read !== $upload->reportedSizeBytes || !feof($stream) || !rewind($stream) || ftell($stream) !== 0) {
                throw new DocumentUnavailable('Document bytes changed.');
            }
            $digest = hash_final($hash, true);
            return new self($stream, $read, bin2hex($digest), base64_encode($digest));
        } catch (\Throwable $failure) { fclose($stream); throw $failure; }
    }

    /** @return resource */
    public function handle(): mixed
    {
        if (!is_resource($this->stream) || ftell($this->stream) !== 0) { throw new DocumentUnavailable('Document upload consumed.'); }
        return $this->stream;
    }

    public function close(): void
    {
        if (is_resource($this->stream)) { fclose($this->stream); }
        $this->stream = null;
    }
}
