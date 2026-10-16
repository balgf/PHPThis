<?php

declare(strict_types=1);

use CatalogImageRecipe\BoundedMp4;
use CatalogImageRecipe\CatalogUnavailable;
use CatalogImageRecipe\InspectionOutcome;
use CatalogImageRecipe\RejectedMp4;

/** @return array<string, string> */
function catalogMp4Seeds(): array
{
    $json = file_get_contents(__DIR__ . '/mp4-fixtures.json');
    if (!is_string($json)) { throw new RuntimeException('Missing MP4 seeds.'); }
    $rows = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($rows) || count($rows) !== 14) { throw new RuntimeException('Invalid MP4 seeds.'); }
    $seeds = [];
    foreach ($rows as $name => $row) {
        if (!is_string($name) || !is_array($row) || !isset($row['base64'], $row['sha256'])
            || !is_string($row['base64']) || !is_string($row['sha256'])) { throw new RuntimeException('Invalid MP4 seed.'); }
        $bytes = base64_decode($row['base64'], true);
        if (!is_string($bytes) || hash('sha256', $bytes) !== $row['sha256']) { throw new RuntimeException('MP4 seed digest mismatch.'); }
        $seeds[$name] = $bytes;
    }
    return $seeds;
}

function catalogMp4Alter(string $bytes, string $box, int $relative, string $value): string
{
    // Known synthetic seed editing only; never use marker search in the actual parser.
    $start = $box === 'avc1' ? strpos($bytes, 'stsd') : 0;
    if ($start === false) { throw new LogicException('Missing sample description.'); }
    $offset = strpos($bytes, $box, $start);
    if ($offset === false) { throw new LogicException('Missing fixture box.'); }
    return substr_replace($bytes, $value, $offset + 4 + $relative, strlen($value));
}

/** @return list<array{string, string, InspectionOutcome}> */
function catalogMp4Cases(): array
{
    $seeds = catalogMp4Seeds(); $base = $seeds['valid-320x240']; $cases = [];
    foreach (['valid-320x240', 'minimum-frame', 'max-dimensions-duration-frames', '24fps-single-frame', '25fps'] as $name) {
        $cases[] = [$name, $seeds[$name], InspectionOutcome::Accepted];
    }
    foreach (['duration-excess', 'frame-rate-excess', 'width-excess', 'height-excess', 'high-profile', 'fragmented', 'title-metadata', 'b-frames', 'seed'] as $name) {
        $cases[] = [$name, $seeds[$name], InspectionOutcome::Rejected];
    }
    $bad = [
        'empty' => '', 'oversized' => str_repeat('x', 1_048_577), 'truncated' => substr($base, 0, -1),
        'trailing' => $base . 'junk', 'zero-box' => substr_replace($base, pack('N', 0), 0, 4),
        'large-box' => substr_replace($base, pack('N', 1), 0, 4), 'wrong-brand' => catalogMp4Alter($base, 'ftyp', 0, 'qt  '),
        'unknown-box' => $base . pack('Na4', 12, 'uuid') . '1234',
        'external-reference' => catalogMp4Alter($base, 'url ', 3, "\0"),
        'audio-track' => catalogMp4Alter($base, 'hdlr', 8, 'soun'),
        'high-profile-declaration' => catalogMp4Alter($base, 'avcC', 1, "\x64"),
        'second-sps' => catalogMp4Alter($base, 'avcC', 5, "\xe2"),
        'second-description' => catalogMp4Alter($base, 'stsd', 4, pack('N', 2)),
        'duration-mismatch' => catalogMp4Alter($base, 'mvhd', 16, pack('N', 2000)),
        'extra-timing-entry' => catalogMp4Alter($base, 'stts', 4, pack('N', 2)),
        'zero-delta' => catalogMp4Alter($base, 'stts', 12, pack('N', 0)),
        'sample-count-excess' => catalogMp4Alter($base, 'stts', 8, pack('N', 301)),
        'outside-offset' => catalogMp4Alter($base, 'stco', 8, pack('N', 0)),
        'size-count-mismatch' => catalogMp4Alter($base, 'stsz', 8, pack('N', 29)),
        'sample-size-excess' => catalogMp4Alter($base, 'stsz', 12, pack('N', 262145)),
        'rotation' => catalogMp4Alter($base, 'tkhd', 40, pack('N', 0)),
        'non-square-pixels' => catalogMp4Alter($base, 'pasp', 0, pack('N', 2)),
        'container-crop' => catalogMp4Alter(catalogMp4Alter($base, 'avc1', 24, pack('n', 318)), 'tkhd', 76, pack('N', 318 << 16)),
        'hidden-sps-width' => catalogMp4Alter(catalogMp4Alter($seeds['width-excess'], 'avc1', 24, pack('n', 640)), 'tkhd', 76, pack('N', 640 << 16)),
        'hidden-sps-height' => catalogMp4Alter(catalogMp4Alter($seeds['height-excess'], 'avc1', 26, pack('n', 480)), 'tkhd', 80, pack('N', 480 << 16)),
        'sps-range' => catalogMp4Alter($base, 'avcC', 12, str_repeat("\0", 8)),
        'png' => catalogPng(), 'jpeg' => catalogJpegSeeds()['sampling420'],
    ];
    foreach ($bad as $name => $bytes) { $cases[] = [$name, $bytes, InspectionOutcome::Rejected]; }
    $mdat = strpos($base, 'mdat');
    if ($mdat === false) { throw new LogicException('Missing fixture media.'); }
    $length = (ord($base[$mdat + 4]) << 24) | (ord($base[$mdat + 5]) << 16) | (ord($base[$mdat + 6]) << 8) | ord($base[$mdat + 7]);
    $cases[] = ['corrupt-frame', substr_replace($base, str_repeat("\0", $length - 1), $mdat + 9, $length - 1), InspectionOutcome::ScannerError];
    return $cases;
}

