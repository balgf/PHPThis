<?php

declare(strict_types=1);

namespace Example\Documents\GetDocument;

use PHPThis\Database\Connection;

final readonly class SelectAuthorizedDocument implements RetrieveAuthorizedDocument
{
    public function __construct(private Connection $connection)
    {
    }

    public function retrieve(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
        AccountId $accountId,
        DocumentKey $documentKey,
    ): ?DocumentDetails {
        $row = $this->connection->selectOneRow(
            <<<'SQL'
                SELECT documents.title
                FROM documents
                WHERE documents.account_id = :account_id
                  AND documents.account_id = :resolved_tenant_account_id
                  AND documents.document_key = :document_key
                  AND EXISTS (
                      SELECT 1
                      FROM account_memberships
                      WHERE account_memberships.principal_id = :principal_id
                        AND account_memberships.account_id = :membership_tenant_account_id
                  )
                SQL,
            [
                'account_id' => $accountId->value,
                'resolved_tenant_account_id' => $tenant->accountId->value,
                'document_key' => $documentKey->value,
                'principal_id' => $principal->id,
                'membership_tenant_account_id' => $tenant->accountId->value,
            ],
        );

        return $row === null ? null : DocumentDetails::fromDatabaseRow($row);
    }
}
