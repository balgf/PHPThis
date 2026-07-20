<?php

declare(strict_types=1);

use Example\Documents\DocumentRoutes;
use Example\Documents\AccountId;
use Example\Documents\AuthenticateDocumentRequest;
use Example\Documents\AuthenticatedPrincipal;
use Example\Documents\CrossTenant;
use Example\Documents\DocumentKey;
use Example\Documents\Forbidden;
use Example\Documents\GetDocument\AuthorizeGetDocument;
use Example\Documents\GetDocument\DocumentDetails;
use Example\Documents\GetDocument\RetrieveAuthorizedDocument;
use Example\Documents\GetDocument\SelectAuthorizedDocument;
use Example\Documents\ListDocuments\AuthorizeListDocuments;
use Example\Documents\ListDocuments\DocumentSummary;
use Example\Documents\ListDocuments\ListDocumentsHandler;
use Example\Documents\ListDocuments\ListDocumentsPageRequest;
use Example\Documents\ResolveDocumentTenant;
use Example\Documents\ResolvedTenant;
use Example\Documents\Unauthenticated;
use PHPThis\Application;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use PHPThis\Http\ErrorResponseRegistry;
use PHPThis\Http\Request;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\Response;
use PHPThis\Http\UnknownFailureBoundary;
use PHPThis\Routing\Route;
use PHPThis\Routing\Router;

final class RequestPolicyTestTrace
{
    public const PRIVATE_FAILURE = 'credential=CredentialSecretMarker tenant=9001001 document=SecretDocumentMarker';

    /** @var list<string> */
    public array $steps = [];

    public ?AuthenticatedPrincipal $retrievedPrincipal = null;

    public ?ResolvedTenant $retrievedTenant = null;

    public ?AccountId $retrievedAccountId = null;

    public ?DocumentKey $retrievedDocumentKey = null;

    public ?AuthenticatedPrincipal $listedPrincipal = null;

    public ?ResolvedTenant $listedTenant = null;

    public function record(string $step): void
    {
        $this->steps[] = $step;
    }

    public function recordRetrieval(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
        AccountId $accountId,
        DocumentKey $documentKey,
    ): void {
        $this->record('retrieve');
        $this->retrievedPrincipal = $principal;
        $this->retrievedTenant = $tenant;
        $this->retrievedAccountId = $accountId;
        $this->retrievedDocumentKey = $documentKey;
    }

    public function recordListAuthorization(
        AuthenticatedPrincipal $principal,
        ResolvedTenant $tenant,
    ): void {
        $this->record('authorize_list');
        $this->listedPrincipal = $principal;
        $this->listedTenant = $tenant;
    }
}

