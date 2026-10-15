<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

final readonly class BoundedPng
{
    private function __construct(public int $width, public int $height) {}

    public static function fromBytes(string $bytes): self
    {
        if (strlen($bytes) > 1_048_576 || substr($bytes, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            throw new RejectedPng('PNG rejected.');
        }
        $offset = 8;
        $phase = 'header';
        $width = 0;
        $height = 0;
        $channels = 0;
        $compressed = '';
        for ($index = 0; $index < 64; $index++) {
            if (strlen($bytes) - $offset < 12) {
                throw new RejectedPng('PNG rejected.');
            }
            $length = unpack('Nlength', substr($bytes, $offset, 4));
            if (!is_array($length) || !isset($length['length']) || !is_int($length['length'])
                || $length['length'] > strlen($bytes) - $offset - 12) {
                throw new RejectedPng('PNG rejected.');
            }
            $size = $length['length'];
            $kind = substr($bytes, $offset + 4, 4);
            $data = substr($bytes, $offset + 8, $size);
            if (hash('crc32b', $kind . $data, true) !== substr($bytes, $offset + 8 + $size, 4)) {
                throw new RejectedPng('PNG rejected.');
            }
            $offset += $size + 12;
            if ($phase === 'header' && $kind === 'IHDR' && $size === 13) {
                $header = PngHeader::fromBytes($data);
                $width = $header->width;
                $height = $header->height;
                $channels = $header->channels;
                $phase = 'data';
            } elseif ($phase === 'data' && $kind === 'IDAT') {
                $compressed .= $data;
            } elseif ($phase === 'data' && $kind === 'IEND' && $size === 0
                && $compressed !== '' && $offset === strlen($bytes)) {
                $phase = 'complete';
                break;
            } else {
                throw new RejectedPng('PNG rejected.');
            }
        }
        if ($phase !== 'complete') {
            throw new RejectedPng('PNG rejected.');
        }
        $stride = $width * $channels + 1;
        $expected = $stride * $height;
        // Cap the first native inflate even when a forged IHDR understates expansion.
        $raw = @gzuncompress($compressed, $expected + 1);
        if (!is_string($raw) || strlen($raw) !== $expected) {
            throw new RejectedPng('PNG rejected.');
        }
        for ($row = 0; $row < $height; $row++) {
            if (ord($raw[$row * $stride]) > 4) {
                throw new RejectedPng('PNG rejected.');
            }
        }
        unset($raw);
        // This identical immutable input has already proved its decoded bound.
        $context = inflate_init(ZLIB_ENCODING_DEFLATE);
        if ($context === false || @inflate_add($context, $compressed, ZLIB_FINISH) === false
            || inflate_get_status($context) !== ZLIB_STREAM_END
            || inflate_get_read_len($context) !== strlen($compressed)) {
            throw new RejectedPng('PNG rejected.');
        }
        return new self($width, $height);
    }
}
