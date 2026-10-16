<?php

declare(strict_types=1);

namespace MetadataS3Recipe;

use App\Observability\CorrelationId;
use App\Observability\QuerySummarySource;
use App\Observability\RequestSummarySink;
use App\Observability\TerminalRequestCoordinator;
use PHPThis\Application;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use PHPThis\Http\ErrorResponseRegistry;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\MultipartLimits;
use PHPThis\Http\RequestBodyTooLarge;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\RequestReader;
use PHPThis\Http\Response;
use PHPThis\Http\UnknownFailureBoundary;
use PHPThis\Http\UnsupportedMediaType;
use PHPThis\Routing\Route;
use PHPThis\Routing\Router;

/** Reuse the starter's unchanged application-owned terminal coordinator. */
function documentCoordinator(
    DocumentUploadPolicy $policy, DocumentReceiver $receiver, QueryBudget $budget, QueryTrace $trace, RequestSummarySink $sink,
): TerminalRequestCoordinator {
    $headers = ['Content-Type' => 'text/plain', 'Cache-Control' => 'private, no-store'];
    return new TerminalRequestCoordinator(new RequestBoundary(
        new RequestReader(131_072, 'php://input', new MultipartLimits(131_072, 65_536, 65_536, 1024, 1153)),
        new Application(new Router([new Route('POST', '/documents', new UploadDocument($policy, $receiver))])),
        new ErrorResponseRegistry([
            InvalidRequest::class => new Response(400, $headers, 'invalid request'),
            InvalidMetadata::class => new Response(422, $headers, 'invalid value'),
            RequestBodyTooLarge::class => new Response(413, $headers, 'too large'),
            UnsupportedMediaType::class => new Response(415, $headers, 'unsupported media'),
            CapacityUnavailable::class => new Response(503, $headers, 'unavailable'),
            AuthenticationRequired::class => new Response(401, $headers + ['WWW-Authenticate' => 'Bearer'], 'unauthorized'),
            UploadDenied::class => new Response(403, $headers, 'forbidden'),
        ]),
    ), new UnknownFailureBoundary(), CorrelationId::generate(), $sink, [new QuerySummarySource('documents', $budget, $trace)]);
}
