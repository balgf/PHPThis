<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

use LogicException;

/**
 * @phpstan-type DocumentDetailsCacheTraceSnapshot array{
 *     read: 'not_attempted'|'hit'|'miss'|'corrupt'|'backend_unavailable',
 *     write: 'not_attempted'|'stored'|'payload_rejected'|'backend_unavailable',
 *     invalidation: 'not_attempted'|'deleted'|'absent'|'backend_unavailable'
 * }
 */
final class DocumentDetailsCacheTrace
{
    private ?DocumentDetailsCacheReadOutcome $readOutcome = null;

    private ?DocumentDetailsCacheWriteOutcome $writeOutcome = null;

    private ?RedisDocumentDetailsInvalidationOutcome $invalidationOutcome = null;

    public function complete(
        DocumentDetailsCacheReadOutcome $readOutcome,
        DocumentDetailsCacheWriteOutcome $writeOutcome,
    ): void {
        if ($this->readOutcome !== null || $this->writeOutcome !== null) {
            throw new LogicException('Document-details cache trace is already complete.');
        }

        $this->readOutcome = $readOutcome;
        $this->writeOutcome = $writeOutcome;
    }

    public function recordInvalidation(
        RedisDocumentDetailsInvalidationOutcome $invalidationOutcome,
    ): void {
        if ($this->invalidationOutcome !== null) {
            throw new LogicException('Document-details cache invalidation trace is already complete.');
        }

        $this->invalidationOutcome = $invalidationOutcome;
    }

    /** @return DocumentDetailsCacheTraceSnapshot */
    public function snapshot(): array
    {
        return [
            'read' => ($this->readOutcome ?? DocumentDetailsCacheReadOutcome::NotAttempted)->value,
            'write' => ($this->writeOutcome ?? DocumentDetailsCacheWriteOutcome::NotAttempted)->value,
            'invalidation' => (
                $this->invalidationOutcome
                ?? RedisDocumentDetailsInvalidationOutcome::NotAttempted
            )->value,
        ];
    }
}
