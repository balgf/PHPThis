<?php

declare(strict_types=1);

namespace Example\Documents\UpdateDocumentTitle;

use Example\Documents\GetDocument\RedisDocumentDetailsInvalidationOutcome;

final readonly class RedisInvalidatingDocumentTitleUpdateResult
{
    public function __construct(
        public DocumentTitleUpdateOutcome $update,
        public RedisDocumentDetailsInvalidationOutcome $invalidation,
    ) {
    }
}
