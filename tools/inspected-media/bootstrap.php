<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/autoload.php';
require_once __DIR__ . '/src/Failures.php';
require_once __DIR__ . '/src/PngHeader.php';
require_once __DIR__ . '/src/BoundedPng.php';
require_once __DIR__ . '/src/BoundedJpeg.php';
require_once __DIR__ . '/src/Mp4Structure.php';
require_once __DIR__ . '/src/BoundedMp4.php';
require_once __DIR__ . '/src/CatalogImageFormat.php';
require_once __DIR__ . '/src/ImageId.php';
require_once __DIR__ . '/src/CatalogPrincipal.php';
require_once __DIR__ . '/src/CatalogClock.php';
require_once __DIR__ . '/src/ImageRecord.php';
require_once __DIR__ . '/src/CatalogSettings.php';
require_once __DIR__ . '/src/CatalogRoot.php';
require_once __DIR__ . '/src/CatalogLock.php';
require_once __DIR__ . '/src/PendingImageUpload.php';
require_once __DIR__ . '/src/InspectionDefinition.php';
require_once __DIR__ . '/src/CatalogInspector.php';
require_once __DIR__ . '/src/DockerCatalogInspector.php';
require_once __DIR__ . '/src/Configuration.php';
require_once __DIR__ . '/src/ReferenceEnvironment.php';
require_once __DIR__ . '/src/CatalogImages.php';
require_once __DIR__ . '/src/CatalogPolicy.php';
require_once __DIR__ . '/src/UploadCatalogImage.php';
require_once __DIR__ . '/src/DownloadCatalogImage.php';
require_once __DIR__ . '/src/CatalogHttp.php';