/** @return array<string, Closure(): void> */
function requestPolicyTests(): array
{
    return [
        'document list page request accepts only finite orders categories and canonical composite cursors' => static function (): void {
            $sqlShapedCategory = "x') OR 1=1 --";
            $cases = [
                'default' => [[], 'rank_asc', null, null, null],
                'explicit_ascending' => [['order' => 'rank_asc'], 'rank_asc', null, null, null],
                'explicit_descending' => [['order' => 'rank_desc'], 'rank_desc', null, null, null],
                'empty_categories' => [['categories' => []], 'rank_asc', [], null, null],
                'empty_categories_transport' => [['categories' => ['']], 'rank_asc', [], null, null],
                'one_category' => [['categories' => ['alpha']], 'rank_asc', ['alpha'], null, null],
                'two_categories' => [
                    ['categories' => ['alpha', 'beta']],
                    'rank_asc',
                    ['alpha', 'beta'],
                    null,
                    null,
                ],
                'three_categories' => [
                    ['categories' => ['alpha', 'beta', $sqlShapedCategory]],
                    'rank_asc',
                    ['alpha', 'beta', $sqlShapedCategory],
                    null,
                    null,
                ],
                'ascending_cursor' => [
                    ['cursor' => 'v1:rank_asc:0:Doc_001'],
                    'rank_asc',
                    null,
                    0,
                    'Doc_001',
                ],
                'descending_cursor' => [
                    ['order' => 'rank_desc', 'cursor' => 'v1:rank_desc:1000000:Doc_9-z'],
                    'rank_desc',
                    null,
                    1_000_000,
                    'Doc_9-z',
                ],
            ];

            foreach ($cases as $case => [$query, $order, $categories, $cursorRank, $cursorKey]) {
                $page = ListDocumentsPageRequest::fromQuery($query);

                if (
                    $page->order !== $order
                    || $page->categories !== $categories
                    || $page->cursorRank !== $cursorRank
                    || $page->cursorDocumentKey?->value !== $cursorKey
                ) {
                    throw new RuntimeException(sprintf(
                        'Document-list page request acceptance changed for case "%s".',
                        $case,
                    ));
                }
            }
        },
        'document list page request rejects adversarial shapes and malformed cursors before SQL' => static function (): void {
            foreach (invalidDocumentListQueries() as $case => $query) {
                try {
                    ListDocumentsPageRequest::fromQuery($query);
                } catch (\PHPThis\Http\InvalidRequest) {
                    continue;
                }

                throw new RuntimeException(sprintf(
                    'Expected adversarial document-list query case "%s" to be rejected.',
                    $case,
                ));
            }
        },
        'protected document list preserves policy order and rejects denials before SQL' => static function (): void {
            $databasePath = createDocumentListDatabaseFixture('list-policy-denials', 3);
            $cases = [
                'authenticate' => [
                    401,
                    ['authenticate'],
                    [
                        'Content-Type' => 'application/json; charset=utf-8',
                        'Cache-Control' => 'private, no-store',
                        'WWW-Authenticate' => 'Bearer',
                    ],
                    "{\"error\":{\"code\":\"unauthenticated\",\"message\":\"Authentication is required.\"}}\n",
                ],
                'resolve_tenant' => [
                    403,
                    ['authenticate', 'resolve_tenant'],
                    requestPolicyJsonHeaders(),
                    "{\"error\":{\"code\":\"forbidden\",\"message\":\"Request is forbidden.\"}}\n",
                ],
                'authorize_list' => [
                    403,
                    ['authenticate', 'resolve_tenant', 'authorize_list'],
                    requestPolicyJsonHeaders(),
                    "{\"error\":{\"code\":\"forbidden\",\"message\":\"Request is forbidden.\"}}\n",
                ],
            ];

            foreach ($cases as $failureStage => [$status, $steps, $headers, $body]) {
                $budget = new QueryBudget(1);
                $queryTrace = new QueryTrace(1);
                $policyTrace = new RequestPolicyTestTrace();
                $response = handleDocumentListPolicyRequest(
                    requestPolicyListApplication(
                        $databasePath,
                        $policyTrace,
                        $failureStage,
                        $budget,
                        $queryTrace,
                    ),
                    [],
                );

                if (
                    $response->status !== $status
                    || $response->headers !== $headers
                    || $response->body !== $body
                    || str_contains($response->body, RequestPolicyTestTrace::PRIVATE_FAILURE)
                    || $policyTrace->steps !== $steps
                    || $budget->used() !== 0
                    || $queryTrace->snapshot()['statements'] !== 0
                ) {
                    throw new RuntimeException(sprintf(
                        'Expected list policy denial "%s" to stop before protected SQL.',
                        $failureStage,
                    ));
                }
            }
        },
        'protected document list passes typed authority and rejects invalid query before protected SQL' => static function (): void {
            $databasePath = createDocumentListDatabaseFixture('list-policy-input', 3);
            $permitted = runDocumentListPageScenario($databasePath, ['order' => 'rank_asc']);

            if (
                $permitted['status'] !== 200
                || $permitted['steps'] !== ['authenticate', 'resolve_tenant', 'authorize_list']
                || $permitted['principal_id'] !== 7
                || $permitted['tenant_account_id'] !== 42
                || $permitted['used'] !== 1
                || $permitted['statements'] !== 1
            ) {
                throw new RuntimeException('Expected typed authority values before one permitted list query.');
            }

            foreach (invalidDocumentListQueries() as $case => $query) {
                $invalidBudget = new QueryBudget(1);
                $invalidTrace = new QueryTrace(1);
                $invalidPolicyTrace = new RequestPolicyTestTrace();
                $invalid = handleDocumentListPolicyRequest(
                    requestPolicyListApplication(
                        $databasePath,
                        $invalidPolicyTrace,
                        null,
                        $invalidBudget,
                        $invalidTrace,
                    ),
                    $query,
                );
                $submitted = json_encode($query, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);

                if (
                    $invalid->status !== 400
                    || $invalid->headers !== requestPolicyJsonHeaders()
                    || $invalid->body
                        !== "{\"error\":{\"code\":\"invalid_request\",\"message\":\"Request is invalid.\"}}\n"
                    || str_contains($invalid->body, $submitted)
                    || str_contains(implode("\n", $invalid->headers), $submitted)
                    || $invalidPolicyTrace->steps
                        !== ['authenticate', 'resolve_tenant', 'authorize_list']
                    || $invalidBudget->used() !== 0
                    || $invalidTrace->snapshot()['statements'] !== 0
                ) {
                    throw new RuntimeException(sprintf(
                        'Rejected document-list query "%s" must remain generic and use zero SQL.',
                        $case,
                    ));
                }
            }
        },
        'document list executes eight finite raw SQL branches and empty filters use zero SQL' => static function (): void {
            $databasePath = createDocumentListDatabaseFixture(
                'list-finite-shapes',
                12,
                false,
                true,
            );
            $shapeCategories = [
                'unfiltered' => null,
                'one' => ['alpha'],
                'two' => ['alpha', 'beta'],
                'three' => ['alpha', 'beta', 'gamma'],
            ];
            $expectedCounts = ['unfiltered' => 13, 'one' => 4, 'two' => 8, 'three' => 12];
            $fingerprints = [];

            foreach (['rank_asc', 'rank_desc'] as $order) {
                foreach ($shapeCategories as $shape => $categories) {
                    $query = ['order' => $order];

                    if ($categories !== null) {
                        $query['categories'] = $categories;
                    }

                    $page = runDocumentListPageScenario($databasePath, $query);
                    $expectedKeys = documentListFixtureKeys(12);

                    if ($categories === null) {
                        $expectedKeys[] = 'FourthCategory';
                    } else {
                        $expectedKeys = array_values(array_filter(
                            $expectedKeys,
                            static function (string $key) use ($categories): bool {
                                $number = (int) substr($key, 4);
                                $category = match ($number % 3) {
                                    0 => 'alpha',
                                    1 => 'beta',
                                    default => 'gamma',
                                };

                                return in_array($category, $categories, true);
                            },
                        ));
                    }

                    if ($order === 'rank_desc') {
                        $expectedKeys = array_reverse($expectedKeys);
                    }

                    if (
                        count($page['document_keys']) !== $expectedCounts[$shape]
                        || $page['document_keys'] !== $expectedKeys
                        || $page['next_cursor'] !== null
                        || $page['used'] !== 1
                        || $page['statements'] !== 1
                        || $page['tracked_fingerprints'] !== 1
                        || $page['repeated_fingerprints'] !== 0
                        || $page['maximum_executions'] !== 1
                        || $page['failures'] !== 0
                    ) {
                        throw new RuntimeException(sprintf(
                            'Finite document-list SQL branch changed for %s/%s.',
                            $order,
                            $shape,
                        ));
                    }

                    $fingerprints[] = $page['fingerprint'];
                }

                foreach ([[], ['']] as $emptyTransport) {
                    $empty = runDocumentListPageScenario(
                        $databasePath,
                        ['order' => $order, 'categories' => $emptyTransport],
                    );

                    if (
                        $empty['document_keys'] !== []
                        || $empty['next_cursor'] !== null
                        || $empty['used'] !== 0
                        || $empty['statements'] !== 0
                        || $empty['tracked_fingerprints'] !== 0
                    ) {
                        throw new RuntimeException(
                            'Explicit empty category transports must return an empty zero-SQL page.',
                        );
                    }
                }
            }

            if (count(array_unique($fingerprints)) !== 8) {
                throw new RuntimeException('Expected exactly eight distinct finite raw SQL fingerprints.');
            }

            $budget = new QueryBudget(1);
            $queryTrace = new QueryTrace(1);
            $policyTrace = new RequestPolicyTestTrace();
            $tooMany = handleDocumentListPolicyRequest(
                requestPolicyListApplication(
                    $databasePath,
                    $policyTrace,
                    null,
                    $budget,
                    $queryTrace,
                ),
                ['categories' => ['alpha', 'beta', 'gamma', 'delta']],
            );

            if (
                $tooMany->status !== 400
                || $policyTrace->steps !== ['authenticate', 'resolve_tenant', 'authorize_list']
                || $budget->used() !== 0
                || $queryTrace->snapshot()['statements'] !== 0
            ) {
                throw new RuntimeException('Four categories must be rejected after policy but before SQL.');
            }
        },
        'protected document list SQL fails closed across tenant and membership mismatches' => static function (): void {
            $databasePath = createDocumentListDatabaseFixture('list-sql-authority-mismatches', 3);
            $cases = [
                'requested_resolved_tenant_mismatch' => [43, 42, 7],
                'missing_principal_membership' => [42, 42, 8],
                'wrong_principal_membership' => [42, 42, 9],
            ];

            foreach ($cases as $case => [$requestedAccountId, $resolvedAccountId, $principalId]) {
                $page = runDocumentListPageScenario(
                    $databasePath,
                    [],
                    $requestedAccountId,
                    $resolvedAccountId,
                    $principalId,
                );

                if (
                    $page['body'] !== "{\"documents\":[],\"next_cursor\":null}\n"
                    || $page['document_keys'] !== []
                    || $page['steps'] !== ['authenticate', 'resolve_tenant', 'authorize_list']
                    || $page['principal_id'] !== $principalId
                    || $page['tenant_account_id'] !== $resolvedAccountId
                    || $page['used'] !== 1
                    || $page['statements'] !== 1
                    || $page['failures'] !== 0
                    || $page['tracked_fingerprints'] !== 1
                    || $page['maximum_executions'] !== 1
                ) {
                    throw new RuntimeException(sprintf(
                        'Protected list authority case "%s" must return zero rows from one statement.',
                        $case,
                    ));
                }
            }
        },
        'document list binds SQL-shaped category data and preserves tenant isolation' => static function (): void {
            $payload = "x') OR 1=1 --";
            $databasePath = createDocumentListDatabaseFixture(
                'list-bound-data-isolation',
                3,
                true,
            );
            $filtered = runDocumentListPageScenario(
                $databasePath,
                ['categories' => [$payload]],
            );
            $unfiltered = runDocumentListPageScenario($databasePath, []);
            $verification = Connection::connect(
                'sqlite:' . $databasePath,
                new QueryBudget(2),
                new QueryTrace(2),
            );
            $table = $verification->selectOneRow(
                <<<'SQL'
                    SELECT COUNT(documents.document_key) AS row_count
                    FROM documents
                    SQL,
            );
            $otherTenant = $verification->selectOneRow(
                <<<'SQL'
                    SELECT COUNT(documents.document_key) AS row_count
                    FROM documents
                    WHERE documents.account_id = :account_id
                    SQL,
                ['account_id' => 43],
            );

            if (
                $filtered['document_keys'] !== ['SqlLookingData']
                || $filtered['categories'] !== [$payload]
                || $filtered['used'] !== 1
                || $filtered['statements'] !== 1
                || str_contains($filtered['trace_json'], $payload)
                || in_array('OtherTenant', $unfiltered['document_keys'], true)
                || ($table['row_count'] ?? null) !== 5
                || ($otherTenant['row_count'] ?? null) !== 1
            ) {
                throw new RuntimeException(
                    'Expected SQL-looking category text to remain bound data inside tenant 42.',
                );
            }
        },
        'document list composite cursor covers exact lookahead and stable 125-document traversal' => static function (): void {
            $exactPath = createDocumentListDatabaseFixture('list-exact-page', 50);
            $lookaheadPath = createDocumentListDatabaseFixture('list-lookahead-page', 51);
            $exact = runDocumentListPageScenario($exactPath, []);
            $lookahead = runDocumentListPageScenario($lookaheadPath, []);
            $lookaheadFinal = runDocumentListPageScenario(
                $lookaheadPath,
                ['cursor' => $lookahead['next_cursor']],
            );

            if (
                count($exact['document_keys']) !== 50
                || $exact['next_cursor'] !== null
                || count($lookahead['document_keys']) !== 50
                || $lookahead['next_cursor'] !== 'v1:rank_asc:2:Doc_050'
                || $lookaheadFinal['document_keys'] !== ['Doc_051']
                || $lookaheadFinal['next_cursor'] !== null
            ) {
                throw new RuntimeException('Expected exact and lookahead page boundaries to remain explicit.');
            }

            $databasePath = createDocumentListDatabaseFixture('list-composite-traversal', 125);

            foreach (['rank_asc', 'rank_desc'] as $order) {
                $traversal = traverseDocumentListFixture($databasePath, $order);
                $expected = documentListFixtureKeys(125);

                if ($order === 'rank_desc') {
                    $expected = array_reverse($expected);
                }

                $expectedCursors = $order === 'rank_asc'
                    ? ['v1:rank_asc:2:Doc_050', 'v1:rank_asc:5:Doc_100', null]
                    : ['v1:rank_desc:4:Doc_076', 'v1:rank_desc:1:Doc_026', null];

                if (
                    $traversal['document_keys'] !== $expected
                    || count(array_unique($traversal['document_keys'])) !== 125
                    || $traversal['page_sizes'] !== [50, 50, 25]
                    || $traversal['next_cursors'] !== $expectedCursors
                ) {
                    throw new RuntimeException(sprintf(
                        'Composite %s traversal must contain every document exactly once.',
                        $order,
                    ));
                }

                $repeated = runDocumentListPageScenario(
                    $databasePath,
                    ['order' => $order, 'cursor' => $expectedCursors[0]],
                );

                if ($repeated['body'] !== $traversal['second_page_body']) {
                    throw new RuntimeException('A repeated cursor on a static fixture must return the same page.');
                }
            }
        },
        'document list page keeps one statement and fingerprint across fixture sizes for all eight shapes' => static function (): void {
            $smallPath = createDocumentListDatabaseFixture('list-scale-small', 3);
            $largePath = createDocumentListDatabaseFixture('list-scale-large', 500);
            $shapeCategories = [
                'unfiltered' => null,
                'one' => ['alpha'],
                'two' => ['alpha', 'beta'],
                'three' => ['alpha', 'beta', 'gamma'],
            ];
            $fingerprints = [];

            foreach (['rank_asc', 'rank_desc'] as $order) {
                foreach ($shapeCategories as $shape => $categories) {
                    $query = ['order' => $order];

                    if ($categories !== null) {
                        $query['categories'] = $categories;
                    }

                    $small = runDocumentListPageScenario($smallPath, $query);
                    $large = runDocumentListPageScenario($largePath, $query);
                    $expectedSmallKeys = documentListFixtureKeys(3);

                    if ($categories !== null) {
                        $expectedSmallKeys = array_values(array_filter(
                            $expectedSmallKeys,
                            static function (string $key) use ($categories): bool {
                                $number = (int) substr($key, 4);
                                $category = match ($number % 3) {
                                    0 => 'alpha',
                                    1 => 'beta',
                                    default => 'gamma',
                                };

                                return in_array($category, $categories, true);
                            },
                        ));
                    }

                    if ($order === 'rank_desc') {
                        $expectedSmallKeys = array_reverse($expectedSmallKeys);
                    }

                    if (
                        $small['document_keys'] !== $expectedSmallKeys
                        || count($large['document_keys']) !== 50
                        || $small['next_cursor'] !== null
                        || $large['next_cursor'] === null
                        || $small['used'] !== 1
                        || $large['used'] !== 1
                        || $small['statements'] !== 1
                        || $large['statements'] !== 1
                        || $small['failures'] !== 0
                        || $large['failures'] !== 0
                        || $small['tracked_fingerprints'] !== 1
                        || $large['tracked_fingerprints'] !== 1
                        || $small['fingerprint'] === null
                        || $small['fingerprint'] !== $large['fingerprint']
                        || $small['repeated_fingerprints'] !== 0
                        || $large['repeated_fingerprints'] !== 0
                        || $small['maximum_executions'] !== 1
                        || $large['maximum_executions'] !== 1
                    ) {
                        throw new RuntimeException(sprintf(
                            'Expected invariant one-statement evidence for %s/%s at 3 and 500 rows.',
                            $order,
                            $shape,
                        ));
                    }

                    $fingerprints[] = $small['fingerprint'];
                }
            }

            if (count(array_unique($fingerprints)) !== 8) {
                throw new RuntimeException('Expected eight distinct scale-invariant raw SQL fingerprints.');
            }
        },
        'document list first pages surface out-of-domain stored ranks to strict projection' => static function (): void {
            $ascendingPath = createDocumentListDatabaseFixture(
                'list-invalid-stored-rank-rank_asc',
                0,
            );
            $ascendingSeed = Connection::connect(
                'sqlite:' . $ascendingPath,
                new QueryBudget(1),
                new QueryTrace(1),
            );
            $ascendingSeed->executeStatement(
                <<<'SQL'
                    INSERT INTO documents (
                        account_id,
                        document_key,
                        title,
                        category,
                        sort_rank
                    )
                    VALUES (
                        :account_id,
                        :document_key,
                        :title,
                        :category,
                        :sort_rank
                    )
                    SQL,
                [
                    'account_id' => 42,
                    'document_key' => 'InvalidLowRank',
                    'title' => 'Invalid stored rank',
                    'category' => 'alpha',
                    'sort_rank' => -1,
                ],
            );
            $descendingPath = createDocumentListDatabaseFixture(
                'list-invalid-stored-rank-rank_desc',
                0,
            );
            $descendingSeed = Connection::connect(
                'sqlite:' . $descendingPath,
                new QueryBudget(1),
                new QueryTrace(1),
            );
            $descendingSeed->executeStatement(
                <<<'SQL'
                    INSERT INTO documents (
                        account_id,
                        document_key,
                        title,
                        category,
                        sort_rank
                    )
                    VALUES (
                        :account_id,
                        :document_key,
                        :title,
                        :category,
                        :sort_rank
                    )
                    SQL,
                [
                    'account_id' => 42,
                    'document_key' => 'InvalidHighRank',
                    'title' => 'Invalid stored rank',
                    'category' => 'alpha',
                    'sort_rank' => 1_000_001,
                ],
            );
            $cases = [
                'rank_asc' => $ascendingPath,
                'rank_desc' => $descendingPath,
            ];

            foreach ($cases as $order => $databasePath) {
                $budget = new QueryBudget(1);
                $queryTrace = new QueryTrace(1);
                $policyTrace = new RequestPolicyTestTrace();
                $failedStrictly = false;

                try {
                    requestPolicyListApplication(
                        $databasePath,
                        $policyTrace,
                        null,
                        $budget,
                        $queryTrace,
                    )->handle(new Request(
                        'GET',
                        '/accounts/42/documents',
                        ['order' => $order],
                    ));
                } catch (UnexpectedValueException $failure) {
                    $failedStrictly = $failure->getMessage()
                        === 'Document summary sort rank is outside the supported range.';
                }

                $summary = $queryTrace->snapshot();

                if (
                    !$failedStrictly
                    || $policyTrace->steps !== ['authenticate', 'resolve_tenant', 'authorize_list']
                    || $budget->used() !== 1
                    || $summary['statements'] !== 1
                    || $summary['failures'] !== 0
                    || $summary['tracked_fingerprints'] !== 1
                    || $summary['maximum_executions_per_fingerprint'] !== 1
                ) {
                    throw new RuntimeException(sprintf(
                        'First-page %s must project and reject its out-of-domain stored rank.',
                        $order,
                    ));
                }
            }
        },
        'document list source uses direct raw SQL without ORM binding or pagination helpers' => static function (): void {
            $path = __DIR__ . '/../example/src/Documents/ListDocuments/ListDocumentsHandler.php';
            $source = file_get_contents($path);

            if (!is_string($source)) {
                throw new RuntimeException('Unable to inspect the document-list source.');
            }

            foreach (
                [
                    'Repository',
                    'ORM',
                    'QueryBuilder',
                    'Paginator',
                    'Hydrator',
                    'Binder',
                    'Binding',
                    'bindValue',
                    'bindParam',
                    'bind(',
                    'buildPlaceholders',
                    'where(',
                    'paginate(',
                    'sprintf(',
                    'implode(',
                ] as $forbiddenHelper
            ) {
                if (str_contains($source, $forbiddenHelper)) {
                    throw new RuntimeException(
                        "Document listing must not use data-access helper {$forbiddenHelper}.",
                    );
                }
            }

            if (
                substr_count($source, "<<<'SQL'") !== 8
                || substr_count($source, 'SELECT') !== 16
                || substr_count($source, 'selectAllRows(') !== 8
                || !str_contains($source, 'match ($pageRequest->order)')
                || !str_contains($source, 'match ($categoryCount)')
            ) {
                throw new RuntimeException(
                    'Expected eight complete visible raw SQL branches with direct Connection calls.',
                );
            }
        },
        'document policy rejects unauthenticated requests before later work' => static function (): void {
            $fixture = requestPolicyRetrievalFixture('policy-unauthenticated', 0);
            $trace = new RequestPolicyTestTrace();
            $response = handleDocumentPolicyRequest(
                requestPolicyApplication($trace, 'authenticate', $fixture['retrieve']),
                '/accounts/42/documents/SecretDocumentMarker',
            );

            if (
                $response->status !== 401
                || $response->headers !== [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Cache-Control' => 'private, no-store',
                    'WWW-Authenticate' => 'Bearer',
                ]
                || $response->body
                    !== "{\"error\":{\"code\":\"unauthenticated\",\"message\":\"Authentication is required.\"}}\n"
                || str_contains($response->body, RequestPolicyTestTrace::PRIVATE_FAILURE)
                || $trace->steps !== ['authenticate']
                || $fixture['budget']->used() !== 0
                || $fixture['trace']->snapshot()['statements'] !== 0
            ) {
                throw new RuntimeException('Expected unauthenticated requests to stop after authentication.');
            }
        },
        'document policy rejects cross-tenant requests before authorization' => static function (): void {
            $fixture = requestPolicyRetrievalFixture('policy-cross-tenant', 0);
            $trace = new RequestPolicyTestTrace();
            $registry = requestPolicyErrorRegistry();
            $response = handleDocumentPolicyRequest(
                requestPolicyApplication($trace, 'resolve_tenant', $fixture['retrieve']),
                '/accounts/42/documents/SecretDocumentMarker',
                $registry,
            );

            if (
                $response->status !== 403
                || $response->headers !== requestPolicyJsonHeaders()
                || $response->body
                    !== "{\"error\":{\"code\":\"forbidden\",\"message\":\"Request is forbidden.\"}}\n"
                || $registry->responseFor(new CrossTenant())
                    !== $registry->responseFor(new Forbidden())
                || str_contains($response->body, RequestPolicyTestTrace::PRIVATE_FAILURE)
                || $trace->steps !== ['authenticate', 'resolve_tenant']
                || $fixture['budget']->used() !== 0
                || $fixture['trace']->snapshot()['statements'] !== 0
            ) {
                throw new RuntimeException('Expected cross-tenant requests to stop at tenant resolution.');
            }
        },
        'document policy rejects forbidden requests before protected retrieval' => static function (): void {
            $fixture = requestPolicyRetrievalFixture('policy-forbidden', 0);
            $trace = new RequestPolicyTestTrace();
            $response = handleDocumentPolicyRequest(
                requestPolicyApplication($trace, 'authorize', $fixture['retrieve']),
                '/accounts/42/documents/SecretDocumentMarker',
            );

            if (
                $response->status !== 403
                || $response->headers !== requestPolicyJsonHeaders()
                || $response->body
                    !== "{\"error\":{\"code\":\"forbidden\",\"message\":\"Request is forbidden.\"}}\n"
                || str_contains($response->body, RequestPolicyTestTrace::PRIVATE_FAILURE)
                || $trace->steps !== ['authenticate', 'resolve_tenant', 'authorize']
                || $fixture['budget']->used() !== 0
                || $fixture['trace']->snapshot()['statements'] !== 0
            ) {
                throw new RuntimeException('Expected forbidden requests to stop before protected retrieval.');
            }
        },
        'consumer replaces every document policy and passes explicit authority values' => static function (): void {
            $small = runPermittedDocumentPolicyScenario('policy-permitted-small', 0);
            $large = runPermittedDocumentPolicyScenario('policy-permitted-large', 500);
            $expectedBody = "{\"document\":{\"account_id\":42,\"key\":\"Doc_9-z\",\"title\":\"Example document\"}}\n";

            if (
                $small['status'] !== 200
                || $large['status'] !== 200
                || $small['headers'] !== requestPolicyJsonHeaders()
                || $large['headers'] !== requestPolicyJsonHeaders()
                || $small['body'] !== $expectedBody
                || $large['body'] !== $expectedBody
                || $small['steps'] !== ['authenticate', 'resolve_tenant', 'authorize', 'retrieve']
                || $large['steps'] !== $small['steps']
                || $small['principal_id'] !== 7
                || $small['tenant_account_id'] !== 42
                || $small['requested_account_id'] !== 42
                || $small['document_key'] !== 'Doc_9-z'
                || $small['used'] !== 1
                || $large['used'] !== 1
                || $small['statements'] !== 1
                || $large['statements'] !== 1
                || str_contains($large['trace_json'], 'Doc_9-z')
                || str_contains($large['trace_json'], 'Example document')
            ) {
                throw new RuntimeException('Expected replaceable ordered policies and one bounded protected query.');
            }
        },
        'permitted document policy keeps protected missing responses private and generic' => static function (): void {
            $fixture = requestPolicyRetrievalFixture('policy-protected-missing', 0);
            $trace = new RequestPolicyTestTrace();
            $response = handleDocumentPolicyRequest(
                requestPolicyApplication($trace, null, $fixture['retrieve']),
                '/accounts/42/documents/SecretDocumentMarker',
            );
            $traceJson = json_encode($fixture['trace']->snapshot(), JSON_THROW_ON_ERROR);

            if (
                $response->status !== 404
                || $response->headers !== requestPolicyJsonHeaders()
                || $response->body
                    !== "{\"error\":{\"code\":\"document_not_found\",\"message\":\"Document was not found.\"}}\n"
                || str_contains($response->body, 'SecretDocumentMarker')
                || str_contains($traceJson, 'SecretDocumentMarker')
                || $trace->steps !== ['authenticate', 'resolve_tenant', 'authorize', 'retrieve']
                || $fixture['budget']->used() !== 1
                || $fixture['trace']->snapshot()['statements'] !== 1
            ) {
                throw new RuntimeException('Expected a private generic bounded protected not-found response.');
            }
        },
        'protected document query fails closed when requested and resolved tenants differ' => static function (): void {
            $fixture = requestPolicyRetrievalFixture('policy-query-tenant-mismatch', 0);
            $document = $fixture['retrieve']->retrieve(
                AuthenticatedPrincipal::fromPositiveInteger(7),
                ResolvedTenant::forAccount(AccountId::fromPositiveInteger(42)),
                AccountId::fromPositiveInteger(43),
                DocumentKey::fromToken('OtherDocument'),
            );

            if (
                $document !== null
                || $fixture['budget']->used() !== 1
                || $fixture['trace']->snapshot()['statements'] !== 1
            ) {
                throw new RuntimeException('Expected protected SQL to retain the resolved tenant boundary.');
            }
        },
        'document policy is not entered for route or method rejection' => static function (): void {
            $fixture = requestPolicyRetrievalFixture('policy-routing-rejection', 0);
            $trace = new RequestPolicyTestTrace();
            $application = requestPolicyApplication($trace, null, $fixture['retrieve']);
            $missing = $application->handle(new Request('GET', '/accounts/42/other/Doc_9-z'));
            $notAllowed = $application->handle(
                new Request('POST', '/accounts/42/documents/Doc_9-z'),
            );

            if (
                $missing->status !== 404
                || $missing->headers['Cache-Control'] !== 'no-store'
                || $notAllowed->status !== 405
                || $notAllowed->headers['Cache-Control'] !== 'no-store'
                || $trace->steps !== []
                || $fixture['budget']->used() !== 0
                || $fixture['trace']->snapshot()['statements'] !== 0
            ) {
                throw new RuntimeException('Expected routing failures before every request policy.');
            }
        },
        'mapped document denials emit no sensitive log data' => static function (): void {
            $fixture = requestPolicyRetrievalFixture('policy-denial-redaction', 0);
            $logPath = __DIR__ . '/../tmp/request-policy-denials.log';

            if (file_put_contents($logPath, '') !== 0) {
                throw new RuntimeException('Unable to reset the policy-denial test log.');
            }

            $previousErrorLog = ini_get('error_log');

            if (ini_set('error_log', $logPath) === false) {
                throw new RuntimeException('Unable to redirect the policy-denial test log.');
            }

            $responses = [];

            try {
                foreach (['authenticate', 'resolve_tenant', 'authorize'] as $failureStage) {
                    $responses[] = handleDocumentPolicyRequest(
                        requestPolicyApplication(
                            new RequestPolicyTestTrace(),
                            $failureStage,
                            $fixture['retrieve'],
                        ),
                        '/accounts/42/documents/SecretDocumentMarker',
                    );
                }
            } finally {
                if (is_string($previousErrorLog)) {
                    ini_set('error_log', $previousErrorLog);
                }
            }

            $log = file_get_contents($logPath);

            if (
                !is_string($log)
                || $log !== ''
                || str_contains($responses[0]->body, 'CredentialSecretMarker')
                || str_contains($responses[1]->body, '9001001')
                || str_contains($responses[2]->body, 'SecretDocumentMarker')
                || $fixture['budget']->used() !== 0
                || $fixture['trace']->snapshot()['statements'] !== 0
            ) {
                throw new RuntimeException('Expected mapped policy denials to remain unlogged and redacted.');
            }
        },
        'unexpected document policy failures use the generic redacted boundary' => static function (): void {
            $fixture = requestPolicyRetrievalFixture('policy-unexpected-failure', 0);
            $trace = new RequestPolicyTestTrace();

            try {
                handleDocumentPolicyRequest(
                    requestPolicyApplication($trace, 'unexpected', $fixture['retrieve']),
                    '/accounts/42/documents/SecretDocumentMarker',
                );
            } catch (UnexpectedValueException) {
                $response = (new UnknownFailureBoundary())->respond();
            }

            if (
                !isset($response)
                || $response->status !== 500
                || $response->headers !== [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Cache-Control' => 'no-store',
                ]
                || $response->body
                    !== "{\"error\":{\"code\":\"internal_server_error\",\"message\":\"Internal server error.\"}}\n"
                || $trace->steps !== ['authenticate']
                || $fixture['budget']->used() !== 0
                || $fixture['trace']->snapshot()['statements'] !== 0
            ) {
                throw new RuntimeException('Expected an unexpected policy failure to receive the generic response.');
            }
        },
    ];
}

