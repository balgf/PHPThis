<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

final readonly class PngHeader
{
    private function __construct(public int $width, public int $height, public int $channels) {}

    public static function fromBytes(string $data): self
    {
        if (strlen($data) !== 13) {
            throw new RejectedPng('PNG rejected.');
        }
        $h = unpack('Nwidth/Nheight/Cdepth/Ccolour/Ccompression/Cfilter/Cinterlace', $data);
        if (!is_array($h) || array_keys($h) !== ['width', 'height', 'depth', 'colour', 'compression', 'filter', 'interlace']
            || !is_int($h['width']) || !is_int($h['height']) || !is_int($h['depth'])
            || !is_int($h['colour']) || !is_int($h['compression']) || !is_int($h['filter']) || !is_int($h['interlace'])) {
            throw new RejectedPng('PNG rejected.');
        }
        if ($h['width'] < 1 || $h['width'] > 1024 || $h['height'] < 1 || $h['height'] > 1024
            || $h['depth'] !== 8 || !in_array($h['colour'], [2, 6], true)
            || $h['compression'] !== 0 || $h['filter'] !== 0 || $h['interlace'] !== 0) {
            throw new RejectedPng('PNG rejected.');
        }
        return new self($h['width'], $h['height'], $h['colour'] === 2 ? 3 : 4);
    }
}
