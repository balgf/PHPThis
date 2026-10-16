<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

final class RejectedPng extends \RuntimeException {}
final class RejectedMp4 extends \RuntimeException {}
final class RejectedJpeg extends \RuntimeException {}
final class CatalogUnavailable extends \RuntimeException {}
final class CatalogBusy extends \RuntimeException {}
final class CatalogNotFound extends \RuntimeException {}
final class CatalogUnauthenticated extends \RuntimeException {}
final class CatalogForbidden extends \RuntimeException {}