/**
 * @param 'authenticate'|'resolve_tenant'|'authorize'|'unexpected'|null $failureStage
 */
function requestPolicyApplication(
    RequestPolicyTestTrace $trace,
    ?string $failureStage,
    RetrieveAuthorizedDocument $retrieve,
): Application {
    $principal = AuthenticatedPrincipal::fromPositiveInteger(7);
    $authenticate = new class ($trace, $principal, $failureStage) implements AuthenticateDocumentRequest {
        public function __construct(
            private RequestPolicyTestTrace $trace,
            private AuthenticatedPrincipal $principal,
            private ?string $failureStage,
        ) {
        }

        public function authenticate(Request $request): AuthenticatedPrincipal
        {
            $this->trace->record('authenticate');

            if ($this->failureStage === 'authenticate') {
                throw new Unauthenticated(RequestPolicyTestTrace::PRIVATE_FAILURE);
            }

            if ($this->failureStage === 'unexpected') {
                throw new UnexpectedValueException(RequestPolicyTestTrace::PRIVATE_FAILURE);
            }

            return $this->principal;
        }
    };
    $resolveTenant = new class ($trace, $failureStage) implements ResolveDocumentTenant {
        public function __construct(
            private RequestPolicyTestTrace $trace,
            private ?string $failureStage,
        ) {
        }

        public function resolve(
            AuthenticatedPrincipal $principal,
            AccountId $accountId,
        ): ResolvedTenant {
            $this->trace->record('resolve_tenant');

            if ($this->failureStage === 'resolve_tenant') {
                throw new CrossTenant(RequestPolicyTestTrace::PRIVATE_FAILURE);
            }

            return ResolvedTenant::forAccount($accountId);
        }
    };
    $authorize = new class ($trace, $failureStage) implements AuthorizeGetDocument {
        public function __construct(
            private RequestPolicyTestTrace $trace,
            private ?string $failureStage,
        ) {
        }

        public function authorize(
            AuthenticatedPrincipal $principal,
            ResolvedTenant $tenant,
            DocumentKey $documentKey,
        ): void {
            $this->trace->record('authorize');

            if ($this->failureStage === 'authorize') {
                throw new Forbidden(RequestPolicyTestTrace::PRIVATE_FAILURE);
            }
        }
    };
    $recordingRetrieve = new class ($trace, $retrieve) implements RetrieveAuthorizedDocument {
        public function __construct(
            private RequestPolicyTestTrace $trace,
            private RetrieveAuthorizedDocument $retrieve,
        ) {
        }

        public function retrieve(
            AuthenticatedPrincipal $principal,
            ResolvedTenant $tenant,
            AccountId $accountId,
            DocumentKey $documentKey,
        ): ?DocumentDetails {
            $this->trace->recordRetrieval($principal, $tenant, $accountId, $documentKey);

            return $this->retrieve->retrieve($principal, $tenant, $accountId, $documentKey);
        }
    };

    return new Application(new Router(DocumentRoutes::create(
        $authenticate,
        $resolveTenant,
        $authorize,
        $recordingRetrieve,
        new class implements AuthorizeListDocuments {
            public function authorizeList(
                AuthenticatedPrincipal $principal,
                ResolvedTenant $tenant,
            ): void {
                throw new Forbidden('Item-policy fixture does not permit document listing.');
            }
        },
        Connection::connect('sqlite::memory:', new QueryBudget(1), new QueryTrace(1)),
    )));
}

