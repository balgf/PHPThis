<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

use PHPThis\Database\Connection;
use PHPThis\Database\QueryBudget;
use PHPThis\Database\QueryTrace;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\Request;
use PHPThis\Http\RequestHandler;
use PHPThis\Http\Response;

final readonly class UploadCatalogImage implements RequestHandler
{
    public function __construct(
        private CatalogRoot $root, private CatalogLock $lock, private CatalogClock $clock,
        private CatalogAuthenticator $authenticator, private AuthorizeCatalogUpload $authorizer,
    ) {}

    public function handle(Request $request): Response
    {
        $principal = $this->authenticator->authenticate($request);
        $this->authorizer->authorizeUpload($principal);
        // Tenant and cookie/CSRF authority are deliberately absent in this machine reference.
        if ($request->query !== []) { throw new InvalidRequest('Unexpected query.'); }
        $this->lock->acquire(true);
        $pending = PendingImageUpload::fromRequest($request);
        $database = Connection::connect('sqlite:' . $this->root->path . '/catalog.sqlite', new QueryBudget(8), new QueryTrace(8), options: [\PDO::ATTR_TIMEOUT => 1]);
        $id = (new CatalogImages($database, $this->root, $this->lock, $this->clock))->retain($pending, $principal);
        return new Response(202, ['Content-Type' => 'application/json', 'Cache-Control' => 'private, no-store'],
            json_encode(['data' => ['image_id' => $id->value]], JSON_THROW_ON_ERROR) . "\n");
    }
}
