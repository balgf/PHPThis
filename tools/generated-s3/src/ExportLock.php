<?php

declare(strict_types=1);

namespace GeneratedS3Recipe;

final readonly class ExportRoot
{
    private function __construct(public string $path) {}

    public static function fromInput(mixed $path): self
    {
        if (!is_string($path) || strlen($path) > 2048 || !str_starts_with($path, '/')
            || str_ends_with($path, '/') || preg_match('~(?:[\x00-\x20\x7f]|//|/\.\.?/)~', $path) === 1) {
            throw new \InvalidArgumentException('Invalid export root.');
        }
        return new self($path);
    }

    public function verify(): void
    {
        if (@realpath($this->path) !== $this->path) { throw new ExportUnavailable('Export root unavailable.'); }
        $this->mode($this->path, 0040000, 0700);
        $this->mode($this->path . '/inventory.sqlite', 0100000, 0600);
        $this->mode($this->path . '/exports.lock', 0100000, 0600);
    }

    private function mode(string $path, int $type, int $mode): void
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if (!is_array($stat) || ($stat['mode'] & 0170000) !== $type || ($stat['mode'] & 0777) !== $mode
            || $stat['uid'] !== posix_geteuid() || $stat['nlink'] !== 1 && $type === 0100000) {
            throw new ExportUnavailable('Export storage unavailable.');
        }
    }
}

final class ExportLock
{
    /** @var resource|null */
    private mixed $handle = null;
    private bool $exclusive = false;
    public function __construct(private readonly ExportRoot $root) {}
    public function acquire(bool $exclusive): void
    {
        if ($this->handle !== null) { throw new \LogicException('Export lock already held.'); }
        $this->root->verify();
        $handle = @fopen($this->root->path . '/exports.lock', 'r+');
        if ($handle === false) { throw new ExportUnavailable('Export lock unavailable.'); }
        if (!flock($handle, ($exclusive ? LOCK_EX : LOCK_SH) | LOCK_NB)) {
            fclose($handle);
            throw new ExportUnavailable('Export operation busy.');
        }
        $this->handle = $handle; $this->exclusive = $exclusive;
    }
    public function requireHeld(bool $exclusive): void
    {
        if ($this->handle === null || $exclusive && !$this->exclusive) { throw new \LogicException('Export lock required.'); }
    }
    public function release(): void
    {
        if ($this->handle !== null) {
            flock($this->handle, LOCK_UN); fclose($this->handle);
            $this->handle = null; $this->exclusive = false;
        }
    }
}