/**
 * @param 'authenticate'|'resolve_tenant'|'authorize_list'|null $failureStage
 */
function requestPolicyListApplication(
    string $databasePath,
    RequestPolicyTestTrace $trace,
    ?string $failureStage,
    QueryBudget $budget,
    QueryTrace $queryTrace,
    int $principalId = 7,
    ?int $resolvedAccountId = null,
): Application {
    $principal = AuthenticatedPrincipal::fromPositiveInteger($principalId);
    $resolvedAccount = $resolvedAccountId === null
        ? null
        : AccountId::fromPositiveInteger($resolvedAccountId);
    $authenticate = new class ($trace, $principal, $failureStage) implements AuthenticateDocumentRequest {
        public function __construct(
            private RequestPolicyTestTrace $trace,
            private AuthenticatedPrincipal $principal,
            private ?string $failureStage,
        ) {
        }

        public function authenticate(Request $request): AuthenticatedPrincipal
        {
            $this->trace->record('authenticate');

            if ($this->failureStage === 'authenticate') {
                throw new Unauthenticated(RequestPolicyTestTrace::PRIVATE_FAILURE);
            }

            return $this->principal;
        }
    };
    $resolveTenant = new class ($trace, $failureStage, $resolvedAccount) implements ResolveDocumentTenant {
        public function __construct(
            private RequestPolicyTestTrace $trace,
            private ?string $failureStage,
            private ?AccountId $resolvedAccount,
        ) {
        }

        public function resolve(
            AuthenticatedPrincipal $principal,
            AccountId $accountId,
        ): ResolvedTenant {
            $this->trace->record('resolve_tenant');

            if ($this->failureStage === 'resolve_tenant') {
                throw new CrossTenant(RequestPolicyTestTrace::PRIVATE_FAILURE);
            }

            return ResolvedTenant::forAccount($this->resolvedAccount ?? $accountId);
        }
    };
    $authorize = new class ($trace, $failureStage) implements AuthorizeListDocuments {
        public function __construct(
            private RequestPolicyTestTrace $trace,
            private ?string $failureStage,
        ) {
        }

        public function authorizeList(
            AuthenticatedPrincipal $principal,
            ResolvedTenant $tenant,
        ): void {
            $this->trace->recordListAuthorization($principal, $tenant);

            if ($this->failureStage === 'authorize_list') {
                throw new Forbidden(RequestPolicyTestTrace::PRIVATE_FAILURE);
            }
        }
    };

    return new Application(new Router([
        new Route(
            'GET',
            '/accounts/{account_id:positive-int}/documents',
            new ListDocumentsHandler(
                $authenticate,
                $resolveTenant,
                $authorize,
                Connection::connect('sqlite:' . $databasePath, $budget, $queryTrace),
            ),
        ),
    ]));
}

