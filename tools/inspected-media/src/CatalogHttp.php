<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

use PHPThis\Application;
use PHPThis\Http\ErrorResponseRegistry;
use PHPThis\Http\InvalidRequest;
use PHPThis\Http\MultipartLimits;
use PHPThis\Http\RequestBodyTooLarge;
use PHPThis\Http\RequestBoundary;
use PHPThis\Http\RequestReader;
use PHPThis\Http\Response;
use PHPThis\Http\UnsupportedMediaType;
use PHPThis\Routing\Route;
use PHPThis\Routing\Router;

final readonly class CatalogHttp
{
    public function __construct(private HttpConfiguration $configuration, private CatalogAuthenticator $authentication,
        private AuthorizeCatalogUpload $upload, private AuthorizeCatalogDownload $download, private CatalogLock $lock) {}

    /** @param array<array-key, mixed> $server
     * @param array<array-key, mixed> $query
     */
    public function handle(array $server, array $query): Response
    {
        $clock = new SystemCatalogClock();
        $application = new Application(new Router([
            new Route('POST', '/catalog-images', new UploadCatalogImage($this->configuration->root, $this->lock, $clock, $this->authentication, $this->upload, CatalogImageFormat::Png)),
            new Route('GET', '/catalog-images/{image_id:token}', new DownloadCatalogImage($this->configuration->root, $this->lock, $clock, $this->authentication, $this->download, CatalogImageFormat::Png)),
            new Route('POST', '/catalog-jpeg-images', new UploadCatalogImage($this->configuration->root, $this->lock, $clock, $this->authentication, $this->upload, CatalogImageFormat::Jpeg)),
            new Route('GET', '/catalog-jpeg-images/{image_id:token}', new DownloadCatalogImage($this->configuration->root, $this->lock, $clock, $this->authentication, $this->download, CatalogImageFormat::Jpeg)),
            new Route('POST', '/catalog-video-clips', new UploadCatalogImage($this->configuration->root, $this->lock, $clock, $this->authentication, $this->upload, CatalogImageFormat::Mp4)),
            new Route('GET', '/catalog-video-clips/{image_id:token}', new DownloadCatalogImage($this->configuration->root, $this->lock, $clock, $this->authentication, $this->download, CatalogImageFormat::Mp4)),
            new Route('POST', '/catalog-audio-clips', new UploadCatalogImage($this->configuration->root, $this->lock, $clock, $this->authentication, $this->upload, CatalogImageFormat::Wav)),
            new Route('GET', '/catalog-audio-clips/{image_id:token}', new DownloadCatalogImage($this->configuration->root, $this->lock, $clock, $this->authentication, $this->download, CatalogImageFormat::Wav)),
        ]));
        $errors = new ErrorResponseRegistry([
            CatalogUnauthenticated::class => new Response(401, ['WWW-Authenticate' => 'Bearer', 'Cache-Control' => 'private, no-store'], "Unauthorized\n"),
            CatalogForbidden::class => self::failure(403), CatalogNotFound::class => self::failure(404),
            CatalogBusy::class => self::failure(503), CatalogUnavailable::class => self::failure(503),
            InvalidRequest::class => self::failure(400), RequestBodyTooLarge::class => self::failure(413),
            UnsupportedMediaType::class => self::failure(415),
        ]);
        $boundary = new RequestBoundary(new RequestReader(8192, 'php://input', new MultipartLimits(2_097_152, 1_048_576, 1_048_576)), $application, $errors);
        try { return $boundary->handle($server, $query); }
        catch (\Throwable) { return self::failure(500); }
    }

    private static function failure(int $status): Response
    {
        return new Response($status, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'private, no-store'], "Request failed\n");
    }
}
