<?php

declare(strict_types=1);

namespace Example\Documents\UpdateDocumentTitle;

use Example\Documents\AccountId;
use Example\Documents\AuthenticatedPrincipal;
use Example\Documents\DocumentKey;
use Example\Documents\GetDocument\RedisDocumentDetailsCache;
use Example\Documents\GetDocument\RedisDocumentDetailsInvalidationOutcome;
use Example\Documents\ResolvedTenant;
use InvalidArgumentException;
use LogicException;
use PHPThis\Database\Connection;
use UnexpectedValueException;

final readonly class RedisInvalidatingDocumentTitleUpdate
{
    public function __construct(
        private Connection $connection,
        private RedisDocumentDetailsCache $cache,
    ) {
    }

    public function update(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
        AccountId $accountId,
        DocumentKey $documentKey,
        string $title,
    ): RedisInvalidatingDocumentTitleUpdateResult {
        if ($tenant->accountId->value !== $accountId->value) {
            throw new InvalidArgumentException(
                'Document title update requires matching requested and resolved tenants.',
            );
        }

        if ($title === '' || strlen($title) > 512 || preg_match('//u', $title) !== 1) {
            throw new InvalidArgumentException(
                'Updated document title must be valid UTF-8 between 1 and 512 bytes.',
            );
        }

        if ($this->connection->inTransaction()) {
            throw new LogicException(
                'Document title update cannot invalidate Redis inside an open database transaction.',
            );
        }

        $affectedRows = $this->connection->executeStatement(
            <<<'SQL'
                UPDATE documents
                SET title = :title
                WHERE account_id = :requested_account_id
                  AND account_id = :resolved_tenant_account_id
                  AND document_key = :document_key
                  AND EXISTS (
                      SELECT 1
                      FROM account_memberships
                      WHERE account_memberships.principal_id = :principal_id
                        AND account_memberships.account_id = :membership_tenant_account_id
                  )
                SQL,
            [
                'title' => $title,
                'requested_account_id' => $accountId->value,
                'resolved_tenant_account_id' => $tenant->accountId->value,
                'document_key' => $documentKey->value,
                'principal_id' => $principal->id,
                'membership_tenant_account_id' => $tenant->accountId->value,
            ],
        );

        if ($affectedRows < 0 || $affectedRows > 1) {
            throw new UnexpectedValueException(
                'Document title update must affect at most one authoritative row.',
            );
        }

        if ($affectedRows === 0) {
            return new RedisInvalidatingDocumentTitleUpdateResult(
                DocumentTitleUpdateOutcome::NotFound,
                RedisDocumentDetailsInvalidationOutcome::NotAttempted,
            );
        }

        return new RedisInvalidatingDocumentTitleUpdateResult(
            DocumentTitleUpdateOutcome::Updated,
            $this->cache->invalidate($accountId, $documentKey),
        );
    }
}
