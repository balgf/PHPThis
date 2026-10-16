<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

final readonly class BoundedJpeg
{
    private function __construct(public int $width, public int $height) {}

    public static function fromBytes(string $bytes): self
    {
        $size = strlen($bytes);
        if ($size > 1_048_576 || substr($bytes, 0, 2) !== "\xff\xd8") { self::reject(); }
        $offset = 2;
        $frame = null;
        $quantization = [];
        $huffman = [];
        for ($index = 0; $index < 32; $index++) {
            if ($size - $offset < 4 || $bytes[$offset] !== "\xff") { self::reject(); }
            $marker = ord($bytes[$offset + 1]);
            $length = ord($bytes[$offset + 2]) * 256 + ord($bytes[$offset + 3]);
            if ($length < 2 || $length > $size - $offset - 2) { self::reject(); }
            $body = substr($bytes, $offset + 4, $length - 2);
            $offset += $length + 2;
            if ($index === 0) {
                if ($marker !== 0xe0 || strlen($body) !== 14 || substr($body, 0, 5) !== "JFIF\0"
                    || !in_array(substr($body, 5, 2), ["\x01\x01", "\x01\x02"], true)
                    || ord($body[7]) > 2 || substr($body, 8, 2) === "\0\0"
                    || substr($body, 10, 2) === "\0\0" || substr($body, 12) !== "\0\0") { self::reject(); }
            } elseif ($marker === 0xdb && $frame === null) {
                $quantization = self::quantization($body, $quantization);
            } elseif ($marker === 0xc0 && $frame === null) {
                $frame = self::frame($body, $quantization);
            } elseif ($marker === 0xc4 && $frame !== null) {
                $huffman = self::huffman($body, $huffman);
            } elseif ($marker === 0xda && $frame !== null) {
                self::scan($body, $huffman);
                $start = $offset;
                while ($offset < $size) {
                    if ($bytes[$offset] !== "\xff") { $offset++; continue; }
                    if (substr($bytes, $offset, 2) === "\xff\0") { $offset += 2; continue; }
                    if ($offset > $start && $offset === $size - 2 && substr($bytes, $offset) === "\xff\xd9") {
                        // Full entropy validity is checked by the isolated strict decoder.
                        return $frame;
                    }
                    self::reject();
                }
                self::reject();
            } else { self::reject(); }
        }
        self::reject();
    }

    /** @param array<int, true> $tables
     * @return array<int, true>
     */
    private static function quantization(string $body, array $tables): array
    {
        if ($body === '') { self::reject(); }
        for ($offset = 0; $offset < strlen($body); $offset += 65) {
            $selector = ord($body[$offset]);
            if ($selector > 3 || isset($tables[$selector]) || strlen($body) - $offset < 65
                || str_contains(substr($body, $offset + 1, 64), "\0")) { self::reject(); }
            $tables[$selector] = true;
        }
        return $tables;
    }

    /** @param array<int, true> $tables */
    private static function frame(string $body, array $tables): self
    {
        if (strlen($body) !== 15 || ord($body[0]) !== 8 || ord($body[5]) !== 3
            || ord($body[6]) !== 1 || ord($body[9]) !== 2 || ord($body[12]) !== 3
            || !in_array(ord($body[7]), [0x11, 0x21, 0x22], true)
            || ord($body[10]) !== 0x11 || ord($body[13]) !== 0x11) { self::reject(); }
        $height = ord($body[1]) * 256 + ord($body[2]);
        $width = ord($body[3]) * 256 + ord($body[4]);
        if ($width < 1 || $width > 1024 || $height < 1 || $height > 1024
            || !isset($tables[ord($body[8])], $tables[ord($body[11])], $tables[ord($body[14])])) { self::reject(); }
        return new self($width, $height);
    }

    /** @param array<int, true> $tables
     * @return array<int, true>
     */
    private static function huffman(string $body, array $tables): array
    {
        if ($body === '') { self::reject(); }
        $offset = 0;
        while ($offset < strlen($body)) {
            if (strlen($body) - $offset < 17) { self::reject(); }
            $selector = ord($body[$offset]);
            if (!in_array($selector, [0, 1, 2, 3, 16, 17, 18, 19], true) || isset($tables[$selector])) { self::reject(); }
            $symbols = 0;
            for ($length = 1; $length <= 16; $length++) { $symbols += ord($body[$offset + $length]); }
            if ($symbols < 1 || $symbols > 256 || $symbols > strlen($body) - $offset - 17) { self::reject(); }
            $tables[$selector] = true;
            $offset += 17 + $symbols;
        }
        return $tables;
    }

    /** @param array<int, true> $tables */
    private static function scan(string $body, array $tables): void
    {
        if (strlen($body) !== 10 || ord($body[0]) !== 3 || ord($body[1]) !== 1
            || ord($body[3]) !== 2 || ord($body[5]) !== 3 || substr($body, 7) !== "\0\x3f\0") { self::reject(); }
        foreach ([2, 4, 6] as $offset) {
            $selector = ord($body[$offset]);
            if (!isset($tables[$selector >> 4], $tables[16 + ($selector & 15)])) { self::reject(); }
        }
    }

    private static function reject(): never { throw new RejectedJpeg('JPEG rejected.'); }
}
