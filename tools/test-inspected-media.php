<?php

declare(strict_types=1);

require __DIR__ . '/inspected-media/bootstrap.php';
require __DIR__ . '/inspected-media/tests/parser.php';
require __DIR__ . '/inspected-media/tests/jpeg-parser.php';
require __DIR__ . '/inspected-media/tests/lifecycle.php';
require __DIR__ . '/inspected-media/tests/format-migration.php';
require __DIR__ . '/inspected-media/tests/contracts.php';

verifyPngParser();
verifyJpegParser();
verifyCatalogLifecycle();
verifyCatalogLifecycle(CatalogImageRecipe\CatalogImageFormat::Jpeg);
verifyCatalogFormatMigration();
verifyJpegPromotionFailure();
verifyCatalogContracts();
fwrite(STDOUT, "PASS inspected PNG/JPEG parsers, catalog lifecycle and format migration (local reference)\n");
