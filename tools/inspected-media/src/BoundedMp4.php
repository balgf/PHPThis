<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

final readonly class BoundedMp4
{
    private const string MATRIX = "\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x40\x00\x00\x00";
    private const string EMPTY_METADATA = "\x00\x00\x00\x35\x6d\x65\x74\x61\x00\x00\x00\x00\x00\x00\x00\x21\x68\x64\x6c\x72\x00\x00\x00\x00\x00\x00\x00\x00\x6d\x64\x69\x72\x61\x70\x70\x6c\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x08\x69\x6c\x73\x74";

    private function __construct(public int $width, public int $height, public int $frames, public int $delta, public int $timescale) {}

    public static function fromBytes(string $bytes): self
    {
        self::require($bytes !== '' && strlen($bytes) <= 1_048_576);
        $top = Mp4Box::children($bytes);
        if (count($top) === 4) {
            self::require($top[2]->type === 'free' && $top[2]->body === '');
            array_splice($top, 2, 1);
        }
        self::shape($top, ['ftyp', 'moov', 'mdat']);
        self::require($top[0]->body === "isom\0\0\x02\0isomiso2avc1mp41");
        $movie = Mp4Box::children($top[1]->body, $top[1]->bodyOffset);
        if (count($movie) === 3) {
            self::require($movie[2]->type === 'udta' && $movie[2]->body === self::EMPTY_METADATA);
            array_pop($movie);
        }
        self::shape($movie, ['mvhd', 'trak']);
        $mvhd = $movie[0]->body; self::full($mvhd, 100);
        self::require(substr($mvhd, 4, 8) === str_repeat("\0", 8) && Mp4Box::uint($mvhd, 12) === 1000
            && substr($mvhd, 36, 36) === self::MATRIX
            && substr($mvhd, 20, 16) === "\0\x01\0\0\x01\0\0\0\0\0\0\0\0\0\0\0"
            && substr($mvhd, 72, 24) === str_repeat("\0", 24) && Mp4Box::uint($mvhd, 96) === 2);
        $track = self::parts($movie[1], ['tkhd', 'mdia']);
        $tkhd = $track[0]->body;
        self::require(strlen($tkhd) === 84 && substr($tkhd, 0, 4) === "\0\0\0\x03"
            && substr($tkhd, 4, 8) === str_repeat("\0", 8) && Mp4Box::uint($tkhd, 12) === 1
            && substr($tkhd, 16, 4) === str_repeat("\0", 4) && substr($tkhd, 24, 16) === str_repeat("\0", 16)
            && substr($tkhd, 40, 36) === self::MATRIX);
        $media = self::parts($track[1], ['mdhd', 'hdlr', 'minf']);
        $mdhd = $media[0]->body; self::full($mdhd, 24);
        $scale = Mp4Box::uint($mdhd, 12); $duration = Mp4Box::uint($mdhd, 16);
        self::require(substr($mdhd, 4, 8) === str_repeat("\0", 8) && substr($mdhd, 20) === "\x55\xc4\0\0"
            && $scale >= 1 && $scale <= 90_000 && $duration >= 1 && $duration <= $scale * 10);
        self::require($media[1]->body === str_repeat("\0", 8) . 'vide' . str_repeat("\0", 12) . "VideoHandler\0");
        $minf = self::parts($media[2], ['vmhd', 'dinf', 'stbl']);
        self::require($minf[0]->body === "\0\0\0\x01" . str_repeat("\0", 8));
        $dref = self::parts($minf[1], ['dref']);
        self::require($dref[0]->body === "\0\0\0\0\0\0\0\x01\0\0\0\x0curl \0\0\0\x01");
        $table = Mp4Box::children($minf[2]->body, $minf[2]->bodyOffset);
        $types = array_map(static fn (Mp4Box $box): string => $box->type, $table);
        $hasSync = $types === ['stsd', 'stts', 'stss', 'stsc', 'stsz', 'stco'];
        self::require($hasSync || $types === ['stsd', 'stts', 'stsc', 'stsz', 'stco']);
        $stsd = $table[0]->body; self::full($stsd);
        self::require(Mp4Box::uint($stsd, 4) === 1);
        $descriptions = Mp4Box::children(substr($stsd, 8)); self::shape($descriptions, ['avc1']);
        $avc1 = $descriptions[0]->body;
        self::require(strlen($avc1) >= 78);
        $width = Mp4Box::uint($avc1, 24, 2); $height = Mp4Box::uint($avc1, 26, 2);
        self::require($width >= 2 && $width <= 640 && $height >= 2 && $height <= 480 && $width % 2 === 0 && $height % 2 === 0
            && substr($avc1, 0, 24) === str_repeat("\0", 6) . "\0\x01" . str_repeat("\0", 16)
            && substr($avc1, 28, 14) === "\0\x48\0\0\0\x48\0\0\0\0\0\0\0\x01"
            && in_array(substr($avc1, 42, 32), [str_repeat("\0", 32), "\x0cLavc libx264" . str_repeat("\0", 19)], true)
            && substr($avc1, 74, 4) === "\0\x18\xff\xff"
            && Mp4Box::uint($tkhd, 76) === $width << 16 && Mp4Box::uint($tkhd, 80) === $height << 16);
        $config = Mp4Box::children(substr($avc1, 78)); self::shape($config, ['avcC', 'pasp', 'btrt']);
        self::require($config[1]->body === "\0\0\0\x01\0\0\0\x01" && strlen($config[2]->body) === 12);
        self::avc($config[0]->body, $width, $height);
        $stts = $table[1]->body; self::full($stts, 16);
        $frames = Mp4Box::uint($stts, 8); $delta = Mp4Box::uint($stts, 12);
        self::require(Mp4Box::uint($stts, 4) === 1 && $frames >= 1 && $frames <= 300 && $delta >= 1 && $delta <= $scale
            && $scale % $delta === 0 && intdiv($scale, $delta) <= 30 && $duration === $frames * $delta);
        $rounded = intdiv($duration * 1000 + $scale - 1, $scale);
        self::require(Mp4Box::uint($tkhd, 20) === $rounded && Mp4Box::uint($mvhd, 16) === $rounded);
        $sync = range(1, $frames);
        if ($hasSync) {
            $stss = $table[2]->body; self::full($stss);
            $count = Mp4Box::uint($stss, 4);
            self::require($count >= 1 && $count <= $frames && strlen($stss) === 8 + $count * 4);
            $sync = []; $previous = 0;
            for ($i = 0; $i < $count; $i++) {
                $value = Mp4Box::uint($stss, 8 + $i * 4);
                self::require($value > $previous && $value <= $frames);
                $sync[] = $value; $previous = $value;
            }
            self::require($sync[0] === 1);
        }
        $index = $hasSync ? 3 : 2;
        $stsc = $table[$index]->body; self::full($stsc, 20);
        self::require(substr($stsc, 4) === pack('NNNN', 1, 1, $frames, 1));
        $stsz = $table[$index + 1]->body; self::full($stsz);
        $common = Mp4Box::uint($stsz, 4);
        self::require(Mp4Box::uint($stsz, 8) === $frames && strlen($stsz) === ($common === 0 ? 12 + 4 * $frames : 12));
        $stco = $table[$index + 2]->body; self::full($stco, 12);
        self::require(Mp4Box::uint($stco, 4) === 1 && Mp4Box::uint($stco, 8) === $top[2]->bodyOffset);
        $payload = $top[2]->body; $offset = 0;
        for ($i = 0; $i < $frames; $i++) {
            $size = $common === 0 ? Mp4Box::uint($stsz, 12 + $i * 4) : $common;
            self::require($size >= 5 && $size <= 262_144 && $offset + $size <= strlen($payload));
            self::require(Mp4Box::uint($payload, $offset) === $size - 4 && in_array(ord($payload[$offset + 4]), [0x41, 0x61, 0x65], true));
            self::require(((ord($payload[$offset + 4]) & 31) === 5) === in_array($i + 1, $sync, true));
            $offset += $size;
        }
        self::require($offset === strlen($payload));
        return new self($width, $height, $frames, $delta, $scale);
    }

    private static function avc(string $config, int $width, int $height): void
    {
        self::require(strlen($config) >= 11 && strlen($config) <= 4096 && substr($config, 0, 6) === "\x01\x42\xc0\x1e\xff\xe1");
        $spsLength = Mp4Box::uint($config, 6, 2);
        self::require($spsLength >= 4 && $spsLength <= 1024 && strlen($config) >= 11 + $spsLength);
        $sps = substr($config, 8, $spsLength);
        self::require(substr($sps, 0, 4) === "\x67\x42\xc0\x1e" && ord($config[8 + $spsLength]) === 1);
        $ppsLength = Mp4Box::uint($config, 9 + $spsLength, 2);
        self::require($ppsLength >= 1 && $ppsLength <= 1024 && 11 + $spsLength + $ppsLength === strlen($config) && ord($config[11 + $spsLength]) === 0x68);
        $bits = Mp4Bits::fromBytes(substr($sps, 4));
        $sequence = $bits->unsigned(31); $bits->unsigned(12); $order = $bits->unsigned(2);
        self::require($order === 0 || $order === 2);
        if ($order === 0) { $bits->unsigned(12); }
        self::require($bits->unsigned(4) >= 1 && $bits->bit() === 0);
        $codedWidth = ($bits->unsigned(39) + 1) * 16; $codedHeight = ($bits->unsigned(29) + 1) * 16;
        self::require($bits->bit() === 1); $bits->bit();
        if ($bits->bit() === 1) {
            $left = $bits->unsigned(319); $right = $bits->unsigned(319); $top = $bits->unsigned(239); $bottom = $bits->unsigned(239);
            $codedWidth -= 2 * ($left + $right); $codedHeight -= 2 * ($top + $bottom);
        }
        // MOV can otherwise apply an additional container crop and hide disagreement.
        self::require($codedWidth === $width && $codedHeight === $height);
        $pps = Mp4Bits::fromBytes(substr($config, 12 + $spsLength));
        $pps->unsigned(255);
        self::require($pps->unsigned(31) === $sequence && $pps->bit() === 0);
        // Remaining bounded SPS/PPS fields and all slice entropy belong to the real decoder.
    }

    public function verifyReport(string $json): void
    {
        if ($json === '' || strlen($json) > 65_536) { throw new CatalogUnavailable('Invalid decoder report.'); }
        try { $report = json_decode($json, true, 8, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new CatalogUnavailable('Invalid decoder report.'); }
        if (!is_array($report) || ($report['programs'] ?? []) !== [] || ($report['stream_groups'] ?? []) !== []
            || !isset($report['streams'], $report['frames']) || !is_array($report['streams']) || !array_is_list($report['streams'])
            || count($report['streams']) !== 1 || !is_array($report['streams'][0]) || !is_array($report['frames'])
            || !array_is_list($report['frames']) || count($report['frames']) !== $this->frames) { throw new CatalogUnavailable('Incomplete decoder report.'); }
        $expected = ['index' => 0, 'codec_name' => 'h264', 'profile' => 'Constrained Baseline', 'codec_type' => 'video',
            'codec_tag_string' => 'avc1', 'width' => $this->width, 'height' => $this->height, 'has_b_frames' => 0,
            'pix_fmt' => 'yuv420p', 'level' => 30, 'time_base' => '1/' . $this->timescale,
            'duration_ts' => $this->frames * $this->delta, 'nb_frames' => (string) $this->frames];
        foreach ($expected as $key => $value) {
            if (($report['streams'][0][$key] ?? null) !== $value) { throw new CatalogUnavailable('Decoder stream disagreement.'); }
        }
        foreach ($report['frames'] as $i => $frame) {
            if (!is_array($frame) || !in_array($frame['pict_type'] ?? null, ['I', 'P'], true) || ($frame['side_data_list'] ?? []) !== []) {
                throw new CatalogUnavailable('Unsupported decoded frame.');
            }
            foreach (['stream_index' => 0, 'pts' => $i * $this->delta, 'width' => $this->width, 'height' => $this->height,
                'pix_fmt' => 'yuv420p', 'interlaced_frame' => 0, 'repeat_pict' => 0] as $key => $value) {
                if (($frame[$key] ?? null) !== $value) { throw new CatalogUnavailable('Decoder frame disagreement.'); }
            }
        }
    }

    private static function require(bool $condition): void
    {
        if (!$condition) { throw new RejectedMp4('MP4 outside the accepted profile.'); }
    }

    private static function full(string $body, ?int $length = null): void
    {
        self::require(substr($body, 0, 4) === "\0\0\0\0" && ($length === null || strlen($body) === $length));
    }

    /** @param list<Mp4Box> $boxes
     * @param list<string> $types
     */
    private static function shape(array $boxes, array $types): void
    {
        self::require(array_map(static fn (Mp4Box $box): string => $box->type, $boxes) === $types);
    }

    /** @param list<string> $types
     * @return list<Mp4Box>
     */
    private static function parts(Mp4Box $box, array $types): array
    {
        $children = Mp4Box::children($box->body, $box->bodyOffset); self::shape($children, $types);
        return $children;
    }
}
