<?php

declare(strict_types=1);

namespace Example\Cli;

use LogicException;
use RuntimeException;

final class LocalScheduleLock
{
    /** @var resource|null */
    private $handle = null;

    public function __construct(private readonly string $path)
    {
    }

    public function acquire(): bool
    {
        if (is_resource($this->handle)) {
            throw new LogicException('The local schedule lock is already held.');
        }

        $handle = @fopen($this->path, 'c+b');

        if ($handle === false) {
            throw new RuntimeException('The local schedule lock is unavailable.');
        }

        $wouldBlock = 0;

        if (!@flock($handle, LOCK_EX | LOCK_NB, $wouldBlock)) {
            $closed = @fclose($handle);

            if (!$closed) {
                throw new RuntimeException('The contended local schedule lock could not be closed.');
            }

            if ($wouldBlock === 1) {
                return false;
            }

            throw new RuntimeException('The local schedule lock could not be acquired.');
        }

        $this->handle = $handle;

        return true;
    }

    public function release(): void
    {
        if (!is_resource($this->handle)) {
            throw new LogicException('The local schedule lock is not held.');
        }

        $handle = $this->handle;
        $this->handle = null;
        $unlocked = @flock($handle, LOCK_UN);
        $closed = @fclose($handle);

        if (!$unlocked || !$closed) {
            throw new RuntimeException('The local schedule lock could not be released.');
        }
    }
}