/** @param array<string, mixed> $query */
function handleDocumentListPolicyRequest(Application $application, array $query): Response
{
    return (new RequestBoundary(
        requestReaderForBody('', 8_192),
        $application,
        requestPolicyErrorRegistry(),
    ))->handle(
        [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/accounts/42/documents',
            'HTTP_AUTHORIZATION' => 'Bearer CredentialSecretMarker',
        ],
        $query,
    );
}

/**
 * @param array<string, mixed> $query
 * @return array{
 *     status: int,
 *     headers: array<string, string>,
 *     body: string,
 *     document_keys: list<string>,
 *     categories: list<string>,
 *     sort_ranks: list<int>,
 *     next_cursor: string|null,
 *     steps: list<string>,
 *     principal_id: int|null,
 *     tenant_account_id: int|null,
 *     used: int,
 *     statements: int,
 *     failures: int,
 *     tracked_fingerprints: int,
 *     repeated_fingerprints: int,
 *     maximum_executions: int,
 *     fingerprint: string|null,
 *     trace_json: string
 * }
 */
function runDocumentListPageScenario(
    string $databasePath,
    array $query,
    int $requestedAccountId = 42,
    int $resolvedAccountId = 42,
    int $principalId = 7,
): array
{
    $budget = new QueryBudget(1);
    $queryTrace = new QueryTrace(1);
    $policyTrace = new RequestPolicyTestTrace();
    $response = requestPolicyListApplication(
        $databasePath,
        $policyTrace,
        null,
        $budget,
        $queryTrace,
        $principalId,
        $resolvedAccountId,
    )->handle(new Request(
        'GET',
        '/accounts/' . $requestedAccountId . '/documents',
        $query,
    ));
    $decoded = json_decode($response->body, true, 64, JSON_THROW_ON_ERROR);

    if (
        $response->status !== 200
        || $response->headers !== requestPolicyJsonHeaders()
        || !is_array($decoded)
        || count($decoded) !== 2
        || !array_key_exists('documents', $decoded)
        || !array_key_exists('next_cursor', $decoded)
    ) {
        throw new RuntimeException('Document listing returned an invalid page response.');
    }

    $documentValues = $decoded['documents'];
    $nextCursor = $decoded['next_cursor'];

    if (!is_array($documentValues) || !array_is_list($documentValues)) {
        throw new RuntimeException('Document listing returned a non-list documents value.');
    }

    if (
        $nextCursor !== null
        && (
            !is_string($nextCursor)
            || preg_match('/^v1:rank_(asc|desc):(0|[1-9][0-9]*):[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D', $nextCursor) !== 1
        )
    ) {
        throw new RuntimeException('Document listing returned an invalid cursor representation.');
    }

    $documentKeys = [];
    $categories = [];
    $sortRanks = [];

    foreach ($documentValues as $documentValue) {
        if (!is_array($documentValue)) {
            throw new RuntimeException('Document listing returned a non-object document representation.');
        }

        $row = [];

        foreach ($documentValue as $name => $value) {
            if (!is_string($name)) {
                throw new RuntimeException('Document listing returned a non-string document field name.');
            }

            $row[$name] = $value;
        }

        $document = DocumentSummary::fromDatabaseRow($row);
        $documentKeys[] = $document->documentKey->value;
        $categories[] = $document->category;
        $sortRanks[] = $document->sortRank;
    }

    $summary = $queryTrace->snapshot();

    return [
        'status' => $response->status,
        'headers' => $response->headers,
        'body' => $response->body,
        'document_keys' => $documentKeys,
        'categories' => $categories,
        'sort_ranks' => $sortRanks,
        'next_cursor' => is_string($nextCursor) ? $nextCursor : null,
        'steps' => $policyTrace->steps,
        'principal_id' => $policyTrace->listedPrincipal?->id,
        'tenant_account_id' => $policyTrace->listedTenant?->accountId->value,
        'used' => $budget->used(),
        'statements' => $summary['statements'],
        'failures' => $summary['failures'],
        'tracked_fingerprints' => $summary['tracked_fingerprints'],
        'repeated_fingerprints' => $summary['repeated_fingerprints'],
        'maximum_executions' => $summary['maximum_executions_per_fingerprint'],
        'fingerprint' => $summary['queries'][0]['fingerprint'] ?? null,
        'trace_json' => json_encode($summary, JSON_THROW_ON_ERROR),
    ];
}

