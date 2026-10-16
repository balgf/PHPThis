<?php

declare(strict_types=1);

namespace MetadataS3Recipe;

final readonly class DocumentRoot
{
    private function __construct(public string $path) {}

    public static function fromInput(mixed $path): self
    {
        if (!is_string($path) || strlen($path) > 2048 || !str_starts_with($path, '/')
            || str_ends_with($path, '/') || preg_match('~(?:[\x00-\x20\x7f]|//|/\.\.?/)~', $path) === 1) {
            throw new \InvalidArgumentException('Invalid document root.');
        }
        return new self($path);
    }

    public function verify(): void
    {
        if (@realpath($this->path) !== $this->path) { throw new DocumentUnavailable('Document root unavailable.'); }
        $this->mode($this->path, 0040000, 0700);
        $this->mode($this->path . '/documents.sqlite', 0100000, 0600);
        $this->mode($this->path . '/documents.lock', 0100000, 0600);
    }

    private function mode(string $path, int $type, int $mode): void
    {
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if (!is_array($stat) || ($stat['mode'] & 0170000) !== $type || ($stat['mode'] & 0777) !== $mode
            || $stat['uid'] !== posix_geteuid() || $stat['nlink'] !== 1 && $type === 0100000) {
            throw new DocumentUnavailable('Document storage unavailable.');
        }
    }
}

final class DocumentLock
{
    /** @var resource|null */
    private mixed $handle = null;
    private bool $exclusive = false;
    public function __construct(private readonly DocumentRoot $root) {}
    public function acquire(bool $exclusive): void
    {
        if ($this->handle !== null) { throw new \LogicException('Document lock already held.'); }
        $this->root->verify();
        $handle = @fopen($this->root->path . '/documents.lock', 'r+');
        if ($handle === false) { throw new DocumentUnavailable('Document lock unavailable.'); }
        if (!flock($handle, ($exclusive ? LOCK_EX : LOCK_SH) | LOCK_NB)) {
            fclose($handle);
            throw new CapacityUnavailable('Document operation busy.');
        }
        $this->handle = $handle; $this->exclusive = $exclusive;
    }
    public function requireHeld(bool $exclusive): void
    {
        if ($this->handle === null || $exclusive && !$this->exclusive) { throw new \LogicException('Document lock required.'); }
    }
    public function release(): void
    {
        if ($this->handle !== null) {
            flock($this->handle, LOCK_UN); fclose($this->handle);
            $this->handle = null; $this->exclusive = false;
        }
    }
}
