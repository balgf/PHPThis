<?php

declare(strict_types=1);

namespace Example\Documents\ListDocuments;

use Example\Documents\AccountId;
use Example\Documents\AuthenticateDocumentRequest;
use Example\Documents\ResolveDocumentTenant;
use LogicException;
use PHPThis\Database\Connection;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;

final readonly class ListDocumentsHandler implements RequestHandler
{
    private const int PAGE_SIZE = 50;
    private const int FETCH_LIMIT = self::PAGE_SIZE + 1;

    public function __construct(
        private AuthenticateDocumentRequest $authenticate,
        private ResolveDocumentTenant $resolveTenant,
        private AuthorizeListDocuments $authorize,
        private Connection $connection,
    ) {
    }

    public function handle(Request $request): Response
    {
        $accountId = AccountId::fromPositiveInteger(
            $request->pathParameters->positiveInteger('account_id'),
        );
        $principal = $this->authenticate->authenticate($request);
        $tenant = $this->resolveTenant->resolve($principal, $accountId);
        $this->authorize->authorizeList($principal, $tenant);

        try {
            $pageRequest = ListDocumentsPageRequest::fromQuery($request->query);
        } catch (InvalidRequest) {
            return new Response(
                status: 400,
                headers: [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Cache-Control' => 'private, no-store',
                ],
                body: "{\"error\":{\"code\":\"invalid_request\",\"message\":\"Request is invalid.\"}}\n",
            );
        }

        if ($pageRequest->categories === []) {
            return new Response(
                status: 200,
                headers: [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Cache-Control' => 'private, no-store',
                ],
                body: "{\"documents\":[],\"next_cursor\":null}\n",
            );
        }

        if (($pageRequest->cursorRank === null) !== ($pageRequest->cursorDocumentKey === null)) {
            throw new LogicException('List-documents cursor components must be present together.');
        }

        $categories = $pageRequest->categories ?? [];
        $categoryCount = count($categories);
        $cursorRank = $pageRequest->cursorRank;
        $cursorDocumentKey = $pageRequest->cursorDocumentKey?->value;

        if ($cursorRank === null || $cursorDocumentKey === null) {
            $cursorIsAbsent = 1;
            $cursorRank = 0;
            $cursorDocumentKey = '';
        } else {
            $cursorIsAbsent = 0;
        }

        $rows = match ($pageRequest->order) {
            'rank_asc' => match ($categoryCount) {
                0 => $this->connection->selectAllRows(
                    <<<'SQL'
                        SELECT
                            documents.document_key,
                            documents.title,
                            documents.category,
                            documents.sort_rank
                        FROM documents
                        WHERE documents.account_id = :requested_account_id
                          AND documents.account_id = :resolved_tenant_account_id
                          AND EXISTS (
                              SELECT 1
                              FROM account_memberships
                              WHERE account_memberships.principal_id = :principal_id
                                AND account_memberships.account_id = :membership_tenant_account_id
                          )
                          AND (
                              :cursor_is_absent = 1
                              OR documents.sort_rank > :cursor_primary_sort_rank
                              OR (
                                  documents.sort_rank = :cursor_tie_sort_rank
                                  AND documents.document_key COLLATE BINARY > :cursor_document_key
                              )
                          )
                        ORDER BY documents.sort_rank ASC, documents.document_key COLLATE BINARY ASC
                        LIMIT :fetch_limit
                        SQL,
                    [
                        'requested_account_id' => $accountId->value,
                        'resolved_tenant_account_id' => $tenant->accountId->value,
                        'principal_id' => $principal->id,
                        'membership_tenant_account_id' => $tenant->accountId->value,
                        'cursor_is_absent' => $cursorIsAbsent,
                        'cursor_primary_sort_rank' => $cursorRank,
                        'cursor_tie_sort_rank' => $cursorRank,
                        'cursor_document_key' => $cursorDocumentKey,
                        'fetch_limit' => self::FETCH_LIMIT,
                    ],
                ),
                1 => $this->connection->selectAllRows(
                    <<<'SQL'
                        SELECT
                            documents.document_key,
                            documents.title,
                            documents.category,
                            documents.sort_rank
                        FROM documents
                        WHERE documents.account_id = :requested_account_id
                          AND documents.account_id = :resolved_tenant_account_id
                          AND EXISTS (
                              SELECT 1
                              FROM account_memberships
                              WHERE account_memberships.principal_id = :principal_id
                                AND account_memberships.account_id = :membership_tenant_account_id
                          )
                          AND documents.category IN (:category_1)
                          AND (
                              :cursor_is_absent = 1
                              OR documents.sort_rank > :cursor_primary_sort_rank
                              OR (
                                  documents.sort_rank = :cursor_tie_sort_rank
                                  AND documents.document_key COLLATE BINARY > :cursor_document_key
                              )
                          )
                        ORDER BY documents.sort_rank ASC, documents.document_key COLLATE BINARY ASC
                        LIMIT :fetch_limit
                        SQL,
                    [
                        'requested_account_id' => $accountId->value,
                        'resolved_tenant_account_id' => $tenant->accountId->value,
                        'principal_id' => $principal->id,
                        'membership_tenant_account_id' => $tenant->accountId->value,
                        'category_1' => $categories[0],
                        'cursor_is_absent' => $cursorIsAbsent,
                        'cursor_primary_sort_rank' => $cursorRank,
                        'cursor_tie_sort_rank' => $cursorRank,
                        'cursor_document_key' => $cursorDocumentKey,
                        'fetch_limit' => self::FETCH_LIMIT,
                    ],
                ),
                2 => $this->connection->selectAllRows(
                    <<<'SQL'
                        SELECT
                            documents.document_key,
                            documents.title,
                            documents.category,
                            documents.sort_rank
                        FROM documents
                        WHERE documents.account_id = :requested_account_id
                          AND documents.account_id = :resolved_tenant_account_id
                          AND EXISTS (
                              SELECT 1
                              FROM account_memberships
                              WHERE account_memberships.principal_id = :principal_id
                                AND account_memberships.account_id = :membership_tenant_account_id
                          )
                          AND documents.category IN (:category_1, :category_2)
                          AND (
                              :cursor_is_absent = 1
                              OR documents.sort_rank > :cursor_primary_sort_rank
                              OR (
                                  documents.sort_rank = :cursor_tie_sort_rank
                                  AND documents.document_key COLLATE BINARY > :cursor_document_key
                              )
                          )
                        ORDER BY documents.sort_rank ASC, documents.document_key COLLATE BINARY ASC
                        LIMIT :fetch_limit
                        SQL,
                    [
                        'requested_account_id' => $accountId->value,
                        'resolved_tenant_account_id' => $tenant->accountId->value,
                        'principal_id' => $principal->id,
                        'membership_tenant_account_id' => $tenant->accountId->value,
                        'category_1' => $categories[0],
                        'category_2' => $categories[1]
                            ?? throw new LogicException('List-documents category two is missing.'),
                        'cursor_is_absent' => $cursorIsAbsent,
                        'cursor_primary_sort_rank' => $cursorRank,
                        'cursor_tie_sort_rank' => $cursorRank,
                        'cursor_document_key' => $cursorDocumentKey,
                        'fetch_limit' => self::FETCH_LIMIT,
                    ],
                ),
                3 => $this->connection->selectAllRows(
                    <<<'SQL'
                        SELECT
                            documents.document_key,
                            documents.title,
                            documents.category,
                            documents.sort_rank
                        FROM documents
                        WHERE documents.account_id = :requested_account_id
                          AND documents.account_id = :resolved_tenant_account_id
                          AND EXISTS (
                              SELECT 1
                              FROM account_memberships
                              WHERE account_memberships.principal_id = :principal_id
                                AND account_memberships.account_id = :membership_tenant_account_id
                          )
                          AND documents.category IN (:category_1, :category_2, :category_3)
                          AND (
                              :cursor_is_absent = 1
                              OR documents.sort_rank > :cursor_primary_sort_rank
                              OR (
                                  documents.sort_rank = :cursor_tie_sort_rank
                                  AND documents.document_key COLLATE BINARY > :cursor_document_key
                              )
                          )
                        ORDER BY documents.sort_rank ASC, documents.document_key COLLATE BINARY ASC
                        LIMIT :fetch_limit
                        SQL,
                    [
                        'requested_account_id' => $accountId->value,
                        'resolved_tenant_account_id' => $tenant->accountId->value,
                        'principal_id' => $principal->id,
                        'membership_tenant_account_id' => $tenant->accountId->value,
                        'category_1' => $categories[0],
                        'category_2' => $categories[1]
                            ?? throw new LogicException('List-documents category two is missing.'),
                        'category_3' => $categories[2]
                            ?? throw new LogicException('List-documents category three is missing.'),
                        'cursor_is_absent' => $cursorIsAbsent,
                        'cursor_primary_sort_rank' => $cursorRank,
                        'cursor_tie_sort_rank' => $cursorRank,
                        'cursor_document_key' => $cursorDocumentKey,
                        'fetch_limit' => self::FETCH_LIMIT,
                    ],
                ),
                default => throw new LogicException('List-documents category count is outside the supported range.'),
            },
            'rank_desc' => match ($categoryCount) {
                0 => $this->connection->selectAllRows(
                    <<<'SQL'
                        SELECT
                            documents.document_key,
                            documents.title,
                            documents.category,
                            documents.sort_rank
                        FROM documents
                        WHERE documents.account_id = :requested_account_id
                          AND documents.account_id = :resolved_tenant_account_id
                          AND EXISTS (
                              SELECT 1
                              FROM account_memberships
                              WHERE account_memberships.principal_id = :principal_id
                                AND account_memberships.account_id = :membership_tenant_account_id
                          )
                          AND (
                              :cursor_is_absent = 1
                              OR documents.sort_rank < :cursor_primary_sort_rank
                              OR (
                                  documents.sort_rank = :cursor_tie_sort_rank
                                  AND documents.document_key COLLATE BINARY < :cursor_document_key
                              )
                          )
                        ORDER BY documents.sort_rank DESC, documents.document_key COLLATE BINARY DESC
                        LIMIT :fetch_limit
                        SQL,
                    [
                        'requested_account_id' => $accountId->value,
                        'resolved_tenant_account_id' => $tenant->accountId->value,
                        'principal_id' => $principal->id,
                        'membership_tenant_account_id' => $tenant->accountId->value,
                        'cursor_is_absent' => $cursorIsAbsent,
                        'cursor_primary_sort_rank' => $cursorRank,
                        'cursor_tie_sort_rank' => $cursorRank,
                        'cursor_document_key' => $cursorDocumentKey,
                        'fetch_limit' => self::FETCH_LIMIT,
                    ],
                ),
                1 => $this->connection->selectAllRows(
                    <<<'SQL'
                        SELECT
                            documents.document_key,
                            documents.title,
                            documents.category,
                            documents.sort_rank
                        FROM documents
                        WHERE documents.account_id = :requested_account_id
                          AND documents.account_id = :resolved_tenant_account_id
                          AND EXISTS (
                              SELECT 1
                              FROM account_memberships
                              WHERE account_memberships.principal_id = :principal_id
                                AND account_memberships.account_id = :membership_tenant_account_id
                          )
                          AND documents.category IN (:category_1)
                          AND (
                              :cursor_is_absent = 1
                              OR documents.sort_rank < :cursor_primary_sort_rank
                              OR (
                                  documents.sort_rank = :cursor_tie_sort_rank
                                  AND documents.document_key COLLATE BINARY < :cursor_document_key
                              )
                          )
                        ORDER BY documents.sort_rank DESC, documents.document_key COLLATE BINARY DESC
                        LIMIT :fetch_limit
                        SQL,
                    [
                        'requested_account_id' => $accountId->value,
                        'resolved_tenant_account_id' => $tenant->accountId->value,
                        'principal_id' => $principal->id,
                        'membership_tenant_account_id' => $tenant->accountId->value,
                        'category_1' => $categories[0],
                        'cursor_is_absent' => $cursorIsAbsent,
                        'cursor_primary_sort_rank' => $cursorRank,
                        'cursor_tie_sort_rank' => $cursorRank,
                        'cursor_document_key' => $cursorDocumentKey,
                        'fetch_limit' => self::FETCH_LIMIT,
                    ],
                ),
                2 => $this->connection->selectAllRows(
                    <<<'SQL'
                        SELECT
                            documents.document_key,
                            documents.title,
                            documents.category,
                            documents.sort_rank
                        FROM documents
                        WHERE documents.account_id = :requested_account_id
                          AND documents.account_id = :resolved_tenant_account_id
                          AND EXISTS (
                              SELECT 1
                              FROM account_memberships
                              WHERE account_memberships.principal_id = :principal_id
                                AND account_memberships.account_id = :membership_tenant_account_id
                          )
                          AND documents.category IN (:category_1, :category_2)
                          AND (
                              :cursor_is_absent = 1
                              OR documents.sort_rank < :cursor_primary_sort_rank
                              OR (
                                  documents.sort_rank = :cursor_tie_sort_rank
                                  AND documents.document_key COLLATE BINARY < :cursor_document_key
                              )
                          )
                        ORDER BY documents.sort_rank DESC, documents.document_key COLLATE BINARY DESC
                        LIMIT :fetch_limit
                        SQL,
                    [
                        'requested_account_id' => $accountId->value,
                        'resolved_tenant_account_id' => $tenant->accountId->value,
                        'principal_id' => $principal->id,
                        'membership_tenant_account_id' => $tenant->accountId->value,
                        'category_1' => $categories[0],
                        'category_2' => $categories[1]
                            ?? throw new LogicException('List-documents category two is missing.'),
                        'cursor_is_absent' => $cursorIsAbsent,
                        'cursor_primary_sort_rank' => $cursorRank,
                        'cursor_tie_sort_rank' => $cursorRank,
                        'cursor_document_key' => $cursorDocumentKey,
                        'fetch_limit' => self::FETCH_LIMIT,
                    ],
                ),
                3 => $this->connection->selectAllRows(
                    <<<'SQL'
                        SELECT
                            documents.document_key,
                            documents.title,
                            documents.category,
                            documents.sort_rank
                        FROM documents
                        WHERE documents.account_id = :requested_account_id
                          AND documents.account_id = :resolved_tenant_account_id
                          AND EXISTS (
                              SELECT 1
                              FROM account_memberships
                              WHERE account_memberships.principal_id = :principal_id
                                AND account_memberships.account_id = :membership_tenant_account_id
                          )
                          AND documents.category IN (:category_1, :category_2, :category_3)
                          AND (
                              :cursor_is_absent = 1
                              OR documents.sort_rank < :cursor_primary_sort_rank
                              OR (
                                  documents.sort_rank = :cursor_tie_sort_rank
                                  AND documents.document_key COLLATE BINARY < :cursor_document_key
                              )
                          )
                        ORDER BY documents.sort_rank DESC, documents.document_key COLLATE BINARY DESC
                        LIMIT :fetch_limit
                        SQL,
                    [
                        'requested_account_id' => $accountId->value,
                        'resolved_tenant_account_id' => $tenant->accountId->value,
                        'principal_id' => $principal->id,
                        'membership_tenant_account_id' => $tenant->accountId->value,
                        'category_1' => $categories[0],
                        'category_2' => $categories[1]
                            ?? throw new LogicException('List-documents category two is missing.'),
                        'category_3' => $categories[2]
                            ?? throw new LogicException('List-documents category three is missing.'),
                        'cursor_is_absent' => $cursorIsAbsent,
                        'cursor_primary_sort_rank' => $cursorRank,
                        'cursor_tie_sort_rank' => $cursorRank,
                        'cursor_document_key' => $cursorDocumentKey,
                        'fetch_limit' => self::FETCH_LIMIT,
                    ],
                ),
                default => throw new LogicException('List-documents category count is outside the supported range.'),
            },
        };

        $documents = [];
        $lastDocument = null;
        $hasNextPage = false;

        foreach ($rows as $index => $row) {
            $document = DocumentSummary::fromDatabaseRow($row);

            if ($index >= self::PAGE_SIZE) {
                $hasNextPage = true;
                continue;
            }

            $lastDocument = $document;
            $documents[] = [
                'document_key' => $document->documentKey->value,
                'title' => $document->title,
                'category' => $document->category,
                'sort_rank' => $document->sortRank,
            ];
        }

        $nextCursor = null;

        if ($hasNextPage) {
            if ($lastDocument === null) {
                throw new LogicException('A continued document page must contain a last returned document.');
            }

            $nextCursor = 'v1:'
                . $pageRequest->order
                . ':'
                . (string) $lastDocument->sortRank
                . ':'
                . $lastDocument->documentKey->value;
        }

        $body = json_encode(
            [
                'documents' => $documents,
                'next_cursor' => $nextCursor,
            ],
            JSON_THROW_ON_ERROR,
        );

        return new Response(
            status: 200,
            headers: [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'private, no-store',
            ],
            body: $body . "\n",
        );
    }
}