/**
 * @param 'rank_asc'|'rank_desc' $order
 * @return array{
 *     document_keys: list<string>,
 *     page_sizes: list<int>,
 *     next_cursors: list<string|null>,
 *     second_page_body: string
 * }
 */
function traverseDocumentListFixture(string $databasePath, string $order): array
{
    $documentKeys = [];
    $pageSizes = [];
    $nextCursors = [];
    $cursor = null;
    $secondPageBody = null;

    for ($pageNumber = 1; $pageNumber <= 10; $pageNumber++) {
        $query = ['order' => $order];

        if ($cursor !== null) {
            $query['cursor'] = $cursor;
        }

        $page = runDocumentListPageScenario($databasePath, $query);

        if (
            $page['used'] !== 1
            || $page['statements'] !== 1
            || $page['failures'] !== 0
            || $page['tracked_fingerprints'] !== 1
            || $page['repeated_fingerprints'] !== 0
            || $page['maximum_executions'] !== 1
        ) {
            throw new RuntimeException('Every traversed document page must use one fresh bounded statement.');
        }

        if ($pageNumber === 2) {
            $secondPageBody = $page['body'];
        }

        $documentKeys = [...$documentKeys, ...$page['document_keys']];
        $pageSizes[] = count($page['document_keys']);
        $nextCursors[] = $page['next_cursor'];
        $cursor = $page['next_cursor'];

        if ($cursor === null) {
            break;
        }
    }

    if (!is_string($secondPageBody) || $cursor !== null) {
        throw new RuntimeException('Document traversal did not reach a stable final page.');
    }

    return [
        'document_keys' => $documentKeys,
        'page_sizes' => $pageSizes,
        'next_cursors' => $nextCursors,
        'second_page_body' => $secondPageBody,
    ];
}

