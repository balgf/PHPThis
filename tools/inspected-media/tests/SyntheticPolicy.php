<?php

declare(strict_types=1);

namespace CatalogImageRecipe\Tests;

use CatalogImageRecipe\AuthorizeCatalogDownload;
use CatalogImageRecipe\AuthorizeCatalogUpload;
use CatalogImageRecipe\CatalogAuthenticator;
use CatalogImageRecipe\CatalogForbidden;
use CatalogImageRecipe\CatalogPrincipal;
use CatalogImageRecipe\CatalogUnauthenticated;
use CatalogImageRecipe\ImageId;
use PHPThis\Http\Request;

// I/O-free fixture credentials only; never loaded by public/index.php.
final class SyntheticPolicy implements CatalogAuthenticator, AuthorizeCatalogUpload, AuthorizeCatalogDownload
{
    /** @var list<string> */
    private array $calls = [];

    /** @return list<string> */
    public function takeCalls(): array
    {
        $calls = $this->calls;
        $this->calls = [];
        return $calls;
    }
    public function authenticate(Request $request): CatalogPrincipal
    {
        $this->calls[] = 'authenticate';
        return match ($request->headers['authorization'] ?? '') {
            'Bearer fixture-owner-one' => new CatalogPrincipal(1),
            'Bearer fixture-owner-two' => new CatalogPrincipal(2),
            'Bearer fixture-forbidden' => new CatalogPrincipal(3),
            'Bearer fixture-outage' => throw new \RuntimeException('Synthetic private failure.'),
            default => throw new CatalogUnauthenticated('No fixture authority.'),
        };
    }
    public function authorizeUpload(CatalogPrincipal $principal): void
    {
        $this->calls[] = 'authorize_upload';
        if ($principal->id === 3) { throw new CatalogForbidden('Synthetic denial.'); }
    }
    public function authorizeDownload(CatalogPrincipal $principal, ImageId $image): void
    {
        $this->calls[] = 'authorize_download';
        if ($principal->id === 3) { throw new CatalogForbidden('Synthetic denial.'); }
    }
}
