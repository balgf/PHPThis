<?php

declare(strict_types=1);

namespace Example;

use Example\Cli\ApplicationCommands;
use Example\Cli\LocalScheduleLock;
use Example\DocumentFiles\DocumentFileNotFound;
use Example\DocumentFiles\LocalDocumentFiles;
use Example\Documents\CrossTenant;
use Example\Documents\DenyAllDocumentAuthentication;
use Example\Documents\DenyAllDocumentAuthorization;
use Example\Documents\DenyAllDocumentTenantResolution;
use Example\Documents\Forbidden;
use Example\Documents\Unauthenticated;
use Example\Jobs\UserWelcomeJobClock;
use Example\Observability\CorrelationId;
use Example\Observability\ErrorLogRequestSummarySink;
use Example\Observability\QuerySummarySource;
use Example\Observability\TerminalRequestCoordinator;
use PHPThis\Application;
use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use PHPThis\Http\ErrorResponseRegistry;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\RequestBodyTooLarge;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\RequestReader;
use PHPThis\Http\Response;
use PHPThis\Http\UnsupportedMediaType;
use PHPThis\Http\UnknownFailureBoundary;
use PHPThis\Routing\Router;
use RuntimeException;

final readonly class ApplicationComposition
{
    private string $databasePath;

    public function __construct(ApplicationDatabasePath $databasePath)
    {
        if (!is_file($databasePath->value)) {
            throw new RuntimeException('The application database is unavailable.');
        }

        $resolvedPath = realpath($databasePath->value);

        if (!is_string($resolvedPath)) {
            throw new RuntimeException('The application database path cannot be resolved.');
        }

        $this->databasePath = $resolvedPath;
    }

    public function http(): TerminalRequestCoordinator
    {
        $dsn = 'sqlite:' . $this->databasePath;
        $listUsersBudget = new QueryBudget(1);
        $listUsersTrace = new QueryTrace(1);
        $getUserBudget = new QueryBudget(1);
        $getUserTrace = new QueryTrace(1);
        $createUserBudget = new QueryBudget(3);
        $createUserTrace = new QueryTrace(3);
        $getDocumentBudget = new QueryBudget(1);
        $getDocumentTrace = new QueryTrace(1);
        $listDocumentsBudget = new QueryBudget(1);
        $listDocumentsTrace = new QueryTrace(1);
        $listUsersConnection = Connection::connect($dsn, $listUsersBudget, $listUsersTrace);
        $getUserConnection = Connection::connect($dsn, $getUserBudget, $getUserTrace);
        $createUserConnection = Connection::connect($dsn, $createUserBudget, $createUserTrace);
        $getDocumentConnection = Connection::connect($dsn, $getDocumentBudget, $getDocumentTrace);
        $listDocumentsConnection = Connection::connect(
            $dsn,
            $listDocumentsBudget,
            $listDocumentsTrace,
        );
        $documentAuthorization = new DenyAllDocumentAuthorization();
        $application = new Application(new Router(Routes::create(
            $listUsersConnection,
            $getUserConnection,
            $createUserConnection,
            $getDocumentConnection,
            $listDocumentsConnection,
            new DenyAllDocumentAuthentication(),
            new DenyAllDocumentTenantResolution(),
            $documentAuthorization,
            $documentAuthorization,
            new LocalDocumentFiles($this->databasePath . '.files'),
        )));
        $jsonHeaders = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ];
        $privateJsonHeaders = [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'private, no-store',
        ];
        $forbiddenResponse = new Response(
            403,
            $privateJsonHeaders,
            "{\"error\":{\"code\":\"forbidden\",\"message\":\"Request is forbidden.\"}}\n",
        );
        $errorResponses = new ErrorResponseRegistry([
            Unauthenticated::class => new Response(
                401,
                [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Cache-Control' => 'private, no-store',
                    'WWW-Authenticate' => 'Bearer',
                ],
                "{\"error\":{\"code\":\"unauthenticated\",\"message\":\"Authentication is required.\"}}\n",
            ),
            Forbidden::class => $forbiddenResponse,
            CrossTenant::class => $forbiddenResponse,
            InvalidRequest::class => new Response(
                400,
                $jsonHeaders,
                "{\"error\":{\"code\":\"invalid_request\",\"message\":\"Request is invalid.\"}}\n",
            ),
            RequestBodyTooLarge::class => new Response(
                413,
                $jsonHeaders,
                "{\"error\":{\"code\":\"request_body_too_large\",\"message\":\"Request body is too large.\"}}\n",
            ),
            UnsupportedMediaType::class => new Response(
                415,
                $jsonHeaders,
                "{\"error\":{\"code\":\"unsupported_media_type\",\"message\":\"Content-Type is unsupported.\"}}\n",
            ),
            DocumentFileNotFound::class => new Response(
                404,
                $privateJsonHeaders,
                "{\"error\":{\"code\":\"document_file_not_found\",\"message\":\"Document file was not found.\"}}\n",
            ),
        ]);

        return new TerminalRequestCoordinator(
            new RequestBoundary(
                new RequestReader(8_192, 'php://input', 2_097_152),
                $application,
                $errorResponses,
            ),
            new UnknownFailureBoundary(),
            CorrelationId::generate(),
            new ErrorLogRequestSummarySink(),
            [
                new QuerySummarySource('list_users', $listUsersBudget, $listUsersTrace),
                new QuerySummarySource('get_user', $getUserBudget, $getUserTrace),
                new QuerySummarySource('create_user', $createUserBudget, $createUserTrace),
                new QuerySummarySource('get_document', $getDocumentBudget, $getDocumentTrace),
                new QuerySummarySource(
                    'list_documents',
                    $listDocumentsBudget,
                    $listDocumentsTrace,
                ),
            ],
        );
    }

    public function commands(UserWelcomeJobClock $clock): ApplicationCommands
    {
        return new ApplicationCommands(
            $this->databasePath,
            $clock,
            new LocalScheduleLock($this->databasePath . '.schedule.lock'),
        );
    }
}
