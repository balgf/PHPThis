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

final readonly class DownloadCatalogImage implements RequestHandler
{
    public function __construct(
        private CatalogRoot $root, private CatalogLock $lock, private CatalogClock $clock,
        private CatalogAuthenticator $authenticator, private AuthorizeCatalogDownload $authorizer,
        private CatalogImageFormat $format = CatalogImageFormat::Png,
    ) {}

    public function handle(Request $request): Response
    {
        $principal = $this->authenticator->authenticate($request);
        $id = new ImageId($request->pathParameters->token('image_id'));
        $this->authorizer->authorizeDownload($principal, $id);
        if ($request->query !== []) { throw new InvalidRequest('Unexpected query.'); }
        $this->lock->acquire(false);
        $database = Connection::connect('sqlite:' . $this->root->path . '/catalog.sqlite', new QueryBudget(8), new QueryTrace(8), options: [\PDO::ATTR_TIMEOUT => 1]);
        $body = (new CatalogImages($database, $this->root, $this->lock, $this->clock))->read($id, $principal, $this->format);
        return new Response(200, [
            'Content-Type' => $this->format === CatalogImageFormat::Png ? 'image/png' : 'image/jpeg',
            'Content-Disposition' => $this->format === CatalogImageFormat::Png
                ? 'attachment; filename="catalog-image.png"' : 'attachment; filename="catalog-image.jpg"',
            'Content-Length' => (string) $body->bytes, 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff', 'Accept-Ranges' => 'none',
        ], '', fileBody: $body);
    }
}
