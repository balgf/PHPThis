<?php

declare(strict_types=1);

use CatalogImageRecipe\BoundedPng;
use CatalogImageRecipe\RejectedPng;

function requireCatalogProof(bool $condition): void
{
    if (!$condition) {
        throw new RuntimeException('Catalog reference assertion failed.');
    }
}

function pngChunk(string $type, string $data): string
{
    return pack('N', strlen($data)) . $type . $data . hash('crc32b', $type . $data, true);
}

function catalogPng(int $width = 1, int $height = 1, int $colour = 2, ?string $raw = null): string
{
    $pixels = $raw ?? str_repeat("\0" . str_repeat('a', $width * ($colour === 6 ? 4 : 3)), $height);
    $compressed = gzcompress($pixels);
    if ($compressed === false) {
        throw new RuntimeException('Fixture compression failed.');
    }
    return "\x89PNG\r\n\x1a\n"
        . pngChunk('IHDR', pack('NNCCCCC', $width, $height, 8, $colour, 0, 0, 0))
        . pngChunk('IDAT', $compressed) . pngChunk('IEND', '');
}

function verifyPngParser(): void
{
    $small = catalogPng();
    $rgb = BoundedPng::fromBytes($small);
    requireCatalogProof($rgb->width === 1 && $rgb->height === 1);
    $maximum = BoundedPng::fromBytes(catalogPng(1024, 1024, 6));
    requireCatalogProof($maximum->width === 1024 && $maximum->height === 1024);
    BoundedPng::fromBytes(catalogPng(1, 1, 6));

    $trailingStream = gzcompress("\0abc");
    requireCatalogProof(is_string($trailingStream));
    if (!is_string($trailingStream)) {
        throw new RuntimeException('Fixture compression failed.');
    }
    $invalid = [
        '', '<svg/>', substr($small, 0, 7), substr($small, 0, -12), substr($small, 0, 42),
        substr($small, 0, 29) . "\0\0\0\0" . substr($small, 33),
        catalogPng(raw: "\x05abc"), catalogPng(raw: "\0ab"),
        catalogPng(1, 2, raw: "\0abc"), catalogPng(raw: str_repeat("\0abc", 5)),
        catalogPng(raw: str_repeat("\0", 8_388_608)),
        $small . '<?php ?>', catalogPng(1025), catalogPng(height: 1025),
        catalogPng(colour: 0),
        substr($small, 0, 8) . pngChunk('IHDR', pack('NNCCCCC', 1, 1, 16, 2, 0, 0, 0)) . substr($small, 33),
        substr($small, 0, 8) . pngChunk('IHDR', pack('NNCCCCC', 1, 1, 8, 2, 0, 0, 1)) . substr($small, 33),
        substr($small, 0, 33) . pngChunk('tEXt', "comment\0hidden") . substr($small, 33),
        substr($small, 0, 33) . pngChunk('acTL', pack('NN', 1, 0)) . substr($small, 33),
        substr($small, 0, 33) . str_repeat(pngChunk('IDAT', ''), 63) . substr($small, 33),
        substr($small, 0, 33) . pngChunk('IDAT', $trailingStream . 'trailing') . pngChunk('IEND', ''),
        $small . str_repeat('x', 1_048_576),
    ];
    foreach ($invalid as $bytes) {
        try {
            BoundedPng::fromBytes($bytes);
            throw new LogicException('Invalid PNG fixture was accepted.');
        } catch (RejectedPng) {
        }
    }
}
