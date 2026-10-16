<?php

declare(strict_types=1);

use CatalogImageRecipe\BoundedJpeg;
use CatalogImageRecipe\InspectionOutcome;
use CatalogImageRecipe\RejectedJpeg;

/** @return array<string, string> */
function catalogJpegSeeds(): array
{
    $json = file_get_contents(__DIR__ . '/jpeg-fixtures.json');
    if (!is_string($json)) { throw new RuntimeException('Missing JPEG seeds.'); }
    $rows = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($rows) || count($rows) !== 16) { throw new RuntimeException('Invalid JPEG seeds.'); }
    $seeds = [];
    foreach ($rows as $name => $row) {
        if (!is_string($name) || !is_array($row) || !isset($row['base64'], $row['sha256'])
            || !is_string($row['base64']) || !is_string($row['sha256'])) { throw new RuntimeException('Invalid JPEG seed.'); }
        $bytes = base64_decode($row['base64'], true);
        if (!is_string($bytes) || hash('sha256', $bytes) !== $row['sha256']) { throw new RuntimeException('JPEG seed digest mismatch.'); }
        $seeds[$name] = $bytes;
    }
    return $seeds;
}

function catalogJpegSegment(int $marker, string $body): string
{
    return "\xff" . chr($marker) . pack('n', strlen($body) + 2) . $body;
}

function catalogJpegAlter(string $bytes, int $marker, int $relative, int $value): string
{
    $offset = strpos($bytes, "\xff" . chr($marker));
    if ($offset === false) { throw new LogicException('Missing fixture marker.'); }
    $bytes[$offset + 4 + $relative] = chr($value);
    return $bytes;
}

