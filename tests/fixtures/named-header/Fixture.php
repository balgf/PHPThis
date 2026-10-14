<?php

declare(strict_types=1);

namespace NamedHeaderRecipe;

use Example\Accounts\AccountId;
use Example\Accounts\AuthenticatedPrincipal;
use Example\Accounts\CrossTenant;
use Example\Accounts\Forbidden;
use Example\Accounts\ResolveAccountTenant;
use Example\Accounts\ResolvedTenant;
use Example\Documents\DocumentKey;
use Example\Documents\GetDocument\AuthorizeGetDocument;
use Example\Documents\GetDocument\DocumentDetails;
use Example\Documents\GetDocument\DocumentDetailsCacheTrace;
use Example\Documents\GetDocument\GetDocumentHandler;
use Example\Documents\GetDocument\RetrieveAuthorizedDocument;
use Example\Observability\CorrelationId;
use Example\Observability\QuerySummarySource;
use Example\Observability\RequestSummary;
use Example\Observability\RequestSummarySink;
use Example\Observability\TerminalRequestCoordinator;
use PHPThis\Application;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use PHPThis\Http\ErrorResponseRegistry;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\RequestReader;
use PHPThis\Http\Response;
use PHPThis\Http\UnknownFailureBoundary;
use PHPThis\Routing\Route;
use PHPThis\Routing\Router;
use RuntimeException;

require_once __DIR__ . '/MachineCredentialRejected.php';
require_once __DIR__ . '/MachineVerificationUnavailable.php';
require_once __DIR__ . '/MachineCredential.php';
require_once __DIR__ . '/VerifyMachineCredential.php';
require_once __DIR__ . '/AuthenticateMachineRequest.php';

// Synthetic policy probe only. This is not a credential issuer or production verifier.
final class MachinePolicyFixture implements VerifyMachineCredential, ResolveAccountTenant, AuthorizeGetDocument, RetrieveAuthorizedDocument, RequestSummarySink
{
    public const string CREDENTIAL = 'mk1_aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    /** @var list<string> */
    public array $steps = [];
    public readonly QueryBudget $budget;
    public readonly QueryTrace $trace;
    public ?RequestSummary $summary = null;
    public int $summaries = 0;
    private readonly Connection $protectedRead;

    public function __construct(private readonly string $mode = 'accepted')
    {
        $this->budget = new QueryBudget(1);
        $this->trace = new QueryTrace(1);
        $this->protectedRead = Connection::connect('sqlite::memory:', $this->budget, $this->trace);
    }

    public function verify(MachineCredential $credential): AuthenticatedPrincipal
    {
        $this->steps[] = 'authenticate';
        if (!hash_equals(self::CREDENTIAL, $credential->value)) {
            throw new MachineCredentialRejected('PrivateVerifierMarker');
        }
        if (in_array($this->mode, ['outage', 'malformed_provider'], true)) {
            throw new MachineVerificationUnavailable('PrivateVerifierMarker');
        }
        if ($this->mode === 'unexpected') {
            throw new RuntimeException('PrivateVerifierMarker');
        }
        if (!in_array($this->mode, ['accepted', 'cross_tenant', 'scope_denied'], true)) {
            throw new MachineCredentialRejected('PrivateVerifierMarker');
        }
        return AuthenticatedPrincipal::fromPositiveInteger(7);
    }

    public function resolve(AuthenticatedPrincipal $principal, AccountId $accountId): ResolvedTenant
    {
        $this->steps[] = 'resolve_tenant';
        if ($this->mode === 'cross_tenant') {
            throw new CrossTenant('PrivateVerifierMarker');
        }
        return ResolvedTenant::forAccount($accountId);
    }

    public function authorize(AuthenticatedPrincipal $principal, ResolvedTenant $tenant, DocumentKey $documentKey): void
    {
        $this->steps[] = 'authorize';
        if ($this->mode === 'scope_denied') {
            throw new Forbidden('PrivateVerifierMarker');
        }
    }

    public function retrieve(AuthenticatedPrincipal $principal, ResolvedTenant $tenant, AccountId $accountId, DocumentKey $documentKey): DocumentDetails
    {
        $this->steps[] = 'retrieve';
        if ($principal->id !== 7 || $tenant->accountId !== $accountId || $accountId->value !== 42 || $documentKey->value !== 'Doc_Visible') {
            throw new RuntimeException('Fixture received different authority values.');
        }
        $row = $this->protectedRead->selectOneRow('SELECT :title AS title', ['title' => 'Machine document']);
        return DocumentDetails::fromDatabaseRow($row ?? throw new RuntimeException('Fixture read returned no row.'));
    }

    public function emit(RequestSummary $summary): void
    {
        ++$this->summaries;
        $this->summary = $summary;
    }

    public function coordinator(RequestReader $reader, ?RequestSummarySink $sink = null): TerminalRequestCoordinator
    {
        $headers = ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'private, no-store'];
        $forbidden = new Response(403, $headers, "{\"error\":{\"code\":\"forbidden\",\"message\":\"Request is forbidden.\"}}\n");
        return new TerminalRequestCoordinator(
            new RequestBoundary(
                $reader,
                new Application(new Router([new Route(
                    'GET', '/accounts/{account_id:positive-int}/documents/{document_key:token}',
                    new GetDocumentHandler(new AuthenticateMachineRequest($this), $this, $this, $this),
                )])),
                new ErrorResponseRegistry([
                    MachineCredentialRejected::class => $forbidden,
                    Forbidden::class => $forbidden,
                    CrossTenant::class => $forbidden,
                    MachineVerificationUnavailable::class => new Response(503, $headers, "{\"error\":{\"code\":\"service_unavailable\",\"message\":\"Service is unavailable.\"}}\n"),
                    InvalidRequest::class => new Response(400, $headers, "{\"error\":{\"code\":\"invalid_request\",\"message\":\"Request is invalid.\"}}\n"),
                ]),
            ),
            new UnknownFailureBoundary(), CorrelationId::generate(), $sink ?? $this,
            new DocumentDetailsCacheTrace(), [new QuerySummarySource('machine_document', $this->budget, $this->trace)],
        );
    }
}
