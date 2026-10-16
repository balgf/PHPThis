<?php

declare(strict_types=1);

require __DIR__ . '/inspected-media/bootstrap.php';
require __DIR__ . '/inspected-media/tests/parser.php';
require __DIR__ . '/inspected-media/tests/jpeg-parser.php';
require __DIR__ . '/inspected-media/tests/mp4-parser.php';
require __DIR__ . '/inspected-media/tests/wav-parser.php';
require __DIR__ . '/inspected-media/tests/lifecycle.php';
require __DIR__ . '/inspected-media/tests/format-migration.php';
require __DIR__ . '/inspected-media/tests/mp4-migration.php';
require __DIR__ . '/inspected-media/tests/wav-migration.php';
require __DIR__ . '/inspected-media/tests/contracts.php';

verifyPngParser();
verifyJpegParser();
verifyMp4Parser();
verifyWavParser();
verifyCatalogLifecycle();
verifyCatalogLifecycle(CatalogImageRecipe\CatalogImageFormat::Jpeg);
verifyCatalogLifecycle(CatalogImageRecipe\CatalogImageFormat::Mp4);
verifyCatalogLifecycle(CatalogImageRecipe\CatalogImageFormat::Wav);
verifyCatalogFormatMigration();
verifyCatalogMp4Migration();
verifyCatalogWavMigration();
verifyCatalogPromotionFailure(CatalogImageRecipe\CatalogImageFormat::Jpeg);
verifyCatalogPromotionFailure(CatalogImageRecipe\CatalogImageFormat::Mp4);
verifyCatalogPromotionFailure(CatalogImageRecipe\CatalogImageFormat::Wav);
verifyCatalogContracts();
fwrite(STDOUT, "PASS inspected PNG/JPEG/MP4/WAV parsers, catalog lifecycle and format migration (local reference)\n");