/** @return list<array{string, string, InspectionOutcome}> */
function catalogJpegCases(): array
{
    $seeds = catalogJpegSeeds();
    $base = $seeds['sampling420'];
    $appEnd = 20;
    $scan = strpos($base, "\xff\xda");
    $quant = strpos($base, "\xff\xdb");
    $huffman = strpos($base, "\xff\xc4");
    if ($scan === false || $quant === false || $huffman === false) { throw new LogicException('Missing seed structure.'); }
    $entropy = $scan + 14;
    $cases = [];
    foreach (['minimum', 'sampling444', 'sampling422', 'sampling420', 'maximum', 'optimized', 'quality100'] as $name) {
        $cases[] = [$name, $seeds[$name], InspectionOutcome::Accepted];
    }
    $cases[] = ['jfif102', catalogJpegAlter($base, 0xe0, 6, 2), InspectionOutcome::Accepted];
    $cases[] = ['density', catalogJpegAlter($base, 0xe0, 7, 2), InspectionOutcome::Accepted];
    foreach (['progressive', 'grayscale', 'rgb', 'arithmetic', 'sampling440', 'sampling411', 'restart', 'wide', 'tall'] as $name) {
        $cases[] = [$name, $seeds[$name], InspectionOutcome::Rejected];
    }
    $rejected = [
        'zero_width' => catalogJpegAlter(catalogJpegAlter($base, 0xc0, 3, 0), 0xc0, 4, 0),
        'zero_height' => catalogJpegAlter(catalogJpegAlter($base, 0xc0, 1, 0), 0xc0, 2, 0),
        'precision12' => catalogJpegAlter($base, 0xc0, 0, 12),
        'four_components' => catalogJpegAlter($base, 0xc0, 5, 4),
        'component_order' => catalogJpegAlter($base, 0xc0, 6, 2),
        'scan_order' => catalogJpegAlter($base, 0xda, 1, 2),
        'spectral_selection' => catalogJpegAlter($base, 0xda, 8, 62),
        'successive_approximation' => catalogJpegAlter($base, 0xda, 9, 1),
        'exif' => substr($base, 0, $appEnd) . catalogJpegSegment(0xe1, "Exif\0\0private") . substr($base, $appEnd),
        'icc' => substr($base, 0, $appEnd) . catalogJpegSegment(0xe2, "ICC_PROFILE\0private") . substr($base, $appEnd),
        'xmp' => substr($base, 0, $appEnd) . catalogJpegSegment(0xe1, 'http://ns.adobe.com/xap/1.0/') . substr($base, $appEnd),
        'comment' => substr($base, 0, $appEnd) . catalogJpegSegment(0xfe, 'private') . substr($base, $appEnd),
        'thumbnail' => catalogJpegAlter($base, 0xe0, 12, 1),
        'jfif_version' => catalogJpegAlter($base, 0xe0, 6, 3),
        'density_unit' => catalogJpegAlter($base, 0xe0, 7, 3),
        'zero_density' => catalogJpegAlter($base, 0xe0, 9, 0),
        'no_jfif' => substr($base, 0, 2) . substr($base, $appEnd),
        'duplicate_jfif' => substr($base, 0, $appEnd) . substr($base, 2),
        'leading_bytes' => 'x' . $base,
        'trailing_bytes' => $base . 'payload',
        'concatenated' => $base . $base,
        'no_eoi' => substr($base, 0, -2),
        'truncated_header' => substr($base, 0, 12),
        'short_length' => substr_replace($base, "\0\x01", 4, 2),
        'long_length' => substr_replace($base, "\xff\xff", 4, 2),
        'marker_padding' => substr_replace($base, "\xff", $appEnd, 0),
        'entropy_marker' => substr_replace($base, "\xff\xd0", $entropy + 1, 0),
        'empty_entropy' => substr($base, 0, $entropy) . "\xff\xd9",
        'zero_quantization' => catalogJpegAlter($base, 0xdb, 1, 0),
        'wide_quantization' => catalogJpegAlter($base, 0xdb, 0, 16),
        'missing_quantization' => catalogJpegAlter($base, 0xc0, 8, 3),
        'duplicate_quantization' => substr_replace($base, substr($base, $quant, 69), $quant, 0),
        'quantization_after_frame' => substr_replace($base, substr($base, $quant, 69), $huffman, 0),
        'duplicate_huffman' => substr_replace($base, substr($base, $huffman, 33), $huffman, 0),
        'huffman_before_frame' => substr_replace($base, substr($base, $huffman, 33), $quant, 0),
        'invalid_huffman_selector' => catalogJpegAlter($base, 0xc4, 0, 32),
        'huffman_symbol_overflow' => catalogJpegAlter($base, 0xc4, 1, 255),
        'missing_huffman' => catalogJpegAlter($base, 0xda, 2, 0x33),
        'no_sos' => substr($base, 0, $scan) . "\xff\xd9",
        'byte_limit' => $base . str_repeat("\0", 1_048_577 - strlen($base)),
        'png' => catalogPng(), 'empty' => '',
    ];
    foreach ($rejected as $name => $bytes) { $cases[] = [$name, $bytes, InspectionOutcome::Rejected]; }
    // These envelopes fit the policy but only the decoder can reject their entropy.
    $cases[] = ['truncated_entropy', substr($base, 0, $entropy + 1) . "\xff\xd9", InspectionOutcome::ScannerError];
    $cases[] = ['corrupt_entropy', substr($base, 0, $entropy) . "\0\0\xff\xd9", InspectionOutcome::ScannerError];
    return $cases;
}

function verifyJpegParser(): void
{
    foreach (catalogJpegCases() as [$name, $bytes, $outcome]) {
        try { $parsed = BoundedJpeg::fromBytes($bytes); }
        catch (RejectedJpeg) {
            if ($outcome !== InspectionOutcome::Rejected) { throw new LogicException('JPEG envelope rejected ' . $name); }
            continue;
        }
        if ($outcome === InspectionOutcome::Rejected) { throw new LogicException('JPEG envelope accepted ' . $name); }
        requireCatalogProof($parsed->width >= 1 && $parsed->width <= 1024 && $parsed->height >= 1 && $parsed->height <= 1024);
        if ($name === 'minimum') { requireCatalogProof($parsed->width === 1 && $parsed->height === 1); }
        if ($name === 'maximum') { requireCatalogProof($parsed->width === 1024 && $parsed->height === 1024); }
    }
}
