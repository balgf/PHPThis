<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

final class CatalogLock
{
    /** @var resource|null */
    private mixed $handle = null;
    private bool $exclusive = false;

    public function __construct(private readonly CatalogRoot $root) {}

    public function acquire(bool $exclusive): void
    {
        if ($this->handle !== null) { throw new \LogicException('Catalog lock already held.'); }
        $this->root->verify();
        $handle = @fopen($this->root->path . '/catalog.lock', 'r+');
        if ($handle === false) { throw new CatalogUnavailable('Catalog lock unavailable.'); }
        if (!flock($handle, ($exclusive ? LOCK_EX : LOCK_SH) | LOCK_NB)) {
            fclose($handle);
            throw new CatalogBusy('Catalog operation busy.');
        }
        $this->handle = $handle;
        $this->exclusive = $exclusive;
    }

    public function requireHeld(bool $exclusive): void
    {
        if ($this->handle === null || ($exclusive && !$this->exclusive)) {
            throw new \LogicException('Catalog operation requires its lock.');
        }
    }

    public function release(): void
    {
        if ($this->handle !== null) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
            $this->exclusive = false;
        }
    }
}