/** @return list<string> */
function documentListFixtureKeys(int $count): array
{
    if ($count < 0 || $count > 500) {
        throw new InvalidArgumentException('Document-list fixture key count must be between 0 and 500.');
    }

    $keys = [];

    for ($number = 1; $number <= $count; $number++) {
        $keys[] = sprintf('Doc_%03d', $number);
    }

    return $keys;
}

/** @return array<string, array<string, mixed>> */
function invalidDocumentListQueries(): array
{
    $invalidUtf8 = "\xB1";

    return [
        'unknown_field' => ['page' => '1'],
        'unknown_field_with_known_fields' => ['order' => 'rank_asc', 'extra' => 'value'],
        'integer_order' => ['order' => 1],
        'list_order' => ['order' => ['rank_asc']],
        'nested_order' => ['order' => [['rank_asc']]],
        'unsupported_order' => ['order' => 'newest'],
        'sql_shaped_order' => ['order' => 'rank_asc; DROP TABLE documents'],
        'scalar_categories' => ['categories' => 'alpha'],
        'associative_categories' => ['categories' => ['category' => 'alpha']],
        'nested_categories' => ['categories' => [['alpha']]],
        'integer_category' => ['categories' => [7]],
        'boolean_category' => ['categories' => [true]],
        'null_category' => ['categories' => [null]],
        'duplicate_category' => ['categories' => ['alpha', 'alpha']],
        'empty_category_with_value' => ['categories' => ['alpha', '']],
        'oversized_category' => ['categories' => [str_repeat('a', 65)]],
        'invalid_utf8_category' => ['categories' => [$invalidUtf8]],
        'nul_category' => ['categories' => ["alpha\0beta"]],
        'newline_category' => ['categories' => ["alpha\nbeta"]],
        'delete_category' => ['categories' => ["alpha\x7Fbeta"]],
        'four_categories' => ['categories' => ['alpha', 'beta', 'gamma', 'delta']],
        'integer_cursor' => ['cursor' => 7],
        'list_cursor' => ['cursor' => ['v1:rank_asc:0:Doc_001']],
        'empty_cursor' => ['cursor' => ''],
        'wrong_version_cursor' => ['cursor' => 'v2:rank_asc:0:Doc_001'],
        'missing_cursor_key' => ['cursor' => 'v1:rank_asc:0'],
        'extra_cursor_component' => ['cursor' => 'v1:rank_asc:0:Doc_001:extra'],
        'leading_zero_cursor_rank' => ['cursor' => 'v1:rank_asc:01:Doc_001'],
        'negative_cursor_rank' => ['cursor' => 'v1:rank_asc:-1:Doc_001'],
        'overflow_cursor_rank' => ['cursor' => 'v1:rank_asc:1000001:Doc_001'],
        'integer_overflow_cursor_rank' => [
            'cursor' => 'v1:rank_asc:999999999999999999999999:Doc_001',
        ],
        'invalid_cursor_document_key' => ['cursor' => 'v1:rank_asc:0:Doc/001'],
        'ascending_cursor_with_descending_order' => [
            'order' => 'rank_desc',
            'cursor' => 'v1:rank_asc:0:Doc_001',
        ],
        'descending_cursor_with_ascending_order' => [
            'order' => 'rank_asc',
            'cursor' => 'v1:rank_desc:0:Doc_001',
        ],
    ];
}

function handleDocumentPolicyRequest(
    Application $application,
    string $path,
    ?ErrorResponseRegistry $registry = null,
): Response {
    return (new RequestBoundary(
        requestReaderForBody('', 8_192),
        $application,
        $registry ?? requestPolicyErrorRegistry(),
    ))->handle(
        [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => $path,
            'HTTP_AUTHORIZATION' => 'Bearer CredentialSecretMarker',
        ],
        [],
    );
}

/** @return array<string, string> */
function requestPolicyJsonHeaders(): array
{
    return [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'private, no-store',
    ];
}

function requestPolicyErrorRegistry(): ErrorResponseRegistry
{
    $forbidden = new Response(
        403,
        requestPolicyJsonHeaders(),
        "{\"error\":{\"code\":\"forbidden\",\"message\":\"Request is forbidden.\"}}\n",
    );

    return new ErrorResponseRegistry([
        \PHPThis\Http\InvalidRequest::class => new Response(
            400,
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'no-store',
            ],
            "{\"error\":{\"code\":\"invalid_request\",\"message\":\"Request is invalid.\"}}\n",
        ),
        Unauthenticated::class => new Response(
            401,
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'private, no-store',
                'WWW-Authenticate' => 'Bearer',
            ],
            "{\"error\":{\"code\":\"unauthenticated\",\"message\":\"Authentication is required.\"}}\n",
        ),
        CrossTenant::class => $forbidden,
        Forbidden::class => $forbidden,
    ]);
}

/**
 * @return array{
 *     retrieve: SelectAuthorizedDocument,
 *     budget: QueryBudget,
 *     trace: QueryTrace
 * }
 */
function requestPolicyRetrievalFixture(string $name, int $extraDocuments): array
{
    $databasePath = createRequestPolicyDatabaseFixture($name, $extraDocuments);
    $budget = new QueryBudget(1);
    $trace = new QueryTrace(1);

    return [
        'retrieve' => new SelectAuthorizedDocument(
            Connection::connect('sqlite:' . $databasePath, $budget, $trace),
        ),
        'budget' => $budget,
        'trace' => $trace,
    ];
}

/**
 * @return array{
 *     status: int,
 *     headers: array<string, string>,
 *     body: string,
 *     steps: list<string>,
 *     principal_id: int,
 *     tenant_account_id: int,
 *     requested_account_id: int,
 *     document_key: string,
 *     used: int,
 *     statements: int,
 *     trace_json: string
 * }
 */
function runPermittedDocumentPolicyScenario(string $name, int $extraDocuments): array
{
    $fixture = requestPolicyRetrievalFixture($name, $extraDocuments);
    $trace = new RequestPolicyTestTrace();
    $response = handleDocumentPolicyRequest(
        requestPolicyApplication($trace, null, $fixture['retrieve']),
        '/accounts/42/documents/Doc_9-z',
    );

    if (
        $trace->retrievedPrincipal === null
        || $trace->retrievedTenant === null
        || $trace->retrievedAccountId === null
        || $trace->retrievedDocumentKey === null
    ) {
        throw new RuntimeException('Expected explicit authority values at protected retrieval.');
    }

    return [
        'status' => $response->status,
        'headers' => $response->headers,
        'body' => $response->body,
        'steps' => $trace->steps,
        'principal_id' => $trace->retrievedPrincipal->id,
        'tenant_account_id' => $trace->retrievedTenant->accountId->value,
        'requested_account_id' => $trace->retrievedAccountId->value,
        'document_key' => $trace->retrievedDocumentKey->value,
        'used' => $fixture['budget']->used(),
        'statements' => $fixture['trace']->snapshot()['statements'],
        'trace_json' => json_encode($fixture['trace']->snapshot(), JSON_THROW_ON_ERROR),
    ];
}

