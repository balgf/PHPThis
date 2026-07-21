<?php

declare(strict_types=1);

namespace Example\Migrations;

use LogicException;
use RuntimeException;

final class LocalMigrationLock
{
    /** @var resource|null */
    private $handle = null;

    public function __construct(private readonly string $path)
    {
    }

    public function acquire(): bool
    {
        if (is_resource($this->handle)) {
            throw new LogicException('The local migration lock is already held.');
        }

        if (is_link($this->path)) {
            throw new RuntimeException('The local migration lock path is unsafe.');
        }

        $handle = @fopen($this->path, 'c+b');

        if ($handle === false) {
            throw new RuntimeException('The local migration lock is unavailable.');
        }

        if (!@chmod($this->path, 0600)) {
            @fclose($handle);
            throw new RuntimeException('The local migration lock permissions are unavailable.');
        }

        if (!$this->openedPathMatchesHandle($handle)) {
            @fclose($handle);
            throw new RuntimeException('The local migration lock identity is unsafe.');
        }

        $wouldBlock = 0;

        if (!@flock($handle, LOCK_EX | LOCK_NB, $wouldBlock)) {
            $closed = @fclose($handle);

            if (!$closed) {
                throw new RuntimeException('The contended local migration lock could not be closed.');
            }

            if ($wouldBlock === 1) {
                return false;
            }

            throw new RuntimeException('The local migration lock could not be acquired.');
        }

        if (!$this->openedPathMatchesHandle($handle)) {
            @flock($handle, LOCK_UN);
            @fclose($handle);
            throw new RuntimeException('The acquired local migration lock identity is unsafe.');
        }

        $this->handle = $handle;

        return true;
    }

    public function release(): void
    {
        if (!is_resource($this->handle)) {
            throw new LogicException('The local migration lock is not held.');
        }

        $handle = $this->handle;
        $this->handle = null;
        $unlocked = @flock($handle, LOCK_UN);
        $closed = @fclose($handle);

        if (!$unlocked || !$closed) {
            throw new RuntimeException('The local migration lock could not be released.');
        }
    }

    /** @param resource $handle */
    private function openedPathMatchesHandle($handle): bool
    {
        $handleStatus = @fstat($handle);
        $pathStatus = @lstat($this->path);

        return is_array($handleStatus)
            && is_array($pathStatus)
            && ($handleStatus['mode'] & 0170000) === 0100000
            && ($pathStatus['mode'] & 0170000) === 0100000
            && ($handleStatus['mode'] & 0777) === 0600
            && ($pathStatus['mode'] & 0777) === 0600
            && $handleStatus['nlink'] === 1
            && $pathStatus['nlink'] === 1
            && $handleStatus['dev'] === $pathStatus['dev']
            && $handleStatus['ino'] === $pathStatus['ino'];
    }
}
