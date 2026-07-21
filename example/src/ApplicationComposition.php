<?php

declare(strict_types=1);

namespace Example;

use Example\Cli\ApplicationCommands;
use Example\DocumentFiles\DocumentFileNotFound;
use Example\DocumentFiles\LocalDocumentFiles;
use Example\Accounts\CrossTenant;
use Example\Accounts\DenyAllAccountAuthentication;
use Example\Accounts\DenyAllAccountAuthorization;
use Example\Accounts\DenyAllAccountTenantResolution;
use Example\Accounts\Forbidden;
use Example\Documents\GetDocument\DocumentDetailsCacheTrace;
use Example\Documents\GetDocument\RedisDocumentDetailsCache;
use Example\Documents\GetDocument\SelectAuthorizedDocument;
use Example\Accounts\Unauthenticated;
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
use InvalidArgumentException;
use RuntimeException;

final readonly class ApplicationComposition
{
    private string $databasePath;

    public function __construct(
        ApplicationDatabasePath $databasePath,
        private string $cacheRedisHost = '127.0.0.1',
        private int $cacheRedisPort = 6379,
        private string $leaseRedisHost = '127.0.0.1',
        private int $leaseRedisPort = 6380,
        private string $redisEnvironment = 'development',
    ) {
        if (
            !self::validRedisHost($cacheRedisHost)
            || !self::validRedisHost($leaseRedisHost)
            || $cacheRedisPort < 1
            || $cacheRedisPort > 65_535
            || $leaseRedisPort < 1
            || $leaseRedisPort > 65_535
            || ($cacheRedisHost === $leaseRedisHost && $cacheRedisPort === $leaseRedisPort)
            || preg_match('/\A[a-z][a-z0-9_-]{0,31}\z/D', $redisEnvironment) !== 1
        ) {
            throw new InvalidArgumentException(
                'Application Redis cache and lease endpoints must be valid and operationally separate.',
            );
        }

        $this->databasePath = $databasePath->value;
    }

    public function http(): TerminalRequestCoordinator
    {
        $databasePath = $this->existingDatabasePath();
        $dsn = 'sqlite:' . $databasePath;
        $listUsersBudget = new QueryBudget(1);
        $listUsersTrace = new QueryTrace(1);
        $getUserBudget = new QueryBudget(1);
        $getUserTrace = new QueryTrace(1);
        $createUserBudget = new QueryBudget(4);
        $createUserTrace = new QueryTrace(4);
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
        $accountAuthorization = new DenyAllAccountAuthorization();
        $documentCacheTrace = new DocumentDetailsCacheTrace();
        $retrieveDocument = new RedisDocumentDetailsCache(
            $this->cacheRedisHost,
            $this->cacheRedisPort,
            0,
            new SelectAuthorizedDocument($getDocumentConnection),
            $this->redisEnvironment,
            30_000,
            $documentCacheTrace,
        );
        $application = new Application(new Router(Routes::create(
            $listUsersConnection,
            $getUserConnection,
            $createUserConnection,
            $retrieveDocument,
            $listDocumentsConnection,
            new DenyAllAccountAuthentication(),
            new DenyAllAccountTenantResolution(),
            $accountAuthorization,
            $accountAuthorization,
            $accountAuthorization,
            new LocalDocumentFiles($databasePath . '.files'),
        )));
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
                $privateJsonHeaders,
                "{\"error\":{\"code\":\"invalid_request\",\"message\":\"Request is invalid.\"}}\n",
            ),
            RequestBodyTooLarge::class => new Response(
                413,
                $privateJsonHeaders,
                "{\"error\":{\"code\":\"request_body_too_large\",\"message\":\"Request body is too large.\"}}\n",
            ),
            UnsupportedMediaType::class => new Response(
                415,
                $privateJsonHeaders,
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
            $documentCacheTrace,
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
            $this->leaseRedisHost,
            $this->leaseRedisPort,
            0,
            'phpthis_example:' . $this->redisEnvironment . ':schedule_run:v1',
        );
    }

    private static function validRedisHost(string $host): bool
    {
        return $host !== ''
            && strlen($host) <= 255
            && preg_match('/[\x00-\x20\x7f]/D', $host) !== 1;
    }

    private function existingDatabasePath(): string
    {
        if (!is_file($this->databasePath)) {
            throw new RuntimeException('The application database is unavailable.');
        }

        $resolvedPath = realpath($this->databasePath);

        if (!is_string($resolvedPath)) {
            throw new RuntimeException('The application database path cannot be resolved.');
        }

        return $resolvedPath;
    }
}