function createRequestPolicyDatabaseFixture(string $name, int $extraDocuments): string
{
    if ($extraDocuments < 0 || $extraDocuments > 500) {
        throw new InvalidArgumentException('Extra document count must be between 0 and 500.');
    }

    $directory = __DIR__ . '/../tmp/application-tests';

    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the request-policy test directory.');
    }

    $databasePath = $directory . '/' . $name . '.sqlite';

    if (is_file($databasePath) && !unlink($databasePath)) {
        throw new RuntimeException('Unable to reset a request-policy test database.');
    }

    $connection = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(6),
        new QueryTrace(6),
    );
    $connection->executeStatement(
        <<<'SQL'
            CREATE TABLE documents (
                account_id INTEGER NOT NULL,
                document_key TEXT NOT NULL,
                title TEXT NOT NULL,
                category TEXT NOT NULL,
                sort_rank INTEGER NOT NULL,
                PRIMARY KEY (account_id, document_key)
            )
            SQL,
    );
    $connection->executeStatement(
        <<<'SQL'
            CREATE TABLE account_memberships (
                principal_id INTEGER NOT NULL,
                account_id INTEGER NOT NULL,
                PRIMARY KEY (principal_id, account_id)
            )
            SQL,
    );
    $connection->executeStatement(
        <<<'SQL'
            INSERT INTO documents (account_id, document_key, title, category, sort_rank)
            VALUES
                (
                    :permitted_account_id,
                    :permitted_document_key,
                    :permitted_title,
                    :permitted_category,
                    :permitted_sort_rank
                ),
                (
                    :other_account_id,
                    :other_document_key,
                    :other_title,
                    :other_category,
                    :other_sort_rank
                )
            SQL,
        [
            'permitted_account_id' => 42,
            'permitted_document_key' => 'Doc_9-z',
            'permitted_title' => 'Example document',
            'permitted_category' => 'general',
            'permitted_sort_rank' => 9,
            'other_account_id' => 43,
            'other_document_key' => 'OtherDocument',
            'other_title' => 'Other tenant document',
            'other_category' => 'general',
            'other_sort_rank' => 1,
        ],
    );
    $connection->executeStatement(
        <<<'SQL'
            INSERT INTO account_memberships (principal_id, account_id)
            VALUES (:principal_id, :account_id)
            SQL,
        ['principal_id' => 7, 'account_id' => 42],
    );

    if ($extraDocuments > 0) {
        $connection->executeStatement(
            <<<'SQL'
                WITH RECURSIVE sequence(value) AS (
                    SELECT 1
                    UNION ALL
                    SELECT value + 1
                    FROM sequence
                    WHERE value < :extra_document_count
                )
                INSERT INTO documents (
                    account_id,
                    document_key,
                    title,
                    category,
                    sort_rank
                )
                SELECT
                    :extra_account_id,
                    'Extra_' || sequence.value,
                    'Extra title ' || sequence.value,
                    CASE sequence.value % 3
                        WHEN 0 THEN 'alpha'
                        WHEN 1 THEN 'beta'
                        ELSE 'gamma'
                    END,
                    CAST((sequence.value - 1) / 17 AS INTEGER)
                FROM sequence
                SQL,
            [
                'extra_document_count' => $extraDocuments,
                'extra_account_id' => 42,
            ],
        );
    }

    return $databasePath;
}

function createDocumentListDatabaseFixture(
    string $name,
    int $tenantDocumentCount,
    bool $includeSqlShapedCategory = false,
    bool $includeFourthTenantCategory = false,
): string {
    if ($tenantDocumentCount < 0 || $tenantDocumentCount > 500) {
        throw new InvalidArgumentException('Document-list fixture count must be between 0 and 500.');
    }

    $directory = __DIR__ . '/../tmp/application-tests';

    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the document-list test directory.');
    }

    $databasePath = $directory . '/' . $name . '.sqlite';

    if (is_file($databasePath) && !unlink($databasePath)) {
        throw new RuntimeException('Unable to reset a document-list test database.');
    }

    $connection = Connection::connect(
        'sqlite:' . $databasePath,
        new QueryBudget(7),
        new QueryTrace(7),
    );
    $connection->executeStatement(
        <<<'SQL'
            CREATE TABLE documents (
                account_id INTEGER NOT NULL,
                document_key TEXT NOT NULL,
                title TEXT NOT NULL,
                category TEXT NOT NULL,
                sort_rank INTEGER NOT NULL,
                PRIMARY KEY (account_id, document_key)
            )
            SQL,
    );
    $connection->executeStatement(
        <<<'SQL'
            CREATE TABLE account_memberships (
                principal_id INTEGER NOT NULL,
                account_id INTEGER NOT NULL,
                PRIMARY KEY (principal_id, account_id)
            )
            SQL,
    );
    $connection->executeStatement(
        <<<'SQL'
            INSERT INTO account_memberships (principal_id, account_id)
            VALUES
                (:permitted_principal_id, :permitted_account_id),
                (:other_principal_id, :other_account_id)
            SQL,
        [
            'permitted_principal_id' => 7,
            'permitted_account_id' => 42,
            'other_principal_id' => 9,
            'other_account_id' => 43,
        ],
    );
    $connection->executeStatement(
        <<<'SQL'
            INSERT INTO documents (account_id, document_key, title, category, sort_rank)
            VALUES (
                :account_id,
                :document_key,
                :title,
                :category,
                :sort_rank
            )
            SQL,
        [
            'account_id' => 43,
            'document_key' => 'OtherTenant',
            'title' => 'Other tenant document',
            'category' => 'alpha',
            'sort_rank' => 0,
        ],
    );

    if ($tenantDocumentCount > 0) {
        $connection->executeStatement(
            <<<'SQL'
                WITH RECURSIVE sequence(value) AS (
                    SELECT 1
                    UNION ALL
                    SELECT value + 1
                    FROM sequence
                    WHERE value < :document_count
                )
                INSERT INTO documents (
                    account_id,
                    document_key,
                    title,
                    category,
                    sort_rank
                )
                SELECT
                    :account_id,
                    printf('Doc_%03d', sequence.value),
                    'Document ' || sequence.value,
                    CASE sequence.value % 3
                        WHEN 0 THEN 'alpha'
                        WHEN 1 THEN 'beta'
                        ELSE 'gamma'
                    END,
                    CAST((sequence.value - 1) / 17 AS INTEGER)
                FROM sequence
                SQL,
            ['document_count' => $tenantDocumentCount, 'account_id' => 42],
        );
    }

    if ($includeSqlShapedCategory) {
        $connection->executeStatement(
            <<<'SQL'
                INSERT INTO documents (
                    account_id,
                    document_key,
                    title,
                    category,
                    sort_rank
                )
                VALUES (
                    :account_id,
                    :document_key,
                    :title,
                    :category,
                    :sort_rank
                )
                SQL,
            [
                'account_id' => 42,
                'document_key' => 'SqlLookingData',
                'title' => 'SQL-looking data remains data',
                'category' => "x') OR 1=1 --",
                'sort_rank' => 900,
            ],
        );
    }

    if ($includeFourthTenantCategory) {
        $connection->executeStatement(
            <<<'SQL'
                INSERT INTO documents (
                    account_id,
                    document_key,
                    title,
                    category,
                    sort_rank
                )
                VALUES (
                    :account_id,
                    :document_key,
                    :title,
                    :category,
                    :sort_rank
                )
                SQL,
            [
                'account_id' => 42,
                'document_key' => 'FourthCategory',
                'title' => 'Fourth tenant category',
                'category' => 'delta',
                'sort_rank' => 1,
            ],
        );
    }

    return $databasePath;
}
