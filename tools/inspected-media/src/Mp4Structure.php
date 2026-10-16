<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

final readonly class Mp4Box
{
    private function __construct(public string $type, public string $body, public int $bodyOffset) {}

    /** @return list<self> */
    public static function children(string $bytes, int $base = 0): array
    {
        $boxes = []; $offset = 0; $length = strlen($bytes);
        while ($offset < $length) {
            if (count($boxes) >= 32 || $offset + 8 > $length) { throw new RejectedMp4('Invalid box structure.'); }
            $size = self::uint($bytes, $offset);
            if ($size < 8 || $size > $length - $offset) { throw new RejectedMp4('Invalid box size.'); }
            $boxes[] = new self(substr($bytes, $offset + 4, 4), substr($bytes, $offset + 8, $size - 8), $base + $offset + 8);
            $offset += $size;
        }
        return $boxes;
    }

    public static function uint(string $bytes, int $offset, int $length = 4): int
    {
        if ($offset < 0 || ($length !== 2 && $length !== 4) || $offset + $length > strlen($bytes)) { throw new RejectedMp4('Truncated integer.'); }
        $value = 0;
        for ($i = 0; $i < $length; $i++) { $value = ($value << 8) | ord($bytes[$offset + $i]); }
        return $value;
    }
}

final class Mp4Bits
{
    private int $position = 0;
    private function __construct(private readonly string $bytes) {}

    public static function fromBytes(string $bytes): self
    {
        if ($bytes === '' || strlen($bytes) > 1024) { throw new RejectedMp4('Invalid parameter set.'); }
        $rbsp = ''; $zeros = 0; $length = strlen($bytes);
        for ($i = 0; $i < $length; $i++) {
            $value = ord($bytes[$i]);
            if ($zeros === 2 && $value === 3) {
                if ($i + 1 >= $length || ord($bytes[$i + 1]) > 3) { throw new RejectedMp4('Invalid escape.'); }
                $zeros = 0; continue;
            }
            $rbsp .= $bytes[$i]; $zeros = $value === 0 ? $zeros + 1 : 0;
        }
        return new self($rbsp);
    }

    public function bit(): int
    {
        if ($this->position >= strlen($this->bytes) * 8) { throw new RejectedMp4('Truncated parameter set.'); }
        $value = (ord($this->bytes[intdiv($this->position, 8)]) >> (7 - $this->position % 8)) & 1;
        $this->position++;
        return $value;
    }

    public function unsigned(int $maximum): int
    {
        $zeros = 0;
        while ($this->bit() === 0) {
            $zeros++;
            if ($zeros > 16) { throw new RejectedMp4('Parameter set integer exceeds bounds.'); }
        }
        $value = 1;
        for ($i = 0; $i < $zeros; $i++) { $value = ($value << 1) | $this->bit(); }
        $value--;
        if ($value > $maximum) { throw new RejectedMp4('Parameter set value exceeds bounds.'); }
        return $value;
    }
}
