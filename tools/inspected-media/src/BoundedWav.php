<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

final readonly class BoundedWav
{
    public const int MINIMUM_BYTES = 46;
    public const int MAXIMUM_BYTES = 320_044;

    private function __construct(public int $sampleFrames) {}

    public static function fromBytes(string $bytes): self
    {
        $size = strlen($bytes);
        if ($size < self::MINIMUM_BYTES || $size > self::MAXIMUM_BYTES || ($size - 44) % 2 !== 0) {
            throw new RejectedWav('WAV outside the accepted profile.');
        }
        // Compare both declared lengths with actual bounded bytes, without trusting allocation/seek claims.
        $header = 'RIFF' . pack('V', $size - 8) . 'WAVEfmt '
            . pack('VvvVVvv', 16, 1, 1, 16_000, 32_000, 2, 16)
            . 'data' . pack('V', $size - 44);
        if (substr($bytes, 0, 44) !== $header) { throw new RejectedWav('WAV outside the accepted profile.'); }
        // Every aligned signed 16-bit pattern is PCM; there is no compressed stream to decode.
        return new self(intdiv($size - 44, 2));
    }
}