function verifyMp4Parser(): void
{
    foreach (catalogMp4Cases() as [$name, $bytes, $outcome]) {
        try { $parsed = BoundedMp4::fromBytes($bytes); }
        catch (RejectedMp4) {
            if ($outcome !== InspectionOutcome::Rejected) { throw new LogicException('MP4 envelope rejected ' . $name); }
            continue;
        }
        if ($outcome === InspectionOutcome::Rejected) { throw new LogicException('MP4 envelope accepted ' . $name); }
        requireCatalogProof($parsed->frames >= 1 && $parsed->frames <= 300 && $parsed->width <= 640 && $parsed->height <= 480);
        if ($name === 'max-dimensions-duration-frames') {
            requireCatalogProof($parsed->frames === 300 && $parsed->width === 640 && $parsed->height === 480);
        }
    }
    $mp4 = BoundedMp4::fromBytes(catalogMp4Seeds()['minimum-frame']);
    $stream = ['index' => 0, 'codec_name' => 'h264', 'profile' => 'Constrained Baseline', 'codec_type' => 'video',
        'codec_tag_string' => 'avc1', 'width' => 2, 'height' => 2, 'has_b_frames' => 0, 'pix_fmt' => 'yuv420p',
        'level' => 30, 'time_base' => '1/1', 'duration_ts' => 1, 'nb_frames' => '1'];
    $frame = ['stream_index' => 0, 'pts' => 0, 'width' => 2, 'height' => 2, 'pix_fmt' => 'yuv420p',
        'pict_type' => 'I', 'interlaced_frame' => 0, 'repeat_pict' => 0];
    $report = ['frames' => [$frame], 'streams' => [$stream], 'programs' => [], 'stream_groups' => []];
    $mp4->verifyReport(json_encode($report, JSON_THROW_ON_ERROR));
    $badReports = ['', '{', str_repeat('x', 65_537), 'null', '[]'];
    foreach (['frames' => [], 'streams' => [$stream, $stream], 'programs' => [['id' => 1]]] as $key => $value) {
        $changed = $report; $changed[$key] = $value; $badReports[] = json_encode($changed, JSON_THROW_ON_ERROR);
    }
    foreach (['width' => 4, 'pts' => 1, 'pix_fmt' => 'yuv420p10le', 'pict_type' => 'B', 'interlaced_frame' => 1,
        'repeat_pict' => 1, 'side_data_list' => [['side_data_type' => 'private']], 'stream_index' => '0'] as $key => $value) {
        $changed = $report; $changed['frames'][0][$key] = $value; $badReports[] = json_encode($changed, JSON_THROW_ON_ERROR);
    }
    foreach ($badReports as $json) {
        try { $mp4->verifyReport($json); throw new LogicException('Invalid decoder report accepted.'); }
        catch (CatalogUnavailable) {}
    }
}
