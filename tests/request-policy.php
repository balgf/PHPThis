<?php

declare(strict_types=1);

use Example\Documents\DocumentRoutes;
use Example\Documents\GetDocument\AccountId;
use Example\Documents\GetDocument\AuthenticateGetDocumentRequest;
use Example\Documents\GetDocument\AuthenticatedPrincipal;
use Example\Documents\GetDocument\AuthorizeGetDocument;
use Example\Documents\GetDocument\CrossTenant;
use Example\Documents\GetDocument\DocumentDetails;
use Example\Documents\GetDocument\DocumentKey;
use Example\Documents\GetDocument\Forbidden;
use Example\Documents\GetDocument\ResolveGetDocumentTenant;
use Example\Documents\GetDocument\ResolvedTenant;
use Example\Documents\GetDocument\RetrieveAuthorizedDocument;
use Example\Documents\GetDocument\SelectAuthorizedDocument;
use Example\Documents\GetDocument\Unauthenticated;
use PHPThis\Application;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use PHPThis\Http\ErrorResponseRegistry;
use PHPThis\Http\Request;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\Response;
use PHPThis\Http\UnknownFailureBoundary;
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
}

/** @return array<string, Closure(): void> */
function requestPolicyTests(): array
{
    return [
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
            $logPath = __DIR__ . '/../tmp/request-policy-unexpected.log';

            if (file_put_contents($logPath, '') !== 0) {
                throw new RuntimeException('Unable to reset the unexpected-policy test log.');
            }

            $previousErrorLog = ini_get('error_log');

            if (ini_set('error_log', $logPath) === false) {
                throw new RuntimeException('Unable to redirect the unexpected-policy test log.');
            }

            try {
                try {
                    handleDocumentPolicyRequest(
                        requestPolicyApplication($trace, 'unexpected', $fixture['retrieve']),
                        '/accounts/42/documents/SecretDocumentMarker',
                    );
                } catch (UnexpectedValueException $failure) {
                    $response = (new UnknownFailureBoundary())->logAndRespond($failure);
                }
            } finally {
                if (is_string($previousErrorLog)) {
                    ini_set('error_log', $previousErrorLog);
                }
            }

            $log = file_get_contents($logPath);

            if (
                !isset($response)
                || !is_string($log)
                || substr_count(
                    $log,
                    'phpthis.request.unhandled exception=UnexpectedValueException',
                ) !== 1
                || str_contains($log, 'CredentialSecretMarker')
                || str_contains($log, '9001001')
                || str_contains($log, 'SecretDocumentMarker')
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
                throw new RuntimeException('Expected an unexpected policy failure to be logged once without secrets.');
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
    $authenticate = new class ($trace, $principal, $failureStage) implements AuthenticateGetDocumentRequest {
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
    $resolveTenant = new class ($trace, $failureStage) implements ResolveGetDocumentTenant {
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
    )));
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
            INSERT INTO documents (account_id, document_key, title)
            VALUES
                (:permitted_account_id, :permitted_document_key, :permitted_title),
                (:other_account_id, :other_document_key, :other_title)
            SQL,
        [
            'permitted_account_id' => 42,
            'permitted_document_key' => 'Doc_9-z',
            'permitted_title' => 'Example document',
            'other_account_id' => 43,
            'other_document_key' => 'OtherDocument',
            'other_title' => 'Other tenant document',
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
                INSERT INTO documents (account_id, document_key, title)
                SELECT
                    :extra_account_id,
                    'Extra_' || sequence.value,
                    'Extra title ' || sequence.value
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
