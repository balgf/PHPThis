<?php

declare(strict_types=1);

namespace CatalogImageRecipe;

final class ReferenceEnvironment
{
    public static function http(): HttpConfiguration
    {
        return HttpConfiguration::fromInput(\getenv('PHPTHIS_PNG_HTTP_ROOT'));
    }

    public static function worker(): WorkerConfiguration
    {
        return WorkerConfiguration::fromInputs(\getenv('PHPTHIS_PNG_WORKER_ROOT'), \getenv('PHPTHIS_PNG_WORKER_IMAGE'));
    }
}
