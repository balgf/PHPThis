<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

final readonly class CatalogRoot
{
    private function __construct(public string $path) {}

    public static function fromInput(mixed $path): self
    {
        if (!is_string($path) || strlen($path) > 2048 || !str_starts_with($path, '/')
            || str_ends_with($path, '/') || preg_match('~(?:[\x00-\x20\x7f]|//|/\.\.?/)~', $path) === 1) {
            throw new \InvalidArgumentException('Invalid catalog root.');
        }
        return new self($path);
    }

    public function verify(): void
    {
        if (@realpath($this->path) !== $this->path) {
            throw new CatalogUnavailable('Catalog root unavailable.');
        }
        $this->requireMode($this->path, 0040000, 0700);
        $this->requireMode($this->path . '/objects', 0040000, 0700);
        $this->requireMode($this->path . '/catalog.sqlite', 0100000, 0600);
        $this->requireMode($this->path . '/catalog.lock', 0100000, 0600);
    }

    public function objectDirectory(ImageId $id): string { return $this->path . '/objects/' . $id->value; }
    public function contentPath(ImageId $id): string { return $this->objectDirectory($id) . '/content'; }

    public function requireMode(string $path, int $type, int $mode): void
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if (!is_array($stat) || ($stat['mode'] & 0170000) !== $type || ($stat['mode'] & 0777) !== $mode
            || $stat['uid'] !== posix_geteuid()) {
            throw new CatalogUnavailable('Catalog storage unavailable.');
        }
    }

    public function bytes(ImageRecord $record): string
    {
        $this->requireMode($this->objectDirectory($record->id), 0040000, 0700);
        $path = $this->contentPath($record->id);
        $this->requireMode($path, 0100000, 0600);
        $bytes = @file_get_contents($path, false, null, 0, 1_048_577);
        if (!is_string($bytes) || strlen($bytes) !== $record->bytes || $record->digest === null
            || !hash_equals($record->digest, hash('sha256', $bytes))) {
            throw new CatalogUnavailable('Catalog content integrity failed.');
        }
        return $bytes;
    }

    public function remove(ImageId $id): void
    {
        $directory = $this->objectDirectory($id);
        clearstatcache();
        if (@lstat($directory) === false) { return; }
        $this->requireMode($directory, 0040000, 0700);
        $path = $this->contentPath($id);
        if (@lstat($path) !== false) {
            $this->requireMode($path, 0100000, 0600);
            if (!@unlink($path)) { throw new CatalogUnavailable('Catalog cleanup failed.'); }
        }
        if (!@rmdir($directory)) { throw new CatalogUnavailable('Catalog cleanup incomplete.'); }
    }
}
