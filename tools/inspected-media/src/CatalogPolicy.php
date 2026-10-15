<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

use PHPThis\Http\Request;

interface CatalogAuthenticator { public function authenticate(Request $request): CatalogPrincipal; }
interface AuthorizeCatalogUpload { public function authorizeUpload(CatalogPrincipal $principal): void; }
interface AuthorizeCatalogDownload { public function authorizeDownload(CatalogPrincipal $principal, ImageId $image): void; }

final class DenyCatalogAccess implements CatalogAuthenticator, AuthorizeCatalogUpload, AuthorizeCatalogDownload
{
    public function authenticate(Request $request): CatalogPrincipal { throw new CatalogUnauthenticated('Authentication required.'); }
    public function authorizeUpload(CatalogPrincipal $principal): void { throw new CatalogForbidden('Action forbidden.'); }
    public function authorizeDownload(CatalogPrincipal $principal, ImageId $image): void { throw new CatalogForbidden('Action forbidden.'); }
}
